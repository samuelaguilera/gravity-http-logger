# Description

This is an add-on for Gravity Forms that logs the code, message, headers and body of HTTP responses provided by WordPress for requests sent to third-party services using the WordPress HTTP API and GF core Background tasks.

# Usage

- Install and activate from your site Plugins page.
- Go to Forms > Settings > Logging and enable logging for Gravity HTTP Logger.

# Add-Ons Supported

* 2Checkout.
* ActiveCampaign.
* Agile CRM.
* Constant Contact.
* Cloudflare Turnstile. (Only for the POST request to perform the Turnstile challenge.)
* Dropbox.
* Geolocation.
* GetResponse.
* HelpScout.
* HubSpot.
* iContact.
* Mad Mimi.
* Mailchimp.
* Mailgun.
* Mollie.
* PayPal Checkout.
* Pipe.
* Postmark.
* reCAPTCHA.
* Salesforce.
* SendGrid.
* Slack.
* Square.
* Webhooks. (Logs the background request sent to admin-ajax.php)
* Zoho CRM.
* Zapier.

# Add-Ons **not** Supported

The following add-ons are not using the WordPress HTTP API.

* Authorize.net.
* Emma.
* Highrise.
* PayPal Payments Pro.
* Stripe.
* Trello.
* Twilio.

# Logging Custom Services

You can use the gravity_http_logger_services filter to include the domain for other services that you may want to log, a partial match is enough. The example below would log requests sent to the domain example.com and any subdomain for it (e.g. www.example.com, auth.example.com, etc...).

```
add_filter( 'gravity_http_logger_services', function( $services ) {
	$services[] = 'example.com'; // Replace this with the request destination domain.
	return $services;
} );
```