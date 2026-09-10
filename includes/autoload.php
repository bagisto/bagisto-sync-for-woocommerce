<?php
/**
 * Dynamically loads classes
 *
 * @package Bagisto Sync for WooCommerce
 */

namespace WKWCBAGCONNECTOR\Inc;

defined( 'ABSPATH' ) || exit();

spl_autoload_register( 'WKWCBAGCONNECTOR\Inc\wkwctobag_namespace_class_autoload' );

/**
 * Autoload callback
 *
 * @param string $class_name The name of the class to load.
 * @return void
 */
function wkwctobag_namespace_class_autoload( $class_name ) {
	if ( false === strpos( $class_name, 'WKWCBAGCONNECTOR' ) ) {
		return;
	}

	$file_parts = explode( '\\', $class_name );
	$namespace  = '';

	for ( $i = count( $file_parts ) - 1; $i > 0; $i-- ) {
		$current = strtolower( $file_parts[ $i ] );
		$current = str_ireplace( '_', '-', $current );

		if ( count( $file_parts ) - 1 === $i ) {
			if ( strpos( strtolower( $file_parts[ count( $file_parts ) - 1 ] ), 'interface' ) ) {
				$interface_name = explode( '_', $file_parts[ count( $file_parts ) - 1 ] );
				array_pop( $interface_name );
				$interface_name = strtolower( implode( '-', $interface_name ) );
				$file_name      = "interface-{$interface_name}.php";
			} else {
				$file_name = "class-{$current}.php";
			}
		} else {
			$namespace = '/' . $current . $namespace;
		}

		$filepath  = trailingslashit( dirname( __DIR__, 1 ) . $namespace );
		$filepath .= $file_name;
	}

	// If the file exists in the specified path, then include it.
	if ( file_exists( $filepath ) ) {
		require_once $filepath;
	} else {
		/* Do not expose the resolved server path to the browser. */
		wp_die(
			/* translators: %s: class name that could not be loaded */
			sprintf( esc_html__( 'Bagisto Sync for WooCommerce could not load the class %s. Please reinstall the plugin.', 'bagisto-sync-for-woocommerce' ), esc_html( $class_name ) )
		);
	}
}
