<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/public
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Marketplace_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the custom stylesheets & scripts for the public area.
	 *
	 * @since 1.0.0
	 * @param string $hook Holds the current page hook.
	 */
	public function smp_wp_enqueue_assets( $hook ) {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/sync-marketplace-public.css',
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'css/sync-marketplace-public.css' )
		);

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/sync-marketplace-public.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/sync-marketplace-public.js' ),
			true
		);
	}

}
