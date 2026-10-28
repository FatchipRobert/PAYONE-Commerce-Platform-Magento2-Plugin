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

namespace Payone\CommercePlatform\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Payone\CommercePlatform\Model\Webhook\SignatureValidator;

class Index extends Action implements CsrfAwareActionInterface
{
    /**
     * Result factory for file-download
     *
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage
     */
    protected $webhookMessage;

    /**
     * @var \Payone\CommercePlatform\Helper\Json
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Payone\CommercePlatform\Helper\Environment
     */
    protected $environmentHelper;

    /**
     * @var \Payone\CommercePlatform\Model\Webhook\SignatureValidator
     */
    protected $signatureValidator;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context                       $context
     * @param \Magento\Framework\Controller\Result\RawFactory             $resultRawFactory
     * @param \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage $webhookMessage
     * @param \Payone\CommercePlatform\Helper\Json                        $jsonHelper
     * @param \Magento\Framework\Event\ManagerInterface                   $eventManager
     * @param \Payone\CommercePlatform\Helper\Environment                 $environmentHelper
     * @param \Payone\CommercePlatform\Model\Webhook\SignatureValidator   $signatureValidator
     * @param \Psr\Log\LoggerInterface                                    $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Payone\CommercePlatform\Model\ResourceModel\WebhookMessage $webhookMessage,
        \Payone\CommercePlatform\Helper\Json $jsonHelper,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Payone\CommercePlatform\Helper\Environment $environmentHelper,
        \Payone\CommercePlatform\Model\Webhook\SignatureValidator $signatureValidator,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->webhookMessage = $webhookMessage;
        $this->jsonHelper = $jsonHelper;
        $this->eventManager = $eventManager;
        $this->environmentHelper = $environmentHelper;
        $this->signatureValidator = $signatureValidator;
        $this->logger = $logger;
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
     * Return request parameter value
     *
     * @param  string $key
     * @return string
     */
    protected function getParam($key)
    {
        return $this->getRequest()->getParam($key, '');
    }

    /**
     * Return Post array
     *
     * @return array
     */
    protected function getPostArray()
    {
        return $this->getRequest()->getPostValue();
    }

    /**
     * Returns the raw request body - the signature is calculated over the unmodified body
     *
     * @return string
     */
    protected function getRawBody()
    {
        return (string)$this->getRequest()->getContent();
    }

    /**
     * Creates the plain text response
     *
     * @param  int    $httpCode
     * @param  string $output
     * @return \Magento\Framework\Controller\Result\Raw
     */
    protected function getResult($httpCode, $output)
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
     * @param  string $reason
     * @return void
     */
    protected function logRejected($reason)
    {
        $this->logger->warning('PAYONE CP webhook rejected: '.$reason, [
            'ip' => $this->environmentHelper->getRemoteIp(),
            'keyId' => (string)$this->getRequest()->getHeader(SignatureValidator::HEADER_KEY_ID),
        ]);
    }

    /**
     * Executing webhook handling
     *
     * Order of checks follows the PAYONE documentation: verify first, then parse, then execute business logic
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        if (!$this->environmentHelper->isRemoteIpValid()) {
            $this->logRejected('IP not allowed');
            return $this->getResult(403, 'Access denied');
        }

        $rawBody = $this->getRawBody();
        if (!$this->signatureValidator->isValid($this->getRequest(), $rawBody)) {
            $this->logRejected('invalid signature');
            return $this->getResult(401, 'Invalid signature');
        }

        $webhookMessage = $this->jsonHelper->jsonDecode($rawBody);
        if (!is_array($webhookMessage) || empty($webhookMessage['type']) || empty($webhookMessage['id'])) {
            $this->logRejected('invalid message');
            return $this->getResult(400, 'Invalid message');
        }

        // Webhooks can be delivered multiple times (retries) - the event id is the idempotency key
        if ($this->webhookMessage->isEventProcessed($webhookMessage['id'])) {
            return $this->getResult(200, 'OK');
        }

        $entityId = $this->webhookMessage->addWebhookMessage($webhookMessage);

        try {
            $params = [
                'order' => $this->webhookMessage->getOrderByMessage($webhookMessage),
                'webhook_message' => $webhookMessage,
            ];
            $this->eventManager->dispatch('payone_cp_webhook_all', $params);
            $this->eventManager->dispatch('payone_cp_webhook_'.strtolower(str_replace('.', '_', $webhookMessage['type'])), $params);
        } catch (\Exception $e) {
            // Non-2xx response makes PAYONE retry the webhook later
            $this->logger->error('PAYONE CP webhook processing failed: '.$e->getMessage(), ['eventId' => $webhookMessage['id']]);
            return $this->getResult(500, 'Processing failed');
        }

        $this->webhookMessage->markProcessed($entityId);
        return $this->getResult(200, 'OK');
    }
}
