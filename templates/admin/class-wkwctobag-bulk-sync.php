<?php
/**
 * Bulk sync template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

if ( ! class_exists( 'Wkwctobag_Bulk_Sync' ) ) {
	/**
	 * Bulk sync template class.
	 */
	class Wkwctobag_Bulk_Sync {
		/**
		 * Constructor of the bulk sync page.
		 */
		public function __construct() {
			$this->get_bulk_sync_template();
		}

		/**
		 * Bulk sync template.
		 */
		public function get_bulk_sync_template() {
			?>
			<div>
				<form method="POST" action="" class="wkwctobag-bulk-sync-form">
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item wkwctobag-collapsible-item-open"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'User Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync only non-customer WooCommerce users to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_exclude_users"><?php esc_html_e( 'Exclude Users', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Exclude users you do not want to sync.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select multiple name="wkwctobag_exclude_users[]" id="wkwctobag_exclude_users" class="wkwctobag-exclude-users-select"></select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_users"><?php esc_html_e( 'Update Existing User', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update already existing user with same email in Bagisto.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_users" id="wkwctobag_update_existing_users">
										<option value="yes"><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-user-btn"><?php esc_html_e( 'Sync User Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="user"></div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Customer Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync only customer roles from WooCommerce to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_exclude_customers"><?php esc_html_e( 'Exclude Customers', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Exclude customers you do not want to sync.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select multiple name="wkwctobag_exclude_customers[]" id="wkwctobag_exclude_customers" class="wkwctobag-exclude-customers-select"></select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_customers"><?php esc_html_e( 'Update Existing Customer', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update already existing customer with same email in Bagisto.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_customers" id="wkwctobag_update_existing_customers">
										<option value="yes"><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-customer-btn"><?php esc_html_e( 'Sync Customer Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="customer"></div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Category Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync Categories from WooCommerce to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_exclude_categories"><?php esc_html_e( 'Exclude Categories ', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Exclude categories you do not want to sync.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select multiple name="wkwctobag_exclude_categories[]" id="wkwctobag_exclude_categories" class="wkwctobag-exclude-categories"></select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_categories"><?php esc_html_e( 'Update Existing Categories', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update existing categories with already synced category in Bagisto.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_categories" id="wkwctobag_update_existing_categories">
										<option value="yes"><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-category-btn"><?php esc_html_e( 'Sync Category Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="category"></div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Attribute Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync Attributes from WooCommerce to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_attributes"><?php esc_html_e( 'Update Existing Attributes', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update existing attributes with already synced attribute in Bagisto.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_attributes" id="wkwctobag_update_existing_attributes">
										<option value="yes"><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-attribute-btn"><?php esc_html_e( 'Sync Attribute Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="attribute"></div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Product Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync Products from WooCommerce to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_exclude_products"><?php esc_html_e( 'Exclude Products ', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Exclude products you do not want to sync.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select multiple name="wkwctobag_exclude_products[]" id="wkwctobag_exclude_products" class="wkwctobag-exclude-products"></select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_products"><?php esc_html_e( 'Update Existing Products', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update existing products with already synced product in Bagisto or SKU already available.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_products" id="wkwctobag_update_existing_products">
										<option value="yes"><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-product-btn"><?php esc_html_e( 'Sync Product Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="product"></div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Taxes Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync tax classes and rates from WooCommerce to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_taxes"><?php esc_html_e( 'Update Existing Taxes', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update already existing tax rate with same identifier in Bagisto.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_taxes" id="wkwctobag_update_existing_taxes">
										<option value="yes" selected><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-tax-btn"><?php esc_html_e( 'Sync Taxes Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="tax"></div>
							</div>
						</div>
					</div>
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div class="wkwctobag-loader">
								<div class="wkwctobag-spinner"></div>
							</div>
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Order Sync', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Sync orders from WooCommerce to Bagisto.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_exclude_order_status"><?php esc_html_e( 'Exclude Order Status', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Exclude order status you do not want to sync.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select multiple name="wkwctobag_exclude_order_status[]" id="wkwctobag_exclude_order_status" class="wkwctobag-exclude-order-status">
									<?php foreach ( wc_get_order_statuses() as $status_slug => $status_name ) : ?>
											<option value="<?php echo esc_attr( $status_slug ); ?>"><?php echo esc_html( $status_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wkwctobag_update_existing_order"><?php esc_html_e( 'Update Existing Orders', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Update existing orders with already synced order in Bagisto.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select name="wkwctobag_update_existing_orders" id="wkwctobag_update_existing_orders">
										<option value="yes"><?php esc_html_e( 'Yes', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="no"><?php esc_html_e( 'No', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<button type="button" class="button button-primary wkwcbagcon-sync-order-btn"><?php esc_html_e( 'Sync Order Now', 'bagisto-sync-for-woocommerce' ); ?></button>
								</div>
								<div class="wkwctobag-sync-status" data-sync-type="order"></div>
							</div>
						</div>
					</div>
				</form>
			</div>
			<?php
		}
	}
}
