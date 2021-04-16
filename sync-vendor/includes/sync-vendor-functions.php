<?php
/**
 * This file is used for writing all the re-usable custom functions.
 *
 * @since   1.0.0
 * @package Sync_Vendor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// These files are included to access WooCommerce Rest API PHP library.
require SVN_PLUGIN_PATH . 'vendor/autoload.php';
use Automattic\WooCommerce\Client;

/**
 * Check to see if the asked user is a dokan vendor.
 *
 * @param int $vendor_id Holds the vendor ID.
 * @return boolean
 */
function is_user_vendor( $vendor_id ) {
	$user = get_userdata( $vendor_id );

	if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
		return false;
	}

	if ( ! in_array( 'seller', $user->roles, true ) ) {
		return false;
	}

	return true;
}

/**
 * Check to see if the asked user is a woocommerce customer.
 *
 * @param int $user_id Holds the user ID.
 * @return boolean
 */
function svn_is_user_customer( $user_id ) {
	$user = get_userdata( $user_id );

	if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
		return false;
	}

	if ( ! in_array( 'customer', $user->roles, true ) ) {
		return false;
	}

	return true;
}

/**
 * Return the WooCommerce Rest API client object.
 *
 * @param int $vendor_id Holds the vendor ID.
 * @return boolean|object
 */
function svn_get_vendor_woocommerce_client( $vendor_id ) {

	if ( ! is_user_vendor( $vendor_id ) ) {
		return false;
	}

	$remote_address  = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_STRING );
	$url             = get_option( "svn_vendor_{$vendor_id}_url" );
	$consumer_key    = get_option( "svn_vendor_{$vendor_id}_rest_api_consumer_key" );
	$consumer_secret = get_option( "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key" );
	$verify_ssl      = is_ssl() ? ( ( '127.0.0.1' === $remote_address ) ? false : true ) : false;

	if ( empty( $consumer_key ) || empty( $consumer_secret ) || empty( $url ) ) {
		return false;
	}

	$woo = new Client(
		$url,
		$consumer_key,
		$consumer_secret,
		array(
			'version'    => 'wc/v3',
			'verify_ssl' => $verify_ssl,
		)
	);

	return $woo;
}

/**
 * Prepare the coupon data to be exported to vendor.
 *
 * @param object $coupon Holds the woocommerce coupon object.
 * @return boolean|array
 */
function svn_get_coupon_data( $coupon ) {

	if ( empty( $coupon ) ) {
		return false;
	}

	// Gather the product's IDs.
	$product_ids        = $coupon->get_product_ids();
	$remote_product_ids = array();

	if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
		foreach ( $product_ids as $product_id ) {
			$remote_product_ids[] = get_post_meta( $product_id, 'synced_vendor_with_id', true );
		}
	}

	// Gather the excluded product's IDs.
	$excluded_product_ids        = $coupon->get_excluded_product_ids();
	$remote_excluded_product_ids = array();

	if ( ! empty( $excluded_product_ids ) && is_array( $excluded_product_ids ) ) {
		foreach ( $excluded_product_ids as $excluded_product_id ) {
			$remote_excluded_product_ids[] = get_post_meta( $excluded_product_id, 'synced_vendor_with_id', true );
		}
	}

	// Gather the product's categories.
	$product_categories        = $coupon->get_product_categories();
	$remote_product_categories = array();

	if ( ! empty( $product_categories ) && is_array( $product_categories ) ) {
		foreach ( $product_categories as $product_category ) {
			$remote_product_categories[] = get_term_meta( $product_category, 'synced_vendor_with_id', true );
		}
	}

	// Gather the excluded product's categories.
	$excluded_product_categories        = $coupon->get_excluded_product_categories();
	$remote_excluded_product_categories = array();

	if ( ! empty( $excluded_product_categories ) && is_array( $excluded_product_categories ) ) {
		foreach ( $excluded_product_categories as $excluded_product_category ) {
			$remote_excluded_product_categories[] = get_term_meta( $excluded_product_category, 'synced_vendor_with_id', true );
		}
	}

	// Gather the coupon expiry date.
	$date_expires       = get_post_meta( $coupon->get_id(), 'date_expires', true );
	$coupon_expiry_date = '';

	if ( ! empty( $date_expires ) ) {
		$coupon_expiry_date = gmdate( 'Y-m-d', $date_expires );
	}

	$coupon_data = array(
		'code'                        => $coupon->get_code(),
		'description'                 => $coupon->get_description(),
		'discount_type'               => $coupon->get_discount_type(),
		'amount'                      => $coupon->get_amount(),
		'individual_use'              => $coupon->get_individual_use(),
		'exclude_sale_items'          => $coupon->get_exclude_sale_items(),
		'minimum_amount'              => $coupon->get_minimum_amount(),
		'maximum_amount'              => $coupon->get_maximum_amount(),
		'email_restrictions'          => $coupon->get_email_restrictions(),
		'excluded_product_categories' => $remote_excluded_product_categories,
		'product_categories'          => $remote_product_categories,
		'free_shipping'               => $coupon->get_free_shipping(),
		'date_expires'                => $coupon_expiry_date,
		'usage_count'                 => $coupon->get_usage_count(),
		'product_ids'                 => $remote_product_ids,
		'excluded_product_ids'        => $remote_excluded_product_ids,
		'usage_limit'                 => $coupon->get_usage_limit(),
		'usage_limit_per_user'        => $coupon->get_usage_limit_per_user(),
		'limit_usage_to_x_items'      => $coupon->get_limit_usage_to_x_items(),
		'used_by'                     => $coupon->get_used_by(),
	);

	return apply_filters( 'svn_exported_coupon_data', $coupon_data, $coupon );
}

/**
 * Write log to the log file.
 *
 * @param string $message Holds the log message.
 * @return void
 */
function svn_write_sync_log( $message = '' ) {
	global $wp_filesystem;

	if ( empty( $message ) ) {
		return;
	}

	require_once ABSPATH . '/wp-admin/includes/file.php';
	WP_Filesystem();

	$local_file = SVN_LOG_DIR_PATH . 'sync-log.log';

	// Fetch the old content.
	if ( $wp_filesystem->exists( $local_file ) ) {
		$content  = $wp_filesystem->get_contents( $local_file );
		$content .= "\n" . gmdate( 'Y-m-d h:i:s' ) . ' :: ' . $message;
	}

	$wp_filesystem->put_contents(
		$local_file,
		$content,
		FS_CHMOD_FILE // predefined mode settings for WP files.
	);
}

