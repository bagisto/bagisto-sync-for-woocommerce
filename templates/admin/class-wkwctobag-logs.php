<?php
/**
 * Logs page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

if ( ! class_exists( 'Wkwctobag_Logs' ) ) {
	/**
	 * Logs page template class.
	 */
	class Wkwctobag_Logs {
		/**
		 * Constructor of the logs page.
		 */
		public function __construct() {
			$this->get_logs_template();
		}

		/**
		 * Render the logs screen.
		 *
		 * @return void
		 */
		public function get_logs_template() {
			// Access our global singleton helper.
			$helper = \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance();

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$view_log = isset( $_GET['view-log'] ) ? sanitize_text_field( wp_unslash( $_GET['view-log'] ) ) : '';

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$limit = isset( $_GET['limit'] ) ? max( 50, intval( wp_unslash( $_GET['limit'] ) ) ) : 200;

			// Process deletion if requested.
			if ( isset( $_GET['delete-log'] ) && isset( $_GET['_wpnonce'] ) ) {
				$delete_source = sanitize_text_field( wp_unslash( $_GET['delete-log'] ) );
				if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_log_' . $delete_source ) ) {
					$helper->logger->delete_log( $delete_source );
					echo '<div id="wkwctobag-log-delete-success" class="notice notice-success is-dismissible" style="margin-top: 20px; border-radius: 6px;"><p><strong>' . esc_html__( 'Success:', 'bagisto-sync-for-woocommerce' ) . '</strong> ' . esc_html__( 'Log file deleted permanently.', 'bagisto-sync-for-woocommerce' ) . '</p></div>';
				}
			}

			// Process retention setting update.
			if ( isset( $_POST['wkwctobag_save_log_retention'] ) && isset( $_POST['_wpnonce'] ) ) {
				if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wkwctobag_log_retention_action' ) ) {
					$retention = isset( $_POST['wkwctobag_log_retention'] ) ? sanitize_text_field( wp_unslash( $_POST['wkwctobag_log_retention'] ) ) : '30';
					$helper->save_log_retention( $retention );
					$purged = 0;
					if ( 'never' !== $retention ) {
						$purged = $helper->logger->clean_expired_logs( $retention );
					}
					$purged_msg = '';
					if ( $purged > 0 ) {
						$purged_msg = ' ' . sprintf(
						/* translators: %d: number of expired log files cleaned */
							_n(
								'(%d expired file cleaned immediately)',
								'(%d expired files cleaned immediately)',
								$purged,
								'bagisto-sync-for-woocommerce'
							),
							$purged
						);
					}
					echo '<div class="notice notice-success is-dismissible" style="margin-top: 20px; border-radius: 6px;"><p><strong>' . esc_html__( 'Settings Saved:', 'bagisto-sync-for-woocommerce' ) . '</strong> ' . esc_html__( 'Log retention policy updated successfully.', 'bagisto-sync-for-woocommerce' ) . esc_html( $purged_msg ) . '</p></div>';
				}
			}

			// Process manual purge action.
			if ( isset( $_POST['wkwctobag_purge_expired_logs'] ) && isset( $_POST['_wpnonce'] ) ) {
				if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wkwctobag_log_retention_action' ) ) {
					$count   = $helper->logger->clean_expired_logs();
					$message = sprintf(
					/* translators: %d: number of log files cleaned */
						_n(
							'Manual log cleanup completed. %d log file cleaned based on current retention policy.',
							'Manual log cleanup completed. %d log files cleaned based on current retention policy.',
							$count,
							'bagisto-sync-for-woocommerce'
						),
						$count
					);
					echo '<div class="notice notice-info is-dismissible" style="margin-top: 20px; border-radius: 6px;"><p><strong>' . esc_html__( 'Log Cleanup:', 'bagisto-sync-for-woocommerce' ) . '</strong> ' . esc_html( $message ) . '</p></div>';
				}
			}
			?>
				<div class="wrap">
					<div class="wkwctobag-bagisto-wrapper">
						<div>
							<p class="wkwctobag-bagisto-title"><?php esc_html_e( 'LOGS', 'bagisto-sync-for-woocommerce' ); ?></p>
							<p class="wkwctobag-bagisto-description"><?php esc_html_e( 'Select a specific background process to view its recent synchronization activity.', 'bagisto-sync-for-woocommerce' ); ?></p>
							<hr />
						</div>

						<?php if ( ! $view_log ) : ?>
							<?php $current_retention = $helper->get_log_retention(); ?>
							<div class="wkwctobag-logs-policy-bar">
								<form method="POST">
									<?php wp_nonce_field( 'wkwctobag_log_retention_action' ); ?>
									<div class="wkwctobag-select-wrapper">
										<label for="wkwctobag_log_retention">
											<svg style="width: 18px; height: 18px; color: #4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
											<?php esc_html_e( 'Auto Delete Logs:', 'bagisto-sync-for-woocommerce' ); ?>
										</label>
										<select id="wkwctobag_log_retention" name="wkwctobag_log_retention" class="wkwctobag-custom-select">
											<option value="1" <?php selected( $current_retention, '1' ); ?>><?php esc_html_e( 'Older than 1 Day', 'bagisto-sync-for-woocommerce' ); ?></option>
											<option value="15" <?php selected( $current_retention, '15' ); ?>><?php esc_html_e( 'Older than 15 Days', 'bagisto-sync-for-woocommerce' ); ?></option>
											<option value="30" <?php selected( $current_retention, '30' ); ?>><?php esc_html_e( 'Older than 30 Days', 'bagisto-sync-for-woocommerce' ); ?></option>
											<option value="never" <?php selected( $current_retention, 'never' ); ?>><?php esc_html_e( 'Never (Keep Indefinitely)', 'bagisto-sync-for-woocommerce' ); ?></option>
										</select>
									</div>
									<button type="submit" name="wkwctobag_save_log_retention" value="1" class="button button-primary" style="border-radius: 6px; padding: 2px 16px; font-weight: 600; font-size: 13px; height: 32px; line-height: 28px;">
										<?php esc_html_e( 'Save Policy', 'bagisto-sync-for-woocommerce' ); ?>
									</button>
								</form>
								<form id="wkwctobag-purge-logs-container" method="POST" style="margin: 0; display: <?php echo 'never' === $current_retention ? 'none' : 'block'; ?>;">
									<?php wp_nonce_field( 'wkwctobag_log_retention_action' ); ?>
									<button type="submit" name="wkwctobag_purge_expired_logs" value="1" class="button button-secondary" style="border-radius: 6px; padding: 2px 16px; font-weight: 600; font-size: 13px; border-color: #d1d5db; color: #374151; height: 32px; line-height: 28px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
										<svg style="width: 14px; height: 14px; color: #ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
										<span><?php esc_html_e( 'Purge Expired Logs Now', 'bagisto-sync-for-woocommerce' ); ?></span>
									</button>
								</form>
							</div>

						<?php endif; ?>

						<?php
						$titles = array(
							'category'           => 'Category Sync Log',
							'product'            => 'Product Sync Log',
							'user'               => 'Admin User Sync Log',
							'customer'           => 'Customer Sync Log',
							'order'              => 'Order Sync Log',
							'api'                => 'API Engine Log',
							'attribute'          => 'Attribute Sync Log',
							'tax'                => 'Tax Sync Log',
							'background-process' => 'Background Process Log',
						);

						if ( ! $helper->logger->has_write_permission() ) :
							?>
							<div class="notice notice-error inline" style="margin-top: 15px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
								<p><strong><?php esc_html_e( 'Permission Denied:', 'bagisto-sync-for-woocommerce' ); ?></strong> <?php esc_html_e( 'The Bagisto Sync for WooCommerce does not have write permissions to the WordPress uploads directory. Background logs will not be recorded until server permissions are fixed.', 'bagisto-sync-for-woocommerce' ); ?></p>
							</div>
							<?php
						elseif ( $view_log ) :
							$log_path    = trailingslashit( wp_upload_dir()['basedir'] ) . 'bagisto-sync-for-woocommerce-logs/wkwctobag-' . sanitize_file_name( $view_log ) . '.log';
							$file_exists = file_exists( $log_path );
							$file_size   = $file_exists ? size_format( filesize( $log_path ) ) : '0 KB';
							$file_date   = $file_exists ? gmdate( 'M d, Y', filemtime( $log_path ) ) : '-';
							$file_time   = $file_exists ? gmdate( 'h:i:s A', filemtime( $log_path ) ) : '-';
							$log_title   = isset( $titles[ $view_log ] ) ? $titles[ $view_log ] : 'System Log';
							$raw_log     = str_replace( array( "\r\n", "\r" ), "\n", $helper->logger->wkwctobag_tail( $view_log, $limit ) );
							$lines       = empty( $raw_log ) ? array() : array_reverse( array_filter( explode( "\n", trim( $raw_log ) ) ) );
							?>
							<div style="margin-top: 15px;">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wkwctobag-logs' ) ); ?>" style="text-decoration: none; color: #2563eb; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; font-size: 14px;">
									<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
									<span><?php esc_html_e( 'Back to Log List', 'bagisto-sync-for-woocommerce' ); ?></span>
								</a>
							</div>

							<div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-top: 15px;">
								<div style="padding: 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; gap: 20px;">
									<div style="display: flex; align-items: center; gap: 16px;">
										<div style="background: #eff6ff; color: #3b82f6; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
											<svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
										</div>
										<div>
											<h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #111827;"><?php echo esc_html( $log_title ); ?></h2>
											<div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
												<span style="color: #4b5563; font-size: 13px;">wkwctobag-<?php echo esc_html( $view_log ); ?>.log</span>
												<span style="background: #dcfce7; color: #166534; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Active</span>
											</div>
										</div>
									</div>

									<div style="display: flex; gap: 20px; color: #4b5563; font-size: 13px; flex-wrap: wrap;">
										<span style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
											<svg style="width: 16px; height: 16px; color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
											Date: <?php echo esc_html( $file_date ); ?>
										</span>
										<span style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
											<svg style="width: 16px; height: 16px; color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
											Time: <?php echo esc_html( $file_time ); ?>
										</span>
										<span style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
											<svg style="width: 16px; height: 16px; color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
											Log Size: <?php echo esc_html( $file_size ); ?>
										</span>
									</div>

									<div style="display: flex; gap: 12px; flex-wrap: wrap;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wkwctobag-logs&view-log=' . urlencode( $view_log ) . '&limit=' . ( $limit + 200 ) ) ); ?>" style="display: flex; align-items: center; gap: 6px; border: 1px solid #d1d5db; color: #374151; background: white; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; white-space: nowrap;">
											<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
											<?php esc_html_e( 'Load More', 'bagisto-sync-for-woocommerce' ); ?>
										</a>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wkwctobag-logs&view-log=' . urlencode( $view_log ) . '&limit=' . $limit ) ); ?>" style="display: flex; align-items: center; gap: 6px; border: 1px solid #3b82f6; color: #3b82f6; background: white; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; white-space: nowrap;">
											<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
											<?php esc_html_e( 'Refresh', 'bagisto-sync-for-woocommerce' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wkwctobag-logs&download-log=' . urlencode( $view_log ) ), 'wkwctobag_download_log' ) ); ?>" style="display: flex; align-items: center; gap: 6px; background: #2563eb; color: white; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; white-space: nowrap;">
											<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
											<?php esc_html_e( 'Download', 'bagisto-sync-for-woocommerce' ); ?>
										</a>
									</div>
								</div>

								<div style="background: #111827; padding: 20px 0; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; height: 500px; overflow-y: auto; font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; line-height: 1.4; direction: ltr; text-align: left;">
									<?php if ( empty( $lines ) ) : ?>
										<div style="color: #6b7280; padding: 0 20px;"><?php esc_html_e( 'No log entries found.', 'bagisto-sync-for-woocommerce' ); ?></div>
									<?php else : ?>
										<?php
										foreach ( $lines as $index => $line ) :
											$log_time    = '';
											$log_level   = '';
											$log_msg     = $line;
											$level_color = '#60a5fa';

											$context_json = '';
											if ( preg_match( '/^\[(.*?)\] \[(.*?)\] (.*)$/', $line, $matches ) ) {
												$log_time  = '[' . $matches[1] . ']';
												$log_level = '[' . $matches[2] . ']';
												$log_msg   = $matches[3];

												// Check for JSON context.
												$context_pos = strrpos( $log_msg, ' | {' );
												if ( false !== $context_pos ) {
													$context_json = substr( $log_msg, $context_pos + 3 );
													$log_msg      = substr( $log_msg, 0, $context_pos );
												}

												if ( 'ERROR' === $matches[2] ) {
													$level_color = '#f87171';
												} elseif ( 'INFO' === $matches[2] ) {
													$level_color = '#34d399';
												}
											}
											?>
											<div style="display: flex; padding: 6px 20px; align-items: flex-start; border-bottom: 1px solid rgba(255,255,255,0.02); flex-direction: column;">
												<div style="display: flex; width: 100%; align-items: flex-start;">
													<span style="color: #4b5563; min-width: 35px; text-align: right; margin-right: 15px; user-select: none; border-right: 1px solid #374151; padding-right: 15px; flex-shrink: 0;"><?php echo (int) $index + 1; ?></span>
													<?php if ( $log_time ) : ?>
														<span style="color: <?php echo esc_attr( $level_color ); ?>; width: 65px; flex-shrink: 0; font-weight: 600;"><?php echo esc_html( $log_level ); ?></span>
														<span style="color: #e5e7eb; flex-grow: 1; padding-right: 20px; word-wrap: break-word; white-space: pre-wrap;"><?php echo esc_html( $log_msg ); ?></span>
														<span style="color: #6b7280; font-size: 12px; flex-shrink: 0; white-space: nowrap;"><?php echo esc_html( $log_time ); ?></span>
													<?php else : ?>
														<span style="color: #e5e7eb; word-wrap: break-word; white-space: pre-wrap; flex-grow: 1;"><?php echo esc_html( $line ); ?></span>
													<?php endif; ?>
												</div>
												<?php if ( $context_json ) : ?>
													<details class="wkwc-log-details" style="margin-top: 6px; margin-left: 130px; width: calc(100% - 130px);">
														<summary style="color: #9ca3af; font-size: 12px; font-weight: 500; display: inline-flex;  gap: 4px; cursor: pointer; user-select: none; outline: none; list-style: none;">
															<svg style="width: 14px; height: 14px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
															<?php esc_html_e( 'Additional context', 'bagisto-sync-for-woocommerce' ); ?>
														</summary>
														<?php
															$decoded     = json_decode( $context_json, true );
															$json_output = ( json_last_error() === JSON_ERROR_NONE ) ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $context_json;
															echo '<div style="margin-top: 8px; background: #1f2937; padding: 12px; border-radius: 6px; color: #d1d5db; font-size: 12px; white-space: pre-wrap; overflow-x: auto; border: 1px solid #374151;">' . esc_html( trim( $json_output ) ) . '</div>';
														?>
													</details>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>
						<?php else : ?>
							<?php $logs = $helper->logger->get_available_logs(); ?>
							<?php if ( empty( $logs ) ) : ?>
								<div style="background: #ffffff; border-radius: 12px; padding: 30px; text-align: center; margin-top: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px dashed #d1d5db;">
									<svg style="width: 48px; height: 48px; color: #9ca3af; margin: 0 auto 15px auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
									<h3 style="margin: 0 0 8px 0; color: #374151; font-weight: 600; font-size: 16px;"><?php esc_html_e( 'No Logs Available', 'bagisto-sync-for-woocommerce' ); ?></h3>
									<p style="margin: 0; color: #6b7280; font-size: 14px;"><?php esc_html_e( 'Log files will appear here automatically once the background synchronization processes execute.', 'bagisto-sync-for-woocommerce' ); ?></p>
								</div>
							<?php else : ?>

								<div style="margin-top: 30px;">
									<?php foreach ( $logs as $log ) : ?>
										<div class="wkwc-log-card">
											<div class="wkwc-log-card-left">
												<div class="wkwc-log-icon">
													<svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
												</div>
												<div class="wkwc-log-info">
													<h4>
														<?php echo esc_html( isset( $titles[ $log['source'] ] ) ? $titles[ $log['source'] ] : $log['filename'] ); ?>
													</h4>
													<div class="wkwc-log-meta">
														<span>
															<svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
															<?php echo esc_html( $log['size'] ); ?>
														</span>
														<span>
															<svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
															<?php echo esc_html( $log['modified'] ); ?>
														</span>
													</div>
												</div>
											</div>
											<div class="wkwc-log-card-right">
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wkwctobag-logs&delete-log=' . urlencode( $log['source'] ) ), 'delete_log_' . $log['source'] ) ); ?>" class="wkwc-btn-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to permanently delete this log file?', 'bagisto-sync-for-woocommerce' ); ?>');">
													<?php esc_html_e( 'Delete', 'bagisto-sync-for-woocommerce' ); ?>
												</a>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=wkwctobag-logs&view-log=' . urlencode( $log['source'] ) ) ); ?>" class="wkwc-btn-view">
													<?php esc_html_e( 'View Log', 'bagisto-sync-for-woocommerce' ); ?>
												</a>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php
		}
	}
}
