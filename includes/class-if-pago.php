<?php
/**
 * Pagos por link de Mercado Pago (sin integración de API).
 *
 * El plugin no se conecta a Mercado Pago: el administrador carga un link
 * de pago (general o por equipo) y cambia manualmente el estado a
 * pagado / pendiente / rechazado.
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase IF_Pago
 */
class IF_Pago {

	/**
	 * Inicializa.
	 */
	public static function init() {
		add_action( 'admin_post_if_estado_pago', array( __CLASS__, 'cambiar_estado' ) );
	}

	/**
	 * Link general de Mercado Pago configurado por el admin.
	 *
	 * @return string
	 */
	public static function get_link_general() {
		return (string) get_option( 'if_mp_link', '' );
	}

	/**
	 * Link de pago de un equipo (específico o general).
	 *
	 * @param int $equipo_id ID del equipo.
	 * @return string
	 */
	public static function get_link_equipo( $equipo_id ) {
		$link = get_post_meta( $equipo_id, '_if_mp_link', true );
		if ( ! $link ) {
			$link = self::get_link_general();
		}
		return $link;
	}

	/**
	 * Cambia el estado de pago de un equipo desde el admin.
	 */
	public static function cambiar_estado() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
		check_admin_referer( 'if_estado_pago' );

		$equipo_id = isset( $_GET['equipo'] ) ? intval( $_GET['equipo'] ) : 0;
		$estado    = isset( $_GET['estado'] ) ? sanitize_key( $_GET['estado'] ) : '';

		if ( ! array_key_exists( $estado, if_pago_estados() ) ) {
			wp_die( esc_html__( 'Estado inválido.', 'inscripciones-futbol' ) );
		}
		if ( get_post( $equipo_id ) ) {
			if_set_equipo_pago_estado( $equipo_id, $estado );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . IF_Post_Types::EQUIPO ) );
		exit;
	}
}