/**
 * Prepare the customer data using customer ID.
 *
 * @param object $user Holds the user object.
 * @param int    $remote_customer_id Holds the remote customer ID.
 * @return array
 */
function svn_get_customer_data( $user, $remote_customer_id = '' ) {
	$customer = new WC_Customer( $user->ID );

	$user_data = array(
		'email'      => $customer->get_email(),
		'first_name' => $customer->get_first_name(),
		'last_name'  => $customer->get_last_name(),
		'username'   => $customer->get_username(),
		'password'   => $user->user_pass,
	);

	if ( ! empty( $remote_customer_id ) ) {
		$user_data['billing'] = array(
			'first_name' => $customer->get_billing_first_name(),
			'last_name'  => $customer->get_billing_last_name(),
			'company'    => $customer->get_billing_company(),
			'address_1'  => $customer->get_billing_address_1(),
			'address_2'  => $customer->get_billing_address_2(),
			'city'       => $customer->get_billing_city(),
			'state'      => $customer->get_billing_state(),
			'postcode'   => $customer->get_billing_postcode(),
			'country'    => $customer->get_billing_country(),
			'email'      => $customer->get_billing_email(),
			'phone'      => $customer->get_billing_phone(),
		);

		$user_data['shipping'] = array(
			'first_name' => $customer->get_shipping_first_name(),
			'last_name'  => $customer->get_shipping_last_name(),
			'company'    => $customer->get_shipping_company(),
			'address_1'  => $customer->get_shipping_address_1(),
			'address_2'  => $customer->get_shipping_address_2(),
			'city'       => $customer->get_shipping_city(),
			'state'      => $customer->get_shipping_state(),
			'postcode'   => $customer->get_shipping_postcode(),
			'country'    => $customer->get_shipping_country(),
		);

		$user_data['id']       = $remote_customer_id;
		$user_data['billing']  = (object) $user_data['billing'];
		$user_data['shipping'] = (object) $user_data['shipping'];
	}

	return apply_filters( 'svn_exported_customer_data', $user_data, $user );
}

if ( ! function_exists( 'svn_is_rest_api_request' ) ) {
	/**
	 * Function to check if the current request is a REST API request.
	 *
	 * @return boolean|int
	 */
	function svn_is_rest_api_request() {
		$prefix     = rest_get_url_prefix();
		$rest_route = filter_input( INPUT_GET, 'rest_route', FILTER_SANITIZE_STRING );

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST
			|| isset( $rest_route )
			&& strpos( trim( $rest_route, '\\/' ), $prefix, 0 ) === 0 ) {

			return true;
		}

		$rest_url    = wp_parse_url( site_url( $prefix ) );
		$current_url = wp_parse_url( home_url( add_query_arg( array() ) ) );

		return strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
	}
}

/**
 * Prepare the product data to be exported to the vendor's website.
 *
 * @param object $product Holds the woocommerce product object.
 * @return boolean|array
 */
