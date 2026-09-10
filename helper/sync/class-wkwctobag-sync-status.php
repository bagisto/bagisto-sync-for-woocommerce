<?php
/**
 * Sync status class file.
 *
 * @package Bagisto Sync for WooCommerce
 */

namespace WKWCBAGCONNECTOR\Helper\Sync;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

if ( ! class_exists( 'Wkwctobag_Sync_Status' ) ) {
	/**
	 * Sync status tracking class.
	 */
	class Wkwctobag_Sync_Status {

		/**
		 * Initialize progress for a sync type.
		 *
		 * @param string $type  Sync type.
		 * @param int    $total Total items.
		 */
		public static function init( $type, $total ) {
			$progress = array(
				'total'  => $total,
				'done'   => 0,
				'failed' => 0,
			);
			update_option( 'wkwctobag_sync_progress_' . $type, $progress, false );
			update_option(
				'wkwctobag_sync_state_' . $type,
				array(
					'state'   => 'running',
					'message' => '',
				),
				false
			);
			delete_option( 'wkwctobag_sync_failed_' . $type );
			wp_cache_delete( 'wkwctobag_all_sync_progress', 'wkwctobag' );
		}

		/**
		 * Increment done count for a sync type.
		 *
		 * @param string $type Sync type.
		 */
		public static function increment_done( $type ) {
			$progress = get_option( 'wkwctobag_sync_progress_' . $type, array() );
			if ( ! empty( $progress ) ) {
				++$progress['done'];
				update_option( 'wkwctobag_sync_progress_' . $type, $progress, false );
			}
		}

		/**
		 * Increment failed count for a sync type.
		 *
		 * @param string $type Sync type.
		 * @param array  $item Item data.
		 */
		public static function increment_failed( $type, $item ) {
			$progress = get_option( 'wkwctobag_sync_progress_' . $type, array() );
			if ( ! empty( $progress ) ) {
				++$progress['failed'];
				update_option( 'wkwctobag_sync_progress_' . $type, $progress, false );
			}

			$failed_items   = get_option( 'wkwctobag_sync_failed_' . $type, array() );
			$failed_items[] = $item;
			update_option( 'wkwctobag_sync_failed_' . $type, $failed_items, false );
		}

		/**
		 * Set state for a sync type.
		 *
		 * @param string $type    Sync type.
		 * @param string $state   State string.
		 * @param string $message Message.
		 */
		public static function set_state( $type, $state, $message = '' ) {
			update_option(
				'wkwctobag_sync_state_' . $type,
				array(
					'state'   => $state,
					'message' => $message,
				),
				false
			);
		}

		/**
		 * Get progress for a sync type.
		 *
		 * @param string $type Sync type.
		 * @return array|null Progress data or null if not active.
		 */
		public static function get_progress( $type ) {
			$progress = get_option( 'wkwctobag_sync_progress_' . $type );
			if ( false === $progress ) {
				return null;
			}

			$state_data = get_option(
				'wkwctobag_sync_state_' . $type,
				array(
					'state'   => 'running',
					'message' => '',
				)
			);

			return array_merge( $progress, $state_data );
		}

		/**
		 * Get all active sync types.
		 *
		 * @return array Array of active progress.
		 */
		public static function get_all() {
			global $wpdb;

			$cache_key = 'wkwctobag_all_sync_progress';
			$results   = wp_cache_get( $cache_key, 'wkwctobag' );

			if ( false === $results ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( 'wkwctobag_sync_progress_' ) . '%'
					)
				);
				if ( is_array( $results ) ) {
					wp_cache_set( $cache_key, $results, 'wkwctobag', 10 );
				}
			}

			$all_progress = array();

			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$type     = str_replace( 'wkwctobag_sync_progress_', '', $row->option_name );
					$progress = maybe_unserialize( $row->option_value );

					$state_data            = get_option(
						'wkwctobag_sync_state_' . $type,
						array(
							'state'   => 'running',
							'message' => '',
						)
					);
					$all_progress[ $type ] = array_merge( $progress, $state_data );
				}
			}

			return $all_progress;
		}

		/**
		 * Clear sync data.
		 *
		 * @param string $type Sync type.
		 */
		public static function clear( $type ) {
			delete_option( 'wkwctobag_sync_progress_' . $type );
			delete_option( 'wkwctobag_sync_state_' . $type );
			delete_option( 'wkwctobag_sync_failed_' . $type );
			wp_cache_delete( 'wkwctobag_all_sync_progress', 'wkwctobag' );
		}
	}
}
