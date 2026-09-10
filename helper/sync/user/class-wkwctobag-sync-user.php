<?php
/**
 * User sync class file.
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\User;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Sync_User' ) ) {
	/**
	 * User sync class.
	 */
	class Wkwctobag_Sync_User {
		/**
		 * Bagisto base URL.
		 *
		 * @var string $bagisto_base_url Bagisto base URL.
		 */
		private $bagisto_base_url = '';

		/**
		 * Helper class instance.
		 *
		 * @var Wkwctobag_Helper $helper Helper class instance.
		 */
		protected $helper = null;

		public function __construct() {
			$this->helper = Helper\Wkwctobag_Helper::get_instance();

			$this->bagisto_base_url = $this->helper->get_bagisto_site_url() . '/api/admin/settings';
		}

		/**
		 * Sync user.
		 *
		 * @throws \Exception Validation error or exception.
		 *
		 * @param int $user_info User info.
		 */
		public function sync( $user_info ) {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( empty( $user_info['user_id'] ) && 0 >= intval( $user_info['user_id'] ) ) {
					throw new \Exception( esc_html__( 'Invalid user ID.', 'bagisto-sync-for-woocommerce' ) );
				}

				$user_id = intval( $user_info['user_id'] );
				$this->helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce User ID: %d', $user_id ), array( 'source' => 'user' ) );

				if ( empty( $user_info['role'] ) && 0 >= intval( $user_info['role'] ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: User ID. */
							esc_html__( 'Invalid user role for user id %d', 'bagisto-sync-for-woocommerce' ),
							$user_id
						)
					);
				}

				$data = $this->get_user_data( $user_id );

				if ( empty( $data ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: User ID. */
							esc_html__( 'No user data found for user id %d', 'bagisto-sync-for-woocommerce' ),
							$user_id
						)
					);
				}

				$sync_data = array(
					'name'      => ! empty( trim( $data['first_name'] . ' ' . $data['last_name'] ) ) ? ( $data['first_name'] . ' ' . $data['last_name'] ) : $data['display_name'],
					'email'     => $data['user_email'],
					'password'  => wp_generate_password( 12, false ),
					'status'    => ! empty( $user_info['user_status'] ) && 'inactive' === $user_info['user_status'] ? 0 : 1,
					'role_id'   => intval( $user_info['role'] ),
					'is_update' => ! empty( $user_info['is_update_existing_user'] ) && 'yes' === $user_info['is_update_existing_user'] ? true : false,
				);

				$result = array();
				if ( $sync_data['is_update'] ) {
					$bagisto_user = $this->get_bagisto_user_by_email( $sync_data['email'] );

					if ( ! empty( $bagisto_user['data'] ) && ! empty( $bagisto_user['data'][0] ) && ! empty( $bagisto_user['data'][0]['id'] ) ) {
						$sync_data['role_id'] = ! empty( $bagisto_user['data'][0]['role'] ) && ! empty( $bagisto_user['data'][0]['role']['id'] ) ? $bagisto_user['data'][0]['role']['id'] : $sync_data['role_id'];

						$result = $this->update_user_with_bagisto( $bagisto_user['data'][0]['id'], $sync_data );
					} else {
						$result = $this->sync_user_with_bagisto( $sync_data );
					}
				} else {
					$result = $this->sync_user_with_bagisto( $sync_data );
				}

				$user_res = array();
				if ( ! empty( $result['id'] ) ) {
					$user_res = $result;
				} elseif ( ! empty( $result['user'] ) && ! empty( $result['user']['id'] ) ) {
					$user_res = $result['user'];
				} elseif ( ! empty( $result['data'] ) && ! empty( $result['data']['id'] ) ) {
					$user_res = $result['data'];
				}

				if ( empty( $result['errors'] ) && ! empty( $user_res['id'] ) ) {
					$this->helper->update_bagisto_user_id( $user_id, $user_res['id'] );
					$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce User ID %d to Bagisto User ID %d', $user_id, $user_res['id'] ), array( 'source' => 'user' ) );

					$response['success'] = true;
					$response['message'] = ! empty( $result['message'] ) ? $result['message'] : esc_html__( 'User synced successfully with Bagisto.', 'bagisto-sync-for-woocommerce' );
					$response['data']    = $user_res;

				} else {
					$error_message = esc_html__( 'Failed to sync user with Bagisto.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $result['detail'] ) ) {
						$error_message = $result['detail'];
					} elseif ( ! empty( $result['message'] ) ) {
						$error_message = $result['message'];
					} elseif ( ! empty( $result['error'] ) ) {
						$error_message = $result['error'];
					} elseif ( ! empty( $result['title'] ) ) {
						$error_message = $result['title'];
					}
					throw new \Exception( $error_message );
				}
			} catch ( \Exception $ex ) {
				$response['message'] = $ex->getMessage();
				$this->helper->logger->wkwctobag_error( sprintf( 'User sync failed: %s', $ex->getMessage() ), array( 'source' => 'user' ) );
			}

			return $response;
		}

		/**
		 * Get user data.
		 *
		 * @param int $user_id User id.
		 *
		 * @throws \Exception If user not found.
		 *
		 * @return array User data.
		 */
		private function get_user_data( $user_id ) {
			$user = new \WP_User( $user_id );

			if ( ! $user || 0 === $user->get_id() ) {
				throw new \Exception( esc_html__( 'User not found.', 'bagisto-sync-for-woocommerce' ) );
			}

			$user_data = array(
				'id'           => $user->ID,
				'user_login'   => $user->user_login,
				'user_email'   => $user->user_email,
				'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
				'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
				'roles'        => $user->roles,
				'display_name' => $user->display_name,
			);

			return $user_data;
		}

		/**
		 * Sync user with Bagisto.
		 *
		 * @param array $user_data User data.
		 *
		 * @throws \Exception If unable to sync user.
		 *
		 * @return $response Response from Bagisto.
		 */
		private function sync_user_with_bagisto( $user_data ) {
			try {
				$url  = $this->bagisto_base_url . '/users';
				$body = array(
					'name'     => $user_data['name'],
					'email'    => $user_data['email'],
					'password' => $user_data['password'],
					'status'   => intval( $user_data['status'] ),
					'role_id'  => intval( $user_data['role_id'] ),
				);

				$result = $this->helper->bagisto_curl_request( $url, 'POST', $body );
				return json_decode( $result, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Update user with Bagisto.
		 *
		 * @param int   $bagisto_user_id Bagisto user ID.
		 * @param array $user_data User data.
		 *
		 * @throws \Exception Validation error or exception.
		 *
		 * @return $response Response from bagisto.
		 */
		private function update_user_with_bagisto( $bagisto_user_id, $user_data ) {
			try {
				$url  = $this->bagisto_base_url . '/users/' . $bagisto_user_id;
				$body = array(
					'name'    => $user_data['name'],
					'email'   => $user_data['email'],
					'role_id' => intval( $user_data['role_id'] ),
					'status'  => intval( $user_data['status'] ),
				);

				$result = $this->helper->bagisto_curl_request( $url, 'PUT', $body );

				return json_decode( $result, true );
			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get bagisto user.
		 *
		 * @param int $user_id User ID.
		 *
		 * @throws \Exception Validation error or exception.
		 *
		 * @return array $response User data response.
		 */
		public function get_bagisto_user_by_id( $user_id ) {
			try {
				$url    = $this->bagisto_base_url . '/users/' . $user_id;
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );

				return json_decode( $result, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get bagisto user by email.
		 *
		 * @param string $email User email.
		 *
		 * @throws \Exception Validation error or exception.
		 *
		 * @return array $response User data response.
		 */
		public function get_bagisto_user_by_email( $email ) {
			try {
				$url    = $this->bagisto_base_url . '/users?email=' . $email;
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );

				return json_decode( $result, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get user Bagisto roles.
		 *
		 * @throws \Exception Validation error or exception.
		 *
		 * @return array $response User roles response.
		 */
		public function get_bagisto_user_roles() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				$url    = $this->bagisto_base_url . '/roles?per_page=500&sort=id&order=asc';
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );
				$data   = json_decode( $result, true );

				if ( isset( $data['data'] ) ) {
					$response['success'] = true;
					$response['data']    = $data['data'];
				} else {
					$response['message'] = esc_html__( 'Invalid response from Bagisto API.', 'bagisto-sync-for-woocommerce' );
				}
			} catch ( \Exception $ex ) {
				$response['message'] = $ex->getMessage();
			}
			return $response;
		}
	}
}
