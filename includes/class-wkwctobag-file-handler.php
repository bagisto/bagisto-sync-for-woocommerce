<?php
/**
 * File handler class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Includes;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Includes\Admin;
use WKWCBAGCONNECTOR\Includes\Front;

if ( ! class_exists( 'Wkwctobag_File_Handler' ) ) {
	/**
	 * File handler class.
	 */
	class Wkwctobag_File_Handler {
		/**
		 * Instance of this class.
		 *
		 * @var Wkwctobag_File_Handler $instance Instance of this class.
		 */
		protected static $instance = null;

		/**
		 * Constructor of the class.
		 */
		public function __construct() {
			if ( is_admin() ) {
				Admin\Wkwctobag_Admin_Hooks::get_instance();
			}
			Front\Wkwctobag_Front_Hooks::get_instance();

			$types = array( 'product', 'category', 'order', 'customer', 'user', 'attribute', 'tax' );
			foreach ( $types as $type ) {
				\WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance()->get_background_process( $type );
			}
		}

		/**
		 * Ensures only one instance of this class is loaded or can be loaded.
		 *
		 * @return object
		 */
		public static function get_instance() {
			if ( ! static::$instance ) {
				static::$instance = new self();
			}
			return static::$instance;
		}
	}
}
