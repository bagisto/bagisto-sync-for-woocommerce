<?php
/**
 * Order sync class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\Order;

defined( 'ABSPATH' ) || exit(); // Exit if accessed directly.

if ( ! class_exists( 'Wkwctobag_Sync_Order' ) ) {
	/**
	 * Order sync class.
	 */
	class Wkwctobag_Sync_Order {
		/**
		 * Order ID.
		 *
		 * @var int $order_id Order ID.
		 */
		protected $order_id = 0;

		/**
		 * Helper instance.
		 *
		 * @var \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper $helper Helper instance.
		 */
		protected $helper = null;

		/**
		 * Demo Shipping Product ID.
		 *
		 * @var int $demo_product_id Demo product ID.
		 */
		protected $demo_product_id = 0;

		/**
		 * Temporary custom price Product IDs.
		 *
		 * @var array $temp_product_ids Temporary product IDs.
		 */
		protected $temp_product_ids = array();

		/**
		 * Constructor of the class.
		 *
		 * @param int $order_id Order ID.
		 */
		public function __construct( $order_id = 0 ) {
			$this->helper = \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance();
			if ( 0 < $order_id ) {
				$this->order_id = intval( $order_id );
			}
		}

		/**
		 * Sync order.
		 *
		 * @param array $order_info Order info (optionally passed from queue runner).
		 */
		public function sync( $order_info = array() ) {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( ! empty( $order_info['order_id'] ) ) {
					$this->order_id = intval( $order_info['order_id'] );
				}

				if ( 0 >= $this->order_id ) {
					throw new \Exception( esc_html__( 'Invalid order ID.', 'bagisto-sync-for-woocommerce' ) );
				}

				$order = wc_get_order( $this->order_id );
				if ( ! $order || 0 === $order->get_id() ) {
					throw new \Exception( esc_html__( 'Order not found.', 'bagisto-sync-for-woocommerce' ) );
				}

				$order_items = $order->get_items();
				if ( empty( $order_items ) ) {
					throw new \Exception( esc_html__( 'Failed to sync: The order contains no items.', 'bagisto-sync-for-woocommerce' ) );
				}

				$this->helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce Order ID: %d', $this->order_id ), array( 'source' => 'order' ) );

				$bagisto_order_id = $this->helper->get_bagisto_order_id( $this->order_id );

				if ( empty( $bagisto_order_id ) ) {
					// 1. Resolve customer
					$bagisto_customer_id = $this->resolve_order_customer( $order );
					if ( ! $bagisto_customer_id ) {
						throw new \Exception( esc_html__( 'Failed to resolve or create customer in Bagisto.', 'bagisto-sync-for-woocommerce' ) );
					}

					// 2. Create Draft Cart
					$cart_id = $this->create_draft_cart( $bagisto_customer_id );
					if ( ! $cart_id ) {
						throw new \Exception( esc_html__( 'Failed to create draft cart in Bagisto.', 'bagisto-sync-for-woocommerce' ) );
					}
					$this->helper->logger->wkwctobag_info( sprintf( 'Created draft cart in Bagisto. Cart ID: %d', $cart_id ), array( 'source' => 'order' ) );

					// 3. Add Line Items
					$cart_id = $this->add_order_items_to_cart( $cart_id, $order );
					$this->helper->logger->wkwctobag_info( sprintf( 'Successfully added WooCommerce line items to draft cart ID: %d', $cart_id ), array( 'source' => 'order' ) );

					// 4. Save Billing and Shipping Address
					$cart_id = $this->save_cart_addresses( $cart_id, $order );
					$this->helper->logger->wkwctobag_info( sprintf( 'Saved billing and shipping addresses for draft cart ID: %d', $cart_id ), array( 'source' => 'order' ) );

					// 5. Select Shipping Method
					$this->save_cart_shipping_method( $cart_id, $order );
					$this->helper->logger->wkwctobag_info( sprintf( 'Selected shipping method for draft cart ID: %d', $cart_id ), array( 'source' => 'order' ) );

					// 6. Select Payment Method
					$this->save_cart_payment_method( $cart_id, $order );
					$this->helper->logger->wkwctobag_info( sprintf( 'Selected payment method for draft cart ID: %d', $cart_id ), array( 'source' => 'order' ) );

					$coupon_codes = $order->get_coupon_codes();
					if ( ! empty( $coupon_codes ) ) {
						foreach ( $coupon_codes as $coupon_code ) {
							try {
								$this->apply_cart_coupon( $cart_id, $coupon_code );
								$this->helper->logger->wkwctobag_info( sprintf( 'Applied coupon "%s" to draft cart ID: %d', $coupon_code, $cart_id ), array( 'source' => 'order' ) );
							} catch ( \Exception $e ) {
								$this->helper->logger->wkwctobag_error( sprintf( 'Could not apply WooCommerce coupon "%s" to Bagisto cart: %s', $coupon_code, $e->getMessage() ), array( 'source' => 'order' ) );
							}
						}
					}

					// 7. Place Order
					$order_result = $this->place_draft_order( $cart_id );

					if ( empty( $order_result['success'] ) || empty( $order_result['orderId'] ) ) {
						$error_msg = esc_html__( 'Failed to place order in Bagisto.', 'bagisto-sync-for-woocommerce' );
						if ( ! empty( $order_result['message'] ) ) {
							$error_msg = esc_html( $order_result['message'] );
						}
						throw new \Exception( esc_html( $error_msg ) );
					}

					$bagisto_order_id = intval( $order_result['orderId'] );

					$this->helper->update_bagisto_order_id( $this->order_id, $bagisto_order_id );

					$this->cleanup_temp_products();
				}

				$this->transition_order_status( $bagisto_order_id, $order );

				$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce Order ID %d to Bagisto Order ID %d', $this->order_id, $bagisto_order_id ), array( 'source' => 'order' ) );

				$response['success'] = true;
				$response['message'] = esc_html__( 'Order synced successfully with Bagisto.', 'bagisto-sync-for-woocommerce' );
				$response['data']    = array(
					'id'         => $this->order_id,
					'bagisto_id' => $bagisto_order_id,
				);

			} catch ( \Exception $ex ) {
				$this->cleanup_temp_products();
				$response['message'] = esc_html( $ex->getMessage() );
				$this->helper->logger->wkwctobag_error( sprintf( 'Order sync failed: %s', $ex->getMessage() ), array( 'source' => 'order' ) );
			}

			return $response;
		}

		/**
		 * Resolve customer for the order (including Guest Checkout Option B: register on-the-fly).
		 *
		 * @param \WC_Order $order Order instance.
		 * @return int|null Customer ID.
		 */
		private function resolve_order_customer( $order ) {
			$wc_customer_id = $order->get_customer_id();

			if ( $wc_customer_id > 0 ) {
				// Member checkout
				$bagisto_customer_id = $this->helper->get_bagisto_customer_id( $wc_customer_id );

				if ( ! empty( $bagisto_customer_id ) ) {
					// Verify if customer actually exists in Bagisto.
					$url = $this->helper->get_bagisto_site_url() . '/api/admin/customers/' . $bagisto_customer_id;
					$res = json_decode( $this->helper->bagisto_curl_request( $url, 'GET' ), true );

					if ( isset( $res['status'] ) && 404 === intval( $res['status'] ) ) {
						delete_user_meta( $wc_customer_id, '__wkwctobag_bagisto_customer_id' );
						$bagisto_customer_id = null;
					} else {
						return intval( $bagisto_customer_id );
					}
				}

				// Not mapped yet: Sync customer
				$customer_sync     = new \WKWCBAGCONNECTOR\Helper\Sync\Customer\Wkwctobag_Sync_Customer();
				$customer_settings = $this->helper->get_customer_settings_data();
				$group             = ! empty( $customer_settings['wc_bagisto_customer_group'] ) ? intval( $customer_settings['wc_bagisto_customer_group'] ) : 1;
				$gender            = ! empty( $customer_settings['wc_bagisto_customer_default_gender'] ) ? $customer_settings['wc_bagisto_customer_default_gender'] : 'male';
				$customer_status   = ! empty( $customer_settings['wc_bagisto_customer_status'] ) ? $customer_settings['wc_bagisto_customer_status'] : 'active';

				$cust_sync_res = $customer_sync->sync(
					array(
						'user_id'         => $wc_customer_id,
						'group'           => $group,
						'gender'          => $gender,
						'customer_status' => $customer_status,
					)
				);

				if ( ! empty( $cust_sync_res['success'] ) && ! empty( $cust_sync_res['data']['id'] ) ) {
					return intval( $cust_sync_res['data']['id'] );
				}
			}

			// 1. Check if Default Guest Customer ID is set in settings
			$order_settings   = $this->helper->get_order_settings_data();
			$default_guest_id = ! empty( $order_settings['wc_bagisto_guest_customer_id'] ) ? intval( $order_settings['wc_bagisto_guest_customer_id'] ) : 0;

			if ( $default_guest_id > 0 ) {
				// Verify if customer actually exists in Bagisto.
				$url = $this->helper->get_bagisto_site_url() . '/api/admin/customers/' . $default_guest_id;
				$res = json_decode( $this->helper->bagisto_curl_request( $url, 'GET' ), true );

				if ( ! empty( $res ) && ! ( isset( $res['status'] ) && 404 === intval( $res['status'] ) ) ) {
					return $default_guest_id;
				}
			}

			// Guest checkout or customer sync failed: Resolve by email
			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
			if ( empty( $email ) && empty( $phone ) ) {
				throw new \Exception( esc_html__( 'Failed to sync: Guest checkout requires either a customer email or a phone number.', 'bagisto-sync-for-woocommerce' ) );
			}

			if ( empty( $email ) ) {
				$clean_phone = preg_replace( '/[^0-9]/', '', $phone );
				$email       = 'guest-' . $clean_phone . '@example.com';
			}

			if ( ! empty( $email ) ) {
				$customer_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Customer\Wkwctobag_Sync_Customer();
				$existing      = $customer_sync->get_bagisto_customer_by_email( $email );

				if ( ! empty( $existing['data'] ) && ! empty( $existing['data'][0] ) && ! empty( $existing['data'][0]['id'] ) ) {
					return intval( $existing['data'][0]['id'] );
				}

				// Create customer on-the-fly in Bagisto
				$customer_settings = $this->helper->get_customer_settings_data();
				$group             = ! empty( $customer_settings['wc_bagisto_customer_group'] ) ? intval( $customer_settings['wc_bagisto_customer_group'] ) : 1;

				$url  = $this->helper->get_bagisto_site_url() . '/api/admin/customers';
				$body = array(
					'first_name'        => ! empty( $order->get_billing_first_name() ) ? $order->get_billing_first_name() : 'Guest',
					'last_name'         => ! empty( $order->get_billing_last_name() ) ? $order->get_billing_last_name() : 'Customer',
					'email'             => $email,
					'gender'            => 'Other',
					'date_of_birth'     => null,
					'phone'             => ! empty( $order->get_billing_phone() ) ? $order->get_billing_phone() : null,
					'customer_group_id' => $group,
					'status'            => 1,
					'send_password'     => false,
					'password'          => wp_generate_password( 12 ),
				);

				$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

				if ( ! empty( $res['id'] ) ) {
					return intval( $res['id'] );
				} elseif ( ! empty( $res['data']['id'] ) ) {
					return intval( $res['data']['id'] );
				}
			}

			return null;
		}

		/**
		 * Create draft cart in Bagisto.
		 *
		 * @param int $bagisto_customer_id Customer ID.
		 * @return int|null Draft Cart ID.
		 */
		private function create_draft_cart( $bagisto_customer_id ) {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/customers/' . $bagisto_customer_id . '/draft-carts';
			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', array() ), true );

			if ( ! empty( $res['cartId'] ) ) {
				return intval( $res['cartId'] );
			}
			return null;
		}

		/**
		 * Add WooCommerce order items to the draft cart.
		 *
		 * @param int       $cart_id Cart ID.
		 * @param \WC_Order $order   Order instance.
		 */
		private function add_order_items_to_cart( $cart_id, $order ) {
			$url             = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/items';
			$current_cart_id = $cart_id;

			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id   = $item->get_product_id();
				$variation_id = $item->get_variation_id();

				$target_id          = $variation_id > 0 ? $variation_id : $product_id;
				$bagisto_product_id = $this->helper->get_bagisto_product_id( $target_id );
				$tax_category_id    = null;

				if ( ! empty( $bagisto_product_id ) ) {
					// Verify if product actually exists in Bagisto.
					$verify_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $bagisto_product_id;
					$res        = json_decode( $this->helper->bagisto_curl_request( $verify_url, 'GET' ), true );

					if ( empty( $res ) || ( isset( $res['status'] ) && 404 === intval( $res['status'] ) ) || ( isset( $res['error'] ) && strpos( strtolower( $res['error'] ), 'not found' ) !== false ) || ! empty( $res['errors'] ) || ( isset( $res['message'] ) && strpos( strtolower( $res['message'] ), 'not found' ) !== false ) ) {
						delete_post_meta( $target_id, '__wkwctobag_bagisto_product_id' );
						$bagisto_product_id = null;
					} else {
						$details = isset( $res['data'] ) ? $res['data'] : $res;
						if ( isset( $details['taxCategoryId'] ) ) {
							$tax_category_id = intval( $details['taxCategoryId'] );
						} elseif ( isset( $details['tax_category_id'] ) ) {
							$tax_category_id = intval( $details['tax_category_id'] );
						}
					}
				}

				if ( ! $bagisto_product_id ) {
					$item_product = $item->get_product();
					$sku          = $item_product ? $item_product->get_sku() : '';

					if ( ! empty( $sku ) ) {
						if ( $variation_id > 0 ) {
							$bagisto_parent_id = $this->helper->get_bagisto_product_id( $product_id );
							if ( ! empty( $bagisto_parent_id ) ) {
								$variants_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products?parent_id=' . intval( $bagisto_parent_id ) . '&pagination=0';
								$variants_res = json_decode( $this->helper->bagisto_curl_request( $variants_url, 'GET' ), true );

								if ( ! empty( $variants_res['data'] ) && is_array( $variants_res['data'] ) ) {
									foreach ( $variants_res['data'] as $variant ) {
										if ( isset( $variant['sku'] ) && $variant['sku'] === $sku ) {
											$bagisto_product_id = intval( $variant['id'] );
											// Save the mapping for future use.
											$this->helper->update_bagisto_product_id( $target_id, $bagisto_product_id );

											// Retrieve tax category.
											if ( isset( $variant['taxCategoryId'] ) ) {
												$tax_category_id = intval( $variant['taxCategoryId'] );
											} elseif ( isset( $variant['tax_category_id'] ) ) {
												$tax_category_id = intval( $variant['tax_category_id'] );
											}

											$this->helper->logger->wkwctobag_info( sprintf( 'Self-healed ID mapping for variant product "%s" (SKU: %s) under parent Bagisto ID %d to Bagisto ID: %d', $item->get_name(), $sku, $bagisto_parent_id, $bagisto_product_id ), array( 'source' => 'order' ) );
											break;
										}
									}
								}
							}
						}

						if ( ! $bagisto_product_id ) {
							$search_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products?sku=' . rawurlencode( $sku );
							$search_res = json_decode( $this->helper->bagisto_curl_request( $search_url, 'GET' ), true );

							if ( ! empty( $search_res['data'] ) && is_array( $search_res['data'] ) ) {
								foreach ( $search_res['data'] as $bagisto_prod ) {
									if ( isset( $bagisto_prod['sku'] ) && $bagisto_prod['sku'] === $sku ) {
										$bagisto_product_id = intval( $bagisto_prod['id'] );
										// Save the mapping for future use.
										$this->helper->update_bagisto_product_id( $target_id, $bagisto_product_id );

										// Retrieve tax category.
										if ( isset( $bagisto_prod['taxCategoryId'] ) ) {
											$tax_category_id = intval( $bagisto_prod['taxCategoryId'] );
										} elseif ( isset( $bagisto_prod['tax_category_id'] ) ) {
											$tax_category_id = intval( $bagisto_prod['tax_category_id'] );
										}

										$this->helper->logger->wkwctobag_info( sprintf( 'Self-healed ID mapping for product "%s" (SKU: %s) to Bagisto ID: %d', $item->get_name(), $sku, $bagisto_product_id ), array( 'source' => 'order' ) );
										break;
									}
								}
							}
						}
					}
				}

				if ( ! $bagisto_product_id ) {
					$item_product = $item->get_product();
					if ( $item_product ) {
						// Auto-sync the product if it exists in WooCommerce but is not synced yet.
						try {
							$sync_id = ( $variation_id > 0 ) ? $product_id : $target_id;
							$this->helper->logger->wkwctobag_info( sprintf( 'Auto-syncing WooCommerce Product ID: %d on the fly during order sync.', $sync_id ), array( 'source' => 'order' ) );

							if ( ! defined( 'WKWCTOBAG_SYNCING_PRODUCT' ) ) {
								define( 'WKWCTOBAG_SYNCING_PRODUCT', true );
							}

							$product_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Product\Wkwctobag_Sync_Product();
							$product_sync->sync( array( 'product_id' => $sync_id ) );

							// Re-fetch mapping
							$bagisto_product_id = $this->helper->get_bagisto_product_id( $target_id );
							if ( $bagisto_product_id ) {
								// Retrieve tax category
								$verify_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $bagisto_product_id;
								$res        = json_decode( $this->helper->bagisto_curl_request( $verify_url, 'GET' ), true );
								$details    = isset( $res['data'] ) ? $res['data'] : $res;
								if ( isset( $details['taxCategoryId'] ) ) {
									$tax_category_id = intval( $details['taxCategoryId'] );
								} elseif ( isset( $details['tax_category_id'] ) ) {
									$tax_category_id = intval( $details['tax_category_id'] );
								}
							}
						} catch ( \Exception $e ) {
							$this->helper->logger->wkwctobag_error( sprintf( 'Failed to auto-sync product ID %d: %s', $target_id, $e->getMessage() ), array( 'source' => 'order' ) );
						}
					}
				}

				if ( ! $bagisto_product_id ) {
					$item_qty         = max( 1, intval( $item->get_quantity() ) );
					$coupon_codes     = $order->get_coupon_codes();
					$order_unit_price = ( ! empty( $coupon_codes ) ? floatval( $item->get_subtotal() ) : floatval( $item->get_total() ) ) / $item_qty;

					// Fallback to mapping tax category from WooCommerce tax class if available
					$tax_category_id = $this->helper->get_bagisto_tax_category_id( $item->get_tax_class() );

					$this->helper->logger->wkwctobag_info( sprintf( 'Product "%s" is not available at WooCommerce or Bagisto. Creating a demo/temporary product on the fly.', $item->get_name() ), array( 'source' => 'order' ) );

					$demo_temp_id = $this->create_custom_price_temp_product( $item->get_name(), $order_unit_price, $item_id, 'demo-item', $tax_category_id );
					if ( $demo_temp_id > 0 ) {
						$bagisto_product_id       = $demo_temp_id;
						$this->temp_product_ids[] = $demo_temp_id;
					} else {
						/* translators: %s: product name */
						throw new \Exception( sprintf( esc_html__( 'Failed to create demo product for "%s".', 'bagisto-sync-for-woocommerce' ), esc_html( $item->get_name() ) ) );
					}
				}

				$product               = $item->get_product();
				$item_qty              = max( 1, intval( $item->get_quantity() ) );
				$coupon_codes          = $order->get_coupon_codes();
				$order_unit_price      = ( ! empty( $coupon_codes ) ? floatval( $item->get_subtotal() ) : floatval( $item->get_total() ) ) / $item_qty;
				$catalog_unit_price    = $product ? floatval( $product->get_price() ) : 0.0;
				$final_bagisto_prod_id = $bagisto_product_id;

				if ( $product && abs( $order_unit_price - $catalog_unit_price ) > 0.001 ) {
					$custom_temp_id = $this->create_custom_price_temp_product( $item->get_name(), $order_unit_price, $item_id, 'custom-item', $tax_category_id );
					if ( $custom_temp_id > 0 ) {
						$final_bagisto_prod_id    = $custom_temp_id;
						$this->temp_product_ids[] = $custom_temp_id;
						$this->helper->logger->wkwctobag_info( sprintf( 'Created temporary custom price product (ID %d) for item "%s" with amount %f', $custom_temp_id, $item->get_name(), $order_unit_price ), array( 'source' => 'order' ) );
					}
				}

				$body = array(
					'productId' => intval( $final_bagisto_prod_id ),
					'quantity'  => intval( $item->get_quantity() ),
				);

				if ( $product && $product->is_downloadable() ) {
					$product_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $final_bagisto_prod_id;
					$res_details = json_decode( $this->helper->bagisto_curl_request( $product_url, 'GET' ), true );
					$details     = isset( $res_details['data'] ) ? $res_details['data'] : $res_details;
					if ( ! empty( $details['downloadableLinks'] ) ) {
						$links = array();
						foreach ( $details['downloadableLinks'] as $link ) {
							if ( isset( $link['id'] ) ) {
								$links[] = intval( $link['id'] );
							}
						}
						if ( ! empty( $links ) ) {
							$body['links'] = $links;
						}
					}
				}

				$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

				if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( isset( $res['success'] ) && ! $res['success'] ) ) {
					/* translators: %s: product name */
					$error_msg = sprintf( esc_html__( 'Failed to add product "%s" to cart.', 'bagisto-sync-for-woocommerce' ), esc_html( $item->get_name() ) );
					if ( ! empty( $res['message'] ) ) {
						$error_msg = esc_html( $res['message'] );
					} elseif ( ! empty( $res['detail'] ) ) {
						$error_msg = esc_html( $res['detail'] );
					}
					throw new \Exception( esc_html( $error_msg ) );
				} else {
					$extracted_id = ! empty( $res['id'] ) ? intval( $res['id'] ) : ( ! empty( $res['data']['id'] ) ? intval( $res['data']['id'] ) : null );
					if ( $extracted_id && $extracted_id !== $current_cart_id ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Cart ID updated from %d to %d during item add.', $current_cart_id, $extracted_id ), array( 'source' => 'order' ) );
						$current_cart_id = $extracted_id;
						$url             = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $current_cart_id . '/items';
					}
				}
			}

			// Add order fees line items if applicable (taxable & non-taxable fees)
			if ( method_exists( $order, 'get_fees' ) && ! empty( $order->get_fees() ) ) {
				foreach ( $order->get_fees() as $fee_id => $fee ) {
					$fee_amount = floatval( $fee->get_total() ) + floatval( $fee->get_total_tax() );
					if ( $fee_amount > 0.0 ) {
						$fee_temp_id = $this->create_custom_price_temp_product( $fee->get_name(), $fee_amount, $fee_id, 'fee-item' );
						if ( ! $fee_temp_id ) {
							/* translators: %s: fee name */
							throw new \Exception( sprintf( esc_html__( 'Failed to resolve or create fee product "%s" in Bagisto.', 'bagisto-sync-for-woocommerce' ), esc_html( $fee->get_name() ) ) );
						}
						$this->temp_product_ids[] = $fee_temp_id;

						$body = array(
							'productId' => intval( $fee_temp_id ),
							'quantity'  => 1,
						);
						$res  = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

						if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( isset( $res['success'] ) && ! $res['success'] ) ) {
							/* translators: %s: fee name */
							$error_msg = sprintf( esc_html__( 'Failed to add fee item "%s" to Bagisto draft cart.', 'bagisto-sync-for-woocommerce' ), esc_html( $fee->get_name() ) );
							if ( ! empty( $res['message'] ) ) {
								$error_msg = esc_html( $res['message'] );
							} elseif ( ! empty( $res['detail'] ) ) {
								$error_msg = esc_html( $res['detail'] );
							}
							throw new \Exception( esc_html( $error_msg ) );
						} else {
							$extracted_id = ! empty( $res['id'] ) ? intval( $res['id'] ) : ( ! empty( $res['data']['id'] ) ? intval( $res['data']['id'] ) : null );
							if ( $extracted_id && $extracted_id !== $current_cart_id ) {
								$this->helper->logger->wkwctobag_info( sprintf( 'Cart ID updated from %d to %d during fee add.', $current_cart_id, $extracted_id ), array( 'source' => 'order' ) );
								$current_cart_id = $extracted_id;
								$url             = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $current_cart_id . '/items';
							}
							$this->helper->logger->wkwctobag_info( sprintf( 'Added fee product (ID %d, name "%s") to draft cart with amount %f', $fee_temp_id, $fee->get_name(), $fee_amount ), array( 'source' => 'order' ) );
						}
					}
				}
			}

			// Add dynamic shipping fee line item if applicable
			$shipping_cost = floatval( $order->get_shipping_total() ) + floatval( $order->get_shipping_tax() );
			if ( $shipping_cost > 0.0 ) {
				$demo_id = $this->get_or_create_shipping_demo_product( $shipping_cost );
				if ( ! $demo_id ) {
					throw new \Exception( esc_html__( 'Failed to resolve or create demo shipping product in Bagisto.', 'bagisto-sync-for-woocommerce' ) );
				}
				$this->demo_product_id = $demo_id;

				$body = array(
					'productId' => intval( $demo_id ),
					'quantity'  => 1,
				);
				$res  = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

				if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( isset( $res['success'] ) && ! $res['success'] ) ) {
					$error_msg = esc_html__( 'Failed to add dynamic shipping fee item to Bagisto draft cart.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $res['message'] ) ) {
						$error_msg = esc_html( $res['message'] );
					} elseif ( ! empty( $res['detail'] ) ) {
						$error_msg = esc_html( $res['detail'] );
					}
					throw new \Exception( esc_html( $error_msg ) );
				} else {
					$extracted_id = ! empty( $res['id'] ) ? intval( $res['id'] ) : ( ! empty( $res['data']['id'] ) ? intval( $res['data']['id'] ) : null );
					if ( $extracted_id && $extracted_id !== $current_cart_id ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Cart ID updated from %d to %d during shipping add.', $current_cart_id, $extracted_id ), array( 'source' => 'order' ) );
						$current_cart_id = $extracted_id;
					}
					$this->helper->logger->wkwctobag_info( sprintf( 'Added dynamic shipping fee product (ID %d) to draft cart with amount %f', $demo_id, $shipping_cost ), array( 'source' => 'order' ) );
				}
			}

			return $current_cart_id;
		}

		/**
		 * Save addresses to draft cart.
		 *
		 * @param int       $cart_id Cart ID.
		 * @param \WC_Order $order   Order instance.
		 */
		private function save_cart_addresses( $cart_id, $order ) {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/addresses';

			// Ensure billing address fields are available (address_1, city, country, and phone are required)
			$billing_address_1 = $order->get_billing_address_1();
			$billing_city      = $order->get_billing_city();
			$billing_country   = $order->get_billing_country();
			$billing_phone     = $order->get_billing_phone();

			if ( empty( $billing_address_1 ) || empty( $billing_city ) || empty( $billing_country ) || empty( $billing_phone ) ) {
				throw new \Exception( esc_html__( 'Failed to sync: Billing address (address 1, city, country, and phone) is missing in the WooCommerce order.', 'bagisto-sync-for-woocommerce' ) );
			}

			// Generate phone-based email fallback if billing email is missing
			$clean_phone    = ! empty( $billing_phone ) ? preg_replace( '/[^0-9]/', '', $billing_phone ) : '';
			$email_fallback = ! empty( $clean_phone ) ? 'guest-' . $clean_phone . '@example.com' : 'guest-checkout@example.com';
			$billing_email  = ! empty( $order->get_billing_email() ) ? $order->get_billing_email() : $email_fallback;

			$billing_address = array(
				'firstName'      => ! empty( $order->get_billing_first_name() ) ? $order->get_billing_first_name() : 'Guest',
				'lastName'       => ! empty( $order->get_billing_last_name() ) ? $order->get_billing_last_name() : 'Customer',
				'email'          => $billing_email,
				'address'        => array(
					$billing_address_1,
					$order->get_billing_address_2(),
				),
				'city'           => $billing_city,
				'country'        => $billing_country,
				'state'          => $order->get_billing_state(),
				'postcode'       => $order->get_billing_postcode(),
				'phone'          => $billing_phone,
				'useForShipping' => false,
			);

			$shipping_address_1 = $order->get_shipping_address_1();
			$shipping_city      = $order->get_shipping_city();
			$shipping_country   = $order->get_shipping_country();

			$is_shipping_available = ! empty( $shipping_address_1 ) && ! empty( $shipping_city ) && ! empty( $shipping_country );

			if ( $is_shipping_available ) {
				$shipping_address = array(
					'firstName' => ! empty( $order->get_shipping_first_name() ) ? $order->get_shipping_first_name() : $billing_address['firstName'],
					'lastName'  => ! empty( $order->get_shipping_last_name() ) ? $order->get_shipping_last_name() : $billing_address['lastName'],
					'email'     => $billing_address['email'],
					'address'   => array(
						$shipping_address_1,
						$order->get_shipping_address_2(),
					),
					'city'      => $shipping_city,
					'country'   => $shipping_country,
					'state'     => $order->get_shipping_state(),
					'postcode'  => $order->get_shipping_postcode(),
					'phone'     => ! empty( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $billing_address['phone'],
				);
			} else {
				// Copy from billing address when shipping is empty/unavailable
				$shipping_address = array(
					'firstName' => $billing_address['firstName'],
					'lastName'  => $billing_address['lastName'],
					'email'     => $billing_address['email'],
					'address'   => $billing_address['address'],
					'city'      => $billing_address['city'],
					'country'   => $billing_address['country'],
					'state'     => $billing_address['state'],
					'postcode'  => $billing_address['postcode'],
					'phone'     => $billing_address['phone'],
				);
			}

			$billing_address['address']  = array_values( array_filter( $billing_address['address'] ) );
			$shipping_address['address'] = array_values( array_filter( $shipping_address['address'] ) );

			$body = array(
				'billing'  => $billing_address,
				'shipping' => $shipping_address,
			);

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$error_msg = esc_html__( 'Failed to save cart addresses in Bagisto.', 'bagisto-sync-for-woocommerce' );
				if ( ! empty( $res['message'] ) ) {
					$error_msg = esc_html( $res['message'] );
				}
				throw new \Exception( esc_html( $error_msg ) );
			}

			$extracted_id = ! empty( $res['id'] ) ? intval( $res['id'] ) : ( ! empty( $res['data']['id'] ) ? intval( $res['data']['id'] ) : $cart_id );
			if ( $extracted_id && $extracted_id !== $cart_id ) {
				$this->helper->logger->wkwctobag_info( sprintf( 'Cart ID updated from %d to %d during address save.', $cart_id, $extracted_id ), array( 'source' => 'order' ) );
			}

			return $extracted_id;
		}

		/**
		 * Save active shipping method to draft cart.
		 *
		 * @param int       $cart_id Cart ID.
		 * @param \WC_Order $order   Order instance.
		 */
		private function save_cart_shipping_method( $cart_id, $order ) {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/shipping-methods';

			$shipping_total = floatval( $order->get_shipping_total() ) + floatval( $order->get_shipping_tax() );

			// Fetch available shipping methods from Bagisto
			$get_url = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/shipping-methods';
			$get_res = json_decode( $this->helper->bagisto_curl_request( $get_url, 'GET' ), true );

			$shipping_method   = 'flatrate_flatrate'; // default fallback
			$available_methods = array();

			if ( ! empty( $get_res['data'] ) && is_array( $get_res['data'] ) ) {
				foreach ( $get_res['data'] as $rate ) {
					if ( ! empty( $rate['method'] ) ) {
						$available_methods[] = $rate['method'];
					}
				}
			}

			if ( in_array( 'free_free', $available_methods, true ) ) {
				$shipping_method = 'free_free';
			} elseif ( ! empty( $available_methods ) ) {
				$shipping_method = $available_methods[0];
			}

			$body = array(
				'shippingMethod' => $shipping_method,
			);

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$error_msg = esc_html__( 'Failed to save cart shipping method in Bagisto.', 'bagisto-sync-for-woocommerce' );
				if ( ! empty( $res['message'] ) ) {
					$error_msg = esc_html( $res['message'] );
				}
				throw new \Exception( esc_html( $error_msg ) );
			}
		}

		/**
		 * Save active offline payment method to draft cart.
		 *
		 * @param int       $cart_id Cart ID.
		 * @param \WC_Order $order   Order instance.
		 */
		private function save_cart_payment_method( $cart_id, $order ) {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/payment-methods';

			$get_url = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/payment-methods';
			$get_res = json_decode( $this->helper->bagisto_curl_request( $get_url, 'GET' ), true );

			$payment_method = 'moneytransfer'; // default fallback for other payments

			if ( $order && $order->get_payment_method() === 'cod' ) {
				$payment_method = 'cashondelivery';
			}

			$available_methods = array();
			if ( ! empty( $get_res['data'] ) && is_array( $get_res['data'] ) ) {
				foreach ( $get_res['data'] as $method ) {
					if ( ! empty( $method['method'] ) ) {
						$available_methods[] = $method['method'];
					}
				}
			}

			$allowed_admin_methods = array( 'cashondelivery', 'moneytransfer' );
			if ( ! in_array( $payment_method, $available_methods, true ) ) {
				$other_method = ( 'cashondelivery' === $payment_method ) ? 'moneytransfer' : 'cashondelivery';
				if ( in_array( $other_method, $available_methods, true ) ) {
					$payment_method = $other_method;
				} else {
					$payment_method = 'moneytransfer';
				}
			}

			$body = array(
				'method' => $payment_method,
			);

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$error_msg = esc_html__( 'Failed to save cart payment method in Bagisto.', 'bagisto-sync-for-woocommerce' );
				if ( ! empty( $res['message'] ) ) {
					$error_msg = esc_html( $res['message'] );
				}
				throw new \Exception( esc_html( $error_msg ) );
			}
		}

		/**
		 * Place order from draft cart.
		 *
		 * @param int $cart_id Cart ID.
		 * @return array Response from Bagisto.
		 */
		private function place_draft_order( $cart_id ) {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/orders/place/' . $cart_id;
			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', array() ), true );
			return $res;
		}

		/**
		 * Retrieve Bagisto order details.
		 *
		 * @param int $bagisto_order_id Order ID.
		 * @return array Order details.
		 */
		private function get_bagisto_order_details( $bagisto_order_id ) {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/orders/' . $bagisto_order_id;
			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'GET' ), true );
			return $res;
		}

		/**
		 * Transition the order status by creating Invoices, Shipments, or Cancellations.
		 *
		 * @param int       $bagisto_order_id Order ID in Bagisto.
		 * @param \WC_Order $order            Order instance.
		 */
		private function transition_order_status( $bagisto_order_id, $order ) {
			$status   = $order->get_status();
			$settings = $this->helper->get_order_settings_data();
			$mappings = isset( $settings['order_status_mappings'] ) ? $settings['order_status_mappings'] : array();

			$wc_status = 'wc-' . $status;
			$action    = isset( $mappings[ $wc_status ] ) ? $mappings[ $wc_status ] : 'pending';

			// Fallback defaults if mappings are empty
			if ( empty( $mappings ) ) {
				if ( in_array( $status, array( 'processing', 'completed' ), true ) ) {
					$action = 'invoice';
				}
				if ( 'completed' === $status ) {
					$action = 'shipment';
				}
				if ( 'cancelled' === $status ) {
					$action = 'cancel';
				}
				if ( 'refunded' === $status ) {
					$action = 'refund';
				}
			}

			switch ( $action ) {
				case 'invoice':
					$this->create_invoice_for_order( $bagisto_order_id, $order );
					break;

				case 'shipment':
					$this->create_invoice_for_order( $bagisto_order_id, $order );
					$this->create_shipment_for_order( $bagisto_order_id, $order );
					break;

				case 'cancel':
					$this->cancel_order( $bagisto_order_id );
					break;

				case 'refund':
					$this->refund_order( $bagisto_order_id, $order );
					break;

				case 'pending':
				default:
					break;
			}
		}

		/**
		 * Refund order.
		 *
		 * @param int       $bagisto_order_id Order ID in Bagisto.
		 * @param \WC_Order $order            Order instance.
		 */
		private function refund_order( $bagisto_order_id, $order ) {
			$order_details = $this->get_bagisto_order_details( $bagisto_order_id );
			$status        = '';
			if ( ! empty( $order_details['status'] ) ) {
				$status = $order_details['status'];
			} elseif ( ! empty( $order_details['data']['status'] ) ) {
				$status = $order_details['data']['status'];
			}

			if ( in_array( $status, array( 'canceled', 'closed' ), true ) ) {
				return;
			}

			$items = array();
			if ( ! empty( $order_details['items'] ) ) {
				$items = $order_details['items'];
			} elseif ( ! empty( $order_details['data']['items'] ) ) {
				$items = $order_details['data']['items'];
			}

			if ( empty( $items ) ) {
				return;
			}

			// Ensure an invoice exists before refunding
			$invoices = array();
			if ( ! empty( $order_details['invoices'] ) ) {
				$invoices = $order_details['invoices'];
			} elseif ( ! empty( $order_details['data']['invoices'] ) ) {
				$invoices = $order_details['data']['invoices'];
			}

			if ( empty( $invoices ) ) {
				$this->create_invoice_for_order( $bagisto_order_id, $order );
				$order_details = $this->get_bagisto_order_details( $bagisto_order_id );
				if ( ! empty( $order_details['items'] ) ) {
					$items = $order_details['items'];
				} elseif ( ! empty( $order_details['data']['items'] ) ) {
					$items = $order_details['data']['items'];
				}
			}

			$refund_items = array();
			foreach ( $items as $item ) {
				if ( ! empty( $item['id'] ) ) {
					$qty_invoiced  = isset( $item['qtyInvoiced'] ) ? intval( $item['qtyInvoiced'] ) : 0;
					$qty_refunded  = isset( $item['qtyRefunded'] ) ? intval( $item['qtyRefunded'] ) : 0;
					$qty_to_refund = max( 0, $qty_invoiced - $qty_refunded );

					if ( $qty_to_refund > 0 ) {
						$refund_items[] = array(
							'orderItemId' => intval( $item['id'] ),
							'quantity'    => $qty_to_refund,
						);
					}
				}
			}

			if ( empty( $refund_items ) ) {
				return;
			}

			$url  = $this->helper->get_bagisto_site_url() . '/api/admin/orders/' . $bagisto_order_id . '/refunds';
			$body = array(
				'items'            => $refund_items,
				'shipping'         => 0,
				'adjustmentRefund' => 0,
				'adjustmentFee'    => 0,
			);

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$this->helper->logger->wkwctobag_error( sprintf( 'Failed to create refund for Bagisto Order ID %d: %s', $bagisto_order_id, wp_json_encode( $res ) ), array( 'source' => 'order' ) );
			} else {
				$this->helper->logger->wkwctobag_info( sprintf( 'Refund created (status set to Closed) for Bagisto Order ID %d', $bagisto_order_id ), array( 'source' => 'order' ) );
			}
		}

		/**
		 * Create invoice for order.
		 *
		 * @param int       $bagisto_order_id Order ID.
		 * @param \WC_Order $order            Order instance.
		 */
		private function create_invoice_for_order( $bagisto_order_id, $order ) {
			$order_details = $this->get_bagisto_order_details( $bagisto_order_id );
			$status        = '';
			if ( ! empty( $order_details['status'] ) ) {
				$status = $order_details['status'];
			} elseif ( ! empty( $order_details['data']['status'] ) ) {
				$status = $order_details['data']['status'];
			}

			if ( in_array( $status, array( 'canceled', 'closed', 'completed' ), true ) ) {
				return;
			}

			$items = array();
			if ( ! empty( $order_details['items'] ) ) {
				$items = $order_details['items'];
			} elseif ( ! empty( $order_details['data']['items'] ) ) {
				$items = $order_details['data']['items'];
			}

			if ( empty( $items ) ) {
				return;
			}

			$invoices = array();
			if ( ! empty( $order_details['invoices'] ) ) {
				$invoices = $order_details['invoices'];
			} elseif ( ! empty( $order_details['data']['invoices'] ) ) {
				$invoices = $order_details['data']['invoices'];
			}

			if ( ! empty( $invoices ) ) {
				return;
			}

			$invoice_items = array();
			foreach ( $items as $item ) {
				if ( ! empty( $item['id'] ) ) {
					$qty_ordered    = isset( $item['qtyOrdered'] ) ? intval( $item['qtyOrdered'] ) : 0;
					$qty_invoiced   = isset( $item['qtyInvoiced'] ) ? intval( $item['qtyInvoiced'] ) : 0;
					$qty_canceled   = isset( $item['qtyCanceled'] ) ? intval( $item['qtyCanceled'] ) : 0;
					$qty_to_invoice = max( 0, $qty_ordered - $qty_invoiced - $qty_canceled );

					if ( $qty_to_invoice > 0 ) {
						$invoice_items[] = array(
							'orderItemId' => intval( $item['id'] ),
							'quantity'    => $qty_to_invoice,
						);
					}
				}
			}

			if ( empty( $invoice_items ) ) {
				return;
			}

			$url  = $this->helper->get_bagisto_site_url() . '/api/admin/orders/' . $bagisto_order_id . '/invoices';
			$body = array(
				'items' => $invoice_items,
			);

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$this->helper->logger->wkwctobag_error( sprintf( 'Failed to create invoice for Bagisto Order ID %d: %s', $bagisto_order_id, wp_json_encode( $res ) ), array( 'source' => 'order' ) );
			} else {
				$this->helper->logger->wkwctobag_info( sprintf( 'Invoice created for Bagisto Order ID %d', $bagisto_order_id ), array( 'source' => 'order' ) );
			}
		}

		/**
		 * Create shipment for order.
		 *
		 * @param int       $bagisto_order_id Order ID.
		 * @param \WC_Order $order            Order instance.
		 */
		private function create_shipment_for_order( $bagisto_order_id, $order ) {
			$order_details = $this->get_bagisto_order_details( $bagisto_order_id );
			$status        = '';
			if ( ! empty( $order_details['status'] ) ) {
				$status = $order_details['status'];
			} elseif ( ! empty( $order_details['data']['status'] ) ) {
				$status = $order_details['data']['status'];
			}

			if ( in_array( $status, array( 'canceled', 'closed', 'completed' ), true ) ) {
				return;
			}

			$items = array();
			if ( ! empty( $order_details['items'] ) ) {
				$items = $order_details['items'];
			} elseif ( ! empty( $order_details['data']['items'] ) ) {
				$items = $order_details['data']['items'];
			}

			if ( empty( $items ) ) {
				return;
			}

			$shipments = array();
			if ( ! empty( $order_details['shipments'] ) ) {
				$shipments = $order_details['shipments'];
			} elseif ( ! empty( $order_details['data']['shipments'] ) ) {
				$shipments = $order_details['data']['shipments'];
			}

			if ( ! empty( $shipments ) ) {
				return;
			}

			$inventory_source_id = 1;
			$sources_url         = $this->helper->get_bagisto_site_url() . '/api/admin/settings/inventory-sources';
			$sources_res         = json_decode( $this->helper->bagisto_curl_request( $sources_url, 'GET' ), true );
			if ( ! empty( $sources_res['data'] ) && ! empty( $sources_res['data'][0]['id'] ) ) {
				$inventory_source_id = intval( $sources_res['data'][0]['id'] );
			}

			$shipment_items = array();
			foreach ( $items as $item ) {
				if ( ! empty( $item['id'] ) ) {
					$is_virtual = ! empty( $item['is_virtual'] ) || ( isset( $item['type'] ) && in_array( $item['type'], array( 'virtual', 'downloadable' ), true ) );

					$sku = ! empty( $item['sku'] ) ? $item['sku'] : '';
					if ( ! $is_virtual && ! empty( $sku ) ) {
						if ( 0 === strpos( $sku, 'wc-fee-item-' ) ||
							0 === strpos( $sku, 'wc-shipping-fee-demo' ) ||
							0 === strpos( $sku, 'wc-custom-item-' ) ||
							0 === strpos( $sku, 'wc-demo-item-' ) ) {
							$is_virtual = true;
						}
					}

					if ( $is_virtual ) {
						continue;
					}

					$qty_ordered  = isset( $item['qtyOrdered'] ) ? intval( $item['qtyOrdered'] ) : 0;
					$qty_shipped  = isset( $item['qtyShipped'] ) ? intval( $item['qtyShipped'] ) : 0;
					$qty_canceled = isset( $item['qtyCanceled'] ) ? intval( $item['qtyCanceled'] ) : 0;
					$qty_to_ship  = max( 0, $qty_ordered - $qty_shipped - $qty_canceled );

					if ( $qty_to_ship > 0 ) {
						$shipment_items[] = array(
							'orderItemId'       => intval( $item['id'] ),
							'inventorySourceId' => $inventory_source_id,
							'quantity'          => $qty_to_ship,
						);
					}
				}
			}

			if ( empty( $shipment_items ) ) {
				return;
			}

			$url  = $this->helper->get_bagisto_site_url() . '/api/admin/orders/' . $bagisto_order_id . '/shipments';
			$body = array(
				'source'       => $inventory_source_id,
				'items'        => $shipment_items,
				'carrierTitle' => ! empty( $order->get_shipping_method() ) ? $order->get_shipping_method() : 'Standard Shipping',
				'trackNumber'  => strval( $order->get_id() ),
			);

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$detail = isset( $res['detail'] ) ? $res['detail'] : '';
				if ( strpos( strtolower( $detail ), 'insufficient' ) !== false ) {
					$this->helper->logger->wkwctobag_error( sprintf( 'Inventory at the selected source is insufficient to create shipment for Bagisto Order ID %d: %s', $bagisto_order_id, $detail ), array( 'source' => 'order' ) );
				} else {
					$this->helper->logger->wkwctobag_error( sprintf( 'Failed to create shipment for Bagisto Order ID %d: %s', $bagisto_order_id, wp_json_encode( $res ) ), array( 'source' => 'order' ) );
				}
			} else {
				$this->helper->logger->wkwctobag_info( sprintf( 'Shipment created for Bagisto Order ID %d', $bagisto_order_id ), array( 'source' => 'order' ) );
			}
		}

		/**
		 * Cancel order.
		 *
		 * @param int $bagisto_order_id Order ID.
		 */
		private function cancel_order( $bagisto_order_id ) {
			$order_details = $this->get_bagisto_order_details( $bagisto_order_id );
			$status        = '';
			if ( ! empty( $order_details['status'] ) ) {
				$status = $order_details['status'];
			} elseif ( ! empty( $order_details['data']['status'] ) ) {
				$status = $order_details['data']['status'];
			}

			if ( 'canceled' === $status ) {
				$this->helper->logger->wkwctobag_info( sprintf( 'Order is already cancelled in Bagisto for Order ID %d. Skipping cancel call.', $bagisto_order_id ), array( 'source' => 'order' ) );
				return;
			}

			$url = $this->helper->get_bagisto_site_url() . '/api/admin/orders/' . $bagisto_order_id . '/cancel';
			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', array() ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( empty( $res['id'] ) && empty( $res['data']['id'] ) ) ) {
				$detail = isset( $res['detail'] ) ? $res['detail'] : '';
				if ( strpos( strtolower( $detail ), 'nothing to cancel' ) !== false ||
					strpos( strtolower( $detail ), 'already been invoiced' ) !== false ||
					strpos( strtolower( $detail ), 'no longer be canceled' ) !== false ) {
					$this->helper->logger->wkwctobag_info( sprintf( 'Order cannot be cancelled in Bagisto (already invoiced/shipped or nothing to cancel) for Order ID %d.', $bagisto_order_id ), array( 'source' => 'order' ) );
				} else {
					$this->helper->logger->wkwctobag_error( sprintf( 'Failed to cancel Bagisto Order ID %d: %s', $bagisto_order_id, wp_json_encode( $res ) ), array( 'source' => 'order' ) );
				}
			} else {
				$this->helper->logger->wkwctobag_info( sprintf( 'Order cancelled for Bagisto Order ID %d', $bagisto_order_id ), array( 'source' => 'order' ) );
			}
		}

		/**
		 * Apply coupon to cart.
		 *
		 * @param int    $cart_id     Cart ID.
		 * @param string $coupon_code Coupon code.
		 */
		private function apply_cart_coupon( $cart_id, $coupon_code ) {
			$url  = $this->helper->get_bagisto_site_url() . '/api/admin/carts/' . $cart_id . '/coupon';
			$body = array(
				'code' => $coupon_code,
			);

			$this->helper->logger->wkwctobag_info( sprintf( 'Syncing coupon "%s" from WooCommerce to Bagisto.', $coupon_code ), array( 'source' => 'order' ) );
			$this->sync_woocommerce_coupon_to_bagisto( $coupon_code );

			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body, array( 'Ignore-Errors' => 'true' ) ), true );

			if ( empty( $res ) || isset( $res['errors'] ) || ! empty( $res['status'] ) || ( isset( $res['success'] ) && ! $res['success'] ) ) {
				$error_msg = esc_html__( 'Failed to apply coupon.', 'bagisto-sync-for-woocommerce' );
				if ( ! empty( $res['message'] ) ) {
					$error_msg = esc_html( $res['message'] );
				} elseif ( ! empty( $res['detail'] ) ) {
					$error_msg = esc_html( $res['detail'] );
				}
				throw new \Exception( esc_html( $error_msg ) );
			}
		}

		/**
		 * Retrieve WooCommerce coupon and sync it to Bagisto (Create/Update).
		 *
		 * @param string $coupon_code Coupon code.
		 * @return bool True if synced successfully.
		 */
		private function sync_woocommerce_coupon_to_bagisto( $coupon_code ) {
			$coupon = new \WC_Coupon( $coupon_code );
			if ( ! $coupon || 0 === $coupon->get_id() ) {
				return false;
			}

			$discount_type = $coupon->get_discount_type();
			$action_type   = 'cart_fixed';
			if ( 'percent' === $discount_type || 'percent_product' === $discount_type ) {
				$action_type = 'by_percent';
			} elseif ( 'fixed_product' === $discount_type ) {
				$action_type = 'by_fixed';
			}

			$channels_url = $this->helper->get_bagisto_site_url() . '/api/admin/settings/channels';
			$channels_res = json_decode( $this->helper->bagisto_curl_request( $channels_url, 'GET' ), true );
			$channel_id   = 1;
			if ( ! empty( $channels_res['data'] ) && ! empty( $channels_res['data'][0]['id'] ) ) {
				$channel_id = intval( $channels_res['data'][0]['id'] );
			}

			$groups_url = $this->helper->get_bagisto_site_url() . '/api/admin/customers/groups';
			$groups_res = json_decode( $this->helper->bagisto_curl_request( $groups_url, 'GET' ), true );
			$group_ids  = array( 1 ); // Default customer group
			if ( ! empty( $groups_res['data'] ) && is_array( $groups_res['data'] ) ) {
				$group_ids = array();
				foreach ( $groups_res['data'] as $group ) {
					if ( ! empty( $group['id'] ) ) {
						$group_ids[] = intval( $group['id'] );
					}
				}
			}

			$starts_from  = gmdate( 'Y-m-d H:i:s' );
			$date_created = $coupon->get_date_created();
			if ( $date_created ) {
				$starts_from = $date_created->date( 'Y-m-d H:i:s' );
			}

			$body = array(
				'name'                => 'WooCommerce Coupon: ' . $coupon_code,
				'description'         => $coupon->get_description() ? $coupon->get_description() : 'Synced from WooCommerce',
				'channels'            => array( $channel_id ),
				'customer_groups'     => $group_ids,
				'coupon_type'         => 1,
				'use_auto_generation' => 0,
				'coupon_code'         => $coupon_code,
				'starts_from'         => $starts_from,
				'uses_per_coupon'     => intval( $coupon->get_usage_limit() ),
				'usage_per_customer'  => intval( $coupon->get_usage_limit_per_user() ),
				'action_type'         => $action_type,
				'discount_amount'     => floatval( $coupon->get_amount() ),
				'status'              => 1,
				'condition_type'      => 1,
				'conditions'          => array(
					array(
						'attribute'      => 'cart_item|sku',
						'operator'       => '!=',
						'value'          => 'wc-shipping-fee-demo',
						'attribute_type' => 'text',
					),
					array(
						'attribute'      => 'cart_item|sku',
						'operator'       => '!{}',
						'value'          => 'wc-fee-item',
						'attribute_type' => 'text',
					),
				),
			);

			$expiry_date = $coupon->get_date_expires();
			if ( $expiry_date ) {
				$body['ends_till'] = $expiry_date->date( 'Y-m-d H:i:s' );
			}

			// Check if coupon rule already exists in Bagisto to perform update
			$search_url  = $this->helper->get_bagisto_site_url() . '/api/admin/marketing/cart-rules?coupon_code=' . rawurlencode( $coupon_code );
			$search_res  = json_decode( $this->helper->bagisto_curl_request( $search_url, 'GET' ), true );
			$existing_id = null;

			if ( ! empty( $search_res['data'] ) && is_array( $search_res['data'] ) ) {
				foreach ( $search_res['data'] as $rule ) {
					if ( isset( $rule['couponCode'] ) && $rule['couponCode'] === $coupon_code ) {
						$existing_id = intval( $rule['id'] );
						break;
					}
				}
			}

			if ( $existing_id ) {
				$url = $this->helper->get_bagisto_site_url() . '/api/admin/marketing/cart-rules/' . $existing_id;
				$res = json_decode( $this->helper->bagisto_curl_request( $url, 'PUT', $body ), true );
			} else {
				$url = $this->helper->get_bagisto_site_url() . '/api/admin/marketing/cart-rules';
				$res = json_decode( $this->helper->bagisto_curl_request( $url, 'POST', $body ), true );
			}

			if ( ! empty( $res['id'] ) || ! empty( $res['data']['id'] ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Resolve or create temporary hidden Demo Shipping Product in Bagisto.
		 *
		 * @param float $price Unit price (shipping total).
		 * @return int|null Product ID in Bagisto.
		 */
		private function get_or_create_shipping_demo_product( $price = 0.0 ) {
			$sku = 'wc-shipping-fee-demo';

			// 1. Search existing product by SKU
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products?sku=' . rawurlencode( $sku );
			$res = json_decode( $this->helper->bagisto_curl_request( $url, 'GET' ), true );

			$product_id = null;
			if ( ! empty( $res['data'] ) && is_array( $res['data'] ) ) {
				foreach ( $res['data'] as $item ) {
					if ( isset( $item['sku'] ) && $item['sku'] === $sku ) {
						$product_id = isset( $item['product_id'] ) ? intval( $item['product_id'] ) : intval( $item['id'] );
						break;
					}
				}
			}

			if ( ! $product_id ) {
				// 2. Create virtual shipping product if not exists
				$create_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products';
				$body       = array(
					'type'                => 'virtual',
					'attribute_family_id' => 1,
					'sku'                 => $sku,
				);

				$create_res = json_decode( $this->helper->bagisto_curl_request( $create_url, 'POST', $body ), true );
				$product_id = ! empty( $create_res['data']['id'] ) ? intval( $create_res['data']['id'] ) : ( ! empty( $create_res['id'] ) ? intval( $create_res['id'] ) : 0 );
			}

			if ( ! $product_id ) {
				return null;
			}

			$update_url  = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $product_id;
			$update_body = array(
				'sku'                  => $sku,
				'status'               => 1,
				'visible_individually' => 0,
				'price'                => floatval( $price ),
				'manage_stock'         => 0,
				'channels'             => array( 1 ),
				'translations'         => array(
					'en' => array(
						'name'              => 'Shipping Charge',
						'url_key'           => 'shipping-charge-' . $product_id,
						'short_description' => 'WooCommerce Dynamic Shipping Fee',
						'description'       => 'WooCommerce Dynamic Shipping Fee',
					),
				),
			);

			$headers = array( 'Content-Type' => 'application/json' );
			$this->helper->bagisto_curl_request( $update_url, 'PUT', wp_json_encode( $update_body ), $headers );

			$inventory_url  = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $product_id . '/inventories';
			$inventory_body = array(
				'inventories' => array(
					'1' => 1000,
				),
			);
			$this->helper->bagisto_curl_request( $inventory_url, 'PUT', $inventory_body );

			return $product_id;
		}

		/**
		 * Resolve or create temporary virtual product for custom price item in Bagisto.
		 *
		 * @param string $name            Product name.
		 * @param float  $price           Product price.
		 * @param int    $item_id         Product item ID.
		 * @param string $type            Product type/prefix (default: custom-item).
		 * @param int    $tax_category_id Tax category ID to apply.
		 *
		 * @return int|null Product ID in Bagisto.
		 */
		private function create_custom_price_temp_product( $name, $price, $item_id, $type = 'custom-item', $tax_category_id = null ) {
			$sku = 'wc-' . $type . '-' . intval( $item_id ) . '-' . time();

			$create_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products';
			$body       = array(
				'type'                => 'virtual',
				'attribute_family_id' => 1,
				'sku'                 => $sku,
			);

			$create_res = json_decode( $this->helper->bagisto_curl_request( $create_url, 'POST', $body ), true );
			$product_id = ! empty( $create_res['data']['id'] ) ? intval( $create_res['data']['id'] ) : ( ! empty( $create_res['id'] ) ? intval( $create_res['id'] ) : 0 );

			if ( ! $product_id ) {
				return null;
			}

			$update_url  = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $product_id;
			$update_body = array(
				'sku'                  => $sku,
				'status'               => 1,
				'visible_individually' => 0,
				'price'                => floatval( $price ),
				'manage_stock'         => 0,
				'channels'             => array( 1 ),
				'translations'         => array(
					'en' => array(
						'name'              => ! empty( $name ) ? $name : 'Custom Item',
						'url_key'           => 'custom-item-' . $product_id,
						'short_description' => 'WooCommerce Custom Price Item',
						'description'       => 'WooCommerce Custom Price Item',
					),
				),
			);

			if ( ! is_null( $tax_category_id ) ) {
				$update_body['tax_category_id'] = intval( $tax_category_id );
			}

			$headers = array( 'Content-Type' => 'application/json' );
			$this->helper->bagisto_curl_request( $update_url, 'PUT', wp_json_encode( $update_body ), $headers );

			$inventory_url  = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $product_id . '/inventories';
			$inventory_body = array(
				'inventories' => array(
					'1' => 1000,
				),
			);
			$this->helper->bagisto_curl_request( $inventory_url, 'PUT', $inventory_body );

			return $product_id;
		}

		/**
		 * Delete temporary Demo Shipping Product from Bagisto catalog.
		 *
		 * @param int $product_id Bagisto Product ID.
		 */
		private function delete_shipping_demo_product( $product_id ) {
			if ( $product_id > 0 ) {
				$url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/products/' . $product_id;
				$this->helper->bagisto_curl_request( $url, 'DELETE' );
				$this->helper->logger->wkwctobag_info( sprintf( 'Deleted temporary product ID %d from Bagisto', $product_id ), array( 'source' => 'order' ) );
			}
		}

		/**
		 * Cleanup all temporary products created during order sync.
		 */
		private function cleanup_temp_products() {
			if ( $this->demo_product_id > 0 ) {
				$this->delete_shipping_demo_product( $this->demo_product_id );
				$this->demo_product_id = 0;
			}

			if ( ! empty( $this->temp_product_ids ) && is_array( $this->temp_product_ids ) ) {
				foreach ( $this->temp_product_ids as $p_id ) {
					$this->delete_shipping_demo_product( intval( $p_id ) );
				}
				$this->temp_product_ids = array();
			}
		}
	}
}
