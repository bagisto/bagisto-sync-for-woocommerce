<?php
/**
 * Sync data class file.
 *
 * @package Bagisto Sync for WooCommerce
 */

namespace WKWCBAGCONNECTOR\Helper\Sync;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

use WKWCBAGCONNECTOR\Helper\Sync;

if ( ! class_exists( 'Wkwctobag_Sync_Data' ) ) {
	/**
	 * Sync data class.
	 */
	class Wkwctobag_Sync_Data {
		/**
		 * Singleton instance.
		 *
		 * @var Wkwctobag_Sync_Data|null $instance Instance of this class.
		 */
		private static $instance = null;

		/**
		 * Constructor of this class.
		 */
		private function __construct() {}

		/**
		 * Get the instance of this class.
		 *
		 * @return Wkwctobag_Sync_Data|null Instance of this class.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Sync data.
		 *
		 * @param array $item Item data.
		 */
		public function sync_data( $item ) {
			static $handlers = array();

			$type = $item['type'] ?? '';

			if ( empty( $type ) ) {
				return;
			}

			$map = array(
				'product'   => Sync\Product\Wkwctobag_Sync_Product::class,
				'category'  => Sync\Category\Wkwctobag_Sync_Category::class,
				'order'     => Sync\Order\Wkwctobag_Sync_Order::class,
				'user'      => Sync\User\Wkwctobag_Sync_User::class,
				'customer'  => Sync\Customer\Wkwctobag_Sync_Customer::class,
				'attribute' => Sync\Attribute\Wkwctobag_Sync_Attribute::class,
				'tax'       => Sync\Tax\Wkwctobag_Sync_Tax::class,
			);

			if ( ! isset( $map[ $type ] ) ) {
				return;
			}

			if ( ! isset( $handlers[ $type ] ) ) {
				$handlers[ $type ] = new $map[ $type ]();
			}

			return $handlers[ $type ]->sync( $item );
		}
	}
}
