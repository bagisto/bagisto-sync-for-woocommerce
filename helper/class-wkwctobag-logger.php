<?php
/**
 * Bagisto WooCommerce Connector Logger
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wkwctobag_Logger' ) ) {
	/**
	 * Wkwctobag_Logger Class
	 */
	class Wkwctobag_Logger {

		/**
		 * Log directory path.
		 *
		 * @var string
		 */
		private $log_dir;

		/**
		 * Array of active file streams mapped by source.
		 *
		 * @var \SplFileObject[]
		 */
		private $file_streams = array();

		/**
		 * Write permission flag.
		 *
		 * @var bool
		 */
		private $has_permission = true;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$upload_dir    = wp_upload_dir();
			$this->log_dir = trailingslashit( $upload_dir['basedir'] ) . 'bagisto-sync-for-woocommerce-logs';

			try {
				$this->ensure_secure_directory();
			} catch ( \Exception $e ) {
				$this->has_permission = false;
			}
		}

		/**
		 * Ensure the log directory exists and is strictly blocked from web access.
		 *
		 * @throws \Exception If directory creation fails.
		 */
		private function ensure_secure_directory() {
			if ( ! file_exists( $this->log_dir ) ) {
				if ( ! wp_mkdir_p( $this->log_dir ) ) {
					throw new \Exception( 'Could not create log directory.' );
				}
			}

			$index_file = trailingslashit( $this->log_dir ) . 'index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden.' );
			}

			/*
			 * Block direct web access to the log files.
			 *
			 * "Deny from all" alone is an Apache 2.2 directive and is ignored by
			 * Apache 2.4 unless mod_access_compat is loaded, so emit both syntaxes.
			 * Note that .htaccess has no effect on nginx: on those servers the log
			 * directory must be denied in the server configuration instead.
			 */
			$htaccess_file = trailingslashit( $this->log_dir ) . '.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				$htaccess_rules = "<IfModule mod_authz_core.c>\n"
					. "\tRequire all denied\n"
					. "</IfModule>\n"
					. "<IfModule !mod_authz_core.c>\n"
					. "\tOrder allow,deny\n"
					. "\tDeny from all\n"
					. "</IfModule>\n";
				file_put_contents( $htaccess_file, $htaccess_rules );
			}
		}

		/**
		 * Check if the logger has write permissions.
		 *
		 * @return bool
		 */
		public function has_write_permission() {
			return $this->has_permission;
		}

		/**
		 * Retrieve or initialize the stream for a specific source.
		 *
		 * @param string $source The source context (e.g., 'product', 'order').
		 * @return \SplFileObject|null
		 */
		private function get_stream( $source ) {
			if ( ! $this->has_permission ) {
				return null;
			}

			$source = sanitize_file_name( $source );

			if ( isset( $this->file_streams[ $source ] ) ) {
				return $this->file_streams[ $source ];
			}

			$filename  = 'wkwctobag-' . $source;
			$file_path = trailingslashit( $this->log_dir ) . $filename . '.log';

			try {
				$this->rotate_log_if_needed( $file_path );
				$stream                        = new \SplFileObject( $file_path, 'a+' );
				$this->file_streams[ $source ] = $stream;
				return $stream;
			} catch ( \Exception $e ) {
				$this->has_permission = false;
				return null;
			}
		}

		/**
		 * Helper to get WP_Filesystem instance safely.
		 *
		 * @return \WP_Filesystem_Base|false
		 */
		private function get_wp_filesystem() {
			global $wp_filesystem;

			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			return $wp_filesystem;
		}

		/**
		 * Rotate the log file if it exceeds the 5MB limit.
		 *
		 * @param string $file_path The absolute path to the log file.
		 */
		private function rotate_log_if_needed( $file_path ) {
			if ( ! file_exists( $file_path ) ) {
				return;
			}

			clearstatcache( true, $file_path );
			$file_size = filesize( $file_path );
			$max_size  = 5 * 1024 * 1024; // 5MB limit

			if ( $file_size >= $max_size ) {
				$rotated_path  = str_replace( '.log', '-' . current_time( 'Ymd_His' ) . '.log', $file_path );
				$wp_filesystem = $this->get_wp_filesystem();
				if ( $wp_filesystem ) {
					$wp_filesystem->move( $file_path, $rotated_path, true );
				}
			}
		}

		/**
		 * Log an info message.
		 *
		 * @param string $message The message to log.
		 * @param array  $context Optional context (must contain 'source' to route to different file).
		 */
		public function wkwctobag_info( $message, $context = array() ) {
			$this->wkwctobag_log( 'INFO', $message, $context );
		}

		/**
		 * Log an error message.
		 *
		 * @param string $message The message to log.
		 * @param array  $context Optional context (must contain 'source' to route to different file).
		 */
		public function wkwctobag_error( $message, $context = array() ) {
			$this->wkwctobag_log( 'ERROR', $message, $context );
		}

		/**
		 * Core writing function with exclusive file locking and dynamic routing.
		 *
		 * @param string $level   The severity level (e.g., INFO, ERROR).
		 * @param string $message The actual log message.
		 * @param array  $context Additional array context.
		 */
		private function wkwctobag_log( $level, $message, $context = array() ) {
			$source = isset( $context['source'] ) ? $context['source'] : 'sync';
			$stream = $this->get_stream( $source );

			if ( ! $stream ) {
				return;
			}

			$time = current_time( 'Y-m-d H:i:s' );

			$output_context = $context;
			unset( $output_context['source'] );
			$context_string = ! empty( $output_context ) ? ' | ' . wp_json_encode( $output_context ) : '';

			$line = sprintf( "[%s] [%s] %s%s\n", $time, $level, $message, $context_string );

			if ( $stream->flock( LOCK_EX ) ) {
				$stream->fseek( 0, SEEK_END );
				$stream->fwrite( $line );
				$stream->flock( LOCK_UN );
			}
		}

		/**
		 * Read the last N lines of a specific log file efficiently.
		 *
		 * @param string $source The source context to read from.
		 * @param int    $lines  Number of lines to read.
		 * @return string The log content.
		 */
		public function wkwctobag_tail( $source = 'sync', $lines = 100 ) {
			$stream = $this->get_stream( $source );

			if ( ! $stream ) {
				return '';
			}

			$stream->rewind();
			$stream->seek( PHP_INT_MAX );
			$total_lines = $stream->key();

			$start_line = max( 0, $total_lines - $lines );
			$stream->seek( $start_line );

			$output = '';
			while ( ! $stream->eof() ) {
				$output .= $stream->current();
				$stream->next();
			}

			return $output;
		}

		/**
		 * Get all available active log files for the UI table.
		 *
		 * @return array Array of log file data.
		 */
		public function get_available_logs() {
			if ( ! $this->has_permission || ! file_exists( $this->log_dir ) ) {
				return array();
			}

			$logs  = array();
			$files = glob( trailingslashit( $this->log_dir ) . '*.log' );

			if ( ! empty( $files ) ) {
				usort(
					$files,
					function ( $a, $b ) {
						return filemtime( $b ) - filemtime( $a );
					}
				);

				foreach ( $files as $file ) {
					$filename = basename( $file );
					$source   = str_replace( array( 'wkwctobag-', '.log' ), '', $filename );

					if ( preg_match( '/-\d{8}_\d{6}\.log$/', $filename ) ) {
						continue;
					}

					$logs[] = array(
						'source'   => $source,
						'filename' => $filename,
						'size'     => size_format( filesize( $file ) ),
						'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $file ) ),
					);
				}
			}

			return $logs;
		}

		/**
		 * Securely delete a specific log file.
		 *
		 * @param string $source The source context to delete.
		 * @return bool True on success, false on failure.
		 */
		public function delete_log( $source ) {
			if ( ! $this->has_permission || empty( $source ) ) {
				return false;
			}

			$source    = sanitize_file_name( $source );
			$filename  = 'wkwctobag-' . $source . '.log';
			$file_path = trailingslashit( $this->log_dir ) . $filename;

			if ( isset( $this->file_streams[ $source ] ) ) {
				$this->file_streams[ $source ] = null;
				unset( $this->file_streams[ $source ] );
			}

			if ( file_exists( $file_path ) ) {
				return wp_delete_file( $file_path );
			}

			return false;
		}

		/**
		 * Clean expired log files based on retention policy.
		 *
		 * @param string|int|null $days Retention period ('1', '15', '30', 'never'). If null, uses saved setting.
		 * @return int Number of files cleaned or updated.
		 */
		public function clean_expired_logs( $days = null ) {
			if ( ! $this->has_permission ) {
				return 0;
			}

			if ( null === $days ) {
				$helper = Wkwctobag_Helper::get_instance();
				$days   = $helper->get_log_retention();
			}

			if ( 'never' === (string) $days || empty( $days ) ) {
				return 0;
			}

			$now_timestamp  = current_time( 'timestamp' );
			$retention_days = intval( $days );
			if ( $retention_days <= 0 ) {
				return 0;
			}
			$cutoff_timestamp = $now_timestamp - ( $retention_days * DAY_IN_SECONDS );

			$upload_dir   = wp_upload_dir();
			$base_log_dir = trailingslashit( $upload_dir['basedir'] ) . 'bagisto-sync-for-woocommerce-logs';

			if ( ! file_exists( $base_log_dir ) || ! is_dir( $base_log_dir ) ) {
				return 0;
			}

			$cleaned_count = 0;

			// Close open file streams to release locks before maintenance
			foreach ( $this->file_streams as $source => $stream ) {
				$this->file_streams[ $source ] = null;
			}
			$this->file_streams = array();

			try {
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $base_log_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::CHILD_FIRST
				);

				foreach ( $iterator as $file_info ) {
					if ( $file_info->isFile() ) {
						$file_path = $file_info->getPathname();
						$file_name = $file_info->getFilename();

						if ( 'log' !== pathinfo( $file_name, PATHINFO_EXTENSION ) ) {
							continue;
						}

						if ( preg_match( '/-\d{8}_\d{6}\.log$/', $file_name ) ) {
							if ( $file_info->getMTime() < $cutoff_timestamp ) {
								if ( wp_delete_file( $file_path ) ) {
									++$cleaned_count;
								}
							}
						} else {
							$lines = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
							if ( is_array( $lines ) ) {
								$fresh_lines = array();
								foreach ( $lines as $line ) {
									if ( preg_match( '/^\[(.*?)\]/', $line, $matches ) ) {
										$entry_time = strtotime( $matches[1] );
										if ( $entry_time && $entry_time >= $cutoff_timestamp ) {
											$fresh_lines[] = $line;
										}
									} else {
										// Retain lines without timestamps or non-standard format
										$fresh_lines[] = $line;
									}
								}
								if ( count( $fresh_lines ) !== count( $lines ) ) {
									if ( empty( $fresh_lines ) ) {
										wp_delete_file( $file_path );
									} else {
										file_put_contents( $file_path, implode( "\n", $fresh_lines ) . "\n" );
									}
									++$cleaned_count;
								}
							}
						}
					} elseif ( $file_info->isDir() ) {
						$dir_path = $file_info->getPathname();
						if ( $dir_path !== $base_log_dir ) {
							$files_in_dir = array_diff( scandir( $dir_path ), array( '.', '..', 'index.php', '.htaccess' ) );
							if ( empty( $files_in_dir ) ) {
								$wp_fs = $this->get_wp_filesystem();
								if ( $wp_fs ) {
									$wp_fs->rmdir( $dir_path );
								}
							}
						}
					}
				}
			} catch ( \Exception $e ) {
				// Silence iterator exceptions gracefully
			}

			return $cleaned_count;
		}
	}
}
