<?php
/**
 * Funciones auxiliares compartidas.
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Estados posibles de un pago.
 *
 * @return array
 */
function if_pago_estados() {
	return array(
		'pendiente' => __( 'Pendiente', 'inscripciones-futbol' ),
		'aprobado'  => __( 'Aprobado', 'inscripciones-futbol' ),
		'rechazado' => __( 'Rechazado', 'inscripciones-futbol' ),
	);
}

/**
 * Etiqueta de estado de pago.
 *
 * @param string $estado Estado.
 * @return string
 */
function if_pago_estado_label( $estado ) {
	$estados = if_pago_estados();
	return isset( $estados[ $estado ] ) ? $estados[ $estado ] : $estado;
}

/**
 * Datos personales de un delegado.
 *
 * @param int $user_id ID del usuario.
 * @return array
 */
function if_get_delegado_meta( $user_id ) {
	return array(
		'nombre'   => get_user_meta( $user_id, '_if_nombre', true ),
		'apellido' => get_user_meta( $user_id, '_if_apellido', true ),
		'dni'      => get_user_meta( $user_id, '_if_dni', true ),
		'telefono' => get_user_meta( $user_id, '_if_telefono', true ),
		'email'    => get_the_author_meta( 'user_email', $user_id ),
	);
}

/**
 * Equipo de un delegado.
 *
 * @param int $user_id ID del usuario.
 * @return int|false
 */
function if_get_equipo_delegado( $user_id ) {
	$equipos = get_posts(
		array(
			'post_type'      => IF_Post_Types::EQUIPO,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'author'         => $user_id,
			'fields'         => 'ids',
		)
	);
	return $equipos ? $equipos[0] : false;
}

/**
 * Monto de inscripción configurado.
 *
 * @return float
 */
function if_get_monto_inscripcion() {
	return (float) get_option( 'if_monto_inscripcion', 1000 );
}

/**
 * Convierte un usuario a delegado.
 *
 * @param int $user_id ID.
 */
function if_make_delegado( $user_id ) {
	$user = new WP_User( $user_id );
	$user->set_role( 'if_delegado' );
}

/**
 * Crea un equipo para el delegado.
 *
 * @param int    $user_id ID del delegado.
 * @param string $nombre  Nombre del equipo.
 * @return int
 */
function if_crear_equipo( $user_id, $nombre ) {
	return wp_insert_post(
		array(
			'post_type'   => IF_Post_Types::EQUIPO,
			'post_status' => 'publish',
			'post_title'  => $nombre,
			'post_author' => $user_id,
		)
	);
}

/**
 * Cambia el estado de pago de un equipo.
 *
 * @param int    $equipo_id ID.
 * @param string $estado    Estado.
 */
function if_set_equipo_pago_estado( $equipo_id, $estado ) {
	update_post_meta( $equipo_id, '_if_pago_estado', $estado );
}

/**
 * Devuelve la URL de inicio de sesión de WordPress.
 *
 * @return string
 */
function if_login_url() {
	return wp_login_url();
}

/**
 * Normaliza datos de exportación (quita saltos de línea).
 *
 * @param mixed $value Valor.
 * @return string
 */
function if_clean_export( $value ) {
	return trim( str_replace( array( "\r", "\n", "\t" ), ' ', (string) $value ) );
}