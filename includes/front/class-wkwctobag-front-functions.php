<?php
/**
 * Front functions
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Includes\Front;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Helper;
use WKWCBAGCONNECTOR\Helper\Sync\Customer;
use WKWCBAGCONNECTOR\Helper\Sync\User;

if ( ! class_exists( 'Wkwctobag_Front_Functions' ) ) {
	/**
	 * Front functions class
	 */
	class Wkwctobag_Front_Functions {
		/**
		 * Instance variable.
		 *
		 * @var $instance
		 */
		protected static $instance = null;

		/**
		 * Helper class instance.
		 *
		 * @var Helper\Wkwctobag_Helper $helper Helper class instance.
		 */
		private $helper = null;

		/**
		 * Customer sync instance.
		 *
		 * @var Customer\Wkwctobag_Sync_Customer $customer_sync Customer sync instance.
		 */
		private $customer_sync = null;

		/**
		 * User sync instance.
		 *
		 * @var User\Wkwctobag_Sync_User $user_sync User sync instance.
		 */
		private $user_sync = null;

		/**
		 * Constructor of the class.
		 */
		public function __construct() {
			$this->helper        = Helper\Wkwctobag_Helper::get_instance();
			$this->user_sync     = new User\Wkwctobag_Sync_User();
			$this->customer_sync = new Customer\Wkwctobag_Sync_Customer();
		}

		/**
		 * Add front scripts.
		 */
		public function wkwctobag_front_enqueue_scripts() {
		}

		/**
		 * Auto sync user on WordPress user registration.
		 *
		 * @param int $user_id User ID.
		 */
		public function auto_sync_user_registration( $user_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$reg_nonce = isset( $_POST['woocommerce-register-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-register-nonce'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$login_nonce = isset( $_POST['woocommerce-login-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-login-nonce'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$checkout_nonce = isset( $_POST['woocommerce-process-checkout-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ) : '';

			if (
				( $reg_nonce && wp_verify_nonce( $reg_nonce, 'woocommerce-register' ) ) ||
				( $login_nonce && wp_verify_nonce( $login_nonce, 'woocommerce-login' ) ) ||
				( $checkout_nonce && wp_verify_nonce( $checkout_nonce, 'woocommerce-process_checkout' ) )
			) {
				return;
			}

			$this->auto_sync_user_or_customer( $user_id );
		}

		/**
		 * Auto sync customer on WooCommerce customer creation.
		 *
		 * @param int $customer_id Customer ID.
		 */
		public function auto_sync_woocommerce_customer( $customer_id ) {
			if ( ! wp_next_scheduled( 'wkwctobag_delayed_customer_sync', array( $customer_id ) ) ) {
				wp_schedule_single_event( time(), 'wkwctobag_delayed_customer_sync', array( $customer_id ) );
			}
		}

		/**
		 * Delayed handler for WooCommerce customer auto-sync.
		 *
		 * @param int $customer_id Customer (user) ID.
		 */
		public function auto_sync_woocommerce_customer_delayed( $customer_id ) {
			$this->auto_sync_user_or_customer( intval( $customer_id ) );
		}

		/**
		 * Unified method to sync registered user or customer.
		 *
		 * @param int $user_id User ID.
		 */
		public function auto_sync_user_or_customer( $user_id ) {
			static $synced = array();
			if ( in_array( $user_id, $synced, true ) ) {
				return;
			}
			$synced[] = $user_id;

			$user = new \WP_User( $user_id );
			if ( ! $user || 0 === $user->ID ) {
				return;
			}

			if ( in_array( 'customer', $user->roles, true ) ) {
				$customer_settings = $this->helper->get_customer_settings_data();
				if ( ! empty( $customer_settings['auto_sync_customer'] ) && 'enabled' === $customer_settings['auto_sync_customer'] ) {
					$group           = ! empty( $customer_settings['wc_bagisto_customer_group'] ) ? intval( $customer_settings['wc_bagisto_customer_group'] ) : 0;
					$gender          = ! empty( $customer_settings['wc_bagisto_customer_default_gender'] ) ? $customer_settings['wc_bagisto_customer_default_gender'] : 'male';
					$customer_status = ! empty( $customer_settings['wc_bagisto_customer_status'] ) ? $customer_settings['wc_bagisto_customer_status'] : 'active';

					if ( $group > 0 ) {
						$this->customer_sync->sync(
							array(
								'user_id'                 => $user_id,
								'group'                   => $group,
								'gender'                  => $gender,
								'customer_status'         => $customer_status,
								'is_update_existing_user' => 'yes',
							)
						);
					}
				}
			} else {
				$user_settings = $this->helper->get_user_settings_data();
				if ( ! empty( $user_settings['auto_sync_user'] ) && 'enabled' === $user_settings['auto_sync_user'] ) {
					$role_id     = ! empty( $user_settings['wc_bagisto_user_role'] ) ? intval( $user_settings['wc_bagisto_user_role'] ) : 0;
					$user_status = ! empty( $user_settings['wc_bagisto_user_status'] ) ? $user_settings['wc_bagisto_user_status'] : 'active';

					if ( $role_id > 0 ) {
						$this->user_sync->sync(
							array(
								'user_id'                 => $user_id,
								'role'                    => $role_id,
								'user_status'             => $user_status,
								'is_update_existing_user' => 'yes',
							)
						);
					}
				}
			}
		}

		/**
		 * Automatically sync a new order placed from the WooCommerce storefront.
		 *
		 * @param int|\WC_Order $order_or_id WooCommerce order object or order ID.
		 */
		public function auto_sync_storefront_order( $order_or_id ) {
			$order_id = 0;
			if ( $order_or_id instanceof \WC_Order ) {
				$order_id = $order_or_id->get_id();
			} elseif ( is_numeric( $order_or_id ) ) {
				$order_id = intval( $order_or_id );
			}

			if ( $order_id <= 0 ) {
				return;
			}

			$settings = $this->helper->get_order_settings_data();
			if ( empty( $settings['auto_sync_order'] ) || 'enabled' !== $settings['auto_sync_order'] ) {
				return;
			}

			if ( ! wp_next_scheduled( 'wkwctobag_delayed_order_sync', array( $order_id ) ) ) {
				wp_schedule_single_event( time(), 'wkwctobag_delayed_order_sync', array( $order_id ) );
			}
		}

		/**
		 * Delayed handler for WooCommerce storefront order auto-sync.
		 *
		 * @param int $order_id Order ID.
		 */
		public function auto_sync_woocommerce_order_delayed( $order_id ) {
			static $synced_order_ids = array();
			$order_id                = intval( $order_id );
			if ( in_array( $order_id, $synced_order_ids, true ) ) {
				return;
			}
			$synced_order_ids[] = $order_id;

			$order_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Order\Wkwctobag_Sync_Order( $order_id );
			$order_sync->sync();
		}

		/**
		 * Delayed handler for WooCommerce order creation auto-sync.
		 *
		 * @param int $order_id Order ID.
		 */
		public function auto_sync_woocommerce_order_create_delayed( $order_id ) {
			static $synced_order_ids = array();
			$order_id                = intval( $order_id );
			if ( in_array( $order_id, $synced_order_ids, true ) ) {
				return;
			}
			$synced_order_ids[] = $order_id;

			$order_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Order\Wkwctobag_Sync_Order( $order_id );
			$order_sync->sync();
		}

		/**
		 * Delayed handler for WooCommerce order status change auto-sync.
		 *
		 * @param int    $order_id   Order ID.
		 * @param string $old_status Old status.
		 * @param string $new_status New status.
		 */
		public function auto_sync_woocommerce_order_status_change_delayed( $order_id, $old_status, $new_status ) {
			static $synced_transitions = array();
			$order_id                  = intval( $order_id );
			$transition_key            = $order_id . '|' . $old_status . '|' . $new_status;
			if ( in_array( $transition_key, $synced_transitions, true ) ) {
				return;
			}
			$synced_transitions[] = $transition_key;

			$order_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Order\Wkwctobag_Sync_Order( $order_id );
			$order_sync->sync();
		}

		/**
		 * Delayed handler for WooCommerce category creation auto-sync.
		 *
		 * @param int $category_id Category (term) ID.
		 */
		public function auto_sync_category_create_delayed( $category_id ) {
			$category_id   = intval( $category_id );
			$category_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Category\Wkwctobag_Sync_Category();
			$category_sync->sync( array( 'category_id' => $category_id ) );
		}

		/**
		 * Delayed handler for WooCommerce category update auto-sync.
		 *
		 * @param int $category_id Category (term) ID.
		 */
		public function auto_sync_category_update_delayed( $category_id ) {
			$category_id   = intval( $category_id );
			$category_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Category\Wkwctobag_Sync_Category();
			$category_sync->sync( array( 'category_id' => $category_id ) );
		}

		/**
		 * Delayed handler for WooCommerce product creation auto-sync.
		 *
		 * @param int $product_id Product ID.
		 */
		public function auto_sync_product_create_delayed( $product_id ) {
			$product_id = intval( $product_id );
			if ( ! defined( 'WKWCTOBAG_SYNCING_PRODUCT' ) ) {
				define( 'WKWCTOBAG_SYNCING_PRODUCT', true );
				$product_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Product\Wkwctobag_Sync_Product();
				$product_sync->sync( array( 'product_id' => $product_id ) );
			}
		}

		/**
		 * Delayed handler for WooCommerce product update auto-sync.
		 *
		 * @param int $product_id Product ID.
		 */
		public function auto_sync_product_update_delayed( $product_id ) {
			$product_id = intval( $product_id );
			if ( ! defined( 'WKWCTOBAG_SYNCING_PRODUCT' ) ) {
				define( 'WKWCTOBAG_SYNCING_PRODUCT', true );
				$product_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Product\Wkwctobag_Sync_Product();
				$product_sync->sync( array( 'product_id' => $product_id ) );
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
