<?php
/**
 * Create connection class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Connection' ) ) {
	/**
	 * Create connection class.
	 */
	class Wkwctobag_Connection {
		/**
		 * Helper class instance.
		 *
		 * @var Helper\Wkwctobag_Helper $helper Helper class instance.
		 */
		private $helper = null;

		/**
		 * Connection data.
		 *
		 * @var array $data Connection data.
		 */
		public $data = array();

		/**
		 * Constructor of this class.
		 */
		public function __construct() {
			$this->helper = Helper\Wkwctobag_Helper::get_instance();
			$nonce        = isset( $_POST['wkwctobag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_nonce'] ) ) : '';

			if ( isset( $_POST['connect'] ) && ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_connect' ) ) {
				$site_url    = isset( $_POST['site_url'] ) ? sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) : '';
				$admin_token = isset( $_POST['admin_token'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_token'] ) ) : '';
				$this->connect( $site_url, $admin_token );
			} elseif ( isset( $_POST['remove_connection'] ) && ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wkwctobag_connect' ) ) {
				$this->helper->update_bagisto_admin_token( null );
				$this->helper->update_bagisto_site_url( null );
				$this->helper->update_bagisto_admin_email( null );
				$this->helper->clear_bagisto_caches();
				delete_option( 'wkwctobag_token_expired' );

				$this->data = array(
					'site_url'    => '',
					'admin_token' => '',
				);
				$this->helper->add_notice( esc_html__( 'Connection removed successfully.', 'bagisto-sync-for-woocommerce' ), 'success' );
			} else {
				$this->data = array(
					'site_url'    => $this->helper->get_bagisto_site_url(),
					'admin_token' => $this->helper->is_connected_with_bagisto() ? '**************' : '',
				);
			}

			$this->get_create_connection_template();
		}

		/**
		 * Get create connection template.
		 */
		public function get_create_connection_template() {
			$is_connected = $this->helper->is_connected_with_bagisto();
			?>
			<div class="wrap wkwctobag-connection-wrap">
				<div class="wkwctobag-ui-card">
					<div class="wkwctobag-card-header-logos">
						<div class="wkwctobag-logo woo-logo" style="display: flex; align-items: center; justify-content: center; height: 45px;">
							<img src="<?php echo esc_url( WKWCTOBAG_PLUGIN_URL . 'assets/images/woo-logo.png' ); ?>" alt="Woo" style="max-width: 110px; max-height: 40px; object-fit: contain;" />
						</div>
						<div class="wkwctobag-connection-line" style="margin-top: 5px;">
							<span class="wkwctobag-line"></span>
							<span class="wkwctobag-check-shield"><span class="dashicons dashicons-yes-alt"></span></span>
							<span class="wkwctobag-line"></span>
						</div>
						<div class="wkwctobag-logo bagisto-logo" style="display: flex; align-items: center; justify-content: center; height: 45px;">
							<img src="<?php echo esc_url( WKWCTOBAG_PLUGIN_URL . 'assets/images/bagisto-logo.png' ); ?>" alt="Bagisto" style="max-width: 150px; max-height: 40px; object-fit: contain;" />
						</div>
					</div>

					<div class="wkwctobag-card-title-section">
						<h2><?php esc_html_e( 'Connect WooCommerce with Bagisto', 'bagisto-sync-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Enter your Bagisto store URL and API Personal Access Token to establish a secure connection.', 'bagisto-sync-for-woocommerce' ); ?></p>
					</div>

					<?php if ( ! $is_connected ) : ?>
						<form method="POST" class="wkwctobag-ui-form">
							<div class="wkwctobag-input-group">
								<label for="site_url"><?php esc_html_e( 'Bagisto Store URL', 'bagisto-sync-for-woocommerce' ); ?> <span class="dashicons dashicons-info-outline" title="<?php esc_attr_e( 'The URL to your Bagisto store directory.', 'bagisto-sync-for-woocommerce' ); ?>"></span></label>
								<div class="wkwctobag-input-wrapper">
									<span class="wkwctobag-input-icon dashicons dashicons-admin-site-alt3"></span>
									<input type="url" id="site_url" name="site_url" placeholder="<?php esc_attr_e( 'https://yourbagisto.com/', 'bagisto-sync-for-woocommerce' ); ?>" required value="<?php echo esc_attr( $this->data['site_url'] ); ?>" />
								</div>
								<small class="wkwctobag-input-help"><?php esc_html_e( 'Example: https://yourbagisto.com/', 'bagisto-sync-for-woocommerce' ); ?></small>
							</div>

							<div class="wkwctobag-input-group">
								<label for="admin_token"><?php esc_html_e( 'Bagisto API Token (Personal Access Token)', 'bagisto-sync-for-woocommerce' ); ?> <span class="dashicons dashicons-info-outline" title="<?php esc_attr_e( 'Generate this token in your Bagisto Admin Panel under My Account -> Personal Access Tokens.', 'bagisto-sync-for-woocommerce' ); ?>"></span></label>
								<div class="wkwctobag-input-wrapper">
									<span class="wkwctobag-input-icon dashicons dashicons-lock"></span>
									<input type="password" id="admin_token" name="admin_token" placeholder="<?php esc_attr_e( 'Enter Bagisto API Token', 'bagisto-sync-for-woocommerce' ); ?>" required value="<?php echo esc_attr( $this->data['admin_token'] ); ?>" />
									<button type="button" class="wkwctobag-toggle-password" id="wkwctobag-toggle-password-btn"><span class="dashicons dashicons-hidden"></span></button>
								</div>
							</div>

							<div class="wkwctobag-form-actions">
								<button type="button" class="button wkwctobag-btn-outline wkwctobag-test-connection-btn">
									<span class="dashicons dashicons-rss"></span> <?php esc_html_e( 'Test Connection', 'bagisto-sync-for-woocommerce' ); ?>
								</button>

								<div class="wkwctobag-action-right">
									<button type="submit" name="connect" class="button wkwctobag-btn-primary">
										<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Connect Store', 'bagisto-sync-for-woocommerce' ); ?>
									</button>
								</div>
							</div>
							<?php wp_nonce_field( 'wkwctobag_connect', 'wkwctobag_nonce' ); ?>
						</form>
					<?php else : ?>
						<form method="POST" class="wkwctobag-ui-form">
							<div class="wkwctobag-input-group">
								<label><?php esc_html_e( 'Bagisto Store URL', 'bagisto-sync-for-woocommerce' ); ?> <span class="dashicons dashicons-info-outline" title="<?php esc_attr_e( 'The URL to your Bagisto store directory.', 'bagisto-sync-for-woocommerce' ); ?>"></span></label>
								<div class="wkwctobag-input-wrapper">
									<span class="wkwctobag-input-icon dashicons dashicons-admin-site-alt3"></span>
									<input type="url" id="site_url" readonly style="opacity: 0.7; background: #f9fafb;" value="<?php echo esc_attr( $this->data['site_url'] ); ?>" />
								</div>
							</div>

							<div class="wkwctobag-input-group">
								<label><?php esc_html_e( 'Connected Admin Email Address', 'bagisto-sync-for-woocommerce' ); ?> <span class="dashicons dashicons-info-outline" title="<?php esc_attr_e( 'Connected Admin Email Address from Bagisto', 'bagisto-sync-for-woocommerce' ); ?>"></span></label>
								<div class="wkwctobag-input-wrapper">
									<span class="wkwctobag-input-icon dashicons dashicons-email"></span>
									<input type="text" id="admin_username" readonly style="opacity: 0.7; background: #f9fafb;" value="<?php echo esc_attr( $this->helper->get_bagisto_admin_email() ); ?>" />
								</div>
							</div>

							<div class="wkwctobag-input-group" style="margin-top: 15px;">
								<label><?php esc_html_e( 'Connection Status', 'bagisto-sync-for-woocommerce' ); ?></label>
								<div class="wkwctobag-connection-status wkwctobag-status-checking" id="wkwctobag-live-status">
									<span class="dashicons dashicons-update wkwctobag-spin"></span>
									<?php esc_html_e( 'Checking...', 'bagisto-sync-for-woocommerce' ); ?>
								</div>
							</div>

							<div class="wkwctobag-form-actions">
								<button type="button" class="button wkwctobag-btn-outline wkwctobag-test-connection-btn">
									<span class="dashicons dashicons-rss"></span> <?php esc_html_e( 'Test Connection', 'bagisto-sync-for-woocommerce' ); ?>
								</button>

								<div class="wkwctobag-action-right">
									<button type="button" id="wkwctobag-update-connection-btn" class="button wkwctobag-btn-primary">
										<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Update Connection', 'bagisto-sync-for-woocommerce' ); ?>
									</button>
									<button type="submit" name="remove_connection" class="button wkwctobag-btn-danger wkwctobag-btn-outline" formnovalidate>
										<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Disconnect', 'bagisto-sync-for-woocommerce' ); ?>
									</button>
								</div>
							</div>
							<?php wp_nonce_field( 'wkwctobag_connect', 'wkwctobag_nonce' ); ?>
						</form>

						<!-- Update Connection Modal -->
						<div id="wkwctobag-update-connection-modal" class="wkwctobag-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center;">
							<div class="wkwctobag-modal-box" style="background: #fff; border-radius: 12px; width: 100%; max-width: 500px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px;">
									<h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #111827;"><?php esc_html_e( 'Update Connection Details', 'bagisto-sync-for-woocommerce' ); ?></h3>
									<button type="button" id="wkwctobag-close-update-modal" style="background: none; border: none; cursor: pointer; color: #6b7280; font-size: 1.5rem; line-height: 1;">&times;</button>
								</div>

								<form method="POST" class="wkwctobag-ui-form" style="margin-top: 0;">
									<div class="wkwctobag-input-group">
										<label for="update_site_url"><?php esc_html_e( 'Bagisto Store URL', 'bagisto-sync-for-woocommerce' ); ?></label>
										<div class="wkwctobag-input-wrapper">
											<span class="wkwctobag-input-icon dashicons dashicons-admin-site-alt3"></span>
											<input type="url" id="update_site_url" name="site_url" required value="<?php echo esc_attr( $this->data['site_url'] ); ?>" />
										</div>
									</div>

									<div class="wkwctobag-input-group">
										<label for="update_admin_token"><?php esc_html_e( 'Bagisto API Token (Personal Access Token)', 'bagisto-sync-for-woocommerce' ); ?></label>
										<div class="wkwctobag-input-wrapper">
											<span class="wkwctobag-input-icon dashicons dashicons-lock"></span>
											<input type="password" id="update_admin_token" name="admin_token" placeholder="<?php esc_attr_e( 'Enter API Token to save changes', 'bagisto-sync-for-woocommerce' ); ?>" required value="" />
											<button type="button" class="wkwctobag-toggle-password" id="wkwctobag-update-toggle-password-btn"><span class="dashicons dashicons-hidden"></span></button>
										</div>
									</div>

									<div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
										<button type="button" id="wkwctobag-cancel-update" class="button" style="padding: 8px 16px; border-radius: 6px;"><?php esc_html_e( 'Cancel', 'bagisto-sync-for-woocommerce' ); ?></button>
										<button type="submit" name="connect" class="button wkwctobag-btn-primary" style="padding: 8px 16px; border-radius: 6px;">
											<?php esc_html_e( 'Save Changes', 'bagisto-sync-for-woocommerce' ); ?>
										</button>
									</div>
									<?php wp_nonce_field( 'wkwctobag_connect', 'wkwctobag_nonce' ); ?>
								</form>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Connect to Bagisto.
		 *
		 * @param string $site_url    Bagisto site URL.
		 * @param string $admin_token Bagisto admin token.
		 */
		public function connect( $site_url = '', $admin_token = '' ) {
			try {
				if ( empty( $site_url ) ) {
					throw new \Exception( esc_html__( 'Site URL is required.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( empty( $admin_token ) ) {
					throw new \Exception( esc_html__( 'API Token is required.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! wp_http_validate_url( $site_url ) ) {
					throw new \Exception( esc_html__( 'Invalid Site URL.', 'bagisto-sync-for-woocommerce' ) );
				}

				$this->helper->connect_to_bagisto( $site_url, $admin_token );
				$this->helper->add_notice( esc_html__( 'Connected to Bagisto successfully.', 'bagisto-sync-for-woocommerce' ), 'success' );

				$this->data = array(
					'site_url'    => $site_url,
					'admin_token' => '',
				);

			} catch ( \Throwable $th ) {
				$this->helper->add_notice( $th->getMessage(), 'error' );
				$this->data = array(
					'site_url'    => $site_url,
					'admin_token' => $admin_token,
				);
			}
		}
	}
}
