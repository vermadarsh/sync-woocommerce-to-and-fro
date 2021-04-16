<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Marketplace_I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'sync-marketplace',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