function svn_get_product_data( $product ) {

	if ( empty( $product ) ) {
		return false;
	}

	$product_id   = $product->get_id();
	$product_type = $product->get_type();

	// Gather the remote category IDs.
	$category_ids        = $product->get_category_ids();
	$remote_category_ids = array();

	if ( ! empty( $category_ids ) ) {
		foreach ( $category_ids as $category_id ) {
			$remote_category_id = get_term_meta( $category_id, 'synced_vendor_with_id', true );

			if ( ! empty( $remote_category_id ) ) {
				$remote_category_ids[] = array(
					'id' => $remote_category_id,
				);
			}
		}
	}

	// Gather the remote tag IDs.
	$tag_ids        = $product->get_tag_ids();
	$remote_tag_ids = array();

	if ( ! empty( $tag_ids ) ) {
		foreach ( $tag_ids as $tag_id ) {
			$remote_tag_id = get_term_meta( $tag_id, 'synced_vendor_with_id', true );

			if ( ! empty( $remote_tag_id ) ) {
				$remote_tag_ids[] = array(
					'id' => $remote_tag_id,
				);
			}
		}
	}

	// Gather gallery images.
	$gallery_images     = $product->get_gallery_image_ids();
	$gallery_image_urls = array();

	if ( ! empty( $gallery_images ) && is_array( $gallery_images ) ) {
		foreach ( $gallery_images as $gallery_image_id ) {
			$gallery_image_url = svn_get_image_src_by_id( $gallery_image_id );

			if ( '' !== $gallery_image_url ) {
				$gallery_image_urls[] = $gallery_image_url;
			}
		}
	}

	// Gather the up sell product's IDs.
	$upsell_product_ids        = $product->get_upsell_ids();
	$remote_upsell_product_ids = array();

	if ( ! empty( $upsell_product_ids ) && is_array( $upsell_product_ids ) ) {
		foreach ( $upsell_product_ids as $upsell_product_id ) {
			$remote_upsell_product_ids[] = get_post_meta( $upsell_product_id, 'synced_vendor_with_id', true );
		}
	}

	// Gather the cross sell product's IDs.
	$cross_sell_product_ids        = $product->get_cross_sell_ids();
	$remote_cross_sell_product_ids = array();

	if ( ! empty( $cross_sell_product_ids ) && is_array( $cross_sell_product_ids ) ) {
		foreach ( $cross_sell_product_ids as $cross_sell_product_id ) {
			$remote_cross_sell_product_ids[] = get_post_meta( $cross_sell_product_id, 'synced_vendor_with_id', true );
		}
	}

	// Gather the sale start from schedule.
	$date_on_sale_from = get_post_meta( $product->get_id(), '_sale_price_dates_from', true );
	$sale_date_from    = '';

	if ( ! empty( $date_on_sale_from ) ) {
		$sale_date_from = gmdate( 'Y-m-d', $date_on_sale_from );
	}

	// Gather the sale ends on schedule.
	$date_on_sale_to = get_post_meta( $product->get_id(), '_sale_price_dates_to', true );
	$sale_date_to    = '';

	if ( ! empty( $date_on_sale_to ) ) {
		$sale_date_to = gmdate( 'Y-m-d', $date_on_sale_to );
	}

	// Gather product attributes.
	$attributes        = $product->get_attributes();
	$remote_attributes = array();

	if ( 'simple' === $product_type || 'external' === $product_type || 'variable' === $product_type ) {
		foreach ( $attributes as $attribute ) {
			$attr_id        = $attribute->get_id();
			$visibility     = $attribute->get_visible();
			$remote_attr_id = svn_get_remote_product_attribute_id( $attr_id );

			if ( false !== $remote_attr_id ) {
				// Gather the attribute terms now.
				$attr_terms        = $attribute->get_options();
				$remote_attr_terms = array();

				if ( ! empty( $attr_terms ) && is_array( $attr_terms ) ) {
					foreach ( $attr_terms as $attr_term_id ) {
						$attr_term = get_term( $attr_term_id );

						if ( ! empty( $attr_term->name ) ) {
							$remote_attr_terms[] = $attr_term->name;
						}
					}
				}

				$temp = array(
					'id'      => $remote_attr_id,
					'visible' => $visibility,
					'options' => $remote_attr_terms,
				);

				// Add the variation index for the attributes supporting variations.
				if ( 'variable' === $product_type ) {
					$temp['variation'] = $attribute->get_variation();
				}

				$remote_attributes[] = $temp;
			}
		}
	}

	if ( 'simple' === $product_type || 'external' === $product_type || 'variable' === $product_type ) {
		foreach ( $attributes as $attribute ) {
			$attr_id        = $attribute->get_id();
			$visibility     = $attribute->get_visible();
			$remote_attr_id = svn_get_remote_product_attribute_id( $attr_id );

			if ( false !== $remote_attr_id ) {
				// Gather the attribute terms now.
				$attr_terms        = $attribute->get_options();
				$remote_attr_terms = array();

				if ( ! empty( $attr_terms ) && is_array( $attr_terms ) ) {
					foreach ( $attr_terms as $attr_term_id ) {
						$attr_term = get_term( $attr_term_id );

						if ( ! empty( $attr_term->name ) ) {
							$remote_attr_terms[] = $attr_term->name;
						}
					}
				}

				$temp = array(
					'id'      => $remote_attr_id,
					'visible' => $visibility,
					'options' => $remote_attr_terms,
				);

				// Add the variation index for the attributes supporting variations.
				if ( 'variable' === $product_type ) {
					$temp['variation'] = $attribute->get_variation();
				}

				$remote_attributes[] = $temp;
			}
		}
	}

	// Gather downloads.
	$downloads        = $product->get_downloads();
	$remote_downloads = array();

	if ( ! empty( $downloads ) && is_array( $downloads ) ) {
		foreach ( $downloads as $download ) {
			$remote_downloads[] = array(
				'id'   => $download->get_id(),
				'name' => $download->get_name(),
				'file' => $download->get_file(),
			);
		}
	}

	// Gather product image ID.
	$image_id          = $product->get_image_id();
	$product_image_src = wc_placeholder_img_src();

	if ( ! empty( $image_id ) ) {
		$product_image_src = svn_get_image_src_by_id( $image_id );
	}

	// Gather the shipping class and shipping class ID.
	$shipping_class_id        = $product->get_shipping_class_id();
	$remote_shipping_class_id = '';
	$shipping_class           = '';

	if ( ! empty( $shipping_class_id ) ) {
		$remote_shipping_class_id = get_term_meta( $shipping_class_id, 'synced_marketplace_with_id', true );
		$shipping_class_term      = get_term( $shipping_class_id );

		if ( ! empty( $shipping_class_term->slug ) ) {
			$shipping_class = $shipping_class_term->slug;
		}
	}

	$product_data = array(
		'type'               => $product_type,
		'name'               => $product->get_name(),
		'slug'               => $product->get_slug(),
		'status'             => $product->get_status(),
		'featured'           => $product->get_featured(),
		'catalog_visibility' => $product->get_catalog_visibility(),
		'description'        => $product->get_description(),
		'short_description'  => $product->get_short_description(),
		'sku'                => $product->get_sku(),
		'price'              => $product->get_price(),
		'regular_price'      => $product->get_regular_price(),
		'sale_price'         => $product->get_sale_price(),
		'date_on_sale_from'  => $sale_date_from,
		'date_on_sale_to'    => $sale_date_to,
		'total_sales'        => $product->get_total_sales(),
		'tax_status'         => $product->get_tax_status(),
		'tax_class'          => $product->get_tax_class(),
		'manage_stock'       => $product->get_manage_stock(),
		'stock_quantity'     => $product->get_stock_quantity(),
		'stock_status'       => $product->get_stock_status(),
		'backorders'         => $product->get_backorders(),
		'low_stock_amount'   => $product->get_low_stock_amount(),
		'sold_individually'  => $product->get_sold_individually(),
		'dimensions'         => array(
			'length' => $product->get_length(),
			'width'  => $product->get_width(),
			'height' => $product->get_height(),
		),
		'weight'             => $product->get_weight(),
		'upsell_ids'         => $remote_upsell_product_ids,
		'cross_sell_ids'     => $remote_cross_sell_product_ids,
		'parent_id'          => $product->get_parent_id(),
		'reviews_allowed'    => $product->get_reviews_allowed(),
		'purchase_note'      => $product->get_purchase_note(),
		'attributes'         => $remote_attributes,
		'default_attributes' => $product->get_default_attributes(),
		'menu_order'         => $product->get_menu_order(),
		'post_password'      => $product->get_post_password(),
		'categories'         => $remote_category_ids,
		'tags'               => $remote_tag_ids,
		'virtual'            => $product->get_virtual(),
		'downloads'          => $remote_downloads,
		'download_expiry'    => $product->get_download_expiry(),
		'downloadable'       => $product->get_downloadable(),
		'download_limit'     => $product->get_download_limit(),
		'rating_counts'      => $product->get_rating_counts(),
		'average_rating'     => $product->get_average_rating(),
		'review_count'       => $product->get_review_count(),
		'meta_data'          => array(
			array(
				'key'   => 'synced_marketplace_with_id',
				'value' => $product->get_id(),
			),
			array(
				'key'   => '_low_stock_amount',
				'value' => get_post_meta( $product_id, '_low_stock_amount', true ),
			),
		),
		'featured_image_url' => $product_image_src,
		'gallery_images'     => $gallery_image_urls,
		'shipping_class'     => $shipping_class,
		'shipping_class_id'  => $remote_shipping_class_id,
	);

	// Gather child products for grouped products.
	if ( 'grouped' === $product_type ) {
		$children_ids        = $product->get_children();
		$remote_children_ids = array();

		if ( ! empty( $children_ids ) && is_array( $children_ids ) ) {
			foreach ( $children_ids as $children_id ) {
				$remote_product_id = get_post_meta( $children_id, 'synced_vendor_with_id', true );

				if ( ! empty( $remote_product_id ) ) {
					$remote_children_ids[] = $remote_product_id;
				}
			}
		}

		if ( ! empty( $remote_children_ids ) ) {
			$product_data['grouped_products'] = $remote_children_ids;
		}
	}

	// Gather button text and product URL in case the product is affiliate.
	if ( 'external' === $product_type ) {
		$product_data['button_text']  = $product->get_button_text();
		$product_data['external_url'] = $product->get_product_url();
	}

	return apply_filters( 'svn_exported_product_data', $product_data, $product );
}

