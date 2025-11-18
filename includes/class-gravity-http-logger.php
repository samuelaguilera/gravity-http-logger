<?php
/**
 * Main class for Gravity HTTP Logger Add-On.
 *
 * @package Gravity HTTP Logger
 * @author    Samuel Aguilera
 * @copyright Copyright (c) 2025 Samuel Aguilera
 */

defined( 'ABSPATH' ) || die();

// Include the Gravity Forms Add-On Framework.
GFForms::include_addon_framework();

/**
 * Main add-on class.
 */
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
	protected $_short_title = 'HTTP Logger';

	/**
	 * Defines the capabilities needed for the Gravity HTTP Logger Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravity_http_logger', 'gravity_http_logger_uninstall' );

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravity_http_logger_uninstall';

	/**
	 * Defines the default list of strings to search for in requests.
	 *
	 * @since  2.0
	 * @access protected
	 * @var    array $request_patterns List of strings to search for in requests.
	 */
	protected $request_patterns = array(
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
		'connect.mailerlite.com', // MailerLite.
		'api.brevo.com', // Brevo.
	);



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
	 * Adds hooks which need to be included before the init hook is triggered.
	 */
	public function pre_init() {
		$this->activation();
	}

	/**
	 * Plugin activation tasks.
	 */
	public function activation() {
		// Create table for HTTP logger.
		$this->create_table();
		// Cron event interval. Other WP default values for this are: twicedaily, hourly, weekly .
		$interval = apply_filters( 'gravity_http_logger_cleanup_interval', 'daily' );
		// Register cron task.
		if ( ! wp_next_scheduled( 'gravity_http_logger_task' ) ) {
			wp_schedule_event( time(), $interval, 'gravity_http_logger_task' );
		}
		// Add cleanup function to cron task.
		add_action( 'gravity_http_logger_task', array( $this, 'cleanup_old_records' ) );
	}

	/**
	 * Register initialization hooks.
	 *
	 * @since  1.0
	 */
	public function init() {
		parent::init();
		add_filter( 'http_api_debug', array( $this, 'get_response_data' ), 10, 5 );
		add_filter( 'gform_addon_navigation', array( $this, 'create_plugin_page_menu' ) );
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'http_logger_css',
				'src'     => plugin_dir_url( __DIR__ ) . 'css/http-logger.min.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'plugin_page', 'plugin_settings' ) ),
				),
			),
		);
		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Enqueue script for modal.
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'    => 'http_logger_js',
				'src'       => plugin_dir_url( __DIR__ ) . 'js/http-logger.min.js',
				'version'   => $this->_version,
				'deps'      => array(),
				'in_footer' => true,
				'callback'  => array( $this, 'localize_scripts' ),
				'strings'   => array(),
				'enqueue'   => array(
					array(
						'admin_page' => array( 'plugin_page' ),
						'tab'        => 'simpleaddon',
					),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return file_get_contents( plugin_dir_path( __DIR__ ) . 'images/menu-icon.svg' ); // phpcs:ignore
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => 'General Settings',
				'description' => '<p>Use the following settings to customize the add-on default values. If you don\'t know what you are doing, just leave the default ones.<p>',
				'fields'      => array(
					array(
						'type'          => 'text',
						'name'          => 'max_records',
						'label'         => 'Maximum Database Records',
						'tooltip'       => 'The number of requests you want to keep in the database after the daily cleanup.',
						'default_value' => GRAVITY_HTTP_LOGGER_MAX_RECORDS,
					),
					array(
						'name'    => 'htpp_codes_to_log',
						'label'   => 'Type of requests to save into the database',
						'type'    => 'checkbox',
						'tooltip' => 'Use this to restrict requests saved to database based on the HTTP response code. Log file will still save all requests.',
						'choices' => array(
							array(
								'label'         => 'Informational response codes (100 – 199)',
								'name'          => 'log_1xx_codes',
								'default_value' => 1,
							),
							array(
								'label'         => 'Successful response codes (200 – 299)',
								'name'          => 'log_2xx_codes',
								'default_value' => 1,
							),
							array(
								'label'         => 'Redirection response codes (300 – 399)',
								'name'          => 'log_3xx_codes',
								'default_value' => 1,
							),
							array(
								'label'         => 'Client error response codes (400 – 499)',
								'name'          => 'log_4xx_codes',
								'default_value' => 1,
							),
							array(
								'label'         => 'Server error response codes (500 – 599)',
								'name'          => 'log_5xx_codes',
								'default_value' => 1,
							),
						),
					),
				),
			),
			array(
				'title'       => 'Logger Monitor Settings',
				'description' => '<p class="ghl-warning">Be as specific as possible when adding a custom Request String. A generic string may save an excessive quantity of requests.</p>',
				'fields'      => array(
					array(
						'type'          => 'textarea',
						'name'          => 'request_patterns',
						'label'         => 'Request Strings',
						'tooltip'       => 'A comma separated list of additional matching strings you want to use to monitor the outgoing requests.',
						'default_value' => '',
						'class'         => 'medium',
					),
					array(
						'name'    => 'default_strings',
						'label'   => 'Default Strings',
						'type'    => 'checkbox',
						'tooltip' => 'This allows you to disable the default strings to monitor only the Request Strings you added in the previous setting.',
						'choices' => array(
							array(
								'label'         => 'Disable Default Strings',
								'name'          => 'disable_default_strings',
								'default_value' => 0,
							),
						),
					),
				),
			),

		);
	}

	/**
	 * Delete older records from database.
	 */
	public function cleanup_old_records() {
		global $wpdb;

		$table_name          = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;
		$max_records_setting = $this->get_plugin_setting( 'max_records' );
		$max_records         = empty( $max_records_setting ) ? GRAVITY_HTTP_LOGGER_MAX_RECORDS : $max_records_setting;
		$max_records         = apply_filters( 'gravity_http_logger_max_database_records', $max_records );

		// Check the table exists before deletion.
		$table_exists = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists !== $table_name ) {
			$this->log_debug( __METHOD__ . "(): Records cleanup failed. The table doesn't exists" );
			return;
		}

		// Get the id to start the cleanup.
		$cutoff_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i ORDER BY id DESC LIMIT 1 OFFSET %d',
				$table_name,
				$max_records
			)
		);

		// Return without changes if there are no more than 10 records.
		if ( null === $cutoff_id ) {
			return;
		}

		// Delete older records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE id <= %d',
				$table_name,
				$cutoff_id
			)
		);

		if ( false === $deleted ) {
			$this->log_debug( __METHOD__ . '(): Records cleanup failed: ' . $wpdb->last_error );
			return;
		}
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Adds a custom page for this add-on.
	 *
	 * @param (string) $menus The request URL.
	 */
	public function create_plugin_page_menu( $menus ) {

		$menus[] = array(
			'name'       => $this->_slug,
			'label'      => $this->get_short_title(),
			'callback'   => array( $this, 'my_plugin_page' ),
			'permission' => 'gravity_http_logger',
		);

		return $menus;
	}


	/**
	 * Render the log page content.
	 */
	public function my_plugin_page() {
		$table = new Gravity_HTTP_Logger_List_Table();
		$table->prepare_items();
		$request = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		?>
	<div class="wrap">
		<h1 class="wp-heading-inline">HTTP Requests Log</h1>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( $request ); ?>" />
			<?php
			wp_nonce_field( 'bulk-' . GRAVITY_HTTP_LOGGER_TABLE_NAME );
			$table->search_box( 'Search requests', 'search_requests' );
			$table->display();
			?>
		</form>
	</div>
	<!-- Modal for additional details. -->
	<div id="ghl-modal">
		<div id="ghl-modal-content-wrapper">
			<span class="close-ghl" title="Close">&times;</span>
			<button id="ghl-copy-btn" class="button" type="button">Copy</button>
			<pre id="ghl-modal-content"></pre>
		</div>
	</div>
		<?php
	}

	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Logs the HTTP response returned by WordPress.
	 *
	 * @param (array|WP_Error) $response HTTP response or WP_Error object.
	 * @param (string)         $context Context under which the hook is fired.
	 * @param (string)         $http_transport HTTP transport used.
	 * @param (array)          $parsed_args HTTP request arguments.
	 * @param (string)         $url The request URL.
	 */
	public function get_response_data( $response, $context, $http_transport, $parsed_args, $url ) {

		$default_patterns = $this->get_plugin_setting( 'disable_default_strings' ) === '1' ? array() : $this->request_patterns;

		// Get request_patterns from settings or use default ones if no setting is saved.
		$request_patterns = $this->get_plugin_setting( 'request_patterns' ) ? array_merge( explode( ',', preg_replace( '/\s+/', '', $this->get_plugin_setting( 'request_patterns' ) ) ), $default_patterns ) : $default_patterns;

		// Allow filtering the request_patterns supported.
		$request_patterns = apply_filters( 'gravity_http_logger_request_strings', $request_patterns );

		foreach ( $request_patterns as $request_pattern ) {
			if ( strpos( $url, $request_pattern ) !== false ) {

				// Response data.
				$response_code    = wp_remote_retrieve_response_code( $response ); // Int or empty string.
				$response_message = wp_remote_retrieve_response_message( $response ); // String.
				$response_headers = wp_remote_retrieve_headers( $response ); // Array.
				$response_body    = wp_remote_retrieve_body( $response ); // String.

				// Current time.
				$timestamp = current_time( 'mysql', true );

				$this->log_data_to_database( $request_pattern, $url, maybe_serialize( $parsed_args ), $response_code, $response_message, maybe_serialize( $response_headers ), $response_body, $timestamp );

				$this->log_debug( __METHOD__ . "(): [Start] Request To: {$url}\n" );
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Request Args ]--------8<--------8<--------' . "\n" );
				$this->log_debug( __METHOD__ . '(): Args: ' . var_export( $parsed_args, true ) ); // phpcs:ignore
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Response Code & Message ]--------8<--------8<--------' . "\n" );
				// Log $response['response']['code'].
				$this->log_debug( __METHOD__ . "(): {$request_pattern} - Code: " . $response_code . "\n" );
				// Log $response['response']['message'].
				$this->log_debug( __METHOD__ . "(): {$request_pattern} - Message: " . $response_message . "\n" );
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Response Headers ]--------8<--------8<--------' . "\n" );
				$this->log_debug( __METHOD__ . "(): {$request_pattern} - Headers: " . print_r( $response_headers, true ) ); // phpcs:ignore
				$this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ Response Body ]--------8<--------8<--------' . "\n" );
				// Log $response['body'].
				$this->log_debug( __METHOD__ . "(): {$request_pattern} - Body: " . print_r( json_decode( $response_body, true ), true ) ); // phpcs:ignore
				// $this->log_debug( __METHOD__ . '(): --------8<--------8<--------[ cut here ]--------8<--------8<--------' . "\n" );
				$this->log_debug( __METHOD__ . "(): [End] Request To: {$url}\n" );

			}
		}
	}

	/**
	 * Create table for HTTP Logger data.
	 */
	public function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`request_pattern` varchar(255) NOT NULL,
			`request_url` varchar(65535) NOT NULL,
			`request_args` varchar(65535) NOT NULL,
			`response_code` smallint(20) DEFAULT NULL,
			`response_message` varchar(65535) DEFAULT NULL,
			`response_headers` varchar(65535) DEFAULT NULL,
			`response_body` varchar(65535) DEFAULT NULL,
			`date_time` datetime DEFAULT NULL,
			PRIMARY KEY (`id`)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add logged data to database.
	 *
	 * @param string $request_pattern Service string to log.
	 * @param string $url URL for the request.
	 * @param array  $parsed_args Arguments used for the request.
	 * @param int    $response_code HTTP status code returned.
	 * @param string $response_message Message returned.
	 * @param array  $response_headers Reponse headers returned.
	 * @param array  $response_body    Response body.
	 * @param string $timestamp Date and time for the request.
	 */
	public function log_data_to_database( $request_pattern, $url, $parsed_args, $response_code, $response_message, $response_headers, $response_body, $timestamp ) {
		global $wpdb;

		$codes_to_log = array(
			'1' => $this->get_plugin_setting( 'log_1xx_codes' ),
			'2' => $this->get_plugin_setting( 'log_2xx_codes' ),
			'3' => $this->get_plugin_setting( 'log_3xx_codes' ),
			'4' => $this->get_plugin_setting( 'log_4xx_codes' ),
			'5' => $this->get_plugin_setting( 'log_5xx_codes' ),
		);

		foreach ( $codes_to_log as $code => $value ) {
			if ( '1' !== $value ) { // GF framework add-on stores 1 as string.
				unset( $codes_to_log[ $code ] );
			}
		}

		// Skip database log if first digit of the response code is not on the list of active settings.
		if ( ! array_key_exists( substr( $response_code, 0, 1 ), $codes_to_log ) ) {
			return;
		}

		$table_name = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;

		$wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				'INSERT INTO %i
			( request_pattern, request_url, request_args, response_code, response_message, response_headers, response_body, date_time )
			VALUES ( %s, %s, %s, %s, %s, %s, %s, %s )',
				array(
					$table_name,
					$request_pattern,
					$url,
					$parsed_args,
					$response_code,
					$response_message,
					$response_headers,
					$response_body,
					$timestamp,
				)
			)
		);
	}
}
