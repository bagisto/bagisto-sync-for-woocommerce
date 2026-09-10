<?php
/**
 * Category sync class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\Category;

defined( 'ABSPATH' ) || exit();

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Sync_Category' ) ) {
	/**
	 * Category sync class.
	 */
	class Wkwctobag_Sync_Category {

		/**
		 * Bagisto base URL.
		 *
		 * @var string
		 */
		private $bagisto_base_url = '';

		/**
		 * Helper instance.
		 *
		 * @var Helper\Wkwctobag_Helper
		 */
		protected $helper = null;

		/**
		 * Constructor of this class.
		 */
		public function __construct() {
			$this->helper           = Helper\Wkwctobag_Helper::get_instance();
			$this->bagisto_base_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog';
		}

		/**
		 * Sync category with Bagisto.
		 *
		 * @param array $category_info Category info.
		 *
		 * @throws \Exception If the record cannot be synchronised with Bagisto.
		 *
		 * @return array
		 */
		public function sync( $category_info ) {

			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( empty( $category_info['category_id'] ) || 0 >= intval( $category_info['category_id'] ) ) {
					throw new \Exception( esc_html__( 'Invalid category ID.', 'bagisto-sync-for-woocommerce' ) );
				}

				$category_id   = intval( $category_info['category_id'] );
				$category_data = $this->get_category_data( $category_id );

				$this->helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce Category ID: %d', $category_id ), array( 'source' => 'category' ) );

				if ( empty( $category_data ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: Category ID. */
							esc_html__( 'No category data found for category id %d', 'bagisto-sync-for-woocommerce' ),
							$category_id
						)
					);
				}

				// Recursive parent sync to guarantee hierarchy in Bagisto.
				$parent_id = ! empty( $category_data['parent'] ) ? intval( $category_data['parent'] ) : 0;
				if ( $parent_id > 0 ) {
					$bagisto_parent_id = $this->helper->get_bagisto_category_id( $parent_id );
					if ( $bagisto_parent_id > 0 ) {
						if ( ! $this->bagisto_category_exists( $bagisto_parent_id ) ) {
							delete_term_meta( $parent_id, '__wkwctobag_bagisto_category_id' );
							$bagisto_parent_id = 0;
						}
					}
					if ( empty( $bagisto_parent_id ) ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Parent category ID %d is not mapped or stale. Syncing parent first.', $parent_id ), array( 'source' => 'category' ) );
						$parent_res = $this->sync( array( 'category_id' => $parent_id ) );
						if ( empty( $parent_res['success'] ) ) {
							/* translators: %1$d: parent category ID, %2$s: error message */
							throw new \Exception( sprintf( esc_html__( 'Failed to sync parent category ID %1$d: %2$s', 'bagisto-sync-for-woocommerce' ), $parent_id, $parent_res['message'] ) );
						}
					}
				}

				$bagisto_id = ! empty( $category_data['bagisto_id'] ) ? intval( $category_data['bagisto_id'] ) : 0;

				if ( $bagisto_id > 0 ) {
					if ( ! $this->bagisto_category_exists( $bagisto_id ) ) {
						delete_term_meta( $category_id, '__wkwctobag_bagisto_category_id' );
						$bagisto_id = 0;
					}
				}

				if ( 0 === $bagisto_id ) {
					$existing_id = $this->get_bagisto_category_id_by_slug( $category_data['slug'] );
					if ( $existing_id ) {
						$bagisto_id = $existing_id;
						$this->helper->update_bagisto_category_id( $category_id, $bagisto_id );
						$category_data['bagisto_id'] = $bagisto_id;
					}
				}

				$result = null;
				if ( $bagisto_id > 0 ) {
					$result = $this->sync_category_with_bagisto( $category_data, $bagisto_id );
				} else {
					$result = $this->sync_category_with_bagisto( $category_data );
				}

				$res_data = array();
				if ( ! empty( $result['id'] ) ) {
					$res_data = $result;
				} elseif ( ! empty( $result['data']['id'] ) ) {
					$res_data = $result['data'];
				}

				if ( ! empty( $res_data ) && ! empty( $res_data['id'] ) ) {
					$bagisto_category_id = intval( $res_data['id'] );

					$this->helper->update_bagisto_category_id( $category_id, $bagisto_category_id );

					$settings_data = $this->helper->get_product_settings_data();
					if ( empty( $settings_data['category_mapping'] ) ) {
						$settings_data['category_mapping'] = array();
					}
					$settings_data['category_mapping'][ $category_id ] = $bagisto_category_id;
					$this->helper->save_product_settings( $settings_data );

					$response['success'] = true;

					$response['message'] = ! empty( $result['message'] ) ? $result['message'] : esc_html__( 'Category synced successfully.', 'bagisto-sync-for-woocommerce' );

					$response['data'] = $res_data;

					$this->helper->logger->wkwctobag_info( sprintf( 'Successfully mapped WooCommerce Category ID %d to Bagisto Category ID %d', $category_id, $bagisto_category_id ), array( 'source' => 'category' ) );
				} else {
					$error_msg = esc_html__( 'Failed to sync category with Bagisto.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $result['detail'] ) ) {
						$error_msg = esc_html( $result['detail'] );
					} elseif ( ! empty( $result['message'] ) ) {
						$error_msg = esc_html( $result['message'] );
					} elseif ( ! empty( $result['error'] ) ) {
						$error_msg = esc_html( $result['error'] );
					} elseif ( ! empty( $result['title'] ) ) {
						$error_msg = esc_html( $result['title'] );
					}

					throw new \Exception( esc_html( $error_msg ) );
				}
			} catch ( \Exception $ex ) {
				$response['message'] = $ex->getMessage();
				$this->helper->logger->wkwctobag_error( sprintf( 'Category sync failed: %s', $ex->getMessage() ), array( 'source' => 'category' ) );
			}

			return $response;
		}

		/**
		 * Create or update category in Bagisto.
		 *
		 * @param array $category_data Category data.
		 * @param int   $bagisto_category_id Bagisto category ID.
		 *
		 * @return array|null
		 */
		private function sync_category_with_bagisto( $category_data, $bagisto_category_id = 0 ) {
			$is_update = ! empty( $bagisto_category_id );

			$url = $is_update ? $this->bagisto_base_url . '/categories/' . intval( $bagisto_category_id ) : $this->bagisto_base_url . '/categories';

			$bagisto_parent_id = 1;

			// Set parent category.
			if ( ! empty( $category_data['parent'] ) && 0 < intval( $category_data['parent'] ) ) {
				$bagisto_cat_id = $this->helper->get_bagisto_category_id(
					$category_data['parent']
				);

				if ( ! empty( $bagisto_cat_id ) && 0 < intval( $bagisto_cat_id ) ) {
					$bagisto_parent_id = $bagisto_cat_id;
				}
			}
			$attr_id = $this->get_bagisto_price_attribute_id();

			$body = array(
				'locale'     => 'en',
				'status'     => 1,
				'attributes' => array( intval( $attr_id ) ),
				'position'   => 0,
				'parent_id'  => intval( $bagisto_parent_id ),
			);

			if ( ! $is_update ) {
				$body['display_mode'] = 'products_and_description';
			}

			$description = ! empty( $category_data['description'] ) ? $category_data['description'] : $category_data['name'];

			if ( $is_update ) {
				$body['en'] = array(
					'name'        => $category_data['name'],
					'slug'        => $category_data['slug'],
					'description' => $description,
				);
				$method     = 'PUT';
			} else {
				// Create category.
				$body['name']        = $category_data['name'];
				$body['slug']        = $category_data['slug'];
				$body['description'] = $description;
				$method              = 'POST';
			}

			$headers = array( 'Content-Type' => 'application/json' );
			$result  = $this->helper->bagisto_curl_request( $url, $method, wp_json_encode( $body ), $headers );

			return json_decode( $result, true );
		}

		/**
		 * Check if a category exists in Bagisto.
		 *
		 * @param int $bagisto_category_id Bagisto category ID.
		 *
		 * @return bool
		 */
		private function bagisto_category_exists( $bagisto_category_id ) {

			try {
				$url    = $this->bagisto_base_url . '/categories/' . intval( $bagisto_category_id );
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );
				$data   = json_decode( $result, true );

				return ! empty( $data['id'] ) || ! empty( $data['data']['id'] );

			} catch ( \Exception $ex ) {
				return false;
			}
		}

		/**
		 * Get bagisto price attribute ID.
		 *
		 * @return int
		 */
		private function get_bagisto_price_attribute_id() {
			$transient_key = 'wkwctobag_bagisto_price_attribute_id';

			$attribute_id = get_transient( $transient_key );

			if ( false !== $attribute_id ) {
				return $attribute_id;
			}

			try {
				$url    = $this->bagisto_base_url . '/attributes?code=price';
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );
				$data   = json_decode( $result, true );

				if ( ! empty( $data['data'][0]['id'] ) ) {
					$attribute_id = intval( $data['data'][0]['id'] );
					set_transient( $transient_key, $attribute_id, 24 * HOUR_IN_SECONDS );

					return $attribute_id;
				}
			} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Falls through to the documented default attribute ID below.
				// Fallback.
			}

			return 11;
		}

		/**
		 * Get category data from WooCommerce.
		 *
		 * @param int $category_id Category ID.
		 * @throws \Exception If category data cannot be retrieved.
		 *
		 * @return array Category data.
		 */
		private function get_category_data( $category_id ) {
			$category_id = absint( $category_id );

			if ( ! $category_id ) {
				throw new \Exception( esc_html__( 'Invalid category ID.', 'bagisto-sync-for-woocommerce' ) );
			}

			$category = get_term( $category_id, 'product_cat' );

			if ( ! $category || is_wp_error( $category ) ) {
				throw new \Exception( esc_html__( 'Category not found.', 'bagisto-sync-for-woocommerce' ) );
			}

			$thumbnail_id = get_term_meta( $category_id, 'thumbnail_id', true );

			$image_url = '';
			if ( $thumbnail_id ) {
				$image     = wp_get_attachment_image_src( $thumbnail_id, 'full' );
				$image_url = ! empty( $image[0] ) ? $image[0] : '';
			}

			return array(
				'id'          => $category->term_id,
				'name'        => $category->name,
				'slug'        => $category->slug,
				'description' => ! empty( $category->description ) ? $category->description : $category->name,
				'parent'      => $category->parent,
				'image_url'   => $image_url,
				'bagisto_id'  => $this->helper->get_bagisto_category_id( $category_id ),
			);
		}

		/**
		 * Get Bagisto product categories.
		 *
		 * @throws \Exception If the Bagisto category list cannot be retrieved.
		 *
		 * @return array
		 */
		public function get_bagisto_product_category() {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				$page         = 1;
				$all_data     = array();
				$has_more     = true;
				$max_attempts = 20;

				while ( $has_more && $page <= $max_attempts ) {
					$url    = $this->bagisto_base_url . '/categories?per_page=100&page=' . $page . '&sort=id&order=asc';
					$result = $this->helper->bagisto_curl_request( $url, 'GET' );
					$data   = json_decode( $result, true );

					if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
						$all_data = array_merge( $all_data, $data['data'] );

						$last_page = isset( $data['meta']['lastPage'] ) ? intval( $data['meta']['lastPage'] ) : 1;
						if ( $page >= $last_page || empty( $data['data'] ) ) {
							$has_more = false;
						} else {
							++$page;
						}
					} else {
						$has_more = false;
						if ( empty( $all_data ) ) {
							throw new \Exception( esc_html__( 'Invalid response from Bagisto API.', 'bagisto-sync-for-woocommerce' ) );
						}
					}
				}

				$response['success'] = true;
				$response['data']    = $all_data;
			} catch ( \Exception $ex ) {
				$response['message'] = $ex->getMessage();
			}
			return $response;
		}

		/**
		 * Get bagisto category ID by slug.
		 *
		 * @param string $slug Slug.
		 * @return int|null
		 */
		private function get_bagisto_category_id_by_slug( $slug ) {
			try {
				$url    = $this->bagisto_base_url . '/categories?slug=' . urlencode( $slug ) . '&per_page=100';
				$result = $this->helper->bagisto_curl_request( $url, 'GET' );
				$data   = json_decode( $result, true );

				if ( ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
					foreach ( $data['data'] as $cat ) {
						if ( isset( $cat['slug'] ) && strtolower( $cat['slug'] ) === strtolower( $slug ) ) {
							return intval( $cat['id'] );
						}
					}
				}
			} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A lookup miss is not an error; the caller handles null.
				// Fail silently and return null.
			}
			return null;
		}
	}
}
