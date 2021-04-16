<?php
/**
 * The admin-settings of the plugin.
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Vendor
 * @subpackage Sync_Vendor/admin/inc
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( class_exists( 'Sync_Vendor_Settings', false ) ) {
	return new Sync_Vendor_Settings();
}

/**
 * Settings class for keeping data sync with vendor.
 */
class Sync_Vendor_Settings extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'sync-vendor';
		$this->label = __( 'Sync Vendor', 'sync-vendor' );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array_merge(
			array(
				'' => __( 'General', 'sync-vendor' ),
			),
			$this->svn_get_sections_by_vendors(),
			array(
				'read-log' => __( 'Sync Log', 'sync-vendor' ),
			)
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Return the sections array, by the registered vendors.
	 */
	public function svn_get_sections_by_vendors() {
		$vendor_sections = array();
		$vendors         = get_users(
			array(
				'role' => 'seller',
			)
		);

		if ( empty( $vendors ) || ! is_array( $vendors ) ) {
			return $vendor_sections;
		}

		foreach ( $vendors as $vendor ) {
			$vendor_heading                  = "#{$vendor->ID} - {$vendor->data->display_name}";
			$vendor_slug                     = sanitize_title( $vendor_heading );
			$vendor_sections[ $vendor_slug ] = $vendor_heading;
		}

		return $vendor_sections;
	}

	/**
	 * Output the settings.
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings.
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );

		if ( $current_section ) {
			do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
		}
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section name.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {

		$vendor_sections = $this->svn_get_sections_by_vendors();

		if ( ! empty( $vendor_sections ) && array_key_exists( $current_section, $vendor_sections ) ) {
			$settings = $this->svn_rest_api_settings_fields();
		} elseif ( 'read-log' === $current_section ) {
			$settings = $this->svn_read_log_settings_fields();
		} else {
			$settings = array();
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Return the fields for general settings.
	 *
	 * @return array
	 */
	public function svn_general_settings_fields() {

		return apply_filters(
			'woocommerce_sync_vendor_settings',
			apply_filters(
				'woocommerce_sync_vendor_general_settings',
				array(
					array(
						'title' => __( 'General Settings', 'sync-vendor' ),
						'type'  => 'title',
						'desc'  => '',
						'id'    => 'smp_general_settings_title',
					),
					array(
						'title'       => __( 'Vendor ID', 'sync-vendor' ),
						'desc'        => __( 'This holds the vendor ID at the vendor.', 'sync-vendor' ),
						'desc_tip'    => true,
						'id'          => 'smp_vendor_id_at_vendor',
						'placeholder' => __( 'E.g.: 99', 'sync-vendor' ),
						'type'        => 'number',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'smp_general_settings_end',
					),

				)
			)
		);
	}

	/**
	 * Return the fields for Rest API settings.
	 *
	 * @return array
	 */
	public function svn_rest_api_settings_fields() {
		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );

		if ( empty( $section ) ) {
			return array();
		}

		$section_parts = explode( '-', $section );

		if ( empty( (int) $section_parts[0] ) || 0 === (int) $section_parts[0] ) {
			return array();
		}

		$vendor_id = (int) $section_parts[0];

		return apply_filters(
			"woocommerce_sync_vendor_rest_api_vendor_{$vendor_id}_settings",
			array(
				array(
					'title' => __( 'Rest API Settings', 'sync-vendor' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'svn_rest_api_settings_title',
				),
				array(
					'title'             => __( 'Vendor\'s URL', 'sync-vendor' ),
					'desc'              => __( 'This holds the vendor\'s URL.', 'sync-vendor' ),
					'desc_tip'          => true,
					'id'                => "svn_vendor_{$vendor_id}_url",
					'type'              => 'url',
					'placeholder'       => 'http(s)://example.com',
					'custom_attributes' => array(
						'required' => 'required',
					),
				),
				array(
					'title'             => __( 'Consumer Key', 'sync-vendor' ),
					'desc'              => __( 'This holds the vendor\'s rest API consumer key.', 'sync-vendor' ),
					'desc_tip'          => true,
					'id'                => "svn_vendor_{$vendor_id}_rest_api_consumer_key",
					'type'              => 'text',
					'placeholder'       => 'ck_**********',
					'custom_attributes' => array(
						'required' => 'required',
					),
				),
				array(
					'title'             => __( 'Consumer Secret', 'sync-vendor' ),
					'desc'              => __( 'This holds the vendor\'s rest API consumer secret key.', 'sync-vendor' ),
					'desc_tip'          => true,
					'id'                => "svn_vendor_{$vendor_id}_rest_api_consumer_secret_key",
					'type'              => 'text',
					'placeholder'       => 'cs_**********',
					'custom_attributes' => array(
						'required' => 'required',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'svn_rest_api_settings_end',
				),

			)
		);
	}

	/**
	 * Return the fields for Rest API settings.
	 *
	 * @return array
	 */
	public function svn_read_log_settings_fields() {
		global $wp_filesystem;
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();

		$local_file = SVN_LOG_DIR_PATH . 'sync-log.log';

		// Fetch the file contents.
		$content = '';

		if ( $wp_filesystem->exists( $local_file ) ) {
			$content = $wp_filesystem->get_contents( $local_file );
		}

		return apply_filters(
			'woocommerce_sync_vendor_read_log_settings',
			array(
				array(
					'title' => __( 'Read Log Settings', 'sync-vendor' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'svn_read_log_settings_title',
				),
				array(
					'title'             => __( 'Sync Log', 'sync-vendor' ),
					'desc'              => __( 'This holds the sync log.', 'sync-vendor' ),
					'desc_tip'          => true,
					'id'                => 'svn_sync_log_input',
					'type'              => 'textarea',
					'value'             => $content,
					'class'             => 'svn-read-sync-log',
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'svn_read_log_settings_end',
				),

			)
		);
	}
}

return new Sync_Vendor_Settings();
