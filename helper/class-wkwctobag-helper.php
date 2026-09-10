<?php
/**
 * Helper class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

if ( ! class_exists( 'Wkwctobag_Helper' ) ) {
	/**
	 * Helper class.
	 */
	class Wkwctobag_Helper {
		/**
		 * Instance of this class.
		 *
		 * @var Wkwctobag_Helper $instance Instance of this class.
		 */
		protected static $instance = null;

		/**
		 * Bagisto admin token.
		 *
		 * @var string|null $bagisto_admin_token Bagisto admin token.
		 */
		protected $bagisto_admin_token = null;

		/**
		 * Bagisto site URL.
		 *
		 * @var string|null $bagisto_site_url Bagisto site URL.
		 */
		protected $bagisto_site_url = null;

		/**
		 * Bagisto admin email.
		 *
		 * @var string|null $bagisto_admin_email Bagisto admin email.
		 */
		protected $bagisto_admin_email = null;

		/**
		 * File Logger Instance
		 *
		 * @var Wkwctobag_Logger
		 */
		public $logger;

		/**
		 * Private constructor of this class.
		 */
		private function __construct() {
			if ( empty( $this->bagisto_admin_token ) ) {
				$this->bagisto_admin_token = get_option( 'wkwctobag_bagisto_admin_token', null );
			}

			if ( empty( $this->bagisto_site_url ) ) {
				$this->bagisto_site_url = get_option( 'wkwctobag_bagisto_site_url', null );
			}

			if ( empty( $this->bagisto_admin_email ) ) {
				$this->bagisto_admin_email = get_option( 'wkwctobag_bagisto_admin_email', null );
			}

			// Initialize the global logger instance.
			$this->logger = new Wkwctobag_Logger( 'wkwctobag-sync' );
		}

		/**
		 * Background process instances, keyed by sync type.
		 *
		 * @var Background_Process\Wkwctobag_Background_Process[] $background_processes Background processes instances.
		 */
		private $background_processes = array();

		/**
		 * Get the background process instance for a specific type.
		 *
		 * @param string $type Process type.
		 * @return Background_Process\Wkwctobag_Background_Process
		 */
		public function get_background_process( $type ) {
			if ( ! isset( $this->background_processes[ $type ] ) ) {
				$this->background_processes[ $type ] = new Background_Process\Wkwctobag_Background_Process( $type );
			}
			return $this->background_processes[ $type ];
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
		 * Get Bagisto admin token.
		 *
		 * @return string|null
		 */
		public function get_bagisto_admin_token() {
			return $this->bagisto_admin_token;
		}

		/**
		 * Get Bagisto site URL.
		 *
		 * @return string|null
		 */
		public function get_bagisto_site_url() {
			return $this->bagisto_site_url;
		}

		/**
		 * Get Bagisto admin email.
		 *
		 * @return string|null Bagisto admin email.
		 */
		public function get_bagisto_admin_email() {
			return $this->bagisto_admin_email;
		}

		/**
		 * Is connected with Bagisto.
		 *
		 * @return bool
		 */
		public function is_connected_with_bagisto() {
			return ! empty( $this->bagisto_admin_token ) && ! empty( $this->bagisto_site_url );
		}

		/**
		 * Add notice.
		 *
		 * @param string $message Message to be display.
		 * @param string $type Type of the message ( success | error | info ).
		 * @param bool   $is_dismissible Is message dismissible.
		 */
		public function add_notice( $message, $type = 'success', $is_dismissible = true ) {
			$dismissible = $is_dismissible ? 'is-dismissible' : '';
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> <?php echo esc_attr( $dismissible ); ?>" style="margin-top: 12px;">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php
		}


		/**
		 * Update Bagisto admin token.
		 *
		 * @param string $token Bagisto admin token.
		 */
		public function update_bagisto_admin_token( $token ) {
			update_option( 'wkwctobag_bagisto_admin_token', $token, true );
			$this->bagisto_admin_token = $token;
			$this->clear_bagisto_caches();
		}

		/**
		 * Update Bagisto site URL.
		 *
		 * @param string $url Bagisto site URL.
		 */
		public function update_bagisto_site_url( $url ) {
			update_option( 'wkwctobag_bagisto_site_url', $url, true );
			$this->bagisto_site_url = $url;
			$this->clear_bagisto_caches();
		}

		/**
		 * Update Bagisto admin email.
		 *
		 * @param string $email Bagisto admin email.
		 */
		public function update_bagisto_admin_email( $email ) {
			update_option( 'wkwctobag_bagisto_admin_email', $email, true );
			$this->bagisto_admin_email = $email;
			$this->clear_bagisto_caches();
		}

		/**
		 * Clear cached Bagisto API responses.
		 */
		public function clear_bagisto_caches() {
			delete_transient( 'wkwctobag_bagisto_roles_cache' );
			delete_transient( 'wkwctobag_bagisto_groups_cache' );
			delete_transient( 'wkwctobag_bagisto_categories_cache' );
		}

		/**
		 * Connect to Bagisto.
		 *
		 * @param string $site_url Site url.
		 * @param string $token    API Token.
		 *
		 * @throws \Exception Exception.
		 *
		 * @return bool True on success, false on failure.
		 */
		public function connect_to_bagisto( $site_url, $token ) {
			try {
				$site_url = rtrim( $site_url, '/' );
				$url      = $site_url . '/api/admin/get';

				$args = array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					),
					'timeout' => 30,
				);

				$response = wp_remote_get( $url, $args );

				if ( is_wp_error( $response ) ) {
					throw new \Exception( $response->get_error_message() );
				}

				$http_code = wp_remote_retrieve_response_code( $response );
				$body      = wp_remote_retrieve_body( $response );
				$data      = json_decode( $body, true );

				if ( 200 !== $http_code ) {
					$error_msg = esc_html__( 'Failed to connect. Please verify the Store URL and Token.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $data['message'] ) ) {
						$error_msg = esc_html( $data['message'] );
					} elseif ( ! empty( $data['detail'] ) ) {
						$error_msg = esc_html( $data['detail'] );
					}
					if ( 401 === $http_code ) {
						update_option( 'wkwctobag_token_expired', '1' );
					}
					throw new \Exception( esc_html( $error_msg ) );
				}

				$email = '';
				if ( ! empty( $data ) ) {
					$user_data = isset( $data[0] ) ? $data[0] : $data;
					if ( ! empty( $user_data['email'] ) ) {
						$email = $user_data['email'];
					}
				}

				$this->update_bagisto_admin_token( $token );
				if ( ! empty( $email ) ) {
					$this->update_bagisto_admin_email( $email );
				}
				$this->update_bagisto_site_url( $site_url );
				delete_option( 'wkwctobag_token_expired' );

				return true;
			} catch ( \Exception $th ) {
				throw new \Exception( esc_html( $th->getMessage() ) );
			}
		}

		/**
		 * Login Bagisto user.
		 *
		 * @param string $site_url Site url.
		 * @param string $email User email.
		 * @param string $password User password.
		 *
		 * @throws \Exception Exception.
		 *
		 * @return object Response body.
		 */
		public function login_to_bagisto( $site_url, $email, $password ) {
			$args = array(
				'body'    => wp_json_encode(
					array(
						'email'       => $email,
						'password'    => $password,
						'device_name' => 'woocommerce',
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
			);

			$response = wp_remote_post( $site_url, $args );

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
				$message = ! empty( $message ) ? $message : esc_html__( 'Error Processing Request', 'bagisto-sync-for-woocommerce' );

				throw new \Exception( esc_html( $message ) );

			} else {
				$data = wp_remote_retrieve_body( $response );
				$data = json_decode( $data, true );

				$status_code = wp_remote_retrieve_response_code( $response );

				if ( 200 !== $status_code && empty( $data ) ) {
					throw new \Exception( esc_html__( 'Invalid site url please check your site url.', 'bagisto-sync-for-woocommerce' ) );
				}

				return $data;
			}
		}

		/**
		 * Save user settings.
		 *
		 * @param array $data User settings data.
		 */
		public function save_user_settings( $data ) {
			update_option( 'wkwctobag_user_settings', $data, true );
		}

		/**
		 * Get user settings data.
		 *
		 * @return array User settings data.
		 */
		public function get_user_settings_data() {
			return get_option( 'wkwctobag_user_settings', array() );
		}

		/**
		 * Save customer settings.
		 *
		 * @param array $data Customer settings data.
		 */
		public function save_customer_settings( $data ) {
			update_option( 'wkwctobag_customer_settings', $data, true );
		}

		/**
		 * Get customer settings data.
		 *
		 * @return array Customer settings data.
		 */
		public function get_customer_settings_data() {
			return get_option( 'wkwctobag_customer_settings', array() );
		}

		/**
		 * Save order settings.
		 *
		 * @param array $data Order settings data.
		 */
		public function save_order_settings( $data ) {
			update_option( 'wkwctobag_order_settings', $data, true );
		}

		/**
		 * Get order settings data.
		 *
		 * @return array Order settings data.
		 */
		public function get_order_settings_data() {
			return get_option( 'wkwctobag_order_settings', array() );
		}

		/**
		 * Save product settings.
		 *
		 * @param array $data Product settings data.
		 */
		public function save_product_settings( $data ) {
			update_option( 'wkwctobag_product_settings', $data, true );
		}

		/**
		 * Get product settings data.
		 *
		 * @return array Product settings data.
		 */
		public function get_product_settings_data() {
			return get_option( 'wkwctobag_product_settings', array() );
		}

		/**
		 * Get log retention policy.
		 *
		 * @return string Retention period ('1', '15', '30', or 'never').
		 */
		public function get_log_retention() {
			$retention = get_option( 'wkwctobag_log_retention', '30' );
			$allowed   = array( '1', '15', '30', 'never' );
			return in_array( $retention, $allowed, true ) ? $retention : '30';
		}

		/**
		 * Save log retention policy.
		 *
		 * @param string $retention Retention period.
		 * @return bool True on success.
		 */
		public function save_log_retention( $retention ) {
			$allowed = array( '1', '15', '30', 'never' );
			if ( in_array( $retention, $allowed, true ) ) {
				return update_option( 'wkwctobag_log_retention', $retention, true );
			}
			return false;
		}

		/**
		 * Get WooCommerce categories.
		 *
		 * @return array Array of categories.
		 */
		public function get_wc_categories() {
			$categories = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);

			$category_list = array();

			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
				foreach ( $categories as $category ) {
					$category_list[] = array(
						'id'   => $category->term_id,
						'name' => $category->name,
					);
				}
			}

			return $category_list;
		}

		/**
		 * Get users.
		 *
		 * Example $filters value:
		 *
		 *     array(
		 *         'exclude_role' => array( 'customer', 'subscriber' ),
		 *         'include_role' => array( 'customer', 'subscriber' ),
		 *         'include_ids'  => array( 1, 2, 3 ),
		 *         'exclude_ids'  => array( 1, 2, 3 ),
		 *         'fields'       => array( 'ID', 'user_login', 'user_email' ),
		 *     )
		 *
		 * @param string $search_query Search query.
		 * @param array  $filters      Filters.
		 *
		 * @return array Users array.
		 */
		public function get_users( $search_query = '', $filters = array() ) {
			$args = array(
				'search' => '*' . $search_query . '*',
				'number' => -1,
			);

			if ( ! empty( $filters['exclude_role'] ) && is_array( $filters['exclude_role'] ) ) {
				$args['role__not_in'] = $filters['exclude_role'];
			}

			if ( ! empty( $filters['include_role'] ) && is_array( $filters['include_role'] ) ) {
				$args['role__in'] = $filters['include_role'];
			}

			if ( ! empty( $filters['include_ids'] ) && is_array( $filters['include_ids'] ) ) {
				$args['include'] = $filters['include_ids'];
			}

			if ( ! empty( $filters['exclude_ids'] ) && is_array( $filters['exclude_ids'] ) ) {
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
				$args['exclude'] = $filters['exclude_ids'];
			}

			if ( ! empty( $filters['fields'] ) && is_array( $filters['fields'] ) ) {
				$args['fields'] = $filters['fields'];
			}

			$user_query = new \WP_User_Query( $args );
			$users      = $user_query->get_results();

			return $users;
		}

		/**
		 * Get products.
		 *
		 * Example $filters value:
		 *
		 *     array(
		 *         'include_ids' => array( 1, 2, 3 ),
		 *         'exclude_ids' => array( 1, 2, 3 ),
		 *     )
		 *
		 * @param string $search_query Search query.
		 * @param array  $filters      Filters.
		 *
		 * @return array Array of WC_Product objects.
		 */
		public function get_products( $search_query = '', $filters = array() ) {

			$args = array(
				'status' => 'publish',
				'limit'  => -1,
			);

			if ( ! empty( $search_query ) ) {
				$args['s'] = sanitize_text_field( $search_query );
			}

			if ( ! empty( $filters['include_ids'] ) && is_array( $filters['include_ids'] ) ) {
				$args['include'] = array_map( 'intval', $filters['include_ids'] );
			}

			if ( ! empty( $filters['exclude_ids'] ) && is_array( $filters['exclude_ids'] ) ) {
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
				$args['exclude'] = array_map( 'intval', $filters['exclude_ids'] );
			}

			$query = new \WC_Product_Query( $args );

			return $query->get_products();
		}

		/**
		 * Get categories.
		 *
		 * Example $filters value:
		 *
		 *     array(
		 *         'include_ids' => array( 1, 2, 3 ),
		 *         'exclude_ids' => array( 1, 2, 3 ),
		 *     )
		 *
		 * @param string $search_query Search query.
		 * @param array  $filters      Filters.
		 *
		 * @return array Array of WC_Category objects.
		 */
		public function get_categories( $search_query = '', $filters = array() ) {

			$args = array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 0,
			);

			if ( ! empty( $search_query ) ) {
				$args['search'] = sanitize_text_field( $search_query );
			}

			if ( ! empty( $filters['include_ids'] ) && is_array( $filters['include_ids'] ) ) {
				$args['include'] = array_map( 'intval', $filters['include_ids'] );
			}

			if ( ! empty( $filters['exclude_ids'] ) && is_array( $filters['exclude_ids'] ) ) {
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
				$args['exclude'] = array_map( 'intval', $filters['exclude_ids'] );
			}

			return get_terms( $args );
		}


		/**
		 * Get bagisto user id from WooCommerce user id.
		 *
		 * @param int $user_id WooCommerce user id.
		 *
		 * @return int|null Bagisto user id.
		 */
		public function get_bagisto_user_id( $user_id ) {
			$bagisto_user_id = get_user_meta( $user_id, '__wkwctobag_bagisto_user_id', true );
			return $bagisto_user_id ? intval( $bagisto_user_id ) : null;
		}

		/**
		 * Update bagisto user id for WooCommerce user id.
		 *
		 * @param int $user_id WooCommerce user id.
		 * @param int $bagisto_user_id Bagisto user id.
		 * @return void
		 */
		public function update_bagisto_user_id( $user_id, $bagisto_user_id ) {
			update_user_meta( $user_id, '__wkwctobag_bagisto_user_id', intval( $bagisto_user_id ) );
		}

		/**
		 * Get bagisto customer id from WooCommerce customer id.
		 *
		 * @param int $customer_id WooCommerce customer id.
		 *
		 * @return int|null Bagisto customer id.
		 */
		public function get_bagisto_customer_id( $customer_id ) {
			$bagisto_customer_id = get_user_meta( $customer_id, '__wkwctobag_bagisto_customer_id', true );
			return $bagisto_customer_id ? intval( $bagisto_customer_id ) : null;
		}

		/**
		 * Update bagisto customer id for WooCommerce customer id.
		 *
		 * @param int $customer_id WooCommerce customer id.
		 * @param int $bagisto_customer_id Bagisto customer id.
		 * @return void
		 */
		public function update_bagisto_customer_id( $customer_id, $bagisto_customer_id ) {
			update_user_meta( $customer_id, '__wkwctobag_bagisto_customer_id', intval( $bagisto_customer_id ) );
		}

		/**
		 * Get bagisto customer address id from WooCommerce customer id.
		 *
		 * @param int $customer_id WooCommerce customer id.
		 *
		 * @return int|null Bagisto customer address id.
		 */
		public function get_bagisto_customer_address_id( $customer_id ) {
			$bagisto_customer_address_id = get_user_meta( $customer_id, '__wkwctobag_bagisto_customer_address_id', true );
			return $bagisto_customer_address_id ? intval( $bagisto_customer_address_id ) : null;
		}

		/**
		 * Update bagisto customer address id for WooCommerce customer id.
		 *
		 * @param int $customer_id WooCommerce customer id.
		 * @param int $bagisto_customer_address_id Bagisto customer address id.
		 * @return void
		 */
		public function update_bagisto_customer_address_id( $customer_id, $bagisto_customer_address_id ) {
			update_user_meta( $customer_id, '__wkwctobag_bagisto_customer_address_id', intval( $bagisto_customer_address_id ) );
		}

		/**
		 * Get bagisto category id from WooCommerce category id.
		 *
		 * @param int $category_id WooCommerce category id.
		 *
		 * @return int|null Bagisto category id.
		 */
		public function get_bagisto_category_id( $category_id ) {
			$bagisto_category_id = get_term_meta( $category_id, '__wkwctobag_bagisto_category_id', true );
			return $bagisto_category_id ? intval( $bagisto_category_id ) : null;
		}

		/**
		 * Update bagisto category id for WooCommerce category id.
		 *
		 * @param int $category_id WooCommerce category id.
		 * @param int $bagisto_category_id Bagisto category id.
		 * @return void
		 */
		public function update_bagisto_category_id( $category_id, $bagisto_category_id ) {
			update_term_meta( $category_id, '__wkwctobag_bagisto_category_id', intval( $bagisto_category_id ) );
			delete_transient( 'wkwctobag_bagisto_categories_cache' );
		}

		/**
		 * Get bagisto product id for WooCommerce product id.
		 *
		 * @param int $product_id WooCommerce product id.
		 * @return int|null Bagisto product id.
		 */
		public function get_bagisto_product_id( $product_id ) {
			$bagisto_product_id = get_post_meta( $product_id, '__wkwctobag_bagisto_product_id', true );
			return $bagisto_product_id ? intval( $bagisto_product_id ) : null;
		}

		/**
		 * Update bagisto product id for WooCommerce product id.
		 *
		 * @param int $product_id WooCommerce product id.
		 * @param int $bagisto_product_id Bagisto product id.
		 * @return void
		 */
		public function update_bagisto_product_id( $product_id, $bagisto_product_id ) {
			update_post_meta( $product_id, '__wkwctobag_bagisto_product_id', intval( $bagisto_product_id ) );
		}

		/**
		 * Get bagisto order id from WooCommerce order id.
		 *
		 * @param int $order_id WooCommerce order id.
		 * @return int|null Bagisto order id.
		 */
		public function get_bagisto_order_id( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$bagisto_order_id = $order->get_meta( '__wkwctobag_bagisto_order_id', true );
				return $bagisto_order_id ? intval( $bagisto_order_id ) : null;
			}
			return null;
		}

		/**
		 * Update bagisto order id for WooCommerce order id.
		 *
		 * @param int $order_id WooCommerce order id.
		 * @param int $bagisto_order_id Bagisto order id.
		 * @return void
		 */
		public function update_bagisto_order_id( $order_id, $bagisto_order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->update_meta_data( '__wkwctobag_bagisto_order_id', intval( $bagisto_order_id ) );
				$order->save();
			}
		}


		/**
		 * Bagisto curl request.
		 *
		 * @param string $url Request URL.
		 * @param string $method Request method.
		 * @param array  $body Request body.
		 * @param array  $headers Request headers.
		 *
		 * @throws \Exception Exception, Validation error or api response error.
		 *
		 * @return $response Response from bagisto.
		 */
		public function bagisto_curl_request( $url = '', $method = 'POST', $body = array(), $headers = array() ) {
			if ( ! $this->is_connected_with_bagisto() ) {
				throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
			}

			if ( empty( $url ) ) {
				throw new \Exception( esc_html__( 'Invalid request url.', 'bagisto-sync-for-woocommerce' ) );
			}

			// Determine log source based on URL to dynamically route logs.
			$log_source = 'api';
			if ( strpos( $url, '/categories' ) !== false ) {
				$log_source = 'category';
			} elseif ( strpos( $url, '/products' ) !== false ) {
				$log_source = 'product';
			} elseif ( strpos( $url, '/customers' ) !== false || strpos( $url, '/users' ) !== false ) {
				$log_source = 'user';
			} elseif ( strpos( $url, '/orders' ) !== false || strpos( $url, '/shipments' ) !== false || strpos( $url, '/carts' ) !== false ) {
				$log_source = 'order';
			} elseif ( strpos( $url, '/attributes' ) !== false ) {
				$log_source = 'attribute';
			} elseif ( strpos( $url, '/taxes' ) !== false || strpos( $url, '/tax-rates' ) !== false || strpos( $url, '/tax-categories' ) !== false ) {
				$log_source = 'tax';
			}

			if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) && is_array( $body ) && ! $this->has_curl_file( $body ) ) {
				$body             = wp_json_encode( $body );
				$has_content_type = false;
				foreach ( $headers as $k => $v ) {
					if ( 'content-type' === strtolower( $k ) ) {
						$has_content_type = true;
						break;
					}
				}
				if ( ! $has_content_type ) {
					$headers['Content-Type'] = 'application/json';
				}
			}

			if ( 'PUT' === strtoupper( $method ) && is_array( $body ) ) {
				$method          = 'POST';
				$body['_method'] = 'PUT';
			}

			$args_headers = array(
				'accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->get_bagisto_admin_token(),
			);
			foreach ( $headers as $k => $v ) {
				$args_headers[ strtolower( $k ) ] = $v;
			}

			if ( is_array( $body ) ) {
				$body = $this->flatten_multipart_body( $body );
			}

			if ( is_array( $body ) && $this->has_curl_file( $body ) ) {
				$boundary       = '----WP' . md5( microtime() . wp_rand() );
				$multipart_body = '';

				foreach ( $body as $key => $value ) {
					if ( $value instanceof \CURLFile ) {
						$file_path = $value->getFilename();
						$file_name = $value->getPostFilename();
						$mime_type = $value->getMimeType() ? $value->getMimeType() : 'image/jpeg';
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local temp file into a multipart request body; WP_Filesystem adds no benefit here.
						$file_contents = ( file_exists( $file_path ) && is_readable( $file_path ) ) ? file_get_contents( $file_path ) : '';

						$multipart_body .= "--{$boundary}\r\n";
						$multipart_body .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"{$file_name}\"\r\n";
						$multipart_body .= "Content-Type: {$mime_type}\r\n\r\n";
						$multipart_body .= $file_contents . "\r\n";
					} else {
						$multipart_body .= "--{$boundary}\r\n";
						$multipart_body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
						$multipart_body .= $value . "\r\n";
					}
				}
				$multipart_body .= "--{$boundary}--\r\n";

				$args_headers['content-type'] = 'multipart/form-data; boundary=' . $boundary;
				$body                         = $multipart_body;
			}

			$args = array(
				'method'  => $method,
				'headers' => $args_headers,
				'body'    => $body,
				'timeout' => 45,
			);

			$api_response = wp_remote_request( $url, $args );

			if ( is_wp_error( $api_response ) ) {
				$this->logger->wkwctobag_error(
					sprintf(
					/* translators: 1: HTTP method, 2: request URL */
						'wp_remote_request Error [%1$s]: %2$s',
						esc_html( $method ),
						esc_html( $url )
					),
					array(
						'source' => $log_source,
						'error'  => $api_response->get_error_message(),
					)
				);
				throw new \Exception( esc_html__( 'Failed to connect to Bagisto API.', 'bagisto-sync-for-woocommerce' ) );
			}

			$http_code     = wp_remote_retrieve_response_code( $api_response );
			$response_body = wp_remote_retrieve_body( $api_response );

			if ( $http_code >= 400 ) {
				$error_msg = sprintf(
				/* translators: 1: HTTP status code, 2: HTTP method, 3: request URL */
					'API Error %1$d [%2$s]: %3$s',
					$http_code,
					esc_html( $method ),
					esc_html( $url )
				);
				if ( 401 === $http_code ) {
					$error_msg .= ' - Authentication failed (401). Your Bagisto token might be expired or invalid. Please disconnect and reconnect.';
					update_option( 'wkwctobag_token_expired', '1' );
				}
				$context_data = array(
					'source'      => $log_source,
					'http_status' => $http_code,
					'method'      => $method,
					'url'         => $url,
				);
				if ( ! empty( $response_body ) ) {
					$decoded_body             = json_decode( $response_body, true );
					$context_data['response'] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded_body : $response_body;
				}
				$this->logger->wkwctobag_error( $error_msg, $context_data );

				if ( 401 === $http_code ) {
					throw new \Exception( esc_html__( 'Invalid Token', 'bagisto-sync-for-woocommerce' ) );
				}
			}

			return $response_body;
		}

		/**
		 * Flatten multi-dimensional array into explicit bracket notation keys for cURL multipart request.
		 *
		 * @param array  $body The request body array to flatten.
		 * @param string $prefix Prefix for keys (used internally for recursion).
		 *
		 * @return array The flattened single-level array.
		 */
		public function flatten_multipart_body( $body, $prefix = '' ) {
			$flat = array();
			foreach ( $body as $key => $value ) {
				$flat_key = empty( $prefix ) ? $key : $prefix . '[' . $key . ']';
				if ( is_array( $value ) ) {
					$flat = array_merge( $flat, $this->flatten_multipart_body( $value, $flat_key ) );
				} elseif ( is_bool( $value ) ) {
					$flat[ $flat_key ] = $value ? 'true' : 'false';
				} else {
					$flat[ $flat_key ] = $value;
				}
			}
			return $flat;
		}

		/**
		 * Download an image/file from a URL and return a \CURLFile object.
		 *
		 * @param string $url The image/file URL.
		 *
		 * @return \CURLFile|false The CURLFile object on success, or false on failure.
		 */
		public function download_and_create_curl_file( $url ) {
			if ( empty( $url ) ) {
				return false;
			}

			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$temp_file = download_url( $url );

			if ( is_wp_error( $temp_file ) ) {
				return false;
			}

			$file_type = mime_content_type( $temp_file );
			$extension = '';

			if ( function_exists( 'exif_imagetype' ) && is_file( $temp_file ) ) {
				$image_type = exif_imagetype( $temp_file );
				if ( false !== $image_type ) {
					$extension = image_type_to_extension( $image_type );
				}
			}

			if ( empty( $extension ) ) {
				$path_info = pathinfo( wp_parse_url( $url, PHP_URL_PATH ) );
				if ( ! empty( $path_info['extension'] ) ) {
					$extension = '.' . $path_info['extension'];
				}
			}

			$final_path = $temp_file . $extension;

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				WP_Filesystem();
			}

			if ( $wp_filesystem && $wp_filesystem->move( $temp_file, $final_path, true ) ) {
				return new \CURLFile(
					$final_path,
					$file_type,
					basename( $final_path )
				);
			}

			return false;
		}

		/**
		 * Save tax settings.
		 *
		 * @param array $data Tax settings data.
		 */
		public function save_tax_settings( $data ) {
			update_option( 'wkwctobag_tax_settings', $data, true );
		}

		/**
		 * Get tax settings data.
		 *
		 * @return array Tax settings data.
		 */
		public function get_tax_settings_data() {
			return get_option( 'wkwctobag_tax_settings', array() );
		}

		/**
		 * Get bagisto tax category id from WooCommerce tax class.
		 *
		 * @param string $wc_tax_class WooCommerce tax class.
		 *
		 * @return int|null Bagisto tax category id.
		 */
		public function get_bagisto_tax_category_id( $wc_tax_class ) {
			$mappings = get_option( 'wkwctobag_tax_category_mappings', array() );

			if ( isset( $mappings[ $wc_tax_class ] ) ) {
				return intval( $mappings[ $wc_tax_class ] );
			}

			$sanitized_target = sanitize_title( $wc_tax_class );
			foreach ( $mappings as $key => $val ) {
				if ( sanitize_title( $key ) === $sanitized_target ) {
					return intval( $val );
				}
			}

			return null;
		}

		/**
		 * Update bagisto tax category id for WooCommerce tax class.
		 *
		 * @param string $wc_tax_class WooCommerce tax class.
		 * @param int    $bagisto_id   Bagisto tax category id.
		 * @return void
		 */
		public function update_bagisto_tax_category_id( $wc_tax_class, $bagisto_id ) {
			$mappings                  = get_option( 'wkwctobag_tax_category_mappings', array() );
			$mappings[ $wc_tax_class ] = intval( $bagisto_id );
			update_option( 'wkwctobag_tax_category_mappings', $mappings, true );
		}

		/**
		 * Get bagisto tax rate id from WooCommerce tax rate id.
		 *
		 * @param int $wc_rate_id WooCommerce tax rate id.
		 *
		 * @return int|null Bagisto tax rate id.
		 */
		public function get_bagisto_tax_rate_id( $wc_rate_id ) {
			$mappings = get_option( 'wkwctobag_tax_rate_mappings', array() );
			return isset( $mappings[ $wc_rate_id ] ) ? intval( $mappings[ $wc_rate_id ] ) : null;
		}

		/**
		 * Update bagisto tax rate id for WooCommerce tax rate id.
		 *
		 * @param int $wc_rate_id WooCommerce tax rate id.
		 * @param int $bagisto_id Bagisto tax rate id.
		 * @return void
		 */
		public function update_bagisto_tax_rate_id( $wc_rate_id, $bagisto_id ) {
			$mappings                = get_option( 'wkwctobag_tax_rate_mappings', array() );
			$mappings[ $wc_rate_id ] = intval( $bagisto_id );
			update_option( 'wkwctobag_tax_rate_mappings', $mappings, true );
		}

		/**
		 * Check if data contains a CURLFile recursively.
		 *
		 * @param mixed $data Data to check.
		 * @return bool
		 */
		private function has_curl_file( $data ) {
			if ( $data instanceof \CURLFile ) {
				return true;
			}
			if ( is_array( $data ) ) {
				foreach ( $data as $val ) {
					if ( $this->has_curl_file( $val ) ) {
						return true;
					}
				}
			}
			return false;
		}
	}
}
