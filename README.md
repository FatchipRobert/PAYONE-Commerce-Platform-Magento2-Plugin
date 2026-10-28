# PAYONE COMMERCE PLATFORM FOR YOUR MAGENTO 2 SHOP
The PAYONE Commerce Platform extension connects your Magento 2 shop to the [PAYONE Commerce Platform](https://www.payone.com) API. Payments are created as commerce cases including the shopping cart, and all follow-up operations such as capture, refund and cancellation are handled directly from the Magento Admin Panel.

## Payment methods
*	Credit card (Visa, Mastercard, American Express, …) via PAYONE Hosted Tokenization, optionally with Click to Pay
*	SEPA Direct Debit
*	PayPal
*	Google Pay

## Important functions for Magento 2
*	Seamless integration of Magento's OnePage Checkout
*	Central configuration on the Magento Admin Panel, configurable per website and store view
*	Authorization with later capture or automatic capture (sale), configurable per payment method
*	Partial and multiple captures for partial deliveries
*	Full and partial refunds via credit memos
*	Void and cancellation of open authorizations directly from the order
*	Shopping cart transmission with line items, shipping costs and discounts
*	Card data is entered in PAYONE's hosted tokenization fields and never touches the shop
*	Status updates via webhooks, secured by HMAC signature check and an optional IP whitelist
*	Protocol of all API calls and webhooks in the Admin Panel, with sensitive data such as IBAN and tokens masked

## Requirements
*	Magento 2.4.4 or higher
*	PHP 8.1, 8.2, 8.3 or 8.4
*	A PAYONE Commerce Platform merchant account with API key and API secret

## Installation
*	Go to your installation directory of Magento 2 and perform the following commands
*	`composer require payone-gmbh/commerce-platform-magento-2`
*	`php bin/magento setup:upgrade`
*	`php bin/magento setup:di:compile`
*	`php bin/magento cache:clean`

## Configuration
*	**General settings:** *Stores → Configuration → Payone Commerce Platform → General* (or *PAYONE → Configuration → General* in the admin menu). Enter your Merchant ID, API Key and API Secret and choose the API environment (test or live).
*	**Payment methods:** *Stores → Configuration → Payone Commerce Platform → Payment* – activate and configure each payment method.
*	**Webhooks:** Configure the following webhook URL for your merchant account in the PAYONE Commerce Platform:
	`https://<your-shop-domain>/payone_cp/webhook`
	Webhooks are only accepted with a valid signature of your API key. Additionally, the IP addresses PAYONE sends webhooks from can be restricted with the setting *Valid PAYONE IPs*.
*	**Logs:** API calls and received webhooks can be found under *PAYONE → Logs → Api* and *PAYONE → Logs → Webhook*.

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
