<?php
/**
 * Front hooks
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Includes\Front;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

if ( ! class_exists( 'Wkwctobag_Front_Hooks' ) ) {
	/**
	 * Front hooks class
	 */
	class Wkwctobag_Front_Hooks {
		/**
		 * Instance variable.
		 *
		 * @var $instance
		 */
		protected static $instance = null;

		/**
		 * Construct.
		 */
		public function __construct() {
			$function_handler = Wkwctobag_Front_Functions::get_instance();
			add_action( 'wp_enqueue_scripts', array( $function_handler, 'wkwctobag_front_enqueue_scripts' ) );

			add_action( 'user_register', array( $function_handler, 'auto_sync_user_registration' ), 10, 1 );
			add_action( 'woocommerce_created_customer', array( $function_handler, 'auto_sync_woocommerce_customer' ), 10, 1 );
			add_action( 'wkwctobag_delayed_customer_sync', array( $function_handler, 'auto_sync_woocommerce_customer_delayed' ), 10, 1 );
			add_action( 'woocommerce_checkout_order_processed', array( $function_handler, 'auto_sync_storefront_order' ), 20, 3 );
			add_action( 'woocommerce_store_api_checkout_order_processed', array( $function_handler, 'auto_sync_storefront_order' ), 20, 1 );
			add_action( 'wkwctobag_delayed_order_sync', array( $function_handler, 'auto_sync_woocommerce_order_delayed' ), 10, 1 );
			add_action( 'wkwctobag_delayed_order_create_sync', array( $function_handler, 'auto_sync_woocommerce_order_create_delayed' ), 10, 1 );
			add_action( 'wkwctobag_delayed_order_status_sync', array( $function_handler, 'auto_sync_woocommerce_order_status_change_delayed' ), 10, 3 );
			add_action( 'wkwctobag_delayed_category_create_sync', array( $function_handler, 'auto_sync_category_create_delayed' ), 10, 1 );
			add_action( 'wkwctobag_delayed_category_update_sync', array( $function_handler, 'auto_sync_category_update_delayed' ), 10, 1 );
			add_action( 'wkwctobag_delayed_product_create_sync', array( $function_handler, 'auto_sync_product_create_delayed' ), 10, 1 );
			add_action( 'wkwctobag_delayed_product_update_sync', array( $function_handler, 'auto_sync_product_update_delayed' ), 10, 1 );
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
