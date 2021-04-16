<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/includes
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Marketplace {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Sync_Marketplace_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'SYNC_MARKETPLACE_VERSION' ) ) {
			$this->version = SYNC_MARKETPLACE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'sync-marketplace';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Sync_Marketplace_Loader. Orchestrates the hooks of the plugin.
	 * - Sync_Marketplace_i18n. Defines internationalization functionality.
	 * - Sync_Marketplace_Admin. Defines all hooks for the admin area.
	 * - Sync_Marketplace_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once __DIR__ . '/class-sync-marketplace-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once __DIR__ . '/class-sync-marketplace-i18n.php';

		/**
		 * The file responsible for defining plugin custom functions.
		 */
		require_once __DIR__ . '/sync-marketplace-functions.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once __DIR__ . '/../admin/class-sync-marketplace-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once __DIR__ . '/../public/class-sync-marketplace-public.php';

		$this->loader = new Sync_Marketplace_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Sync_Marketplace_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Sync_Marketplace_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Sync_Marketplace_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'smp_admin_enqueue_assets' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'smp_admin_init_callback' );
		$this->loader->add_filter( 'woocommerce_get_settings_pages', $plugin_admin, 'smp_woocommerce_get_settings_pages_callback' );
		$this->loader->add_action( 'wp_ajax_smp_delete_log', $plugin_admin, 'smp_delete_log' );
		$this->loader->add_action( 'wp_ajax_smp_verify_rest_api_credentials', $plugin_admin, 'smp_verify_rest_api_credentials' );
		$this->loader->add_filter( 'post_row_actions', $plugin_admin, 'smp_post_row_actions_callback', 20, 2 );
		$this->loader->add_filter( 'comment_row_actions', $plugin_admin, 'smp_comment_row_actions_callback', 20, 2 );
		$this->loader->add_filter( 'user_row_actions', $plugin_admin, 'smp_user_row_actions_callback', 20, 2 );
		$this->loader->add_filter( 'woocommerce_admin_order_buyer_name', $plugin_admin, 'smp_woocommerce_admin_order_buyer_name_callback', 20, 2 );
		$this->loader->add_filter( 'http_request_host_is_external', $plugin_admin, 'smp_http_request_host_is_external_callback' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'smp_admin_notices_callback' );

		// Hooks related to coupon management.
		$this->loader->add_action( 'woocommerce_coupon_object_updated_props', $plugin_admin, 'smp_woocommerce_coupon_object_updated_props_callback', 20 );
		$this->loader->add_action( 'before_delete_post', $plugin_admin, 'smp_before_delete_post_callback' );

		// Hooks related to customer management.
		$this->loader->add_action( 'user_register', $plugin_admin, 'smp_user_register_callback' );
		$this->loader->add_action( 'profile_update', $plugin_admin, 'smp_profile_update_callback' );
		$this->loader->add_action( 'woocommerce_customer_save_address', $plugin_admin, 'smp_profile_update_callback' );
		$this->loader->add_action( 'woocommerce_save_account_details', $plugin_admin, 'smp_profile_update_callback' );
		$this->loader->add_action( 'delete_user', $plugin_admin, 'smp_delete_user_callback' );

		// Hooks related to product's taxonomy ( category, tag & product attribute terms ) terms.
		$this->loader->add_action( 'created_term', $plugin_admin, 'smp_created_term_callback', 20, 3 );
		$this->loader->add_action( 'pre_delete_term', $plugin_admin, 'smp_pre_delete_term_callback', 10, 2 );
		$this->loader->add_action( 'edited_term', $plugin_admin, 'smp_edited_term_callback', 20, 3 );

		// Hooks related to product attributes.
		$this->loader->add_action( 'woocommerce_attribute_added', $plugin_admin, 'smp_woocommerce_attribute_added_callback', 5, 2 );
		$this->loader->add_action( 'woocommerce_attribute_updated', $plugin_admin, 'smp_woocommerce_attribute_updated_callback', 5, 2 );
		$this->loader->add_action( 'woocommerce_before_attribute_delete', $plugin_admin, 'smp_woocommerce_before_attribute_delete_callback' );

		// Hooks related to products.
		$this->loader->add_action( 'woocommerce_update_product', $plugin_admin, 'smp_woocommerce_update_product_callback', 20, 2 );
		$this->loader->add_action( 'woocommerce_save_product_variation', $plugin_admin, 'smp_woocommerce_save_product_variation_callback', 20 );
		$this->loader->add_action( 'woocommerce_before_delete_product_variation', $plugin_admin, 'smp_woocommerce_before_delete_product_variation_callback', 20 );

		// Hooks related to orders.
		$this->loader->add_action( 'woocommerce_thankyou', $plugin_admin, 'smp_woocommerce_thankyou_callback', 20 );
		$this->loader->add_action( 'woocommerce_update_order', $plugin_admin, 'smp_woocommerce_update_order_callback', 20, 2 );
		$this->loader->add_action( 'woocommerce_refund_created', $plugin_admin, 'smp_woocommerce_refund_created_callback', 20 );
		$this->loader->add_action( 'woocommerce_refund_deleted', $plugin_admin, 'smp_woocommerce_refund_deleted_callback', 20, 2 );

		// Hooks related to order notes.
		$this->loader->add_action( 'wp_insert_comment', $plugin_admin, 'smp_wp_insert_comment_callback', 10, 2 );
		$this->loader->add_action( 'delete_comment', $plugin_admin, 'smp_delete_comment_callback', 20, 2 );
		$this->loader->add_filter( 'woocommerce_order_note_class', $plugin_admin, 'smp_woocommerce_order_note_class_callback', 20, 2 );

		// Hooks related to product reviews.
		$this->loader->add_action( 'comment_post', $plugin_admin, 'smp_comment_post_callback', 20 );
		$this->loader->add_action( 'edit_comment', $plugin_admin, 'smp_edit_comment_callback', 20 );

		// Hooks related to taxes.
		$this->loader->add_action( 'woocommerce_settings_saved', $plugin_admin, 'smp_woocommerce_settings_saved_callback' );
		$this->loader->add_action( 'woocommerce_tax_rate_added', $plugin_admin, 'smp_woocommerce_tax_rate_added_callback', 20 );
		$this->loader->add_action( 'woocommerce_tax_rate_updated', $plugin_admin, 'smp_woocommerce_tax_rate_updated_callback', 20 );
		$this->loader->add_action( 'woocommerce_tax_rate_deleted', $plugin_admin, 'smp_woocommerce_tax_rate_deleted_callback', 20 );

		// Hooks related to webhooks.
		$this->loader->add_action( 'woocommerce_new_webhook', $plugin_admin, 'smp_woocommerce_new_webhook_callback', 20, 2 );
		$this->loader->add_action( 'woocommerce_webhook_updated', $plugin_admin, 'smp_woocommerce_webhook_updated_callback', 20 );
		$this->loader->add_action( 'woocommerce_webhook_deleted', $plugin_admin, 'smp_woocommerce_webhook_deleted_callback', 20 );

		// Hooks related to shipping zones.
		$this->loader->add_action( 'woocommerce_after_shipping_zone_object_save', $plugin_admin, 'smp_woocommerce_after_shipping_zone_object_save_callback', 20 );

		// Cron.
		$this->loader->add_action( 'smp_sync_marketplace_cron', $plugin_admin, 'smp_smp_sync_marketplace_cron_callback' );

		// Hooks related to the management of custom fields in Rest API.
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'smp_rest_api_init_callback' );

		// Hooks related to product import feature.
		$this->loader->add_action( 'woocommerce_product_import_inserted_product_object', $plugin_admin, 'smp_woocommerce_product_import_inserted_product_object_callback', 20, 2 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Sync_Marketplace_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'smp_wp_enqueue_assets' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Sync_Marketplace_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
