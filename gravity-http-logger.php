<?php
/**
 * Plugin Name: Gravity HTTP Logger
 * Description: Logs the code, message, headers and body of HTTP responses provided by WordPress for requests sent to third-party services and GF core Background tasks.
 * Version: 1.1.1
 * Author: Samuel Aguilera
 * Author URI: https://www.samuelaguilera.com
 * License: GPL-3.0+
 * PluginURI: gravityhttplogger.example.com
 * Update URI: gravityhttplogger.example.com
 *
 * @package Gravity HTTP Logger
 */

/*
------------------------------------------------------------------------
Copyright 2021 Samuel Aguilera

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.
*/

defined( 'ABSPATH' ) || die();

// Updates handler.
require_once plugin_dir_path( __FILE__ ) . 'class-gravity-http-logger-updater.php';
$gravity_http_logger_updater = class_exists( 'Gravity_HTTP_Logger_Updater' ) ? new Gravity_HTTP_Logger_Updater( __FILE__ ) : false;

// Defines the current version of the Gravity HTTP Logger.
define( 'GRAVITY_HTTP_LOGGER_VERSION', '1.1.1' );

// Defines the minimum version of Gravity Forms required to run Gravity HTTP Logger.
define( 'GRAVITY_HTTP_LOGGER_MIN_GF_VERSION', '2.5' );

// After Gravity Forms is loaded, load the Add-On.
add_action( 'gform_loaded', array( 'Gravity_HTTP_Logger_Bootstrap', 'load_addon' ), 5 );

/**
 * Loads the Gravity HTTP Logger Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0
 */
class Gravity_HTTP_Logger_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since  1.0
	 */
	public static function load_addon() {

		// Requires the class file.
		require_once plugin_dir_path( __FILE__ ) . 'class-gravity-http-logger.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'Gravity_HTTP_Logger' );
	}
}

/**
 * Returns an instance of the Gravity_HTTP_Logger class
 *
 * @since  1.0
 *
 * @return Gravity_HTTP_Logger|bool An instance of the Gravity_HTTP_Logger class
 */
function gravity_http_logger() {
	return class_exists( 'Gravity_HTTP_Logger' ) ? Gravity_HTTP_Logger::get_instance() : false;
}
