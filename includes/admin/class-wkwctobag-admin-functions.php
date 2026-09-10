<?php
/**
 * Admin functions handler class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Includes\Admin;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

use WKWCBAGCONNECTOR\Templates\Admin;
use WKWCBAGCONNECTOR\Helper;
use WKWCBAGCONNECTOR\Helper\Sync\Customer;
use WKWCBAGCONNECTOR\Helper\Sync\User;
use WKWCBAGCONNECTOR\Helper\Background_Process;

if ( ! class_exists( 'Wkwctobag_Admin_Functions' ) ) {
	/**
	 * Admin functions handler class.
	 */
	class Wkwctobag_Admin_Functions {
		/**
		 * Instance of this class.
		 *
		 * @var Wkwctobag_Admin_Functions $instance Instance of this class.
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
		 * Background processes instances.
		 *
		 * @var Background_Process\Wkwctobag_Background_Process[] $background_processes Background processes instances.
		 */
		private $background_processes = array();


		/**
		 * Constructor of the class.
		 *
		 * @return void
		 */
		public function __construct() {
			$this->helper        = Helper\Wkwctobag_Helper::get_instance();
			$this->user_sync     = new User\Wkwctobag_Sync_User();
			$this->customer_sync = new Customer\Wkwctobag_Sync_Customer();

			$types = array( 'product', 'category', 'order', 'customer', 'user', 'attribute', 'tax' );
			foreach ( $types as $type ) {
				$this->background_processes[ $type ] = $this->helper->get_background_process( $type );
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

		/**
		 * Enqueue the admin stylesheet and script on the screens this plugin uses.
		 *
		 * @return void
		 */
		public function wkwctobag_enqueue_admin_scripts() {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page                = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$is_connector_page   = ( 0 === strpos( $page, 'wkwctobag-' ) );
			$is_users_page       = ( 'users' === $screen->base );
			$is_product_cat_page = ( 'edit-tags' === $screen->base && 'product_cat' === $screen->taxonomy );
			$is_products_page    = ( 'edit' === $screen->base && 'product' === $screen->post_type );
			$is_attributes_page  = ( 'product_attributes' === $page );
			$is_orders_page      = ( ( 'edit' === $screen->base && 'shop_order' === $screen->post_type ) || ( 'woocommerce_page_wc-orders' === $screen->id || 'woocommerce_page_wc-orders' === $screen->base ) );

			if ( ! $is_connector_page && ! $is_users_page && ! $is_product_cat_page && ! $is_products_page && ! $is_attributes_page && ! $is_orders_page ) {
				return;
			}

			$dependencies = array( 'jquery' );
			if ( wp_script_is( 'select2', 'registered' ) ) {
				$dependencies[] = 'select2';
			}
			wp_enqueue_style( 'wkwctobag-admin-style', WKWCTOBAG_PLUGIN_URL . 'assets/css/admin.css', array(), WKWCTOBAG_SCRIPT_VERSION, false );
			wp_enqueue_script( 'wkwctobag-admin-script', WKWCTOBAG_PLUGIN_URL . 'assets/js/admin.js', $dependencies, WKWCTOBAG_SCRIPT_VERSION, false );

			$user_settings     = $this->helper->get_user_settings_data();
			$customer_settings = $this->helper->get_customer_settings_data();

			$bagisto_roles = get_transient( 'wkwctobag_bagisto_roles_cache' );
			if ( false === $bagisto_roles ) {
				$bagisto_roles = array();
				if ( $this->helper->is_connected_with_bagisto() ) {
					$roles_response = $this->user_sync->get_bagisto_user_roles();
					if ( ! empty( $roles_response['success'] ) && ! empty( $roles_response['data'] ) ) {
						$bagisto_roles = $roles_response['data'];
						set_transient( 'wkwctobag_bagisto_roles_cache', $bagisto_roles, 10 * MINUTE_IN_SECONDS );
					}
				}
			}

			$bagisto_groups = get_transient( 'wkwctobag_bagisto_groups_cache' );
			if ( false === $bagisto_groups ) {
				$bagisto_groups = array();
				if ( $this->helper->is_connected_with_bagisto() ) {
					$groups_response = $this->customer_sync->get_bagisto_customer_groups();
					if ( ! empty( $groups_response['success'] ) && ! empty( $groups_response['data'] ) ) {
						$bagisto_groups = $groups_response['data'];
						set_transient( 'wkwctobag_bagisto_groups_cache', $bagisto_groups, 10 * MINUTE_IN_SECONDS );
					}
				}
			}

			$bagisto_categories = get_transient( 'wkwctobag_bagisto_categories_cache' );
			$category_mappings  = array();
			if ( $this->helper->is_connected_with_bagisto() ) {
				if ( false === $bagisto_categories ) {
					$bagisto_categories  = array();
					$category_sync       = new \WKWCBAGCONNECTOR\Helper\Sync\Category\Wkwctobag_Sync_Category();
					$categories_response = $category_sync->get_bagisto_product_category();
					if ( ! empty( $categories_response['success'] ) && ! empty( $categories_response['data'] ) ) {
						$bagisto_categories = $categories_response['data'];
						set_transient( 'wkwctobag_bagisto_categories_cache', $bagisto_categories, 10 * MINUTE_IN_SECONDS );
					}
				}

				$wc_categories = $this->helper->get_wc_categories();
				if ( ! empty( $wc_categories ) ) {
					foreach ( $wc_categories as $cat ) {
						$bagisto_id = $this->helper->get_bagisto_category_id( $cat['id'] );
						if ( ! empty( $bagisto_id ) ) {
							$category_mappings[ $cat['id'] ] = $bagisto_id;
						}
					}
				}
			}

			if ( ! is_array( $bagisto_categories ) ) {
				$bagisto_categories = array();
			}

			$sync_completed = get_transient( 'wkwctobag_sync_just_completed' );

			wp_localize_script(
				'wkwctobag-admin-script',
				'wkwctobagAdminObj',
				array(
					'admin_url'               => admin_url( 'admin.php' ),
					'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
					'nonce'                   => wp_create_nonce( 'wkwctobag_admin_ajax_nonce' ),
					'is_queue_running'        => $this->is_any_queue_running(),
					'sync_just_completed'     => ! empty( $sync_completed ),
					'sync_type'               => is_string( $sync_completed ) ? $sync_completed : get_option( 'wkwctobag_active_sync_type', '' ),
					'bagisto_roles'           => $bagisto_roles,
					'bagisto_groups'          => $bagisto_groups,
					'bagisto_categories'      => $bagisto_categories,
					'category_mappings'       => $category_mappings,
					'default_user_role'       => isset( $user_settings['wc_bagisto_user_role'] ) ? intval( $user_settings['wc_bagisto_user_role'] ) : 0,
					'default_user_status'     => isset( $user_settings['wc_bagisto_user_status'] ) ? $user_settings['wc_bagisto_user_status'] : 'active',
					'default_customer_group'  => isset( $customer_settings['wc_bagisto_customer_group'] ) ? intval( $customer_settings['wc_bagisto_customer_group'] ) : 0,
					'default_customer_gender' => isset( $customer_settings['wc_bagisto_customer_default_gender'] ) ? $customer_settings['wc_bagisto_customer_default_gender'] : 'male',
					'default_customer_status' => isset( $customer_settings['wc_bagisto_customer_status'] ) ? $customer_settings['wc_bagisto_customer_status'] : 'active',
					'i18n'                    => array(
						'sync_completed'           => esc_html__( 'Synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'user_sync_completed'      => esc_html__( 'User synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'customer_sync_completed'  => esc_html__( 'Customer synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'product_sync_completed'   => esc_html__( 'Product synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'category_sync_completed'  => esc_html__( 'Category synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'attribute_sync_completed' => esc_html__( 'Attribute synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'sync_error'               => esc_html__( 'An error occurred during synchronization.', 'bagisto-sync-for-woocommerce' ),
						'sync_in_progress'         => esc_html__( 'Another synchronization is already in progress. Please wait.', 'bagisto-sync-for-woocommerce' ),
						'btn_syncing'              => esc_html__( 'Syncing...', 'bagisto-sync-for-woocommerce' ),
						'syncing_users'            => esc_html__( 'Synchronizing users to Bagisto...', 'bagisto-sync-for-woocommerce' ),
						'syncing_customers'        => esc_html__( 'Synchronizing customers to Bagisto...', 'bagisto-sync-for-woocommerce' ),
						'syncing_products'         => esc_html__( 'Synchronizing products to Bagisto...', 'bagisto-sync-for-woocommerce' ),
						'syncing_categories'       => esc_html__( 'Synchronizing categories to Bagisto...', 'bagisto-sync-for-woocommerce' ),
						'order_sync_completed'     => esc_html__( 'Order synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'syncing_orders'           => esc_html__( 'Synchronizing orders to Bagisto...', 'bagisto-sync-for-woocommerce' ),
						'tax_sync_completed'       => esc_html__( 'Tax synchronization completed successfully.', 'bagisto-sync-for-woocommerce' ),
						'syncing_taxes'            => esc_html__( 'Synchronizing taxes to Bagisto...', 'bagisto-sync-for-woocommerce' ),
					),
					'attribute_mappings'      => get_option( 'wkwctobag_attribute_mappings', array() ),
				)
			);

			if ( ! empty( $sync_completed ) ) {
				delete_transient( 'wkwctobag_sync_just_completed' );
			}
		}

		/**
		 * Register admin menu and submenus.
		 */
		public function wkwctobag_register_admin_menu() {
			$capability = apply_filters( 'wkwctobag_modify_capabilities', 'manage_options' );
			add_menu_page(
				esc_html__( 'WC to Bagisto', 'bagisto-sync-for-woocommerce' ),
				esc_html__( 'WC to Bagisto', 'bagisto-sync-for-woocommerce' ),
				$capability,
				'wkwctobag-configuration',
				array( $this, 'wkwctobag_connection_page_callback' ),
				WKWCTOBAG_PLUGIN_URL . 'assets/images/top-logo.svg',
				50
			);

			add_submenu_page(
				'wkwctobag-configuration',
				esc_html__( 'Connection', 'bagisto-sync-for-woocommerce' ),
				esc_html__( 'Connection', 'bagisto-sync-for-woocommerce' ),
				$capability,
				'wkwctobag-configuration',
				array( $this, 'wkwctobag_connection_page_callback' )
			);

			add_submenu_page(
				'wkwctobag-configuration',
				esc_html__( 'Settings', 'bagisto-sync-for-woocommerce' ),
				esc_html__( 'Settings', 'bagisto-sync-for-woocommerce' ),
				$capability,
				'wkwctobag-settings',
				array( $this, 'wkwctobag_settings_page_callback' )
			);

			add_submenu_page(
				'wkwctobag-configuration',
				esc_html__( 'Bulk Sync', 'bagisto-sync-for-woocommerce' ),
				esc_html__( 'Bulk Sync', 'bagisto-sync-for-woocommerce' ),
				$capability,
				'wkwctobag-bulk-sync',
				array( $this, 'wkwctobag_bulk_sync_page_callback' )
			);

			add_submenu_page(
				'wkwctobag-configuration',
				esc_html__( 'Logs', 'bagisto-sync-for-woocommerce' ),
				esc_html__( 'Logs', 'bagisto-sync-for-woocommerce' ),
				$capability,
				'wkwctobag-logs',
				array( $this, 'wkwctobag_logs_page_callback' )
			);
		}

		/**
		 * Create connection.
		 */
		public function wkwctobag_connection_page_callback() {
			new Admin\Wkwctobag_Connection();
		}

		/**
		 * Settings page callback.
		 */
		public function wkwctobag_settings_page_callback() {
			new Admin\Settings\Wkwctobag_Settings();
		}

		/**
		 * Bulk sync page callback.
		 */
		public function wkwctobag_bulk_sync_page_callback() {
			new Admin\Wkwctobag_Bulk_Sync();
		}

		/**
		 * Logs page callback.
		 */
		public function wkwctobag_logs_page_callback() {
			new Admin\Wkwctobag_Logs();
		}

		/**
		 * Render user settings tab.
		 */
		public function render_user_settings_tab() {
			new Admin\Settings\Tabs\Wkwctobag_User_Settings();
		}

		/**
		 * Render order settings tab.
		 */
		public function render_order_settings_tab() {
			new Admin\Settings\Tabs\Wkwctobag_Order_Settings();
		}

		/**
		 * Render customer settings tab.
		 */
		public function render_customer_settings_tab() {
			new Admin\Settings\Tabs\Wkwctobag_Customer_Settings();
		}

		/**
		 * Render product settings tab.
		 */
		public function render_product_settings_tab() {
			new Admin\Settings\Tabs\Wkwctobag_Product_Settings();
		}

		/**
		 * Render tax settings tab.
		 */
		public function render_tax_settings_tab() {
			new Admin\Settings\Tabs\Wkwctobag_Tax_Settings();
		}

		/**
		 * Get searched users.
		 *
		 * @throws \Exception If the security or capability check fails, or the search string is empty.
		 */
		public function get_searched_users() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				$search_query = ! empty( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
				$exclude_role = ! empty( $_POST['exclude_role'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['exclude_role'] ) ) : array();
				$include_role = ! empty( $_POST['include_role'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['include_role'] ) ) : array();

				if ( empty( $search_query ) ) {
					throw new \Exception( esc_html__( 'Search string can not be empty.', 'bagisto-sync-for-woocommerce' ) );
				}

				$users = $this->helper->get_users(
					$search_query,
					array(
						'exclude_role' => $exclude_role,
						'include_role' => $include_role,
					)
				);

				if ( ! empty( $users ) ) {
					foreach ( $users as $user ) {
						$response['data'][] = array(
							'id'           => $user->ID,
							'email'        => $user->user_email,
							'display_name' => $user->display_name,
							'text'         => sprintf( '%s ( %s )', esc_html( $user->display_name ), esc_html( $user->user_email ) ),
						);
					}
				}

				$response['message'] = esc_html__( 'User fetched sucessfully.', 'bagisto-sync-for-woocommerce' );
				$response['success'] = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Get searched Bagisto customers.
		 *
		 * @throws \Exception If the security or capability check fails.
		 */
		public function get_searched_bagisto_customers() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				$search_query = ! empty( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

				$customers = array();
				if ( ! empty( $search_query ) ) {
					$url_name = $this->helper->get_bagisto_site_url() . '/api/admin/customers?per_page=20&name=' . urlencode( $search_query );
					$res_name = json_decode( $this->helper->bagisto_curl_request( $url_name, 'GET' ), true );
					if ( ! empty( $res_name['data'] ) && is_array( $res_name['data'] ) ) {
						foreach ( $res_name['data'] as $cust ) {
							if ( ! empty( $cust['id'] ) ) {
								$customers[ $cust['id'] ] = $cust;
							}
						}
					}

					$url_email = $this->helper->get_bagisto_site_url() . '/api/admin/customers?per_page=20&email=' . urlencode( $search_query );
					$res_email = json_decode( $this->helper->bagisto_curl_request( $url_email, 'GET' ), true );
					if ( ! empty( $res_email['data'] ) && is_array( $res_email['data'] ) ) {
						foreach ( $res_email['data'] as $cust ) {
							if ( ! empty( $cust['id'] ) ) {
								$customers[ $cust['id'] ] = $cust;
							}
						}
					}
				} else {
					$url = $this->helper->get_bagisto_site_url() . '/api/admin/customers?per_page=20';
					$res = json_decode( $this->helper->bagisto_curl_request( $url, 'GET' ), true );
					if ( ! empty( $res['data'] ) && is_array( $res['data'] ) ) {
						foreach ( $res['data'] as $cust ) {
							if ( ! empty( $cust['id'] ) ) {
								$customers[ $cust['id'] ] = $cust;
							}
						}
					}
				}

				if ( ! empty( $customers ) ) {
					foreach ( $customers as $customer ) {
						$name = '';
						if ( ! empty( $customer['name'] ) ) {
							$name = $customer['name'];
						} else {
							$first_name = ! empty( $customer['first_name'] ) ? $customer['first_name'] : ( ! empty( $customer['firstName'] ) ? $customer['firstName'] : '' );
							$last_name  = ! empty( $customer['last_name'] ) ? $customer['last_name'] : ( ! empty( $customer['lastName'] ) ? $customer['lastName'] : '' );
							$name       = trim( $first_name . ' ' . $last_name );
						}
						if ( empty( $name ) ) {
							$name = esc_html__( 'Guest', 'bagisto-sync-for-woocommerce' );
						}
						$response['data'][] = array(
							'id'   => intval( $customer['id'] ),
							'text' => sprintf( '%s ( %s )', esc_html( $name ), esc_html( $customer['email'] ) ),
						);
					}
				}

				$response['message'] = esc_html__( 'Customers fetched successfully.', 'bagisto-sync-for-woocommerce' );
				$response['success'] = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Get searched products.
		 *
		 * Sends the product search response as JSON and halts.
		 *
		 * @throws \Exception If the security or capability check fails, or the search string is empty.
		 *
		 * @return void
		 */
		public function get_searched_products() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				$search_query = ! empty( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

				if ( empty( $search_query ) ) {
					throw new \Exception( esc_html__( 'Search string can not be empty.', 'bagisto-sync-for-woocommerce' ) );
				}

				$products = $this->helper->get_products( $search_query );

				if ( ! empty( $products ) ) {
					foreach ( $products as $product ) {

						if ( ! $product instanceof \WC_Product ) {
							continue;
						}

						$response['data'][] = array(
							'id'   => $product->get_id(),
							'name' => $product->get_name(),
							'sku'  => $product->get_sku(),
							'text' => sprintf( '%s %s', esc_html( $product->get_name() ), ! empty( $product->get_sku() ) ? esc_html( '(' . $product->get_sku() . ')' ) : '' ),
						);
					}
				}

				$response['message'] = esc_html__( 'Product fetched sucessfully.', 'bagisto-sync-for-woocommerce' );
				$response['success'] = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Get searched categories.
		 *
		 * Sends the category search response as JSON and halts.
		 *
		 * @throws \Exception If the security or capability check fails, or the search string is empty.
		 *
		 * @return void
		 */
		public function get_searched_categories() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				$search_query = ! empty( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

				if ( empty( $search_query ) ) {
					throw new \Exception( esc_html__( 'Search string can not be empty.', 'bagisto-sync-for-woocommerce' ) );
				}

				$categories = $this->helper->get_categories( $search_query );

				if ( ! empty( $categories ) ) {
					foreach ( $categories as $category ) {

						if ( ! $category instanceof \WP_Term ) {
							continue;
						}

						$response['data'][] = array(
							'id'   => $category->term_id,
							'name' => $category->name,
							'sku'  => $category->slug,
							'text' => sprintf( '%s %s', esc_html( $category->name ), ! empty( $category->slug ) ? esc_html( '(' . $category->slug . ')' ) : '' ),
						);
					}
				}

				$response['message'] = esc_html__( 'Category fetched successfully.', 'bagisto-sync-for-woocommerce' );
				$response['success'] = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Bulk user sync.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function bulk_user_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				$exclude_users_raw = isset( $_POST['exclude_users'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_users'] ) ) : '';
				$exclude_users     = array_filter( explode( ',', $exclude_users_raw ) );

				$is_update_existing_customers = isset( $_POST['update_existing_users'] ) && 'yes' === $_POST['update_existing_users'] ? 'yes' : 'no';

				$exclude_role = isset( $_POST['exclude_role'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_role'] ) ) : '';
				$exclude_role = array_filter( explode( ',', $exclude_role ) );

				$include_role = isset( $_POST['include_role'] ) ? sanitize_text_field( wp_unslash( $_POST['include_role'] ) ) : '';
				$include_role = array_filter( explode( ',', $include_role ) );

				$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'user';

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => $type ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				$setting_data = array();

				if ( 'user' === $type ) {
					$setting_data = $this->helper->get_user_settings_data();
					if ( empty( $setting_data['wc_bagisto_user_role'] ) ) {
						throw new \Exception( esc_html__( 'Please set default Bagisto user role in settings before bulk sync.', 'bagisto-sync-for-woocommerce' ) );
					}

					$user_roles = $this->user_sync->get_bagisto_user_roles();

					if ( ! $user_roles['success'] ) {
						throw new \Exception( $user_roles['message'] );
					}

					if ( empty( $user_roles['data'] ) ) {
						throw new \Exception( esc_html__( 'No user roles created in the Bagisto. First create the roles in Bagisto and set the default user role in the settings.', 'bagisto-sync-for-woocommerce' ) );
					}

					$matched_user_role = array_filter(
						$user_roles['data'],
						function ( $role ) use ( $setting_data ) {
							return intval( $role['id'] ) === intval( $setting_data['wc_bagisto_user_role'] );
						}
					);

					if ( empty( $matched_user_role ) ) {
						throw new \Exception( esc_html__( 'The selected default user role in settings does not exist in Bagisto. Please select a valid user role.', 'bagisto-sync-for-woocommerce' ) );
					}
				}

				if ( 'customer' === $type ) {
					$setting_data = $this->helper->get_customer_settings_data();
					if ( empty( $setting_data['wc_bagisto_customer_group'] ) ) {
						throw new \Exception( esc_html__( 'Please set default Bagisto customer group in settings before bulk sync.', 'bagisto-sync-for-woocommerce' ) );
					}

					$customer_groups = $this->customer_sync->get_bagisto_customer_groups();

					if ( ! $customer_groups['success'] ) {
						throw new \Exception( $customer_groups['message'] );
					}

					if ( empty( $customer_groups['data'] ) ) {
						throw new \Exception( esc_html__( 'No customer groups created in the Bagisto. First create the groups in Bagisto and set the default customer group in the settings.', 'bagisto-sync-for-woocommerce' ) );
					}

					$matched_customer_group = array_filter(
						$customer_groups['data'],
						function ( $group ) use ( $setting_data ) {
							return intval( $group['id'] ) === intval( $setting_data['wc_bagisto_customer_group'] );
						}
					);

					if ( empty( $matched_customer_group ) ) {
						throw new \Exception( esc_html__( 'The selected default customer group in settings does not exist in Bagisto. Please select a valid customer group.', 'bagisto-sync-for-woocommerce' ) );
					}
				}

				$users = $this->helper->get_users(
					'',
					array(
						'exclude_role' => $exclude_role,
						'include_role' => $include_role,
						'exclude_ids'  => $exclude_users,
						'fields'       => array( 'ID' ),
					)
				);

				if ( empty( $users ) ) {
					$msg = 'user' === $type ? esc_html__( 'No users found for sync.', 'bagisto-sync-for-woocommerce' ) : esc_html__( 'No customers found for sync.', 'bagisto-sync-for-woocommerce' );
					throw new \Exception( $msg );
				}

				foreach ( $users as $key => $user ) {
					$data = array(
						'type'                    => $type,
						'user_id'                 => $user->ID,
						'is_update_existing_user' => $is_update_existing_customers,
					);

					if ( 'user' === $type ) {
						$data['role']        = intval( $setting_data['wc_bagisto_user_role'] );
						$data['user_status'] = 'inactive' === $setting_data['wc_bagisto_user_status'] ? 'inactive' : 'active';
					}

					if ( 'customer' === $type ) {
						$data['group']           = intval( $setting_data['wc_bagisto_customer_group'] );
						$data['gender']          = in_array( $setting_data['wc_bagisto_customer_default_gender'], array( 'male', 'female', 'other' ), true ) ? $setting_data['wc_bagisto_customer_default_gender'] : 'male';
						$data['customer_status'] = isset( $setting_data['wc_bagisto_customer_status'] ) && 'inactive' === $setting_data['wc_bagisto_customer_status'] ? 'inactive' : 'active';
					}

					$this->background_processes[ $type ]->push_to_queue( $data );
				}

				$this->background_processes[ $type ]->save();
				$this->update_active_sync_metadata( $type, count( $users ) );
				$this->background_processes[ $type ]->dispatch();

				$response['total_items'] = count( $users );
				$response['message']     = esc_html__( 'Bulk sync initiated successfully in the background. Please check the log for the report.', 'bagisto-sync-for-woocommerce' );
				$response['success']     = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Bulk category sync.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function bulk_category_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'category' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				$exclude_categories_raw = isset( $_POST['exclude_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_categories'] ) ) : '';
				$exclude_categories     = array_filter( explode( ',', $exclude_categories_raw ) );

				$is_update_existing_category = isset( $_POST['update_existing_categories'] ) && 'yes' === $_POST['update_existing_categories'] ? 'yes' : 'no';

				$categories = $this->helper->get_categories( '', array( 'exclude_ids' => $exclude_categories ) );

				if ( empty( $categories ) ) {
					throw new \Exception( esc_html__( 'No categories found for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				foreach ( $categories as $category ) {
					$data = array(
						'type'                        => 'category',
						'category_id'                 => $category->term_id,
						'is_update_existing_category' => $is_update_existing_category,
					);
					$this->background_processes['category']->push_to_queue( $data );
				}

				$this->background_processes['category']->save();
				$this->update_active_sync_metadata( 'category', count( $categories ) );
				$this->background_processes['category']->dispatch();

				$response['total_items'] = count( $categories );
				$response['message']     = esc_html__( 'Bulk category sync initiated successfully in the background. Please check the log for the report.', 'bagisto-sync-for-woocommerce' );
				$response['success']     = true;
			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Bulk product sync.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function bulk_product_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'product' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				$exclude_products_raw = isset( $_POST['exclude_products'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_products'] ) ) : '';
				$exclude_products     = array_filter( explode( ',', $exclude_products_raw ) );

				$is_update_existing_products = isset( $_POST['update_existing_products'] ) && 'yes' === $_POST['update_existing_products'] ? 'yes' : 'no';

				$products = $this->helper->get_products(
					'',
					array(
						'exclude_ids' => $exclude_products,
					)
				);

				if ( empty( $products ) ) {
					throw new \Exception( esc_html__( 'No products found for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				foreach ( $products as $product ) {
					$data = array(
						'type'                    => 'product',
						'product_id'              => $product->get_id(),
						'is_update_existing_user' => $is_update_existing_products,
					);

					$this->background_processes['product']->push_to_queue( $data );
				}

				$this->background_processes['product']->save();
				$this->update_active_sync_metadata( 'product', count( $products ) );
				$this->background_processes['product']->dispatch();

				$response['total_items'] = count( $products );
				$response['message']     = esc_html__( 'Bulk product sync initiated successfully in the background. Please check the log for the report.', 'bagisto-sync-for-woocommerce' );
				$response['success']     = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Bulk order sync.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function bulk_order_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'order' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				$exclude_order_status      = isset( $_POST['exclude_order_status'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['exclude_order_status'] ) ) : array();
				$is_update_existing_orders = isset( $_POST['update_existing_orders'] ) && 'yes' === $_POST['update_existing_orders'] ? 'yes' : 'no';

				$all_statuses      = array_keys( wc_get_order_statuses() );
				$statuses_to_query = array();
				foreach ( $all_statuses as $status ) {
					if ( ! in_array( $status, $exclude_order_status, true ) && ! in_array( str_replace( 'wc-', '', $status ), $exclude_order_status, true ) ) {
						$statuses_to_query[] = $status;
					}
				}

				$args = array(
					'type'   => 'shop_order',
					'limit'  => -1,
					'return' => 'ids',
				);

				if ( ! empty( $statuses_to_query ) ) {
					$args['status'] = $statuses_to_query;
				} else {
					throw new \Exception( esc_html__( 'All order statuses are excluded.', 'bagisto-sync-for-woocommerce' ) );
				}

				$order_ids = wc_get_orders( $args );

				if ( empty( $order_ids ) ) {
					throw new \Exception( esc_html__( 'No orders found for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				foreach ( $order_ids as $order_id ) {
					$data = array(
						'type'                    => 'order',
						'order_id'                => $order_id,
						'is_update_existing_user' => $is_update_existing_orders,
					);

					$this->background_processes['order']->push_to_queue( $data );
				}

				$this->background_processes['order']->save();
				$this->update_active_sync_metadata( 'order', count( $order_ids ) );
				$this->background_processes['order']->dispatch();

				$response['total_items'] = count( $order_ids );
				$response['message']     = esc_html__( 'Bulk order sync initiated successfully in the background. Please check the log for the report.', 'bagisto-sync-for-woocommerce' );
				$response['success']     = true;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Bulk attribute sync.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function bulk_attribute_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'attribute' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				$is_update_existing_attributes = isset( $_POST['update_existing_attributes'] ) && 'yes' === $_POST['update_existing_attributes'] ? 'yes' : 'no';

				$attributes = wc_get_attribute_taxonomies();

				if ( empty( $attributes ) ) {
					throw new \Exception( esc_html__( 'No attributes found for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				foreach ( $attributes as $attribute ) {
					$data = array(
						'type'                      => 'attribute',
						'attribute_id'              => $attribute->attribute_id,
						'is_update_existing_attrib' => $is_update_existing_attributes,
					);
					$this->background_processes['attribute']->push_to_queue( $data );
				}

				$this->background_processes['attribute']->save();
				$this->update_active_sync_metadata( 'attribute', count( $attributes ) );
				$this->background_processes['attribute']->dispatch();

				$response['total_items'] = count( $attributes );
				$response['message']     = esc_html__( 'Bulk attribute sync initiated successfully in the background. Please check the log for the report.', 'bagisto-sync-for-woocommerce' );
				$response['success']     = true;
			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Bulk tax sync.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function bulk_tax_sync() {
			global $wpdb;
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please configure connection.', array( 'source' => 'tax' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please configure connection.', 'bagisto-sync-for-woocommerce' ) );
				}

				$update_existing_taxes = isset( $_POST['update_existing_taxes'] ) ? sanitize_text_field( wp_unslash( $_POST['update_existing_taxes'] ) ) : 'yes';

				$tax_classes = array_merge( array( '' ), \WC_Tax::get_tax_classes() );
				$rates       = array();

				foreach ( $tax_classes as $tax_class ) {
					$class_rates = \WC_Tax::get_rates_for_tax_class( $tax_class );
					if ( ! empty( $class_rates ) && is_array( $class_rates ) ) {
						foreach ( $class_rates as $rate_key => $rate_obj ) {
							$rate_id = 0;
							if ( is_array( $rate_obj ) && ! empty( $rate_obj['tax_rate_id'] ) ) {
								$rate_id = intval( $rate_obj['tax_rate_id'] );
							} elseif ( is_object( $rate_obj ) && ! empty( $rate_obj->tax_rate_id ) ) {
								$rate_id = intval( $rate_obj->tax_rate_id );
							} elseif ( is_numeric( $rate_key ) && $rate_key > 0 ) {
								$rate_id = intval( $rate_key );
							}
							if ( $rate_id > 0 ) {
								$rates[] = (object) array( 'tax_rate_id' => $rate_id );
							}
						}
					}
				}

				$total_items = 0;
				if ( ! empty( $rates ) ) {
					foreach ( $rates as $rate ) {
						$data = array(
							'type'                  => 'tax',
							'sub_type'              => 'tax_rate',
							'tax_rate_id'           => intval( $rate->tax_rate_id ),
							'update_existing_taxes' => $update_existing_taxes,
						);
						$this->background_processes['tax']->push_to_queue( $data );
						++$total_items;
					}
				}

				if ( ! empty( $tax_classes ) ) {
					foreach ( $tax_classes as $class ) {
						$data = array(
							'type'                  => 'tax',
							'sub_type'              => 'tax_category',
							'tax_class'             => $class,
							'update_existing_taxes' => $update_existing_taxes,
						);
						$this->background_processes['tax']->push_to_queue( $data );
						++$total_items;
					}
				}

				if ( $total_items > 0 ) {
					$this->background_processes['tax']->save();
					$this->update_active_sync_metadata( 'tax', $total_items );
					$this->background_processes['tax']->dispatch();

					$response['total_items'] = $total_items;
					$response['message']     = esc_html__( 'Bulk tax sync initiated successfully in the background. Please check the log for the report.', 'bagisto-sync-for-woocommerce' );
					$response['success']     = true;
				} else {
					throw new \Exception( esc_html__( 'No tax rates or classes found to sync.', 'bagisto-sync-for-woocommerce' ) );
				}
			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Check if any background process queue is actually running.
		 *
		 * @return bool
		 */
		public function is_any_queue_running() {
			$active_totals = get_option( 'wkwctobag_active_sync_totals', array() );
			if ( empty( $active_totals ) ) {
				return false;
			}

			$is_actually_running = false;
			foreach ( $this->background_processes as $type => $process ) {
				if ( $process->is_queue_running() ) {
					$is_actually_running = true;
					break;
				}
			}

			if ( ! $is_actually_running ) {
				update_option( 'wkwctobag_active_sync_totals', array(), false );
				update_option( 'wkwctobag_active_sync_type', '', false );
				$active_totals = array();
			}

			return ! empty( $active_totals );
		}

		/**
		 * Check sync status.
		 */
		public function check_sync_status() {
			check_ajax_referer( 'wkwctobag_admin_ajax_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) ) );
			}

			$counts           = array();
			$is_queue_running = $this->is_any_queue_running();
			$active_totals    = get_option( 'wkwctobag_active_sync_totals', array() );
			foreach ( $this->background_processes as $type => $process ) {
				$process_counts = $process->get_queued_counts();
				if ( isset( $process_counts[ $type ] ) ) {
					$counts[ $type ] = $process_counts[ $type ];
				}
			}

			$response = array(
				'success'          => true,
				'is_queue_running' => $is_queue_running,
				'counts'           => $counts,
				'initial_totals'   => $active_totals,
				'progress'         => \WKWCBAGCONNECTOR\Helper\Sync\Wkwctobag_Sync_Status::get_all(),
			);

			wp_send_json( $response );
		}

		/**
		 * Update active sync types and totals.
		 *
		 * @param string $type  Sync type.
		 * @param int    $total Total items.
		 */
		private function update_active_sync_metadata( $type, $total ) {
			// Update active types list (comma separated).
			$active_types = get_option( 'wkwctobag_active_sync_type', '' );
			$types_array  = array_filter( explode( ',', $active_types ) );

			if ( ! in_array( $type, $types_array, true ) ) {
				$types_array[] = $type;
				update_option( 'wkwctobag_active_sync_type', implode( ',', $types_array ), false );
			}

			// Update initial totals for progress calculation.
			$totals          = get_option( 'wkwctobag_active_sync_totals', array() );
			$totals[ $type ] = intval( $total );
			update_option( 'wkwctobag_active_sync_totals', $totals, false );

			\WKWCBAGCONNECTOR\Helper\Sync\Wkwctobag_Sync_Status::init( $type, intval( $total ) );
		}

		/**
		 * Automatically sync category with Bagisto when created.
		 *
		 * @param int $term_id Term ID.
		 * @param int $tt_id Term taxonomy ID.
		 */
		public function auto_sync_category_create( $term_id, $tt_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature fixed by the created_product_cat hook.
			$term_id  = intval( $term_id );
			$settings = $this->helper->get_product_settings_data();
			if ( ! empty( $settings['auto_sync_categories'] ) && 'enabled' === $settings['auto_sync_categories'] ) {
				if ( ! wp_next_scheduled( 'wkwctobag_delayed_category_create_sync', array( $term_id ) ) ) {
					wp_schedule_single_event( time(), 'wkwctobag_delayed_category_create_sync', array( $term_id ) );
				}
			}
		}

		/**
		 * Automatically update category with Bagisto when edited.
		 *
		 * @param int $term_id Term ID.
		 * @param int $tt_id Term taxonomy ID.
		 */
		public function auto_sync_category_update( $term_id, $tt_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature fixed by the edited_product_cat hook.
			$term_id  = intval( $term_id );
			$settings = $this->helper->get_product_settings_data();
			if ( ! empty( $settings['update_categories'] ) && 'enabled' === $settings['update_categories'] ) {
				if ( ! wp_next_scheduled( 'wkwctobag_delayed_category_update_sync', array( $term_id ) ) ) {
					wp_schedule_single_event( time(), 'wkwctobag_delayed_category_update_sync', array( $term_id ) );
				}
			}
		}

		/**
		 * Automatically sync product with Bagisto when created.
		 *
		 * @param int $product_id Product ID.
		 */
		public function auto_sync_product_create( $product_id ) {
			$product_id = intval( $product_id );
			$settings   = $this->helper->get_product_settings_data();
			if ( ! empty( $settings['auto_sync_products'] ) && 'enabled' === $settings['auto_sync_products'] ) {
				if ( ! wp_next_scheduled( 'wkwctobag_delayed_product_create_sync', array( $product_id ) ) ) {
					wp_schedule_single_event( time(), 'wkwctobag_delayed_product_create_sync', array( $product_id ) );
				}
			}
		}

		/**
		 * Automatically update product with Bagisto when edited.
		 *
		 * @param int $product_id Product ID.
		 */
		public function auto_sync_product_update( $product_id ) {
			$product_id = intval( $product_id );
			$settings   = $this->helper->get_product_settings_data();
			if ( ! empty( $settings['update_product'] ) && 'enabled' === $settings['update_product'] ) {
				if ( ! wp_next_scheduled( 'wkwctobag_delayed_product_update_sync', array( $product_id ) ) ) {
					wp_schedule_single_event( time(), 'wkwctobag_delayed_product_update_sync', array( $product_id ) );
				}
			}
		}

		/**
		 * Automatically sync attribute with Bagisto when created.
		 *
		 * @param int   $id   Added attribute ID.
		 * @param array $data Attribute data.
		 */
		public function auto_sync_attribute_create( $id, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature fixed by the woocommerce_attribute_added hook.
			$settings = $this->helper->get_product_settings_data();
			if ( ! empty( $settings['auto_sync_attributes'] ) && 'enabled' === $settings['auto_sync_attributes'] ) {
				$attribute_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Attribute\Wkwctobag_Sync_Attribute();
				$attribute_sync->sync( array( 'attribute_id' => $id ) );
			}
		}

		/**
		 * Automatically update attribute with Bagisto when edited.
		 *
		 * @param int    $id       Added attribute ID.
		 * @param array  $data     Attribute data.
		 * @param string $old_slug Attribute old slug.
		 */
		public function auto_sync_attribute_update( $id, $data, $old_slug ) {
			$settings = $this->helper->get_product_settings_data();
			if ( ! empty( $settings['update_attributes'] ) && 'enabled' === $settings['update_attributes'] ) {
				$attribute_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Attribute\Wkwctobag_Sync_Attribute();
				$attribute_sync->sync(
					array(
						'attribute_id'              => $id,
						'is_update_existing_attrib' => 'yes',
						'old_slug'                  => $old_slug,
					)
				);
			}
		}

		/**
		 * Automatically sync attribute with Bagisto when a term (option) is created.
		 *
		 * @param int    $term_id  Term ID.
		 * @param int    $tt_id    Term taxonomy ID.
		 * @param string $taxonomy Taxonomy slug.
		 */
		public function auto_sync_attribute_term_create( $term_id, $tt_id, $taxonomy ) {
			if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
				$attribute_id = wc_attribute_taxonomy_id_by_name( $taxonomy );
				if ( $attribute_id ) {
					$this->auto_sync_attribute_create( $attribute_id, array() );
				}
			}
		}

		/**
		 * Automatically update attribute with Bagisto when a term (option) is edited.
		 *
		 * @param int    $term_id  Term ID.
		 * @param int    $tt_id    Term taxonomy ID.
		 * @param string $taxonomy Taxonomy slug.
		 */
		public function auto_sync_attribute_term_update( $term_id, $tt_id, $taxonomy ) {
			if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
				$attribute_id = wc_attribute_taxonomy_id_by_name( $taxonomy );
				if ( $attribute_id ) {
					$this->auto_sync_attribute_update( $attribute_id, array(), '' );
				}
			}
		}

		/**
		 * Automatically sync tax rate when added or updated.
		 *
		 * @param int   $rate_id Rate ID.
		 * @param array $rate    Rate details.
		 */
		public function auto_sync_tax( $rate_id, $rate = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature fixed by the woocommerce_tax_rate_added/updated hooks.
			$settings = $this->helper->get_tax_settings_data();
			if ( ! empty( $settings['auto_sync_taxes'] ) && 'enabled' === $settings['auto_sync_taxes'] ) {
				$tax_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Tax\Wkwctobag_Sync_Tax();

				$tax_sync->sync(
					array(
						'sub_type'              => 'tax_rate',
						'tax_rate_id'           => $rate_id,
						'update_existing_taxes' => 'yes',
					)
				);

				$tax_rate_data = \WC_Tax::get_tax_rate( $rate_id, ARRAY_A );
				$tax_class     = isset( $tax_rate_data['tax_rate_class'] ) ? $tax_rate_data['tax_rate_class'] : '';

				$tax_sync->sync(
					array(
						'sub_type'              => 'tax_category',
						'tax_class'             => $tax_class,
						'update_existing_taxes' => 'yes',
					)
				);
			}
		}

		/**
		 * Automatically sync taxes when a rate is deleted.
		 *
		 * @param int $rate_id Rate ID.
		 */
		public function auto_sync_tax_delete( $rate_id ) {
			$settings = $this->helper->get_tax_settings_data();
			if ( ! empty( $settings['auto_sync_taxes'] ) && 'enabled' === $settings['auto_sync_taxes'] ) {
				$mappings = get_option( 'wkwctobag_tax_rate_mappings', array() );
				if ( isset( $mappings[ $rate_id ] ) ) {
					unset( $mappings[ $rate_id ] );
					update_option( 'wkwctobag_tax_rate_mappings', $mappings, true );
				}

				$tax_sync    = new \WKWCBAGCONNECTOR\Helper\Sync\Tax\Wkwctobag_Sync_Tax();
				$tax_classes = array_merge( array( '' ), \WC_Tax::get_tax_classes() );

				foreach ( $tax_classes as $class ) {
					$tax_sync->sync(
						array(
							'sub_type'              => 'tax_category',
							'tax_class'             => $class,
							'update_existing_taxes' => 'yes',
						)
					);
				}
			}
		}



		/**
		 * Auto sync user or customer on profile update.
		 *
		 * @param int      $user_id       User ID.
		 * @param \WP_User $old_user_data User's prior data.
		 */
		public function auto_sync_user_profile_update( $user_id, $old_user_data = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature fixed by the profile_update hook.
			if ( doing_action( 'profile_update' ) && is_admin() ) {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ( $screen && in_array( $screen->base, array( 'user-edit', 'profile' ), true ) ) || isset( $_POST['first_name'] ) || isset( $_POST['last_name'] ) ) {
					return;
				}
			}

			static $updating = array();
			if ( in_array( $user_id, $updating, true ) ) {
				return;
			}
			$updating[] = $user_id;

			$user = new \WP_User( $user_id );
			if ( ! $user || 0 === $user->ID ) {
				return;
			}

			if ( in_array( 'customer', $user->roles, true ) ) {
				$bagisto_customer_id = $this->helper->get_bagisto_customer_id( $user_id );
				if ( $bagisto_customer_id ) {
					$customer_settings = $this->helper->get_customer_settings_data();
					if ( ! empty( $customer_settings['update_customer'] ) && 'enabled' === $customer_settings['update_customer'] ) {
						$group           = ! empty( $customer_settings['wc_bagisto_customer_group'] ) ? intval( $customer_settings['wc_bagisto_customer_group'] ) : 0;
						$gender          = ! empty( $customer_settings['wc_bagisto_customer_default_gender'] ) ? $customer_settings['wc_bagisto_customer_default_gender'] : 'male';
						$customer_status = ! empty( $customer_settings['wc_bagisto_customer_status'] ) ? $customer_settings['wc_bagisto_customer_status'] : 'active';

						if ( $group > 0 ) {
							$this->customer_sync->sync(
								array(
									'user_id'         => $user_id,
									'group'           => $group,
									'gender'          => $gender,
									'customer_status' => $customer_status,
									'is_update_existing_user' => 'yes',
								)
							);
						}
					}
				}
			} else {
				$bagisto_user_id = $this->helper->get_bagisto_user_id( $user_id );
				if ( $bagisto_user_id ) {
					$user_settings = $this->helper->get_user_settings_data();
					if ( ! empty( $user_settings['update_user'] ) && 'enabled' === $user_settings['update_user'] ) {
						$role_id     = ! empty( $user_settings['wc_bagisto_user_role'] ) ? intval( $user_settings['wc_bagisto_user_role'] ) : 0;
						$user_status = ! empty( $user_settings['wc_bagisto_user_status'] ) ? $user_settings['wc_bagisto_user_status'] : 'active';

						if ( $role_id > 0 ) {
							$this->user_sync->sync(
								array(
									'user_id'     => $user_id,
									'role'        => $role_id,
									'user_status' => $user_status,
									'is_update_existing_user' => 'yes',
								)
							);
						}
					}
				}
			}
		}



		/**
		 * Automatically sync order with Bagisto when created from WP admin or REST API.
		 *
		 * @param int $order_id Order ID.
		 */
		public function auto_sync_order_create( $order_id ) {
			static $created_order_ids = array();
			$order_id                 = intval( $order_id );

			// Skip storefront checkout requests.
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( is_checkout() || ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST && strpos( $request_uri, '/wc/store/' ) !== false ) ) {
				return;
			}

			// Prevent duplicate scheduling for the same order in the same request.
			if ( in_array( $order_id, $created_order_ids, true ) ) {
				return;
			}
			$created_order_ids[] = $order_id;

			$settings = $this->helper->get_order_settings_data();
			if ( ! empty( $settings['auto_sync_order'] ) && 'enabled' === $settings['auto_sync_order'] ) {
				if ( ! wp_next_scheduled( 'wkwctobag_delayed_order_create_sync', array( $order_id ) ) ) {
					wp_schedule_single_event( time(), 'wkwctobag_delayed_order_create_sync', array( $order_id ) );
				}
			}
		}

		/**
		 * Automatically update order with Bagisto when status changes.
		 *
		 * @param int    $order_id   Order ID.
		 * @param string $old_status Old status.
		 * @param string $new_status New status.
		 */
		public function auto_sync_order_status_change( $order_id, $old_status, $new_status ) {
			static $synced_transitions = array();
			$order_id                  = intval( $order_id );

			$transition_key = $order_id . '|' . $old_status . '|' . $new_status;
			if ( in_array( $transition_key, $synced_transitions, true ) ) {
				return;
			}
			$synced_transitions[] = $transition_key;

			$settings = $this->helper->get_order_settings_data();
			if ( ! empty( $settings['update_order'] ) && 'enabled' === $settings['update_order'] ) {
				$allowed_statuses = ! empty( $settings['allowed_order_status'] ) ? $settings['allowed_order_status'] : array();
				$target_status    = 'wc-' . $new_status;
				if ( empty( $allowed_statuses ) || in_array( $target_status, $allowed_statuses, true ) || in_array( $new_status, $allowed_statuses, true ) ) {
					if ( ! wp_next_scheduled( 'wkwctobag_delayed_order_status_sync', array( $order_id, $old_status, $new_status ) ) ) {
						wp_schedule_single_event( time(), 'wkwctobag_delayed_order_status_sync', array( $order_id, $old_status, $new_status ) );
					}
				}
			}
		}

		/**
		 * Add bulk sync action to users list page.
		 *
		 * @param array $actions Bulk actions.
		 * @return array Bulk actions.
		 */
		public function wkwctobag_add_users_bulk_action( $actions ) {
			$actions['wkwctobag_bulk_sync_users'] = esc_html__( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' );
			return $actions;
		}

		/**
		 * Render users sync configuration modal in users page footer.
		 */
		public function wkwctobag_render_users_sync_modal() {
			$screen = get_current_screen();
			if ( ! $screen || 'users' !== $screen->id ) {
				return;
			}
			?>

			<div id="wkwctobag-user-sync-modal" class="wkwctobag-modal-overlay" style="display: none;">
				<div class="wkwctobag-modal-card wkwctobag-modal-card-large">
					<div class="wkwctobag-modal-loader" style="display: none;">
						<div class="wkwctobag-spinner"></div>
					</div>
					<div class="wkwctobag-modal-header">
						<h3><?php esc_html_e( 'Sync users to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></h3>
						<button type="button" class="wkwctobag-modal-close-btn">&times;</button>
					</div>
					<div class="wkwctobag-modal-body">
					</div>
					<div class="wkwctobag-modal-footer">
						<button type="button" class="wkwctobag-modal-cancel-btn"><?php esc_html_e( 'Cancel', 'bagisto-sync-for-woocommerce' ); ?></button>
						<button type="button" class="wkwctobag-modal-submit-btn"><?php esc_html_e( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Selected users sync handler via AJAX.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function wkwctobag_selected_users_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'user' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$users_data = isset( $_POST['users_data'] ) ? wp_unslash( $_POST['users_data'] ) : array();

				if ( is_string( $users_data ) ) {
					$users_data = map_deep( json_decode( stripslashes( $users_data ), true ), 'sanitize_text_field' );
				}

				if ( empty( $users_data ) || ! is_array( $users_data ) ) {
					throw new \Exception( esc_html__( 'No users selected for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				$user_count     = 0;
				$customer_count = 0;

				foreach ( $users_data as $item ) {
					if ( empty( $item['user_id'] ) ) {
						continue;
					}

					$user_id = absint( $item['user_id'] );
					$user    = new \WP_User( $user_id );

					if ( ! $user || 0 === $user->ID ) {
						continue;
					}

					$sync_as         = isset( $item['sync_as'] ) && 'user' === $item['sync_as'] ? 'user' : 'customer';
					$update_existing = isset( $item['update_existing'] ) && 'yes' === $item['update_existing'] ? 'yes' : 'no';

					if ( 'user' === $sync_as ) {
						$user_role   = isset( $item['role'] ) ? intval( $item['role'] ) : 0;
						$user_status = isset( $item['status'] ) && 'inactive' === $item['status'] ? 'inactive' : 'active';

						$data = array(
							'type'                    => 'user',
							'user_id'                 => $user_id,
							'is_update_existing_user' => $update_existing,
							'role'                    => $user_role,
							'user_status'             => $user_status,
						);
						$this->background_processes['user']->push_to_queue( $data );
						++$user_count;
					} else {
						$customer_group  = isset( $item['group'] ) ? intval( $item['group'] ) : 0;
						$customer_gender = isset( $item['gender'] ) && in_array( $item['gender'], array( 'male', 'female', 'other' ), true ) ? $item['gender'] : 'male';
						$customer_status = isset( $item['status'] ) && 'inactive' === $item['status'] ? 'inactive' : 'active';

						$data = array(
							'type'                    => 'customer',
							'user_id'                 => $user_id,
							'is_update_existing_user' => $update_existing,
							'group'                   => $customer_group,
							'gender'                  => $customer_gender,
							'customer_status'         => $customer_status,
						);
						$this->background_processes['customer']->push_to_queue( $data );
						++$customer_count;
					}
				}

				if ( $user_count > 0 ) {
					$this->background_processes['user']->save();
					$this->update_active_sync_metadata( 'user', $user_count );
					$this->background_processes['user']->dispatch();
				}
				if ( $customer_count > 0 ) {
					$this->background_processes['customer']->save();
					$this->update_active_sync_metadata( 'customer', $customer_count );
					$this->background_processes['customer']->dispatch();
				}

				$response['success'] = true;
				$response['message'] = sprintf(
					/* translators: 1: user count, 2: customer count */
					esc_html__( 'Initiated sync for %1$d users and %2$d customers in the background.', 'bagisto-sync-for-woocommerce' ),
					$user_count,
					$customer_count
				);
				$response['user_count']     = $user_count;
				$response['customer_count'] = $customer_count;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Add bulk sync action to categories list page.
		 *
		 * @param array $actions Bulk actions.
		 * @return array Bulk actions.
		 */
		public function wkwctobag_add_categories_bulk_action( $actions ) {
			$actions['wkwctobag_bulk_sync_categories'] = esc_html__( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' );
			return $actions;
		}

		/**
		 * Add Bagisto mapping column header to the categories table.
		 *
		 * @param array $columns Columns list.
		 * @return array Modified columns list.
		 */
		public function wkwctobag_add_category_columns( $columns ) {
			$new_columns = array();
			foreach ( $columns as $key => $title ) {
				$new_columns[ $key ] = $title;
				if ( 'slug' === $key ) {
					$new_columns['wkwctobag_bagisto_mapping'] = esc_html__( 'Bagisto Mapping', 'bagisto-sync-for-woocommerce' );
				}
			}
			return $new_columns;
		}

		/**
		 * Render the mapping column content for each category.
		 *
		 * @param string $content HTML content.
		 * @param string $column_name Column name.
		 * @param int    $term_id Term ID.
		 * @return string Rendered content.
		 */
		public function wkwctobag_render_category_column_content( $content, $column_name, $term_id ) {
			if ( 'wkwctobag_bagisto_mapping' === $column_name ) {
				$bagisto_id = $this->helper->get_bagisto_category_id( $term_id );
				if ( $bagisto_id ) {
					static $bagisto_categories = null;
					if ( is_null( $bagisto_categories ) ) {
						$bagisto_categories = get_transient( 'wkwctobag_bagisto_categories_cache' );
						if ( false === $bagisto_categories ) {
							$category_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Category\Wkwctobag_Sync_Category();
							$response      = $category_sync->get_bagisto_product_category();
							if ( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
								$bagisto_categories = array();
								foreach ( $response['data'] as $cat ) {
									$bagisto_categories[ intval( $cat['id'] ) ] = $cat['name'];
								}
								set_transient( 'wkwctobag_bagisto_categories_cache', $bagisto_categories, 5 * MINUTE_IN_SECONDS );
							} else {
								$bagisto_categories = array();
							}
						}
					}

					$name = '';
					foreach ( $bagisto_categories as $key => $cat ) {
						if ( is_array( $cat ) ) {
							$cat_id   = isset( $cat['id'] ) ? intval( $cat['id'] ) : 0;
							$cat_name = isset( $cat['name'] ) ? $cat['name'] : '';
							if ( intval( $bagisto_id ) === $cat_id ) {
								$name = $cat_name;
								break;
							}
						} elseif ( intval( $key ) === intval( $bagisto_id ) ) {
								$name = $cat;
								break;
						}
					}

					if ( empty( $name ) ) {
						return sprintf(
							'<span class="wkwctobag-badge wkwctobag-badge-danger" title="%s">%s (#%d)</span>',
							esc_attr__( 'This category ID does not exist in Bagisto.', 'bagisto-sync-for-woocommerce' ),
							esc_html__( 'Stale Mapping', 'bagisto-sync-for-woocommerce' ),
							$bagisto_id
						);
					} else {
						return sprintf(
							'<span class="wkwctobag-badge wkwctobag-badge-success">%s (#%d)</span>',
							esc_html( $name ),
							$bagisto_id
						);
					}
				} else {
					return sprintf(
						'<span class="wkwctobag-badge wkwctobag-badge-secondary">%s</span>',
						esc_html__( 'Not Mapped', 'bagisto-sync-for-woocommerce' )
					);
				}
			}
			return $content;
		}

		/**
		 * Render categories sync configuration modal in categories page footer.
		 */
		public function wkwctobag_render_categories_sync_modal() {
			$screen = get_current_screen();
			if ( ! $screen || 'edit-product_cat' !== $screen->id ) {
				return;
			}
			?>

			<div id="wkwctobag-category-sync-modal" class="wkwctobag-modal-overlay" style="display: none;">
				<div class="wkwctobag-modal-card wkwctobag-modal-card-large">
					<div class="wkwctobag-modal-loader" style="display: none;">
						<div class="wkwctobag-spinner"></div>
					</div>
					<div class="wkwctobag-modal-header">
						<h3><?php esc_html_e( 'Sync categories to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></h3>
						<button type="button" class="wkwctobag-modal-close-btn">&times;</button>
					</div>
					<div class="wkwctobag-modal-body">
					</div>
					<div class="wkwctobag-modal-footer">
						<button type="button" class="wkwctobag-modal-cancel-btn"><?php esc_html_e( 'Cancel', 'bagisto-sync-for-woocommerce' ); ?></button>
						<button type="button" class="wkwctobag-modal-submit-btn"><?php esc_html_e( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Selected categories sync handler via AJAX.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function wkwctobag_selected_categories_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'category' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$categories_data = isset( $_POST['categories_data'] ) ? wp_unslash( $_POST['categories_data'] ) : array();

				if ( is_string( $categories_data ) ) {
					$categories_data = map_deep( json_decode( stripslashes( $categories_data ), true ), 'sanitize_text_field' );
				}

				if ( empty( $categories_data ) || ! is_array( $categories_data ) ) {
					throw new \Exception( esc_html__( 'No categories selected for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				$category_count = 0;

				foreach ( $categories_data as $item ) {
					if ( empty( $item['category_id'] ) ) {
						continue;
					}

					$category_id = absint( $item['category_id'] );
					$term        = get_term( $category_id, 'product_cat' );

					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}

					$update_existing    = isset( $item['update_existing'] ) && 'yes' === $item['update_existing'] ? 'yes' : 'no';
					$mapped_category_id = isset( $item['mapped_category_id'] ) ? intval( $item['mapped_category_id'] ) : -1;

					if ( $mapped_category_id > 0 ) {
						$this->helper->update_bagisto_category_id( $category_id, $mapped_category_id );

						$settings_data = $this->helper->get_product_settings_data();
						if ( empty( $settings_data['category_mapping'] ) ) {
							$settings_data['category_mapping'] = array();
						}
						$settings_data['category_mapping'][ $category_id ] = $mapped_category_id;
						$this->helper->save_product_settings( $settings_data );
					} elseif ( -1 === $mapped_category_id ) {
						delete_term_meta( $category_id, '__wkwctobag_bagisto_category_id' );

						$settings_data = $this->helper->get_product_settings_data();
						if ( isset( $settings_data['category_mapping'][ $category_id ] ) ) {
							unset( $settings_data['category_mapping'][ $category_id ] );
							$this->helper->save_product_settings( $settings_data );
						}
					}

					$data = array(
						'type'                        => 'category',
						'category_id'                 => $category_id,
						'is_update_existing_category' => $update_existing,
					);

					$this->background_processes['category']->push_to_queue( $data );
					++$category_count;
				}

				$this->background_processes['category']->save();

				if ( $category_count > 0 ) {
					$this->update_active_sync_metadata( 'category', $category_count );
				}

				$this->background_processes['category']->dispatch();

				$response['success'] = true;
				$response['message'] = sprintf(
					/* translators: %d: category count */
					esc_html__( 'Initiated sync for %d categories in the background.', 'bagisto-sync-for-woocommerce' ),
					$category_count
				);
				$response['category_count'] = $category_count;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Add bulk sync action to products list page.
		 *
		 * @param array $actions Bulk actions.
		 * @return array Bulk actions.
		 */
		public function wkwctobag_add_products_bulk_action( $actions ) {
			$actions['wkwctobag_bulk_sync_products'] = esc_html__( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' );
			return $actions;
		}

		/**
		 * Render products sync configuration modal in products page footer.
		 */
		public function wkwctobag_render_products_sync_modal() {
			$screen = get_current_screen();
			if ( ! $screen || 'edit-product' !== $screen->id ) {
				return;
			}
			?>

			<div id="wkwctobag-product-sync-modal" class="wkwctobag-modal-overlay" style="display: none;">
				<div class="wkwctobag-modal-card wkwctobag-modal-card-large">
					<div class="wkwctobag-modal-loader" style="display: none;">
						<div class="wkwctobag-spinner"></div>
					</div>
					<div class="wkwctobag-modal-header">
						<h3><?php esc_html_e( 'Sync products to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></h3>
						<button type="button" class="wkwctobag-modal-close-btn">&times;</button>
					</div>
					<div class="wkwctobag-modal-body">
					</div>
					<div class="wkwctobag-modal-footer">
						<button type="button" class="wkwctobag-modal-cancel-btn"><?php esc_html_e( 'Cancel', 'bagisto-sync-for-woocommerce' ); ?></button>
						<button type="button" class="wkwctobag-modal-submit-btn"><?php esc_html_e( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Selected products sync handler via AJAX.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function wkwctobag_selected_products_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'product' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$products_data = isset( $_POST['products_data'] ) ? wp_unslash( $_POST['products_data'] ) : array();

				if ( is_string( $products_data ) ) {
					$products_data = map_deep( json_decode( stripslashes( $products_data ), true ), 'sanitize_text_field' );
				}

				if ( empty( $products_data ) || ! is_array( $products_data ) ) {
					throw new \Exception( esc_html__( 'No products selected for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				$product_count = 0;

				foreach ( $products_data as $item ) {
					if ( empty( $item['product_id'] ) ) {
						continue;
					}

					$product_id = absint( $item['product_id'] );
					$product    = wc_get_product( $product_id );

					if ( ! $product ) {
						continue;
					}

					$update_existing = isset( $item['update_existing'] ) && 'yes' === $item['update_existing'] ? 'yes' : 'no';

					$data = array(
						'type'                    => 'product',
						'product_id'              => $product_id,
						'is_update_existing_user' => $update_existing,
					);

					$this->background_processes['product']->push_to_queue( $data );
					++$product_count;
				}

				$this->background_processes['product']->save();

				if ( $product_count > 0 ) {
					$this->update_active_sync_metadata( 'product', $product_count );
				}

				$this->background_processes['product']->dispatch();

				$response['success'] = true;
				$response['message'] = sprintf(
					/* translators: %d: product count */
					esc_html__( 'Initiated sync for %d products in the background.', 'bagisto-sync-for-woocommerce' ),
					$product_count
				);
				$response['product_count'] = $product_count;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Add Bagisto mapping column header to the users table.
		 *
		 * @param array $columns Columns list.
		 * @return array Modified columns list.
		 */
		public function wkwctobag_add_users_columns( $columns ) {
			$new_columns = array();
			foreach ( $columns as $key => $title ) {
				$new_columns[ $key ] = $title;
				if ( 'email' === $key ) {
					$new_columns['wkwctobag_bagisto_user_mapping'] = esc_html__( 'Bagisto Mapping', 'bagisto-sync-for-woocommerce' );
				}
			}
			return $new_columns;
		}

		/**
		 * Render the mapping column content for each user.
		 *
		 * @param string $val Original column value.
		 * @param string $column_name Column name.
		 * @param int    $user_id User ID.
		 * @return string Rendered content.
		 */
		public function wkwctobag_render_users_column_content( $val, $column_name, $user_id ) {
			if ( 'wkwctobag_bagisto_user_mapping' === $column_name ) {
				$bagisto_user_id     = $this->helper->get_bagisto_user_id( $user_id );
				$bagisto_customer_id = $this->helper->get_bagisto_customer_id( $user_id );

				$output = '';

				if ( $bagisto_user_id ) {
					$output .= sprintf(
						'<span class="wkwctobag-badge wkwctobag-badge-success">%s (#%d)</span>',
						esc_html__( 'User', 'bagisto-sync-for-woocommerce' ),
						$bagisto_user_id
					);
				}

				if ( $bagisto_customer_id ) {
					$output .= sprintf(
						'<span class="wkwctobag-badge wkwctobag-badge-info">%s (#%d)</span>',
						esc_html__( 'Customer', 'bagisto-sync-for-woocommerce' ),
						$bagisto_customer_id
					);
				}

				if ( ! empty( $output ) ) {
					return $output;
				} else {
					return sprintf(
						'<span class="wkwctobag-badge wkwctobag-badge-secondary">%s</span>',
						esc_html__( 'Not Mapped', 'bagisto-sync-for-woocommerce' )
					);
				}
			}
			return $val;
		}

		/**
		 * Add Bagisto Mapping column to WooCommerce products list table.
		 *
		 * @param array $columns Products columns.
		 * @return array Modified columns.
		 */
		public function wkwctobag_add_product_columns( $columns ) {
			$new_columns = array();
			foreach ( $columns as $key => $title ) {
				$new_columns[ $key ] = $title;
				if ( 'sku' === $key ) {
					$new_columns['wkwctobag_bagisto_mapping'] = esc_html__( 'Bagisto ID', 'bagisto-sync-for-woocommerce' );
				}
			}
			if ( ! isset( $new_columns['wkwctobag_bagisto_mapping'] ) ) {
				$new_columns['wkwctobag_bagisto_mapping'] = esc_html__( 'Bagisto ID', 'bagisto-sync-for-woocommerce' );
			}
			return $new_columns;
		}

		/**
		 * Render Bagisto Mapping column content in WooCommerce products list table.
		 *
		 * @param string $column_name Column name.
		 * @param int    $post_id Post/Product ID.
		 */
		public function wkwctobag_render_product_column_content( $column_name, $post_id ) {
			if ( 'wkwctobag_bagisto_mapping' === $column_name ) {
				$bagisto_product_id = $this->helper->get_bagisto_product_id( $post_id );
				if ( $bagisto_product_id ) {
					printf(
						'<span class="wkwctobag-badge wkwctobag-badge-success">#%d</span>',
						absint( $bagisto_product_id )
					);
				} else {
					printf(
						'<span class="wkwctobag-badge wkwctobag-badge-secondary">%s</span>',
						esc_html__( 'Not Mapped', 'bagisto-sync-for-woocommerce' )
					);
				}
			}
		}

		/**
		 * Render attributes sync configuration modal in attributes page footer.
		 */
		public function wkwctobag_render_attributes_sync_modal() {
			$screen = get_current_screen();
			if ( ! $screen || 'product_page_product_attributes' !== $screen->id ) {
				return;
			}
			?>
			<div id="wkwctobag-attribute-sync-modal" class="wkwctobag-modal-overlay" style="display: none;">
				<div class="wkwctobag-modal-card wkwctobag-modal-card-medium">
					<div class="wkwctobag-modal-loader" style="display: none;">
						<div class="wkwctobag-spinner"></div>
					</div>
					<div class="wkwctobag-modal-header">
						<h3><?php esc_html_e( 'Sync Attributes to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></h3>
						<button type="button" class="wkwctobag-modal-close-btn">&times;</button>
					</div>
					<div class="wkwctobag-modal-body">
						<div class="wkwctobag-modal-intro">
							<?php esc_html_e( 'You have selected attributes to synchronize. This will create or update their corresponding attributes and all variations/options in Bagisto Catalog.', 'bagisto-sync-for-woocommerce' ); ?>
						</div>

						<div class="wkwctobag-modal-form-group">
							<label>
								<?php esc_html_e( 'Force Update Existing Attributes?', 'bagisto-sync-for-woocommerce' ); ?>
							</label>
							<select id="wkwctobag-attr-sync-update-existing">
								<option value="yes"><?php esc_html_e( 'Yes, overwrite existing data', 'bagisto-sync-for-woocommerce' ); ?></option>
								<option value="no" selected><?php esc_html_e( 'No, only sync missing/new data', 'bagisto-sync-for-woocommerce' ); ?></option>
							</select>
						</div>
					</div>
					<div class="wkwctobag-modal-footer">
						<button type="button" class="wkwctobag-modal-cancel-btn"><?php esc_html_e( 'Cancel', 'bagisto-sync-for-woocommerce' ); ?></button>
						<button type="button" class="wkwctobag-modal-submit-btn wkwctobag-btn-primary" ><?php esc_html_e( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Selected attributes sync handler via AJAX.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function wkwctobag_selected_attributes_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'attribute' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$attributes_data = isset( $_POST['attributes_data'] ) ? wp_unslash( $_POST['attributes_data'] ) : array();

				if ( is_string( $attributes_data ) ) {
					$attributes_data = map_deep( json_decode( stripslashes( $attributes_data ), true ), 'sanitize_text_field' );
				}

				if ( empty( $attributes_data ) || ! is_array( $attributes_data ) ) {
					throw new \Exception( esc_html__( 'No attributes selected for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				$attribute_count = 0;

				foreach ( $attributes_data as $item ) {
					if ( empty( $item['attribute_id'] ) ) {
						continue;
					}

					$attribute_id    = absint( $item['attribute_id'] );
					$update_existing = isset( $item['update_existing'] ) && 'yes' === $item['update_existing'] ? 'yes' : 'no';

					$data = array(
						'type'                      => 'attribute',
						'attribute_id'              => $attribute_id,
						'is_update_existing_attrib' => $update_existing,
					);

					$this->background_processes['attribute']->push_to_queue( $data );
					++$attribute_count;
				}

				$this->background_processes['attribute']->save();

				if ( $attribute_count > 0 ) {
					$this->update_active_sync_metadata( 'attribute', $attribute_count );
				}

				$this->background_processes['attribute']->dispatch();

				$response['success'] = true;
				$response['message'] = sprintf(
					/* translators: %d: attribute count */
					esc_html__( 'Initiated sync for %d attributes in the background.', 'bagisto-sync-for-woocommerce' ),
					$attribute_count
				);
				$response['attribute_count'] = $attribute_count;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Handle log file downloads securely before headers are sent.
		 */
		public function wkwctobag_download_log() {
			if ( ! isset( $_GET['download-log'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wkwctobag_download_log' ) ) {
				return;
			}

			$source = sanitize_text_field( wp_unslash( $_GET['download-log'] ) );
			$path   = trailingslashit( wp_upload_dir()['basedir'] ) . 'bagisto-sync-for-woocommerce-logs/wkwctobag-' . sanitize_file_name( $source ) . '.log';

			if ( file_exists( $path ) ) {
				while ( ob_get_level() ) {
					ob_end_clean();
				}

				header( 'Content-Description: File Transfer' );
				header( 'Content-Type: text/plain' );
				header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
				header( 'Expires: 0' );
				header( 'Cache-Control: must-revalidate' );
				header( 'Pragma: public' );
				global $wp_filesystem;
				if ( empty( $wp_filesystem ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}

				if ( $wp_filesystem && $wp_filesystem->exists( $path ) ) {
					$content = $wp_filesystem->get_contents( $path );
					header( 'Content-Length: ' . strlen( $content ) );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $content;
					exit;
				}
			}
		}

		/**
		 * Test Bagisto connection via AJAX.
		 */
		public function test_connection() {
			check_ajax_referer( 'wkwctobag_admin_ajax_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'bagisto-sync-for-woocommerce' ) ) );
			}

			$site_url = isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : '';
			$token    = isset( $_POST['admin_token'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_token'] ) ) : '';

			$helper = \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance();

			if ( empty( $site_url ) ) {
				$site_url = $helper->get_bagisto_site_url();
			}

			if ( empty( $token ) ) {
				$token = $helper->get_bagisto_admin_token();
			}

			if ( empty( $site_url ) || empty( $token ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Store URL and API Token are required.', 'bagisto-sync-for-woocommerce' ) ) );
			}

			$test_url = untrailingslashit( $site_url ) . '/api/admin/get';
			$args     = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
				'timeout' => 15,
			);

			$response = wp_remote_get( $test_url, $args );

			if ( is_wp_error( $response ) ) {
				/* translators: %s: error message from API */
				wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Connection failed: %s', 'bagisto-sync-for-woocommerce' ), $response->get_error_message() ) ) ); //phpcs:ignore
			}

			$http_code = wp_remote_retrieve_response_code( $response );
			$body      = wp_remote_retrieve_body( $response );
			$data      = json_decode( $body, true );

			if ( 200 === $http_code ) {
				$email = '';
				if ( ! empty( $data ) ) {
					$user_data = isset( $data[0] ) ? $data[0] : $data;
					if ( ! empty( $user_data['email'] ) ) {
						$email = $user_data['email'];
					}
				}
				delete_option( 'wkwctobag_token_expired' );
				$msg = esc_html__( 'Connection successful! API is reachable.', 'bagisto-sync-for-woocommerce' );
				if ( ! empty( $email ) ) {
					/* translators: %s: admin email */
					$msg = sprintf( esc_html__( 'Connection successful! Logged in as %s.', 'bagisto-sync-for-woocommerce' ), $email ); //phpcs:ignore
				}
				wp_send_json_success( array( 'message' => $msg ) );
			}

			if ( 401 === $http_code ) {
				update_option( 'wkwctobag_token_expired', '1' );
			}

			$error_msg = esc_html__( 'Invalid token or API error.', 'bagisto-sync-for-woocommerce' );
			if ( ! empty( $data['message'] ) ) {
				$error_msg = esc_html( $data['message'] );
			} elseif ( ! empty( $data['detail'] ) ) {
				$error_msg = esc_html( $data['detail'] );
			}

			/* translators: %s: error message */
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Connection failed: %s', 'bagisto-sync-for-woocommerce' ), $error_msg ) ) ); //phpcs:ignore
		}

		/**
		 * Add bulk sync action to orders list page.
		 *
		 * @param array $actions Bulk actions.
		 * @return array Bulk actions.
		 */
		public function wkwctobag_add_orders_bulk_action( $actions ) {
			$actions['wkwctobag_bulk_sync_orders'] = esc_html__( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' );
			return $actions;
		}

		/**
		 * Render orders sync configuration modal in orders page footer.
		 */
		public function wkwctobag_render_orders_sync_modal() {
			$screen = get_current_screen();
			if ( ! $screen || ( 'edit-shop_order' !== $screen->id && 'woocommerce_page_wc-orders' !== $screen->id ) ) {
				return;
			}
			?>

			<div id="wkwctobag-order-sync-modal" class="wkwctobag-modal-overlay" style="display: none;">
				<div class="wkwctobag-modal-card wkwctobag-modal-card-large">
					<div class="wkwctobag-modal-loader" style="display: none;">
						<div class="wkwctobag-spinner"></div>
					</div>
					<div class="wkwctobag-modal-header">
						<h3><?php esc_html_e( 'Sync orders to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></h3>
						<button type="button" class="wkwctobag-modal-close-btn">&times;</button>
					</div>
					<div class="wkwctobag-modal-body">
					</div>
					<div class="wkwctobag-modal-footer">
						<button type="button" class="wkwctobag-modal-cancel-btn"><?php esc_html_e( 'Cancel', 'bagisto-sync-for-woocommerce' ); ?></button>
						<button type="button" class="wkwctobag-modal-submit-btn"><?php esc_html_e( 'Sync to Bagisto', 'bagisto-sync-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Selected orders sync handler via AJAX.
		 *
		 * @throws \Exception If the security or capability check fails, or the sync cannot be queued.
		 */
		public function wkwctobag_selected_orders_sync() {
			$response = array(
				'success' => false,
				'message' => '',
			);

			try {
				$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				if ( ! wp_verify_nonce( $nonce, 'wkwctobag_admin_ajax_nonce' ) ) {
					throw new \Exception( esc_html__( 'Security check failed.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					throw new \Exception( esc_html__( 'You do not have permission to perform this action.', 'bagisto-sync-for-woocommerce' ) );
				}

				if ( ! $this->helper->is_connected_with_bagisto() ) {
					$this->helper->logger->wkwctobag_error( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', array( 'source' => 'order' ) );
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$orders_data = isset( $_POST['orders_data'] ) ? wp_unslash( $_POST['orders_data'] ) : array();

				if ( is_string( $orders_data ) ) {
					$orders_data = map_deep( json_decode( stripslashes( $orders_data ), true ), 'sanitize_text_field' );
				}

				if ( empty( $orders_data ) || ! is_array( $orders_data ) ) {
					throw new \Exception( esc_html__( 'No orders selected for sync.', 'bagisto-sync-for-woocommerce' ) );
				}

				$order_count = 0;

				foreach ( $orders_data as $item ) {
					if ( empty( $item['order_id'] ) ) {
						continue;
					}

					$order_id = absint( $item['order_id'] );
					$order    = wc_get_order( $order_id );

					if ( ! $order ) {
						continue;
					}

					$update_existing = isset( $item['update_existing'] ) && 'yes' === $item['update_existing'] ? 'yes' : 'no';

					$data = array(
						'type'                    => 'order',
						'order_id'                => $order_id,
						'is_update_existing_user' => $update_existing,
					);

					$this->background_processes['order']->push_to_queue( $data );
					++$order_count;
				}

				$this->background_processes['order']->save();

				if ( $order_count > 0 ) {
					$this->update_active_sync_metadata( 'order', $order_count );
				}

				$this->background_processes['order']->dispatch();

				$response['success'] = true;
				$response['message'] = sprintf(
					/* translators: %d: order count */
					esc_html__( 'Initiated sync for %d orders in the background.', 'bagisto-sync-for-woocommerce' ),
					$order_count
				);
				$response['order_count'] = $order_count;

			} catch ( \Exception $e ) {
				$response['message'] = $e->getMessage();
			}

			wp_send_json( $response );
		}

		/**
		 * Add Bagisto mapping column header to WooCommerce orders table.
		 *
		 * @param array $columns Orders columns.
		 * @return array Modified columns.
		 */
		public function wkwctobag_add_order_columns( $columns ) {
			$new_columns = array();
			foreach ( $columns as $key => $title ) {
				$new_columns[ $key ] = $title;
				if ( 'order_title' === $key || 'order_number' === $key ) {
					$new_columns['wkwctobag_bagisto_order_mapping'] = esc_html__( 'Bagisto ID', 'bagisto-sync-for-woocommerce' );
				}
			}
			if ( ! isset( $new_columns['wkwctobag_bagisto_order_mapping'] ) ) {
				$new_columns['wkwctobag_bagisto_order_mapping'] = esc_html__( 'Bagisto ID', 'bagisto-sync-for-woocommerce' );
			}
			return $new_columns;
		}

		/**
		 * Render Bagisto Mapping column content in WooCommerce orders list table.
		 *
		 * @param string        $column_name Column name.
		 * @param int|\WC_Order $post_id_or_order Post ID (CPT) or WC_Order object (HPOS).
		 */
		public function wkwctobag_render_order_column_content( $column_name, $post_id_or_order ) {
			if ( 'wkwctobag_bagisto_order_mapping' === $column_name ) {
				$order_id         = $post_id_or_order instanceof \WC_Order ? $post_id_or_order->get_id() : intval( $post_id_or_order );
				$bagisto_order_id = $this->helper->get_bagisto_order_id( $order_id );
				if ( $bagisto_order_id ) {
					printf(
						'<span class="wkwctobag-badge wkwctobag-badge-success">#%d</span>',
						absint( $bagisto_order_id )
					);
				} else {
					printf(
						'<span class="wkwctobag-badge wkwctobag-badge-secondary">%s</span>',
						esc_html__( 'Not Mapped', 'bagisto-sync-for-woocommerce' )
					);
				}
			}
		}

		/**
		 * Execute log cleanup manually or via admin request.
		 *
		 * @param string|null $days Optional custom days threshold.
		 * @return int Number of cleaned items.
		 */
		public function execute_log_cleanup( $days = null ) {
			if ( isset( $this->helper->logger ) ) {
				return $this->helper->logger->clean_expired_logs( $days );
			}
			return 0;
		}

		/**
		 * Show expired token notice on settings, bulk, and log pages.
		 */
		public function wkwctobag_show_expired_token_notice() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page          = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$allowed_pages = array(
				'wkwctobag-settings',
				'wkwctobag-bulk-sync',
				'wkwctobag-logs',
			);

			$is_connector_page = in_array( $page, $allowed_pages, true );
			$is_woo_page       = false;
			if ( $screen ) {
				$is_woo_page = ( 'shop_order' === $screen->post_type || 'product' === $screen->post_type || 'product_cat' === $screen->taxonomy || 'woocommerce_page_wc-orders' === $screen->id || 'woocommerce_page_wc-orders' === $screen->base );
			}

			if ( ! $is_connector_page && ! $is_woo_page ) {
				return;
			}

			$connection_url = admin_url( 'admin.php?page=wkwctobag-configuration' );

			if ( ! $this->helper->is_connected_with_bagisto() ) {
				$message = sprintf(
					/* translators: %s: connection page URL */
					__( 'WooCommerce to Bagisto connection is not established. Please configure your credentials on the <a href="%s">Connection page</a> to enable synchronization.', 'bagisto-sync-for-woocommerce' ),
					esc_url( $connection_url )
				);
				$this->helper->add_notice( $message, 'warning' );
				return;
			}

			if ( get_option( 'wkwctobag_token_expired' ) === '1' ) {
				$message = sprintf(
					/* translators: %s: connection page URL */
					__( 'Your Bagisto API token has expired or is invalid. Please visit the <a href="%s">Connection page</a> to update it.', 'bagisto-sync-for-woocommerce' ),
					esc_url( $connection_url )
				);
				$this->helper->add_notice( $message, 'error' );
			}
		}

		/**
		 * Register Bagisto order mapping meta box for WooCommerce orders.
		 */
		public function wkwctobag_register_order_metabox() {
			$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
			foreach ( $screens as $screen ) {
				add_meta_box(
					'wkwctobag_order_mapping_box',
					esc_html__( 'Bagisto Sync for WooCommerce', 'bagisto-sync-for-woocommerce' ),
					array( $this, 'wkwctobag_render_order_metabox_content' ),
					$screen,
					'side',
					'default'
				);
			}
		}

		/**
		 * Render Bagisto order mapping meta box content.
		 *
		 * @param \WP_Post|\WC_Order $post_or_order WP_Post or WC_Order instance depending on screen.
		 */
		public function wkwctobag_render_order_metabox_content( $post_or_order ) {
			$order_id = 0;
			if ( $post_or_order instanceof \WP_Post ) {
				$order_id = $post_or_order->ID;
			} elseif ( $post_or_order instanceof \WC_Order ) {
				$order_id = $post_or_order->get_id();
			} else {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$order_id = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : ( isset( $_GET['post'] ) ? intval( wp_unslash( $_GET['post'] ) ) : 0 );
			}

			if ( ! $order_id ) {
				echo '<p>' . esc_html__( 'No order context found.', 'bagisto-sync-for-woocommerce' ) . '</p>';
				return;
			}

			$bagisto_order_id = $this->helper->get_bagisto_order_id( $order_id );

			if ( $bagisto_order_id ) {
				echo '<div style="padding: 6px 0; display: flex; align-items: center; justify-content: space-between; font-size: 13px;">';
				echo '<strong>' . esc_html__( 'Bagisto Order ID:', 'bagisto-sync-for-woocommerce' ) . '</strong>';
				echo '<span style="background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 6px; font-weight: 600;">#' . esc_html( $bagisto_order_id ) . '</span>';
				echo '</div>';
			} else {
				echo '<div style="padding: 6px 0; color: #6b7280; font-style: italic; font-size: 13px;">';
				echo esc_html__( 'This order has not been synchronized to Bagisto yet.', 'bagisto-sync-for-woocommerce' );
				echo '</div>';
			}
		}
	}
}
