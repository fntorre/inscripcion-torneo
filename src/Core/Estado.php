<?php
/**
 * Máquina de estados de la inscripción (dominio puro, sin WordPress).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Estados y transiciones de la inscripción de un equipo.
 */
final class Estado {

	public const PENDIENTE  = 'pendiente';
	public const RECHAZADA  = 'rechazada';
	public const ACTIVA     = 'activa';
	public const BLOQUEADA  = 'bloqueada';

	/**
	 * Acciones y estados de origen permitidos.
	 * devuelve el estado destino, o null si la transición no está permitida.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function transiciones() {
		return array(
			'aprobar'    => array( self::PENDIENTE => self::ACTIVA, self::RECHAZADA => self::ACTIVA ),
			'rechazar'   => array( self::PENDIENTE => self::RECHAZADA, self::ACTIVA => self::RECHAZADA ),
			'marcar_pendiente' => array( self::RECHAZADA => self::PENDIENTE, self::ACTIVA => self::PENDIENTE ),
			'bloquear'   => array( self::PENDIENTE => self::BLOQUEADA, self::RECHAZADA => self::BLOQUEADA, self::ACTIVA => self::BLOQUEADA ),
		);
	}

	/**
	 * Etiquetas de los estados.
	 *
	 * @return array<string,string>
	 */
	public static function etiquetas() {
		return array(
			self::PENDIENTE => 'Pendiente de pago',
			self::RECHAZADA => 'Pago rechazado',
			self::ACTIVA    => 'Aprobada',
			self::BLOQUEADA => 'Bloqueada',
		);
	}

	/**
	 * Estados válidos.
	 *
	 * @return array
	 */
	public static function todos() {
		return array_keys( self::etiquetas() );
	}

	/**
	 * Aplica una acción a un estado.
	 *
	 * @param string $estado Estado actual.
	 * @param string $accion Acción.
	 * @return string|null Estado destino o null si no está permitido.
	 */
	public static function aplicar( $estado, $accion ) {
		$trans = self::transiciones();
		if ( ! isset( $trans[ $accion ] ) ) {
			return null;
		}
		if ( ! array_key_exists( $estado, $trans[ $accion ] ) ) {
			return null;
		}
		return $trans[ $accion ][ $estado ];
	}

	/**
	 * ¿Se permite cargar jugadores en este estado?
	 *
	 * @param string $estado Estado.
	 * @return bool
	 */
	public static function permiteCargarJugadores( $estado ) {
		return self::ACTIVA === $estado;
	}

	/**
	 * Estado por defecto de una nueva inscripción.
	 *
	 * @return string
	 */
	public static function porDefecto() {
		return self::PENDIENTE;
	}
}
