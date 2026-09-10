<?php
/**
 * Plugin Name: Bagisto Sync for WooCommerce
 * Description: A WooCommerce addon by Webkul to seamlessly connect and sync WooCommerce store data with Bagisto.
 * Version: 1.0.0
 * Author: Webkul
 * Author URI: https://profiles.wordpress.org/webkul/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: bagisto-sync-for-woocommerce
 * Domain Path: /languages
 *
 * Requires at least:    6.5
 * Tested up to:         7.0
 * WC requires at least: 9.0
 * WC tested up to:      10.9
 * Requires PHP:         7.4
 * Tested up to PHP:     8.4
 *
 * Requires Plugins: woocommerce
 *
 * @package Bagisto Sync for WooCommerce
 *
 * Bagisto Sync for WooCommerce
 * Copyright (C) 2026 Webkul
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use WKWCBAGCONNECTOR\Includes;

defined( 'ABSPATH' ) || exit(); // Exit if access directly.

// Define Constants.
defined( 'WKWCTOBAG_PLUGIN_FILE' ) || define( 'WKWCTOBAG_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
defined( 'WKWCTOBAG_PLUGIN_URL' ) || define( 'WKWCTOBAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'WKWCTOBAG_BASENAME' ) || define( 'WKWCTOBAG_BASENAME', plugin_basename( __FILE__ ) );
defined( 'WKWCTOBAG_VERSION' ) || define( 'WKWCTOBAG_VERSION', '1.0.0' );
defined( 'WKWCTOBAG_SCRIPT_VERSION' ) || define( 'WKWCTOBAG_SCRIPT_VERSION', '1.0.0' );


// Exclude development-only directories from Plugin Check analysis.
add_filter(
	'wp_plugin_check_ignore_directories',
	function ( $directories ) {
		return array_unique(
			array_merge(
				(array) $directories,
				array(
					'.git',
					'.github',
					'bin',
					'node_modules',
					'playwright-report',
					'src',
					'test-results',
					'tests',
					'vendor',
				)
			)
		);
	}
);

// Exclude development-only files from Plugin Check analysis.
add_filter(
	'wp_plugin_check_ignore_files',
	function ( $files ) {
		return array_unique(
			array_merge(
				(array) $files,
				array(
					'.distignore',
					'.editorconfig',
					'.env',
					'.gitattributes',
					'.gitignore',
					'babel.config.js',
					'composer.json',
					'composer.lock',
					'Gruntfile.js',
					'package-lock.json',
					'package.json',
					'phpcs.xml.dist',
					'webpack.config.js',
				)
			)
		);
	}
);

if ( ! function_exists( 'wkwctobag_includes' ) ) {
	/**
	 * Includes function
	 *
	 * @return void
	 */
	function wkwctobag_includes() {
		require_once WKWCTOBAG_PLUGIN_FILE . 'includes/autoload.php';
		Includes\Wkwctobag_File_Handler::get_instance();
	}

	add_action( 'plugins_loaded', 'wkwctobag_includes' );
}

if ( ! function_exists( 'wkwctobag_register_cron_schedules' ) ) {
	/**
	 * Register custom cron interval required by the background process.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	function wkwctobag_register_cron_schedules( $schedules ) {
		$types = array( 'product', 'category', 'order', 'customer', 'user', 'attribute', 'tax' );
		foreach ( $types as $type ) {
			$schedules[ "wp_wkwctobag_sync_{$type}_cron_interval" ] = array(
				'interval' => 300,
				'display'  => sprintf(
				/* translators: %s: sync type (e.g. product, category, order) */
					esc_html__( 'Every 5 Minutes (Bagisto %s Sync)', 'bagisto-sync-for-woocommerce' ),
					ucfirst( $type )
				),
			); // phpcs:ignore
		}
		return $schedules;
	}

	add_filter( 'cron_schedules', 'wkwctobag_register_cron_schedules' );
}

if ( ! function_exists( 'wkwctobag_declear_woocommerce_compatibility' ) ) {
	/**
	 * Declear woocommerce compatibility.
	 */
	function wkwctobag_declear_woocommerce_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	add_action( 'before_woocommerce_init', 'wkwctobag_declear_woocommerce_compatibility' );
}

if ( ! function_exists( 'wkwctobag_schedule_log_cleanup' ) ) {
	/**
	 * Schedule daily log cleanup cron event.
	 */
	function wkwctobag_schedule_log_cleanup() {
		if ( ! wp_next_scheduled( 'wkwctobag_daily_log_cleanup_event' ) ) {
			wp_schedule_event( time(), 'daily', 'wkwctobag_daily_log_cleanup_event' );
		}
	}

	add_action( 'init', 'wkwctobag_schedule_log_cleanup' );
}

if ( ! function_exists( 'wkwctobag_handle_daily_log_cleanup' ) ) {
	/**
	 * Callback handler for daily log cleanup event.
	 */
	function wkwctobag_handle_daily_log_cleanup() {
		$helper = \WKWCBAGCONNECTOR\Helper\Wkwctobag_Helper::get_instance();
		if ( isset( $helper->logger ) ) {
			$helper->logger->clean_expired_logs();
		}
	}

	add_action( 'wkwctobag_daily_log_cleanup_event', 'wkwctobag_handle_daily_log_cleanup' );
}

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'wkwctobag_daily_log_cleanup_event' );
	}
);
