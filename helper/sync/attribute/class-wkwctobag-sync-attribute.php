<?php
/**
 * Product Attribute sync helper class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Helper\Sync\Attribute;

defined( 'ABSPATH' ) || exit();

use WKWCBAGCONNECTOR\Helper;

if ( ! class_exists( 'Wkwctobag_Sync_Attribute' ) ) {

	class Wkwctobag_Sync_Attribute {

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

		public function __construct() {
			$this->helper           = Helper\Wkwctobag_Helper::get_instance();
			$this->bagisto_base_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog';
		}

		/**
		 * Main background entry point for attribute sync task.
		 *
		 * @param array $item Queue task item.
		 * @return array
		 */
		public function sync( $item ) {
			$response = array(
				'success' => false,
				'message' => '',
				'data'    => array(),
			);

			try {
				if ( empty( $item['attribute_id'] ) ) {
					throw new \Exception( esc_html__( 'Invalid attribute ID.', 'bagisto-sync-for-woocommerce' ) );
				}

				$attribute_id = intval( $item['attribute_id'] );
				$this->helper->logger->wkwctobag_info( sprintf( 'Starting sync for WooCommerce Attribute ID: %d', $attribute_id ), array( 'source' => 'attribute' ) );

				delete_transient( 'wc_attribute_taxonomies' );
				if ( class_exists( 'WC_Cache_Helper' ) ) {
					\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
				}

				$attribute = wc_get_attribute( $attribute_id );

				if ( ! $attribute || is_wp_error( $attribute ) ) {
					/* translators: %d: WooCommerce attribute ID */
					throw new \Exception( sprintf( esc_html__( 'Attribute ID %d not found in WooCommerce.', 'bagisto-sync-for-woocommerce' ), $attribute_id ) ); //phpcs:ignore
				}

				$taxonomy_slug = $attribute->slug;

				if ( ! empty( $item['old_slug'] ) ) {
					$old_taxonomy_slug = 'pa_' . $item['old_slug'];
					if ( $old_taxonomy_slug !== $taxonomy_slug ) {
						$old_mapped_id = $this->get_bagisto_attribute_id( $old_taxonomy_slug );
						if ( $old_mapped_id ) {
							$this->update_bagisto_attribute_id( $taxonomy_slug, $old_mapped_id );
							$this->delete_bagisto_attribute_mapping( $old_taxonomy_slug );
						}
					}
				}
				$attribute_code  = str_replace( '-', '_', str_replace( 'pa_', '', $attribute->slug ) );
				$update_existing = isset( $item['is_update_existing_attrib'] ) && 'yes' === $item['is_update_existing_attrib'];

				$bagisto_attr_id = $this->get_bagisto_attribute_id( $taxonomy_slug );

				$result = null;
				if ( $bagisto_attr_id ) {
					$exists = $this->bagisto_attribute_exists( $bagisto_attr_id );
					if ( $exists ) {
						if ( $update_existing ) {
							$result = $this->sync_attribute_with_bagisto( $attribute, $bagisto_attr_id );
						} else {
							$result = $this->sync_attribute_options_only( $attribute, $bagisto_attr_id );
						}
					} else {
						$this->delete_bagisto_attribute_mapping( $taxonomy_slug );
						$result = $this->sync_attribute_with_bagisto( $attribute );
					}
				} else {
					$fallback_id = $this->get_bagisto_attribute_id_by_code( $attribute_code );
					if ( $fallback_id ) {
						$this->update_bagisto_attribute_id( $taxonomy_slug, $fallback_id );
						if ( $update_existing ) {
							$result = $this->sync_attribute_with_bagisto( $attribute, $fallback_id );
						} else {
							$result = $this->sync_attribute_options_only( $attribute, $fallback_id );
						}
					} else {
						$result = $this->sync_attribute_with_bagisto( $attribute );
					}
				}

				if ( isset( $result['success'] ) && true === $result['success'] ) {
					$bagisto_id = 0;
					if ( ! empty( $result['data'] ) ) {
						if ( ! empty( $result['data']['id'] ) ) {
							$bagisto_id = intval( $result['data']['id'] );
						} elseif ( ! empty( $result['data']['data']['id'] ) ) {
							$bagisto_id = intval( $result['data']['data']['id'] );
						}
					}
					$this->helper->logger->wkwctobag_info( sprintf( 'Successfully synced WooCommerce Attribute ID %d (Bagisto ID: %d)', $attribute_id, $bagisto_id ), array( 'source' => 'attribute' ) );
				}

				return $result;

			} catch ( \Exception $e ) {
				$response['message'] = esc_html( $e->getMessage() );
				$this->helper->logger->wkwctobag_error( sprintf( 'Attribute sync failed: %s', $e->getMessage() ), array( 'source' => 'attribute' ) );
			}

			return $response;
		}

		/**
		 * Retrieve mapped Bagisto attribute ID from options.
		 */
		public function get_bagisto_attribute_id( $taxonomy_slug ) {
			$mappings = get_option( 'wkwctobag_attribute_mappings', array() );
			return isset( $mappings[ $taxonomy_slug ] ) ? intval( $mappings[ $taxonomy_slug ] ) : null;
		}

		/**
		 * Save mapped Bagisto attribute ID.
		 */
		public function update_bagisto_attribute_id( $taxonomy_slug, $bagisto_attr_id ) {
			$mappings                   = get_option( 'wkwctobag_attribute_mappings', array() );
			$mappings[ $taxonomy_slug ] = intval( $bagisto_attr_id );
			update_option( 'wkwctobag_attribute_mappings', $mappings, false );
		}

		/**
		 * Delete mapped Bagisto attribute ID.
		 */
		public function delete_bagisto_attribute_mapping( $taxonomy_slug ) {
			$mappings = get_option( 'wkwctobag_attribute_mappings', array() );
			if ( isset( $mappings[ $taxonomy_slug ] ) ) {
				unset( $mappings[ $taxonomy_slug ] );
				update_option( 'wkwctobag_attribute_mappings', $mappings, false );
			}
		}

		/**
		 * Check if attribute ID exists in Bagisto.
		 */
		private function bagisto_attribute_exists( $bagisto_attr_id ) {
			$url    = $this->bagisto_base_url . '/attributes/' . $bagisto_attr_id;
			$result = $this->helper->bagisto_curl_request( $url, 'GET' );
			$data   = json_decode( $result, true );
			return ! empty( $data['data']['id'] ) || ! empty( $data['id'] );
		}

		/**
		 * Get attribute by code fallback from Bagisto.
		 */
		private function get_bagisto_attribute_id_by_code( $code ) {
			$url       = $this->bagisto_base_url . '/attributes?code=' . rawurlencode( $code ) . '&pagination=0';
			$result    = $this->helper->bagisto_curl_request( $url, 'GET' );
			$data      = json_decode( $result, true );
			$attr_list = ! empty( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
			if ( ! empty( $attr_list ) && is_array( $attr_list ) ) {
				foreach ( $attr_list as $attr ) {
					if ( ! empty( $attr['code'] ) && strcasecmp( $attr['code'], $code ) === 0 ) {
						return intval( $attr['id'] );
					}
				}
			}
			return null;
		}

		/**
		 * Synchronize full attribute details and options.
		 */
		private function sync_attribute_with_bagisto( $attribute, $bagisto_attr_id = null ) {
			$response = array(
				'success' => false,
				'message' => '',
			);

			$taxonomy_slug = $attribute->slug;
			$terms         = get_terms(
				array(
					'taxonomy'   => $taxonomy_slug,
					'hide_empty' => false,
				)
			);

			$options = array();
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $index => $term ) {
					$options[] = array(
						'admin_name'   => $term->name,
						'sort_order'   => $index + 1,
						'translations' => array(
							'en' => array(
								'label' => $term->name,
							),
						),
					);
				}
			}

			$body = array(
				'code'                => str_replace( '-', '_', str_replace( 'pa_', '', $attribute->slug ) ),
				'admin_name'          => $attribute->name,
				'type'                => 'select',
				'swatch_type'         => 'dropdown',
				'is_required'         => false,
				'is_unique'           => false,
				'is_configurable'     => true,
				'is_visible_on_front' => true,
				'is_filterable'       => true,
				'translations'        => array(
					'en' => array(
						'name' => $attribute->name,
					),
				),
			);

			if ( ! empty( $options ) ) {
				$body['options'] = $options;
			}

			if ( $bagisto_attr_id ) {
				$url = $this->bagisto_base_url . '/attributes/' . $bagisto_attr_id;

				$existing_details = $this->get_bagisto_attribute_details( $bagisto_attr_id );
				$merged_options   = array();

				if ( ! empty( $existing_details['options'] ) ) {
					foreach ( $existing_details['options'] as $idx => $opt ) {
						$opt_details      = $this->get_option_admin_name_and_label( $opt );
						$merged_options[] = array(
							'id'           => intval( $opt['id'] ),
							'admin_name'   => $opt_details['admin_name'],
							'sort_order'   => $idx + 1,
							'translations' => array(
								'en' => array(
									'label' => $opt_details['label'],
								),
							),
						);
					}
				}

				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$found = false;
						if ( ! empty( $existing_details['options'] ) ) {
							foreach ( $existing_details['options'] as $opt ) {
								$opt_details = $this->get_option_admin_name_and_label( $opt );
								if ( strcasecmp( $opt_details['label'], $term->name ) === 0 || strcasecmp( $opt_details['admin_name'], $term->name ) === 0 ) {
									$found = true;
									break;
								}
							}
						}
						if ( ! $found ) {
							$merged_options[] = array(
								'admin_name'   => $term->name,
								'sort_order'   => count( $merged_options ) + 1,
								'translations' => array(
									'en' => array(
										'label' => $term->name,
									),
								),
							);
						}
					}
				}

				$body['options'] = $merged_options;

				$result = $this->helper->bagisto_curl_request( $url, 'PUT', $body );
				$data   = json_decode( $result, true );

				$attr_res_data = array();
				if ( ! empty( $data['id'] ) ) {
					$attr_res_data = $data;
				} elseif ( ! empty( $data['data']['id'] ) ) {
					$attr_res_data = $data['data'];
				}

				if ( ! empty( $attr_res_data ) && ! empty( $attr_res_data['id'] ) ) {
					$response['success'] = true;
					$response['data']    = $attr_res_data;
					$response['message'] = esc_html__( 'Attribute updated successfully.', 'bagisto-sync-for-woocommerce' );
				} else {
					$error_msg = esc_html__( 'Failed to update attribute on Bagisto.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $data['detail'] ) ) {
						$error_msg = esc_html( $data['detail'] );
					} elseif ( ! empty( $data['message'] ) ) {
						$error_msg = esc_html( $data['message'] );
					} elseif ( ! empty( $data['error'] ) ) {
						$error_msg = esc_html( $data['error'] );
					} elseif ( ! empty( $data['title'] ) ) {
						$error_msg = esc_html( $data['title'] );
					}
					throw new \Exception( esc_html( $error_msg ) );
				}
			} else {
				$url = $this->bagisto_base_url . '/attributes';

				$result = $this->helper->bagisto_curl_request( $url, 'POST', $body );
				$data   = json_decode( $result, true );

				$attr_res_data = array();
				if ( ! empty( $data['id'] ) ) {
					$attr_res_data = $data;
				} elseif ( ! empty( $data['data']['id'] ) ) {
					$attr_res_data = $data['data'];
				}

				if ( ! empty( $attr_res_data ) && ! empty( $attr_res_data['id'] ) ) {
					$new_id = intval( $attr_res_data['id'] );
					$this->update_bagisto_attribute_id( $taxonomy_slug, $new_id );
					$response['success'] = true;
					$response['data']    = $attr_res_data;
					$response['message'] = esc_html__( 'Attribute created successfully.', 'bagisto-sync-for-woocommerce' );
				} else {
					$err_text = '';
					if ( ! empty( $data['detail'] ) ) {
						$err_text = $data['detail'];
					} elseif ( ! empty( $data['message'] ) ) {
						$err_text = $data['message'];
					} elseif ( ! empty( $data['error'] ) ) {
						$err_text = $data['error'];
					}

					if ( empty( $err_text ) && ! empty( $data['errors'] ) ) {
						if ( is_array( $data['errors'] ) ) {
							$err_text = wp_json_encode( $data['errors'] );
						} else {
							$err_text = (string) $data['errors'];
						}
					}

					$is_taken = false;
					if ( ! empty( $err_text ) && ( false !== stripos( $err_text, 'code has already been taken' ) || false !== stripos( $err_text, 'code is already taken' ) || ( false !== stripos( $err_text, 'code' ) && false !== stripos( $err_text, 'taken' ) ) ) ) {
						$is_taken = true;
					}

					if ( $is_taken ) {
						$attribute_code = str_replace( '-', '_', str_replace( 'pa_', '', $attribute->slug ) );
						$found_id       = $this->get_bagisto_attribute_id_by_code( $attribute_code );
						if ( $found_id ) {
							$this->update_bagisto_attribute_id( $taxonomy_slug, $found_id );
							return $this->sync_attribute_with_bagisto( $attribute, $found_id );
						}
					}

					$error_msg = esc_html__( 'Failed to create attribute on Bagisto.', 'bagisto-sync-for-woocommerce' );
					if ( ! empty( $data['detail'] ) ) {
						$error_msg = esc_html( $data['detail'] );
					} elseif ( ! empty( $data['message'] ) ) {
						$error_msg = esc_html( $data['message'] );
					} elseif ( ! empty( $data['error'] ) ) {
						$error_msg = esc_html( $data['error'] );
					} elseif ( ! empty( $data['title'] ) ) {
						$error_msg = esc_html( $data['title'] );
					}
					throw new \Exception( esc_html( $error_msg ) );
				}
			}

			if ( ! empty( $response['success'] ) && ! empty( $response['data']['id'] ) ) {
				$this->assign_attribute_to_family( intval( $response['data']['id'] ) );
			}

			return $response;
		}

		/**
		 * Assign the synced attribute to the default attribute family (ID 1)
		 *
		 * @param int $attribute_id Attribute ID in Bagisto.
		 * @return bool
		 */
		private function assign_attribute_to_family( $attribute_id ) {
			try {
				if ( isset( $this->helper->logger ) ) {
					$this->helper->logger->wkwctobag_info( sprintf( 'Checking default family mapping for attribute ID: %d', $attribute_id ), array( 'source' => 'attribute' ) );
				}

				$family_id = 1;
				$get_url   = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/families/' . $family_id;
				$res       = $this->helper->bagisto_curl_request( $get_url, 'GET' );
				if ( empty( $res ) ) {
					if ( isset( $this->helper->logger ) ) {
						$this->helper->logger->wkwctobag_error( sprintf( 'Failed to fetch family ID %d details from Bagisto API.', $family_id ), array( 'source' => 'attribute' ) );
					}
					return false;
				}

				$data        = json_decode( $res, true );
				$family_data = array();
				if ( ! empty( $data['id'] ) ) {
					$family_data = $data;
				} elseif ( ! empty( $data['data']['id'] ) ) {
					$family_data = $data['data'];
				}

				if ( empty( $family_data ) || empty( $family_data['attributeGroups'] ) ) {
					if ( isset( $this->helper->logger ) ) {
						$this->helper->logger->wkwctobag_error( sprintf( 'Invalid response or empty attribute groups for family ID %d.', $family_id ), array( 'source' => 'attribute' ) );
					}
					return false;
				}

				$code             = $family_data['code'];
				$name             = $family_data['name'];
				$attribute_groups = array();

				$already_mapped = false;
				foreach ( $family_data['attributeGroups'] as $group ) {
					if ( ! empty( $group['attributes'] ) ) {
						foreach ( $group['attributes'] as $attr ) {
							if ( intval( $attr['id'] ) === intval( $attribute_id ) ) {
								$already_mapped = true;
								break 2;
							}
						}
					}
				}

				if ( $already_mapped ) {
					if ( isset( $this->helper->logger ) ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Attribute ID %d is already mapped to the default family.', $attribute_id ), array( 'source' => 'attribute' ) );
					}
					return true;
				}

				foreach ( $family_data['attributeGroups'] as $group ) {
					$group_id          = intval( $group['id'] );
					$custom_attributes = array();

					if ( ! empty( $group['attributes'] ) ) {
						foreach ( $group['attributes'] as $idx => $attr ) {
							$custom_attributes[] = array(
								'id'       => intval( $attr['id'] ),
								'position' => $idx + 1,
							);
						}
					}

					$attribute_groups[ $group_id ] = array(
						'code'              => $group['code'],
						'name'              => $group['name'],
						'column'            => intval( $group['column'] ),
						'position'          => intval( $group['position'] ),
						'custom_attributes' => $custom_attributes,
					);
				}

				$target_group_id = null;
				foreach ( $attribute_groups as $gid => $group ) {
					if ( 'general' === $group['code'] ) {
						$target_group_id = $gid;
						break;
					}
				}

				if ( null === $target_group_id ) {
					$keys            = array_keys( $attribute_groups );
					$target_group_id = reset( $keys );
				}

				if ( $target_group_id ) {
					if ( isset( $this->helper->logger ) ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Assigning attribute ID %d to group ID %d (%s) in default family...', $attribute_id, $target_group_id, $attribute_groups[ $target_group_id ]['code'] ), array( 'source' => 'attribute' ) );
					}
					$attribute_groups[ $target_group_id ]['custom_attributes'][] = array(
						'id'       => intval( $attribute_id ),
						'position' => count( $attribute_groups[ $target_group_id ]['custom_attributes'] ) + 1,
					);
				}

				$put_body = array(
					'code'             => $code,
					'name'             => $name,
					'attribute_groups' => $attribute_groups,
				);

				$put_url = $this->helper->get_bagisto_site_url() . '/api/admin/catalog/families/' . $family_id;
				$put_res = $this->helper->bagisto_curl_request( $put_url, 'PUT', $put_body );

				if ( ! empty( $put_res ) ) {
					if ( isset( $this->helper->logger ) ) {
						$this->helper->logger->wkwctobag_info( sprintf( 'Successfully assigned attribute ID %d to the default family.', $attribute_id ), array( 'source' => 'attribute' ) );
					}
					return true;
				} else {
					if ( isset( $this->helper->logger ) ) {
						$this->helper->logger->wkwctobag_error( sprintf( 'Failed to assign attribute ID %d to the default family via API.', $attribute_id ), array( 'source' => 'attribute' ) );
					}
					return false;
				}
			} catch ( \Exception $e ) {
				if ( isset( $this->helper->logger ) ) {
					$this->helper->logger->wkwctobag_error( 'Failed to assign attribute to family: ' . $e->getMessage(), array( 'source' => 'attribute' ) );
				}
				return false;
			}
		}

		/**
		 * Synchronize options only.
		 */
		private function sync_attribute_options_only( $attribute, $bagisto_attr_id ) {
			return $this->sync_attribute_with_bagisto( $attribute, $bagisto_attr_id );
		}

		/**
		 * Fetch Bagisto attribute detail.
		 */
		private function get_bagisto_attribute_details( $bagisto_attr_id ) {
			$url    = $this->bagisto_base_url . '/attributes/' . $bagisto_attr_id;
			$result = $this->helper->bagisto_curl_request( $url, 'GET' );
			$data   = json_decode( $result, true );
			return isset( $data['data'] ) ? $data['data'] : $data;
		}

		/**
		 * Get option admin name and label dynamically supporting both snake_case and camelCase keys.
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
	}
}