/**
 * Get image data from image URL.
 *
 * @param string $image_url Holds the image URL.
 * @return string
 */
function svn_get_image_data( $image_url ) {

	if ( '' === $image_url ) {
		return;
	}

	$response      = wp_remote_get( $image_url );
	$response_code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $response_code ) {
		return;
	}

	return wp_remote_retrieve_body( $response );
}

/**
 * This function sets the featured image to the post ID.
 *
 * @param string $image_url Holds the image URL.
 * @param int    $post_id Holds the post ID.
 */
function svn_set_featured_image_to_post( $image_url, $post_id ) {
	$image_basename   = pathinfo( $image_url );
	$image_name       = $image_basename['basename'];
	$upload_dir       = wp_upload_dir();
	$image_data       = svn_get_image_data( $image_url );
	$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name );
	$filename         = basename( $unique_file_name );

	// Check folder permission and define file location.
	if ( wp_mkdir_p( $upload_dir['path'] ) ) {
		$file = $upload_dir['path'] . '/' . $filename;
	} else {
		$file = $upload_dir['basedir'] . '/' . $filename;
	}

	// Create the image  file on the server.
	file_put_contents( $file, $image_data );

	// Check image file type.
	$wp_filetype = wp_check_filetype( $filename, null );

	// Set attachment data.
	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'post_title'     => sanitize_file_name( $filename ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	// Create the attachment.
	$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

	// Include image.php file.
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Define attachment metadata.
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

	// Assign metadata to attachment.
	wp_update_attachment_metadata( $attach_id, $attach_data );

	// And finally assign featured image to post.
	set_post_thumbnail( $post_id, $attach_id );
}

/**
 * Add media to WordPress by external image URL.
 *
 * @param string $image_url Holds the external image URL.
 */
function svn_get_media_id_by_external_media_url( $image_url ) {
	$image_basename   = pathinfo( $image_url );
	$image_name       = $image_basename['basename'];
	$upload_dir       = wp_upload_dir();
	$image_data       = file_get_contents( $image_url );
	$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name );
	$filename         = basename( $unique_file_name );

	// Check folder permission and define file location.
	if ( wp_mkdir_p( $upload_dir['path'] ) ) {
		$file = $upload_dir['path'] . '/' . $filename;
	} else {
		$file = $upload_dir['basedir'] . '/' . $filename;
	}

	// Create the image  file on the server.
	file_put_contents( $file, $image_data );

	// Check image file type.
	$wp_filetype = wp_check_filetype( $filename, null );

	// Set attachment data.
	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'post_title'     => sanitize_file_name( $filename ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	// Create the attachment.
	$attach_id = wp_insert_attachment( $attachment, $file );

	// Include image.php file.
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Define attachment metadata.
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

	// Assign metadata to attachment.
	wp_update_attachment_metadata( $attach_id, $attach_data );

	return $attach_id;
}

/**
 * Returns the image src by id.
 *
 * @param int $img_id Holds the attachment ID.
 * @return boolean|string
 */
function svn_get_image_src_by_id( $img_id ) {

	if ( empty( $img_id ) ) {
		return false;
	}

	return apply_filters( 'svn_image_src', wp_get_attachment_url( $img_id ), $img_id );

}

/**
 * Get remote attribute ID from source attribute ID.
 *
 * @param int $attr_id Holds the attribute ID.
 * @return boolean|string
 */
function svn_get_remote_product_attribute_id( $attr_id ) {

	if ( empty( $attr_id ) ) {
		return false;
	}

	$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

	if ( empty( $saved_remote_attributes ) || ! is_array( $saved_remote_attributes ) ) {
		return false;
	}

	if ( empty( $saved_remote_attributes[ $attr_id ]['remote_id'] ) ) {
		return false;
	}

	return $saved_remote_attributes[ $attr_id ]['remote_id'];
}

/**
 * Get the term data that is posted to the wendor's website.
 *
 * @param int $term_id Holds the term ID.
 * @return boolean|array
 */
function svn_get_taxonomy_term_data( $term_id ) {

	if ( empty( $term_id ) ) {
		return false;
	}

	$term = get_term( $term_id );

	if ( empty( $term ) || null === $term ) {
		return false;
	}

	if ( 'product_cat' === $term->taxonomy ) {
		$thumbnail_src = '';
		$thumbnail_id  = get_term_meta( $term_id, 'thumbnail_id', true );
		$display_type  = get_term_meta( $term_id, 'display_type', true );

		if ( ! empty( $thumbnail_id ) ) {
			$thumbnail_src = wp_get_attachment_url( $thumbnail_id );
		}

		// Display type is received as blank while importing woocommerce default products CSV.
		if ( empty( $display_type ) ) {
			$display_type = 'default';
		}

		// Gather the parent category ID.
		$remote_parent_term = 0;

		if ( 0 !== $term->parent ) {
			$remote_parent_term = get_term_meta( $term->parent, 'synced_vendor_with_id', true );
		}

		$term_data = array(
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'display'     => $display_type,
			'parent'      => $remote_parent_term,
			'image'       => array(
				'src' => $thumbnail_src,
			),
		);
	} else {
		$term_data = array(
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
		);
	}

	$term_data['meta'] = array(
		array(
			'key'   => 'synced_marketplace_with_id',
			'value' => $term_id,
		),
	);

	return apply_filters( 'svn_exported_term_data', $term_data, $term );
}

/**
 * Prepare the variation data to be exported to the vendor's store.
 *
 * @param object $variation Holds the woocommerce variation object data.
 * @param int    $remote_parent_id Holds the remote parent variable product ID.
 * @return boolean|array
 */
