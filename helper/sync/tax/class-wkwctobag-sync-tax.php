<?php
/**
 * Tax sync class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\Tax;

defined( 'ABSPATH' ) || exit();

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Sync_Tax' ) ) {

	class Wkwctobag_Sync_Tax {

		/**
		 * Helper instance.
		 *
		 * @var Helper\Wkwctobag_Helper
		 */
		protected $helper = null;

		/**
		 * Base tax rate API URL.
		 *
		 * @var string
		 */
		private $tax_rate_url = '';

		/**
		 * Base tax category API URL.
		 *
		 * @var string
		 */
		private $tax_category_url = '';

		public function __construct() {
			$this->helper           = Helper\Wkwctobag_Helper::get_instance();
			$site_url               = $this->helper->get_bagisto_site_url();
			$this->tax_rate_url     = $site_url . '/api/admin/settings/tax-rates';
			$this->tax_category_url = $site_url . '/api/admin/settings/tax-categories';
		}

		public function sync( $item ) {
			if ( empty( $item['sub_type'] ) ) {
				return array(
					'success' => false,
					'message' => esc_html__( 'Invalid tax sub-type.', 'bagisto-sync-for-woocommerce' ),
				);
			}

			$update_existing_taxes = isset( $item['update_existing_taxes'] ) ? $item['update_existing_taxes'] : 'yes';

			if ( 'tax_rate' === $item['sub_type'] ) {
				if ( empty( $item['tax_rate_id'] ) ) {
					return array(
						'success' => false,
						'message' => esc_html__( 'Invalid tax rate ID.', 'bagisto-sync-for-woocommerce' ),
					);
				}
				return $this->sync_single_tax_rate( intval( $item['tax_rate_id'] ), $update_existing_taxes );
			} elseif ( 'tax_category' === $item['sub_type'] ) {
				$tax_class = isset( $item['tax_class'] ) ? $item['tax_class'] : '';
				return $this->sync_single_tax_category( $tax_class, $update_existing_taxes );
			}

			return array(
				'success' => false,
				'message' => esc_html__( 'Unsupported tax sub-type.', 'bagisto-sync-for-woocommerce' ),
			);
		}

		/**
		 * Sync a single tax rate to Bagisto.
		 *
		 * @param int    $wc_rate_id WooCommerce tax rate ID.
		 * @param string $update_existing_taxes Update option.
		 * @return array Sync result.
		 */
		public function sync_single_tax_rate( $wc_rate_id, $update_existing_taxes = 'yes' ) {
			global $wpdb;
			try {
				$cache_key = 'wkwctobag_tax_rate_' . $wc_rate_id;
				$rate      = wp_cache_get( $cache_key, 'wkwctobag' );

				if ( false === $rate ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$rate = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d", $wc_rate_id ) );
					if ( $rate ) {
						wp_cache_set( $cache_key, $rate, 'wkwctobag', 300 );
					}
				}

				if ( empty( $rate ) ) {
					$msg = sprintf( 'WooCommerce tax rate ID %d not found in database.', $wc_rate_id );
					$this->helper->logger->wkwctobag_error( $msg, array( 'source' => 'tax' ) );
					return array(
						'success' => false,
						'message' => esc_html( $msg ),
					);
				}

				$existing_bagisto_rates = array();
				$fetched_successfully   = false;
				try {
					$response = $this->helper->bagisto_curl_request( $this->tax_rate_url . '?per_page=500', 'GET' );
					$decoded  = json_decode( $response, true );

					if ( isset( $decoded['data'] ) ) {
						$fetched_successfully = true;
						if ( is_array( $decoded['data'] ) ) {
							foreach ( $decoded['data'] as $bagisto_rate ) {
								if ( ! empty( $bagisto_rate['identifier'] ) ) {
									$existing_bagisto_rates[ $bagisto_rate['identifier'] ] = $bagisto_rate['id'];
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					$this->helper->logger->wkwctobag_error( 'Failed to fetch existing Bagisto tax rates: ' . $e->getMessage(), array( 'source' => 'tax' ) );
				}

				$default_location = get_option( 'woocommerce_default_country', 'US' );
				$default_parts    = explode( ':', $default_location );
				$default_country  = ! empty( $default_parts[0] ) ? $default_parts[0] : 'US';

				$identifier = 'wc-rate-' . $wc_rate_id;

				$is_zip   = 0;
				$zip_code = '';
				$zip_from = '';
				$zip_to   = '';

				$cache_key_loc = 'wkwctobag_tax_rate_loc_' . $wc_rate_id;
				$locations     = wp_cache_get( $cache_key_loc, 'wkwctobag' );

				if ( false === $locations ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$locations = $wpdb->get_col( $wpdb->prepare( "SELECT location_code FROM {$wpdb->prefix}woocommerce_tax_rate_locations WHERE tax_rate_id = %d AND location_type = 'postcode'", $wc_rate_id ) );
					if ( is_array( $locations ) ) {
						wp_cache_set( $cache_key_loc, $locations, 'wkwctobag', 300 );
					}
				}

				if ( ! empty( $locations ) ) {
					$postcode = trim( $locations[0] );
					if ( strpos( $postcode, '*' ) !== false ) {
						$is_zip   = 1;
						$zip_from = str_replace( '*', '0', $postcode );
						$zip_to   = str_replace( '*', '9', $postcode );
					} elseif ( strpos( $postcode, '...' ) !== false ) {
						$is_zip   = 1;
						$parts    = explode( '...', $postcode );
						$zip_from = trim( $parts[0] );
						$zip_to   = trim( $parts[1] );
					} elseif ( strpos( $postcode, '-' ) !== false ) {
						$is_zip   = 1;
						$parts    = explode( '-', $postcode );
						$zip_from = trim( $parts[0] );
						$zip_to   = trim( $parts[1] );
					} else {
						$zip_code = $postcode;
					}
				}

				$country = ! empty( $rate->tax_rate_country ) && '*' !== $rate->tax_rate_country ? $rate->tax_rate_country : $default_country;
				$state   = ! empty( $rate->tax_rate_state ) && '*' !== $rate->tax_rate_state ? $rate->tax_rate_state : '';

				$tax_rate_val = floatval( $rate->tax_rate );
				if ( $tax_rate_val < 0.0001 ) {
					$tax_rate_val = 0.0001;
				}

				$body = array(
					'identifier' => $identifier,
					'country'    => $country,
					'state'      => $state,
					'tax_rate'   => $tax_rate_val,
					'is_zip'     => (bool) $is_zip,
				);

				if ( $is_zip ) {
					$body['zip_from'] = $zip_from;
					$body['zip_to']   = $zip_to;
				} else {
					$body['zip_code'] = empty( $zip_code ) ? '*' : $zip_code;
				}

				$bagisto_rate_id = null;
				if ( isset( $existing_bagisto_rates[ $identifier ] ) ) {
					$bagisto_rate_id = intval( $existing_bagisto_rates[ $identifier ] );
				} else {
					$bagisto_rate_id = $this->helper->get_bagisto_tax_rate_id( $wc_rate_id );
					if ( $bagisto_rate_id && $fetched_successfully && ! in_array( intval( $bagisto_rate_id ), $existing_bagisto_rates, true ) ) {
						$bagisto_rate_id = null;
						$mappings        = get_option( 'wkwctobag_tax_rate_mappings', array() );
						if ( isset( $mappings[ $wc_rate_id ] ) ) {
							unset( $mappings[ $wc_rate_id ] );
							update_option( 'wkwctobag_tax_rate_mappings', $mappings, true );
						}
					}
				}

				if ( $bagisto_rate_id ) {
					if ( 'no' === $update_existing_taxes ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Tax rate update skipped for WC Rate ID %d (Bagisto ID %d)', $wc_rate_id, $bagisto_rate_id ), array( 'source' => 'tax' ) );
						return array(
							'success' => true,
							'message' => esc_html__( 'Tax rate update skipped.', 'bagisto-sync-for-woocommerce' ),
						);
					}
					$url      = $this->tax_rate_url . '/' . $bagisto_rate_id;
					$response = $this->helper->bagisto_curl_request( $url, 'PUT', $body );
				} else {
					$response = $this->helper->bagisto_curl_request( $this->tax_rate_url, 'POST', $body );
				}

				$decoded = json_decode( $response, true );
				$new_id  = null;
				if ( ! empty( $decoded['data']['id'] ) ) {
					$new_id = intval( $decoded['data']['id'] );
				} elseif ( ! empty( $decoded['id'] ) ) {
					$new_id = intval( $decoded['id'] );
				}

				if ( $new_id ) {
					$this->helper->update_bagisto_tax_rate_id( $wc_rate_id, $new_id );
					$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce Tax Rate ID %d to Bagisto ID %d', $wc_rate_id, $new_id ), array( 'source' => 'tax' ) );
					return array(
						'success' => true,
						/* translators: %d: WooCommerce tax rate ID */
						'message' => sprintf( esc_html__( 'Tax rate ID %d synced successfully.', 'bagisto-sync-for-woocommerce' ), $wc_rate_id ),
					);
				} else {
					$err_msg = esc_html__( 'Failed to sync tax rate.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $decoded['detail'] ) ) {
						$err_msg = $decoded['detail'];
					} elseif ( ! empty( $decoded['message'] ) ) {
						$err_msg = $decoded['message'];
					} elseif ( ! empty( $decoded['error'] ) ) {
						$err_msg = $decoded['error'];
					} elseif ( ! empty( $decoded['title'] ) ) {
						$err_msg = $decoded['title'];
					}
					if ( ! empty( $decoded['errors'] ) ) {
						$err_msg .= ' ' . wp_json_encode( $decoded['errors'] );
					}
					$this->helper->logger->wkwctobag_error( sprintf( 'Failed syncing WC Tax Rate ID %d: %s', $wc_rate_id, $err_msg ), array( 'source' => 'tax' ) );
					return array(
						'success' => false,
						'message' => $err_msg,
					);
				}
			} catch ( \Exception $e ) {
				$this->helper->logger->wkwctobag_error( sprintf( 'Exception in sync_single_tax_rate (WC Rate ID %d): %s', $wc_rate_id, $e->getMessage() ), array( 'source' => 'tax' ) );
				return array(
					'success' => false,
					'message' => $e->getMessage(),
				);
			}
		}

		/**
		 * Sync a single tax class/category to Bagisto.
		 *
		 * @param string $tax_class WooCommerce tax class.
		 * @param string $update_existing_taxes Update option.
		 * @return array Sync result.
		 */
		public function sync_single_tax_category( $tax_class, $update_existing_taxes = 'yes' ) {
			try {
				$existing_bagisto_categories = array();
				$existing_categories_by_id   = array();
				$fetched_successfully        = false;
				try {
					$response = $this->helper->bagisto_curl_request( $this->tax_category_url . '?per_page=500', 'GET' );
					$decoded  = json_decode( $response, true );

					if ( isset( $decoded['data'] ) ) {
						$fetched_successfully = true;
						if ( is_array( $decoded['data'] ) ) {
							foreach ( $decoded['data'] as $bagisto_cat ) {
								if ( ! empty( $bagisto_cat['code'] ) ) {
									$existing_bagisto_categories[ $bagisto_cat['code'] ]       = intval( $bagisto_cat['id'] );
									$existing_categories_by_id[ intval( $bagisto_cat['id'] ) ] = array(
										'code' => $bagisto_cat['code'],
										'name' => $bagisto_cat['name'],
									);
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					$this->helper->logger->wkwctobag_error( 'Failed to fetch existing Bagisto tax categories: ' . $e->getMessage(), array( 'source' => 'tax' ) );
				}

				$default_location = get_option( 'woocommerce_default_country', 'US' );
				$default_parts    = explode( ':', $default_location );
				$default_country  = ! empty( $default_parts[0] ) ? $default_parts[0] : 'US';

				$code = empty( $tax_class ) ? 'standard' : 'wc-class-' . sanitize_title( $tax_class );
				$name = empty( $tax_class ) ? esc_html__( 'Standard Rate', 'bagisto-sync-for-woocommerce' ) : ucwords( str_replace( '-', ' ', $tax_class ) );

				$class_rates = \WC_Tax::get_rates_for_tax_class( $tax_class );
				$taxrates    = array();

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
							$bag_rate_id = $this->helper->get_bagisto_tax_rate_id( $rate_id );
							if ( $bag_rate_id ) {
								$taxrates[] = intval( $bag_rate_id );
							}
						}
					}
				}

				if ( empty( $taxrates ) ) {
					$fallback_rate_id = get_option( 'wkwctobag_fallback_tax_rate_id' );
					if ( $fallback_rate_id ) {
						try {
							$verify_res     = $this->helper->bagisto_curl_request( $this->tax_rate_url . '/' . $fallback_rate_id, 'GET' );
							$decoded_verify = json_decode( $verify_res, true );
							$verified_id    = null;
							if ( ! empty( $decoded_verify['data']['id'] ) ) {
								$verified_id = intval( $decoded_verify['data']['id'] );
							} elseif ( ! empty( $decoded_verify['id'] ) ) {
								$verified_id = intval( $decoded_verify['id'] );
							}
							if ( ! $verified_id ) {
								$fallback_rate_id = null;
								delete_option( 'wkwctobag_fallback_tax_rate_id' );
							}
						} catch ( \Exception $e ) {
							$fallback_rate_id = null;
							delete_option( 'wkwctobag_fallback_tax_rate_id' );
						}
					}

					if ( ! $fallback_rate_id ) {
						$fallback_body = array(
							'identifier' => 'wc-fallback-rate',
							'country'    => $default_country,
							'state'      => '',
							'tax_rate'   => 0.0001,
							'is_zip'     => false,
							'zip_code'   => '*',
						);

						try {
							$response_search = $this->helper->bagisto_curl_request( $this->tax_rate_url . '?identifier=wc-fallback-rate', 'GET' );
							$decoded_search  = json_decode( $response_search, true );
							if ( ! empty( $decoded_search['data'] ) && is_array( $decoded_search['data'] ) ) {
								foreach ( $decoded_search['data'] as $rate_item ) {
									if ( isset( $rate_item['identifier'] ) && 'wc-fallback-rate' === $rate_item['identifier'] ) {
										$fallback_rate_id = intval( $rate_item['id'] );
										break;
									}
								}
							}
						} catch ( \Exception $e ) {
							// Silent ignore search error
						}

						if ( ! $fallback_rate_id ) {
							$response_fallback = $this->helper->bagisto_curl_request( $this->tax_rate_url, 'POST', $fallback_body );
							$decoded_fallback  = json_decode( $response_fallback, true );
							if ( ! empty( $decoded_fallback['data']['id'] ) ) {
								$fallback_rate_id = intval( $decoded_fallback['data']['id'] );
							} elseif ( ! empty( $decoded_fallback['id'] ) ) {
								$fallback_rate_id = intval( $decoded_fallback['id'] );
							} else {
								$err_msg = esc_html__( 'Failed to create fallback tax rate.', 'bagisto-sync-for-woocommerce' );
								if ( ! empty( $decoded_fallback['detail'] ) ) {
									$err_msg = $decoded_fallback['detail'];
								} elseif ( ! empty( $decoded_fallback['message'] ) ) {
									$err_msg = $decoded_fallback['message'];
								} elseif ( ! empty( $decoded_fallback['error'] ) ) {
									$err_msg = $decoded_fallback['error'];
								} elseif ( ! empty( $decoded_fallback['title'] ) ) {
									$err_msg = $decoded_fallback['title'];
								}
								if ( ! empty( $decoded_fallback['errors'] ) ) {
									$err_msg .= ' ' . wp_json_encode( $decoded_fallback['errors'] );
								}
								throw new \Exception( $err_msg );
							}
						}

						if ( $fallback_rate_id ) {
							update_option( 'wkwctobag_fallback_tax_rate_id', $fallback_rate_id, true );
						}
					}

					if ( $fallback_rate_id ) {
						$taxrates[] = $fallback_rate_id;
					}
				}

				$bagisto_cat_id = $this->helper->get_bagisto_tax_category_id( $tax_class );

				if ( $bagisto_cat_id && $fetched_successfully && ! in_array( intval( $bagisto_cat_id ), $existing_bagisto_categories, true ) ) {
					$bagisto_cat_id = null;
					$mappings       = get_option( 'wkwctobag_tax_category_mappings', array() );
					if ( isset( $mappings[ $tax_class ] ) ) {
						unset( $mappings[ $tax_class ] );
						update_option( 'wkwctobag_tax_category_mappings', $mappings, true );
					}
				}

				if ( ! $bagisto_cat_id && isset( $existing_bagisto_categories[ $code ] ) ) {
					$bagisto_cat_id = intval( $existing_bagisto_categories[ $code ] );
					$this->helper->update_bagisto_tax_category_id( $tax_class, $bagisto_cat_id );
				}

				if ( $bagisto_cat_id && isset( $existing_categories_by_id[ $bagisto_cat_id ] ) ) {
					$code = $existing_categories_by_id[ $bagisto_cat_id ]['code'];
					$name = $existing_categories_by_id[ $bagisto_cat_id ]['name'];
				}

				$body = array(
					'code'        => $code,
					'name'        => $name,
					/* translators: %s is replaced with the WooCommerce tax class name. */
					'description' => sprintf( esc_html__( 'WooCommerce Synced Tax Class: %s', 'bagisto-sync-for-woocommerce' ), empty( $tax_class ) ? 'standard' : $tax_class ),
					'taxrates'    => array_values( array_map( 'intval', $taxrates ) ),
				);

				$headers = array( 'Content-Type' => 'application/json' );

				if ( $bagisto_cat_id ) {
					if ( 'no' === $update_existing_taxes ) {
						return array(
							'success' => true,
							'message' => esc_html__( 'Tax class update skipped.', 'bagisto-sync-for-woocommerce' ),
						);
					}
					$url      = $this->tax_category_url . '/' . $bagisto_cat_id;
					$response = $this->helper->bagisto_curl_request( $url, 'PUT', wp_json_encode( $body ), $headers );
				} else {
					$response = $this->helper->bagisto_curl_request( $this->tax_category_url, 'POST', wp_json_encode( $body ), $headers );
				}

				$decoded = json_decode( $response, true );
				$new_id  = null;
				if ( ! empty( $decoded['data']['id'] ) ) {
					$new_id = intval( $decoded['data']['id'] );
				} elseif ( ! empty( $decoded['id'] ) ) {
					$new_id = intval( $decoded['id'] );
				}

				if ( $new_id ) {
					$this->helper->update_bagisto_tax_category_id( $tax_class, $new_id );
					$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce Tax Class "%s" to Bagisto Category ID %d', empty( $tax_class ) ? 'standard' : $tax_class, $new_id ), array( 'source' => 'tax' ) );
					return array(
						'success' => true,
						/* translators: %s: WooCommerce tax class name */
						'message' => sprintf( esc_html__( 'Tax class "%s" synced successfully.', 'bagisto-sync-for-woocommerce' ), empty( $tax_class ) ? 'standard' : $tax_class ),
					);
				} else {
					$err_msg = esc_html__( 'Failed to sync tax category.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $decoded['detail'] ) ) {
						$err_msg = $decoded['detail'];
					} elseif ( ! empty( $decoded['message'] ) ) {
						$err_msg = $decoded['message'];
					} elseif ( ! empty( $decoded['error'] ) ) {
						$err_msg = $decoded['error'];
					} elseif ( ! empty( $decoded['title'] ) ) {
						$err_msg = $decoded['title'];
					}
					if ( ! empty( $decoded['errors'] ) ) {
						$err_msg .= ' ' . wp_json_encode( $decoded['errors'] );
					}
					return array(
						'success' => false,
						'message' => $err_msg,
					);
				}
			} catch ( \Exception $e ) {
				return array(
					'success' => false,
					'message' => $e->getMessage(),
				);
			}
		}
	}
}
