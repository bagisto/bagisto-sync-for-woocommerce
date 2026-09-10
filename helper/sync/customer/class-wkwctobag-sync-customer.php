<?php
/**
 * Customer sync class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\Customer;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Sync_Customer' ) ) {
	/**
	 * Customer sync class.
	 */
	class Wkwctobag_Sync_Customer {
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

		/**
		 * Constructor of this class.
		 */
		public function __construct() {
			$this->helper           = Helper\Wkwctobag_Helper::get_instance();
			$this->bagisto_base_url = $this->helper->get_bagisto_site_url() . '/api/admin/customers';
		}

		/**
		 * Sync customer.
		 *
		 * @param array $customer_info Customer info.
		 *
		 * @throws \Exception If the record cannot be synchronised with Bagisto.
		 */
		public function sync( $customer_info ) {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( empty( $customer_info['user_id'] ) && 0 >= intval( $customer_info['user_id'] ) ) {
					throw new \Exception( esc_html__( 'Invalid customer ID.', 'bagisto-sync-for-woocommerce' ) );
				}

				$customer_id = intval( $customer_info['user_id'] );
				$this->helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce Customer ID: %d', $customer_id ), array( 'source' => 'customer' ) );

				if ( empty( $customer_info['group'] ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: User ID. */
							esc_html__( 'Customer group is required for customer id %d', 'bagisto-sync-for-woocommerce' ),
							$customer_id
						)
					);
				}

				if ( empty( $customer_info['gender'] ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: User ID. */
							esc_html__( 'Customer gender is required for customer id %d', 'bagisto-sync-for-woocommerce' ),
							$customer_id
						)
					);
				}

				$wc_customer_data = $this->get_customer_data( $customer_id );

				if ( empty( $wc_customer_data ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: User ID. */
							esc_html__( 'No customer data found for customer id %d', 'bagisto-sync-for-woocommerce' ),
							$customer_id
						)
					);
				}

				$customer_status_val = 1;
				if ( isset( $customer_info['customer_status'] ) ) {
					$customer_status_val = ( 'active' === $customer_info['customer_status'] || 1 === intval( $customer_info['customer_status'] ) ) ? 1 : 0;
				}

				$customer_data = array(
					'first_name'        => $wc_customer_data['first_name'],
					'last_name'         => $wc_customer_data['last_name'],
					'email'             => $wc_customer_data['email'],
					'gender'            => ! empty( $wc_customer_data['gender'] ) ? $wc_customer_data['gender'] : $customer_info['gender'],
					'date_of_birth'     => ! empty( $wc_customer_data['date_of_birth'] ) ? $wc_customer_data['date_of_birth'] : '',
					'phone'             => ! empty( $wc_customer_data['phone'] ) ? $wc_customer_data['phone'] : '',
					'customer_group_id' => ! empty( $wc_customer_data['customer_group_id'] ) ? $wc_customer_data['customer_group_id'] : intval( $customer_info['group'] ),
					'status'            => $customer_status_val,
				);

				$is_update = ! empty( $customer_info['is_update_existing_user'] ) && 'yes' === $customer_info['is_update_existing_user'] ? true : false;

				$customer_result = array();

				$insert_type = '';

				if ( $is_update ) {
					$bagisto_customer = $this->get_bagisto_customer_by_email( $customer_data['email'] );

					if ( ! empty( $bagisto_customer['data'] ) && ! empty( $bagisto_customer['data'][0] ) && ! empty( $bagisto_customer['data'][0]['id'] ) ) {

						$customer_data['date_of_birth'] = ! empty( $bagisto_customer['data'][0]['date_of_birth'] ) ? $bagisto_customer['data'][0]['date_of_birth'] : $customer_data['date_of_birth'];

						$customer_data['status'] = isset( $customer_info['customer_status'] ) ? $customer_status_val : ( isset( $bagisto_customer['data'][0]['status'] ) ? $bagisto_customer['data'][0]['status'] : 1 );

						$customer_data['phone'] = ! empty( $bagisto_customer['data'][0]['phone'] ) ? $bagisto_customer['data'][0]['phone'] : $customer_data['phone'];

						$customer_data['customer_group_id'] = ! empty( $customer_info['group'] ) ? intval( $customer_info['group'] ) : ( ! empty( $bagisto_customer['data'][0]['group'] ) && ! empty( $bagisto_customer['data'][0]['group']['id'] ) ? $bagisto_customer['data'][0]['group']['id'] : $customer_data['customer_group_id'] );

						$customer_data['gender'] = ! empty( $customer_info['gender'] ) ? $customer_info['gender'] : ( ! empty( $bagisto_customer['data'][0]['gender'] ) ? $bagisto_customer['data'][0]['gender'] : $customer_data['gender'] );

						$customer_data['is_suspended'] = ! empty( $bagisto_customer['data'][0]['is_suspended'] ) ? $bagisto_customer['data'][0]['is_suspended'] : 0;

						$customer_result = $this->update_customer_with_bagisto( $bagisto_customer['data'][0]['id'], $customer_data );

						if ( ! empty( $customer_result['detail'] ) && strpos( $customer_result['detail'], 'Duplicate entry' ) !== false && strpos( $customer_result['detail'], 'customers_phone_unique' ) !== false ) {
							$this->helper->logger->wkwctobag_error( sprintf( 'Duplicate phone number constraint violation for Customer ID %d. Retrying update with null phone.', $customer_id ), array( 'source' => 'customer' ) );
							$customer_data['phone'] = null;
							$customer_result        = $this->update_customer_with_bagisto( $bagisto_customer['data'][0]['id'], $customer_data );
						}

						$insert_type = 'update';

					} else {
						$customer_result = $this->sync_customer_with_bagisto( $customer_data );

						if ( ! empty( $customer_result['detail'] ) && strpos( $customer_result['detail'], 'Duplicate entry' ) !== false && strpos( $customer_result['detail'], 'customers_phone_unique' ) !== false ) {
							$this->helper->logger->wkwctobag_error( sprintf( 'Duplicate phone number constraint violation for Customer ID %d. Retrying sync with null phone.', $customer_id ), array( 'source' => 'customer' ) );
							$customer_data['phone'] = null;
							$customer_result        = $this->sync_customer_with_bagisto( $customer_data );
						}

						$insert_type = 'insert';
					}
				} else {
					$customer_result = $this->sync_customer_with_bagisto( $customer_data );

					if ( ! empty( $customer_result['detail'] ) && strpos( $customer_result['detail'], 'Duplicate entry' ) !== false && strpos( $customer_result['detail'], 'customers_phone_unique' ) !== false ) {
						$this->helper->logger->wkwctobag_error( sprintf( 'Duplicate phone number constraint violation for Customer ID %d. Retrying sync with null phone.', $customer_id ), array( 'source' => 'customer' ) );
						$customer_data['phone'] = null;
						$customer_result        = $this->sync_customer_with_bagisto( $customer_data );
					}

					$insert_type = 'insert';
				}

				$cust_data = array();
				if ( ! empty( $customer_result['id'] ) ) {
					$cust_data = $customer_result;
				} elseif ( ! empty( $customer_result['data']['id'] ) ) {
					$cust_data = $customer_result['data'];
				}

				if ( ! empty( $customer_result['errors'] ) || empty( $cust_data ) || empty( $cust_data['id'] ) ) {
					$error_message = esc_html__( 'Failed to sync customer with Bagisto.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $customer_result['detail'] ) ) {
						$error_message = $customer_result['detail'];
					} elseif ( ! empty( $customer_result['message'] ) ) {
						$error_message = $customer_result['message'];
					} elseif ( ! empty( $customer_result['error'] ) ) {
						$error_message = $customer_result['error'];
					} elseif ( ! empty( $customer_result['title'] ) ) {
						$error_message = $customer_result['title'];
					}
					throw new \Exception( $error_message );
				}

				$bagisto_cust_id = intval( $cust_data['id'] );
				$this->helper->update_bagisto_customer_id( $customer_id, $bagisto_cust_id );

				if ( 'insert' === $insert_type || 'update' === $insert_type ) {
					$customer_address = array(
						'company_name'    => ! empty( $wc_customer_data['billing_address']['company'] ) ? $wc_customer_data['billing_address']['company'] : ( ! empty( $wc_customer_data['shipping_address']['company'] ) ? $wc_customer_data['shipping_address']['company'] : '' ),
						'vat_id'          => ! empty( $wc_customer_data['billing_address']['vat_id'] ) ? $wc_customer_data['billing_address']['vat_id'] : ( ! empty( $wc_customer_data['shipping_address']['vat_id'] ) ? $wc_customer_data['shipping_address']['vat_id'] : '' ),
						'first_name'      => ! empty( $wc_customer_data['billing_address']['first_name'] ) ? $wc_customer_data['billing_address']['first_name'] : ( ! empty( $wc_customer_data['shipping_address']['first_name'] ) ? $wc_customer_data['shipping_address']['first_name'] : '' ),
						'last_name'       => ! empty( $wc_customer_data['billing_address']['last_name'] ) ? $wc_customer_data['billing_address']['last_name'] : ( ! empty( $wc_customer_data['shipping_address']['last_name'] ) ? $wc_customer_data['shipping_address']['last_name'] : '' ),
						'address'         => array(
							! empty( trim( $wc_customer_data['billing_address']['address_1'] ) ) ? trim( $wc_customer_data['billing_address']['address_1'] ) : '',
							! empty( trim( $wc_customer_data['billing_address']['address_2'] ) ) ? trim( $wc_customer_data['billing_address']['address_2'] ) : '',
						),

						'country'         => ! empty( $wc_customer_data['billing_address']['country'] ) ? $wc_customer_data['billing_address']['country'] : ( ! empty( $wc_customer_data['shipping_address']['country'] ) ? $wc_customer_data['shipping_address']['country'] : '' ),
						'state'           => ! empty( $wc_customer_data['billing_address']['state'] ) ? $wc_customer_data['billing_address']['state'] : ( ! empty( $wc_customer_data['shipping_address']['state'] ) ? $wc_customer_data['shipping_address']['state'] : '' ),
						'city'            => ! empty( $wc_customer_data['billing_address']['city'] ) ? $wc_customer_data['billing_address']['city'] : ( ! empty( $wc_customer_data['shipping_address']['city'] ) ? $wc_customer_data['shipping_address']['city'] : '' ),
						'postcode'        => ! empty( $wc_customer_data['billing_address']['postcode'] ) ? $wc_customer_data['billing_address']['postcode'] : ( ! empty( $wc_customer_data['shipping_address']['postcode'] ) ? $wc_customer_data['shipping_address']['postcode'] : '' ),
						'email'           => $wc_customer_data['email'],
						'phone'           => $wc_customer_data['phone'],
						'default_address' => 0,
					);

					if ( ! empty( $customer_address['first_name'] ) &&
							! empty( $customer_address['last_name'] ) &&
							! empty( $customer_address['address'] ) &&
							! empty( $customer_address['country'] ) &&
							! empty( $customer_address['state'] ) &&
							! empty( $customer_address['city'] ) &&
							! empty( $customer_address['postcode'] ) &&
							! empty( $customer_address['phone'] ) &&
							! empty( $customer_address['email'] ) ) {

						$address_result = array();

						if ( 'insert' === $insert_type ) {
							$address_result = $this->sync_customer_address_with_bagisto( $bagisto_cust_id, $customer_address );
						}

						if ( 'update' === $insert_type ) {
							$address_id = $this->helper->get_bagisto_customer_address_id( $wc_customer_data['id'] );

							if ( empty( $address_id ) ) {
								$address_result = $this->sync_customer_address_with_bagisto( $bagisto_cust_id, $customer_address );

							} else {
								$bagisto_customer_address = $this->get_customer_address_by_id( $bagisto_cust_id, $address_id );

								$addr_check = array();
								if ( ! empty( $bagisto_customer_address['id'] ) ) {
									$addr_check = $bagisto_customer_address;
								} elseif ( ! empty( $bagisto_customer_address['data']['id'] ) ) {
									$addr_check = $bagisto_customer_address['data'];
								}

								if ( ! empty( $addr_check ) && ! empty( $addr_check['id'] ) ) {
									$address_result = $this->update_customer_address_with_bagisto( $bagisto_cust_id, $address_id, $customer_address );
								} else {
									$address_result = $this->sync_customer_address_with_bagisto( $bagisto_cust_id, $customer_address );
								}
							}
						}

						$addr_data = array();
						if ( ! empty( $address_result['id'] ) ) {
							$addr_data = $address_result;
						} elseif ( ! empty( $address_result['data']['id'] ) ) {
							$addr_data = $address_result['data'];
						}

						if ( ! empty( $addr_data ) && ! empty( $addr_data['id'] ) ) {
							$this->helper->update_bagisto_customer_address_id( $wc_customer_data['id'], $addr_data['id'] );
						}
					}
				}

				$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce Customer ID %d to Bagisto Customer ID %d', $customer_id, $bagisto_cust_id ), array( 'source' => 'customer' ) );

				$response['success'] = true;
				$response['message'] = ! empty( $customer_result['message'] ) ? $customer_result['message'] : esc_html__( 'Customer synced successfully with Bagisto.', 'bagisto-sync-for-woocommerce' );
				$response['data']    = $cust_data;

			} catch ( \Exception $ex ) {
				$response['message'] = $ex->getMessage();
				$this->helper->logger->wkwctobag_error( sprintf( 'Customer sync failed: %s', $ex->getMessage() ), array( 'source' => 'customer' ) );
			}

			return $response;
		}

		/**
		 * Get customer data.
		 *
		 * @param int $customer_id Customer ID.
		 * @throws \Exception If the customer cannot be found.
		 *
		 * @return array Customer data.
		 */
		public function get_customer_data( $customer_id ) {
			$user_data = array();

			$user = new \WP_User( $customer_id );

			if ( ! $user || 0 === $user->get_id() ) {
				throw new \Exception( esc_html__( 'Customer not found.', 'bagisto-sync-for-woocommerce' ) );
			}

			$customer = new \WC_Customer( $customer_id );

			/*
			 * Resolve each WooCommerce getter once up front. The fallback chains
			 * below are the long form of the short ternaries they replace, and
			 * hoisting keeps every getter called exactly once as before.
			 */
			$account_first_name  = $customer->get_first_name();
			$account_last_name   = $customer->get_last_name();
			$account_email       = $customer->get_email();
			$billing_first_name  = $customer->get_billing_first_name();
			$billing_last_name   = $customer->get_billing_last_name();
			$billing_email       = $customer->get_billing_email();
			$billing_phone       = $customer->get_billing_phone();
			$shipping_first_name = $customer->get_shipping_first_name();
			$shipping_last_name  = $customer->get_shipping_last_name();

			$customer_data = array(
				'id'               => $customer_id,
				'display_name'     => $user->display_name,
				'first_name'       => $account_first_name ? $account_first_name : ( $user->first_name ? $user->first_name : $user->display_name ),
				'last_name'        => $account_last_name ? $account_last_name : ( $user->last_name ? $user->last_name : $user->display_name ),
				'email'            => $account_email ? $account_email : $user->user_email,
				'gender'           => get_user_meta( $customer_id, 'gender', true ),
				'date_of_birth'    => get_user_meta( $customer_id, 'date_of_birth', true ),
				'phone'            => $billing_phone ? $billing_phone : get_user_meta( $customer_id, 'phone', true ),
				'billing_address'  => array(
					'first_name' => $billing_first_name ? $billing_first_name : ( $user->first_name ? $user->first_name : $user->display_name ),
					'last_name'  => $billing_last_name ? $billing_last_name : ( $user->last_name ? $user->last_name : $user->display_name ),
					'email'      => $billing_email ? $billing_email : $user->user_email,
					'company'    => $customer->get_billing_company(),
					'address_1'  => $customer->get_billing_address_1(),
					'address_2'  => $customer->get_billing_address_2(),
					'city'       => $customer->get_billing_city(),
					'postcode'   => $customer->get_billing_postcode(),
					'state'      => $customer->get_billing_state(),
					'country'    => $customer->get_billing_country(),
					'phone'      => $customer->get_billing_phone(),
				),
				'shipping_address' => array(
					'first_name' => $shipping_first_name ? $shipping_first_name : ( $user->first_name ? $user->first_name : $user->display_name ),
					'last_name'  => $shipping_last_name ? $shipping_last_name : ( $user->last_name ? $user->last_name : $user->display_name ),
					'company'    => $customer->get_shipping_company(),
					'address_1'  => $customer->get_shipping_address_1(),
					'address_2'  => $customer->get_shipping_address_2(),
					'city'       => $customer->get_shipping_city(),
					'postcode'   => $customer->get_shipping_postcode(),
					'state'      => $customer->get_shipping_state(),
					'country'    => $customer->get_shipping_country(),
					'phone'      => $customer->get_shipping_phone(),
				),
			);
			return $customer_data;
		}

		/**
		 * Sync customer with Bagisto.
		 *
		 * @param array $customer_data Customer data.
		 *
		 * @throws \Exception If unable to sync customer.
		 *
		 * @return $response Response from Bagisto.
		 */
		private function sync_customer_with_bagisto( $customer_data ) {
			try {
				$url = $this->bagisto_base_url;

				$gender_raw = ! empty( $customer_data['gender'] ) ? $customer_data['gender'] : '';
				$gender_val = null;
				if ( ! empty( $gender_raw ) ) {
					$gender_title = ucfirst( strtolower( trim( $gender_raw ) ) );
					if ( in_array( $gender_title, array( 'Male', 'Female', 'Other' ), true ) ) {
						$gender_val = $gender_title;
					}
				}

				$body = array(
					'first_name'        => $customer_data['first_name'],
					'last_name'         => $customer_data['last_name'],
					'email'             => $customer_data['email'],
					'gender'            => $gender_val,
					'date_of_birth'     => ! empty( $customer_data['date_of_birth'] ) && '0000-00-00' !== $customer_data['date_of_birth'] ? $customer_data['date_of_birth'] : null,
					'phone'             => ! empty( $customer_data['phone'] ) ? strval( $customer_data['phone'] ) : null,
					'customer_group_id' => intval( $customer_data['customer_group_id'] ),
					'status'            => intval( $customer_data['status'] ),
					'send_password'     => true,
				);

				$response = $this->helper->bagisto_curl_request( $url, 'POST', $body );

				return json_decode( $response, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Update customer with Bagisto.
		 *
		 * @param int   $bagisto_customer_id Bagisto customer ID.
		 * @param array $customer_data Customer data.
		 *
		 * @throws \Exception Validation error or exception.
		 *
		 * @return $response Response from bagisto.
		 */
		private function update_customer_with_bagisto( $bagisto_customer_id, $customer_data ) {
			try {
				$url = $this->bagisto_base_url . '/' . $bagisto_customer_id;

				$gender_raw = ! empty( $customer_data['gender'] ) ? $customer_data['gender'] : '';
				$gender_val = null;
				if ( ! empty( $gender_raw ) ) {
					$gender_title = ucfirst( strtolower( trim( $gender_raw ) ) );
					if ( in_array( $gender_title, array( 'Male', 'Female', 'Other' ), true ) ) {
						$gender_val = $gender_title;
					}
				}

				$body = array(
					'first_name'        => $customer_data['first_name'],
					'last_name'         => $customer_data['last_name'],
					'email'             => $customer_data['email'],
					'gender'            => $gender_val,
					'date_of_birth'     => ! empty( $customer_data['date_of_birth'] ) && '0000-00-00' !== $customer_data['date_of_birth'] ? $customer_data['date_of_birth'] : null,
					'phone'             => ! empty( $customer_data['phone'] ) ? strval( $customer_data['phone'] ) : null,
					'customer_group_id' => intval( $customer_data['customer_group_id'] ),
					'status'            => intval( $customer_data['status'] ),
					'is_suspended'      => ! empty( $customer_data['is_suspended'] ) ? 1 : 0,
				);

				$result = $this->helper->bagisto_curl_request( $url, 'PUT', $body );

				return json_decode( $result, true );
			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get Bagisto customer by ID.
		 *
		 * @param int $customer_id Customer ID.
		 *
		 * @throws \Exception If unable to get customer or validation error.
		 *
		 * @return array $response Bagisto customer response.
		 */
		public function get_bagisto_customer_by_id( $customer_id ) {
			try {
				$url    = $this->bagisto_base_url . '/' . $customer_id;
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );

				return json_decode( $result, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get Bagisto customer by email.
		 *
		 * @param string $email Customer email.
		 *
		 * @throws \Exception If unable to get customer or validation error.
		 *
		 * @return array $response Bagisto customer response.
		 */
		public function get_bagisto_customer_by_email( $email ) {
			try {
				$url    = $this->bagisto_base_url . '/?email=' . $email;
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );
				return json_decode( $result, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Sync customer address with Bagisto.
		 *
		 * @param int   $bagisto_customer_id Bagisto customer ID.
		 * @param array $customer_address Customer address.
		 *
		 * @throws \Exception If unable to sync customer address or validation error.
		 *
		 * @return array $response Bagisto customer address response.
		 */
		private function sync_customer_address_with_bagisto( $bagisto_customer_id, $customer_address ) {
			try {
				$url = $this->bagisto_base_url . '/' . $bagisto_customer_id . '/addresses';

				$address_val = '';
				if ( is_array( $customer_address['address'] ) ) {
					$address_val = implode( "\n", array_filter( $customer_address['address'] ) );
				} else {
					$address_val = $customer_address['address'];
				}

				$body = array(
					'company_name'    => $customer_address['company_name'] ? $customer_address['company_name'] : null,
					'vat_id'          => $customer_address['vat_id'] ? $customer_address['vat_id'] : null,
					'first_name'      => $customer_address['first_name'],
					'last_name'       => $customer_address['last_name'],
					'address'         => $address_val,
					'country'         => $customer_address['country'],
					'state'           => $customer_address['state'] ? $customer_address['state'] : null,
					'city'            => $customer_address['city'],
					'postcode'        => $customer_address['postcode'],
					'email'           => $customer_address['email'] ? $customer_address['email'] : null,
					'phone'           => $customer_address['phone'],
					'default_address' => $customer_address['default_address'] ? true : false,
				);

				$result = $this->helper->bagisto_curl_request( $url, 'POST', $body );
				return json_decode( $result, true );
			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Update customer address with Bagisto.
		 *
		 * @param int   $bagisto_customer_id Bagisto customer ID.
		 * @param int   $address_id          Bagisto customer address ID.
		 * @param array $customer_address     Customer address.
		 *
		 * @throws \Exception If unable to update customer address or validation error.
		 *
		 * @return array $response Bagisto customer address response.
		 */
		private function update_customer_address_with_bagisto( $bagisto_customer_id, $address_id, $customer_address ) {
			try {
				$url = $this->bagisto_base_url . '/' . $bagisto_customer_id . '/addresses/' . $address_id;

				$address_val = '';
				if ( is_array( $customer_address['address'] ) ) {
					$address_val = implode( "\n", array_filter( $customer_address['address'] ) );
				} else {
					$address_val = $customer_address['address'];
				}

				$body = array(
					'company_name'    => $customer_address['company_name'] ? $customer_address['company_name'] : null,
					'vat_id'          => $customer_address['vat_id'] ? $customer_address['vat_id'] : null,
					'first_name'      => $customer_address['first_name'],
					'last_name'       => $customer_address['last_name'],
					'address'         => $address_val,
					'country'         => $customer_address['country'],
					'state'           => $customer_address['state'] ? $customer_address['state'] : null,
					'city'            => $customer_address['city'],
					'postcode'        => $customer_address['postcode'],
					'email'           => $customer_address['email'] ? $customer_address['email'] : null,
					'phone'           => $customer_address['phone'],
					'default_address' => $customer_address['default_address'] ? true : false,
				);

				$result = $this->helper->bagisto_curl_request( $url, 'PUT', $body );

				return json_decode( $result, true );
			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get Bagisto customer address by ID.
		 *
		 * @param int $bagisto_customer_id Bagisto customer ID.
		 * @param int $address_id Bagisto customer address ID.
		 *
		 * @throws \Exception If unable to get customer address or validation error.
		 *
		 * @return array $response Bagisto customer address response.
		 */
		public function get_customer_address_by_id( $bagisto_customer_id, $address_id ) {
			try {
				$url    = $this->bagisto_base_url . '/' . $bagisto_customer_id . '/addresses/' . $address_id;
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );

				return json_decode( $result, true );

			} catch ( \Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get Bagisto customer groups.
		 *
		 * @throws \Exception If unable to get customer groups or validation error.
		 *
		 * @return array $response Bagisto customer groups response.
		 */
		public function get_bagisto_customer_groups() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( ! $this->helper->is_connected_with_bagisto() ) {
					throw new \Exception( esc_html__( 'Not connected with Bagisto. Please create connection with Bagisto then try again.', 'bagisto-sync-for-woocommerce' ) );
				}

				$url = $this->helper->get_bagisto_site_url() . '/api/admin/customers/groups?per_page=500&sort=id&order=asc';

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
