<?php
/**
 * Fired during plugin activation
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Marketplace_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Create a log directory within the WordPress uploads directory.
		$_upload     = wp_upload_dir();
		$_upload_dir = $_upload['basedir'];
		$_upload_dir = "{$_upload_dir}/sync-marketplace-log/";

		if ( ! file_exists( $_upload_dir ) ) {
			mkdir( $_upload_dir, 0755, true );
		}

		/**
		 * Setup the crons for syncing the WooCommerce settings from this vendor website to marketplace.
		 *
		 * Setup the hourly cron for updating the tax rates.
		 */
		if ( ! wp_next_scheduled( 'smp_sync_marketplace_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'smp_sync_marketplace_cron' );
		}

		// Redirect to plugin settings.
		add_option( 'smp_do_activation_redirect', 1, false );
	}

}
