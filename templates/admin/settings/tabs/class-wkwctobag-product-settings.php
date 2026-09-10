<?php
/**
 * Product settings page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin\Settings\Tabs;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper;
use WKWCBAGCONNECTOR\Helper\Sync\Category;

if ( ! class_exists( 'Wkwctobag_Product_Settings' ) ) {
	/**
	 * Product settings page template class
	 */
	class Wkwctobag_Product_Settings {

		/**
		 * Helper class instance.
		 *
		 * @var Wkwctobag_Helper $helper Helper class instance.
		 */
		protected $helper = null;

		/**
		 * Categories data.
		 *
		 * @var array $categories Categories data.
		 */
		protected $categories = array();

		/**
		 * Bagisto categories data.
		 *
		 * @var array $bagisto_categories Bagisto categories data.
		 */
		protected $bagisto_categories = array();

		/**
		 * Settings data.
		 */
		protected $data = array(
			'auto_sync_products'   => 'enabled',
			'update_product'       => 'enabled',
			'auto_sync_categories' => 'enabled',
			'update_categories'    => 'enabled',
			'auto_sync_attributes' => 'enabled',
			'update_attributes'    => 'enabled',
			'category_mapping'     => array(),
		);


		/**
		 * Constructor of the product settings page.
		 */
		public function __construct() {
			$this->helper  = Helper\Wkwctobag_Helper::get_instance();
			$category_sync = new Category\Wkwctobag_Sync_Category();

			$this->categories   = $this->helper->get_wc_categories();
			$bagisto_categories = $category_sync->get_bagisto_product_category();

			$this->bagisto_categories = ! empty( $bagisto_categories['data'] ) ? $bagisto_categories['data'] : array();

			$nonce = isset( $_POST['wkwctobag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_nonce'] ) ) : '';

			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_save_settings' ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$raw_category_mapping       = isset( $_POST['category_mapping'] ) && is_array( $_POST['category_mapping'] ) ? (array) wp_unslash( $_POST['category_mapping'] ) : array();
				$sanitized_category_mapping = array();
				foreach ( $raw_category_mapping as $wc_id => $bag_id ) {
					$sanitized_category_mapping[ absint( $wc_id ) ] = absint( $bag_id );
				}

				$post_data = array(
					'auto_sync_products'   => isset( $_POST['auto_sync_products'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_products'] ) ) : '',
					'auto_sync_categories' => isset( $_POST['auto_sync_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_categories'] ) ) : '',
					'update_product'       => isset( $_POST['update_product'] ) ? sanitize_text_field( wp_unslash( $_POST['update_product'] ) ) : '',
					'update_categories'    => isset( $_POST['update_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['update_categories'] ) ) : '',
					'auto_sync_attributes' => isset( $_POST['auto_sync_attributes'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_attributes'] ) ) : '',
					'update_attributes'    => isset( $_POST['update_attributes'] ) ? sanitize_text_field( wp_unslash( $_POST['update_attributes'] ) ) : '',
					'category_mapping'     => $sanitized_category_mapping,
				);
				$this->save_settings( $post_data );
				$this->data = array_merge( $this->data, $this->helper->get_product_settings_data() );
			} else {
				$settings_data = $this->helper->get_product_settings_data();

				if ( ! empty( $settings_data ) ) {
					$this->data = array_merge( $this->data, $settings_data );
				}
			}

			$this->get_product_settings_template();
		}

		/**
		 * Get product settings template.
		 */
		public function get_product_settings_template() {
			?>
				<form method="POST" class="wkwctobag-settings-form">
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item wkwctobag-collapsible-item-open"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Products Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure your product synchronization settings for products.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Note: Categories and attributes will also be mapped with Bagisto whenever products are synced.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_products"><?php esc_html_e( 'Auto Sync Products', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync products with Bagisto whenever new product added.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_products" name="auto_sync_products">
										<option value="enabled" <?php selected( $this->data['auto_sync_products'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_products'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="update_product"><?php esc_html_e( 'Update Product', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically update products with Bagisto whenever anything changes in the product.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="update_product" name="update_product">
										<option value="enabled" <?php selected( $this->data['update_product'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['update_product'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Category Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure your category synchronization settings.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_categories"><?php esc_html_e( 'Auto Sync Categories', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync categories with Bagisto whenever new category added.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_categories" name="auto_sync_categories">
										<option value="enabled" <?php selected( $this->data['auto_sync_categories'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_categories'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="update_categories"><?php esc_html_e( 'Update Categories', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically update categories with Bagisto whenever anything changes in the category.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="update_categories" name="update_categories">
										<option value="enabled" <?php selected( $this->data['update_categories'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['update_categories'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>

					<?php if ( ! empty( $this->categories ) ) : ?>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Map Category', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Map your category with bagisto category.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-category-mapping-table-wrap" style="margin-top: 15px; width: 100%;">
									<table class="wp-list-table widefat fixed striped wkwctobag-mapping-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'WooCommerce Category', 'bagisto-sync-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Bagisto Category', 'bagisto-sync-for-woocommerce' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ( $this->categories as $category ) :
												$mapped_id = isset( $this->data['category_mapping'][ $category['id'] ] ) ? $this->data['category_mapping'][ $category['id'] ] : '';
												if ( empty( $mapped_id ) || -1 === intval( $mapped_id ) ) {
													$mapped_id = $this->helper->get_bagisto_category_id( $category['id'] );
												}
												?>
												<tr>
													<td>
														<?php echo esc_html( $category['name'] ); ?>
													</td>
													<td>
														<select id="category_<?php echo esc_attr( $category['id'] ); ?>" name="category_mapping[<?php echo esc_attr( $category['id'] ); ?>]">
															<option value="-1"><?php esc_html_e( 'Select Bagisto Category', 'bagisto-sync-for-woocommerce' ); ?></option>
															<?php foreach ( $this->bagisto_categories as $bagisto_category ) : ?>
																<option value="<?php echo esc_attr( $bagisto_category['id'] ); ?>" <?php selected( $mapped_id, $bagisto_category['id'] ); ?>><?php echo esc_html( $bagisto_category['name'] ); ?></option>
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
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Attribute Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure your attribute synchronization settings.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_attributes"><?php esc_html_e( 'Auto Sync Attributes', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync attributes with Bagisto whenever new attribute added.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_attributes" name="auto_sync_attributes">
										<option value="enabled" <?php selected( $this->data['auto_sync_attributes'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_attributes'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="update_attributes"><?php esc_html_e( 'Update Attributes', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically update attributes with Bagisto whenever anything changes in the attribute.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="update_attributes" name="update_attributes">
										<option value="enabled" <?php selected( $this->data['update_attributes'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['update_attributes'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<?php wp_nonce_field( 'wkwctobag_save_settings', 'wkwctobag_nonce' ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Configurations', 'bagisto-sync-for-woocommerce' ); ?></button>
						</div>
					</div>
				</form>
			<?php
		}

		/**
		 * Save product settings.
		 *
		 * @param array $post_data Post data array.
		 */
		public function save_settings( $post_data = array() ) {
			$data = array(
				'auto_sync_products'   => ! empty( $post_data['auto_sync_products'] ) && 'enabled' === $post_data['auto_sync_products'] ? 'enabled' : 'disabled',
				'auto_sync_categories' => ! empty( $post_data['auto_sync_categories'] ) && 'enabled' === $post_data['auto_sync_categories'] ? 'enabled' : 'disabled',
				'update_product'       => ! empty( $post_data['update_product'] ) && 'enabled' === $post_data['update_product'] ? 'enabled' : 'disabled',
				'update_categories'    => ! empty( $post_data['update_categories'] ) && 'enabled' === $post_data['update_categories'] ? 'enabled' : 'disabled',
				'auto_sync_attributes' => ! empty( $post_data['auto_sync_attributes'] ) && 'enabled' === $post_data['auto_sync_attributes'] ? 'enabled' : 'disabled',
				'update_attributes'    => ! empty( $post_data['update_attributes'] ) && 'enabled' === $post_data['update_attributes'] ? 'enabled' : 'disabled',
				'category_mapping'     => ! empty( $post_data['category_mapping'] ) && is_array( $post_data['category_mapping'] ) ? $post_data['category_mapping'] : array(),
			);

			if ( ! empty( $data['category_mapping'] ) ) {
				foreach ( $data['category_mapping'] as $wc_id => $bagisto_id ) {
					if ( intval( $bagisto_id ) > 0 ) {
						$this->helper->update_bagisto_category_id( $wc_id, $bagisto_id );
					}
				}
			}

			$this->helper->save_product_settings( $data );
			$this->helper->add_notice( esc_html__( 'Product settings saved successfully.', 'bagisto-sync-for-woocommerce' ) );
		}
	}
}
