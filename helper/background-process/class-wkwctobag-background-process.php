<?php
/**
 * Background process class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Background_Process;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper\Sync;

/*
 * Defence in depth. The bootstrap in the main plugin file already makes sure
 * WP_Background_Process is loaded before anything can reach this file, but if
 * this class is ever autoloaded through another path with the base class
 * missing, return instead of fataling on the `extends` below.
 */
if ( ! class_exists( 'WP_Background_Process' ) ) {
	return;
}

if ( ! class_exists( 'Wkwctobag_Background_Process' ) ) {
	/**
	 * Background process class.
	 */
	class Wkwctobag_Background_Process extends \WP_Background_Process {
		/**
		 * Action name.
		 *
		 * @var string $action Action name.
		 */
		protected $action = 'wkwctobag_sync_data';

		/**
		 * Is background processing.
		 *
		 * @var bool $is_processing Is background processing.
		 */
		protected $is_processing = false;


		/**
		 * Sync type handled by this queue instance.
		 *
		 * @var string $sync_type Sync type.
		 */
		protected $sync_type = 'general';

		/**
		 * Sync data instance.
		 *
		 * @var Sync\Wkwctobag_Sync_Data
		 */
		protected $sync_data_instance = null;

		/**
		 * Constructor.
		 *
		 * @param string $sync_type Sync type handled by this queue instance.
		 */
		public function __construct( $sync_type = 'general' ) {
			$this->sync_type          = $sync_type;
			$this->action             = 'wkwctobag_sync_' . $sync_type;
			$this->sync_data_instance = Sync\Wkwctobag_Sync_Data::get_instance();
			parent::__construct();
		}

		/**
		 * Register this queue's cron health-check schedule.
		 *
		 * Overrides WP_Background_Process::schedule_cron_healthcheck().
		 *
		 * The parent implementation registers this queue's schedule key with a
		 * label built from __( 'Every %d minutes', 'woocommerce' ). Because it is
		 * hooked to `cron_schedules` in the parent constructor, and this plugin
		 * builds one queue per sync type on `plugins_loaded`, that puts several
		 * callbacks translating the `woocommerce` text domain in front of any
		 * code that reads the cron schedules. On WordPress 6.7 and later that can
		 * raise the "_load_textdomain_just_in_time was called incorrectly ...
		 * triggered too early" notice, which is attributed to this plugin.
		 *
		 * The schedule itself is already registered by
		 * wkwctobag_register_cron_schedules() in the main plugin file, using the
		 * same key and the same 300 second interval, so the parent registration is
		 * a duplicate. Returning the schedules untouched keeps the interval and
		 * the key exactly as they were while leaving the translation to the main
		 * plugin file, which uses this plugin's own text domain.
		 *
		 * @param array $schedules Existing cron schedules.
		 *
		 * @return array Cron schedules, unchanged.
		 */
		public function schedule_cron_healthcheck( $schedules ) {
			return $schedules;
		}

		/**
		 * Is background task processing.
		 */
		public function is_background_processing() {
			return $this->is_processing;
		}

		/**
		 * Memory exceeded
		 *
		 * Ensures the batch process never exceeds 80%
		 * of the maximum WordPress memory.
		 *
		 * @return bool
		 */
		public function memory_exceeded() {
			$memory_limit   = $this->get_memory_limit() * 0.8; // 80% of max memory
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			return apply_filters( $this->identifier . '_memory_exceeded', $return );
		}


		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param mixed $item Queue item to iterate over.
		 *
		 * @throws \Exception If the queue item cannot be processed.
		 *
		 * @return mixed
		 */
		protected function task( $item ) {
			try {
				static $has_logged_disconnect = array();

				$helper = \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance();
				if ( ! $helper->is_connected_with_bagisto() ) {
					$type = ! empty( $item['type'] ) ? $item['type'] : 'background-process';

					if ( empty( $has_logged_disconnect[ $type ] ) ) {
						$entity_id = '';
						foreach ( $item as $k => $v ) {
							if ( strpos( $k, '_id' ) !== false && ! empty( $v ) ) {
								$entity_id = $v;
								break;
							}
						}

						if ( ! empty( $entity_id ) && ! empty( $item['type'] ) ) {
							$helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce %s ID: %s', ucfirst( $item['type'] ), $entity_id ), array( 'source' => $type ) );
						}

						$msg = esc_html__( 'Connection was removed mid-sync. Please reconnect to resume.', 'bagisto-sync-for-woocommerce' );
						$helper->logger->wkwctobag_error( $msg, array( 'source' => $type ) );

						if ( ! empty( $item['type'] ) ) {
							Sync\Wkwctobag_Sync_Status::set_state( $item['type'], 'token_error', $msg );
						}

						$has_logged_disconnect[ $type ] = true;
					}

					return false; // Stop immediately, do not retry.
				}

				$this->is_processing = true;

				$result = $this->sync_data_instance->sync_data( $item );

				if ( isset( $result['success'] ) && false === $result['success'] ) {
					if ( isset( $result['message'] ) && ( strpos( $result['message'], 'token_invalid_401' ) !== false || strpos( $result['message'], 'Invalid Token' ) !== false ) ) {
						if ( ! empty( $item['type'] ) ) {
							Sync\Wkwctobag_Sync_Status::set_state( $item['type'], 'token_error', esc_html__( 'Token expired mid-sync. Please reconnect and re-run.', 'bagisto-sync-for-woocommerce' ) );
						}
						return false; // Stop immediately, do not retry.
					}
					throw new \Exception( $result['message'] ?? 'Sync failed.' );
				}

				if ( ! empty( $item['type'] ) ) {
					Sync\Wkwctobag_Sync_Status::increment_done( $item['type'] );
				}
			} catch ( \Exception $e ) {
				$attempts = isset( $item['_attempts'] ) ? intval( $item['_attempts'] ) : 0;
				if ( $attempts < 3 ) {
					$item['_attempts'] = $attempts + 1;
					return $item;
				}

				$helper = \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance();
				if ( $helper && isset( $helper->logger ) ) {
					$helper->logger->wkwctobag_error(
						sprintf( 'Item permanently failed after 3 attempts: %s', $e->getMessage() ),
						array(
							'source' => 'background-process',
							'item'   => $item,
						)
					);
				}

				if ( ! empty( $item['type'] ) ) {
					Sync\Wkwctobag_Sync_Status::increment_failed( $item['type'], $item );
				}

				return false;
			}

			return false;
		}

		/**
		 * Is queue running.
		 *
		 * @return bool
		 */
		public function is_queue_running() {
			return ! $this->is_queue_empty();
		}

		/**
		 * Get counts of items currently in the queue, grouped by type.
		 *
		 * @return array Map of sync type => remaining count.
		 */
		public function get_queued_counts() {
			$counts        = array();
			$all_progress  = Sync\Wkwctobag_Sync_Status::get_all();
			$active_totals = get_option( 'wkwctobag_active_sync_totals', array() );

			if ( ! empty( $all_progress ) ) {
				foreach ( $all_progress as $type => $progress ) {
					if ( ! isset( $active_totals[ $type ] ) ) {
						continue;
					}

					$total  = isset( $progress['total'] ) ? intval( $progress['total'] ) : 0;
					$done   = isset( $progress['done'] ) ? intval( $progress['done'] ) : 0;
					$failed = isset( $progress['failed'] ) ? intval( $progress['failed'] ) : 0;

					$remaining = max( 0, $total - $done - $failed );
					if ( $remaining > 0 ) {
						$counts[ $type ] = $remaining;
					}
				}
			}

			return $counts;
		}

		/**
		 * Complete.
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {
			set_transient( 'wkwctobag_sync_just_completed', $this->sync_type, 60 );

			$progress = Sync\Wkwctobag_Sync_Status::get_progress( $this->sync_type );
			if ( null !== $progress ) {
				if ( ! isset( $progress['state'] ) || 'token_error' !== $progress['state'] ) {
					$state = ( $progress['failed'] > 0 && 0 === $progress['done'] )
						? 'failed'
						: ( $progress['failed'] > 0 ? 'partial_failure' : 'completed' );
					Sync\Wkwctobag_Sync_Status::set_state( $this->sync_type, $state, "{$progress['done']} synced, {$progress['failed']} failed." );
				}
			}

			$active_totals = get_option( 'wkwctobag_active_sync_totals', array() );
			if ( isset( $active_totals[ $this->sync_type ] ) ) {
				unset( $active_totals[ $this->sync_type ] );
				update_option( 'wkwctobag_active_sync_totals', $active_totals, false );
			}

			$active_types = get_option( 'wkwctobag_active_sync_type', '' );
			$types_array  = array_filter( explode( ',', $active_types ) );
			$types_array  = array_diff( $types_array, array( $this->sync_type ) );
			update_option( 'wkwctobag_active_sync_type', implode( ',', $types_array ), false );

			parent::complete();
		}
	}
}
