<?php
/**
 * Assets (CSS).
 *
 * @package InscripcionesFutbol
 */

/**
 * Encola estilos del frontend y del admin.
 */
final class IF_Assets {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
	}

	/**
	 * Frontend.
	 */
	public static function frontend() {
		wp_enqueue_style( 'if-frontend', IF_PLUGIN_URL . 'assets/css/frontend.css', array(), IF_VERSION );
	}

	/**
	 * Admin.
	 *
	 * @param string $hook Hook.
	 */
	public static function admin( $hook ) {
		wp_enqueue_style( 'if-admin', IF_PLUGIN_URL . 'assets/css/admin.css', array(), IF_VERSION );
	}
}
