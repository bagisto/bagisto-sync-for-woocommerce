<?php
/**
 * Tax settings page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin\Settings\Tabs;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Tax_Settings' ) ) {
	/**
	 * Tax settings page template class
	 */
	class Wkwctobag_Tax_Settings {

		/**
		 * Helper class instance.
		 *
		 * @var Helper\Wkwctobag_Helper $helper Helper class instance.
		 */
		protected $helper = null;

		/**
		 * WooCommerce Tax Classes.
		 *
		 * @var array $tax_classes Tax Classes.
		 */
		protected $tax_classes = array();

		/**
		 * Bagisto tax categories data.
		 *
		 * @var array $bagisto_tax_categories Bagisto tax categories.
		 */
		protected $bagisto_tax_categories = array();

		/**
		 * Settings data.
		 */
		protected $data = array(
			'auto_sync_taxes' => 'enabled',
			'tax_mapping'     => array(),
		);

		/**
		 * Constructor of the tax settings page.
		 */
		public function __construct() {
			$this->helper = Helper\Wkwctobag_Helper::get_instance();

			// WooCommerce standard tax classes + custom ones
			$this->tax_classes = array_merge( array( '' ), \WC_Tax::get_tax_classes() );

			// Fetch Bagisto tax categories if connected
			if ( $this->helper->is_connected_with_bagisto() ) {
				$url = $this->helper->get_bagisto_site_url() . '/api/admin/settings/tax-categories?per_page=500';
				try {
					$response = $this->helper->bagisto_curl_request( $url, 'GET' );
					$decoded  = json_decode( $response, true );
					if ( ! empty( $decoded['data'] ) ) {
						$this->bagisto_tax_categories = $decoded['data'];
					}
				} catch ( \Exception $e ) {
					// Silent ignore
				}
			}

			$nonce = isset( $_POST['wkwctobag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_nonce'] ) ) : '';

			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_save_tax_settings' ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$raw_tax_mapping       = isset( $_POST['tax_mapping'] ) && is_array( $_POST['tax_mapping'] ) ? (array) wp_unslash( $_POST['tax_mapping'] ) : array();
				$sanitized_tax_mapping = array();
				foreach ( $raw_tax_mapping as $wc_slug => $bag_id ) {
					$sanitized_tax_mapping[ sanitize_key( $wc_slug ) ] = intval( $bag_id );
				}

				$post_data = array(
					'auto_sync_taxes' => isset( $_POST['auto_sync_taxes'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_taxes'] ) ) : '',
					'tax_mapping'     => $sanitized_tax_mapping,
				);
				$this->save_settings( $post_data );
				$this->data = $this->helper->get_tax_settings_data();
			} else {
				$settings_data = $this->helper->get_tax_settings_data();
				if ( ! empty( $settings_data ) ) {
					$this->data = array_merge( $this->data, $settings_data );
				}
			}

			$this->get_tax_settings_template();
		}

		/**
		 * Get tax settings template.
		 */
		public function get_tax_settings_template() {
			?>
				<form method="POST" class="wkwctobag-settings-form">
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item wkwctobag-collapsible-item-open"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Tax Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure WooCommerce tax settings and map tax classes with Bagisto tax categories.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_taxes"><?php esc_html_e( 'Auto Sync Taxes', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync tax rates/classes with Bagisto when created or updated.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_taxes" name="auto_sync_taxes">
										<option value="enabled" <?php selected( $this->data['auto_sync_taxes'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_taxes'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>

					<?php if ( ! empty( $this->tax_classes ) ) : ?>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Map Tax Classes', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Map WooCommerce tax classes to Bagisto tax categories.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-tax-mapping-table-wrap" style="margin-top: 15px; width: 100%;">
									<table class="wp-list-table widefat fixed striped wkwctobag-mapping-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'WooCommerce Tax Class', 'bagisto-sync-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Bagisto Tax Category', 'bagisto-sync-for-woocommerce' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ( $this->tax_classes as $class_slug ) :
												$class_name = empty( $class_slug ) ? esc_html__( 'Standard Rate', 'bagisto-sync-for-woocommerce' ) : ucwords( str_replace( '-', ' ', $class_slug ) );
												$form_key   = empty( $class_slug ) ? 'standard' : $class_slug;
												$mapped_id  = $this->helper->get_bagisto_tax_category_id( $class_slug );
												if ( empty( $mapped_id ) ) {
													$mapped_id = -1;
												}
												?>
												<tr>
													<td>
														<?php echo esc_html( $class_name ); ?>
													</td>
													<td>
														<select id="tax_class_<?php echo esc_attr( sanitize_title( $form_key ) ); ?>" name="tax_mapping[<?php echo esc_attr( $form_key ); ?>]">
															<option value="-1"><?php esc_html_e( 'Select Bagisto Tax Category', 'bagisto-sync-for-woocommerce' ); ?></option>
															<?php foreach ( $this->bagisto_tax_categories as $bagisto_tax_cat ) : ?>
																<option value="<?php echo esc_attr( $bagisto_tax_cat['id'] ); ?>" <?php selected( $mapped_id, $bagisto_tax_cat['id'] ); ?>><?php echo esc_html( $bagisto_tax_cat['name'] ); ?></option>
															<?php endforeach; ?>
														</select>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<?php endif; ?>

					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<?php wp_nonce_field( 'wkwctobag_save_tax_settings', 'wkwctobag_nonce' ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Configurations', 'bagisto-sync-for-woocommerce' ); ?></button>
						</div>
					</div>
				</form>
			<?php
		}

		/**
		 * Save tax settings.
		 *
		 * @param array $post_data Post data array.
		 */
		public function save_settings( $post_data = array() ) {
			$data = array(
				'auto_sync_taxes' => ! empty( $post_data['auto_sync_taxes'] ) && 'enabled' === $post_data['auto_sync_taxes'] ? 'enabled' : 'disabled',
				'tax_mapping'     => array(),
			);

			if ( ! empty( $post_data['tax_mapping'] ) && is_array( $post_data['tax_mapping'] ) ) {
				foreach ( $post_data['tax_mapping'] as $wc_slug => $bagisto_id ) {
					$class_key = 'standard' === $wc_slug ? '' : $wc_slug;
					if ( intval( $bagisto_id ) > 0 ) {
						$this->helper->update_bagisto_tax_category_id( $class_key, $bagisto_id );
						$data['tax_mapping'][ $wc_slug ] = intval( $bagisto_id );
					}
				}
			}

			$this->helper->save_tax_settings( $data );
			$this->helper->add_notice( esc_html__( 'Tax settings saved successfully.', 'bagisto-sync-for-woocommerce' ) );
		}
	}
}