function svn_get_variation_data( $variation, $remote_parent_id ) {

	if ( empty( $variation ) ) {
		return false;
	}

	// Gather the sale start from schedule.
	$date_on_sale_from = get_post_meta( $variation->get_id(), '_sale_price_dates_from', true );
	$sale_date_from    = '';

	if ( ! empty( $date_on_sale_from ) ) {
		$sale_date_from = gmdate( 'Y-m-d', $date_on_sale_from );
	}

	// Gather the sale ends on schedule.
	$date_on_sale_to = get_post_meta( $variation->get_id(), '_sale_price_dates_to', true );
	$sale_date_to    = '';

	if ( ! empty( $date_on_sale_to ) ) {
		$sale_date_to = gmdate( 'Y-m-d', $date_on_sale_to );
	}

	// Gather downloads.
	$downloads        = $variation->get_downloads();
	$remote_downloads = array();

	if ( ! empty( $downloads ) && is_array( $downloads ) ) {
		foreach ( $downloads as $download ) {
			$remote_downloads[] = array(
				'id'   => $download->get_id(),
				'name' => $download->get_name(),
				'file' => $download->get_file(),
			);
		}
	}

	// Gather product attributes.
	$attributes        = $variation->get_attributes();
	$remote_attributes = array();

	if ( ! empty( $attributes ) && is_array( $attributes ) ) {
		foreach ( $attributes as $taxonomy => $term_slug ) {
			$term = get_term_by( 'slug', $term_slug, $taxonomy );

			if ( ! empty( $term ) ) {
				$remote_attr = svn_get_remote_product_attribute_by_slug( $taxonomy );

				if ( false !== $remote_attr ) {
					$remote_attributes[] = array(
						'id'     => $remote_attr['remote_id'],
						'name'   => $remote_attr['name'],
						'option' => $term->name,
					);
				}
			}
		}
	}

	// Gather variation image ID.
	$image_id            = $variation->get_image_id();
	$variation_image_src = wc_placeholder_img_src();

	if ( ! empty( $image_id ) ) {
		$variation_image_src = svn_get_image_src_by_id( $image_id );
	}

	$variation_data = array(
		'title'              => $variation->get_title(),
		'formatted_name'     => $variation->get_formatted_name(),
		'sku'                => $variation->get_sku(),
		'weight'             => $variation->get_weight(),
		'dimensions'         => array(
			'length' => $variation->get_length(),
			'width'  => $variation->get_width(),
			'height' => $variation->get_height(),
		),
		'tax_status'         => $variation->get_tax_status(),
		'tax_class'          => $variation->get_tax_class(),
		'manage_stock'       => $variation->get_manage_stock(),
		'stock_quantity'     => $variation->get_stock_quantity(),
		'stock_status'       => $variation->get_stock_status(),
		'backorders'         => $variation->get_backorders(),
		'purchase_note'      => $variation->get_purchase_note(),
		'catalog_visibility' => $variation->get_catalog_visibility(),
		'regular_price'      => $variation->get_regular_price(),
		'sale_price'         => $variation->get_sale_price(),
		'date_on_sale_from'  => $sale_date_from,
		'date_on_sale_to'    => $sale_date_to,
		'virtual'            => $variation->get_virtual(),
		'downloadable'       => $variation->get_downloadable(),
		'downloads'          => $remote_downloads,
		'download_expiry'    => $variation->get_download_expiry(),
		'download_limit'     => $variation->get_download_limit(),
		'parent_id'          => $remote_parent_id,
		'description'        => $variation->get_description(),
		'attributes'         => $remote_attributes,
		'meta_data'          => array(
			array(
				'key'   => 'synced_vendor_with_id',
				'value' => $variation->get_id(),
			),
		),
		'featured_image_url' => $variation_image_src,
	);

	return apply_filters( 'svn_exported_variation_data', $variation_data, $variation );
}

/**
 * Prepare the attribute data which shall be posted to the vendor's store.
 *
 * @param int   $attr_id Holds the attribute ID.
 * @param array $attr_data Holds the attribute data.
 * @return boolean|array
 */
function svn_prepare_product_attribute_data( $attr_id, $attr_data ) {

	if ( empty( $attr_id ) ) {
		return false;
	}

	if ( empty( $attr_data ) ) {
		return false;
	}

	// Prepare the data now.
	$attribute = array(
		'name'         => ( ! empty( $attr_data['attribute_label'] ) ) ? $attr_data['attribute_label'] : '',
		'slug'         => ( ! empty( $attr_data['attribute_name'] ) ) ? 'pa_' . $attr_data['attribute_name'] : '',
		'type'         => ( ! empty( $attr_data['attribute_type'] ) ) ? $attr_data['attribute_type'] : '',
		'order_by'     => ( ! empty( $attr_data['attribute_orderby'] ) ) ? $attr_data['attribute_orderby'] : '',
		'has_archives' => ( ! empty( $attr_data['attribute_public'] ) && 1 === $attr_data['attribute_public'] ) ? true : false,
	);

	return apply_filters( 'svn_exported_product_attribute', $attribute, $attr_data );

}

/**
 * Get remote attribute ID from source attribute slug.
 *
 * @param int $attr_slug Holds the attribute slug.
 * @return boolean|array
 */
function svn_get_remote_product_attribute_by_slug( $attr_slug ) {

	if ( empty( $attr_slug ) ) {
		return false;
	}

	$saved_remote_attributes = get_option( 'svn_saved_remote_attributes' );

	if ( ! empty( $saved_remote_attributes ) && is_array( $saved_remote_attributes ) ) {
		foreach ( $saved_remote_attributes as $index => $saved_attr ) {

			if ( ! empty( $saved_attr['slug'] ) && $attr_slug === $saved_attr['slug'] ) {
				return $saved_remote_attributes[ $index ];
			}
		}
	}

	return false;
}

/**
 * Fetch the WooCommerce attributes taxonomies.
 *
 * @return boolean|array
 */
function svn_get_wc_product_attribute_taxonomies() {
	$wc_attributes      = wc_get_attribute_taxonomies();
	$wc_attr_taxonomies = array();

	if ( empty( $wc_attributes ) || ! is_array( $wc_attributes ) ) {
		return false;
	}

	foreach ( $wc_attributes as $attribute ) {
		$wc_attr_taxonomies[] = "pa_{$attribute->attribute_name}";
	}

	return $wc_attr_taxonomies;
}

/**
 * Get the pre-defined WordPress & WooCommerce taxonomies.
 *
 * @return array
 */
function svn_get_wp_wc_default_taxonomies() {

	return array( 'product_tag', 'product_cat', 'product_shipping_class' );
}

/**
 * Fetch all the registered WP & WC taxonomies.
 *
 * @return array
 */
function svn_get_wp_wc_taxonomies() {
	// Default WordPress & WooCommerce taxonomies.
	$taxonomies = svn_get_wp_wc_default_taxonomies();

	// Fetch the registered WC product attribute taxonomies.
	$wc_attr_taxonomies = svn_get_wc_product_attribute_taxonomies();

	/**
	 * Merge both the taxonomies now.
	 * If WC attribute taxonomies are available.
	 */
	if ( false !== $wc_attr_taxonomies ) {
		$taxonomies = array_merge( $taxonomies, $wc_attr_taxonomies );
	}

	return $taxonomies;
}

