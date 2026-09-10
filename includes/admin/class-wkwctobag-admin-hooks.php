<?php
/**
 * Admin hooks handler class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Includes\Admin;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

if ( ! class_exists( 'Wkwctobag_Admin_Hooks' ) ) {
	/**
	 * Admin hooks handler class.
	 */
	class Wkwctobag_Admin_Hooks {
		/**
		 * Instance of this class.
		 *
		 * @var Wkwctobag_Admin_Hooks $instance Instance of this class.
		 */
		protected static $instance = null;

		/**
		 * Constructor of the class.
		 */
		public function __construct() {
			$function_handler = Wkwctobag_Admin_Functions::get_instance();
			add_action( 'admin_enqueue_scripts', array( $function_handler, 'wkwctobag_enqueue_admin_scripts' ) );
			add_action( 'admin_menu', array( $function_handler, 'wkwctobag_register_admin_menu' ) );
			add_action( 'admin_init', array( $function_handler, 'wkwctobag_download_log' ) );
			add_action( 'admin_notices', array( $function_handler, 'wkwctobag_show_expired_token_notice' ) );
			add_action( 'add_meta_boxes', array( $function_handler, 'wkwctobag_register_order_metabox' ) );

			add_action( 'wp_ajax_wkwctobag_get_searched_users', array( $function_handler, 'get_searched_users' ) );

			add_action( 'wp_ajax_wkwctobag_get_searched_products', array( $function_handler, 'get_searched_products' ) );
			add_action( 'wp_ajax_wkwctobag_get_searched_categories', array( $function_handler, 'get_searched_categories' ) );
			add_action( 'wp_ajax_wkwctobag_get_searched_bagisto_customers', array( $function_handler, 'get_searched_bagisto_customers' ) );

			add_action( 'wp_ajax_wkwctobag_bulk_user_sync', array( $function_handler, 'bulk_user_sync' ) );
			add_action( 'wp_ajax_wkwctobag_bulk_category_sync', array( $function_handler, 'bulk_category_sync' ) );
			add_action( 'wp_ajax_wkwctobag_bulk_product_sync', array( $function_handler, 'bulk_product_sync' ) );
			add_action( 'wp_ajax_wkwctobag_bulk_attribute_sync', array( $function_handler, 'bulk_attribute_sync' ) );
			add_action( 'wp_ajax_wkwctobag_bulk_tax_sync', array( $function_handler, 'bulk_tax_sync' ) );
			add_action( 'wp_ajax_wkwctobag_bulk_order_sync', array( $function_handler, 'bulk_order_sync' ) );
			add_action( 'wp_ajax_wkwctobag_check_sync_status', array( $function_handler, 'check_sync_status' ) );
			add_action( 'wp_ajax_wkwctobag_test_connection', array( $function_handler, 'test_connection' ) );

			// Settings tabs hooks.
			add_action( 'wkwctobag_settings_tab_order', array( $function_handler, 'render_order_settings_tab' ) );
			add_action( 'wkwctobag_settings_tab_user', array( $function_handler, 'render_user_settings_tab' ) );
			add_action( 'wkwctobag_settings_tab_customer', array( $function_handler, 'render_customer_settings_tab' ) );
			add_action( 'wkwctobag_settings_tab_product', array( $function_handler, 'render_product_settings_tab' ) );
			add_action( 'wkwctobag_settings_tab_tax', array( $function_handler, 'render_tax_settings_tab' ) );

			add_action( 'created_product_cat', array( $function_handler, 'auto_sync_category_create' ), 10, 2 );
			add_action( 'edited_product_cat', array( $function_handler, 'auto_sync_category_update' ), 10, 2 );
			add_action( 'woocommerce_new_product', array( $function_handler, 'auto_sync_product_create' ), 10, 1 );
			add_action( 'woocommerce_update_product', array( $function_handler, 'auto_sync_product_update' ), 10, 1 );
			add_action( 'woocommerce_attribute_added', array( $function_handler, 'auto_sync_attribute_create' ), 10, 2 );
			add_action( 'woocommerce_attribute_updated', array( $function_handler, 'auto_sync_attribute_update' ), 10, 3 );
			add_action( 'created_term', array( $function_handler, 'auto_sync_attribute_term_create' ), 10, 3 );
			add_action( 'edited_term', array( $function_handler, 'auto_sync_attribute_term_update' ), 10, 3 );
			add_action( 'woocommerce_tax_rate_added', array( $function_handler, 'auto_sync_tax' ), 10, 2 );
			add_action( 'woocommerce_tax_rate_updated', array( $function_handler, 'auto_sync_tax' ), 10, 2 );
			add_action( 'woocommerce_tax_rate_deleted', array( $function_handler, 'auto_sync_tax_delete' ), 10, 1 );

			add_action( 'profile_update', array( $function_handler, 'auto_sync_user_profile_update' ), 10, 2 );
			add_action( 'personal_options_update', array( $function_handler, 'auto_sync_user_profile_update' ), 10, 1 );
			add_action( 'edit_user_profile_update', array( $function_handler, 'auto_sync_user_profile_update' ), 10, 1 );
			add_action( 'woocommerce_new_order', array( $function_handler, 'auto_sync_order_create' ), 10, 1 );
			add_action( 'woocommerce_order_status_changed', array( $function_handler, 'auto_sync_order_status_change' ), 10, 3 );

			add_filter( 'bulk_actions-users', array( $function_handler, 'wkwctobag_add_users_bulk_action' ) );
			add_action( 'admin_footer', array( $function_handler, 'wkwctobag_render_users_sync_modal' ) );
			add_action( 'wp_ajax_wkwctobag_selected_users_sync', array( $function_handler, 'wkwctobag_selected_users_sync' ) );
			add_filter( 'manage_users_columns', array( $function_handler, 'wkwctobag_add_users_columns' ) );
			add_filter( 'manage_users_custom_column', array( $function_handler, 'wkwctobag_render_users_column_content' ), 10, 3 );

			add_filter( 'bulk_actions-edit-product_cat', array( $function_handler, 'wkwctobag_add_categories_bulk_action' ) );
			add_action( 'admin_footer', array( $function_handler, 'wkwctobag_render_categories_sync_modal' ) );
			add_action( 'wp_ajax_wkwctobag_selected_categories_sync', array( $function_handler, 'wkwctobag_selected_categories_sync' ) );
			add_filter( 'manage_edit-product_cat_columns', array( $function_handler, 'wkwctobag_add_category_columns' ) );
			add_action( 'manage_product_cat_custom_column', array( $function_handler, 'wkwctobag_render_category_column_content' ), 10, 3 );

			add_filter( 'bulk_actions-edit-product', array( $function_handler, 'wkwctobag_add_products_bulk_action' ) );
			add_action( 'admin_footer', array( $function_handler, 'wkwctobag_render_products_sync_modal' ) );
			add_action( 'wp_ajax_wkwctobag_selected_products_sync', array( $function_handler, 'wkwctobag_selected_products_sync' ) );
			add_filter( 'manage_edit-product_columns', array( $function_handler, 'wkwctobag_add_product_columns' ) );
			add_action( 'manage_product_posts_custom_column', array( $function_handler, 'wkwctobag_render_product_column_content' ), 10, 2 );
			add_action( 'admin_footer', array( $function_handler, 'wkwctobag_render_attributes_sync_modal' ) );
			add_action( 'wp_ajax_wkwctobag_selected_attributes_sync', array( $function_handler, 'wkwctobag_selected_attributes_sync' ) );

			add_filter( 'bulk_actions-edit-shop_order', array( $function_handler, 'wkwctobag_add_orders_bulk_action' ) );
			add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $function_handler, 'wkwctobag_add_orders_bulk_action' ) );
			add_action( 'admin_footer', array( $function_handler, 'wkwctobag_render_orders_sync_modal' ) );
			add_action( 'wp_ajax_wkwctobag_selected_orders_sync', array( $function_handler, 'wkwctobag_selected_orders_sync' ) );
			add_filter( 'manage_edit-shop_order_columns', array( $function_handler, 'wkwctobag_add_order_columns' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( $function_handler, 'wkwctobag_render_order_column_content' ), 10, 2 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $function_handler, 'wkwctobag_add_order_columns' ) );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $function_handler, 'wkwctobag_render_order_column_content' ), 10, 2 );
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
