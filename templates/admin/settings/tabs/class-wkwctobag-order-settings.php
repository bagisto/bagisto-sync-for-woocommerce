<?php
/**
 * Order settings page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin\Settings\Tabs;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper;


if ( ! class_exists( 'Wkwctobag_Order_Settings' ) ) {
	/**
	 * Order settings page template class
	 */
	class Wkwctobag_Order_Settings {
		/**
		 * Settings data.
		 *
		 * @var array $data Settings data.
		 */
		protected $data = array(
			'auto_sync_order'              => 'enabled',
			'update_order'                 => 'enabled',
			'allowed_order_status'         => array(),
			'wc_bagisto_guest_customer_id' => '',
			'order_status_mappings'        => array(),
		);

		/**
		 * Helper class instance.
		 *
		 * @var Wkwctobag_Helper $helper Helper class instance.
		 */
		protected $helper = null;

		/**
		 * Order statuses.
		 *
		 * @var array $order_statuses Order statuses.
		 */
		protected $order_statuses = array();


		/**
		 * Constructor of the order settings page.
		 */
		public function __construct() {
			$this->helper         = Helper\Wkwctobag_Helper::get_instance();
			$this->order_statuses = wc_get_order_statuses();

			$nonce = isset( $_POST['wkwctobag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_nonce'] ) ) : '';

			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_save_settings' ) ) {
				$post_data = array(
					'auto_sync_order'              => isset( $_POST['auto_sync_order'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_order'] ) ) : '',
					'update_order'                 => isset( $_POST['update_order'] ) ? sanitize_text_field( wp_unslash( $_POST['update_order'] ) ) : '',
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'allowed_order_status'         => isset( $_POST['allowed_order_status'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['allowed_order_status'] ) ) : array(),
					'wc_bagisto_guest_customer_id' => isset( $_POST['wc_bagisto_guest_customer_id'] ) ? intval( wp_unslash( $_POST['wc_bagisto_guest_customer_id'] ) ) : '',
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'order_status_mappings'        => isset( $_POST['order_status_mappings'] ) ? (array) wp_unslash( $_POST['order_status_mappings'] ) : array(),
				);
				$this->save_settings( $post_data );
				$this->data = array_merge( $this->data, $this->helper->get_order_settings_data() );
			} else {
				$settings_data = $this->helper->get_order_settings_data();

				if ( ! empty( $settings_data ) ) {
					$this->data = array_merge( $this->data, $settings_data );
				} else {
					$this->data['allowed_order_status'] = array_keys( $this->order_statuses );
				}
			}

			if ( empty( $this->data['order_status_mappings'] ) ) {
				$this->data['order_status_mappings'] = array(
					'wc-pending'    => 'pending',
					'wc-processing' => 'invoice',
					'wc-on-hold'    => 'pending',
					'wc-completed'  => 'shipment',
					'wc-cancelled'  => 'cancel',
					'wc-refunded'   => 'refund',
					'wc-failed'     => 'pending',
				);
			}

			$this->get_order_settings_template();
		}

		/**
		 * Get order settings template.
		 */
		public function get_order_settings_template() {
			?>
				<form method="POST" class="wkwctobag-settings-form">
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item wkwctobag-collapsible-item-open"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Order Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure your order synchronization settings for orders.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_order"><?php esc_html_e( 'Auto Sync Order', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync orders with Bagisto whenever new order placed.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_order" name="auto_sync_order">
										<option value="enabled" <?php selected( $this->data['auto_sync_order'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_order'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="update_order"><?php esc_html_e( 'Update Order', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically update orders with Bagisto whenever order status changes.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="update_order" name="update_order">
										<option value="enabled" <?php selected( $this->data['update_order'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['update_order'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="allowed_order_status"><?php esc_html_e( 'Allowed Order Status', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync orders with Bagisto whenever new order placed.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select multiple id="allowed_order_status" name="allowed_order_status[]">
									<?php foreach ( $this->order_statuses as $status_slug => $status_name ) : ?>
											<option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( in_array( $status_slug, $this->data['allowed_order_status'], true ), true ); ?>><?php echo esc_html( $status_name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wc_bagisto_guest_customer_id"><?php esc_html_e( 'Bagisto Customer for Guest Orders', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Select a Bagisto customer for guest orders. Leave blank to auto-create guests.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="wc_bagisto_guest_customer_id" class="wkwctobag-bagisto-customers-select" name="wc_bagisto_guest_customer_id">
										<option value=""><?php esc_html_e( '- - - Search and Select a Bagisto Customer - - -', 'bagisto-sync-for-woocommerce' ); ?></option>
										<?php
										$saved_guest_customer_id = ! empty( $this->data['wc_bagisto_guest_customer_id'] ) ? intval( $this->data['wc_bagisto_guest_customer_id'] ) : 0;
										if ( $saved_guest_customer_id > 0 ) {
											// Get customer details from Bagisto to pre-populate the label.
											$customer_name = esc_html__( 'Customer', 'bagisto-sync-for-woocommerce' ) . ' #' . $saved_guest_customer_id;
											try {
												$url       = $this->helper->get_bagisto_site_url() . '/api/admin/customers/' . $saved_guest_customer_id;
												$cust_res  = json_decode( $this->helper->bagisto_curl_request( $url, 'GET' ), true );
												$cust_data = ! empty( $cust_res['data'] ) ? $cust_res['data'] : $cust_res;
												if ( ! empty( $cust_data ) && ! empty( $cust_data['id'] ) ) {
													$name = '';
													if ( ! empty( $cust_data['name'] ) ) {
														$name = $cust_data['name'];
													} else {
														$first_name = ! empty( $cust_data['first_name'] ) ? $cust_data['first_name'] : ( ! empty( $cust_data['firstName'] ) ? $cust_data['firstName'] : '' );
														$last_name  = ! empty( $cust_data['last_name'] ) ? $cust_data['last_name'] : ( ! empty( $cust_data['lastName'] ) ? $cust_data['lastName'] : '' );
														$name       = trim( $first_name . ' ' . $last_name );
													}
													$email         = ! empty( $cust_data['email'] ) ? $cust_data['email'] : '';
													$customer_name = sprintf( '%s ( %s )', $name, $email );
												}
											} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The label falls back to the raw ID rendered below.
												// Fallback to ID label if API call fails.
											}
											?>
											<option value="<?php echo esc_attr( $saved_guest_customer_id ); ?>" selected="selected"><?php echo esc_html( $customer_name ); ?></option>
											<?php
										}
										?>
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
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'Order Status Mappings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Map WooCommerce order statuses to Bagisto transition actions.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-status-mapping-table-wrap" style="margin-top: 15px; width: 100%;">
									<table class="wp-list-table widefat fixed striped wkwctobag-mapping-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'WooCommerce Status', 'bagisto-sync-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Bagisto Action', 'bagisto-sync-for-woocommerce' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											$actions        = array(
												'pending'  => esc_html__( 'Pending', 'bagisto-sync-for-woocommerce' ),
												'invoice'  => esc_html__( 'Processing (Create Invoice)', 'bagisto-sync-for-woocommerce' ),
												'shipment' => esc_html__( 'Complete (Create Invoice & Shipment)', 'bagisto-sync-for-woocommerce' ),
												'cancel'   => esc_html__( 'Cancel Order', 'bagisto-sync-for-woocommerce' ),
												'refund'   => esc_html__( 'Closed (Refund)', 'bagisto-sync-for-woocommerce' ),
											);
											$saved_mappings = isset( $this->data['order_status_mappings'] ) ? $this->data['order_status_mappings'] : array();
											foreach ( $this->order_statuses as $status_slug => $status_name ) :
												$current_action = isset( $saved_mappings[ $status_slug ] ) ? $saved_mappings[ $status_slug ] : 'pending';
												?>
												<tr>
													<td>
														<?php echo esc_html( $status_name ); ?>
													</td>
													<td>
														<select name="order_status_mappings[<?php echo esc_attr( $status_slug ); ?>]">
															<?php foreach ( $actions as $action_key => $action_label ) : ?>
																<option value="<?php echo esc_attr( $action_key ); ?>" <?php selected( $current_action, $action_key ); ?>><?php echo esc_html( $action_label ); ?></option>
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
		 * Save order settings.
		 *
		 * @param array $post_data Post data array.
		 */
		public function save_settings( $post_data = array() ) {
			$raw_mappings       = ! empty( $post_data['order_status_mappings'] ) ? (array) $post_data['order_status_mappings'] : array();
			$sanitized_mappings = array();
			foreach ( $raw_mappings as $status => $action ) {
				$sanitized_mappings[ sanitize_key( $status ) ] = sanitize_text_field( $action );
			}

			$data = array(
				'auto_sync_order'              => ! empty( $post_data['auto_sync_order'] ) && 'enabled' === $post_data['auto_sync_order'] ? 'enabled' : 'disabled',
				'update_order'                 => ! empty( $post_data['update_order'] ) && 'enabled' === $post_data['update_order'] ? 'enabled' : 'disabled',
				'allowed_order_status'         => ! empty( $post_data['allowed_order_status'] ) ? array_map( 'sanitize_text_field', (array) $post_data['allowed_order_status'] ) : array(),
				'wc_bagisto_guest_customer_id' => ! empty( $post_data['wc_bagisto_guest_customer_id'] ) ? intval( $post_data['wc_bagisto_guest_customer_id'] ) : '',
				'order_status_mappings'        => $sanitized_mappings,
			);

			$this->helper->save_order_settings( $data );
			$this->helper->add_notice( esc_html__( 'Order settings saved successfully.', 'bagisto-sync-for-woocommerce' ) );
		}
	}
}
