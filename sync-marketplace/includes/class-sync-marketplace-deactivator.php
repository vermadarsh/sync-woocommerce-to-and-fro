<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Marketplace_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear the scheduled crons now.
		if ( wp_next_scheduled( 'smp_sync_marketplace_cron' ) ) {
			wp_clear_scheduled_hook( 'smp_sync_marketplace_cron' );
		}
	}

}