/**
 * Gather the dynamic hooks and callbacks for the row actions of the taxonomies.
 * This is purposely done for the dynamic taxonomies generated by product attributes.
 *
 * @return array
 */
function svn_get_wp_wc_taxonomies_hooks() {
	$taxonomies     = svn_get_wp_wc_taxonomies();
	$taxonomy_hooks = array();

	if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_hooks[ "{$taxonomy}_row_actions" ] = str_replace( '-', '_', "svn_{$taxonomy}_row_actions_callback" );
		}
	}

	return $taxonomy_hooks;
}

// Hooks related to row actions for taxonomy terms.
$taxonomy_hooks = svn_get_wp_wc_taxonomies_hooks();

if ( ! empty( $taxonomy_hooks ) && is_array( $taxonomy_hooks ) ) {
	foreach ( $taxonomy_hooks as $taxonomy_hook => $taxonomy_callback ) {
		add_filter(
			$taxonomy_hook,
			function( $actions, $term ) {

				if ( ! current_user_can( 'manage_options' ) ) {
					return $actions;
				}

				/* translators: %s: term ID */
				$actions['svn-term-id'] = sprintf( __( 'ID: %1$s', 'sync-vendor' ), $term->term_id );

				// Get the term ID at vendor's store.
				$vendor_term_id = get_term_meta( $term->term_id, 'synced_vendor_with_id', true );

				if ( empty( $vendor_term_id ) ) {
					return $actions;
				}

				/* translators: %s: marketplace user ID */
				$actions['svn-marketplace-user-id'] = sprintf( __( 'Vendor\'s ID: %1$s', 'sync-vendor' ), $vendor_term_id );

				return $actions;
			},
			20,
			2
		);
	}
}

/**
 * Prepare the order data to be exported to the vendor's store.
 *
 * @param object $order Holds the woocommerce order object data.
 * @return boolean|array
 */
function svn_get_order_data( $order ) {

	if ( false === $order ) {
		return false;
	}

	// Gather the shipping lines.
	$shipping_methods = $order->get_shipping_methods();
	$shipping_lines   = array();

	if ( ! empty( $shipping_methods ) && is_array( $shipping_methods ) ) {
		foreach ( $shipping_methods as $shipping_method ) {
			$temp = array(
				'method_id'    => $shipping_method->get_method_id(),
				'method_title' => $shipping_method->get_method_title(),
				'total'        => $shipping_method->get_total(),
			);

			if ( ! empty( wc_get_order_item_meta( $shipping_method->get_id(), 'synced_vendor_with_id' ) ) ) {
				$temp['id'] = wc_get_order_item_meta( $shipping_method->get_id(), 'synced_vendor_with_id' );
			}

			$shipping_lines[] = $temp;
		}
	}

	// Gather the coupon lines.
	$used_coupons = $order->get_coupon_codes();
	$coupon_lines = array();

	if ( ! empty( $used_coupons ) && is_array( $used_coupons ) ) {
		foreach ( $used_coupons as $used_coupon ) {
			$coupon_lines[] = array(
				'code' => $used_coupon,
			);
		}
	}

	$order_data = array(
		'status'               => $order->get_status(),
		'currency'             => $order->get_currency(),
		'customer_note'        => $order->get_customer_note(),
		'billing'              => array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'company'    => $order->get_billing_company(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
		),
		'shipping'             => array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'company'    => $order->get_shipping_company(),
			'address_1'  => $order->get_shipping_address_1(),
			'address_2'  => $order->get_shipping_address_2(),
			'city'       => $order->get_shipping_city(),
			'state'      => $order->get_shipping_state(),
			'postcode'   => $order->get_shipping_postcode(),
			'country'    => $order->get_shipping_country(),
		),
		'payment_method'       => $order->get_payment_method(),
		'payment_method_title' => $order->get_payment_method_title(),
		'transaction_id'       => $order->get_transaction_id(),
		'line_items'           => svn_get_order_line_items( $order->get_items() ),
		'shipping_lines'       => $shipping_lines,
		'fee_lines'            => array(),
		'coupon_lines'         => $coupon_lines,
		'meta_data'            => array(
			array(
				'key'   => 'synced_marketplace_with_id',
				'value' => $order->get_id(),
			),
		),
	);

	$order_data['billing']  = (object) $order_data['billing'];
	$order_data['shipping'] = (object) $order_data['shipping'];

	return apply_filters( 'svn_exported_order_data', $order_data, $order );
}

/**
 * Prepare line items to be exported to the vendor's store.
 *
 * @param object $line_items Holds the woocommerce line items object.
 * @return boolean|array
 */
function svn_get_order_line_items( $line_items ) {

	if ( empty( $line_items ) ) {
		return false;
	}

	$items = array();

	foreach ( $line_items as $line_item_id => $line_item ) {
		$temp = array(
			'quantity'  => $line_item->get_quantity(),
			'tax_class' => $line_item->get_tax_class(),
			'subtotal'  => $line_item->get_subtotal(), // before discounts.
			'total'     => $line_item->get_total(), // after discounts.
		);

		// Gather the remote item ID.
		$remote_item_id = wc_get_order_item_meta( $line_item_id, 'synced_vendor_with_id' );

		if ( ! empty( $remote_item_id ) ) {
			$temp['id'] = $remote_item_id;
		}

		// Gather the product ID.
		$product_id = $line_item->get_product_id();

		if ( ! empty( $product_id ) ) {
			$temp['product_id'] = get_post_meta( $product_id, 'synced_vendor_with_id', true );
		}

		// Gather the variation ID.
		$variation_id = $line_item->get_variation_id();

		if ( ! empty( $variation_id ) ) {
			$temp['variation_id'] = get_post_meta( $variation_id, 'synced_vendor_with_id', true );
		}

		/**
		 * Fetch product and variation title based on whichever is available.
		 */
		if ( array_key_exists( 'variation_id', $temp ) ) {
			$temp['name'] = get_the_title( $variation_id );
		} else {
			$temp['name'] = get_the_title( $product_id );
		}

		$items[] = $temp;
	}

	return apply_filters( 'svn_exported_order_line_items_data', $items, $line_items );
}

/**
 * Prepare the product review data.
 *
 * @param int    $post_id Holds the post ID.
 * @param object $comment Holds the WordPress comment object.
 * @return boolean|array
 */
