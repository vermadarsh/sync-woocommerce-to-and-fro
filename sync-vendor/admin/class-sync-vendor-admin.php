<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link        https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/admin
 */

// These files are included to access WooCommerce Rest API PHP library.
require SVN_PLUGIN_PATH . 'vendor/autoload.php';
use Automattic\WooCommerce\HttpClient\HttpClientException;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/admin
 * @author     Adarsh Verma <adarsh.srmcem@gmail.com>
 */
class Sync_Vendor_Admin {

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
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the custom stylesheets & scripts for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function svn_admin_enqueue_assets() {
		global $current_tab;

		if ( 'sync-vendor' !== $current_tab ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );

		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/sync-vendor-admin.css',
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'css/sync-vendor-admin.css' )
		);

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/sync-vendor-admin.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/sync-vendor-admin.js' ),
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'SVN_Admin_JS_Obj',
			array(
				'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
				'delete_log_confirmation' => __( 'Are you sure to delete the log? This action won\'t be undone.', 'sync-vendor' ),
				'delete_log_button_text'  => __( 'Delete Log', 'sync-vendor' ),
				'section'                 => $section,
			)
		);
	}

	/**
	 * Actions to be taken at admin initialization.
	 */
	public function svn_admin_init_callback() {

		// Redirect after plugin redirect.
		if ( get_option( 'svn_do_activation_redirect' ) ) {
			delete_option( 'svn_do_activation_redirect' );
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=sync-vendor' ) );
			exit;
		}
	}

	/**
	 * Admin settings for syncing marketplace.
	 *
	 * @param array $settings Array of WC settings.
	 */
	public function svn_woocommerce_get_settings_pages_callback( $settings ) {
		$settings[] = include __DIR__ . '/inc/class-sync-vendor-settings.php';

		return $settings;
	}

	/**
	 * AJAX request to delete sync log.
	 */
	public function svn_delete_log() {
		$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );

		if ( 'svn_delete_log' !== $action ) {
			return;
		}

		global $wp_filesystem;
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();

		$wp_filesystem->put_contents(
			SVN_LOG_DIR_PATH . 'sync-log.log',
			'',
			FS_CHMOD_FILE // predefined mode settings for WP files.
		);

		wp_send_json_success(
			array(
				'code' => 'svn-sync-log-deleted',
			)
		);
		wp_die();
	}

	/**
	 * Add row action to reveal the remote vendor post ID.
	 *
	 * @param array  $actions Holds the list of actions.
	 * @param object $post Holds the WordPress post object.
	 * @return array
	 */
	public function svn_post_row_actions_callback( $actions, $post ) {

		if ( empty( $post ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		if ( 'shop_coupon' === $post->post_type || 'product' === $post->post_type ) {
			$vendor_product_id = get_post_meta( $post->ID, 'synced_vendor_with_id', true );

			if ( ! empty( $vendor_product_id ) && false !== $vendor_product_id ) {
				/* translators: %s: vendor product ID */
				$actions['svn-vendor-product-id'] = sprintf( __( 'Remote ID: %1$s', 'sync-vendor' ), $vendor_product_id );
			}
		}

		// Add action to show the original coupon ID.
		if ( 'shop_coupon' === $post->post_type ) {
			/* translators: %s: coupon ID */
			$actions['svn-coupon-id'] = sprintf( __( 'ID: %1$s', 'sync-vendor' ), $post->ID );
		}

		return $actions;
	}

	/**
	 * Add row action to reveal the remote customer ID at vendor's end.
	 *
	 * @param array  $actions Holds the list of actions.
	 * @param object $user Holds the WordPress user object.
	 * @return array
	 */
	public function svn_user_row_actions_callback( $actions, $user ) {

		if ( ! svn_is_user_customer( $user->ID ) ) {
			return $actions;
		}

		$vendor_customer_id = get_user_meta( $user->ID, 'synced_vendor_with_id', true );

		if ( empty( $vendor_customer_id ) ) {
			return $actions;
		}

		/* translators: %s: user ID */
		$actions['svn-user-id'] = sprintf( __( 'ID: %1$s', 'sync-marketplace' ), $user->ID );
		/* translators: %s: vendor user ID */
		$actions['svn-vendor-user-id'] = sprintf( __( 'Remote ID: %1$s', 'sync-marketplace' ), $vendor_customer_id );

		return $actions;
	}

	/**
	 * Show marketplace order ID on admin order listing page.
	 *
	 * @param string $buyer Holds the order buyer name.
	 * @param object $order Holds the WooCommerce order object.
	 * @return string
	 */
	public function svn_woocommerce_admin_order_buyer_name_callback( $buyer, $order ) {
		$order_id = $order->get_id();

		$remote_order_id = (int) get_post_meta( $order_id, 'synced_vendor_with_id', true );
		/* translators: 1: %s: new line, 2: %d: remote order id */
		$buyer .= ( 0 !== $remote_order_id ) ? sprintf( __( '%2$s[Remote ID: %1$d]', 'sync-marketplace' ), $remote_order_id, "\n" ) : '';

		return $buyer;
	}

	/**
	 * This filter is added to ensure image transfers on http (local) domains.
	 */
	public function svn_http_request_host_is_external_callback() {

		return true;
	}

	/**
	 * This filter is added to bypass the vendor coupon check by dokan plugin.
	 */
	public function svn_dokan_ensure_vendor_coupon_callback() {

		return false;
	}

	/**
	 * Show the admin notice in case the admin settings are not setup.
	 */
	public function svn_admin_notices_callback() {
		$vendors = get_users(
			array(
				'role' => 'seller',
			)
		);

		if ( empty( $vendors ) || ! is_array( $vendors ) ) {
			return;
		}

		foreach ( $vendors as $vendor ) {
			$vendor_heading  = "#{$vendor->ID} - {$vendor->data->display_name}";
			$vendor_slug     = sanitize_title( $vendor_heading );
			$vendor_rest_api = svn_get_vendor_rest_api_data( $vendor->ID );

			if ( -1 === $vendor_rest_api ) {
				$settings_url = admin_url( "admin.php?page=wc-settings&tab=sync-vendor&section={$vendor_slug}" );
				/* translators: 1: %s: vendor display name, 2: %s: opening anchor tag, 2: %s: closing anchor tag */
				$error_message = sprintf( __( 'Sync settings pending for the vendor %1$s. %2$sClick to update%3$s.', 'sync-vendor' ), $vendor->data->display_name, '<a title="' . esc_attr( $vendor->data->display_name ) . '" href="' . esc_url( $settings_url ) . '">', '</a>' );
				echo wp_kses_post( svn_get_admin_error_message_html( $error_message ) );
			}
		}
	}

	/**
	 * Create new section in vendor's add/edit page to show/update Rest API credentials.
	 *
	 * @param object $user Holds the WordPress user object.
	 */
	public function svn_rest_api_settings_user_profile_callback( $user ) {

		if ( ! is_user_vendor( $user->ID ) ) {
			return;
		}

		$vendor_id = $user->ID;

		// Fetch the API settings.
		$url             = get_option( "svn_vendor_{$vendor_id}_url" );
		$consumer_key    = get_option( "svn_vendor_{$vendor_id}_rest_api_consumer_key" );
		$consumer_secret = get_option( "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key" );
		?>
		<h3><?php esc_html_e( 'Rest API', 'sync-vendor' ); ?></h3>
		<table class="form-table svn-rest-api-settings">
			<tbody>
				<!-- VENDOR URL -->
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( "svn_vendor_{$vendor_id}_url" ); ?>"><?php esc_html_e( 'Vendor URL', 'sync-vendor' ); ?></label>
					</th>
					<td>
						<input class="regular-text" required type="url" placeholder="http(s)://example.com" name="<?php echo esc_attr( "svn_vendor_{$vendor_id}_url" ); ?>" id="<?php echo esc_attr( "svn_vendor_{$vendor_id}_url" ); ?>" value="<?php echo esc_attr( $url ); ?>">
						<p class="description"><?php esc_html_e( 'This holds the vendor URL.', 'sync-vendor' ); ?></p>
					</td>
				</tr>

				<!-- CONSUMER KEY -->
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( "svn_vendor_{$vendor_id}_rest_api_consumer_key" ); ?>"><?php esc_html_e( 'Consumer key', 'sync-vendor' ); ?></label>
					</th>
					<td>
						<input class="regular-text" required type="text" placeholder="ck_**********" name="<?php echo esc_attr( "svn_vendor_{$vendor_id}_rest_api_consumer_key" ); ?>" id="<?php echo esc_attr( "svn_vendor_{$vendor_id}_rest_api_consumer_key" ); ?>" value="<?php echo esc_attr( $consumer_key ); ?>">
						<p class="description"><?php esc_html_e( 'This holds the consumer key for syncing data to the vendor.', 'sync-vendor' ); ?></p>
					</td>
				</tr>

				<!-- CONSUMER SECRET KEY -->
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key" ); ?>"><?php esc_html_e( 'Consumer Secret', 'sync-vendor' ); ?></label>
					</th>
					<td>
						<input class="regular-text" required type="text" placeholder="cs_**********" name="<?php echo esc_attr( "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key" ); ?>" id="<?php echo esc_attr( "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key" ); ?>" value="<?php echo esc_attr( $consumer_secret ); ?>">
						<p class="description"><?php esc_html_e( 'This holds the consumer secret key for syncing data to the vendor.', 'sync-vendor' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php

	}

	/**
	 * Update the user rest API settings.
	 *
	 * @param int $user_id Holds the user ID.
	 */
	public function svn_update_rest_api_settings_user_profile_callback( $user_id ) {

		if ( ! is_user_vendor( $user_id ) ) {
			return;
		}

		$url             = filter_input( INPUT_POST, "svn_vendor_{$user_id}_url", FILTER_SANITIZE_STRING );
		$consumer_key    = filter_input( INPUT_POST, "svn_vendor_{$user_id}_rest_api_consumer_key", FILTER_SANITIZE_STRING );
		$consumer_secret = filter_input( INPUT_POST, "svn_vendor_{$user_id}_rest_api_consumer_secret_key", FILTER_SANITIZE_STRING );

		if (
			! empty( $url ) &&
			! empty( $consumer_key ) &&
			! empty( $consumer_secret )
		) {
			$vendors_rest_api_data = array(
				'url'             => $url,
				'consumer_key'    => $consumer_key,
				'consumer_secret' => $consumer_secret,
			);
			update_user_meta( $user_id, 'svn_rest_api_settings', $vendors_rest_api_data );
		}
	}

	/**
	 * Create and update the coupon at the vendor.
	 *
	 * @param object $coupon Holds the WooCommerce coupon object.
	 */
	public function svn_woocommerce_coupon_object_updated_props_callback( $coupon ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $coupon ) ) {
			// Write the log.
			svn_write_sync_log( 'ERROR: Invalid coupon, couldn\'t be updated.' );
			return;
		}

		// Get to know the associated vendor ID.
		$associated_vendor_id = get_post_meta( $coupon->get_id(), 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating coupon {$coupon->get_id()}, couldn't be updated." );
			return;
		}

		// Fetch the marketplace WooCommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object while updating coupon {$coupon->get_id()}, couldn't be updated." );
			return;
		}

		$coupon_data = svn_get_coupon_data( $coupon );

		if ( false === $coupon_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank coupon array for coupon: {$coupon->get_id()}, couldn't be updated." );
			return;
		}

		$coupon_id = $coupon->get_id();

		// Fetch the coupon ID at vendor's end.
		$remote_coupon_id = get_post_meta( $coupon_id, 'synced_vendor_with_id', true );

		try {
			if ( ! empty( $remote_coupon_id ) ) {
				$remote_coupon = $woo->put( "coupons/{$remote_coupon_id}", $coupon_data );

				if ( ! empty( $remote_coupon->id ) ) {
					// Write the log.
					svn_write_sync_log( "SUCCESS: Updated the coupon at vendor's end. Coupon at vendor's end: {$remote_coupon_id}. Coupon ID here: {$coupon_id}." );
				}
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't update the coupon at vendor's end due to the error: {$error_message}." );
		}
	}

	/**
	 * Delete the post at vendor's end on deleting the synced post.
	 *
	 * @param int $post_id Holds the deleted post ID.
	 */
	public function svn_before_delete_post_callback( $post_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		$post_type      = get_post_type( $post_id );
		$remote_post_id = get_post_meta( $post_id, 'synced_vendor_with_id', true );

		// Get to know the associated vendor ID.
		$associated_vendor_id = get_post_meta( $post_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while deleting post: {$post_id}, couldn't be deleted." );
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object found while deleting post: {$post_id}, couldn't be deleted." );
			return;
		}

		switch ( $post_type ) {
			case 'shop_coupon':
				self::svn_delete_coupon_at_vendor( $post_id, $remote_post_id, $woo );
				break;

			case 'shop_order':
				self::svn_delete_order_at_vendor( $post_id, $remote_post_id, $woo );
				break;

			case 'product':
				self::svn_delete_product_at_vendor( $post_id, $remote_post_id, $woo );
				break;

			default:
				return;
		}
	}

	/**
	 * Delete the coupon at vendor's end.
	 *
	 * @param int    $post_id Holds the coupon ID.
	 * @param int    $remote_post_id Holds the remote post ID.
	 * @param object $woo Holds the WooCommerce Rest API client object.
	 */
	public static function svn_delete_coupon_at_vendor( $post_id, $remote_post_id, $woo ) {
		try {
			$woo->delete(
				"coupons/{$remote_post_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			svn_write_sync_log( "SUCCESS: Coupon deleted at vendor's end. Marketplace ID: {$post_id}. Vendor's ID: {$remote_post_id}" );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete the coupon at vendor's end due to the error: {$error_message}." );
		}
	}

	/**
	 * Delete the product at vendor' end.
	 *
	 * @param int    $post_id Holds the product ID.
	 * @param int    $remote_post_id Holds the remote post ID.
	 * @param object $woo Holds the WooCommerce Rest API client object.
	 */
	public static function svn_delete_product_at_vendor( $post_id, $remote_post_id, $woo ) {
		try {
			$woo->delete(
				"products/{$remote_post_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			svn_write_sync_log( "SUCCESS: Product deleted at vendor's end. Marketplace ID: {$post_id}. Vendor's ID: {$remote_post_id}" );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete the product at vendor's end due to the error: {$error_message}." );
		}
	}

	/**
	 * Delete the order at vendor's end.
	 *
	 * @param int    $post_id Holds the order ID.
	 * @param int    $remote_post_id Holds the remote post ID.
	 * @param object $woo Holds the WooCommerce Rest API client object.
	 */
	public static function svn_delete_order_at_vendor( $post_id, $remote_post_id, $woo ) {
		try {
			$woo->delete(
				"orders/{$remote_post_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			svn_write_sync_log( "SUCCESS: Order deleted at vendor's end. Marketplace ID: {$post_id}. Vendor's ID: {$remote_post_id}" );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete the order at vendor's end due to the error: {$error_message}." );
		}
	}

	/**
	 * Sync the changes made in customer profile, with the customer data on marketplace.
	 *
	 * @param int $user_id Holds the customer ID.
	 */
	public function svn_profile_update_callback( $user_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid user ID: {$user_id}, couldn't be updated." );
			return;
		}

		// Get to know the associated vendor ID.
		$associated_vendor_id = get_user_meta( $user_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating customer {$user_id}, couldn't be updated." );
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object while updating customer {$user_id}, couldn't be updated." );
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! svn_is_user_customer( $user_id ) ) {
			return;
		}

		$remote_customer_id = get_user_meta( $user_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_customer_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote customer ID not found for customer: {$user_id}, couldn't be updated." );
			return;
		}

		$customer_data = svn_get_customer_data( $user, $remote_customer_id );

		if ( false === $customer_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank customer array for customer: {$user_id}, couldn't be updated." );
			return;
		}

		try {
			$remote_customer = $woo->put( "customers/{$remote_customer_id}", $customer_data );

			if ( ! empty( $remote_customer->id ) ) {
				// Write the log.
				svn_write_sync_log( "SUCCESS: Customer updated at marketplace. Marketplace customer ID: {$remote_customer_id}. Vendor's customer ID: {$user_id}" );
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Cannot update customer at marketplace due to the error: {$error_message}." );
		}
	}

	/**
	 * Delete the remote customer from marketplace.
	 *
	 * @param int $user_id Holds the deleted user ID.
	 */
	public function svn_delete_user_callback( $user_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $user_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid user request found, ID: {$user_id}, couldn't be deleted." );
			return;
		}

		$user_data = get_userdata( $user_id );

		if ( false === $user_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid user object request found, ID: {$user_id}, couldn't be deleted." );
			return;
		}

		// Get to know the associated vendor ID.
		$associated_vendor_id = get_user_meta( $user_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating customer {$user_id}, couldn't be deleted." );
			return;
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object while deleting user ID {$user_id}, couldn't be deleted." );
			return;
		}

		$remote_customer_id = get_user_meta( $user_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_customer_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote customer ID not found for customer: {$user_id}, couldn't be deleted." );
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
			svn_write_sync_log( "SUCCESS: Deleted customer at vendor's end with ID: {$remote_customer_id}. Marketplace customer ID: {$user_id}." );
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete the customer at vendor's website due to the error: {$error_message} while deleting customer {$user_id}." );
		}
	}

	/**
	 * Post the updated term to the vendor's website.
	 *
	 * @param int    $term_id Holds the currently updated term ID.
	 * @param int    $term_taxonomy_id Holds the currently updated taxonomy term ID.
	 * @param string $taxonomy Holds the taxonomy name.
	 */
	public function svn_edited_term_callback( $term_id, $term_taxonomy_id, $taxonomy ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $term_id ) || null === get_term( $term_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid term {$term_id} to be updated." );
			return;
		}

		if ( empty( $taxonomy ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid taxonomy found while updating {$term_id}." );
			return;
		}

		// Get the associated vendor ID.
		$associated_vendor_id = get_term_meta( $term_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating term {$term_id}, couldn't be updated." );
			return;
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object while updating term {$term_id}, couldn't be updated." );
			return;
		}

		// Get the term data.
		$term_data = svn_get_taxonomy_term_data( $term_id );

		if ( empty( $term_data ) || ! is_array( $term_data ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank term data found while updating term {$term_id}, couldn't be updated." );
			return;
		}

		// Get the remote term data.
		$remote_term_id = get_term_meta( $term_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_term_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote term ID not found while updating term {$term_id}, couldn't be updated." );
			return;
		}

		if ( 'product_cat' === $taxonomy ) {
			try {
				$remote_term = $woo->put( "products/categories/{$remote_term_id}", $term_data );

				// Write the log.
				if ( ! empty( $remote_term->id ) ) {
					svn_write_sync_log( "SUCCESS: Updated the term at vendor's website with ID: {$remote_term->id}. At marketplace: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't update the term due to the error: {$e->getMessage()}." );
				return;
			}
		} elseif ( 'product_tag' === $taxonomy ) {
			try {
				$remote_term = $woo->put( "products/tags/{$remote_term_id}", $term_data );

				// Write the log.
				if ( ! empty( $remote_term->id ) ) {
					svn_write_sync_log( "SUCCESS: Updated the term at vendor's website with ID: {$remote_term->id}. At marketplace: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't update the term due to the error: {$e->getMessage()}." );
				return;
			}
		} elseif ( 'product_shipping_class' === $taxonomy ) {
			try {
				$remote_term = $woo->put( "products/shipping_classes/{$remote_term_id}", $term_data );

				// Write the log.
				if ( ! empty( $remote_term->id ) ) {
					svn_write_sync_log( "SUCCESS: Updated the term at vendor's website with ID: {$remote_term->id}. At marketplace: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't update the term due to the error: {$e->getMessage()}." );
				return;
			}
		} else {
			$remote_taxonomy_id = false;

			if ( false === stripos( $taxonomy, 'pa_' ) ) {
				return;
			}

			$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

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
					svn_write_sync_log( "SUCCESS: Updated term at vendor's store with ID: {$remote_term->id}. At marketplace: {$term_id}" );
				}
			} catch ( HttpClientException $e ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't update the term {$term_id} at vendor's store due to the error: {$e->getMessage()}." );
			}
		}
	}

	/**
	 * Delete the remote product category term.
	 *
	 * @param int    $term_id Holds the term ID getting deleted.
	 * @param string $taxonomy Holds the taxonomy title.
	 */
	public function svn_pre_delete_term_callback( $term_id, $taxonomy ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $term_id ) || null === get_term( $term_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid term {$term_id} to be deleted." );
			return;
		}

		if ( empty( $taxonomy ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid taxonomy found while deleting {$term_id}." );
			return;
		}

		// Get the associated vendor ID.
		$associated_vendor_id = get_term_meta( $term_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while deleting term {$term_id}." );
			return;
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object while deleting term {$term_id}." );
			return;
		}

		// Get the remote term data.
		$remote_term_id = get_term_meta( $term_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_term_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote term ID not found while deleting term {$term_id}." );
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
				svn_write_sync_log( "SUCCESS: Deleted term {$term_id} at vendor's website with ID: {$remote_term_id}." );
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't delete term due to the error: {$error_message}." );
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
				svn_write_sync_log( "SUCCESS: Deleted term {$term_id} at vendor's website with ID: {$remote_term_id}." );
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't delete term due to the error: {$error_message}." );
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
				svn_write_sync_log( "SUCCESS: Deleted term {$term_id} at vendor's website with ID: {$remote_term_id}." );
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't delete term due to the error: {$error_message}." );
			}
		} else {
			$remote_taxonomy_id = false;

			if ( false === stripos( $taxonomy, 'pa_' ) ) {
				return;
			}

			$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

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
				$woo->delete(
					"products/attributes/{$remote_taxonomy_id}/terms/{$remote_term_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				svn_write_sync_log( "SUCCESS: Deleted a product attribute term at vendor's store with ID {$remote_term_id}. At marketplace: {$term_id}" );
			} catch ( HttpClientException $e ) {
				$error_message = $e->getMessage();
				// Write the log.
				svn_write_sync_log( "ERROR: {$error_message} while deleting product attribute {$taxonomy} with ID {$term_id}." );
			}
		}
	}

	/**
	 * Post the updated attribute to the vendor's store.
	 *
	 * @param int   $attr_id Holds the attribute ID.
	 * @param array $attr_data Holds the created attribute data.
	 */
	public function svn_woocommerce_attribute_updated_callback( $attr_id, $attr_data ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $attr_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid attribute ID {$attr_id}, could update at vendor's store." );
			return;
		}

		if ( empty( $attr_data ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid attribute object for ID {$attr_id}, couldn't update at vendor's store." );
			return;
		}

		$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

		if ( empty( $saved_remote_attributes ) || ! is_array( $saved_remote_attributes ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: No saved attributes found while updating {$attr_id}, couldn't update at marketplace." );
			return;
		}

		if ( ! array_key_exists( $attr_id, $saved_remote_attributes ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Requested attribute doesn't exist in the saved attributes while updating attribute: {$attr_id}." );
			return;
		}

		// Get the remote attribute ID.
		if ( empty( $saved_remote_attributes[ $attr_id ]['remote_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote attribute ID not found while updating attribute: {$attr_id}." );
			return;
		}

		$remote_attr_id = $saved_remote_attributes[ $attr_id ]['remote_id'];

		// Get the associated vendor ID.
		if ( empty( $saved_remote_attributes[ $attr_id ]['associated_vendor_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating attribute: {$attr_id}." );
			return;
		}

		$associated_vendor_id = $saved_remote_attributes[ $attr_id ]['associated_vendor_id'];

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object found while updating attribute {$attr_id}." );
			return;
		}

		$attribute_data = svn_prepare_product_attribute_data( $attr_id, $attr_data );

		if ( false === $attribute_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank attribute array found while updating attribute: {$attr_id}." );
			return;
		}

		try {
			$remote_attribute = $woo->put( "products/attributes/{$remote_attr_id}", $attribute_data );
			/**
			 * Update the options table for remote attribute.
			 */
			$saved_remote_attributes[ $attr_id ]['name'] = $attribute_data['name'];
			$saved_remote_attributes[ $attr_id ]['slug'] = $attribute_data['slug'];
			update_option( 'svn_saved_remote_attributes', $saved_remote_attributes, false );

			// Write the log.
			if ( ! empty( $remote_attribute->id ) ) {
				svn_write_sync_log( "SUCCESS: Updated product attribute at marketplace with ID {$remote_attribute->id}. At vendor's store: {$attr_id}" );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't update the attribute {$attr_id} due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete the remote attribute at vendor's end.
	 *
	 * @param int $attr_id Holds the attribute ID to be deleted.
	 */
	public function svn_woocommerce_before_attribute_delete_callback( $attr_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $attr_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid attribute ID {$attr_id}, couldn't delete at vendor's store." );
			return;
		}

		$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

		if ( empty( $saved_remote_attributes ) || ! is_array( $saved_remote_attributes ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: No saved attributes found while updating {$attr_id}, couldn't update at marketplace." );
			return;
		}

		if ( ! array_key_exists( $attr_id, $saved_remote_attributes ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Requested attribute doesn't exist in the saved attributes while deleting attribute: {$attr_id}." );
			return;
		}

		// Get the remote attribute ID.
		if ( empty( $saved_remote_attributes[ $attr_id ]['remote_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote attribute ID not found while deleting attribute: {$attr_id}." );
			return;
		}

		$remote_attr_id = $saved_remote_attributes[ $attr_id ]['remote_id'];

		// Get the associated vendor ID.
		if ( empty( $saved_remote_attributes[ $attr_id ]['associated_vendor_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while deleting attribute: {$attr_id}." );
			return;
		}

		$associated_vendor_id = $saved_remote_attributes[ $attr_id ]['associated_vendor_id'];

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object found while deleting attribute {$attr_id}." );
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
			update_option( 'svn_saved_remote_attributes', $saved_remote_attributes, false );

			// Write the log.
			svn_write_sync_log( "SUCCESS: Deleted product attribute at vendor's store with ID {$remote_attr_id}. At marketplace: {$attr_id}" );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete the attribute {$attr_id} due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Create/Update the product at vendor's website.
	 *
	 * @param int    $product_id Holds the woocommerce product ID.
	 * @param object $product Holds the woocommerce product object.
	 */
	public function svn_woocommerce_update_product_callback( $product_id, $product ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $product_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid product request found, ID: {$product_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		if ( empty( $product ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid product object request found, ID: {$product_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		// Get the vendor associated with the product.
		$associated_vendor_id = get_post_meta( $product_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			$associated_vendor_id = get_post_field( 'post_author', $product_id );

			if ( empty( $associated_vendor_id ) || ! is_user_vendor( $associated_vendor_id ) ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Associated vendor ID not found while updating product {$product_id}. Action taken by administrator." );
				return;
			}
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object, product {$product_id} couldn't be updated. Action taken by administrator." );
			return;
		}

		$product_data = svn_get_product_data( $product );

		if ( false === $product_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank product array for {$product_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		// Fetch the remote product ID.
		$remote_product_id = get_post_meta( $product_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_product_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote product ID not found while updating product {$product_id}. Action taken by administrator." );
			return;
		}

		try {
			$remote_product = $woo->put( "products/{$remote_product_id}", $product_data );

			if ( ! empty( $remote_product->id ) ) {
				// Write the log.
				svn_write_sync_log( "SUCCESS: Product updated at vendor's website with ID: {$remote_product->id}. At marketplace: {$product_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't update product at vendor's website due to the error: {$e->getMessage()}. Product ID: {$product_id}. Action taken by administrator." );
		}
	}

	/**
	 * Post the variation data to the marketplace.
	 *
	 * @param int $variation_id Holds the variation ID.
	 */
	public function svn_woocommerce_save_product_variation_callback( $variation_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $variation_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation request found, ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		$variation = wc_get_product( $variation_id );

		if ( empty( $variation ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation data request found, ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		$variation_parent_id = $variation->get_parent_id();

		if ( 0 === $variation_parent_id ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation prent ID found for variation ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		// Get the remote parent ID.
		$remote_parent_id = get_post_meta( $variation_parent_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_parent_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation remote prent ID found for variation ID: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		// Get the vendor associated with the product.
		$associated_vendor_id = get_post_meta( $variation_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			$associated_vendor_id = get_post_field( 'post_author', $variation_id );

			if ( empty( $associated_vendor_id ) || ! is_user_vendor( $associated_vendor_id ) ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Associated vendor ID not found while updating product {$variation_id}. Action taken by administrator." );
				return;
			}
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object, variation {$variation_id} couldn't be updated. Action taken by administrator." );
			return;
		}

		$variation_data = svn_get_variation_data( $variation, $remote_parent_id );

		if ( false === $variation_data ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Blank variation data for variation: {$variation_id}, couldn't be updated. Action taken by administrator." );
			return;
		}

		try {
			/**
			 * Fetch the marketplace variation with similar ID.
			 */
			$remote_variation_id = get_post_meta( $variation_id, 'synced_vendor_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created variation, will not have the synced marketplace variation ID.
			 * Thus, we'll assign the synced ID same as the vendor's variation ID, which will be helpful in fetching the variation actual details.
			 */
			if ( empty( $remote_variation_id ) || false === $remote_variation_id ) {
				$remote_variation_id = $variation_id;
			}

			$vendors_variation = $woo->get( "products/{$remote_parent_id}/variations/{$remote_variation_id}" );
			if ( ! empty( $vendors_variation ) && 'object' === gettype( $vendors_variation ) ) {
				$remote_variation = $woo->put( "products/{$remote_variation_id}", $variation_data );

				// Write the log.
				if ( ! empty( $remote_variation->id ) ) {
					svn_write_sync_log( "SUCCESS: Variation updated at vendor's store with ID: {$remote_variation->id}. At marketplace: {$variation_id}" );
				}
			}
		} catch ( HttpClientException $e ) {
			$error_message = $e->getMessage();
			/**
			 * If you're here, this means that variation with this ID doesn't exist.
			 */
			if ( false !== stripos( $error_message, 'woocommerce_rest_product_variation_invalid_id' ) ) {
				$remote_variation = $woo->post( "products/{$remote_parent_id}/variations", $variation_data );

				/**
				 * Add a remote product ID to the meta of this product.
				 */
				if ( ! empty( $remote_variation->id ) ) {
					update_post_meta( $variation_id, 'synced_vendor_with_id', $remote_variation->id );

					// Write the log.
					svn_write_sync_log( "SUCCESS: Variation created at vendor's store with ID: {$remote_variation->id}. At marketplace {$variation_id}." );
				}
			}
		}
	}

	/**
	 * Delete the variation from remote.
	 *
	 * @param int $variation_id Holds the variation ID.
	 */
	public function svn_woocommerce_before_delete_product_variation_callback( $variation_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $variation_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation ID: {$variation_id} couldn't delete at vendor's store." );
			return;
		}

		$variation = wc_get_product( $variation_id );

		if ( empty( $variation ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation object for ID: {$variation_id} couldn't delete at vendor's store." );
			return;
		}

		$variation_parent_id = $variation->get_parent_id();

		if ( 0 === $variation_parent_id ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation parent for ID: {$variation_id} couldn't delete at vendor's store." );
			return;
		}

		// Get the remote parent ID.
		$remote_parent_id = get_post_meta( $variation_parent_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_parent_id ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid variation remote parent for ID: {$variation_id} couldn't delete at vendor's store." );
			return;
		}

		// Get the vendor associated with the product.
		$associated_vendor_id = get_post_meta( $variation_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			$associated_vendor_id = get_post_field( 'post_author', $variation_id );

			if ( empty( $associated_vendor_id ) || ! is_user_vendor( $associated_vendor_id ) ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Associated vendor ID not found while deleting variation {$variation_id}. Action taken by administrator." );
				return;
			}
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$remote_variation_id = get_post_meta( $variation_id, 'synced_vendor_with_id', true );
		try {
			$woo->delete(
				"products/{$remote_parent_id}/variations/{$remote_variation_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			svn_write_sync_log( "SUCCESS: Deleted variation at vendor's store with ID: {$remote_variation_id}. At marketplace: {$variation_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete variation at vendor's store due to the error: {$e->getMessage()} while deleting variation {$variation_id}." );
		}
	}

	/**
	 * Post the created order to the marketplace.
	 *
	 * @param int $order_id Holds the order ID.
	 */
	public function svn_woocommerce_thankyou_callback( $order_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( false === $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();

		if ( empty( $customer_id ) ) {
			return;
		}

		// Check to see of the customer is him/herself a vendor of the marketplace.
		if ( ! is_user_vendor( $customer_id ) ) {
			return;
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $customer_id );

		if ( false === $woo ) {
			return;
		}

		$order_data = svn_get_order_data( $order );
		try {
			do_action( 'svn_before_posting_order_to_marketplace', $order_data, $order_id, $order );
			$remote_order = $woo->post( 'orders', $order_data );
			do_action( 'svn_after_posting_order_to_marketplace', $order_data, $order_id, $order );
			/**
			 * Add a remote order ID to the meta of this order.
			 */
			if ( ! empty( $remote_order->id ) ) {
				update_post_meta( $order_id, 'synced_vendor_with_id', $remote_order->id );

				// Write the log.
				svn_write_sync_log( "SUCCESS: Order created at vendor's store with ID {$remote_order->id}. At marketplace: {$order_id}." );
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
								wc_add_order_item_meta( $src_line_item_id, 'synced_vendor_with_id', $remote_shipping_line->id, true );
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
				$src_remote_prod_id = (int) get_post_meta( $src_prod_id, 'synced_vendor_with_id', true );

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
						wc_add_order_item_meta( $src_line_item_id, 'synced_vendor_with_id', $remote_line_item['id'], true );
					}
				}
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't create order (on thank you page) at vendor's store due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Update the order at the marketplace.
	 *
	 * @param int    $order_id Holds the order ID.
	 * @param object $order Holds the woocommerce order object.
	 */
	public function svn_woocommerce_update_order_callback( $order_id, $order ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
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

		$associated_vendor_id = get_post_meta( $order_id, 'smp_associated_vendor_id', true );

		// Check to see of the customer is him/herself a vendor of the marketplace.
		if ( ! is_user_vendor( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$order_data = svn_get_order_data( $order );

		try {
			/**
			 * Fetch the vendor's order with similar ID.
			 */
			$remote_order_id = get_post_meta( $order_id, 'synced_vendor_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created order, will not have the synced vendor's store order ID.
			 * Thus, we'll assign the synced ID same as the marketplace order ID, which will be helpful in fetching the order actual details.
			 */
			if ( empty( $remote_order_id ) || false === $remote_order_id ) {
				$remote_order_id = $order_id;
			}

			$remote_order = $woo->get( "orders/{$remote_order_id}" );

			if ( ! empty( $remote_order ) && 'object' === gettype( $remote_order ) ) {
				do_action( 'svn_before_updating_order_at_vendor_store', $remote_order_id, $order_data, $order_id, $order );
				$woo->put( "orders/{$remote_order_id}", $order_data );

				// Write the log.
				svn_write_sync_log( "SUCCESS: Order updated at vendor's store with ID, {$remote_order_id}. At marketplace: {$order_id}." );
				do_action( 'svn_after_updating_order_at_vendor_store', $remote_order_id, $order_data, $order_id, $order );
			}
		} catch ( HttpClientException $e ) {
			// If you're here, this means that order with this ID doesn't exist.
			if ( false !== stripos( $e->getMessage(), 'woocommerce_rest_shop_order_invalid_id' ) ) {
				// Write the log.
				svn_write_sync_log( "NOTICE: Creating the order from order id: {$order_id} using the hook: woocommerce_new_order." );
			}
		}
	}

	/**
	 * Post the newly created order, from admin panel, to the vendor's store.
	 *
	 * @param int    $order_id Holds the order ID.
	 * @param object $order Holds the WooCommerce order object.
	 */
	public function svn_woocommerce_new_order_callback( $order_id, $order ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( false === $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();

		if ( empty( $customer_id ) ) {
			return;
		}
		// Check to see of the customer is him/herself a vendor of the marketplace.
		if ( ! is_user_vendor( $customer_id ) ) {
			return;
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $customer_id );

		if ( false === $woo ) {
			return;
		}

		$order_data = svn_get_order_data( $order );

		if ( false === $order_data ) {
			return;
		}

		try {
			$remote_order = $woo->post( 'orders', $order_data );

			// Add a remote order ID to the meta of this order.
			if ( ! empty( $remote_order->id ) ) {
				update_post_meta( $order_id, 'synced_vendor_with_id', $remote_order->id );

				// Write the log.
				svn_write_sync_log( "SUCCESS: Order created at vendor's store with ID, {$remote_order->id}. At marketplace: {$order_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Ciuldn't create order at vendor's store due to the error: {$e->getMessage()}. At marketplace: {$order_id}." );
		}
	}

	/**
	 * Create refund at the marketplace.
	 *
	 * @param int $refund_id Holds the order refund ID.
	 */
	public function svn_woocommerce_refund_created_callback( $refund_id ) {
		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $refund_id ) || 0 === $refund_id ) {
			return;
		}

		$associated_vendor_id = get_post_meta( $refund_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$order_id = svn_get_order_id_by_refund_id( $refund_id );

		if ( false === $order_id ) {
			return;
		}

		$remote_order_id = get_post_meta( $order_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_order_id ) ) {
			return;
		}

		$refund_data = svn_get_order_refund_data( $refund_id );

		if ( false === $refund_data ) {
			return;
		}

		try {
			$remote_refund = $woo->post( "orders/{$remote_order_id}/refunds", $refund_data );

			if ( ! empty( $remote_refund->id ) ) {
				update_post_meta( $refund_id, 'synced_vendor_with_id', $remote_refund->id );
				// Write the log.
				svn_write_sync_log( "SUCCESS: Refund created at vendor's end with ID {$remote_refund->id}. At marketplace: {$refund_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Refund couldn't be created at vendor's end due to the error {$e->getMessage()}. At marketplace: {$refund_id}." );
		}
	}

	/**
	 * Delete the refund ID at the marketplace.
	 *
	 * @param int $refund_id Holds the refund ID.
	 * @param int $order_id Holds the order ID.
	 */
	public function svn_woocommerce_refund_deleted_callback( $refund_id, $order_id ) {
		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $refund_id ) || 0 === $refund_id ) {
			return;
		}

		$remote_refund_id = get_post_meta( $refund_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_refund_id ) ) {
			return;
		}

		if ( empty( $order_id ) ) {
			return;
		}

		$associated_vendor_id = get_post_meta( $refund_id, 'smp_associated_vendor_id', true );

		if ( empty( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the marketplace woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$remote_order_id = get_post_meta( $order_id, 'synced_vendor_with_id', true );

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
			svn_write_sync_log( "SUCCESS: Deleted refund at vendor's end with ID {$remote_refund_id}. At marketplace: {$refund_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Refund couldn't be deleted at vendor's end due to the error {$e->getMessage()}. At marketplace: {$refund_id}." );
		}
	}

	/**
	 * Insert order notes at marketplace order.
	 *
	 * @param int    $comment_id Holds the comment ID.
	 * @param object $comment Holds the WordPress comment object.
	 */
	public function svn_wp_insert_comment_callback( $comment_id, $comment ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment ) ) {
			return;
		}

		// Gather the post ID.
		$post_id = $comment->comment_post_ID;

		if ( ! 'shop_order' === get_post_type( $post_id ) ) {
			return;
		}

		$customer_id = get_post_meta( $post_id, '_customer_user', true );

		if ( ! is_user_vendor( $customer_id ) ) {
			return;
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $customer_id );

		if ( false === $woo ) {
			return;
		}

		// Check if the note is to the customer.
		$note_type = filter_input( INPUT_POST, 'note_type', FILTER_SANITIZE_STRING );

		$note_data = array(
			'note'          => $comment->comment_content,
			'customer_note' => ( 'customer' === $note_type ) ? true : false,
		);

		// Fetch the order at vendor's store with similar ID.
		$remote_order_id = get_post_meta( $post_id, 'synced_vendor_with_id', true );

		try {
			$remote_order_note = $woo->post( "orders/{$remote_order_id}/notes", $note_data );

			// Add a remote order ID to the meta of this order.
			if ( ! empty( $remote_order_note->id ) ) {
				update_comment_meta( $comment_id, 'synced_vendor_with_id', $remote_order_note->id );
			}

			// Write the log.
			svn_write_sync_log( "SUCCESS: Created order note, {$comment_id} at vendor's store for the order {$post_id}. Vendor's order id: {$remote_order_id}. Vendor's note ID: {$remote_order_note->id}" );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't create order note for the order{$post_id} due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete order note at marketplace.
	 *
	 * @param int    $comment_id Holds the comment ID.
	 * @param object $comment Holds the WordPress comment object.
	 */
	public function svn_delete_comment_callback( $comment_id, $comment ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment ) ) {
			return;
		}

		// Gather the post ID.
		$post_id = $comment->comment_post_ID;

		// Check to see if the comment is or order.
		$remote_comment_id = get_comment_meta( $comment_id, 'synced_vendor_with_id', true );

		if ( empty( $remote_comment_id ) ) {
			return;
		}

		if ( 'shop_order' === get_post_type( $post_id ) ) {
			$customer_id = get_post_meta( $post_id, '_customer_user', true );

			if ( ! is_user_vendor( $customer_id ) ) {
				return;
			}

			// Fetch the vendor's woocommerce client.
			$woo = svn_get_vendor_woocommerce_client( $customer_id );

			if ( false === $woo ) {
				return;
			}

			$remote_order_id = get_post_meta( $post_id, 'synced_vendor_with_id', true );
			try {
				$woo->delete(
					"orders/{$remote_order_id}/notes/{$remote_comment_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				svn_write_sync_log( "SUCCESS: Deleted the note ID {$comment_id} at vendor's store with ID: {$remote_comment_id}." );
			} catch ( HttpClientException $e ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't delete order note {$comment_id} at vendor's store with id: {$remote_comment_id} due to the error: {$e->getMessage()}." );
			}
		} elseif ( 'product' === get_post_type( $post_id ) ) {
			$associated_vendor_id = get_post_field( 'post_author', $post_id );

			if ( empty( $associated_vendor_id ) ) {
				return;
			}

			if ( ! is_user_vendor( $associated_vendor_id ) ) {
				return;
			}

			// Fetch the vendor's woocommerce client.
			$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

			if ( false === $woo ) {
				return;
			}

			// Delete the marketplace product review.
			try {
				$woo->delete(
					"products/reviews/{$remote_comment_id}",
					array(
						'force' => true,
					)
				);
				// Write the log.
				svn_write_sync_log( "SUCCESS: Deleted the review ID {$comment_id} at vendor's store with ID: {$remote_comment_id}." );
			} catch ( HttpClientException $e ) {
				// Write the log.
				svn_write_sync_log( "ERROR: Couldn't delete review {$comment_id} at vendor's store with id: {$remote_comment_id} due to the error: {$e->getMessage()}." );
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
	public function svn_woocommerce_order_note_class_callback( $note_class, $note ) {

		if ( empty( $note->id ) ) {
			return $note_class;
		}

		if ( current_user_can( 'manage_options' ) ) {
			$remote_note_id = get_comment_meta( $note->id, 'synced_vendor_with_id', true );

			if ( ! empty( $remote_note_id ) && false !== $remote_note_id ) {
				$note_class[] = "vendors-id-{$remote_note_id}";
			}
		}

		return $note_class;
	}

	/**
	 * Post product review to the vendor's store.
	 *
	 * @param int $comment_id Holds the comment ID.
	 */
	public function svn_comment_post_callback( $comment_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment_id ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( empty( $comment ) ) {
			return;
		}

		// Gather the post ID.
		$post_id = $comment->comment_post_ID;

		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		$associated_vendor_id = get_post_field( 'post_author', $post_id );

		if ( empty( $associated_vendor_id ) ) {
			return;
		}

		if ( ! is_user_vendor( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$review_data = svn_get_product_review_data( $post_id, $comment );

		if ( false === $review_data ) {
			return;
		}

		try {
			$remote_review = $woo->post( 'products/reviews', $review_data );
			/**
			 * Add a remote review ID to the meta of this product.
			 */
			if ( ! empty( $remote_review->id ) ) {
				update_comment_meta( $comment_id, 'synced_vendor_with_id', $remote_review->id );

				// Write the log.
				svn_write_sync_log( "SUCCESS: Product review created at vendor's store with ID {$remote_review->id}. At marketplace: {$comment_id}." );
			}
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't create product review with ID {$comment_id} at vendor's store due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Update the comment on the vendor's store.
	 *
	 * @param int $comment_id Holds the comment ID.
	 */
	public function svn_edit_comment_callback( $comment_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $comment_id ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( empty( $comment ) ) {
			return;
		}

		// Gather the post ID.
		$post_id = $comment->comment_post_ID;

		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		$associated_vendor_id = get_post_field( 'post_author', $post_id );

		if ( empty( $associated_vendor_id ) ) {
			return;
		}

		if ( ! is_user_vendor( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the vendor's woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$post_id     = $comment->comment_post_ID;
		$review_data = svn_get_product_review_data( $post_id, $comment );

		if ( false === $review_data ) {
			return;
		}

		try {
			// Fetch the marketplace review with similar ID.
			$remote_review_id = get_comment_meta( $comment_id, 'synced_vendor_with_id', true );

			/**
			 * This assignment is because the very first time, for a fresh created product review, will not have the synced marketplace review ID.
			 * Thus, we'll assign the synced ID same as the vendor's review ID, which will be helpful in fetching the review actual details.
			 */
			if ( empty( $remote_review_id ) || false === $remote_review_id ) {
				$remote_review_id = $comment_id;
			}

			$remote_review = $woo->get( "products/reviews/{$remote_review_id}" );

			if ( ! empty( $remote_review ) && 'object' === gettype( $remote_review ) ) {
				$remote_review = $woo->put( "products/reviews/{$remote_review_id}", $review_data );

				if ( ! empty( $remote_review->id ) ) {
					// Write the log.
					svn_write_sync_log( "SUCCESS: Updated the product review at vendor's store with ID {$remote_review_id}. At marketplace: {$comment_id}." );
				}
			}
		} catch ( HttpClientException $e ) {
			$review_error_message = $e->getMessage();

			// If you're here, this means that variation with this ID doesn't exist.
			if ( false !== stripos( $review_error_message, 'woocommerce_rest_review_invalid_id' ) ) {
				try {
					$remote_review = $woo->post( 'products/reviews', $review_data );

					// Add a remote review ID to the meta of this comment.
					if ( ! empty( $remote_review->id ) ) {
						update_comment_meta( $comment_id, 'synced_vendor_with_id', $remote_review->id );

						// Write the log.
						svn_write_sync_log( "SUCCESS: Product review created at vendor's store with ID {$remote_review->id}. At marketplace: {$comment_id}." );
					}
				} catch ( HttpClientException $e ) {
					// Write the log.
					svn_write_sync_log( "ERROR: Couldn't create product review with ID {$comment_id} at vendor's store due to the error: {$e->getMessage()}." );
				}
			}
		}
	}

	/**
	 * Post the new tax rate to the vendor's store.
	 *
	 * @param int $tax_rate_id Holds the tax rate ID.
	 */
	public function svn_woocommerce_tax_rate_added_callback( $tax_rate_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $tax_rate_id ) ) {
			return;
		}

		$tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

		if ( null === $tax_rate ) {
			return;
		}

		$tax_class            = ( empty( $tax_rate ) ) ? 'standard' : $tax_rate['tax_rate_class'];
		$associated_vendor_id = svn_get_associated_vendor_by_tax_class( $tax_class );

		if ( false === $associated_vendor_id ) {
			return;
		}

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		// Get the tax data to be posted.
		$tax_rate_data = svn_get_tax_rate_data( $tax_rate_id, $tax_rate );

		if ( false === $tax_rate_data ) {
			return;
		}

		// Post the tax to the remote location.
		try {
			$remote_tax_rate = $woo->post( 'taxes', $tax_rate_data );

			// Add a remote tax rate ID to the meta of this tax rate ID.
			if ( ! empty( $remote_tax_rate->id ) ) {
				// Since there isn't anything as tax rate meta, thus saving the remote IDs in the options table.
				$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

				if ( empty( $saved_remote_tax_rates ) || false === $saved_remote_tax_rates ) {
					$saved_remote_tax_rates = array();
				}

				$saved_remote_tax_rates[ $tax_rate_id ] = array(
					'id'                   => $tax_rate_id,
					'name'                 => $tax_rate_data['name'],
					'rate'                 => $tax_rate_data['rate'],
					'remote_id'            => $remote_tax_rate->id,
					'associated_vendor_id' => $associated_vendor_id,
				);
				update_option( 'svn_saved_remote_tax_rates', $saved_remote_tax_rates, false );
			}

			// Write the log.
			svn_write_sync_log( "SUCCESS: Created tax rate at vendor's store with ID: {$remote_tax_rate->id}. At marketplace: {$tax_rate_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't create tax rate: {$tax_rate_id} at vendor's store due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Update the tax rate at the vendor's store.
	 *
	 * @param int $tax_rate_id Holds the tax rate ID.
	 */
	public function svn_woocommerce_tax_rate_updated_callback( $tax_rate_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $tax_rate_id ) ) {
			return;
		}

		$tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

		if ( null === $tax_rate ) {
			return;
		}

		$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

		if ( empty( $saved_remote_tax_rates ) || ! is_array( $saved_remote_tax_rates ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: No saved tax rates found while updating {$tax_rate_id}, couldn't update at vendor's store." );
			return;
		}

		if ( ! array_key_exists( $tax_rate_id, $saved_remote_tax_rates ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Requested tax rate doesn't exist in the saved tax rates while updating tax: {$tax_rate_id}." );
			return;
		}

		// Get the remote tax rate ID.
		if ( empty( $saved_remote_tax_rates[ $tax_rate_id ]['remote_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote tax rate ID not found while updating tax: {$tax_rate_id}." );
			return;
		}

		$remote_tax_rate_id = $saved_remote_tax_rates[ $tax_rate_id ]['remote_id'];

		// Get the associated vendor ID.
		if ( empty( $saved_remote_tax_rates[ $tax_rate_id ]['associated_vendor_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while updating tax: {$tax_rate_id}." );
			return;
		}

		$associated_vendor_id = $saved_remote_tax_rates[ $tax_rate_id ]['associated_vendor_id'];

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object found while updating tax {$tax_rate_id}." );
			return;
		}

		$tax_rate_data = svn_get_tax_rate_data( $tax_rate_id, $tax_rate );

		if ( false === $tax_rate_data ) {
			return;
		}

		try {
			$remote_tax_rate = $woo->put( "taxes/{$remote_tax_rate_id}", $tax_rate_data );

			// Add a remote tax rate ID in the database.
			if ( ! empty( $remote_tax_rate->id ) ) {
				// Since there isn't anything as tax rate meta, thus saving the remote IDs in the options table.
				$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

				if ( empty( $saved_remote_tax_rates ) || false === $saved_remote_tax_rates ) {
					$saved_remote_tax_rates = array();
				}

				$saved_remote_tax_rates[ $tax_rate_id ]['name'] = $tax_rate_data['name'];
				$saved_remote_tax_rates[ $tax_rate_id ]['rate'] = $tax_rate_data['rate'];
				update_option( 'svn_saved_remote_tax_rates', $saved_remote_tax_rates, false );
			}

			// Write the log.
			svn_write_sync_log( "SUCCESS: Updated tax rate at vendor's store with ID: {$remote_tax_rate_id}. At marketplace: {$tax_rate_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't update tax rate {$tax_rate_id} at vendor's store with ID {$remote_tax_rate_id} due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Delete the remote tax rate.
	 *
	 * @param int $tax_rate_id Holds the tax rate ID.
	 */
	public function svn_woocommerce_tax_rate_deleted_callback( $tax_rate_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $tax_rate_id ) ) {
			return;
		}

		$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

		if ( empty( $saved_remote_tax_rates ) || ! is_array( $saved_remote_tax_rates ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: No saved tax rates found while deleting {$tax_rate_id}." );
			return;
		}

		if ( ! array_key_exists( $tax_rate_id, $saved_remote_tax_rates ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Requested tax rate doesn't exist in the saved tax rates while deleting tax: {$tax_rate_id}." );
			return;
		}

		// Get the remote tax rate ID.
		if ( empty( $saved_remote_tax_rates[ $tax_rate_id ]['remote_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Remote tax rate ID not found while deleting tax: {$tax_rate_id}." );
			return;
		}

		$remote_tax_rate_id = $saved_remote_tax_rates[ $tax_rate_id ]['remote_id'];

		// Get the associated vendor ID.
		if ( empty( $saved_remote_tax_rates[ $tax_rate_id ]['associated_vendor_id'] ) ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Associated vendor ID not found while deleting tax: {$tax_rate_id}." );
			return;
		}

		$associated_vendor_id = $saved_remote_tax_rates[ $tax_rate_id ]['associated_vendor_id'];

		// Fetch the vendor woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Invalid WC Rest API client object found while deleting tax {$tax_rate_id}." );
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
			svn_write_sync_log( "SUCCESS: Tax rate deleted at vendor's store with ID: {$remote_tax_rate_id}. At marketplace: {$tax_rate_id}." );

			// Removing the same attribute ID from the options table.
			$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );
			unset( $saved_remote_tax_rates[ $tax_rate_id ] );
			update_option( 'svn_saved_remote_tax_rates', $saved_remote_tax_rates, false );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete tax rate: {$tax_rate_id} at vendor's store due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Update the webhook at the vendor's store.
	 *
	 * @param int $webhook_id Holds the webhook ID.
	 */
	public function svn_woocommerce_webhook_updated_callback( $webhook_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $webhook_id ) ) {
			return;
		}

		$webhook = wc_get_webhook( $webhook_id );

		if ( empty( $webhook ) ) {
			return;
		}

		$remote_webhook_id = svn_get_remote_webhook_id( $webhook_id );

		if ( false === $remote_webhook_id ) {
			return;
		}

		$associated_vendor_id = svn_get_associated_vendor_id_by_webhook_id( $webhook_id );

		if ( empty( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the vendor's store woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		$webhook_data = svn_get_webhook_data( $webhook_id, $webhook );

		if ( false === $webhook_data ) {
			return;
		}

		try {
			$remote_webhook = $woo->put( "webhooks/{$remote_webhook_id}", $webhook_data );

			// Add a remote webhook ID to the meta of this webhook ID.
			if ( ! empty( $remote_webhook->id ) ) {
				// Update the database.
				$saved_remote_webhooks = get_option( 'svn_saved_remote_webhooks' );

				if ( empty( $saved_remote_webhooks ) || false === $saved_remote_webhooks ) {
					$saved_remote_webhooks = array();
				}

				$saved_remote_webhooks[ $webhook_id ]['name'] = $webhook_data['name'];
				update_option( 'svn_saved_remote_webhooks', $saved_remote_webhooks, false );
			}

			// Write the log.
			svn_write_sync_log( "SUCCESS: Updated webhook at vendor's store with ID: {$remote_webhook_id}. At marketplace: {$webhook_id}." );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't update webhook, {$webhook_id} due to the error: {$e->getMessage()}" );
		}
	}

	/**
	 * Delete the remote webhook.
	 *
	 * @param int $webhook_id Holds the webhook ID.
	 */
	public function svn_woocommerce_webhook_deleted_callback( $webhook_id ) {

		// Exit the request is called by Rest API.
		if ( svn_is_rest_api_request() ) {
			return;
		}

		if ( empty( $webhook_id ) ) {
			return;
		}

		$remote_webhook_id = svn_get_remote_webhook_id( $webhook_id );

		if ( false === $remote_webhook_id ) {
			return;
		}

		$associated_vendor_id = svn_get_associated_vendor_id_by_webhook_id( $webhook_id );

		if ( empty( $associated_vendor_id ) ) {
			return;
		}

		// Fetch the vendor's store woocommerce client.
		$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

		if ( false === $woo ) {
			return;
		}

		try {
			$woo->delete(
				"webhooks/{$remote_webhook_id}",
				array(
					'force' => true,
				)
			);
			// Write the log.
			svn_write_sync_log( "SUCCESS: Webhook deleted at vendor's store with ID: {$remote_webhook_id}. At marketplace: {$webhook_id}." );

			// Removing the same webhook ID from the options table.
			$saved_remote_webhooks = get_option( 'svn_saved_remote_webhooks' );
			unset( $saved_remote_webhooks[ $webhook_id ] );
			update_option( 'svn_saved_remote_webhooks', $saved_remote_webhooks, false );
		} catch ( HttpClientException $e ) {
			// Write the log.
			svn_write_sync_log( "ERROR: Couldn't delete webhook, {$webhook_id} due to the error: {$e->getMessage()}." );
		}
	}

	/**
	 * Cron jobs at marketplace.
	 */
	public function svn_svn_sync_vendor_cron_callback() {

		// Check to see if the relating class exists.
		if ( ! class_exists( 'WC_Tax' ) ) {
			return;
		}

		self::svn_update_tax_rates_cron();
	}

	/**
	 * Update the tax rates at the vendor's store to keep in sync.
	 * This cron is because tax locations don't get updated by the default hook provided by WooCommerce.
	 */
	public static function svn_update_tax_rates_cron() {
		$saved_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

		if ( empty( $saved_tax_rates ) || ! is_array( $saved_tax_rates ) ) {
			return;
		}

		// Write the log.
		svn_write_sync_log( 'WC TAX CRON: Initiating the tax rates updation cron..' );

		foreach ( $saved_tax_rates as $tax_rate_id => $tax_rate ) {
			$wc_tax_rate = WC_Tax::_get_tax_rate( $tax_rate_id );

			if ( null === $wc_tax_rate ) {
				continue;
			}

			$associated_vendor_id = $tax_rate['associated_vendor_id'];

			if ( empty( $associated_vendor_id ) ) {
				continue;
			}

			$tax_rate_data = svn_get_tax_rate_data( $tax_rate_id, $wc_tax_rate );

			if ( false === $tax_rate_data ) {
				continue;
			}

			// Fetch the vendor's store woocommerce client.
			$woo = svn_get_vendor_woocommerce_client( $associated_vendor_id );

			if ( false === $woo ) {
				continue;
			}

			$remote_tax_rate_id = $tax_rate_data['id'];

			if ( empty( $remote_tax_rate_id ) ) {
				continue;
			}

			// Write the log.
			svn_write_sync_log( "WC TAX CRON: NOTICE: Updating the tax {$tax_rate_id} at the vendor's store." );

			// Finally update the tax rate.
			try {
				$remote_tax_rate = $woo->put( "taxes/{$remote_tax_rate_id}", $tax_rate_data );

				if ( ! empty( $remote_tax_rate->id ) ) {
					svn_write_sync_log( "SUCCESS: Updated tax rate at vendor's store with ID: {$remote_tax_rate_id}. At marketplace: {$tax_rate_id}." );
				}
			} catch ( HttpClientException $e ) {
				svn_write_sync_log( "ERROR: Couldn't update the tax rate {$tax_rate_id} at the vendor's store due to the error: {$e->getMessage()}." );
			}
		}

		// Write the log.
		svn_write_sync_log( 'WC TAX CRON: Tax rates updation cron ends..' );
	}

	/**
	 * This hook saves the custom data being sent by the vendor website for taxonomy terms.
	 * Registers new fields to the taxonomies.
	 */
	public function svn_rest_api_init_callback() {
		// Register custom field to add meta to taxonomy terms.
		self::svn_register_term_meta_custom_fields();

		// Register custom field to add featured image to product and product variation.
		self::svn_register_product_featured_image_custom_field();

		// Register custom field to add gallery images to product.
		self::svn_register_product_gallery_images_custom_field();

		// Register custom field for synced ID at vendor's store for product attributes.
		self::svn_register_remote_product_attribute_id_custom_field();

		// Register custom field for associated vendor ID for product attributes.
		self::svn_register_associate_vendor_id_product_attributes_custom_field();

		// Register custom field for synced ID at vendor's store for tax rates.
		self::svn_register_remote_tax_rate_id_custom_field();

		// Register custom field for tax rates.
		self::svn_register_associate_vendor_id_tax_rate_custom_field();

		// Register custom field for tax classes.
		self::svn_register_remote_tax_class_slug_custom_field();

		// Register custom field for tax classes.
		self::svn_register_associate_vendor_id_tax_class_custom_field();

		// Register custom field for synced ID at vendor's store for webhooks.
		self::svn_register_remote_webhook_id_custom_field();

		// Register custom field for associated vendor ID for webhooks.
		self::svn_register_associate_vendor_id_webhook_custom_field();

		// Register custom field for synced ID at vendor's store for shipping zones.
		self::svn_register_remote_shipping_zone_id_custom_field();

		// Register custom field for associated vendor ID for shipping zones.
		self::svn_register_associate_vendor_id_shipping_zone_custom_field();
	}

	/**
	 * Register the meta fields for taxonomy terms.
	 */
	public static function svn_register_term_meta_custom_fields() {
		// WordPress & WooCommerce taxonomies.
		$taxonomies = svn_get_wp_wc_default_taxonomies();

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
					return false;
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
	public static function svn_register_product_featured_image_custom_field() {
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
						$featured_image_src = svn_get_image_src_by_id( $featured_image_id );
					}

					return array(
						'id'  => $featured_image_id,
						'src' => $featured_image_src,
					);
				},
				'update_callback' => function ( $value, $object, $field ) {
					$product_id = $object->get_id();

					if ( ! empty( $product_id ) && ! empty( $value ) ) {
						svn_set_featured_image_to_post( $value, $product_id );
					}
					return true;
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the gallery images field for products.
	 */
	public static function svn_register_product_gallery_images_custom_field() {
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
							$gallery_image_url = svn_get_image_src_by_id( $gallery_image_id );

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
							$product_gallery_img_ids[] = svn_get_media_id_by_external_media_url( $img_src );
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
	 * Register the remote attribute ID for syncing product attributes.
	 */
	public static function svn_register_remote_product_attribute_id_custom_field() {
		register_rest_field(
			array( 'product_attribute' ),
			'synced_vendor_with_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_attributes' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

					if ( empty( $saved_remote_attributes ) || false === $saved_remote_attributes ) {
						$saved_remote_attributes = array();
					}

					$saved_remote_attributes[ $object->attribute_id ] = array(
						'id'                   => $object->attribute_id,
						'name'                 => $object->attribute_label,
						'slug'                 => $object->attribute_name,
						'remote_id'            => $value,
						'associated_vendor_id' => '',
					);
					return update_option( 'svn_saved_remote_attributes', $saved_remote_attributes, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the associated vendor ID for syncing product attributes.
	 */
	public static function svn_register_associate_vendor_id_product_attributes_custom_field() {
		register_rest_field(
			array( 'product_attribute' ),
			'smp_associated_vendor_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_attributes' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

					if ( empty( $saved_remote_attributes ) || false === $saved_remote_attributes ) {
						$saved_remote_attributes = array();
					}

					$saved_remote_attributes[ $object->attribute_id ]['associated_vendor_id'] = $value;
					return update_option( 'svn_saved_remote_attributes', $saved_remote_attributes, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the custom rest field for tax rates.
	 */
	public static function svn_register_remote_tax_rate_id_custom_field() {
		register_rest_field(
			array( 'tax' ),
			'synced_vendor_with_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_tax_rates' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

					if ( empty( $saved_remote_tax_rates ) || false === $saved_remote_tax_rates ) {
						$saved_remote_tax_rates = array();
					}

					$saved_remote_tax_rates[ $object->tax_rate_id ] = array(
						'id'                   => $object->tax_rate_id,
						'name'                 => $object->tax_rate_name,
						'rate'                 => $object->rate,
						'remote_id'            => $value,
						'associated_vendor_id' => '',
					);
					return update_option( 'svn_saved_remote_tax_rates', $saved_remote_tax_rates, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the associated vendor ID for syncing product attributes.
	 */
	public static function svn_register_associate_vendor_id_tax_rate_custom_field() {
		register_rest_field(
			array( 'tax' ),
			'smp_associated_vendor_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_tax_rates' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

					if ( empty( $saved_remote_tax_rates ) || false === $saved_remote_tax_rates ) {
						$saved_remote_tax_rates = array();
					}

					$saved_remote_tax_rates[ $object->tax_rate_id ]['associated_vendor_id'] = $value;
					return update_option( 'svn_saved_remote_tax_rates', $saved_remote_tax_rates, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the custom rest field for tax rates.
	 */
	public static function svn_register_remote_tax_class_slug_custom_field() {
		register_rest_field(
			array( 'tax_class' ),
			'synced_vendor_with_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_tax_classes' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_tax_classes = get_option( 'svn_saved_remote_tax_classes' );

					if ( empty( $saved_remote_tax_classes ) || false === $saved_remote_tax_classes ) {
						$saved_remote_tax_classes = array();
					}

					$saved_remote_tax_classes[ $object['slug'] ] = array(
						'id'                   => $object['slug'],
						'name'                 => $object['name'],
						'remote_id'            => $value,
						'associated_vendor_id' => '',
					);
					return update_option( 'svn_saved_remote_tax_classes', $saved_remote_tax_classes, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the associated vendor ID for syncing tax classes.
	 */
	public static function svn_register_associate_vendor_id_tax_class_custom_field() {
		register_rest_field(
			array( 'tax_class' ),
			'smp_associated_vendor_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_tax_classes' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_tax_classes = get_option( 'svn_saved_remote_tax_classes' );

					if ( empty( $saved_remote_tax_classes ) || false === $saved_remote_tax_classes ) {
						$saved_remote_tax_classes = array();
					}

					$saved_remote_tax_classes[ $object['slug'] ]['associated_vendor_id'] = $value;
					return update_option( 'svn_saved_remote_tax_classes', $saved_remote_tax_classes, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the custom rest field for webhooks.
	 */
	public static function svn_register_remote_webhook_id_custom_field() {
		register_rest_field(
			array( 'webhook' ),
			'synced_vendor_with_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_webhooks' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_webhooks = get_option( 'svn_saved_remote_webhooks' );

					if ( empty( $saved_remote_webhooks ) || false === $saved_remote_webhooks ) {
						$saved_remote_webhooks = array();
					}

					$saved_remote_webhooks[ $object->get_id() ] = array(
						'id'                   => $object->get_id(),
						'name'                 => $object->get_name(),
						'remote_id'            => $value,
						'associated_vendor_id' => '',
					);
					return update_option( 'svn_saved_remote_webhooks', $saved_remote_webhooks, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the associated vendor ID for syncing shipping zones.
	 */
	public static function svn_register_associate_vendor_id_webhook_custom_field() {
		register_rest_field(
			array( 'webhook' ),
			'smp_associated_vendor_id',
			array(
				'get_callback'    => function( $object ) {
					return get_option( 'svn_saved_remote_webhooks' );
				},
				'update_callback' => function ( $value, $object, $field ) {
					$saved_remote_webhooks = get_option( 'svn_saved_remote_webhooks' );

					if ( empty( $saved_remote_webhooks ) || false === $saved_remote_webhooks ) {
						$saved_remote_webhooks = array();
					}

					$saved_remote_webhooks[ $object->get_id() ]['associated_vendor_id'] = $value;
					return update_option( 'svn_saved_remote_webhooks', $saved_remote_webhooks, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the custom rest field for shipping zone ID.
	 */
	public static function svn_register_remote_shipping_zone_id_custom_field() {
		register_rest_field(
			array( 'shipping_zone' ),
			'synced_vendor_with_id',
			array(
				'get_callback'    => function( $object ) {
					return $object;
				},
				'update_callback' => function ( $value, $object, $field ) {
					update_option( 't1_value', $value, false );
					update_option( 't1_object', $object->id, false );
					update_option( 't1_field', $field, false );
					return true;
					// $saved_remote_shipping_zones = get_option( 'svn_saved_remote_shipping_zones' );

					// if ( empty( $saved_remote_shipping_zones ) || false === $saved_remote_shipping_zones ) {
					// 	$saved_remote_shipping_zones = array();
					// }

					// $saved_remote_shipping_zones[ $object->get_id() ] = array(
					// 	'id'                   => $object->get_id(),
					// 	'name'                 => $object->get_zone_name(),
					// 	'remote_id'            => $value,
					// 	'associated_vendor_id' => '',
					// );
					// return update_option( 'svn_saved_remote_shipping_zones', $saved_remote_shipping_zones, false );
				},
				'schema'          => null,
			)
		);
	}

	/**
	 * Register the associated vendor ID for syncing shipping zones.
	 */
	public static function svn_register_associate_vendor_id_shipping_zone_custom_field() {
		register_rest_field(
			array( 'shipping_zone' ),
			'smp_associated_vendor_id',
			array(
				'get_callback'    => function( $object ) {
					return $object;
				},
				'update_callback' => function ( $value, $object, $field ) {
					update_option( 't2_value', $value, false );
					update_option( 't2_object', $object->id, false );
					update_option( 't2_field', $field, false );
					return true;
					// $saved_remote_shipping_zones = get_option( 'svn_saved_remote_shipping_zones' );

					// if ( empty( $saved_remote_shipping_zones ) || false === $saved_remote_shipping_zones ) {
					// 	$saved_remote_shipping_zones = array();
					// }

					// $saved_remote_shipping_zones[ $object->get_id() ]['associated_vendor_id'] = $value;
					// return update_option( 'svn_saved_remote_shipping_zones', $saved_remote_shipping_zones, false );
				},
				'schema'          => null,
			)
		);
	}
}
