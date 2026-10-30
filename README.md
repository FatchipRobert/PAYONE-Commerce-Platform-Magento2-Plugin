# PAYONE COMMERCE PLATFORM FOR YOUR MAGENTO 2 SHOP
The PAYONE Commerce Platform extension connects your Magento 2 shop to the [PAYONE Commerce Platform](https://www.payone.com) API. Payments are created as commerce cases including the shopping cart, and all follow-up operations such as capture, refund and cancellation are handled directly from the Magento Admin Panel.

## Payment methods
*	Credit card (Visa, Mastercard, American Express, …) via PAYONE Hosted Tokenization, optionally with Click to Pay
*	SEPA Direct Debit
*	PayPal
*	Google Pay
*	Apple Pay
*	PAYONE Secured Invoice and PAYONE Secured Direct Debit (Buy Now Pay Later, Germany and Austria, EUR) – must be activated for your PAYONE merchant account

## Important functions for Magento 2
*	Seamless integration of Magento's OnePage Checkout
*	Central configuration on the Magento Admin Panel, configurable per website and store view
*	Authorization with later capture or automatic capture (sale), configurable per payment method
*	Partial and multiple captures for partial deliveries
*	Full and partial refunds via credit memos
*	Void and cancellation of open authorizations directly from the order
*	Shopping cart transmission with line items, shipping costs and discounts
*	Card data is entered in PAYONE's hosted tokenization fields and never touches the shop
*	Status updates via webhooks, secured by HMAC signature check and an optional IP whitelist, processed asynchronously via the Magento message queue
*	Protocol of all API calls and webhooks in the Admin Panel, with sensitive data such as IBAN and tokens masked

## Requirements
*	Magento 2.4.4 or higher
*	PHP 8.1, 8.2, 8.3 or 8.4
*	A PAYONE Commerce Platform merchant account with API key and API secret
*	Magento cron (`bin/magento cron:run`) running every minute – it starts the message queue consumer that processes the webhooks

## Installation
*	Go to your installation directory of Magento 2 and perform the following commands
*	`composer require payone-gmbh/commerce-platform-magento-2`
*	`php bin/magento setup:upgrade`
*	`php bin/magento setup:di:compile`
*	`php bin/magento cache:clean`

## Configuration
*	**General settings:** *Stores → Configuration → Payone Commerce Platform → General* (or *PAYONE → Configuration → General* in the admin menu). Enter your Merchant ID, API Key and API Secret and choose the API environment (test or live).
*	**Payment methods:** *Stores → Configuration → Payone Commerce Platform → Payment* – activate and configure each payment method.
*	**PAYONE Secured Invoice / Secured Direct Debit:** only offered for billing addresses in Germany and Austria and orders in EUR; billing and shipping address must be identical (otherwise the checkout shows a hint and the order cannot be placed). The customer enters the date of birth (and the phone number if the address has none) in the checkout, for Secured Direct Debit also account holder and IBAN; a device fingerprint script of PAYONE's risk partner Payla (`d.payla.io`) is loaded when the method is selected. Secured Direct Debit is not available for companies (billing address with company). For Secured Invoice, the bank account, due date and payment reference for the customer's transfer are returned by PAYONE when the payment is captured and are shown in the payment information of the order, in the invoice email and on the invoice PDF.
*	**Apple Pay:** requires your own Apple Developer account (see the [PAYONE Apple Pay setup guide](https://docs.commerce.payone.com/docs/payment-methods/apple-pay/apple-setup)):
	1. Create a Merchant ID and an Apple Pay Merchant Identity Certificate in the Apple Developer account. Enter the Merchant ID and upload the certificate file (e.g. `merchant_id.cer`, DER or PEM) and its private key file (PEM, with password if encrypted) in the Apple Pay settings – the shop uses them for the merchant validation of the Apple Pay session. The files are stored encrypted in the configuration.
	2. Register the domain of the shop for the Merchant ID. Apple's domain association file must be reachable at `https://<your-shop-domain>/.well-known/apple-developer-merchantid-domain-association.txt` (place it in the web server's document root).
	3. Create the Apple Pay Payment Processing Certificate with the certificate signing request you receive from PAYONE customer service – PAYONE decrypts the payment data with it.

	Apple Pay is offered in Safari and, through Apple's Apple Pay JS SDK, also in other browsers (payment via QR code with an iPhone). The customer confirms the payment in the Apple Pay sheet, then the order is placed with the addresses of the checkout; a 3-D Secure redirect may follow.
*	**Webhooks:** Configure the following webhook URL for your merchant account in the PAYONE Commerce Platform:
	`https://<your-shop-domain>/payone_cp/webhook`
	Webhooks are only accepted with a valid signature of your API key. Additionally, the IP addresses PAYONE sends webhooks from can be restricted with the setting *Valid PAYONE IPs*.
*	**Webhook processing:** see the next section.
*	**Logs:** API calls and received webhooks can be found under *PAYONE → Logs → Api* and *PAYONE → Logs → Webhook*.

## Webhook processing (message queue)
Incoming webhooks are only checked, stored and confirmed to PAYONE right away. The actual processing (authorizing or cancelling the order, creating the invoice and sending the invoice email) is done by the message queue consumer `payone_cp.webhook.process`. This way PAYONE always gets a fast answer and does not resend webhooks because of slow processing.

*	**Default setup:** Nothing to do as long as the Magento cron is running. The cron job `consumers_runner` starts the consumer automatically. With the default setting `queue/consumers_wait_for_messages = 1` the consumer then keeps running in the background and processes new webhooks within seconds.
*	**Restricted consumers:** If the cron consumer runner is disabled (`cron_consumers_runner/cron_run = false` in `app/etc/env.php`) or limited to a list of consumers (`cron_consumers_runner/consumers`), add `payone_cp.webhook.process` to that list or run the consumer yourself, e.g. with supervisor or systemd:
	`php bin/magento queue:consumers:start payone_cp.webhook.process`
	Otherwise the webhooks are stored but not processed, and orders stay in *Pending Payment*.
*	**After a module update:** A running consumer keeps the old code until it is restarted: `php bin/magento queue:consumers:restart`
*	**Failed processing:** The webhook stays marked as unprocessed in the webhook log and is put back into the queue by the cron job `payone_cp_webhook_retry` every 5 minutes, up to 5 attempts. Errors are written to `var/log/system.log` ("PAYONE CP webhook processing failed").
*	The message queue uses the database (MySQL) by default; RabbitMQ is used automatically if it is configured as the default queue connection.

## More information
*	For the PAYONE Commerce Platform documentation see: https://docs.payone.com

## Contact
PAYONE GmbH<br>
Lyoner Straße 15<br>
60528 Frankfurt am Main, Germany<br>
Phone +49 431 25968-400<br>
tech.support@payone.com<br>

## License
This extension is released under the MIT License, see [LICENSE](LICENSE).

## About PAYONE
PAYONE GmbH is headquartered in Frankfurt am Main and is one of the leading omnichannel payment providers in Europe. In addition to providing customer support to numerous Sparkasse banks, the full-service payment service provider also provides cashless payment transactions services to more than 255,000 customers from various branches – whether that be in stationary retail or when completing e-commerce and mobile payment transactions.
