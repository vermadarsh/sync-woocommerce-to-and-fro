<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/vermadarsh/
 * @since             1.0.0
 * @package           Sync_Marketplace
 *
 * @wordpress-plugin
 * Plugin Name:       Sync Marketplace
 * Plugin URI:        https://github.com/vermadarsh/
 * Description:       This plugin helps sync this vendor with the main marketplace.
 * Version:           1.0.0
 * Author:            Adarsh Verma
 * Author URI:        https://github.com/vermadarsh/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sync-marketplace
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SYNC_MARKETPLACE_VERSION', '1.0.0' );

/**
 * Define the log directory URL and PATH constants.
 */
$uploads_dir = wp_upload_dir();
$cons        = array(
	'SMP_PLUGIN_PATH'  => plugin_dir_path( __FILE__ ),
	'SMP_LOG_DIR_URL'  => $uploads_dir['baseurl'] . '/sync-marketplace-log/',
	'SMP_LOG_DIR_PATH' => $uploads_dir['basedir'] . '/sync-marketplace-log/',
);
foreach ( $cons as $con => $value ) {
	define( $con, $value );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-sync-marketplace-activator.php
 */
function activate_sync_marketplace() {
	require_once __DIR__ . '/includes/class-sync-marketplace-activator.php';
	Sync_Marketplace_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-sync-marketplace-deactivator.php
 */
function deactivate_sync_marketplace() {
	require_once __DIR__ . '/includes/class-sync-marketplace-deactivator.php';
	Sync_Marketplace_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_sync_marketplace' );
register_deactivation_hook( __FILE__, 'deactivate_sync_marketplace' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_sync_marketplace() {
	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require __DIR__ . '/includes/class-sync-marketplace.php';
	$plugin = new Sync_Marketplace();
	$plugin->run();

}

/**
 * This initiates the plugin.
 * Checks for the required plugins to be installed and active.
 */
function smp_plugin_loaded_callback() {
	$active_plugins = get_option( 'active_plugins' );
	$is_wc_active   = in_array( 'woocommerce/woocommerce.php', $active_plugins, true );

	if ( false === $is_wc_active ) {
		add_action( 'admin_notices', 'smp_admin_notices_callback' );
	} else {
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'smp_plugin_actions_callback' );
		run_sync_marketplace();
	}
}

add_action( 'plugins_loaded', 'smp_plugin_loaded_callback' );


/**
 * This function is called to show admin notices for any required plugin not active || installed.
 */
function smp_admin_notices_callback() {
	$this_plugin_data = get_plugin_data( __FILE__ );
	$this_plugin      = $this_plugin_data['Name'];
	$wc_plugin        = 'WooCommerce';
	?>
	<div class="error">
		<p>
			<?php /* translators: 1: %s: string tag open, 2: %s: strong tag close, 3: %s: this plugin, 4: %s: woocommerce plugin */ ?>
			<?php echo wp_kses_post( sprintf( __( '%1$s%3$s%2$s is ineffective as it requires %1$s%4$s%2$s to be installed and active.', 'sync-marketplace' ), '<strong>', '</strong>', esc_html( $this_plugin ), esc_html( $wc_plugin ) ) ); ?>
		</p>
	</div>
	<?php
}

/**
 * This function adds custom plugin actions.
 *
 * @param array $links Links array.
 * @return array
 */
function smp_plugin_actions_callback( $links ) {
	$this_plugin_links = array(
		'<a title="' . __( 'Settings', 'sync-marketplace' ) . '" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=sync-marketplace' ) ) . '">' . __( 'Settings', 'sync-marketplace' ) . '</a>',
	);

	return array_merge( $this_plugin_links, $links );
}