function svn_get_product_review_data( $post_id, $comment ) {

	if ( empty( $comment ) ) {
		return false;
	}

	$comment_id = $comment->comment_ID;

	// Gather the remote product ID.
	$remote_post_id = get_post_meta( $post_id, 'synced_vendor_with_id', true );

	if ( empty( $remote_post_id ) ) {
		return false;
	}

	$comment_status = $comment->comment_approved;

	if ( '1' === $comment_status ) {
		$status = 'approved';
	} elseif ( '0' === $comment_status ) {
		$status = 'hold';
	} elseif ( 'spam' === $comment_status ) {
		$status = 'spam';
	}

	$comment_verified = get_comment_meta( $comment_id, 'verified', true );

	if ( ! empty( $comment_verified ) && '1' === $comment_verified ) {
		$verified = true;
	} else {
		$verified = false;
	}

	$comment_data = array(
		'product_id'     => $remote_post_id,
		'status'         => $status,
		'reviewer'       => $comment->comment_author,
		'reviewer_email' => $comment->comment_author_email,
		'review'         => $comment->comment_content,
		'rating'         => get_comment_meta( $comment_id, 'rating', true ),
		'verified'       => $verified,
		'meta'           => array(
			array(
				'key'   => 'synced_marketplace_with_id',
				'value' => $comment_id,
			),
		),
	);

	return $comment_data;
}

/**
 * Get tax rate locations from tax rate ID.
 *
 * @param int $tax_rate_id Holds the tax rate ID.
 * @return boolean|array
 */
function svn_get_tax_rate_locations_by_id( $tax_rate_id ) {
	global $wpdb;

	if ( empty( $tax_rate_id ) ) {
		return false;
	}

	$tax_rate_locations = array();
	$tax_rate_tbl       = $wpdb->prefix . 'woocommerce_tax_rate_locations';
	$query              = $wpdb->prepare(
		"SELECT `location_code`, `location_type` FROM {$tax_rate_tbl} WHERE `tax_rate_id` = %s",
		$tax_rate_id
	);
	$results            = $wpdb->get_results( $query );

	if ( ! empty( $results ) && is_array( $results ) ) {
		foreach ( $results as $result ) {
			$tax_rate_locations[ $result->location_type ] = $result->location_code;
		}
	}

	return $tax_rate_locations;
}

/**
 * Prepare the tax rate data array.
 *
 * @param int   $tax_rate_id Holds the tax rate ID.
 * @param array $tax_rate Holds the tax rate.
 * @return boolean|array
 */
function svn_get_tax_rate_data( $tax_rate_id, $tax_rate ) {

	if ( empty( $tax_rate_id ) ) {
		return false;
	}

	if ( empty( $tax_rate ) ) {
		return false;
	}

	$tax_rate_locations = svn_get_tax_rate_locations_by_id( $tax_rate_id );
	$tax_rate_data      = array(
		'country'  => ( ! empty( $tax_rate['tax_rate_country'] ) ) ? $tax_rate['tax_rate_country'] : '',
		'state'    => ( ! empty( $tax_rate['tax_rate_state'] ) ) ? $tax_rate['tax_rate_state'] : '',
		'postcode' => ( ! empty( $tax_rate_locations['postcode'] ) ) ? $tax_rate_locations['postcode'] : '',
		'city'     => ( ! empty( $tax_rate_locations['city'] ) ) ? $tax_rate_locations['city'] : '',
		'rate'     => ( ! empty( $tax_rate['tax_rate'] ) ) ? $tax_rate['tax_rate'] : '',
		'name'     => ( ! empty( $tax_rate['tax_rate_name'] ) ) ? $tax_rate['tax_rate_name'] : '',
		'priority' => ( ! empty( $tax_rate['tax_rate_priority'] ) ) ? $tax_rate['tax_rate_priority'] : 1,
		'compound' => ( ! empty( $tax_rate['tax_rate_compound'] ) && '1' === $tax_rate['tax_rate_compound'] ) ? true : false,
		'shipping' => ( ! empty( $tax_rate['tax_rate_shipping'] ) && '1' === $tax_rate['tax_rate_shipping'] ) ? true : false,
		'order'    => ( ! empty( $tax_rate['tax_rate_order'] ) ) ? $tax_rate['tax_rate_order'] : 1,
		'class'    => ( ! empty( $tax_rate['tax_rate_class'] ) ) ? $tax_rate['tax_rate_class'] : 'standard',
	);

	// Gather remote tax rate.
	$remote_tax_rate_id = svn_get_remote_tax_rate_id( $tax_rate_id );

	if ( ! empty( $remote_tax_rate_id ) && false !== $remote_tax_rate_id ) {
		$tax_rate_data['id'] = $remote_tax_rate_id;
	}

	return $tax_rate_data;
}

/**
 * Get remote tax rate ID from source tax rate ID.
 *
 * @param int $tax_rate_id Holds the tax rate ID.
 * @return boolean|string
 */
function svn_get_remote_tax_rate_id( $tax_rate_id ) {

	if ( empty( $tax_rate_id ) ) {
		return false;
	}

	$saved_remote_tax_rates = get_option( 'svn_saved_remote_tax_rates' );

	if ( empty( $saved_remote_tax_rates ) || ! is_array( $saved_remote_tax_rates ) ) {
		return false;
	}

	if ( empty( $saved_remote_tax_rates[ $tax_rate_id ]['remote_id'] ) ) {
		return false;
	}

	return $saved_remote_tax_rates[ $tax_rate_id ]['remote_id'];
}

/**
 * Get associated vendor ID by tax class.
 *
 * @param string $tax_class Holds the tax class.
 * @return boolean|int
 */
function svn_get_associated_vendor_by_tax_class( $tax_class ) {

	if ( empty( $tax_class ) ) {
		return false;
	}

	$saved_remote_tax_classes = get_option( 'svn_saved_remote_tax_classes' );

	if ( empty( $saved_remote_tax_classes ) || ! is_array( $saved_remote_tax_classes ) ) {
		return false;
	}

	if ( ! array_key_exists( $tax_class, $saved_remote_tax_classes ) ) {
		return false;
	}

	if ( empty( $saved_remote_tax_classes[ $tax_class ]['associated_vendor_id'] ) ) {
		return false;
	}

	$associated_vendor_id = (int) $saved_remote_tax_classes[ $tax_class ]['associated_vendor_id'];

	return $associated_vendor_id;
}

/**
 * Get remote webhook ID.
 *
 * @param int $webhook_id Holds the webhook ID.
 * @return boolean|int
 */
