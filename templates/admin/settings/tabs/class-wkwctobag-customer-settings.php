<?php
/**
 * Customer settings page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin\Settings\Tabs;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper\Sync\Customer;
use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Customer_Settings' ) ) {
	/**
	 * Customer settings page template class
	 */
	class Wkwctobag_Customer_Settings {

		/**
		 * Helper class instance.
		 *
		 * @var Wkwctobag_Helper $helper Helper class instance.
		 */
		protected $helper = null;

		/**
		 * Bagisto customer groups.
		 *
		 * @var array $bagisto_customer_groups Bagisto customer groups.
		 */
		protected $bagisto_customer_groups = array();


		/**
		 * Settings data.
		 *
		 * @var array $data Settings data.
		 */
		protected $data = array(
			'auto_sync_customer'                 => 'enabled',
			'update_customer'                    => 'enabled',
			'wc_bagisto_customer_group'          => -1,
			'wc_bagisto_customer_default_gender' => 'male',
			'wc_bagisto_customer_status'         => 'active',
		);

		/**
		 * Constructor of the customer settings page.
		 */
		public function __construct() {
			$this->helper = Helper\Wkwctobag_Helper::get_instance();

			$nonce = isset( $_POST['wkwctobag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_nonce'] ) ) : '';

			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_save_settings' ) ) {
				$post_data = array(
					'auto_sync_customer'                 => isset( $_POST['auto_sync_customer'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_customer'] ) ) : '',
					'update_customer'                    => isset( $_POST['update_customer'] ) ? sanitize_text_field( wp_unslash( $_POST['update_customer'] ) ) : '',
					'wc_bagisto_customer_group'          => isset( $_POST['wc_bagisto_customer_group'] ) ? intval( wp_unslash( $_POST['wc_bagisto_customer_group'] ) ) : -1,
					'wc_bagisto_customer_default_gender' => isset( $_POST['wc_bagisto_customer_default_gender'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_bagisto_customer_default_gender'] ) ) : 'male',
					'wc_bagisto_customer_status'         => isset( $_POST['wc_bagisto_customer_status'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_bagisto_customer_status'] ) ) : 'active',
				);
				$this->save_settings( $post_data );
				$this->data = array_merge( $this->data, $this->helper->get_customer_settings_data() );
			} else {
				$settings_data = $this->helper->get_customer_settings_data();

				if ( ! empty( $settings_data ) ) {
					$this->data = array_merge( $this->data, $settings_data );
				}
			}

			$customer_sync                 = new Customer\Wkwctobag_Sync_Customer();
			$cusotmer_groups               = $customer_sync->get_bagisto_customer_groups();
			$this->bagisto_customer_groups = ! empty( $cusotmer_groups['data'] ) ? $cusotmer_groups['data'] : array();

			$this->get_customer_settings_template();
		}

		/**
		 * Get customer settings template.
		 */
		public function get_customer_settings_template() {
			?>
				<form method="POST" class="wkwctobag-settings-form">
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item wkwctobag-collapsible-item-open"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Customer Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure your customer synchronization settings for customers.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_customer"><?php esc_html_e( 'Auto Sync Customer', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync customer with Bagisto whenever new customer role registered.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_customer" name="auto_sync_customer">
										<option value="enabled" <?php selected( $this->data['auto_sync_customer'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_customer'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="update_customer"><?php esc_html_e( 'Auto Update Customer', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically update customers with Bagisto whenever customer profile edited/updated.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="update_customer" name="update_customer">
										<option value="enabled" <?php selected( $this->data['update_customer'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['update_customer'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wc_bagisto_customer_group"><?php esc_html_e( 'Select Default Bagisto Customer Group', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Select default bagisto customer group for new customers and bulk sync customers', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="wc_bagisto_customer_group" name="wc_bagisto_customer_group">
										<option value="-1" <?php selected( $this->data['wc_bagisto_customer_group'], '-1' ); ?>><?php esc_html_e( '- - - Select a default customer group - - -', 'bagisto-sync-for-woocommerce' ); ?></option>
										<?php
										foreach ( $this->bagisto_customer_groups as $key => $group ) {
											?>
												<option value="<?php echo esc_attr( $group['id'] ); ?>" <?php selected( $this->data['wc_bagisto_customer_group'], $group['id'] ); ?>><?php echo esc_html( $group['name'] ); ?></option>
												<?php
										}
										?>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wc_bagisto_customer_default_gender"><?php esc_html_e( 'Select Default Bagisto Customer Gender', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Select default bagisto customer gender for customers', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="wc_bagisto_customer_default_gender" name="wc_bagisto_customer_default_gender">
										<option value="male" <?php selected( $this->data['wc_bagisto_customer_default_gender'], 'male' ); ?>><?php esc_html_e( 'Male', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="female" <?php selected( $this->data['wc_bagisto_customer_default_gender'], 'female' ); ?>><?php esc_html_e( 'Female', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="other" <?php selected( $this->data['wc_bagisto_customer_default_gender'], 'other' ); ?>><?php esc_html_e( 'Other', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wc_bagisto_customer_status"><?php esc_html_e( 'Select Default Bagisto Customer Status', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Select default bagisto customer status for new customers and bulk sync customers', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="wc_bagisto_customer_status" name="wc_bagisto_customer_status">
										<option value="active" <?php selected( isset( $this->data['wc_bagisto_customer_status'] ) ? $this->data['wc_bagisto_customer_status'] : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="inactive" <?php selected( isset( $this->data['wc_bagisto_customer_status'] ) ? $this->data['wc_bagisto_customer_status'] : 'active', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'bagisto-sync-for-woocommerce' ); ?></option>
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
		 * Save settings.
		 *
		 * @param array $post_data Post data array.
		 */
		public function save_settings( $post_data = array() ) {
			$data = array(
				'auto_sync_customer'                 => ! empty( $post_data['auto_sync_customer'] ) && 'enabled' === $post_data['auto_sync_customer'] ? 'enabled' : 'disabled',
				'update_customer'                    => ! empty( $post_data['update_customer'] ) && 'enabled' === $post_data['update_customer'] ? 'enabled' : 'disabled',
				'wc_bagisto_customer_group'          => ! empty( $post_data['wc_bagisto_customer_group'] ) && 0 < intval( $post_data['wc_bagisto_customer_group'] ) ? intval( $post_data['wc_bagisto_customer_group'] ) : -1,
				'wc_bagisto_customer_default_gender' => ! empty( $post_data['wc_bagisto_customer_default_gender'] ) ? sanitize_text_field( $post_data['wc_bagisto_customer_default_gender'] ) : 'male',
				'wc_bagisto_customer_status'         => ! empty( $post_data['wc_bagisto_customer_status'] ) && 'active' === $post_data['wc_bagisto_customer_status'] ? $post_data['wc_bagisto_customer_status'] : 'inactive',
			);
			$this->helper->save_customer_settings( $data );
			$this->helper->add_notice( esc_html__( 'Customer settings saved successfully.', 'bagisto-sync-for-woocommerce' ) );
		}
	}
}
