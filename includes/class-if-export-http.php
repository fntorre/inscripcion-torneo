<?php
/**
 * Exportación: envía CSV/Excel/PDF generados por el dominio.
 *
 * @package InscripcionesFutbol
 */

use IF\Core\Exportador;

/**
 * Captura la petición de descarga y envía el archivo.
 */
final class IF_Export_Http {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'descargar' ) );
	}

	/**
	 * Detecta y ejecuta la descarga.
	 */
	public static function descargar() {
		if ( empty( $_GET['if_exportar'] ) ) {
			return;
		}
		$formato = isset( $_GET['if_formato'] ) ? sanitize_key( $_GET['if_formato'] ) : Exportador::CSV;
		$equipo  = isset( $_GET['if_equipo'] ) ? intval( $_GET['if_equipo'] ) : 0;
		$servicio = IF_App::servicio();

		if ( $equipo ) {
			self::verificar_acceso( $equipo );
			$rows = $servicio->filasEquipo( $equipo );
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
			}
			$rows = $servicio->filasGeneral();
		}

		$archivo = Exportador::generar( $formato, $rows );
		if ( ! $archivo ) {
			$archivo = Exportador::generar( Exportador::CSV, $rows );
		}

		nocache_headers();
		foreach ( $archivo['headers'] as $cabecera => $valor ) {
			header( $cabecera . ': ' . $valor );
		}
		echo $archivo['content']; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Verifica acceso del delegado/admin al equipo.
	 *
	 * @param int $equipo_id ID.
	 */
	private static function verificar_acceso( $equipo_id ) {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Iniciá sesión para descargar.', 'inscripciones-futbol' ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		$equipo = IF_App::store()->obtenerEquipo( $equipo_id );
		if ( ! $equipo || $equipo->delegadoId !== get_current_user_id() ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
	}
}