function svn_get_remote_webhook_id( $webhook_id ) {

	if ( empty( $webhook_id ) ) {
		return false;
	}

	$saved_remote_webhooks = get_option( 'svn_saved_remote_webhooks' );

	if ( empty( $saved_remote_webhooks ) || ! is_array( $saved_remote_webhooks ) ) {
		return false;
	}

	if ( ! array_key_exists( $webhook_id, $saved_remote_webhooks ) ) {
		return false;
	}

	if ( empty( $saved_remote_webhooks[ $webhook_id ]['remote_id'] ) ) {
		return false;
	}

	$remote_webhook_id = (int) $saved_remote_webhooks[ $webhook_id ]['remote_id'];

	return $remote_webhook_id;
}

/**
 * Get associated vendor ID by webhook ID.
 *
 * @param int $webhook_id Holds the webhook ID.
 * @return boolean|int
 */
function svn_get_associated_vendor_id_by_webhook_id( $webhook_id ) {

	if ( empty( $webhook_id ) ) {
		return false;
	}

	$saved_remote_webhooks = get_option( 'svn_saved_remote_webhooks' );

	if ( empty( $saved_remote_webhooks ) || ! is_array( $saved_remote_webhooks ) ) {
		return false;
	}

	if ( ! array_key_exists( $webhook_id, $saved_remote_webhooks ) ) {
		return false;
	}

	if ( empty( $saved_remote_webhooks[ $webhook_id ]['associated_vendor_id'] ) ) {
		return false;
	}

	$remote_webhook_id = (int) $saved_remote_webhooks[ $webhook_id ]['associated_vendor_id'];

	return $remote_webhook_id;
}

/**
 * Prepare the webhook data.
 *
 * @param int    $webhook_id Holds the webhook ID.
 * @param object $webhook Holds the WC webhook object.
 * @return boolean|array
 */
function svn_get_webhook_data( $webhook_id, $webhook ) {

	if ( empty( $webhook_id ) ) {
		return false;
	}

	if ( empty( $webhook ) ) {
		return false;
	}

	$webhook_data = array(
		'name'         => $webhook->get_name(),
		'status'       => $webhook->get_status(),
		'topic'        => $webhook->get_topic(),
		'delivery_url' => $webhook->get_delivery_url(),
		'secret'       => $webhook->get_secret(),
	);

	// Gather remote webhook.
	$remote_webhook_id = svn_get_remote_webhook_id( $webhook_id );

	if ( ! empty( $remote_webhook_id ) && false !== $remote_webhook_id ) {
		$webhook_data['id'] = $remote_webhook_id;
	}

	return $webhook_data;
}

/**
 * Gather the order refund data.
 *
 * @param int $refund_id Holds the order refund ID.
 * @return boolean|array
 */
function svn_get_order_refund_data( $refund_id ) {

	if ( empty( $refund_id ) || 0 === $refund_id ) {
		return false;
	}

	$refund      = new WC_Order_Refund( $refund_id );
	$refund_data = array(
		'amount'      => $refund->get_amount(),
		'reason'      => $refund->get_reason(),
		'refunded_by' => $refund->get_refunded_by(),
		'line_items'  => svn_get_refund_items( $refund->get_items() ),
		'api_refund'  => $refund->get_refunded_payment(),
		'meta_data'   => array(
			array(
				'key'   => 'synced_marketplace_with_id',
				'value' => $refund_id,
			),
		),
	);

	return $refund_data;
}

/**
 * Get order refunded line items.
 *
 * @param array $refund_items Holds the refund items array.
 * @return array
 */
function svn_get_refund_items( $refund_items ) {

	if ( empty( $refund_items ) ) {
		return false;
	}

	if ( ! is_array( $refund_items ) ) {
		return false;
	}

	$refund_line_items = array();

	foreach ( $refund_items as $refund_item ) {
		// Get remote item ID.
		$refund_item_id      = $refund_item->get_id();
		$refund_line_item_id = ( ! empty( $refund_item_id ) ) ? wc_get_order_item_meta( $refund_item_id, '_refunded_item_id' ) : '';
		$remote_line_item_id = ( ! empty( $refund_line_item_id ) ) ? wc_get_order_item_meta( $refund_line_item_id, 'synced_vendor_with_id' ) : '';

		// Get remote product ID.
		$product_id        = $refund_item->get_product_id();
		$remote_product_id = ( ! empty( $product_id ) ) ? get_post_meta( $product_id, 'synced_vendor_with_id', true ) : 0;

		// Get remote variation ID.
		$variation_id        = $refund_item->get_variation_id();
		$remote_variation_id = ( ! empty( $variation_id ) ) ? get_post_meta( $variation_id, 'synced_vendor_with_id', true ) : 0;

		// Gather all the refund item data.
		$refund_line_items[] = array(
			'id'           => $remote_line_item_id,
			'name'         => $refund_item->get_name(),
			'product_id'   => $remote_product_id,
			'variation_id' => $remote_variation_id,
			'quantity'     => $refund_item->get_quantity(),
			'tax_class'    => $refund_item->get_tax_class(),
			'subtotal'     => $refund_item->get_subtotal(),
			'total'        => $refund_item->get_total(),
		);
	}

	return $refund_line_items;
}

/**
 * Get order ID from refund ID.
 *
 * @param int $refund_id Holds the order refund ID.
 * @return boolean|int
 */
function svn_get_order_id_by_refund_id( $refund_id ) {

	if ( empty( $refund_id ) ) {
		return false;
	}

	$refund = new WC_Order_Refund( $refund_id );

	return $refund->get_parent_id();
}

/**
 * Returns the error message html in admin panel.
 *
 * @param string $message Holds the error message string.
 * @return string
 */
function svn_get_admin_error_message_html( $message ) {
	ob_start();
	?>
	<div id="setting-error-settings_updated" class="notice notice-error settings-error is-dismissible">
		<p><strong><?php echo wp_kses_post( $message ); ?></strong></p>
		<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Get REST API data for vendor.
 *
 * @param int $vendor_id Holds the vendor ID.
 * @return boolean|int
 */
function svn_get_vendor_rest_api_data( $vendor_id ) {

	if ( empty( $vendor_id ) ) {
		return false;
	}

	$vendor_url      = get_option( "svn_vendor_{$vendor_id}_url" );
	$consumer_key    = get_option( "svn_vendor_{$vendor_id}_rest_api_consumer_key" );
	$consumer_secret = get_option( "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key" );

	if (
		empty( $vendor_url ) ||
		empty( $consumer_key ) ||
		empty( $consumer_secret )
	) {
		return -1;
	}

	return 1;
}
