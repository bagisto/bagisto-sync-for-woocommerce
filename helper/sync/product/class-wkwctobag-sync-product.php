<?php
/**
 * Product sync class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\Product;

defined( 'ABSPATH' ) || exit();

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Sync_Product' ) ) {
	/**
	 * Product sync class.
	 */
	class Wkwctobag_Sync_Product {

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
		 * Sync product with Bagisto.
		 *
		 * @param array $product_info Product info.
		 *
		 * @throws \Exception If the record cannot be synchronised with Bagisto.
		 *
		 * @return array
		 */
		public function sync( $product_info ) {

			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( empty( $product_info['product_id'] ) || 0 >= intval( $product_info['product_id'] ) ) {
					throw new \Exception( esc_html__( 'Invalid product ID.', 'bagisto-sync-for-woocommerce' ) );
				}

				$product_id = intval( $product_info['product_id'] );
				$this->helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce Product ID: %d', $product_id ), array( 'source' => 'product' ) );

				$product_data = $this->get_product_data( $product_id );

				if ( empty( $product_data ) ) {
					throw new \Exception(
						sprintf(
							/* translators: %d: Product ID. */
							esc_html__( 'No product data found for product id %d', 'bagisto-sync-for-woocommerce' ),
							$product_id
						)
					);
				}

				$product_sku = ! empty( $product_data['sku'] ) ? $product_data['sku'] : '';

				$bagisto_product_data = array();

				$bagisto_product_id = $this->helper->get_bagisto_product_id( $product_id );
				if ( $bagisto_product_id ) {
					$bagisto_product = $this->get_bagisto_product_details( $bagisto_product_id );
					$details_data    = isset( $bagisto_product['data'] ) ? $bagisto_product['data'] : $bagisto_product;
					if ( ! empty( $details_data ) && ! empty( $details_data['id'] ) ) {
						$bagisto_product_data = array( $details_data );
					}
				}

				if ( empty( $bagisto_product_data ) && ! empty( $product_sku ) ) {
					$bagisto_product = $this->get_bagisto_product_by_sku( $product_sku );
					$sku_data        = isset( $bagisto_product['data'] ) ? $bagisto_product['data'] : $bagisto_product;
					if ( ! empty( $sku_data ) ) {
						$bagisto_product_data = isset( $sku_data[0] ) ? $sku_data : array( $sku_data );
						if ( ! empty( $bagisto_product_data[0]['id'] ) ) {
							$this->helper->update_bagisto_product_id( $product_id, $bagisto_product_data[0]['id'] );
						}
					}
				}

				$expected_type = 'simple';
				if ( 'variable' === $product_data['type'] ) {
					$expected_type = 'configurable';
				} elseif ( in_array( $product_data['type'], array( 'grouped', 'bundle' ), true ) ) {
					$expected_type = $product_data['type'];
				} elseif ( ! empty( $product_data['downloadable'] ) ) {
					$expected_type = 'downloadable';
				} elseif ( ! empty( $product_data['virtual'] ) ) {
					$expected_type = 'virtual';
				}

				if ( ! empty( $bagisto_product_data ) && ! empty( $bagisto_product_data[0] ) && ! empty( $bagisto_product_data[0]['id'] ) ) {
					$existing_type = isset( $bagisto_product_data[0]['type'] ) ? $bagisto_product_data[0]['type'] : '';
					if ( $existing_type && $existing_type !== $expected_type ) {
						$delete_url = $this->bagisto_base_url . '/products/' . $bagisto_product_data[0]['id'];
						$this->helper->bagisto_curl_request( $delete_url, 'DELETE' );
						$this->helper->logger->wkwctobag_info( sprintf( 'Deleted mismatched product (ID %d) in Bagisto to recreate it.', $bagisto_product_data[0]['id'] ), array( 'source' => 'product' ) );
						$bagisto_product_data = array();
					}
				}

				$result = array();
				if ( ! empty( $bagisto_product_data ) && ! empty( $bagisto_product_data[0] ) && ! empty( $bagisto_product_data[0]['id'] ) ) {
					$result = $this->update_product_with_bagisto( $bagisto_product_data[0]['id'], $product_data, array( 'data' => $bagisto_product_data[0] ) );
				} else {
					$result        = $this->sync_product_with_bagisto( $product_data );
					$sync_res_data = array();
					if ( ! empty( $result['id'] ) ) {
						$sync_res_data = $result;
					} elseif ( ! empty( $result['data']['id'] ) ) {
						$sync_res_data = $result['data'];
					}

					if ( empty( $sync_res_data ) || empty( $sync_res_data['id'] ) ) {
						$error_msg = esc_html__( 'Failed to sync product with Bagisto.', 'bagisto-sync-for-woocommerce' );
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
					$this->helper->update_bagisto_product_id( $product_id, $sync_res_data['id'] );
					$result = $this->update_product_with_bagisto( $sync_res_data['id'], $product_data, $result );
				}

				$final_res_data = array();
				if ( ! empty( $result['id'] ) ) {
					$final_res_data = $result;
				} elseif ( ! empty( $result['data']['id'] ) ) {
					$final_res_data = $result['data'];
				}

				if ( empty( $result ) || empty( $final_res_data ) || empty( $final_res_data['id'] ) ) {
					$message = esc_html__( 'Failed to sync product with Bagisto.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $result['detail'] ) ) {
						$message = $result['detail'];
					} else {
						if ( ! empty( $result['message'] ) ) {
							$message .= ' ' . $result['message'];
						}
						if ( ! empty( $result['error'] ) ) {
							$message .= ' ' . $result['error'];
						}
						if ( ! empty( $result['errors'] ) ) {
							$message .= ' ' . wp_json_encode( $result['errors'] );
						}
					}
					throw new \Exception( $message );
				}

				$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce Product ID %d to Bagisto Product ID %d', $product_id, $final_res_data['id'] ), array( 'source' => 'product' ) );

				$response['success'] = true;
				$response['message'] = ! empty( $result['message'] ) ? $result['message'] : esc_html__( 'Product synced successfully.', 'bagisto-sync-for-woocommerce' );
				$response['data']    = $final_res_data;

			} catch ( \Exception $ex ) {
				$response['message'] = $ex->getMessage();
				$this->helper->logger->wkwctobag_error( sprintf( 'Product sync failed: %s', $ex->getMessage() ), array( 'source' => 'product' ) );
			}

			return $response;
		}

		/**
		 * Get WooCommerce product data.
		 *
		 * @param int $product_id Product ID.
		 *
		 * @throws \Exception If the product cannot be found.
		 *
		 * @return array
		 */
		private function get_product_data( $product_id ) {

			$product = wc_get_product( $product_id );

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				throw new \Exception( esc_html__( 'Product not found.', 'bagisto-sync-for-woocommerce' ) );
			}

			$product_data = array(
				'id'           => $product_id,
				'name'         => $product->get_name(),
				'sku'          => ! empty( $product->get_sku() ) ? $product->get_sku() : $product->get_slug(),
				'slug'         => $product->get_slug(),
				'type'         => $product->get_type(),
				'price'        => $product->get_regular_price(),
				'special'      => $product->get_sale_price(),
				'status'       => 'publish' === $product->get_status() ? 1 : 0,
				'description'  => $product->get_description(),
				'short_desc'   => $product->get_short_description(),
				'qty'          => $product->get_manage_stock() ? $product->get_stock_quantity() : ( ! $product->is_in_stock() ? 0 : 5000 ),
				'manage_stock' => $product->get_manage_stock() ? 1 : ( ! $product->is_in_stock() ? 0 : 1 ),
				'weight'       => $product->get_weight(),
				'length'       => $product->get_length(),
				'width'        => $product->get_width(),
				'height'       => $product->get_height(),
				'tax_class'    => $product->get_tax_class(),
				'attributes'   => array(),
				'variations'   => array(),
				'categories'   => array(),
				'images'       => array(),
			);

			if ( $product->is_type( 'variable' ) ) {
				$attributes = $product->get_attributes();
				foreach ( $attributes as $attr ) {
					if ( is_a( $attr, 'WC_Product_Attribute' ) ) {
						$options = array();
						if ( $attr->is_taxonomy() ) {
							$terms = $attr->get_terms();
							foreach ( $terms as $term ) {
								$options[] = array(
									'id'   => $term->term_id,
									'name' => $term->name,
									'slug' => $term->slug,
								);
							}
						} else {
							foreach ( $attr->get_options() as $option ) {
								$options[] = array(
									'id'   => 0,
									'name' => $option,
									'slug' => sanitize_title( $option ),
								);
							}
						}

						$product_data['attributes'][ $attr->get_name() ] = array(
							'id'           => $attr->get_id(),
							'name'         => $attr->get_name(),
							'is_variation' => $attr->get_variation(),
							'options'      => $options,
						);
					}
				}

				$variations = $product->get_children();
				foreach ( $variations as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation ) {
						$variation_attributes = array();
						foreach ( $variation->get_attributes() as $attr_name => $attr_value ) {
							$variation_attributes[ $attr_name ] = $attr_value;
						}

						$product_data['variations'][ $variation_id ] = array(
							'id'           => $variation_id,
							'sku'          => ( ! empty( $variation->get_sku() ) && $variation->get_sku() !== $product_data['sku'] ) ? $variation->get_sku() : $product_data['sku'] . '-var-' . $variation_id,
							'name'         => $variation->get_name(),
							'price'        => $variation->get_regular_price(),
							'special'      => $variation->get_sale_price(),
							'status'       => 'publish' === $variation->get_status() ? 1 : 0,
							'qty'          => ( 'yes' === $variation->get_manage_stock( 'edit' ) || 'yes' === get_post_meta( $variation_id, '_manage_stock', true ) ) ? $variation->get_stock_quantity() : ( ! $variation->is_in_stock() ? 0 : 5000 ),
							'manage_stock' => ( 'yes' === $variation->get_manage_stock( 'edit' ) || 'yes' === get_post_meta( $variation_id, '_manage_stock', true ) ) ? 1 : ( ! $variation->is_in_stock() ? 0 : 1 ),
							'weight'       => $variation->get_weight(),
							'attributes'   => $variation_attributes,
							'images'       => array(),
						);

						if ( $variation->get_image_id() ) {
							$product_data['variations'][ $variation_id ]['images'][] = wp_get_attachment_url( $variation->get_image_id() );
						} elseif ( $product->get_image_id() ) {
							$product_data['variations'][ $variation_id ]['images'][] = wp_get_attachment_url( $product->get_image_id() );
						}
					}
				}
			}

			if ( $product->is_type( 'grouped' ) ) {
				$product_data['children'] = $product->get_children();
			}

			if ( $product->is_virtual() ) {
				$product_data['virtual'] = 1;
			}

			if ( $product->is_downloadable() ) {
				$product_data['downloadable']       = 1;
				$product_data['downloadable_links'] = array();
				$downloads                          = $product->get_downloads();
				foreach ( $downloads as $download ) {
					$product_data['downloadable_links'][] = array(
						'title'     => $download->get_name(),
						'price'     => 0,
						'type'      => 'url',
						'url'       => $download->get_file(),
						'downloads' => 0,
					);
				}
			}

			$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $categories ) ) {
				$product_data['categories'] = $categories;
			}

			$image_ids = $product->get_gallery_image_ids();
			if ( ! empty( $image_ids ) ) {
				foreach ( $image_ids as $image_id ) {
					$product_data['images'][] = wp_get_attachment_url( $image_id );
				}
			}

			if ( $product->get_image_id() ) {
				array_unshift( $product_data['images'], wp_get_attachment_url( $product->get_image_id() ) );
			}

			return $product_data;
		}

		/**
		 * Create product in Bagisto.
		 * First step: Create product with only required fields (type, attribute_family_id, sku).
		 *
		 * @param array $product_data Product data from WooCommerce.
		 *
		 * @return array|null
		 */
		private function sync_product_with_bagisto( $product_data ) {

			$url = $this->bagisto_base_url . '/products';

			$product_type = 'simple';
			if ( 'variable' === $product_data['type'] ) {
				$product_type = 'configurable';
			} elseif ( in_array( $product_data['type'], array( 'grouped', 'bundle' ), true ) ) {
				$product_type = $product_data['type'];
			} elseif ( ! empty( $product_data['downloadable'] ) ) {
				$product_type = 'downloadable';
			} elseif ( ! empty( $product_data['virtual'] ) ) {
				$product_type = 'virtual';
			}

			$body = array(
				'sku'                 => $product_data['sku'],
				'attribute_family_id' => 1,
				'type'                => $product_type,
			);

			if ( 'configurable' === $product_type ) {
				$super_attributes = $this->get_mapped_super_attributes( $product_data );
				if ( ! empty( $super_attributes ) ) {
					$body['super_attributes'] = $super_attributes;
				}
			}

			$headers = array( 'Content-Type' => 'application/json' );
			$result  = $this->helper->bagisto_curl_request( $url, 'POST', wp_json_encode( $body ), $headers );

			return json_decode( $result, true );
		}

		/**
		 * Update product in Bagisto.
		 * Second step: Update product with attributes, pricing, inventory and other details.
		 *
		 * @param int   $bagisto_product_id Product ID in Bagisto.
		 * @param array $product_data Product data from WooCommerce.
		 * @param array $bagisto_product Optional already fetched Bagisto product data.
		 *
		 * @return array|null
		 */
		private function update_product_with_bagisto( $bagisto_product_id, $product_data, $bagisto_product = array() ) {

			$url = $this->bagisto_base_url . '/products/' . $bagisto_product_id;

			$body = array(
				'sku'                  => $product_data['sku'],
				'status'               => ! empty( $product_data['status'] ) ? 1 : 0,
				'visible_individually' => 1,
				'price'                => ! empty( $product_data['price'] ) ? floatval( $product_data['price'] ) : 0,
				'weight'               => ! empty( $product_data['weight'] ) ? floatval( $product_data['weight'] ) : 0,
				'length'               => ! empty( $product_data['length'] ) ? floatval( $product_data['length'] ) : 0,
				'width'                => ! empty( $product_data['width'] ) ? floatval( $product_data['width'] ) : 0,
				'height'               => ! empty( $product_data['height'] ) ? floatval( $product_data['height'] ) : 0,
				'new'                  => 0,
				'featured'             => 0,
				'guest_checkout'       => 0,
				'manage_stock'         => ! empty( $product_data['manage_stock'] ) ? 1 : 0,
				'translations'         => array(
					'en' => array(
						'name'              => $product_data['name'],
						'url_key'           => sanitize_title( $product_data['slug'] . '-' . $bagisto_product_id ),
						'short_description' => ! empty( $product_data['short_desc'] ) ? $product_data['short_desc'] : $product_data['name'],
						'description'       => ! empty( $product_data['description'] ) ? $product_data['description'] : $product_data['name'],
						'meta_keywords'     => '',
					),
				),
			);

			$wc_tax_class       = isset( $product_data['tax_class'] ) ? $product_data['tax_class'] : '';
			$bagisto_tax_cat_id = $this->helper->get_bagisto_tax_category_id( $wc_tax_class );
			if ( ! empty( $bagisto_tax_cat_id ) ) {
				$body['tax_category_id'] = intval( $bagisto_tax_cat_id );
			}

			if ( ! empty( $product_data['special'] ) && floatval( $product_data['special'] ) > 0 ) {
				$body['special_price'] = floatval( $product_data['special'] );
			}

			$categories = array();
			if ( ! empty( $product_data['categories'] ) && is_array( $product_data['categories'] ) ) {
				foreach ( $product_data['categories'] as $category_id ) {
					$bagisto_cat_id = $this->helper->get_bagisto_category_id( $category_id );
					if ( $bagisto_cat_id ) {
						$categories[] = intval( $bagisto_cat_id );
					}
				}
			}
			$this->helper->logger->wkwctobag_info( sprintf( 'Mapping product ID %d to Bagisto category IDs: %s', $bagisto_product_id, wp_json_encode( $categories ) ), array( 'source' => 'product' ) );
			$body['categories'] = $categories;
			$body['channels']   = array( 1 );

			if ( 'grouped' === $product_data['type'] && ! empty( $product_data['children'] ) ) {
				$links      = array();
				$link_index = 0;
				foreach ( $product_data['children'] as $child_id ) {
					$child_product = wc_get_product( $child_id );
					if ( $child_product ) {
						$child_sku     = ! empty( $child_product->get_sku() ) ? $child_product->get_sku() : $child_product->get_slug();
						$bagisto_child = $this->get_bagisto_product_by_sku( $child_sku );

						if ( empty( $bagisto_child['data'][0]['id'] ) ) {
							$child_sync = new Wkwctobag_Sync_Product();
							$child_sync->sync( array( 'product_id' => $child_id ) );
							$bagisto_child = $this->get_bagisto_product_by_sku( $child_sku );
						}

						if ( ! empty( $bagisto_child['data'][0]['id'] ) ) {
							$links[ 'link_' . $link_index ] = array(
								'associated_product_id' => intval( $bagisto_child['data'][0]['id'] ),
								'qty'                   => 1,
								'sort_order'            => $link_index,
							);
							++$link_index;
						}
					}
				}
				$body['links'] = $links;
			}

			if ( 'downloadable' === $product_data['type'] || ! empty( $product_data['downloadable'] ) ) {
				if ( ! empty( $product_data['downloadable_links'] ) ) {
					$downloadable_links = array();
					$link_index         = 0;
					foreach ( $product_data['downloadable_links'] as $link ) {
						$downloadable_links[ 'link_' . $link_index ] = array(
							'type'       => $link['type'],
							'url'        => $link['url'],
							'price'      => $link['price'],
							'downloads'  => $link['downloads'],
							'sort_order' => $link_index,
							'en'         => array(
								'title' => $link['title'],
							),
						);
						++$link_index;
					}
					$body['downloadable_links'] = $downloadable_links;
				}
			}

			$temp_images = array();
			if ( 'variable' === $product_data['type'] ) {
				if ( empty( $bagisto_product ) || ( empty( $bagisto_product['variants'] ) && empty( $bagisto_product['data']['variants'] ) ) ) {
					$bagisto_product = $this->get_bagisto_product_details( $bagisto_product_id );
				}
				$mapped_variants = $this->get_mapped_variants_data( $bagisto_product, $product_data );
				if ( ! empty( $mapped_variants ) ) {
					$variants_payload = array();
					foreach ( $mapped_variants as $v_id => $variant ) {
						$v_data = array(
							'sku'          => $variant['sku'],
							'name'         => $variant['name'],
							'price'        => floatval( $variant['price'] ),
							'weight'       => floatval( $variant['weight'] ),
							'status'       => intval( $variant['status'] ),
							'manage_stock' => intval( $variant['manage_stock'] ),
						);

						if ( isset( $variant['special_price'] ) ) {
							$v_data['special_price'] = floatval( $variant['special_price'] );
						}

						foreach ( $product_data['attributes'] as $wc_attr_name => $wc_attr_data ) {
							if ( empty( $wc_attr_data['is_variation'] ) ) {
								continue;
							}
							$wc_attr_code = strtolower( str_replace( '-', '_', str_replace( 'pa_', '', $wc_attr_name ) ) );
							if ( isset( $variant[ $wc_attr_code ] ) ) {
								$v_data[ $wc_attr_code ] = $variant[ $wc_attr_code ];
							}
						}

						$variants_payload[ $v_id ] = $v_data;
						$v_update_url              = $this->bagisto_base_url . '/products/' . $v_id;
						$v_update_body             = array(
							'sku'                  => $v_data['sku'],
							'status'               => $v_data['status'],
							'visible_individually' => 0,
							'parent_id'            => $bagisto_product_id,
							'price'                => $v_data['price'],
							'weight'               => $v_data['weight'],
							'manage_stock'         => $v_data['manage_stock'],
							'translations'         => array(
								'en' => array(
									'name'              => $v_data['name'],
									'short_description' => ! empty( $product_data['short_desc'] ) ? $product_data['short_desc'] : $v_data['name'],
									'description'       => ! empty( $product_data['description'] ) ? $product_data['description'] : $v_data['name'],
								),
							),
						);
						if ( isset( $v_data['special_price'] ) ) {
							$v_update_body['special_price'] = $v_data['special_price'];
						}
						foreach ( $v_data as $key => $val ) {
							if ( ! in_array( $key, array( 'sku', 'name', 'price', 'weight', 'status', 'manage_stock', 'special_price' ), true ) ) {
								$v_update_body[ $key ] = $val;
							}
						}
						$this->helper->bagisto_curl_request( $v_update_url, 'PUT', $v_update_body );

						if ( ! empty( $variant['wc_variation_id'] ) ) {
							$this->helper->update_bagisto_product_id( $variant['wc_variation_id'], $v_id );
						}

						if ( isset( $variant['inventories'] ) ) {
							$v_inventory_url  = $this->bagisto_base_url . '/products/' . $v_id . '/inventories';
							$v_inventory_body = array(
								'inventories' => $variant['inventories'],
							);
							$this->helper->bagisto_curl_request( $v_inventory_url, 'PUT', $v_inventory_body );
						}

						if ( ! empty( $variant['images'] ) && is_array( $variant['images'] ) ) {
							$v_details = $this->get_bagisto_product_details( $v_id );
							$v_images  = ! empty( $v_details['images'] ) ? $v_details['images'] : ( ! empty( $v_details['data']['images'] ) ? $v_details['data']['images'] : array() );
							if ( ! empty( $v_images ) && is_array( $v_images ) ) {
								foreach ( $v_images as $img ) {
									$del_img_url = $this->bagisto_base_url . '/products/' . $v_id . '/images/' . intval( $img['id'] );
									$this->helper->bagisto_curl_request( $del_img_url, 'DELETE' );
								}
							}

							$v_img_index = 0;
							foreach ( $variant['images'] as $image_url ) {
								if ( empty( $image_url ) ) {
									continue;
								}

								$curl_file = $this->helper->download_and_create_curl_file( $image_url );
								if ( $curl_file ) {
									$temp_images[] = $curl_file->getFilename();
									$img_url       = $this->bagisto_base_url . '/products/' . $v_id . '/images';
									$img_body      = array(
										'image'    => $curl_file,
										'position' => $v_img_index + 1,
									);
									$this->helper->bagisto_curl_request( $img_url, 'POST', $img_body );
									++$v_img_index;
								}
							}
						}
					}
					$body['variants'] = $variants_payload;
				}
			}

			$headers = array( 'Content-Type' => 'application/json' );
			$result  = $this->helper->bagisto_curl_request( $url, 'PUT', wp_json_encode( $body ), $headers );
			$this->helper->logger->wkwctobag_info( sprintf( 'Updated product details in Bagisto. Product ID: %d', $bagisto_product_id ), array( 'source' => 'product' ) );

			if ( isset( $product_data['qty'] ) && null !== $product_data['qty'] && '' !== $product_data['qty'] ) {
				$inventory_url  = $this->bagisto_base_url . '/products/' . $bagisto_product_id . '/inventories';
				$inventory_body = array(
					'inventories' => array(
						'1' => max( 0, intval( $product_data['qty'] ) ),
					),
				);
				$this->helper->bagisto_curl_request( $inventory_url, 'PUT', $inventory_body );
			}

			if ( ! empty( $product_data['images'] ) && is_array( $product_data['images'] ) ) {
				$parent_details = $this->get_bagisto_product_details( $bagisto_product_id );
				$parent_images  = ! empty( $parent_details['images'] ) ? $parent_details['images'] : ( ! empty( $parent_details['data']['images'] ) ? $parent_details['data']['images'] : array() );
				if ( ! empty( $parent_images ) && is_array( $parent_images ) ) {
					foreach ( $parent_images as $img ) {
						$del_img_url = $this->bagisto_base_url . '/products/' . $bagisto_product_id . '/images/' . intval( $img['id'] );
						$this->helper->bagisto_curl_request( $del_img_url, 'DELETE' );
					}
				}

				$img_index = 0;
				foreach ( $product_data['images'] as $image_url ) {
					if ( empty( $image_url ) ) {
						continue;
					}

					$curl_file = $this->helper->download_and_create_curl_file( $image_url );
					if ( $curl_file ) {
						$temp_images[] = $curl_file->getFilename();
						$img_url       = $this->bagisto_base_url . '/products/' . $bagisto_product_id . '/images';
						$img_body      = array(
							'image'    => $curl_file,
							'position' => $img_index + 1,
						);
						$this->helper->bagisto_curl_request( $img_url, 'POST', $img_body );
						++$img_index;
					}
				}
			}

			if ( ! empty( $temp_images ) ) {
				foreach ( $temp_images as $temp_image ) {
					if ( file_exists( $temp_image ) ) {
						wp_delete_file( $temp_image );
					}
				}
			}

			return json_decode( $result, true );
		}

		/**
		 * Get Bagisto product details by ID.
		 *
		 * @param int $id Bagisto product ID.
		 *
		 * @return array Bagisto product data.
		 */
		public function get_bagisto_product_details( $id ) {
			$url = $this->bagisto_base_url . '/products/' . $id;

			$result  = $this->helper->bagisto_curl_request( $url, 'GET' );
			$details = json_decode( $result, true );

			$data = isset( $details['data'] ) ? $details['data'] : $details;

			if ( ! empty( $data ) && isset( $data['type'] ) && 'configurable' === $data['type'] && ! isset( $data['variants'] ) ) {
				$variants_url    = $this->bagisto_base_url . '/products?parent_id=' . $id . '&per_page=500';
				$variants_result = $this->helper->bagisto_curl_request( $variants_url, 'GET' );
				$variants_data   = json_decode( $variants_result, true );
				if ( ! empty( $variants_data['data'] ) ) {
					$data['variants'] = $variants_data['data'];
				}
			}

			if ( isset( $details['data'] ) ) {
				$details['data'] = $data;
			} else {
				$details = $data;
			}

			return $details;
		}

		/**
		 * Get Bagisto product by SKU.
		 *
		 * @param string $sku Product SKU.
		 *
		 * @return array Bagisto product data.
		 */
		public function get_bagisto_product_by_sku( $sku ) {

			$url = $this->bagisto_base_url . '/products?sku=' . rawurlencode( $sku );

			$result = $this->helper->bagisto_curl_request( $url, 'GET' );

			return json_decode( $result, true );
		}
		/**
		 * Get mapped super attributes for configurable products.
		 *
		 * @param array $product_data Product data.
		 * @return array
		 */
		private function get_mapped_super_attributes( $product_data ) {
			$super_attributes = array();

			if ( empty( $product_data['attributes'] ) ) {
				return $super_attributes;
			}

			$bagisto_attributes = $this->get_bagisto_attributes();

			foreach ( $product_data['attributes'] as $wc_attr_name => $wc_attr_data ) {
				if ( empty( $wc_attr_data['is_variation'] ) ) {
					continue;
				}

				$wc_attr_code = strtolower( str_replace( '-', '_', str_replace( 'pa_', '', $wc_attr_name ) ) );
				$bagisto_attr = null;

				foreach ( $bagisto_attributes as $attr ) {
					if ( $attr['code'] === $wc_attr_code ) {
						$bagisto_attr = $attr;
						break;
					}
				}

				if ( ! $bagisto_attr ) {
					if ( ! empty( $wc_attr_data['id'] ) ) {
						$attr_sync = new \WKWCBAGCONNECTOR\Helper\Sync\Attribute\Wkwctobag_Sync_Attribute();
						$sync_item = array(
							'type'                      => 'attribute',
							'attribute_id'              => $wc_attr_data['id'],
							'is_update_existing_attrib' => 'no',
						);
						$attr_sync->sync( $sync_item );

						$bagisto_attributes = $this->get_bagisto_attributes();
						foreach ( $bagisto_attributes as $attr ) {
							if ( $attr['code'] === $wc_attr_code ) {
								$bagisto_attr = $attr;
								break;
							}
						}
					}
				}

				if ( ! $bagisto_attr ) {
					continue;
				}

				if ( ! empty( $bagisto_attr['id'] ) ) {
					$bagisto_attr = $this->get_bagisto_attribute_details( $bagisto_attr['id'] );
				}

				$option_ids = array();
				if ( ! empty( $bagisto_attr['options'] ) ) {
					foreach ( $wc_attr_data['options'] as $wc_option ) {
						foreach ( $bagisto_attr['options'] as $bag_option ) {
							$opt_details = $this->get_option_admin_name_and_label( $bag_option );
							if ( strcasecmp( $wc_option['name'], $opt_details['label'] ) === 0 || strcasecmp( $wc_option['slug'], $opt_details['admin_name'] ) === 0 ) {
								$option_ids[] = intval( $bag_option['id'] );
								break;
							}
						}
					}
				}

				if ( count( $option_ids ) < count( $wc_attr_data['options'] ) ) {
					$option_ids = $this->add_missing_attribute_options( $bagisto_attr, $wc_attr_data['options'] );
				}

				if ( ! empty( $option_ids ) ) {
					$super_attributes[ $wc_attr_code ] = $option_ids;
				}
			}

			return $super_attributes;
		}

		/**
		 * Add missing attribute options to Bagisto.
		 *
		 * @param array $bagisto_attr Bagisto attribute data.
		 * @param array $wc_options   WooCommerce options data.
		 * @return array New list of option IDs.
		 */
		private function add_missing_attribute_options( $bagisto_attr, $wc_options ) {
			$option_ids      = array();
			$payload_options = array();

			if ( ! empty( $bagisto_attr['options'] ) ) {
				foreach ( $bagisto_attr['options'] as $index => $option ) {
					$opt_details       = $this->get_option_admin_name_and_label( $option );
					$payload_options[] = array(
						'id'           => intval( $option['id'] ),
						'admin_name'   => $opt_details['admin_name'],
						'sort_order'   => $index + 1,
						'translations' => array(
							'en' => array(
								'label' => $opt_details['label'],
							),
						),
					);

					foreach ( $wc_options as $wc_option ) {
						if ( strcasecmp( $wc_option['name'], $opt_details['label'] ) === 0 || strcasecmp( $wc_option['slug'], $opt_details['admin_name'] ) === 0 ) {
							if ( ! in_array( intval( $option['id'] ), $option_ids, true ) ) {
								$option_ids[] = intval( $option['id'] );
							}
						}
					}
				}
			}

			$new_added = false;
			foreach ( $wc_options as $wc_option ) {
				$found = false;
				if ( ! empty( $bagisto_attr['options'] ) ) {
					foreach ( $bagisto_attr['options'] as $option ) {
						$opt_details = $this->get_option_admin_name_and_label( $option );
						if ( strcasecmp( $wc_option['name'], $opt_details['label'] ) === 0 || strcasecmp( $wc_option['slug'], $opt_details['admin_name'] ) === 0 ) {
							$found = true;
							break;
						}
					}
				}

				if ( ! $found ) {
					$payload_options[] = array(
						'admin_name'   => $wc_option['name'],
						'sort_order'   => count( $payload_options ) + 1,
						'translations' => array(
							'en' => array(
								'label' => $wc_option['name'],
							),
						),
					);
					$new_added         = true;
				}
			}

			if ( $new_added ) {
				$url  = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/attributes/' . $bagisto_attr['id'];
				$body = array(
					'code'       => $bagisto_attr['code'],
					'admin_name' => isset( $bagisto_attr['adminName'] ) ? $bagisto_attr['adminName'] : ( isset( $bagisto_attr['admin_name'] ) ? $bagisto_attr['admin_name'] : '' ),
					'type'       => $bagisto_attr['type'],
					'options'    => $payload_options,
				);

				$headers = array( 'Content-Type' => 'application/json' );
				$result  = $this->helper->bagisto_curl_request( $url, 'PUT', $body, $headers );
				$data    = json_decode( $result, true );

				$attr_res_data = array();
				if ( ! empty( $data['data'] ) ) {
					$attr_res_data = $data['data'];
				} else {
					$attr_res_data = $data;
				}

				if ( ! empty( $attr_res_data ) && ! empty( $attr_res_data['options'] ) ) {
					$option_ids = array();
					foreach ( $wc_options as $wc_option ) {
						foreach ( $attr_res_data['options'] as $new_option ) {
							$new_opt_details = $this->get_option_admin_name_and_label( $new_option );
							if ( strcasecmp( $wc_option['name'], $new_opt_details['label'] ) === 0 || strcasecmp( $wc_option['slug'], $new_opt_details['admin_name'] ) === 0 ) {
								if ( ! in_array( intval( $new_option['id'] ), $option_ids, true ) ) {
									$option_ids[] = intval( $new_option['id'] );
								}
								break;
							}
						}
					}
				}
			}

			return $option_ids;
		}

		/**
		 * Fetch Bagisto attribute detail.
		 *
		 * @param int $bagisto_attr_id Bagisto attribute ID.
		 *
		 * @return array Bagisto attribute data.
		 */
		private function get_bagisto_attribute_details( $bagisto_attr_id ) {
			$url    = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/attributes/' . $bagisto_attr_id;
			$result = $this->helper->bagisto_curl_request( $url, 'GET' );
			$data   = json_decode( $result, true );
			return isset( $data['data'] ) ? $data['data'] : $data;
		}

		/**
		 * Get option admin name and label dynamically supporting both snake_case and camelCase keys.
		 *
		 * @param array $opt Bagisto attribute option data.
		 *
		 * @return array Admin name and label pair.
		 */
		private function get_option_admin_name_and_label( $opt ) {
			$admin_name = isset( $opt['adminName'] ) ? $opt['adminName'] : ( isset( $opt['admin_name'] ) ? $opt['admin_name'] : '' );
			$label      = '';
			if ( isset( $opt['label'] ) ) {
				$label = $opt['label'];
			} elseif ( ! empty( $opt['translations'] ) ) {
				if ( is_array( $opt['translations'] ) ) {
					foreach ( $opt['translations'] as $t_key => $t_val ) {
						if ( isset( $t_val['label'] ) ) {
							$label = $t_val['label'];
							break;
						} elseif ( is_array( $t_val ) && isset( $t_val['locale'] ) && 'en' === $t_val['locale'] && isset( $t_val['label'] ) ) {
							$label = $t_val['label'];
							break;
						}
					}
				}
			}
			if ( empty( $label ) ) {
				$label = $admin_name;
			}
			return array(
				'admin_name' => $admin_name,
				'label'      => $label,
			);
		}

		/**
		 * Get mapped variants data for update.
		 *
		 * @param array $bagisto_product Bagisto product details.
		 * @param array $wc_product_data WooCommerce product data.
		 * @return array
		 */
		private function get_mapped_variants_data( $bagisto_product, $wc_product_data ) {
			$mapped_variants = array();

			$bagisto_variants = array();
			if ( ! empty( $bagisto_product['variants'] ) && is_array( $bagisto_product['variants'] ) ) {
				$bagisto_variants = $bagisto_product['variants'];
			} elseif ( ! empty( $bagisto_product['data']['variants'] ) && is_array( $bagisto_product['data']['variants'] ) ) {
				$bagisto_variants = $bagisto_product['data']['variants'];
			}

			if ( empty( $bagisto_variants ) || empty( $wc_product_data['variations'] ) ) {
				return $mapped_variants;
			}

			$bagisto_attributes = $this->get_bagisto_attributes();

			foreach ( $bagisto_variants as $bag_variant ) {
				$bag_variant_id = ! empty( $bag_variant['product_id'] ) ? intval( $bag_variant['product_id'] ) : intval( $bag_variant['id'] );

				$matched_wc_variation = null;
				$matched_wc_id        = null;
				foreach ( $wc_product_data['variations'] as $wc_variation_id => $wc_variation ) {
					$match = true;
					foreach ( $wc_variation['attributes'] as $wc_attr_name => $wc_attr_value ) {
						$wc_attr_code = strtolower( str_replace( '-', '_', str_replace( 'pa_', '', $wc_attr_name ) ) );

						$bag_option_label = '';
						if ( isset( $bag_variant['attributeValues'] ) && is_array( $bag_variant['attributeValues'] ) ) {
							if ( isset( $bag_variant['attributeValues'][ $wc_attr_code ] ) ) {
								$bag_option_label = $bag_variant['attributeValues'][ $wc_attr_code ];
							} else {
								$match = false;
								break;
							}
						} else {
							if ( ! isset( $bag_variant[ $wc_attr_code ] ) ) {
								$match = false;
								break;
							}
							foreach ( $bagisto_attributes as $attr ) {
								if ( $attr['code'] === $wc_attr_code ) {
									foreach ( $attr['options'] as $option ) {
										if ( (int) $option['id'] === (int) $bag_variant[ $wc_attr_code ] ) {
											$bag_option_label = $option['label'];
											break;
										}
									}
									break;
								}
							}
						}

						$wc_option_label = $wc_attr_value;
						if ( ! empty( $wc_product_data['attributes'][ $wc_attr_name ]['options'] ) ) {
							foreach ( $wc_product_data['attributes'][ $wc_attr_name ]['options'] as $opt ) {
								if ( $opt['slug'] === $wc_attr_value || $opt['name'] === $wc_attr_value ) {
									$wc_option_label = $opt['name'];
									break;
								}
							}
						}

						if ( strcasecmp( trim( $wc_option_label ), trim( $bag_option_label ) ) !== 0 ) {
							$match = false;
							break;
						}
					}

					if ( $match ) {
						$matched_wc_variation = $wc_variation;
						$matched_wc_id        = $wc_variation_id;
						break;
					}
				}

				if ( $matched_wc_variation ) {

					$mapped_variants[ $bag_variant_id ] = array(
						'wc_variation_id' => $matched_wc_id,
						'sku'             => $matched_wc_variation['sku'],
						'name'            => $matched_wc_variation['name'],
						'price'           => floatval( $matched_wc_variation['price'] ),
						'weight'          => floatval( $matched_wc_variation['weight'] ),
						'status'          => $matched_wc_variation['status'] ? 1 : 0,
						'manage_stock'    => ! empty( $matched_wc_variation['manage_stock'] ) ? 1 : 0,
						'images'          => ! empty( $matched_wc_variation['images'] ) ? $matched_wc_variation['images'] : array(),
						'inventories'     => array(
							'1' => ! empty( $matched_wc_variation['qty'] ) || '0' === $matched_wc_variation['qty'] ? max( 0, intval( $matched_wc_variation['qty'] ) ) : 0,
						),
					);

					if ( ! empty( $matched_wc_variation['special'] ) && floatval( $matched_wc_variation['special'] ) > 0 ) {
						$mapped_variants[ $bag_variant_id ]['special_price'] = floatval( $matched_wc_variation['special'] );
					}

					foreach ( $matched_wc_variation['attributes'] as $wc_attr_name => $wc_attr_value ) {
						$wc_attr_code  = strtolower( str_replace( '-', '_', str_replace( 'pa_', '', $wc_attr_name ) ) );
						$bag_option_id = null;
						if ( isset( $bag_variant['attributeValues'] ) && is_array( $bag_variant['attributeValues'] ) ) {
							$bag_option_label = isset( $bag_variant['attributeValues'][ $wc_attr_code ] ) ? $bag_variant['attributeValues'][ $wc_attr_code ] : '';
							if ( $bag_option_label ) {
								static $loaded_attributes = array();
								foreach ( $bagisto_attributes as $attr ) {
									if ( $attr['code'] === $wc_attr_code ) {
										$attr_id = $attr['id'];
										if ( ! isset( $loaded_attributes[ $attr_id ] ) ) {
											$loaded_attributes[ $attr_id ] = $this->get_bagisto_attribute_details( $attr_id );
										}
										$detailed_attr = $loaded_attributes[ $attr_id ];
										if ( ! empty( $detailed_attr['options'] ) ) {
											foreach ( $detailed_attr['options'] as $option ) {
												$opt_details = $this->get_option_admin_name_and_label( $option );
												if ( strcasecmp( trim( $opt_details['label'] ), trim( $bag_option_label ) ) === 0 || strcasecmp( trim( $opt_details['admin_name'] ), trim( $bag_option_label ) ) === 0 ) {
													$bag_option_id = (int) $option['id'];
													break;
												}
											}
										}
										break;
									}
								}
							}
						} else {
							$bag_option_id = isset( $bag_variant[ $wc_attr_code ] ) ? (int) $bag_variant[ $wc_attr_code ] : null;
						}
						$mapped_variants[ $bag_variant_id ][ $wc_attr_code ] = $bag_option_id;
					}
				}
			}

			return $mapped_variants;
		}

		/**
		 * Get Bagisto attributes.
		 *
		 * @return array
		 */
		public function get_bagisto_attributes() {
			$url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/attributes?per_page=500';

			$result = $this->helper->bagisto_curl_request( $url, 'GET' );
			$data   = json_decode( $result, true );

			return ! empty( $data['data'] ) ? $data['data'] : array();
		}
	}
}
