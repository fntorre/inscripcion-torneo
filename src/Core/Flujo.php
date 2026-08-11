<?php
/**
 * Flujo (wizard) de inscripción (dominio puro).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Calcula en qué paso del proceso está un delegado/equipo.
 */
final class Flujo {

	public const PASO_DATOS     = 'datos';
	public const PASO_EQUIPO    = 'equipo';
	public const PASO_PAGO      = 'pago';
	public const PASO_JUGADORES = 'jugadores';

	/**
	 * Pasos del proceso con sus etiquetas.
	 *
	 * @return array<string,array{etiqueta:string,ayuda:string}>
	 */
	public static function pasos() {
		return array(
			self::PASO_DATOS     => array( 'etiqueta' => 'Delegado',     'ayuda' => 'Tus datos personales' ),
			self::PASO_EQUIPO    => array( 'etiqueta' => 'Equipo',       'ayuda' => 'Datos del equipo' ),
			self::PASO_PAGO      => array( 'etiqueta' => 'Pago',         'ayuda' => 'Abonar la inscripción' ),
			self::PASO_JUGADORES => array( 'etiqueta' => 'Jugadores',    'ayuda' => 'Carga de la lista' ),
		);
	}

	/**
	 * Paso actual del delegado según sus datos y equipo.
	 *
	 * @param Delegado|null $delegado Delegado.
	 * @param Equipo|null   $equipo   Equipo.
	 * @return string
	 */
	public static function pasoActual( $delegado, $equipo ) {
		if ( ! $delegado || ! $delegado->datosCompletos() ) {
			return self::PASO_DATOS;
		}
		if ( ! $equipo ) {
			return self::PASO_EQUIPO;
		}
		if ( ! Estado::permiteCargarJugadores( $equipo->estado ) ) {
			return self::PASO_PAGO;
		}
		return self::PASO_JUGADORES;
	}

	/**
	 * Índice (1-4) del paso actual.
	 *
	 * @param string $paso Paso.
	 * @return int
	 */
	public static function indice( $paso ) {
		$orden = array_keys( self::pasos() );
		$i     = array_search( $paso, $orden, true );
		return false === $i ? 1 : $i + 1;
	}

	/**
	 * Progreso porcentual (25, 50, 75, 100).
	 *
	 * @param string $paso Paso.
	 * @return int
	 */
	public static function progreso( $paso ) {
		return self::indice( $paso ) * 25;
	}

	/**
	 * Pasos completados (array de claves) hasta el paso actual.
	 *
	 * @param string $paso Paso.
	 * @return string[]
	 */
	public static function completados( $paso ) {
		$orden   = array_keys( self::pasos() );
		$idx     = self::indice( $paso );
		return array_slice( $orden, 0, $idx );
	}
}
