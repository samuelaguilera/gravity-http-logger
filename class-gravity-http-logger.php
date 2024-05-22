<?php

defined( 'ABSPATH' ) || die();

// Include the Gravity Forms Add-On Framework.
GFForms::include_addon_framework();

class Gravity_HTTP_Logger extends GFAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @var    Gravity_HTTP_Logger $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Gravity HTTP Logger Add-On.
	 *
	 * @since  1.0
	 * @var    string $_version Contains the version.
	 */
	protected $_version = GRAVITY_HTTP_LOGGER_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GRAVITY_HTTP_LOGGER_MIN_GF_VERSION;

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravity-http-logger';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravity-http-logger/gravity-http-logger.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since  1.0
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'https://www.samuelaguilera.com';

	/**
	 * Defines the title of this add-on.
	 *
	 * @since  1.0
	 * @var    string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity HTTP Logger Add-On';

	/**
	 * Defines the short title of the add-on.
	 *
	 * @since  1.0
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Gravity HTTP Logger';

	/**
	 * Defines the capabilities needed for the Gravity HTTP Logger Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravity-http-logger', 'gravity-http-logger_uninstall' );

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravity-http-logger_uninstall';

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since  1.0
	 *
	 * @return Gravity_HTTP_Logger $_instance An instance of the Gravity_HTTP_Logger class
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new Gravity_HTTP_Logger();
		}

		return self::$_instance;

	}

	/**
	 * Register initialization hooks.
	 *
	 * @since  1.0
	 */
	public function init() {
		parent::init();
		add_filter( 'http_api_debug', array( $this, 'get_response_data' ), 10, 5 );
	}

	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Logs the HTTP response returned by WordPress.
	 *
	 * @param (array|WP_Error) $response HTTP response or WP_Error object.
	 * @param (string)         $context Context under which the hook is fired.
	 * @param (string)         $class HTTP transport used.
	 * @param (array)          $parsed_args HTTP request arguments.
	 * @param (string)         $url The request URL.
	 */
	public function get_response_data( $response, $context, $class, $parsed_args, $url ) {
		$services = array(
			'api.mailchimp.com', // Mailchimp.
			'admin.mailchimp.com',
			'api.2checkout.com', // 2Checkout.
			'api.paypal.com', // PayPal Checkout.
			'api.sandbox.paypal.com', // PayPal Checkout Sandbox.
			'api.mollie.com', // Mollie.
			'www.zohoapis.com', // Zoho CRM.
			'api.dropboxapi.com', // Dropbox.
			'api.sendgrid.com', // SendGrid.
			'mailgun.net', // Mailgun.
			'api.postmarkapp.com', // Postmark.
			'api.getresponse.com', // GetResponse.
			'api3.getresponse360.com', // GetResponse 360.
			'www.googleapis.com', // Google Analytics.
			'api.hubapi.com', // HubSpot.
			'api.hsforms.com', // HubSpot.
			'api-us1.com', // ActiveCampaign.
			'agilecrm.com/dev/api/', // Agile CRM.
			'api.helpscout.net', // HelpScout.
			'api.madmimi.com', // Mad Mimi.
			'app.icontact.com', // iContact.
			'slack.com/api', // Slack.
			'api.cc.email', // Constant Contact.
			'constantcontact.com', // Constant Contact (To catch token refresh).
			'api.addpipe.com', // Pipe.
			'www.google.com/recaptcha/api', // reCAPTCHA.
			'hooks.zapier.com', // Zapier.
			'gf_check_background_tasks', // Background Tasks test (System Status).
			'wp_gf_feed_processor', // Background feed processor (e.g. Wehooks add-on).
			'squareup', // Square.
			'maps.googleapis.com/maps/api/place/', // Geolocation.
			'challenges.cloudflare.com', // Cloudflare Turnstile. Only for the POST request to perform the Turnstile challenge done by the make_turnstile_challenge function.
			'gravityapi.com', // License check.
			'salesforce.com', // Salesforce.
		);

		// Allow filtering the services supported.
		$services = apply_filters( 'gravity_http_logger_services', $services );

		foreach ( $services as $service ) {
			if ( strpos( $url, $service ) !== false ) {
				$this->log_debug( __METHOD__ . "(): [Start] Request To: {$url}\n" );
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Request Args ]--------8<--------8<--------' . "\n" );
				$this->log_debug( __METHOD__ . '(): Args: ' . var_export( $parsed_args, true ) );
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Response Code & Message ]--------8<--------8<--------' . "\n" );
				// Log $response['response']['code'].
				$this->log_debug( __METHOD__ . "(): {$service} - Code: " . wp_remote_retrieve_response_code( $response ) . "\n" );
				// Log $response['response']['message'].
				$this->log_debug( __METHOD__ . "(): {$service} - Message: " . wp_remote_retrieve_response_message( $response ) . "\n" );
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Response Headers ]--------8<--------8<--------' . "\n" );
				$this->log_debug( __METHOD__ . "(): {$service} - Headers: " . print_r( wp_remote_retrieve_headers( $response ), true ) );
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Response Body ]--------8<--------8<--------' . "\n" );
				// Log $response['body'].
				$this->log_debug( __METHOD__ . "(): {$service} - Body: " . print_r( json_decode( wp_remote_retrieve_body( $response ), true ), true ) );
				// $this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ cut here ]--------8<--------8<--------' . "\n" );
				// Full response.
				// $this->log_debug( __METHOD__ . '(): Full Raw Response: ' . print_r( $response, true ) );
				$this->log_debug( __METHOD__ . "(): [End] Request To: {$url}\n" );

			}
		}

	}

}
