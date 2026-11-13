# PAYONE COMMERCE PLATFORM FOR YOUR MAGENTO 2 SHOP
The PAYONE Commerce Platform extension connects your Magento 2 shop to the [PAYONE Commerce Platform](https://www.payone.com) API. Payments are created as commerce cases including the shopping cart, and all follow-up operations such as capture, refund and cancellation are handled directly from the Magento Admin Panel.

## Requirements
*	Magento 2.4.4 or higher
*	PHP 8.1, 8.2, 8.3, 8.4 or 8.5
*	A PAYONE Commerce Platform merchant account with API key and API secret
*	Magento cron (`bin/magento cron:run`) running every minute – it starts the message queue consumer that processes the webhooks

## Installation
The installation consists of four steps. **Steps 2 and 4 are required** – without the Magento cron and the webhook in the PAYONE Commerce Portal, payments are not completed in Magento.

### 1. Install the extension
The extension is installed with Composer. Go to the installation directory of Magento 2 and run:

```
composer require <packagist-package-name>
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy   # production mode only
php bin/magento cache:flush
```

**Update:** `composer update <packagist-package-name>`, then the same commands as above and `php bin/magento queue:consumers:restart` (see [Webhook processing](#webhook-processing-message-queue)).

### 2. Set up the Magento cron (required)
The extension needs the Magento cron running every minute. If it is not set up yet, install it as the user that owns the Magento files:

```
php bin/magento cron:install
```

Check it with `crontab -l` (see also [Configure and run cron](https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs) in the Adobe Commerce documentation). The cron starts the message queue consumer that processes the webhooks of PAYONE, retries failed webhooks, checks open Pay in Store orders and cleans up the logs. **Without the cron, orders paid by credit card, PayPal, Wero, Google Pay or Apple Pay stay in *Pending Payment* and no invoices are created for automatic captures.**

### 3. Enter the API credentials
Create an API key and API secret in the PAYONE Commerce Portal under *Configuration → API Authentication*. Enter them together with your Merchant ID in Magento under *Stores → Configuration → Payone Commerce Platform → General* and choose the API environment (test or live). Then activate the payment methods (see [Configuration](#configuration)).

### 4. Register the webhook in the PAYONE Commerce Portal (required)
PAYONE reports the result of payments (e.g. after a 3-D Secure or PayPal redirect) and captures to the shop via webhooks. The webhook URL must be registered in the PAYONE Commerce Portal – separately for the test and the live environment:

1. Log in to the PAYONE Commerce Portal – live: https://portal.commerce.payone.com/, test: https://portal.preprod.commerce.payone.com/ – and open *Configuration → Webhooks*.
2. Click *Add new webhook*.
3. Enter the webhook URL of your shop in the *URL* field: the base URL of the shop followed by `payone_cp/webhook`, e.g. `https://www.example.com/payone_cp/webhook` (if your shop URL contains `/pub/`, the webhook URL does as well).
4. Select **all** event types: *Payments*, *Refund*, *Payout*, *Checkout*, *Commerce Case*, *Payment Execution* and *Payment Information*.
5. Click *Submit*.

One webhook URL per Magento installation is enough, also with several websites or store views. Webhooks are only accepted with a valid signature and from PAYONE's IP addresses (see [Configuration](#configuration)).

## Payment methods
*	Credit card (Visa, Mastercard, American Express, …) via PAYONE Hosted Tokenization
*	SEPA Direct Debit
*	PayPal
*	Google Pay
*	Apple Pay
*	Wero (Germany and Belgium, direct sale)
*	PAYONE Secured Invoice and PAYONE Secured Direct Debit (Buy Now Pay Later, Germany and Austria, EUR) – must be activated for your PAYONE merchant account
*	Pay in Store – payment when the customer picks up the order in a store (only with Magento In-Store Pickup, see [Pay in Store setup](#pay-in-store-setup))

## Important functions for Magento 2
*	Seamless integration of Magento's OnePage Checkout
*	Central configuration on the Magento Admin Panel, configurable per website and store view
*	Authorization with later capture or automatic capture (sale), configurable per payment method
*	Partial and multiple captures for partial deliveries – capture partial amounts from Magento (invoice with the delivered items). A capture made in the PAYONE portal is recorded in Magento as an invoice over the full order total.
*	Full and partial refunds via credit memos
*	Void and cancellation of open authorizations directly from the order
*	Shopping cart transmission with line items, shipping costs and discounts
*	Card data is entered in PAYONE's hosted tokenization fields and never touches the shop
*	Status updates via webhooks, secured by HMAC signature check and an optional IP whitelist, processed asynchronously via the Magento message queue
*	Protocol of all API calls and webhooks in the Admin Panel, with sensitive data such as IBAN and tokens masked

## Operation alongside the PAYONE module for Magento 2
This module can be installed in the same shop as the previous PAYONE module (`payone-gmbh/magento-2`, module `Payone_Core`) with version X.Y.Z or higher. Older versions of that module change the order status of the PAYONE Commerce Platform payment methods when an order is placed, so orders paid with a redirect payment method (credit card, PayPal, Wero, Google Pay, Apple Pay) are not completed.

If the address check or credit rating of the previous module is active, it filters all payment methods of the checkout by the customer's score, including the PAYONE Commerce Platform payment methods. For a yellow or red score, only the payment methods selected in that module's settings *PROTECT → Credit rating → Allowed Payment-Methods for Result "YELLOW"* / *"RED"* are offered – add the PAYONE Commerce Platform payment methods there if they should remain available.

## Configuration
*	**General settings:** *Stores → Configuration → Payone Commerce Platform → General* (or *PAYONE → Configuration → General* in the admin menu). Enter your Merchant ID, API Key and API Secret and choose the API environment (test or live).
*	**Payment methods:** *Stores → Configuration → Payone Commerce Platform → Payment* – activate and configure each payment method.
*	**PAYONE Secured Invoice / Secured Direct Debit:** only offered for billing addresses in Germany and Austria and orders in EUR; billing and shipping address must be identical (otherwise the checkout shows a hint and the order cannot be placed). The customer enters the date of birth (and the phone number if the address has none) in the checkout, for Secured Direct Debit also account holder and IBAN; a device fingerprint script of PAYONE's risk partner Payla (`d.payla.io`) is loaded when the method is selected. Secured Direct Debit is not available for companies (billing address with company). For Secured Invoice, the bank account, due date and payment reference for the customer's transfer are returned by PAYONE when the payment is captured and are shown in the payment information of the order, in the invoice email and on the invoice PDF.
*	**Apple Pay:** requires your own Apple Developer account (see the [PAYONE Apple Pay setup guide](https://docs.commerce.payone.com/docs/payment-methods/apple-pay/apple-setup)):
	1. Create a Merchant ID and an Apple Pay Merchant Identity Certificate in the Apple Developer account. Enter the Merchant ID and upload the certificate file (e.g. `merchant_id.cer`, DER or PEM) and its private key file (PEM, with password if encrypted) in the Apple Pay settings – the shop uses them for the merchant validation of the Apple Pay session. The files are stored encrypted in the configuration.
	2. Register the domain of the shop for the Merchant ID. Apple's domain association file must be reachable at `https://<your-shop-domain>/.well-known/apple-developer-merchantid-domain-association.txt` (place it in the web server's document root).
	3. Create the Apple Pay Payment Processing Certificate with the certificate signing request you receive from PAYONE customer service – PAYONE decrypts the payment data with it.

	Apple Pay is offered in Safari and, through Apple's Apple Pay JS SDK, also in other browsers (payment via QR code with an iPhone). The customer confirms the payment in the Apple Pay sheet, then the order is placed with the addresses of the checkout; a 3-D Secure redirect may follow.
*	**Webhooks:** Register the webhook URL `https://<your-shop-domain>/payone_cp/webhook` in the PAYONE Commerce Portal (see [Installation](#4-register-the-webhook-in-the-payone-commerce-portal-required)).
	Webhooks are only accepted with a valid signature of your API key. Additionally, only the IP addresses PAYONE sends webhooks from are accepted (setting *General → IP addresses → Valid PAYONE IPs*, prefilled with PAYONE's addresses). We recommend keeping this list active; an empty list disables the check.
*	**Shop behind a proxy or load balancer:** enter the IP addresses of your own proxies / load balancers (e.g. Varnish, SSL terminator, CDN) in *General → IP addresses → Trusted proxies*. For requests from these addresses the client IP is taken from the `X-Forwarded-For` header – otherwise the IP of the connection is used. This IP is checked against *Valid PAYONE IPs* and sent to PAYONE with the payment (*Transmit IP address*, always for Secured Invoice / Secured Direct Debit). Without this setting every webhook is rejected with the IP of the proxy, and PAYONE gets the proxy's IP instead of the customer's (which weakens the risk check of Secured Invoice / Secured Direct Debit). If a webhook from a PAYONE IP arrives through a proxy that is not configured, the extension writes a hint with the proxy's IP to the log (`var/log/system.log`) – only enter it if it really is your own proxy.
*	**Pay in Store:** see [Pay in Store setup](#pay-in-store-setup).
*	**Webhook processing:** see the next section.
*	**Logs:** API calls and received webhooks can be found under *PAYONE → Logs → Api* and *PAYONE → Logs → Webhook*. They contain customer data and are deleted by the cron job `payone_cp_clean_logs` after the number of days set in *General → Logs* (default 180, minimum 30, 0 = keep forever).

## Pay in Store setup
With Pay in Store the customer orders online, picks up the order in a store and pays there at the till. PAYONE handles no online payment: when the order is placed, the extension creates a commerce case with a checkout (shopping cart) at PAYONE, the till books the payment on this checkout.

**The method is only offered with Magento In-Store Pickup.** The extension hides Pay in Store unless the shipping method of the cart is In-Store Pickup (`instore_pickup`, "In-Store Delivery" of Magento's inventory modules). Carts without shipping (virtual products only) never get the method. This also applies when the payment method is set via REST or GraphQL. There is no setting for it, so In-Store Pickup must be set up in Magento:

1. **Inventory modules:** Magento's In-Store Pickup modules (`Magento_InventoryInStorePickup*`) must be enabled. They are part of Magento 2.4 and enabled by default unless MSI was removed or disabled.
2. **Shipping method:** *Stores → Configuration → Sales → Delivery Methods → In-Store Delivery* – set *Enabled* to *Yes*, optionally adjust name and price.
3. **Pickup locations:** *Stores → Inventory → Sources* – edit or create a source per store and set *Use as Pickup Location* to *Yes*. Enter the complete address (country, city, postcode, street), a frontend name and description, and the latitude and longitude.
4. **Stock and products:** The source must be assigned to a stock that is linked to the website, and the products need salable quantity at this source. The checkout only lists pickup locations where the items of the cart are available.
5. **Pickup location search (optional):** Searching pickup locations by postcode or city needs a distance provider: *Stores → Configuration → Catalog → Inventory → Distance Provider for Distance Based SSA*. Use Google (API key required) or the offline provider, which needs the geo data of the countries once, e.g. `php bin/magento inventory-geonames:import de`.
6. **Payment method:** *Stores → Configuration → Payone Commerce Platform → Payment → Pay in Store* – enable it and set the title. *Cancel Unpaid Orders After (Days)* cancels orders without payment after the given number of days (0 = never). The general PAYONE settings (Merchant ID, API Key, API Secret) are required as well.
7. **Cron:** The Magento cron must be running. The cron job `payone_cp_payinstore_status` checks the open Pay in Store orders every 15 minutes.

In the checkout the customer now chooses the *Pick in Store* tab and a pickup location; the payment step then offers Pay in Store, the billing address is entered there.

**Order processing:**
*	The order stays *Pending* after it was placed.
*	As soon as a payment was booked at the till – fully or partially – the cron creates the invoice over the order total and sends the invoice email. The extension does not create shipments, so the order stays *Processing* until the shipment is created in Magento.
*	If the checkout was cancelled in the store, the cron cancels the order.
*	If the order is cancelled in Magento (manually or after the configured days), the checkout at PAYONE is deleted. This only works as long as nothing was paid; otherwise PAYONE rejects it and the order is not cancelled.
*	Partial pickups, items removed at the till and refunds in the store are not transferred to Magento. Refunds are recorded in Magento with an offline credit memo.

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
