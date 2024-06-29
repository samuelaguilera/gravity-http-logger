<?php
/**
 * Manage download of plugin updates from GitHub.
 *
 * @package Gravity HTTP Logger Updater
 * @version 1.0.1
 */

/*
Copyright 2022 Samuel Aguilera.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License version 3 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) || die();

/**
 * Update WordPress plugin from GitHub Repository.
 * Change the class name to your own unique class name.
 */
class Gravity_HTTP_Logger_Updater {

	/**
	 * Defines the GitHub respository name.
	 *
	 * @var $github_repository GitHub respository name.
	 */
	private $github_repository = 'gravity-http-logger';

	/**
	 * Defines the GitHub username
	 *
	 * @var $github_username GitHub username.
	 */
	private $github_username = 'samuelaguilera';

	/**
	 * Defines a fake hostname for updates. This is to prevent update conflicts with plugins hosted at WordPress.org using the same slug, it doesn't accept folders or prefixes, just the hostname.
	 * The same hostname must be used for the Update URI header in the plugin main file. For more details see https://make.wordpress.org/core/2021/06/29/introducing-update-uri-plugin-header-in-wordpress-5-8/ .
	 *
	 * @var $update_hostname A dummy hostname used as identifier for the function using the update_plugins_$hostname hook.
	 */
	private $update_hostname = 'gravityhttplogger.example.com';

	/**
	 * NO MORE EDITING REQUIRED AFTER THIS LINE.
	 */

	/**
	 * Main plugin file.
	 *
	 * @var $file Main plugin file.
	 */
	private $file;

	/**
	 * Plugin basename.
	 *
	 * @var $basename folder/file.php .
	 */
	private $basename;

	/**
	 * Plugin slug (folder).
	 *
	 * @var $slug Plugin slug (folder)
	 */
	private $slug;

	/**
	 * Class constructor.
	 *
	 * @param string $file Main plugin file.
	 */
	public function __construct( $file ) {
		// Some variables for later usage.
		$this->file     = $file;
		$this->basename = plugin_basename( $this->file );
		$this->slug     = dirname( plugin_basename( $this->file ) );
		// Display update information when View version details link is clicked.
		add_filter( 'plugins_api', array( $this, 'get_update_details' ), 20, 3 );
		// Using this filter for the update check to prevent conflicts with w.org hosted plugins.
		add_filter( "update_plugins_$this->update_hostname", array( $this, 'check_plugin_update' ), 10, 4 );
		// Ensure the plugin folder name remains the same when update is done from Dashboard > Updates.
		add_filter( 'upgrader_source_selection', array( $this, 'change_source_dir' ), 10, 4 );
	}

	/**
	 * Get plugin data from Github.
	 */
	public function get_github_plugin_data() {
		$github_plugin_data = array();

		// Cached request to prevent GitHub rate limit.
		$github_plugin_data = get_transient( $this->github_username . '-' . $this->github_repository . '_update_check' );

		$request_uri = esc_url_raw( "https://api.github.com/repos/$this->github_username/$this->github_repository/releases" );

		// Fetch plugin data from GitHub if transient is no longer valid.
		if ( false === $github_plugin_data ) {

			$github_plugin_data = wp_remote_get( $request_uri ); // Using default WP_Http::request() args.

			if ( ! is_wp_error( $github_plugin_data ) ) {
				// Get body response.
				$github_plugin_data = current( json_decode( wp_remote_retrieve_body( $github_plugin_data ), true ) );

				// Prevent GitHub rate limit if for some reason an empty response was returned. e.g. No releases available.
				if ( ! is_array( $github_plugin_data ) && empty( $github_plugin_data ) ) {
					$github_plugin_data = 'empty_response';
				}
			}
			set_transient( $this->github_username . '-' . $this->github_repository . '_update_check', $github_plugin_data, HOUR_IN_SECONDS );
		}

		// Remove v prefix from tag name if present.
		if ( isset( $github_plugin_data['tag_name'] ) && substr( $github_plugin_data['tag_name'], 0, 1 ) === 'v' ) {
			$github_plugin_data['tag_name'] = substr( $github_plugin_data['tag_name'], 1 );
		}

		return $github_plugin_data;
	}

