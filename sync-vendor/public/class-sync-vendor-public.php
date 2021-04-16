<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link        https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/public
 */

 // These files are included to access WooCommerce Rest API PHP library.
require SVN_PLUGIN_PATH . 'vendor/autoload.php';
use Automattic\WooCommerce\HttpClient\HttpClientException;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/public
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Vendor_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
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
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @param string $hook Holds the current page hook.
	 */
	public function svn_wp_enqueue_scripts_callback( $hook ) {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/sync-vendor-public.css',
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'css/sync-vendor-public.css' ),
			'all'
		);
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/sync-vendor-public.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/sync-vendor-public.js' ),
			false
		);
	}

	/**
	 * Manage the args before listing out vendor's products.
	 * This is basically done to show the synced products from the vendor's personal store on the vendor's dashboard.
	 *
	 * @param array $args Holds the array of arguments.
	 */
	public function svn_dokan_product_listing_arg_callback( $args ) {
		$pagenum       = filter_input( INPUT_GET, 'pagenum', FILTER_SANITIZE_STRING );
		$pagenum       = isset( $pagenum ) ? absint( $pagenum ) : 1;
		$post_statuses = array( 'publish', 'draft', 'pending', 'future' );
		$args          = array(
			'posts_per_page' => 15,
			'paged'          => $pagenum,
			'post_status'    => $post_statuses,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => apply_filters( 'dokan_product_listing_exclude_type', array() ),
					'operator' => 'NOT IN',
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'smp_associated_vendor_id',
					'value'   => get_current_user_id(),
					'compare' => '=',
				),
			),
		);

		return $args;
	}

	/**
	 * Post the product to vendor's website when any vendor adds product to the marketplace.
	 *
	 * @param int $product_id Holds the product ID.
	 */
	public function svn_dokan_new_product_added_callback( $product_id ) {

		if ( empty( $product_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid product request found, ID: {$product_id}, couldn't be created. Action taken by vendor." );
			return;
		}

		$product = wc_get_product( $product_id );

		if ( empty( $product ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid product object request found, ID: {$product_id}, couldn't be created. Action taken by vendor." );
			return;
		}

		$associated_vendor_id = get_post_field( 'post_author', $product_id );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while creating product {$product_id}. Action taken by vendor." );
			return;
		}

		if ( ! is_user_vendor( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "NOTICE: Product create action is taken by a non-vendor user: {$associated_vendor_id} for the product ID: {$product_id}." );
			return;
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object, product {$product_id} couldn't be created. Action taken by vendor." );
			return;
		}

		$product_data = svn_get_product_data( $product );

		if ( false === $product_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank product array for {$product_id}, couldn't be created/updated." );
			return;
		}

		try {
			$remote_product = $woo->post( 'products', $product_data );

			// Add remote product ID to the meta of this product.
			if ( ! empty( $remote_product->id ) ) {
				update_post_meta( $product_id, 'synced_vendor_with_id', $remote_product->id );
				update_post_meta( $product_id, 'smp_associated_vendor_id', get_current_user_id() );

				// Write the log.
				svn_write_sync_log( "SUCCESS: Product created at vendor's website with ID: {$remote_product->id}. At marketplace: {$product_id}." );
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't create product at vendor's website due to the error: {$error_message}. Product ID: {$product_id}. Action taken by vendor {$associated_vendor_id}." );
		}
	}

	/**
	 * Post the product to vendor's website when any vendor updates product at the marketplace.
	 *
	 * @param int $product_id Holds the product ID.
	 */
	public function svn_dokan_product_updated_callback( $product_id ) {

		if ( empty( $product_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid product request found, ID: {$product_id}, couldn't be updated. Action taken by vendor." );
			return;
		}

		$product = wc_get_product( $product_id );

		if ( empty( $product ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid product object request found, ID: {$product_id}, couldn't be updated. Action taken by vendor." );
			return;
		}

		$associated_vendor_id = get_post_field( 'post_author', $product_id );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating product {$product_id}. Action taken by vendor." );
			return;
		}

		if ( ! is_user_vendor( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "NOTICE: Product update action is taken by a non-vendor user: {$associated_vendor_id} for the product ID: {$product_id}." );
			return;
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object, product {$product_id} couldn't be updated. Action taken by vendor." );
			return;
		}

		$product_data = svn_get_product_data( $product );

		if ( false === $product_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank product array for {$product_id}, couldn't be updated." );
			return;
		}

		// Fetch the remote product ID.
		$remote_product_id = get_post_meta( $product_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_product_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote product ID not found while updating product {$product_id}. Action taken by vendor." );
			return;
		}

		try {
			$remote_product = $woo->put( "products/{$remote_product_id}", $product_data );

			// Add remote product ID to the meta of this product.
			if ( ! empty( $remote_product->id ) ) {
				update_post_meta( $product_id, 'synced_vendor_with_id', $remote_product->id );

				// Write the log.
				svn_write_sync_log( "SUCCESS: Product updated at vendor's website with ID: {$remote_product->id}. At marketplace: {$product_id}." );
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't update product at vendor's website due to the error: {$error_message}. Product ID: {$product_id}. Action taken by vendor {$associated_vendor_id}." );
		}
	}

}
