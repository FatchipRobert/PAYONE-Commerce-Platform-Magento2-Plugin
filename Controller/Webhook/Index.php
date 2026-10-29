<?php
/**
 * PAYONE Commerce Platform Magento 2 Connector
 *
 * Copyright (c) 2026 PAYONE GmbH
 *
 * This source file is subject to the MIT License that is bundled
 * with this package in the file LICENSE.
 *
 * PHP version 8
 *
 * @category  Payone
 * @package   Payone_CommercePlatform_Magento2_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2026 PAYONE GmbH
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://www.payone.de
 */

declare(strict_types=1);

namespace Payone\CommercePlatform\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Payone\CommercePlatform\Model\RemoteIp;
use Payone\CommercePlatform\Model\ResourceModel\WebhookMessage;
use Payone\CommercePlatform\Model\Webhook\IpValidator;
use Payone\CommercePlatform\Model\Webhook\Processor;
use Payone\CommercePlatform\Model\Webhook\QueueConsumer;
use Payone\CommercePlatform\Model\Webhook\SignatureValidator;
use Psr\Log\LoggerInterface;

/**
 * Receives the PAYONE webhooks (payone_cp/webhook)
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param Http $request
     * @param RawFactory $resultRawFactory
     * @param WebhookMessage $webhookMessage
     * @param Json $json
     * @param PublisherInterface $publisher
     * @param Processor $processor
     * @param IpValidator $ipValidator
     * @param RemoteIp $remoteIp
     * @param SignatureValidator $signatureValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Http $request,
        private readonly RawFactory $resultRawFactory,
        private readonly WebhookMessage $webhookMessage,
        private readonly Json $json,
        private readonly PublisherInterface $publisher,
        private readonly Processor $processor,
        private readonly IpValidator $ipValidator,
        private readonly RemoteIp $remoteIp,
        private readonly SignatureValidator $signatureValidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Executing webhook handling
     *
     * Order of checks follows the PAYONE documentation: verify first, then parse, then execute business logic
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        if (!$this->ipValidator->isValid($this->remoteIp->get())) {
            $this->logRejected('IP not allowed');
            return $this->getResult(403, 'Access denied');
        }

        // The signature is calculated over the unmodified body
        $rawBody = (string)$this->request->getContent();
        if (!$this->signatureValidator->isValid($this->request, $rawBody)) {
            $this->logRejected('invalid signature');
            return $this->getResult(401, 'Invalid signature');
        }

        $webhookMessage = $this->decodeMessage($rawBody);
        if ($webhookMessage === null || empty($webhookMessage['type']) || empty($webhookMessage['id'])) {
            $this->logRejected('invalid message');
            return $this->getResult(400, 'Invalid message');
        }

        // Webhooks can be delivered multiple times (retries) - the event id is the idempotency key
        if ($this->webhookMessage->isEventReceived((string)$webhookMessage['id'])) {
            $this->logTiming('duplicate', $webhookMessage); // TODO: REMOVE temporary retry analysis (TODO.md)
            return $this->getResult(200, 'OK');
        }

        // Accept only - the processing (observers) runs in the queue consumer, so PAYONE gets its answer at once
        $entityId = $this->webhookMessage->addWebhookMessage($webhookMessage);
        try {
            $this->publisher->publish(QueueConsumer::TOPIC, (string)$entityId);
        } catch (\Exception $e) {
            $this->logger->warning('PAYONE CP webhook could not be queued, processing it directly: '.$e->getMessage());
            $this->processor->process($entityId);
        }

        $this->logTiming('accepted', $webhookMessage); // TODO: REMOVE temporary retry analysis (TODO.md)
        return $this->getResult(200, 'OK');
    }

    /**
     * TEMPORARY: logs every accepted webhook with the time since the request started
     *
     * To find out why PAYONE retries some webhooks after ~10 minutes although they were answered with 200 (TODO.md).
     *
     * @todo Remove when the retry analysis is done
     * @param string $result "accepted" or "duplicate"
     * @param array $webhookMessage
     * @return void
     */
    private function logTiming(string $result, array $webhookMessage): void
    {
        $requestStart = (float)$this->request->getServer('REQUEST_TIME_FLOAT');
        $this->logger->info('PAYONE CP webhook '.$result, [
            'eventId' => $webhookMessage['id'],
            'type' => $webhookMessage['type'],
            'ip' => $this->remoteIp->get(),
            'durationMs' => $requestStart > 0 ? (int)round((microtime(true) - $requestStart) * 1000) : null,
        ]);
    }

    /**
     * Decodes the JSON body, null if it is not a JSON object
     *
     * @param string $rawBody
     * @return array|null
     */
    private function decodeMessage(string $rawBody): ?array
    {
        try {
            $decoded = $this->json->unserialize($rawBody);
        } catch (\InvalidArgumentException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Creates the plain text response
     *
     * @param int $httpCode
     * @param string $output
     * @return Raw
     */
    private function getResult(int $httpCode, string $output): Raw
    {
        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setHttpResponseCode($httpCode);
        $resultRaw->setHeader('Content-Type', 'text/plain', true);
        $resultRaw->setContents($output);
        return $resultRaw;
    }

    /**
     * Logs a rejected webhook with minimal metadata - never the body or the secret
     *
     * @param string $reason
     * @return void
     */
    private function logRejected(string $reason): void
    {
        $this->logger->warning('PAYONE CP webhook rejected: '.$reason, [
            'ip' => $this->remoteIp->get(),
            'keyId' => (string)$this->request->getHeader(SignatureValidator::HEADER_KEY_ID),
        ]);
    }
}
