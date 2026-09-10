<?php
/**
 * Settings page template class file.
 *
 * @package Bagisto Sync for WooCommerce
 * @version 1.0.0
 */

namespace WKWCBAGCONNECTOR\Templates\Admin\Settings;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

if ( ! class_exists( 'Wkwctobag_Settings' ) ) {
	/**
	 * Settings page template class
	 */
	class Wkwctobag_Settings {

		/**
		 * Constructor of the settings page.
		 */
		public function __construct() {
			$this->get_settings_template();
		}

		/**
		 * Get settings template.
		 */
		public function get_settings_template() {
			$page        = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$current_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( isset( $page ) && ! empty( $page ) && 'wkwctobag-settings' === $page ) {
				$tabs = array(
					'user'     => '<span class="dashicons dashicons-admin-users"></span>' . esc_html__( 'User', 'bagisto-sync-for-woocommerce' ),
					'customer' => '<span class="dashicons dashicons-businessman"></span>' . esc_html__( 'Customer', 'bagisto-sync-for-woocommerce' ),
					'product'  => '<span class="dashicons dashicons-archive"></span>' . esc_html__( 'Product', 'bagisto-sync-for-woocommerce' ),
					'tax'      => '<span class="dashicons dashicons-money-alt"></span>' . esc_html__( 'Tax', 'bagisto-sync-for-woocommerce' ),
					'order'    => '<span class="dashicons dashicons-cart"></span>' . esc_html__( 'Order', 'bagisto-sync-for-woocommerce' ),
				);

				if ( ! array_key_exists( $current_tab, $tabs ) ) {
					$current_tab = 'user';
				}

				echo '<div class="wrap">';
				echo '<h1>' . esc_html__( 'Connector Settings', 'bagisto-sync-for-woocommerce' ) . '</h1>';

				// Render tab navigation
				echo '<nav class="nav-tab-wrapper">';
				foreach ( $tabs as $tab_name => $tab_label ) {
					$tab_url = add_query_arg( 'tab', $tab_name );
					$active  = ( $current_tab === $tab_name ) ? 'nav-tab-active' : '';
					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab ' . esc_attr( $active ) . '">' . wp_kses_post( $tab_label ) . '</a>';
				}
				echo '</nav>';

				do_action( 'wkwctobag_settings_tab_' . $current_tab, $this );

				echo '</div>';

			}
		}
	}
}
