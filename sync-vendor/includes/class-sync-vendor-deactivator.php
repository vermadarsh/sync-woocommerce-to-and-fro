<?php
/**
 * Fired during plugin deactivation
 *
 * @link        https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/includes
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Vendor_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear the scheduled crons now.
		if ( wp_next_scheduled( 'svn_update_tax_rates_cron' ) ) {
			wp_clear_scheduled_hook( 'svn_update_tax_rates_cron' );
		}

		if ( wp_next_scheduled( 'svn_sync_vendor_cron' ) ) {
			wp_clear_scheduled_hook( 'svn_sync_vendor_cron' );
		}
	}

}
