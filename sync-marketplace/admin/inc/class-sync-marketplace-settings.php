<?php
/**
 * The admin-settings of the plugin.
 *
 * @link       https://github.com/vermadarsh/
 * @since      1.0.0
 *
 * @package    Sync_Marketplace
 * @subpackage Sync_Marketplace/admin/inc
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( class_exists( 'Sync_Marketplace_Settings', false ) ) {
	return new Sync_Marketplace_Settings();
}

/**
 * Settings class for keeping data sync with marketplace.
 */
class Sync_Marketplace_Settings extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'sync-marketplace';
		$this->label = __( 'Sync Marketplace', 'sync-marketplace' );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			''         => __( 'General', 'sync-marketplace' ),
			'rest-api' => __( 'Rest API', 'sync-marketplace' ),
			'read-log' => __( 'Sync Log', 'sync-marketplace' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
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

		if ( 'rest-api' === $current_section ) {
			$settings = $this->smp_rest_api_settings_fields();
		} elseif ( 'read-log' === $current_section ) {
			$settings = $this->smp_read_log_settings_fields();
		} else {
			$settings = $this->smp_general_settings_fields();
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Return the fields for general settings.
	 *
	 * @return array
	 */
	public function smp_general_settings_fields() {

		return apply_filters(
			'woocommerce_sync_marketplace_settings',
			apply_filters(
				'woocommerce_sync_marketplace_general_settings',
				array(
					array(
						'title' => __( 'General Settings', 'sync-marketplace' ),
						'type'  => 'title',
						'desc'  => '',
						'id'    => 'smp_general_settings_title',
					),
					array(
						'title'             => __( 'Vendor ID', 'sync-marketplace' ),
						'desc'              => __( 'This holds the vendor ID at the marketplace.', 'sync-marketplace' ),
						'desc_tip'          => true,
						'id'                => 'smp_vendor_id_at_marketplace',
						'placeholder'       => __( 'E.g.: 99', 'sync-marketplace' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'min' => 1,
						),
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
	public function smp_rest_api_settings_fields() {

		return apply_filters(
			'woocommerce_sync_marketplace_rest_api_settings',
			array(
				array(
					'title' => __( 'Rest API Settings', 'sync-marketplace' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'smp_rest_api_settings_title',
				),
				array(
					'title'       => __( 'App Name', 'sync-marketplace' ),
					'desc'        => __( 'This holds the marketplace rest API application name.', 'sync-marketplace' ),
					'desc_tip'    => true,
					'id'          => 'smp_marketplace_rest_api_app_name',
					'type'        => 'text',
					'placeholder' => __( 'E.g.: My App', 'sync-marketplace' ),
				),
				array(
					'name'     => esc_html__( 'Scope', 'sync-marketplace' ),
					'type'     => 'select',
					'options'  => array(
						'read'       => esc_html__( 'Read', 'sync-marketplace' ),
						'write'      => esc_html__( 'Write', 'sync-marketplace' ),
						'read_write' => esc_html__( 'Read & Write', 'sync-marketplace' ),
					),
					'class'    => 'wc-enhanced-select',
					'desc'     => esc_html__( 'This holds the Rest API scope.', 'sync-marketplace' ),
					'desc_tip' => true,
					'default'  => '',
					'id'       => 'smp_marketplace_rest_api_app_scope'
				),
				array(
					'title'       => __( 'Marketplace URL', 'sync-marketplace' ),
					'desc'        => __( 'This holds the marketplace URL.', 'sync-marketplace' ),
					'desc_tip'    => true,
					'id'          => 'smp_marketplace_url',
					'type'        => 'url',
					'placeholder' => 'http(s)://example.com',
				),
				array(
					'title'       => __( 'Consumer Key', 'sync-marketplace' ),
					'desc'        => __( 'This holds the marketplace rest API consumer key.', 'sync-marketplace' ),
					'desc_tip'    => true,
					'id'          => 'smp_marketplace_rest_api_consumer_key',
					'type'        => 'text',
					'placeholder' => 'ck_**********',
				),
				array(
					'title'       => __( 'Consumer Secret', 'sync-marketplace' ),
					'desc'        => __( 'This holds the marketplace rest API consumer secret key.', 'sync-marketplace' ),
					'desc_tip'    => true,
					'id'          => 'smp_marketplace_rest_api_consumer_secret_key',
					'type'        => 'text',
					'placeholder' => 'cs_**********',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'smp_rest_api_settings_end',
				),

			)
		);
	}

	/**
	 * Return the fields for Rest API settings.
	 *
	 * @return array
	 */
	public function smp_read_log_settings_fields() {
		global $wp_filesystem;
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();

		$local_file = SMP_LOG_DIR_PATH . 'sync-log.log';

		// Fetch the file contents.
		$content = '';

		if ( $wp_filesystem->exists( $local_file ) ) {
			$content = $wp_filesystem->get_contents( $local_file );
		}

		return apply_filters(
			'woocommerce_sync_marketplace_read_log_settings',
			array(
				array(
					'title' => __( 'Read Log Settings', 'sync-marketplace' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'smp_read_log_settings_title',
				),
				array(
					'title'             => __( 'Sync Log', 'sync-marketplace' ),
					'desc'              => __( 'This holds the sync log.', 'sync-marketplace' ),
					'desc_tip'          => true,
					'id'                => 'smp_sync_log_input',
					'type'              => 'textarea',
					'value'             => $content,
					'class'             => 'smp-read-sync-log',
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'smp_read_log_settings_end',
				),

			)
		);
	}
}

return new Sync_Marketplace_Settings();
