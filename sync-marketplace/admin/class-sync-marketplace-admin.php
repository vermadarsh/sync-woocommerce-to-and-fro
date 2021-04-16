<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/admin
 */

// These files are included to access WooCommerce Rest API PHP library.
require SMP_PLUGIN_PATH . 'vendor/autoload.php';
use Automattic\WooCommerce\HttpClient\HttpClientException;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/admin
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Marketplace_Admin {

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
	 * @since    1.0.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_settings_tabs = array();
		$this->plugin_name          = $plugin_name;
		$this->version              = $version;

		// Check, if the rest API credentials' verification request is made.
		$verify_creds = filter_input( INPUT_POST, 'smp-verify-rest-api-credentials', FILTER_SANITIZE_STRING );

		if ( ! empty( $verify_creds ) ) {
			$this->smp_verify_rest_api_credentials();
		}
	}

	/**
	 * Register the custom stylesheets & scripts for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function smp_admin_enqueue_assets() {
		global $current_tab;

		if ( 'sync-marketplace' !== $current_tab ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/sync-marketplace-admin.css',
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'css/sync-marketplace-admin.css' )
		);

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/sync-marketplace-admin.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/sync-marketplace-admin.js' ),
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'SMP_Admin_JS_Obj',
			array(
				'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
				'delete_log_confirmation'  => __( 'Are you sure to delete the log? This action won\'t be undone.', 'sync-marketplace' ),
				'delete_log_button_text'   => __( 'Delete Log', 'sync-marketplace' ),
				'verify_creds_button_text' => __( 'Verify Rest API Credentials', 'sync-marketplace' ),
				'section'                  => $section,
			)
		);
	}

	/**
	 * Actions to be taken at admin initialization.
	 */
	public function smp_admin_init_callback() {

		// Redirect after plugin redirect.
		if ( get_option( 'smp_do_activation_redirect' ) ) {
			delete_option( 'smp_do_activation_redirect' );
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=sync-marketplace' ) );
			exit;
		}
	}

	/**
	 * Admin settings for syncing marketplace.
	 *
	 * @param array $settings Array of WC settings.
	 */
	public function smp_woocommerce_get_settings_pages_callback( $settings ) {
		$settings[] = include __DIR__ . '/inc/class-sync-marketplace-settings.php';

		return $settings;
	}

	/**
	 * AJAX request to delete sync log.
	 */
	public function smp_delete_log() {
		$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );

		if ( 'smp_delete_log' !== $action ) {
			return;
		}

		global $wp_filesystem;
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();

		$wp_filesystem->put_contents(
			SMP_LOG_DIR_PATH . 'sync-log.log',
			'',
			FS_CHMOD_FILE // predefined mode settings for WP files.
		);

		wp_send_json_success(
			array(
				'code' => 'smp-sync-log-deleted',
			)
		);
		wp_die();
	}

	/**
	 * AJAX request to delete sync log.
	 */
	public function smp_verify_rest_api_credentials() {
		$app_name        = filter_input( INPUT_POST, 'smp_marketplace_rest_api_app_name', FILTER_SANITIZE_STRING );
		$app_scope       = filter_input( INPUT_POST, 'smp_marketplace_rest_api_app_scope', FILTER_SANITIZE_STRING );
		$marketplace_url = filter_input( INPUT_POST, 'smp_marketplace_url', FILTER_SANITIZE_STRING );
		$consumer_key    = filter_input( INPUT_POST, 'smp_marketplace_rest_api_consumer_key', FILTER_SANITIZE_STRING );
		$consumer_secret = filter_input( INPUT_POST, 'smp_marketplace_rest_api_consumer_secret_key', FILTER_SANITIZE_STRING );
		$http_referer    = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );

		$endpoint = '/wc-auth/v1/authorize';
		$params   = array(
			'app_name'     => $app_name,
			'scope'        => $app_scope,
			'user_id'      => 1,
			'return_url'   => home_url(),
			'callback_url' => $http_referer,
		);

		$query_string = http_build_query( $params );
		$redirect_to = $marketplace_url . $endpoint . '?' . $query_string;
		header( "Location: {$redirect_to}" );
		exit;

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client( true );

		if ( false === $woo ) {
			return;
		}

		debug( $woo );
		die;
	}

	/**
	 * Add row action to reveal the remote marketplace post ID.
	 *
	 * @param array  $actions Holds the list of actions.
	 * @param object $post Holds the WordPress post object.
	 * @return array
	 */
	public function smp_post_row_actions_callback( $actions, $post ) {

		if ( empty( $post ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$marketplace_id = get_post_meta( $post->ID, 'synced_marketplace_with_id', true );

		if ( empty( $marketplace_id ) ) {
			return $actions;
		}

		if ( 'shop_coupon' === $post->post_type || 'product' === $post->post_type ) {
			/* translators: %s: marketplace ID */
			$actions['smp-marketplace-id'] = sprintf( __( 'Marketplace ID: %1$s', 'sync-marketplace' ), $marketplace_id );
		}

		// Add action to show the original coupon ID.
		if ( 'shop_coupon' === $post->post_type ) {
			/* translators: %s: coupon ID */
			$actions['smp-coupon-id'] = sprintf( __( 'ID: %1$s', 'sync-marketplace' ), $post->ID );
		}

		return $actions;
	}

	/**
	 * Add row action to reveal the remote marketplace comment ID.
	 *
	 * @param array  $actions Holds the list of actions.
	 * @param object $comment Holds the WordPress comment object.
	 * @return array
	 */
	public function smp_comment_row_actions_callback( $actions, $comment ) {

		if ( empty( $comment ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$post_id = $comment->comment_post_ID;

		if ( 'product' !== get_post_type( $post_id ) ) {
			return $actions;
		}

		$marketplace_comment_id = get_comment_meta( $comment->comment_ID, 'synced_marketplace_with_id', true );

		if ( empty( $marketplace_comment_id ) || false === $marketplace_comment_id ) {
			return $actions;
		}

		/* translators: %s: marketplace ID */
		$actions['smp-marketplace-id'] = sprintf( __( 'Marketplace ID: %1$s', 'sync-marketplace' ), $marketplace_comment_id );

		return $actions;
	}

	/**
	 * Add row action to reveal the remote marketplace customer ID.
	 *
	 * @param array  $actions Holds the list of actions.
	 * @param object $user Holds the WordPress user object.
	 * @return array
	 */
	public function smp_user_row_actions_callback( $actions, $user ) {

		if ( ! smp_is_user_customer( $user->ID ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		/* translators: %s: user ID */
		$actions['smp-user-id'] = sprintf( __( 'ID: %1$s', 'sync-marketplace' ), $user->ID );

		// Get the marketplace customer ID.
		$marketplace_customer_id = get_user_meta( $user->ID, 'synced_marketplace_with_id', true );

		if ( empty( $marketplace_customer_id ) ) {
			return $actions;
		}

		/* translators: %s: marketplace user ID */
		$actions['smp-marketplace-user-id'] = sprintf( __( 'Marketplace ID: %1$s', 'sync-marketplace' ), $marketplace_customer_id );

		return $actions;
	}

	/**
	 * Show marketplace order ID on admin order listing page.
	 *
	 * @param string $buyer Holds the order buyer name.
	 * @param object $order Holds the WooCommerce order object.
	 * @return string
	 */
	public function smp_woocommerce_admin_order_buyer_name_callback( $buyer, $order ) {
		$order_id = $order->get_id();

		$remote_order_id = (int) get_post_meta( $order_id, 'synced_marketplace_with_id', true );
		$buyer          .= ( 0 !== $remote_order_id ) ? sprintf( __( '%2$s[Marketplace ID: %1$d]', 'sync-marketplace' ), $remote_order_id, "\n" ) : '';

		return $buyer;
	}

	/**
	 * This filter is added to ensure image transfers on http (local) domains.
	 *
	 * @return boolean
	 */
	public function smp_http_request_host_is_external_callback() {

		return true;
	}

	/**
	 * Show the admin notice in case the admin settings are not setup.
	 */
	public function smp_admin_notices_callback() {
		// Check if required settings are saved.
		$associated_vendor = get_option( 'smp_vendor_id_at_marketplace' );
		$marketplace_url   = get_option( 'smp_marketplace_url' );
		$consumer_key      = get_option( 'smp_marketplace_rest_api_consumer_key' );
		$consumer_secret   = get_option( 'smp_marketplace_rest_api_consumer_secret_key' );

		if (
			empty( $associated_vendor ) ||
			empty( $marketplace_url ) ||
			empty( $consumer_key ) ||
			empty( $consumer_secret )
		) {
			/* translators: 1: %s: opening anchor tag, 2: %s: closing anchor tag */
			echo wp_kses_post( smp_get_admin_error_message_html( sprintf( __( 'You need to setup sync plugin settings in order to sync data to the marketplace. %1$sClick here%2$s.', 'sync-marketplace' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=sync-marketplace' ) ) . '">', '</a>' ) ) );
		}
	}

	/**
	 * Create and update the coupon at the marketplace.
	 *
	 * @param object $coupon Holds the woocommerce coupon object.
	 */
	public function smp_woocommerce_coupon_object_updated_props_callback( $coupon ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $coupon ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$coupon_data = smp_get_coupon_data( $coupon );

		if ( false === $coupon_data ) {
			return;
		}

		$coupon_id = $coupon->get_id();

		try {
			// Fetch the marketplace coupon with similar ID.
			$marketplace_coupon_id = get_post_meta( $coupon_id, 'synced_marketplace_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created coupon, will not have the synced marketplace coupon ID.
			 * Thus, we'll assign the synced ID same as the vendor's coupon ID, which will be helpful in fetching the coupon actual details.
			 */
			if ( empty( $marketplace_coupon_id ) || false === $marketplace_coupon_id ) {
				$marketplace_coupon_id = $coupon_id;
			}
			$marketplace_coupon = $woo->get( "coupons/{$marketplace_coupon_id}" );

			if ( ! empty( $marketplace_coupon ) && 'object' === gettype( $marketplace_coupon ) ) {
				$remote_coupon = $woo->put( "coupons/{$marketplace_coupon_id}", $coupon_data );

				if ( ! empty( $remote_coupon->id ) ) {
					// Write the log.
					smp_write_sync_log( "SUCCESS: Updated the coupon at marketplace with ID {$remote_coupon->id}. At vendor's store: {$coupon_id}." );
				}
			}
		} catch ( HttpClientException $e ) {
			// If you're here, this means that coupon with this ID doesn't exist.
			if ( false !== stripos( $e->getMessage(), 'woocommerce_rest_shop_coupon_invalid_id' ) ) {
				$remote_coupon = $woo->post( 'coupons', $coupon_data );

				// Add a remote coupon ID to the meta of this coupon ID, for updating the same coupon in future instances.
				if ( ! empty( $remote_coupon->id ) ) {
					update_post_meta( $coupon_id, 'synced_marketplace_with_id', $remote_coupon->id );

					// Write the log.
					smp_write_sync_log( "SUCCESS: Coupon created at marketplace with ID: {$remote_coupon->id}. At vendor's store: {$coupon_id}." );
				}
			}
		}
	}

	/**
	 * Delete the post at marketplace on deleting the post.
	 *
	 * @param int $post_id Holds the deleted post ID.
	 */
	public function smp_before_delete_post_callback( $post_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$remote_post_id = get_post_meta( $post_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		switch ( $post_type ) {
			case 'shop_coupon':
				self::smp_delete_marketplace_coupon( $post_id, $remote_post_id, $woo );
				break;

			case 'shop_order':
				self::smp_delete_marketplace_order( $post_id, $remote_post_id, $woo );
				break;

			case 'product':
				self::smp_delete_marketplace_product( $post_id, $remote_post_id, $woo );
				break;

			default:
				return;
		}
	}

	/**
	 * Delete the coupon at remote end.
	 *
	 * @param int    $post_id Holds the coupon ID.
	 * @param int    $remote_post_id Holds the remote coupon ID.
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_delete_marketplace_coupon( $post_id, $remote_post_id, $woo ) {
		try {
			$woo->delete(
				"coupons/{$remote_post_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted the coupon at marketplace with ID {$remote_post_id}. At vendor's store: {$post_id}" );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete coupon, {$post_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete the product at remote end.
	 *
	 * @param int    $post_id Holds the product ID.
	 * @param int    $remote_post_id Holds the remote product ID.
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_delete_marketplace_product( $post_id, $remote_post_id, $woo ) {
		try {
			$woo->delete(
				"products/{$remote_post_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted the product at marketplace with ID {$remote_post_id}. At vendor's store: {$post_id}" );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete product, {$post_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete the order at remote end.
	 *
	 * @param int    $post_id Holds the order ID.
	 * @param int    $remote_post_id Holds the remote order ID.
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_delete_marketplace_order( $post_id, $remote_post_id, $woo ) {
		try {
			$woo->delete(
				"orders/{$remote_post_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted the order at marketplace with ID {$remote_post_id}. At vendor's store: {$post_id}" );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete order, {$post_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Sync new customer data to the marketplace.
	 *
	 * @param int $user_id Holds the customer ID.
	 */
	public function smp_user_register_callback( $user_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			return;
		}

		if ( ! smp_is_user_customer( $user_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$user          = get_userdata( $user_id );
		$customer_data = smp_get_customer_data( $user );

		if ( false === $customer_data ) {
			return;
		}

		try {
			$remote_customer = $woo->post( 'customers', $customer_data );
			// Add a remote user ID to the meta of this user ID, for updating the same user in future instances.
			if ( ! empty( $remote_customer->id ) ) {
				update_user_meta( $user_id, 'synced_marketplace_with_id', $remote_customer->id );
				// Write the log.
				smp_write_sync_log( "SUCCESS: Customer created at marketplace with ID: {$remote_customer->id}. Vendor's customer ID: {$user_id}." );
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "ERROR: Cannot create customer {$user_id} at marketplace due to the error: {$error_message}." );
		}
	}

	/**
	 * Sync the changes made in customer profile, with the customer data on marketplace.
	 *
	 * @param int $user_id Holds the customer ID.
	 */
	public function smp_profile_update_callback( $user_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			return;
		}

		if ( ! smp_is_user_customer( $user_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$user               = get_userdata( $user_id );
		$remote_customer_id = get_user_meta( $user_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_customer_id ) ) {
			return;
		}

		$customer_data = smp_get_customer_data( $user, $remote_customer_id );

		if ( false === $customer_data ) {
			return;
		}

		try {
			$remote_customer = $woo->put( "customers/{$remote_customer_id}", $customer_data );

			if ( ! empty( $remote_customer->id ) ) {
				// Write the log.
				smp_write_sync_log( "SUCCESS: Customer updated at marketplace. Marketplace customer ID: {$remote_customer_id}. Vendor's customer ID: {$user_id}" );
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "ERROR: Cannot update customer {$user_id} at marketplace due to the error: {$error_message}." );
		}
	}

	/**
	 * Delete the remote customer from marketplace.
	 *
	 * @param int $user_id Holds the deleted user ID.
	 */
	public function smp_delete_user_callback( $user_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			return;
		}

		if ( ! smp_is_user_customer( $user_id ) ) {
			return;
		}

		$user_data = get_userdata( $user_id );

		if ( false === $user_data ) {
			return;
		}

		$remote_customer_id = get_user_meta( $user_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_customer_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		try {
			$woo->delete(
				"customers/{$remote_customer_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted a customer at marketplace with ID {$remote_customer_id}. At vendor's store: {$user_id}" );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete the customer, {$user_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Post the created term to the marketplace.
	 *
	 * @param int    $term_id Holds the currently created term ID.
	 * @param int    $term_taxonomy_id Holds the currently created taxonomy term ID.
	 * @param string $taxonomy Holds the taxonomy name.
	 */
	public function smp_created_term_callback( $term_id, $term_taxonomy_id, $taxonomy ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$term_data = smp_get_taxonomy_term_data( $term_id );

		if ( false === $term_data || ! is_array( $term_data ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Blank term data found while creating term {$term_id}." );
			return;
		}

		if ( 'product_cat' === $taxonomy ) {
			try {
				$remote_term = $woo->post( 'products/categories', $term_data );
				// Add remote term ID to the term meta.
				if ( ! empty( $remote_term->id ) ) {
					update_term_meta( $term_id, 'synced_marketplace_with_id', $remote_term->id );
					// Write the log.
					smp_write_sync_log( "SUCCESS: Created category term at marketplace with ID {$remote_term->id}. At vendor's store: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't create category term, {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		} elseif ( 'product_tag' === $taxonomy ) {
			try {
				$remote_term = $woo->post( 'products/tags', $term_data );
				// Add remote term ID to the term meta.
				if ( ! empty( $remote_term->id ) ) {
					update_term_meta( $term_id, 'synced_marketplace_with_id', $remote_term->id );
					// Write the log.
					smp_write_sync_log( "SUCCESS: Created tag term at marketplace with ID {$remote_term->id}. At vendor's store: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't create product tag term {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		} elseif ( 'product_shipping_class' === $taxonomy ) {
			try {
				$remote_term = $woo->post( 'products/shipping_classes', $term_data );
				// Add remote term ID to the term meta.
				if ( ! empty( $remote_term->id ) ) {
					update_term_meta( $term_id, 'synced_marketplace_with_id', $remote_term->id );
					// Write the log.
					smp_write_sync_log( "SUCCESS: Created shipping class at marketplace with ID {$remote_term->id}. At vendor's store: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't create product shipping class {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		} else {
			// Check if the taxonomy is one of the woocommerce product attributes.
			if ( false === stripos( $taxonomy, 'pa_' ) ) {
				return;
			}

			// Gather the remote attributes.
			$saved_remote_attributes = get_option( 'smp_saved_remote_attributes' );

			if ( empty( $saved_remote_attributes ) || false === $saved_remote_attributes ) {
				return;
			}

			$remote_taxonomy_id = false;
			foreach ( $saved_remote_attributes as $remote_attribute ) {
				if ( 'pa_' . $remote_attribute['slug'] === $taxonomy ) {
					$remote_taxonomy_id = $remote_attribute['remote_id'];
					break;
				}
			}

			if ( false === $remote_taxonomy_id ) {
				return;
			}

			try {
				$remote_term = $woo->post( "products/attributes/{$remote_taxonomy_id}/terms", $term_data );
				// Add remote term ID to the term meta.
				if ( ! empty( $remote_term->id ) ) {
					update_term_meta( $term_id, 'synced_marketplace_with_id', $remote_term->id );
					// Write the log.
					smp_write_sync_log( "SUCCESS: Created product attribute term at marketplace with ID {$remote_term->id}. At vendor's store: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't create product attribute term {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		}
	}

	/**
	 * Delete the remote product category term.
	 *
	 * @param int    $term_id Holds the term ID getting deleted.
	 * @param string $taxonomy Holds the taxonomy title.
	 */
	public function smp_pre_delete_term_callback( $term_id, $taxonomy ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $term_id ) ) {
			return;
		}

		if ( null === get_term( $term_id ) ) {
			return;
		}

		if ( empty( $taxonomy ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$remote_term_id = get_term_meta( $term_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_term_id ) ) {
			return;
		}

		if ( 'product_cat' === $taxonomy ) {
			try {
				$woo->delete(
					"products/categories/{$remote_term_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				smp_write_sync_log( "SUCCESS: Deleted a category term at marketplace with ID: {$remote_term_id}. At vendor's store: {$term_id}." );
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't delete category term {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		} elseif ( 'product_tag' === $taxonomy ) {
			try {
				$woo->delete(
					"products/tags/{$remote_term_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				smp_write_sync_log( "SUCCESS: Deleted a tag term at marketplace with ID: {$remote_term_id}. At vendor's store: {$term_id}." );
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't delete tag term {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		} elseif ( 'product_shipping_class' === $taxonomy ) {
			try {
				$woo->delete(
					"products/shipping_classes/{$remote_term_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				smp_write_sync_log( "SUCCESS: Deleted a shipping class at marketplace with ID: {$remote_term_id}. At vendor's store: {$term_id}." );
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't delete shipping class {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		} else {
			// Check if the taxonomy is one of the woocommerce product attributes.
			if ( false === stripos( $taxonomy, 'pa_' ) ) {
				return;
			}

			$saved_remote_attributes = get_option( 'smp_saved_remote_attributes' );

			if ( empty( $saved_remote_attributes ) ) {
				return;
			}

			$remote_taxonomy_id = false;

			foreach ( $saved_remote_attributes as $remote_attribute ) {
				if ( 'pa_' . $remote_attribute['slug'] === $taxonomy ) {
					$remote_taxonomy_id = $remote_attribute['remote_id'];
					break;
				}
			}

			if ( false === $remote_taxonomy_id ) {
				return;
			}

			try {
				$woo->delete(
					"products/attributes/{$remote_taxonomy_id}/terms/{$remote_term_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				smp_write_sync_log( "SUCCESS: Deleted a product attribute term at marketplace with ID {$remote_term_id}. At vendor's store: {$term_id}" );
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't delete product attribute {$taxonomy} with ID {$term_id} due to the error: {$e->getMessage()}." );
			}
		}
	}

	/**
	 * Post the updated term to the marketplace.
	 *
	 * @param int    $term_id Holds the currently updated term ID.
	 * @param int    $term_taxonomy_id Holds the currently updated taxonomy term ID.
	 * @param string $taxonomy Holds the taxonomy name.
	 */
	public function smp_edited_term_callback( $term_id, $term_taxonomy_id, $taxonomy ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $term_id ) || null === get_term( $term_id ) ) {
			return;
		}

		if ( empty( $taxonomy ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid WC Rest API client object while updating term {$term_id}, couldn't be updated." );
			return;
		}

		// Get the term data.
		$term_data = smp_get_taxonomy_term_data( $term_id );

		if ( empty( $term_data ) || ! is_array( $term_data ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Blank term data found while updating term {$term_id}, couldn't be updated." );
			return;
		}

		// Get the remote term data.
		$remote_term_id = get_term_meta( $term_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_term_id ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Remote term ID not found while updating term {$term_id}, couldn't be updated." );
			return;
		}

		if ( 'product_cat' === $taxonomy ) {
			try {
				$remote_term = $woo->put( "products/categories/{$remote_term_id}", $term_data );

				// Write the log.
				if ( ! empty( $remote_term->id ) ) {
					smp_write_sync_log( "SUCCESS: Updated the term at marketplace with ID: {$remote_term->id}. At vendor end: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't update the term due to the error: {$e->getMessage()}." );
				return;
			}
		} elseif ( 'product_tag' === $taxonomy ) {
			try {
				$remote_term = $woo->put( "products/tags/{$remote_term_id}", $term_data );

				// Write the log.
				if ( ! empty( $remote_term->id ) ) {
					smp_write_sync_log( "SUCCESS: Updated the term at marketplace with ID: {$remote_term->id}. At vendor end: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't update the term due to the error: {$e->getMessage()}." );
				return;
			}
		} elseif ( 'product_shipping_class' === $taxonomy ) {
			try {
				$remote_term = $woo->put( "products/shipping_classes/{$remote_term_id}", $term_data );

				// Write the log.
				if ( ! empty( $remote_term->id ) ) {
					smp_write_sync_log( "SUCCESS: Updated the term at marketplace with ID: {$remote_term->id}. At vendor end: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't update the term due to the error: {$e->getMessage()}." );
				return;
			}
		} else {
			$remote_taxonomy_id = false;

			if ( false === stripos( $taxonomy, 'pa_' ) ) {
				return;
			}

			$saved_remote_attributes = get_option( 'smp_saved_remote_attributes' );

			if ( empty( $saved_remote_attributes ) || ! is_array( $saved_remote_attributes ) ) {
				return;
			}

			// Loop through the created attributes to know the remote ID of the taxonomy.
			foreach ( $saved_remote_attributes as $remote_attribute ) {

				if ( 'pa_' . $remote_attribute['slug'] === $taxonomy ) {
					$remote_taxonomy_id = $remote_attribute['remote_id'];
					break;
				}
			}

			if ( false === $remote_taxonomy_id ) {
				return;
			}

			try {
				$remote_term = $woo->put( "products/attributes/{$remote_taxonomy_id}/terms/{$remote_term_id}", $term_data );
				// Write log.
				if ( ! empty( $remote_term->id ) ) {
					smp_write_sync_log( "SUCCESS: Updated term at marketplace with ID: {$remote_term->id}. At vendor's store: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't update the term {$term_id} at marketplace due to the error: {$e->getMessage()}." );
			}
		}
	}

	/**
	 * Post the created attribute to the marketplace.
	 *
	 * @param int   $attr_id Holds the attribute ID.
	 * @param array $attr_data Holds the created attribute data.
	 */
	public function smp_woocommerce_attribute_added_callback( $attr_id, $attr_data ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $attr_id ) ) {
			return;
		}

		if ( empty( $attr_data ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$attribute_data = smp_prepare_product_attribute_data( $attr_id, $attr_data );

		if ( empty( $attribute_data ) || ! is_array( $attribute_data ) ) {
			return;
		}

		try {
			$remote_attribute = $woo->post( 'products/attributes', $attribute_data );
			// Add a remote product attribute ID to term meta.
			if ( ! empty( $remote_attribute->id ) ) {
				// Save the remote attributes in database.
				$saved_remote_attributes = get_option( 'smp_saved_remote_attributes' );

				if ( empty( $saved_remote_attributes ) || false === $saved_remote_attributes ) {
					$saved_remote_attributes = array();
				}

				$saved_remote_attributes[ $attr_id ] = array(
					'id'        => $attr_id,
					'name'      => $attribute_data['name'],
					'slug'      => $attribute_data['slug'],
					'remote_id' => $remote_attribute->id,
				);
				update_option( 'smp_saved_remote_attributes', $saved_remote_attributes, false );

				// Write the log.
				if ( ! empty( $remote_attribute->id ) ) {
					smp_write_sync_log( "SUCCESS: Added product attribute at marketplace with ID: {$attr_id}." );
				}
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't add product attribute {$attr_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Post the updated attribute to the marketplace.
	 *
	 * @param int   $attr_id Holds the attribute ID.
	 * @param array $attr_data Holds the created attribute data.
	 */
	public function smp_woocommerce_attribute_updated_callback( $attr_id, $attr_data ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $attr_id ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid attribute ID {$attr_id}, could update at marketplace." );
			return;
		}

		if ( empty( $attr_data ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid attribute object for ID {$attr_id}, could update at marketplace." );
			return;
		}

		$saved_remote_attributes = get_option( 'smp_saved_remote_attributes' );

		if ( empty( $saved_remote_attributes ) || ! is_array( $saved_remote_attributes ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: No saved attributes found while updating {$attr_id}, could update at marketplace." );
			return;
		}

		if ( ! array_key_exists( $attr_id, $saved_remote_attributes ) ) {
			return;
		}

		if ( empty( $saved_remote_attributes[ $attr_id ]['remote_id'] ) ) {
			return;
		}

		$remote_attr_id = $saved_remote_attributes[ $attr_id ]['remote_id'];

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid WC Rest API client object found while updating attribute {$attr_id}." );
			return;
		}

		$attribute_data = smp_prepare_product_attribute_data( $attr_id, $attr_data );

		if ( false === $attribute_data ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Blank attribute array found while updating attribute: {$attr_id}." );
			return;
		}

		try {
			$remote_attribute = $woo->put( "products/attributes/{$remote_attr_id}", $attribute_data );

			// Update the options table for remote attribute.
			if ( ! empty( $remote_attribute->id ) ) {
				$saved_remote_attributes[ $attr_id ]['name'] = $attribute_data['name'];
				$saved_remote_attributes[ $attr_id ]['slug'] = $attribute_data['slug'];
				update_option( 'smp_saved_remote_attributes', $saved_remote_attributes, false );

				// Write the log.
				smp_write_sync_log( "SUCCESS: Updated product attribute at marketplace with ID {$remote_attribute->id}. At vendor's store: {$attr_id}" );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't update the attribute {$attr_id} due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete the remote attribute on deleting the attribute on the vendor's end.
	 *
	 * @param int $attr_id Holds the attribute ID to be deleted.
	 */
	public function smp_woocommerce_before_attribute_delete_callback( $attr_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		$saved_remote_attributes = get_option( 'smp_saved_remote_attributes' );

		if ( empty( $saved_remote_attributes ) || ! is_array( $saved_remote_attributes ) ) {
			return;
		}

		if ( ! array_key_exists( $attr_id, $saved_remote_attributes ) ) {
			return;
		}

		if ( empty( $saved_remote_attributes[ $attr_id ]['remote_id'] ) ) {
			return;
		}

		$remote_attr_id = $saved_remote_attributes[ $attr_id ]['remote_id'];

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		try {
			$woo->delete(
				"products/attributes/{$remote_attr_id}",
				array(
					'force' => true,
				)
			);

			// Manage the saved attributes.
			unset( $saved_remote_attributes[ $attr_id ] );

			if ( empty( $saved_remote_attributes ) ) {
				delete_option( 'smp_saved_remote_attributes' );
			} else {
				update_option( 'smp_saved_remote_attributes', $saved_remote_attributes, false );
			}

			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted product attribute, {$attr_id} at marketplace with ID: {$remote_attr_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete product attribute, {$attr_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Create/Update the product at marketplace.
	 *
	 * @param int    $product_id Holds the woocommerce product ID.
	 * @param object $product Holds the woocommerce product object.
	 */
	public function smp_woocommerce_update_product_callback( $product_id, $product ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $product_id ) ) {
			return;
		}

		if ( empty( $product ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$product_data = smp_get_product_data( $product );

		if ( false === $product_data ) {
			return;
		}

		try {
			// Fetch the marketplace product with similar ID.
			$marketplace_product_id = get_post_meta( $product_id, 'synced_marketplace_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created product, will not have the synced marketplace product ID.
			 * Thus, we'll assign the synced ID same as the vendor's product ID, which will be helpful in fetching the product actual details.
			 */
			if ( empty( $marketplace_product_id ) || false === $marketplace_product_id ) {
				$marketplace_product_id = $product_id;
			}

			$marketplace_product = $woo->get( "products/{$marketplace_product_id}" );
			if ( ! empty( $marketplace_product ) && 'object' === gettype( $marketplace_product ) ) {
				try {
					$remote_product = $woo->put( "products/{$marketplace_product_id}", $product_data );
					// Write the log.
					if ( ! empty( $remote_product->id ) ) {
						smp_write_sync_log( "SUCCESS: Updated product at marketplace with ID: {$remote_product->id}. At vendor's store: {$product_id}." );
					}
				} catch ( HttpClientException $e ) {
					// Write the log.
					smp_write_sync_log( "ERROR: Error in updating the product at marketplace due to the error: {$e->getMessage()}." );
				}
			}
		} catch ( HttpClientException $e ) {
			$product_error_message = $e->getMessage();
			// If you're here, this means that product with this ID doesn't exist.
			if ( false !== stripos( $product_error_message, 'woocommerce_rest_product_invalid_id' ) ) {
				try {
					$remote_product = $woo->post( 'products', $product_data );
					// Add a remote product ID to the meta of this product.
					if ( ! empty( $remote_product->id ) ) {
						update_post_meta( $product_id, 'synced_marketplace_with_id', $remote_product->id );

						// Write the log.
						smp_write_sync_log( "Product created at marketplace, where product ID: {$remote_product->id} and vendor's product ID: {$product_id}." );
					}
				} catch ( HttpClientException $e ) {
					smp_write_sync_log( "ERROR: Error in creating the product at marketplace due to the error: {$e->getMessage()}." );
				}
			}
		}
	}

	/**
	 * Post the variation data to the marketplace.
	 *
	 * @param int $variation_id Holds the variation ID.
	 */
	public function smp_woocommerce_save_product_variation_callback( $variation_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $variation_id ) ) {
			return;
		}

		$variation = wc_get_product( $variation_id );

		if ( empty( $variation ) ) {
			return;
		}

		$variation_parent_id = $variation->get_parent_id();

		if ( 0 === $variation_parent_id ) {
			return;
		}

		// Get the remote parent ID.
		$remote_parent_id = get_post_meta( $variation_parent_id, 'synced_marketplace_with_id', true );

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$variation_data = smp_get_variation_data( $variation, $remote_parent_id );

		if ( false === $variation_data ) {
			return;
		}

		try {
			// Fetch the marketplace variation with similar ID.
			$marketplace_variation_id = get_post_meta( $variation_id, 'synced_marketplace_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created variation, will not have the synced marketplace variation ID.
			 * Thus, we'll assign the synced ID same as the vendor's variation ID, which will be helpful in fetching the variation actual details.
			 */
			if ( empty( $marketplace_variation_id ) || false === $marketplace_variation_id ) {
				$marketplace_variation_id = $variation_id;
			}

			$marketplace_variation = $woo->get( "products/{$remote_parent_id}/variations/{$marketplace_variation_id}" );
			if ( ! empty( $marketplace_product ) && 'object' === gettype( $marketplace_product ) ) {
				$remote_variation = $woo->put( "products/{$marketplace_variation_id}", $variation_data );

				// Write the log.
				if ( ! empty( $remote_variation->id ) ) {
					smp_write_sync_log( "SUCCESS: Updated a variation at marketplace with ID: {$remote_variation->id}. At vendor: {$variation_id}." );
				}
			}
		} catch ( HttpClientException $e ) {
			$variation_error_message = $e->getMessage();
			// If you're here, this means that variation with this ID doesn't exist.
			if ( false !== stripos( $variation_error_message, 'woocommerce_rest_product_variation_invalid_id' ) ) {
				$remote_variation = $woo->post( "products/{$remote_parent_id}/variations", $variation_data );

				// Add a remote product ID to the meta of this product.
				if ( ! empty( $remote_variation->id ) ) {
					update_post_meta( $variation_id, 'synced_marketplace_with_id', $remote_variation->id );

					// Write the log.
					smp_write_sync_log( "Variation created at marketplace. Marketplace variation ID is {$remote_variation->id} and vendor's variation ID is {$variation_id}." );
				}
			}
		}
	}

	/**
	 * Delete the variation from remote.
	 *
	 * @param int $variation_id Holds the variation ID.
	 */
	public function smp_woocommerce_before_delete_product_variation_callback( $variation_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $variation_id ) ) {
			return;
		}

		$variation = wc_get_product( $variation_id );

		if ( empty( $variation ) ) {
			return;
		}

		$variation_parent_id = $variation->get_parent_id();

		if ( 0 === $variation_parent_id ) {
			return;
		}

		// Get the remote parent ID.
		$remote_parent_id = get_post_meta( $variation_parent_id, 'synced_marketplace_with_id', true );

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$marketplace_variation_id = get_post_meta( $variation_id, 'synced_marketplace_with_id', true );
		try {
			$woo->delete(
				"products/{$remote_parent_id}/variations/{$marketplace_variation_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			smp_write_sync_log( "Successfully deleted variation: {$variation_id}." );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "Error: {$error_message} while deleting variation {$variation_id}." );
		}
	}

	/**
	 * Post the created order to the marketplace.
	 *
	 * @param int $order_id Holds the order ID.
	 */
	public function smp_woocommerce_thankyou_callback( $order_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( false === $order ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$order_data = smp_get_order_data( $order );
		try {
			$remote_order = $woo->post( 'orders', $order_data );

			// Add a remote order ID to the meta of this order.
			if ( ! empty( $remote_order->id ) ) {
				update_post_meta( $order_id, 'synced_marketplace_with_id', $remote_order->id );

				// Write the log.
				smp_write_sync_log( "Order created at marketplace. Marketplace order ID: {$remote_order->id} and vendor's order ID: {$order_id}." );
			}

			// Update the remote items meta -> shipping.
			$order_shipping_methods = $order->get_shipping_methods();

			if ( ! empty( $order_shipping_methods ) && is_array( $order_shipping_methods ) ) {
				foreach ( $order_shipping_methods as $src_line_item_id => $order_shipping_method ) {
					$shipping_method_id = $order_shipping_method->get_method_id();

					if ( ! empty( $remote_order->shipping_lines ) ) {
						foreach ( $remote_order->shipping_lines as $remote_shipping_line ) {
							// Check to match the remote shipping method id with the source.
							if ( $shipping_method_id === $remote_shipping_line->method_id ) {
								wc_add_order_item_meta( $src_line_item_id, 'synced_marketplace_with_id', $remote_shipping_line->id, true );
							}
						}
					}
				}
			}

			// Update the remote items meta -> line items.
			$line_items = $order->get_items();

			if ( empty( $line_items ) || ! is_array( $line_items ) ) {
				return;
			}
		
			if ( empty( $remote_order->line_items ) || ! is_array( $remote_order->line_items ) ) {
				return;
			}
		
			foreach ( $line_items as $src_line_item ) {
				// Find the remote product and variation ID.
				$src_line_item_id = $src_line_item->get_id();
				$product_id       = $src_line_item->get_product_id();
				$variation_id     = $src_line_item->get_variation_id();
				$src_prod_id      = ( 0 === $variation_id ) ? $product_id : $variation_id;
		
				// Fetch the remote id of the source product.
				$src_remote_prod_id = (int) get_post_meta( $src_prod_id, 'synced_marketplace_with_id', true );
		
				// Skip if the product doesn't exist remotely.
				if ( 0 === $src_remote_prod_id ) {
					continue;
				}
		
				// Loop in the remote line items to find the remote product ID.
				foreach ( $remote_order->line_items as $remote_line_item ) {
					$remote_line_item = (array) $remote_line_item;
					
					// Search for the line item in the remote items.
					$index = array_search( $src_remote_prod_id, $remote_line_item, true );
		
					if ( false !== $index ) {
						wc_add_order_item_meta( $src_line_item_id, 'synced_marketplace_with_id', $remote_line_item['id'], true );
					}
				}
			}
		} catch ( HttpClientException $e ) {
			$order_error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "Error while creating order on order completion, thank you page. Error: {$order_error_message}" );
		}
	}

	/**
	 * Update the order at the marketplace.
	 *
	 * @param int    $order_id Holds the order ID.
	 * @param object $order Holds the woocommerce order object.
	 */
	public function smp_woocommerce_update_order_callback( $order_id, $order ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		// Return if it's the checkout order received page.
		$request_uri            = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
		$is_order_received_page = ( false !== stripos( $request_uri, '/order-received/' ) ) ? true : false;

		if ( is_checkout() || $is_order_received_page ) {
			return;
		}

		if ( false === $order ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$order_data = smp_get_order_data( $order );
		try {
			// Fetch the marketplace order with similar ID.
			$marketplace_order_id = get_post_meta( $order_id, 'synced_marketplace_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created order, will not have the synced marketplace order ID.
			 * Thus, we'll assign the synced ID same as the vendor's order ID, which will be helpful in fetching the order actual details.
			 */
			if ( empty( $marketplace_order_id ) || false === $marketplace_order_id ) {
				$marketplace_order_id = $order_id;
			}

			$marketplace_order = $woo->get( "orders/{$marketplace_order_id}" );

			if ( ! empty( $marketplace_order ) && 'object' === gettype( $marketplace_order ) ) {
				do_action( 'smp_before_updating_order_at_marketplace', $marketplace_order_id, $order_data, $order_id, $order );
				$woo->put( "orders/{$marketplace_order_id}", $order_data );
				do_action( 'smp_after_updating_order_at_marketplace', $marketplace_order_id, $order_data, $order_id, $order );
			}
		} catch ( HttpClientException $e ) {
			$order_error_message = $e->getMessage();
			// If you're here, this means that order with this ID doesn't exist.
			if ( false !== stripos( $order_error_message, 'woocommerce_rest_shop_order_invalid_id' ) ) {
				do_action( 'smp_before_posting_order_to_marketplace', $order_data, $order_id, $order );
				$remote_order = $woo->post( 'orders', $order_data );
				do_action( 'smp_after_posting_order_to_marketplace', $order_data, $order_id, $order );
				// Add a remote order ID to the meta of this order.
				if ( ! empty( $remote_order->id ) ) {
					update_post_meta( $order_id, 'synced_marketplace_with_id', $remote_order->id );

					// Write the log.
					smp_write_sync_log( "Order created at marketplace with ID is {$remote_order->id} and vendor's order ID is {$order_id}." );
				}
			}
		}
	}

	/**
	 * Create refund at the marketplace.
	 *
	 * @param int $refund_id Holds the order refund ID.
	 */
	public function smp_woocommerce_refund_created_callback( $refund_id ) {
		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $refund_id ) || 0 === $refund_id ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$order_id = smp_get_order_id_by_refund_id( $refund_id );

		if ( false === $order_id ) {
			return;
		}

		$remote_order_id = get_post_meta( $order_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_order_id ) ) {
			return;
		}

		$refund_data = smp_get_order_refund_data( $refund_id );

		if ( false === $refund_data ) {
			return;
		}

		try {
			$remote_refund = $woo->post( "orders/{$remote_order_id}/refunds", $refund_data );

			if ( ! empty( $remote_refund->id ) ) {
				update_post_meta( $refund_id, 'synced_marketplace_with_id', $remote_refund->id );
				// Write the log.
				smp_write_sync_log( "SUCCESS: Refund created at marketplace with ID {$remote_refund->id}. At vendor's end: {$refund_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Refund couldn't be created at marketplace due to the error {$e->getMessage()}. At vendor's end: {$refund_id}." );
		}
	}

	/**
	 * Delete the refund ID at the marketplace.
	 *
	 * @param int $refund_id Holds the refund ID.
	 * @param int $order_id Holds the order ID.
	 */
	public function smp_woocommerce_refund_deleted_callback( $refund_id, $order_id ) {
		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $refund_id ) || 0 === $refund_id ) {
			return;
		}

		$remote_refund_id = get_post_meta( $refund_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_refund_id ) ) {
			return;
		}

		if ( empty( $order_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$remote_order_id = get_post_meta( $order_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_order_id ) ) {
			return;
		}

		try {
			$woo->delete(
				"orders/{$remote_order_id}/refunds/{$remote_refund_id}",
				array(
					'force' => true,
				)
			);

			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted refund at marketplace with ID {$remote_refund_id}. At vendor's end: {$refund_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Refund couldn't be deleted at marketplace due to the error {$e->getMessage()}. At vendor's end: {$refund_id}." );
		}
	}

	/**
	 * Insert order notes at marketplace order.
	 *
	 * @param int    $comment_id Holds the comment ID.
	 * @param object $comment Holds the WordPress comment object.
	 */
	public function smp_wp_insert_comment_callback( $comment_id, $comment ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		// Gather the post ID.
		$post_id = $comment->comment_post_ID;

		if ( ! 'shop_order' === get_post_type( $post_id ) ) {
			return;
		}

		// Check if the note is to the customer.
		$note_type = filter_input( INPUT_POST, 'note_type', FILTER_SANITIZE_STRING );

		$note_data = array(
			'note'          => $comment->comment_content,
			'customer_note' => ( 'customer' === $note_type ) ? true : false,
		);

		// Fetch the marketplace order with similar ID.
		$marketplace_order_id = get_post_meta( $post_id, 'synced_marketplace_with_id', true );

		try {
			do_action( 'smp_before_posting_order_note_to_marketplace', $note_data, $comment );
			$remote_order_note = $woo->post( "orders/{$marketplace_order_id}/notes", $note_data );
			do_action( 'smp_after_posting_order_note_to_marketplace', $note_data, $comment );
			// Add a remote order ID to the meta of this order.
			if ( ! empty( $remote_order_note->id ) ) {
				update_comment_meta( $comment_id, 'synced_marketplace_with_id', $remote_order_note->id );
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			smp_write_sync_log( "Error occured while creating order note via customer checkout. Error: {$error_message}." );
		}
	}

	/**
	 * Delete order note at marketplace.
	 *
	 * @param int    $comment_id Holds the comment ID.
	 * @param object $comment Holds the WordPress comment object.
	 */
	public function smp_delete_comment_callback( $comment_id, $comment ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		// Check to see if the comment is or order.
		$post_id                = $comment->comment_post_ID;
		$marketplace_comment_id = get_comment_meta( $comment_id, 'synced_marketplace_with_id', true );

		if ( 'shop_order' === get_post_type( $post_id ) ) {
			$marketplace_order_id = get_post_meta( $post_id, 'synced_marketplace_with_id', true );
			try {
				$woo->delete(
					"orders/{$marketplace_order_id}/notes/{$marketplace_comment_id}",
					array(
						'force' => true,
					)
				);
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "Error: {$error_message} while deleting order note {$comment_id}." );
			}
		} elseif ( 'product' === get_post_type( $post_id ) ) {
			// Delete the marketplace product review.
			try {
				$woo->delete(
					"products/reviews/{$marketplace_comment_id}",
					array(
						'force' => true,
					)
				);
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "Error: {$error_message} while deleting product review {$comment_id}." );
			}
		}
	}

	/**
	 * Add custom class to the order notes listing in order edit panel.
	 *
	 * @param array  $note_class Holds the array of order note classes.
	 * @param object $note Holds the woocommerce order note object.
	 * @return array
	 */
	public function smp_woocommerce_order_note_class_callback( $note_class, $note ) {

		if ( empty( $note->id ) ) {
			return $note_class;
		}

		if ( current_user_can( 'manage_options' ) ) {
			$marketplace_id = get_comment_meta( $note->id, 'synced_marketplace_with_id', true );

			if ( ! empty( $marketplace_id ) && false !== $marketplace_id ) {
				$note_class[] = "marketplace-id-{$marketplace_id}";
			}
		}

		return $note_class;
	}

	/**
	 * Post product review to the marketplace.
	 *
	 * @param int $comment_id Holds the comment ID.
	 */
	public function smp_comment_post_callback( $comment_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment_id ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( empty( $comment ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$post_id = $comment->comment_post_ID;

		if ( 'product' === get_post_type( $post_id ) ) {
			$review_data = smp_get_product_review_data( $post_id, $comment );

			if ( false === $review_data ) {
				return;
			}

			try {
				$remote_review = $woo->post( 'products/reviews', $review_data );
				// Add a remote review ID to the meta of this product.
				if ( ! empty( $remote_review->id ) ) {
					update_comment_meta( $comment_id, 'synced_marketplace_with_id', $remote_review->id );

					// Write the log.
					smp_write_sync_log( "Product review created at marketplace with ID {$remote_review->id} and vendor's review ID is {$comment_id}." );
				}
			} catch ( HttpClientException $e ) {
				$review_error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "Error in creating product review. Message: {$review_error_message}." );
			}
		}
	}

	/**
	 * Update the comment on the marketplace.
	 *
	 * @param int $comment_id Holds the comment ID.
	 */
	public function smp_edit_comment_callback( $comment_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment_id ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( empty( $comment ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$post_id = $comment->comment_post_ID;

		if ( 'product' === get_post_type( $post_id ) ) {
			$review_data = smp_get_product_review_data( $post_id, $comment );

			if ( false === $review_data ) {
				return;
			}

			try {
				// Fetch the marketplace review with similar ID.
				$marketplace_review_id = get_comment_meta( $comment_id, 'synced_marketplace_with_id', true );

				/**
				 * This assignment is because the very first time, for a fresh created product review, will not have the synced marketplace review ID.
				 * Thus, we'll assign the synced ID same as the vendor's review ID, which will be helpful in fetching the review actual details.
				 */
				if ( empty( $marketplace_review_id ) || false === $marketplace_review_id ) {
					$marketplace_review_id = $comment_id;
				}

				$marketplace_review = $woo->get( "products/reviews/{$marketplace_review_id}" );

				if ( ! empty( $marketplace_review ) && 'object' === gettype( $marketplace_review ) ) {
					$woo->put( "products/reviews/{$marketplace_review_id}", $review_data );
				}
			} catch ( HttpClientException $e ) {
				$review_error_message = $e->getMessage();

				// If you're here, this means that variation with this ID doesn't exist.
				if ( false !== stripos( $review_error_message, 'woocommerce_rest_review_invalid_id' ) ) {
					$remote_review = $woo->post( 'products/reviews', $review_data );

					// Add a remote review ID to the meta of this product.
					if ( ! empty( $remote_review->id ) ) {
						update_comment_meta( $comment_id, 'synced_marketplace_with_id', $remote_review->id );

						// Write the log.
						smp_write_sync_log( "Product review created at marketplace with ID {$remote_review->id} and vendor's review ID is {$comment_id}." );
					}
				}
			}
		}
	}

	/**
	 * Post woocommerce following settings to the marketplace.
	 * 1. Tax
	 */
	public function smp_woocommerce_settings_saved_callback() {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		global $current_tab;

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		switch ( $current_tab ) {
			case 'tax':
				self::smp_sync_wc_taxes( $woo );
				break;

			default:
				// Write the log.
				smp_write_sync_log( "ERROR: Invalid tab accessed: {$current_tab}." );
		}
	}

	/**
	 * Sync WC taxes.
	 *
	 * @param object $woo Holds the WC Rest API client object.
	 */
	public static function smp_sync_wc_taxes( $woo ) {
		if ( class_exists( 'WC_Tax' ) ) {
			$src_tax_classes = WC_Tax::get_tax_classes();

			// Fetch the pre existing tax classes from the marketplace.
			try {
				$marketplace_tax_classes     = $woo->get( 'taxes/classes' );
				$marketplace_tax_classes_arr = array();

				// Prepare the marketplace tax classes array.
				if ( ! empty( $marketplace_tax_classes ) && is_array( $marketplace_tax_classes ) ) {
					foreach ( $marketplace_tax_classes as $marketplace_tax_class ) {
						$marketplace_tax_classes_arr[] = $marketplace_tax_class->name;
					}
				}

				if ( ! empty( $marketplace_tax_classes_arr ) && ! empty( $src_tax_classes ) ) {
					// Remove the "Standard rate" from the marketplace tax classes.
					$standard_rate_key = array_search( 'Standard rate', $marketplace_tax_classes_arr, true );
					if ( false !== $standard_rate_key ) {
						unset( $marketplace_tax_classes_arr[ $standard_rate_key ] );
					}

					$to_be_created_marketplace_tax_classes = array_diff( $src_tax_classes, $marketplace_tax_classes_arr );

					// Create the taxes at marketplace now.
					if ( ! empty( $to_be_created_marketplace_tax_classes ) && is_array( $to_be_created_marketplace_tax_classes ) ) {
						foreach ( $to_be_created_marketplace_tax_classes as $tax_class ) {
							$tax_data = array(
								'name'                     => $tax_class,
								'synced_vendor_with_id'    => sanitize_title( $tax_class ),
								'smp_associated_vendor_id' => smp_get_vendor_id_at_marketplace(),
							);

							try {
								$woo->post( 'taxes/classes', $tax_data );
								// Write the log.
								smp_write_sync_log( "SUCCESS: Created tax class at marketplace: {$tax_class}." );
							} catch ( HttpClientException $e ) {
								// Write the log.
								smp_write_sync_log( "ERROR: Couldn't create tax class: {$tax_class} at marketplace due to the error: {$e->getMessage()}." );
							}
						}
					}
				}
			} catch ( HttpClientException $e ) {
				$tax_error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "Error in fetching marketplace tax classes. Message: {$tax_error_message}." );
			}
		}
	}

	/**
	 * Post the new tax rate to the marketplace.
	 *
	 * @param int $tax_rate_id Holds the tax rate ID.
	 */
	public function smp_woocommerce_tax_rate_added_callback( $tax_rate_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $tax_rate_id ) ) {
			return;
		}

		$tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

		if ( null === $tax_rate ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$tax_rate_data = smp_get_tax_rate_data( $tax_rate_id, $tax_rate );

		if ( false === $tax_rate_data ) {
			return;
		}

		try {
			$remote_tax_rate = $woo->post( 'taxes', $tax_rate_data );
			// Add a remote tax rate ID to the meta of this tax rate ID.
			if ( ! empty( $remote_tax_rate->id ) ) {
				// Since there isn't anything as tax rate meta, thus saving the remote IDs in the options table.
				$saved_remote_tax_rates = get_option( 'smp_saved_remote_tax_rates' );

				if ( empty( $saved_remote_tax_rates ) || false === $saved_remote_tax_rates ) {
					$saved_remote_tax_rates = array();
				}

				$saved_remote_tax_rates[ $tax_rate_id ] = array(
					'id'        => $tax_rate_id,
					'name'      => $tax_rate_data['name'],
					'rate'      => $tax_rate_data['rate'],
					'remote_id' => $remote_tax_rate->id,
				);
				update_option( 'smp_saved_remote_tax_rates', $saved_remote_tax_rates, false );
			}

			// Write the log.
			smp_write_sync_log( "SUCCESS: Created tax rate at marketplace with ID: {$remote_tax_rate->id}. At vendor's store: {$tax_rate_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't create tax rate: {$tax_rate_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Update the tax rate at the marketplace.
	 *
	 * @param int $tax_rate_id Holds the tax rate ID.
	 */
	public function smp_woocommerce_tax_rate_updated_callback( $tax_rate_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $tax_rate_id ) ) {
			return;
		}

		$tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

		if ( null === $tax_rate ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$tax_rate_data = smp_get_tax_rate_data( $tax_rate_id, $tax_rate );

		if ( false === $tax_rate_data ) {
			return;
		}

		try {
			$remote_tax_rate_id = $tax_rate_data['id'];
			$remote_tax_rate    = $woo->put( "taxes/{$remote_tax_rate_id}", $tax_rate_data );

			// Add a remote tax rate ID to the meta of this attribute ID.
			if ( ! empty( $remote_tax_rate->id ) ) {
				// Since there isn't anything as tax rate meta, thus saving the remote IDs in the options table.
				$saved_remote_tax_rates = get_option( 'smp_saved_remote_tax_rates' );

				if ( empty( $saved_remote_tax_rates ) || false === $saved_remote_tax_rates ) {
					$saved_remote_tax_rates = array();
				}

				$saved_remote_tax_rates[ $tax_rate_id ]['name'] = $tax_rate_data['name'];
				$saved_remote_tax_rates[ $tax_rate_id ]['rate'] = $tax_rate_data['rate'];
				update_option( 'smp_saved_remote_tax_rates', $saved_remote_tax_rates, false );
			}

			// Write the log.
			smp_write_sync_log( "SUCCESS: Updated tax rate at marketplace with ID: {$remote_tax_rate->id}. At vendor's store: {$tax_rate_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't update tax {$tax_rate_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete the remote tax rate.
	 *
	 * @param int $tax_rate_id Holds the tax rate ID.
	 */
	public function smp_woocommerce_tax_rate_deleted_callback( $tax_rate_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $tax_rate_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$remote_tax_rate_id = smp_get_remote_tax_rate_id( $tax_rate_id );

		if ( empty( $remote_tax_rate_id ) ) {
			return;
		}

		try {
			$woo->delete(
				"taxes/{$remote_tax_rate_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			smp_write_sync_log( "SUCCESS: Tax rate deleted at marketplace with ID: {$remote_tax_rate_id}. At vendor's store: {$tax_rate_id}." );

			// Removing the same attribute ID from the options table.
			$saved_remote_tax_rates = get_option( 'smp_saved_remote_tax_rates' );
			unset( $saved_remote_tax_rates[ $tax_rate_id ] );

			// Update the database.
			if ( empty( $saved_remote_tax_rates ) ) {
				delete_option( 'smp_saved_remote_tax_rates' );
			} else {
				update_option( 'smp_saved_remote_tax_rates', $saved_remote_tax_rates, false );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete tax rate, {$tax_rate_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Post the new webhook to the marketplace.
	 *
	 * @param int    $webhook_id Holds the webhook ID.
	 * @param object $webhook Holds the WC webhook object.
	 */
	public function smp_woocommerce_new_webhook_callback( $webhook_id, $webhook ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $webhook_id ) ) {
			return;
		}

		if ( empty( $webhook ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$webhook_data = smp_get_webhook_data( $webhook_id, $webhook );

		if ( false === $webhook_data ) {
			return;
		}

		try {
			$remote_webhook = $woo->post( 'webhooks', $webhook_data );
			// Add a remote webhook ID.
			if ( ! empty( $remote_webhook->id ) ) {
				$saved_remote_webhooks = get_option( 'smp_saved_remote_webhooks' );

				if ( empty( $saved_remote_webhooks ) || false === $saved_remote_webhooks ) {
					$saved_remote_webhooks = array();
				}

				$saved_remote_webhooks[ $webhook_id ] = array(
					'id'        => $webhook_id,
					'name'      => $webhook_data['name'],
					'remote_id' => $remote_webhook->id,
				);
				update_option( 'smp_saved_remote_webhooks', $saved_remote_webhooks, false );

				// Write the log.
				smp_write_sync_log( "SUCCESS: Created webhook at marketplace with ID: {$remote_webhook->id}. At vendor's store: {$webhook_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't create webhook, {$webhook_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Update the webhook at the marketplace.
	 *
	 * @param int $webhook_id Holds the webhook ID.
	 */
	public function smp_woocommerce_webhook_updated_callback( $webhook_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $webhook_id ) ) {
			return;
		}

		$webhook = wc_get_webhook( $webhook_id );

		if ( empty( $webhook ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$webhook_data = smp_get_webhook_data( $webhook_id, $webhook );

		if ( false === $webhook_data ) {
			return;
		}

		try {
			$remote_webhook_id = $webhook_data['id'];
			$remote_webhook    = $woo->put( "webhooks/{$remote_webhook_id}", $webhook_data );

			// Update the database for the changes.
			if ( ! empty( $remote_webhook->id ) ) {
				$saved_remote_webhooks = get_option( 'smp_saved_remote_webhooks' );

				if ( empty( $saved_remote_webhooks ) || false === $saved_remote_webhooks ) {
					$saved_remote_webhooks = array();
				}

				$saved_remote_webhooks[ $webhook_id ]['name'] = $webhook_data['name'];
				update_option( 'smp_saved_remote_webhooks', $saved_remote_webhooks, false );

				// Write the log.
				smp_write_sync_log( "SUCCESS: Updated webhook at marketplace with ID: {$remote_webhook_id}. At vendor's store: {$webhook_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't update the webhook, {$webhook_id} at marketplace due to the error: {$e->getMessage()}" );
		}
	}

	/**
	 * Delete the remote webhook.
	 *
	 * @param int $webhook_id Holds the webhook ID.
	 */
	public function smp_woocommerce_webhook_deleted_callback( $webhook_id ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $webhook_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$remote_webhook_id = smp_get_remote_webhook_id( $webhook_id );

		if ( empty( $remote_webhook_id ) ) {
			return;
		}

		try {
			$woo->delete(
				"webhooks/{$remote_webhook_id}",
				array(
					'force' => true,
				)
			);

			// Removing the same webhook ID from the options table.
			$saved_remote_webhooks = get_option( 'smp_saved_remote_webhooks' );
			unset( $saved_remote_webhooks[ $webhook_id ] );

			// Update the database for remaining webhooks.
			if ( empty( $saved_remote_webhooks ) ) {
				delete_option( 'smp_saved_remote_webhooks' );
			} else {
				update_option( 'smp_saved_remote_webhooks', $saved_remote_webhooks, false );
			}

			// Write the log.
			smp_write_sync_log( "SUCCESS: Deleted webhook, {$webhook_id} at marketplace with ID: {$remote_webhook_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Couldn't delete webhook, {$webhook_id} at marketplace due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Post the shipping zones to the marketplace.
	 *
	 * @param object $shipping_zone Holds the WC shipping zone data object.
	 */
	public function smp_woocommerce_after_shipping_zone_object_save_callback( $shipping_zone ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		$shipping_zone_id = $shipping_zone->get_id();

		if ( empty( $shipping_zone_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		$shipping_zone_name = $shipping_zone->get_zone_name();
		$shipping_zone_data = array(
			'name'                     => $shipping_zone_name,
			'order'                    => $shipping_zone->get_zone_order(),
			'synced_vendor_with_id'    => $shipping_zone_id,
			'smp_associated_vendor_id' => smp_get_vendor_id_at_marketplace(),
		);

		$remote_shipping_zone_id = smp_get_remote_shipping_zone_id( $shipping_zone_id );

		if ( false !== $remote_shipping_zone_id ) {
			// Add the remote shipping zone ID to the posted data.
			$shipping_zone_data['id'] = $remote_shipping_zone_id;

			try {
				$remote_shipping_zone = $woo->put( "shipping/zones/{$remote_shipping_zone_id}", $shipping_zone_data );

				// Save a remote shipping zone ID in database.
				if ( ! empty( $remote_shipping_zone->id ) ) {
					$saved_remote_shipping_zones = get_option( 'smp_saved_remote_shipping_zones' );

					if ( empty( $saved_remote_shipping_zones ) || false === $saved_remote_shipping_zones ) {
						$saved_remote_shipping_zones = array();
					}

					$saved_remote_shipping_zones[ $shipping_zone_id ]['name'] = $shipping_zone_name;
					update_option( 'smp_saved_remote_shipping_zones', $saved_remote_shipping_zones, false );

					// Write the log.
					smp_write_sync_log( "SUCCESS: Updated shipping zone at marketplace. ID: {$shipping_zone_id}." );
				}
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't update shipping zone id, {$shipping_zone_id}. Error: {$error_message}." );
			}
		} else {
			try {
				$remote_shipping_zone = $woo->post( 'shipping/zones', $shipping_zone_data );

				// Save a remote shipping zone ID in database.
				if ( ! empty( $remote_shipping_zone->id ) ) {
					$saved_remote_shipping_zones = get_option( 'smp_saved_remote_shipping_zones' );

					if ( empty( $saved_remote_shipping_zones ) || false === $saved_remote_shipping_zones ) {
						$saved_remote_shipping_zones = array();
					}

					$saved_remote_shipping_zones[ $shipping_zone_id ] = array(
						'id'        => $shipping_zone_id,
						'name'      => $shipping_zone_name,
						'remote_id' => $remote_shipping_zone->id,
					);
					update_option( 'smp_saved_remote_shipping_zones', $saved_remote_shipping_zones, false );

					// Write the log.
					smp_write_sync_log( "SUCCESS: Created shipping zone id at marketplace with ID: {$remote_shipping_zone->id}. At vendor's store: {$shipping_zone_id}." );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't create shipping zone, {$shipping_zone_id} due to the error: {$e->getMessage()}." );
			}
		}
	}

	/**
	 * Cron jobs at vendor's store.
	 */
	public function smp_smp_sync_marketplace_cron_callback() {

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			return;
		}

		// Check to see if the relating class exists.
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return;
		}

		// Check to see if the relating class exists.
		if ( ! class_exists( 'WC_Tax' ) ) {
			return;
		}

		self::smp_delete_shipping_zones_cron( $woo );
		self::smp_update_tax_rates_cron( $woo );
		self::smp_update_shipping_locations_cron( $woo );
		self::smp_update_shipping_methods_cron( $woo );
	}

	/**
	 * Cron job to delete the shipping zones at marketplace.
	 *
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_delete_shipping_zones_cron( $woo ) {
		// Write the log.
		smp_write_sync_log( 'WC SHIPPING ZONE CRON: Initiating the shipping zones deletion cron..' );

		$saved_shipping_zones = get_option( 'smp_saved_remote_shipping_zones' );

		if ( empty( $saved_shipping_zones ) || ! is_array( $saved_shipping_zones ) ) {
			// Write the log.
			smp_write_sync_log( 'WC SHIPPING ZONE CRON: No shipping zones found to be deleted. Hence the cron ends here.' );
			return;
		}

		// Loop in every shipping zone ID to delete the same at marketplace.
		foreach ( $saved_shipping_zones as $zone_id => $zone_data ) {
			// Check to see if the shipping zone exists with the saved ID.
			$zone = WC_Shipping_Zones::get_zone( $zone_id );

			if ( false !== $zone ) {
				// This means that the zone exists on the vendor's website and need not to be deleted.
				continue;
			}

			// Get the remote shipping zone ID.
			$remote_zone_id = smp_get_remote_shipping_zone_id( $zone_id );

			if (
				empty( $remote_zone_id ) ||
				false === $remote_zone_id ||
				null === $remote_zone_id
			) {
				// Write the log.
				smp_write_sync_log( "WC SHIPPING ZONE CRON: No remote shipping zone ID found for zone: {$zone_id}." );
				continue;
			}

			try {
				$woo->delete(
					"shipping/zones/{$remote_zone_id}",
					array(
						'force' => true,
					)
				);

				// Remove the entry from the database also.
				unset( $saved_shipping_zones[ $zone_id ] );

				// Write the log.
				smp_write_sync_log( "WC SHIPPING ZONE CRON: SUCCESS: Deleted the shipping zone at the marketplace with ID: {$remote_zone_id}. At vendor's store: {$zone_id}." );
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "WC SHIPPING ZONE CRON: ERROR: Couldn't delete shipping zone, {$zone_id} at marketplace, {$remote_zone_id}. due to the error: {$e->getMessage()}." );
			}
		}

		// Finally, update the database to know about the deleted shipping zone IDs.
		delete_option( 'smp_saved_remote_shipping_zones' );

		// Write the log.
		smp_write_sync_log( 'WC SHIPPING ZONE CRON: Shipping zone deletion cron ends..' );
	}

	/**
	 * Update the tax rates at the marketplace to keep in sync.
	 *
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_update_tax_rates_cron( $woo ) {
		global $wpdb;

		// Write the log.
		smp_write_sync_log( 'WC TAX CRON: Initiating the tax rates updation cron..' );

		$tax_rate_table = $wpdb->prefix . 'woocommerce_tax_rates';
		$results        = $wpdb->get_results( $wpdb->prepare( 'SELECT `tax_rate_id` FROM %s', $tax_rate_table ), ARRAY_A );

		if ( empty( $results ) ) {
			// Write the log.
			smp_write_sync_log( 'WC TAX CRON: No tax rates available to update.' );
			return;
		}

		foreach ( $results as $tax_rate ) {
			$tax_rate_id = $tax_rate['tax_rate_id'];
			$wc_tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

			if ( null === $wc_tax_rate ) {
				continue;
			}

			$tax_rate_data = smp_get_tax_rate_data( $tax_rate_id, $wc_tax_rate );

			if ( empty( $tax_rate_data['id'] ) ) {
				continue;
			}

			$remote_tax_rate_id = $tax_rate_data['id'];

			// Finally update the tax rate.
			try {
				$remote_tax_rate = $woo->put( "taxes/{$remote_tax_rate_id}", $tax_rate_data );
				// Write the log.
				smp_write_sync_log( "SUCCESS: Updated tax rate at marketplace with ID: {$remote_tax_rate_id}. At vendor's store: {$tax_rate_id}." );
			} catch ( HttpClientException $e ) {
				// Write the log.
				smp_write_sync_log( "ERROR: Couldn't update the tax rate {$tax_rate_id} at the marketplace due to the error: {$e->getMessage()}." );
			}
		}

		// Write the log.
		smp_write_sync_log( 'WC TAX CRON: Tax rates updation cron ends..' );
	}

	/**
	 * Update the shipping zones locations at the marketplace to keep in sync.
	 *
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_update_shipping_locations_cron( $woo ) {
		// Write the log.
		smp_write_sync_log( 'WC SHIPPING ZONE CRON: Initiating the shipping zone locations updation cron..' );

		$shipping_zones = WC_Shipping_Zones::get_zones();

		if ( empty( $shipping_zones ) ) {
			// Write the log.
			smp_write_sync_log( 'WC SHIPPING ZONE LOCATION & METHODS CRON: No shipping zones available to update.' );
			return;
		}

		// Loop through the shipping zones.
		foreach ( $shipping_zones as $shipping_zone_id => $shipping_zone ) {

			if ( empty( $shipping_zone['zone_locations'] ) ) {
				continue;
			}

			$remote_shipping_zone_id = smp_get_remote_shipping_zone_id( $shipping_zone_id );

			if ( false === $remote_shipping_zone_id ) {
				continue;
			}

			$zone_locations = $shipping_zone['zone_locations'];

			if ( empty( $zone_locations ) ) {
				return;
			}

			$shipping_zone_locations = array();
			foreach ( $zone_locations as $zone_location ) {
				$shipping_zone_locations[] = array(
					'code' => $zone_location->code,
					'type' => $zone_location->type,
				);
			}

			try {
				$remote_shipping_zone_location = $woo->put( "shipping/zones/{$remote_shipping_zone_id}/locations", $shipping_zone_locations );
				// Write the log.
				smp_write_sync_log( "WC SHIPPING ZONE CRON: SUCCESS: Updated the shipping zone locations for shipping zone ID: {$shipping_zone_id}. At marketplace: {$remote_shipping_zone_id}." );
			} catch ( HttpClientExceprion $e ) {
				// Write the log.
				smp_write_sync_log( "WC SHIPPING ZONE CRON: ERROR: Couldn't update the shipping zone location due to the error: {$e->getMessage()} for shipping zone ID: {$shipping_zone_id}." );
			}
		}

		// Write the log.
		smp_write_sync_log( 'WC SHIPPING ZONE CRON: Shipping zone locations updation cron ends..' );
	}

	/**
	 * Do the cron job to sync the shipping zone locations.
	 *
	 * @param object $woo Holds the WooCommerce PHP client object.
	 */
	public static function smp_update_shipping_methods_cron( $woo ) {

	}

	/**
	 * This hook saves the custom data being sent by the vendor website for taxonomy terms.
	 * Registers new fields to the taxonomies.
	 */
	public function smp_rest_api_init_callback() {
		// Register custom field to add meta to taxonomy terms.
		self::smp_register_term_meta_custom_fields();

		// Register custom field to add featured image to product and product variation.
		self::smp_register_product_featured_image_custom_field();

		// Register custom field to add gallery images to product.
		self::smp_register_product_gallery_images_custom_field();
	}

	/**
	 * Register the meta fields for taxonomy terms.
	 */
	public static function smp_register_term_meta_custom_fields() {
		$taxonomies = smp_get_wp_wc_default_taxonomies();

		// Register rest field for pre-defined taxonomy terms.
		register_rest_field(
			$taxonomies,
			'meta',
			array(
				'get_callback'    => function( $object ) {
					return get_term_meta( $object['id'] );
				},
				'update_callback' => function ( $value, $object, $field ) {

					if ( ! empty( $value ) && is_array( $value ) ) {
						foreach ( $value as $val ) {
							update_term_meta( $object->term_id, $val['key'], $val['value'] );
						}
						return true;
					}
				},
				'schema'          => null,
			)
		);

		// Register rest field for attribute terms.
		register_rest_field(
			array( 'product_attribute_term' ),
			'meta',
			array(
				'get_callback'    => function( $object ) {
					return get_term_meta( $object['id'] );
				},
				'update_callback' => function ( $value, $object, $field ) {

					if ( ! empty( $value ) && is_array( $value ) ) {
						foreach ( $value as $val ) {
							update_term_meta( $object->term_id, $val['key'], $val['value'] );
						}
						return true;
					}
					return false;
				},
				'schema'          => null,
			)
		);

		// Register rest field for product reviews meta.
		register_rest_field(
			array( 'product_review' ),
			'meta',
			array(
				'get_callback'    => function( $object ) {
					return get_term_meta( $object['id'] );
				},
				'update_callback' => function ( $value, $object, $field ) {

					if ( ! empty( $value ) && is_array( $value ) ) {
						foreach ( $value as $val ) {
							update_comment_meta( $object->comment_ID, $val['key'], $val['value'] );
						}
						return true;
					}
					return false;
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the featured image field for products and variations.
	 */
	public static function smp_register_product_featured_image_custom_field() {
		register_rest_field(
			array( 'product', 'product_variation' ),
			'featured_image_url',
			array(
				'get_callback'    => function ( $object ) {
					$product            = wc_get_product( $object['id'] );
					$featured_image_src = '';

					if ( empty( $product ) || false === $product ) {
						return false;
					}

					$featured_image_id = $product->get_image_id();

					if ( ! empty( $featured_image_id ) ) {
						$featured_image_src = smp_get_image_src_by_id( $featured_image_id );
					}

					return array(
						'id'  => $featured_image_id,
						'src' => $featured_image_src,
					);
				},
				'update_callback' => function ( $value, $object, $field ) {
					$product_id = $object->get_id();

					if ( ! empty( $product_id ) && ! empty( $value ) ) {
						smp_set_featured_image_to_post( $value, $product_id );
					}
					return true;
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the featured image field for products and variations.
	 */
	public static function smp_register_product_gallery_images_custom_field() {
		register_rest_field(
			array( 'product' ),
			'gallery_images',
			array(
				'get_callback'    => function ( $object ) {
					$product            = wc_get_product( $object['id'] );
					$gallery_images     = $product->get_gallery_image_ids();
					$gallery_image_urls = array();

					if ( ! empty( $gallery_images ) && is_array( $gallery_images ) ) {
						foreach ( $gallery_images as $gallery_image_id ) {
							$gallery_image_url = smp_get_image_src_by_id( $gallery_image_id );

							if ( '' !== $gallery_image_url ) {
								$gallery_image_urls[] = array(
									'id'  => $gallery_image_id,
									'src' => $gallery_image_url,
								);
							}
						}
					}

					return $gallery_image_urls;
				},
				'update_callback' => function ( $value, $object, $field ) {
					$product_id = $object->get_id();
					$product_gallery_img_ids = array();

					if ( ! empty( $value ) && is_array( $value ) ) {
						foreach ( $value as $img_src ) {
							$product_gallery_img_ids[] = smp_get_media_id_by_external_media_url( $img_src );
						}
					}

					if ( ! empty( $product_gallery_img_ids ) ) {
						// Convert the array to comma separated image IDs.
						$product_gallery_img_ids = implode( ',', $product_gallery_img_ids );
						update_post_meta( $product_id, '_product_image_gallery', $product_gallery_img_ids );
					}
					return true;
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * This hook runs when the product is imported.
	 *
	 * @param object $product Holds the WooCommerce product object.
	 * @param array  $data Holds the imported data array.
	 */
	public function smp_woocommerce_product_import_inserted_product_object_callback( $product, $data ) {

		// Exit the request is called by Rest API.
		if ( smp_is_rest_api_request() ) {
			return;
		}

		if ( empty( $data ) ) {
			return;
		}

		if ( 'variation' !== $data['type'] ) {
			return;
		}

		$variation_id = $data['id'];
		$variation    = wc_get_product( $variation_id );

		if ( empty( $variation ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid variation data object found for variation ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		$variation_parent_id = $variation->get_parent_id();

		if ( 0 === $variation_parent_id ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid variation parent ID found for variation ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		// Get the remote parent ID.
		$remote_parent_id = get_post_meta( $variation_parent_id, 'synced_marketplace_with_id', true );

		if ( empty( $remote_parent_id ) ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid variation remote parent ID found for variation ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = smp_get_marketplace_woocommerce_client();

		if ( false === $woo ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Invalid WC Rest API client object, variation {$variation_id} couldn't be updated. Action taken by administrator." );
			return;
		}

		$variation_data = smp_get_variation_data( $variation, $remote_parent_id );

		if ( false === $variation_data ) {
			// Write the log.
			smp_write_sync_log( "ERROR: Blank variation data for variation: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		try {
			// Fetch the marketplace variation with similar ID.
			$marketplace_variation_id = get_post_meta( $variation_id, 'synced_marketplace_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created variation, will not have the synced marketplace variation ID.
			 * Thus, we'll assign the synced ID same as the vendor's variation ID, which will be helpful in fetching the variation actual details.
			 */
			if ( empty( $marketplace_variation_id ) || false === $marketplace_variation_id ) {
				$marketplace_variation_id = $variation_id;
			}

			$marketplace_variation = $woo->get( "products/{$remote_parent_id}/variations/{$marketplace_variation_id}" );
			if ( ! empty( $marketplace_product ) && 'object' === gettype( $marketplace_product ) ) {
				$remote_variation = $woo->put( "products/{$marketplace_variation_id}", $variation_data );

				// Write the log.
				if ( ! empty( $remote_variation->id ) ) {
					smp_write_sync_log( "SUCCESS: Updated a variation at marketplace with ID: {$remote_variation->id}. At vendor: {$variation_id}." );
				}
			}
		} catch ( HttpClientException $e ) {
			$variation_error_message = $e->getMessage();
			// If you're here, this means that variation with this ID doesn't exist.
			if ( false !== stripos( $variation_error_message, 'woocommerce_rest_product_variation_invalid_id' ) ) {
				$remote_variation = $woo->post( "products/{$remote_parent_id}/variations", $variation_data );

				// Add a remote product ID to the meta of this product.
				if ( ! empty( $remote_variation->id ) ) {
					update_post_meta( $variation_id, 'synced_marketplace_with_id', $remote_variation->id );

					// Write the log.
					smp_write_sync_log( "Variation created at marketplace. Marketplace variation ID is {$remote_variation->id} and vendor's variation ID is {$variation_id}." );
				}
			}
		}
	}

}
