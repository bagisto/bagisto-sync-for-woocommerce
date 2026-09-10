<?php
/**
 * User settings page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin\Settings\Tabs;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper\Sync\User;
use WKWCBAGCONNECTOR\Helper;


if ( ! class_exists( 'Wkwctobag_User_Settings' ) ) {
	/**
	 * User settings page template class
	 */
	class Wkwctobag_User_Settings {

		/**
		 * Helper class instance.
		 *
		 * @var Wkwctobag_Helper $helper Helper class instance.
		 */
		protected $helper = null;

		/**
		 * Bagisto user roles.
		 *
		 * @var array $bagisto_user_roles Bagisto user roles.
		 */
		protected $bagisto_user_roles = array();


		/**
		 * User settings data.
		 *
		 * @var array $data User settings data.
		 */
		protected $data = array(
			'auto_sync_user'         => 'enabled',
			'update_user'            => 'enabled',
			'wc_bagisto_user_role'   => -1,
			'wc_bagisto_user_status' => 'active',
		);

		/**
		 * Constructor of the user settings page.
		 */
		public function __construct() {
			$this->helper = Helper\Wkwctobag_Helper::get_instance();

			$nonce = isset( $_POST['wkwctobag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_nonce'] ) ) : '';

			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_save_settings' ) ) {
				$post_data = array(
					'auto_sync_user'         => isset( $_POST['auto_sync_user'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_sync_user'] ) ) : '',
					'update_user'            => isset( $_POST['update_user'] ) ? sanitize_text_field( wp_unslash( $_POST['update_user'] ) ) : '',
					'wc_bagisto_user_role'   => isset( $_POST['wc_bagisto_user_role'] ) ? intval( wp_unslash( $_POST['wc_bagisto_user_role'] ) ) : -1,
					'wc_bagisto_user_status' => isset( $_POST['wc_bagisto_user_status'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_bagisto_user_status'] ) ) : 'active',
				);
				$this->save_settings( $post_data );
				$this->data = array_merge( $this->data, $this->helper->get_user_settings_data() );
			} else {
				$settings_data = $this->helper->get_user_settings_data();

				if ( ! empty( $settings_data ) ) {
					$this->data = array_merge( $this->data, $settings_data );
				}
			}

			$user_sync  = new User\Wkwctobag_Sync_User();
			$user_roles = $user_sync->get_bagisto_user_roles();

			$this->bagisto_user_roles = ! empty( $user_roles['data'] ) ? $user_roles['data'] : array();

			$this->get_user_settings_template();
		}

		/**
		 * Get user settings template.
		 */
		public function get_user_settings_template() {
			?>
				<form method="POST" class="wkwctobag-settings-form">
					<div class="wrap">
						<div class="wkwctobag-bagisto-wrapper">
							<div>
								<div class="wkwctobag-collapsible-container">
									<div class="wkwctobag-collapsible-item wkwctobag-collapsible-item-open"></div>
								</div>
								<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'User Settings', 'bagisto-sync-for-woocommerce' ); ?></p>
								<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Configure your user synchronization settings for users.', 'bagisto-sync-for-woocommerce' ); ?></p>
								<hr />
							</div>
							<div class="wkwctobag-bagisto-content">
								<div class="wkwctobag-form-group">
									<label for="auto_sync_user"><?php esc_html_e( 'Auto Sync User', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically sync users with Bagisto whenever new user registered.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="auto_sync_user" name="auto_sync_user">
										<option value="enabled" <?php selected( $this->data['auto_sync_user'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['auto_sync_user'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="update_user"><?php esc_html_e( 'Auto Update User', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Automatically update users with Bagisto whenever user profile edited/updated.', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="update_user" name="update_user">
										<option value="enabled" <?php selected( $this->data['update_user'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="disabled" <?php selected( $this->data['update_user'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'bagisto-sync-for-woocommerce' ); ?></option>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wc_bagisto_user_role"><?php esc_html_e( 'Select Default Bagisto User Role', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Select default bagisto user role for new user and bulk sync users', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="wc_bagisto_user_role" name="wc_bagisto_user_role">
										<option value="-1" <?php selected( $this->data['wc_bagisto_user_role'], '-1' ); ?>><?php esc_html_e( '- - - Select a default user role - - -', 'bagisto-sync-for-woocommerce' ); ?></option>
										<?php
										foreach ( $this->bagisto_user_roles as $key => $role ) {
											?>
												<option value="<?php echo esc_attr( $role['id'] ); ?>" <?php selected( $this->data['wc_bagisto_user_role'], $role['id'] ); ?>><?php echo esc_html( $role['name'] ); ?></option>
												<?php
										}
										?>
									</select>
								</div>
								<div class="wkwctobag-form-group">
									<label for="wc_bagisto_user_status"><?php esc_html_e( 'Select Default Bagisto User Status', 'bagisto-sync-for-woocommerce' ); ?></label>
									<small class="wkwctobag-bagisto-description">  - - - <?php esc_html_e( 'Select default bagisto user status for new user and bulk sync users', 'bagisto-sync-for-woocommerce' ); ?> - - - </small>
									<select id="wc_bagisto_user_status" name="wc_bagisto_user_status">
										<option value="active" <?php selected( $this->data['wc_bagisto_user_status'], 'active' ); ?>><?php esc_html_e( 'Active', 'bagisto-sync-for-woocommerce' ); ?></option>
										<option value="inactive" <?php selected( $this->data['wc_bagisto_user_status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'bagisto-sync-for-woocommerce' ); ?></option>
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
				'auto_sync_user'         => ! empty( $post_data['auto_sync_user'] ) && 'enabled' === $post_data['auto_sync_user'] ? 'enabled' : 'disabled',
				'update_user'            => ! empty( $post_data['update_user'] ) && 'enabled' === $post_data['update_user'] ? 'enabled' : 'disabled',
				'wc_bagisto_user_role'   => ! empty( $post_data['wc_bagisto_user_role'] ) && 0 < intval( $post_data['wc_bagisto_user_role'] ) ? intval( $post_data['wc_bagisto_user_role'] ) : -1,
				'wc_bagisto_user_status' => ! empty( $post_data['wc_bagisto_user_status'] ) && 'active' === $post_data['wc_bagisto_user_status'] ? $post_data['wc_bagisto_user_status'] : 'inactive',
			);

			$this->helper->save_user_settings( $data );
			$this->helper->add_notice( esc_html__( 'User settings saved successfully.', 'bagisto-sync-for-woocommerce' ) );
		}
	}
}