	/**
	 * Obtain information to display when View version details link is clicked.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args   Plugin API arguments.
	 */
	public function get_update_details( $result, $action, $args ) {

		// Run only for the right action and our plugin slug.
		if ( 'plugin_information' !== $action || $args->slug !== $this->slug ) {
			return $result;
		}

		$github_plugin_data = $this->get_github_plugin_data();

		// Return if there's no Github data. e.g. No releases or repo was deleted.
		if ( ! is_array( $github_plugin_data ) ) {
			return $result;
		}

		$local_plugin_data = get_plugin_data( $this->file );

		// Only the information to be displayed when clicking the view version details link.
		$args           = new stdClass();
		$args->plugin   = $this->basename;
		$args->name     = $local_plugin_data['Name'];
		$args->version  = $github_plugin_data['tag_name'];
		$args->slug     = $this->slug;
		$args->url      = $local_plugin_data['PluginURI'];
		$args->author   = $local_plugin_data['AuthorName'];
		$args->homepage = esc_url_raw( "https://github.com/$this->github_username/$this->github_repository/" );
		$args->sections = array(
			'Description' => $local_plugin_data['Description'],
			'Updates'     => $github_plugin_data['body'],
		);

		return $args;
	}

	/**
	 * Check if there's a new version as Github release available for update.
	 *
	 * @param array|false $update {
	 *     The plugin update data with the latest details. Default false.
	 *
	 *     @type string $id           Optional. ID of the plugin for update purposes, should be a URI
	 *                                specified in the `Update URI` header field.
	 *     @type string $slug         Slug of the plugin.
	 *     @type string $version      The version of the plugin.
	 *     @type string $url          The URL for details of the plugin.
	 *     @type string $package      Optional. The update ZIP for the plugin.
	 *     @type string $tested       Optional. The version of WordPress the plugin is tested against.
	 *     @type string $requires_php Optional. The version of PHP which the plugin requires.
	 *     @type bool   $autoupdate   Optional. Whether the plugin should automatically update.
	 *     @type array  $icons        Optional. Array of plugin icons.
	 *     @type array  $banners      Optional. Array of plugin banners.
	 *     @type array  $banners_rtl  Optional. Array of plugin RTL banners.
	 *     @type array  $translations {
	 *         Optional. List of translation updates for the plugin.
	 *
	 *         @type string $language   The language the translation update is for.
	 *         @type string $version    The version of the plugin this translation is for.
	 *                                  This is not the version of the language file.
	 *         @type string $updated    The update timestamp of the translation file.
	 *                                  Should be a date in the `YYYY-MM-DD HH:MM:SS` format.
	 *         @type string $package    The ZIP location containing the translation update.
	 *         @type string $autoupdate Whether the translation should be automatically installed.
	 *     }
	 * }
	 * @param array       $plugin_data      Plugin headers.
	 * @param string      $plugin_file      Plugin filename.
	 * @param array       $locales          Installed locales to look translations for.
	 */
	public function check_plugin_update( $update, $plugin_data, $plugin_file, $locales ) {

		$local_plugin_data = get_plugin_data( $this->file );

		// Double check it's our plugin in case of using same host for several plugins.
		if ( $local_plugin_data['Name'] !== $plugin_data['Name'] ) {
			return $update;
		}

		// Get plugin data from github.com.
		$github_plugin_data = $this->get_github_plugin_data();

		// Prevent notices if repository is deleted or can't get the data.
		if ( ! is_array( $github_plugin_data ) || ! isset( $github_plugin_data['tag_name'] ) ) {
			return $update;
		}

		// Compare versions.
		if ( version_compare( $github_plugin_data['tag_name'], $plugin_data['Version'], '>' ) ) {
			$update = array(
				'plugin'      => $this->basename,
				'url'         => $local_plugin_data['PluginURI'],
				'slug'        => $this->slug,
				'package'     => $github_plugin_data['zipball_url'],
				'version'     => $plugin_data['Version'],
				'new_version' => $github_plugin_data['tag_name'],
				'id'          => '',
			);

			return $update;
		}

		return $update;
	}

	/**
	 * For some reason WP needs this to prevent issues when update is done from Dashboard > Updates or Automatic Update.
	 * Not needed when you update from the Plugins page.
	 *
	 * @param string      $source        File source location.
	 * @param string      $remote_source Remote file source location.
	 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra arguments passed to hooked filters.
	 */
	public function change_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		// Return if we don't have access to the filesystem or $hook_extra['plugin'] is not defined.
		if ( ! is_object( $wp_filesystem ) || ! isset( $hook_extra['plugin'] ) ) {
			return $source;
		}

		// Confirm this is an update for our plugin. Using $hook_extra because $upgrader->skin->plugin_info is not available during automatic update.
		if ( $this->basename !== $hook_extra['plugin'] ) {
			return $source;
		}

		// New path using the expected folder name to prevent issues.
		$new_source = dirname( $remote_source ) . "/$this->slug/";

		// Set the new source path only after successful move.
		if ( true === $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		return $source;
	}
}
