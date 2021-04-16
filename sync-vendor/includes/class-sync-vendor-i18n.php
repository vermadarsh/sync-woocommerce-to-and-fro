<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link        https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/includes
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Vendor_I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'sync-vendor',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
