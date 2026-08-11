<?php
/**
 * Entidad Jugador (dominio puro).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Jugador de un equipo.
 */
final class Jugador {

	const POS_ARQUERO      = 'arquero';
	const POS_DEFENSA      = 'defensa';
	const POS_MEDIOCAMPO   = 'mediocampista';
	const POS_DELANTERO    = 'delantero';

	const ROL_TITULAR      = 'titular';
	const ROL_SUPLENTE     = 'suplente';
	const ROL_CAPITAN      = 'capitan';

	/** @var int */
	public $id = 0;

	/** @var int */
	public $equipoId = 0;

	/** @var string */
	public $nombre = '';

	/** @var string */
	public $apellido = '';

	/** @var string */
	public $dni = '';

	/** @var string */
	public $archivoDni = '';

	/** @var string URL de la foto del jugador */
	public $foto = '';

	/** @var string Posición: arquero, defensa, mediocampista, delantero */
	public $posicion = '';

	/** @var string Rol: titular, suplente, capitan */
	public $rol = '';

	/**
	 * @return string
	 */
	public function nombreCompleto() {
		return trim( $this->nombre . ' ' . $this->apellido );
	}

	/**
	 * Etiquetas de posiciones.
	 *
	 * @return array
	 */
	public static function posiciones() {
		return array(
			self::POS_ARQUERO    => 'Arquero',
			self::POS_DEFENSA    => 'Defensa',
			self::POS_MEDIOCAMPO => 'Mediocampista',
			self::POS_DELANTERO  => 'Delantero',
		);
	}

	/**
	 * Etiquetas de roles.
	 *
	 * @return array
	 */
	public static function roles() {
		return array(
			self::ROL_TITULAR  => 'Titular',
			self::ROL_SUPLENTE => 'Suplente',
			self::ROL_CAPITAN  => 'Capitán',
		);
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return array(
			'id'         => $this->id,
			'equipoId'   => $this->equipoId,
			'nombre'     => $this->nombre,
			'apellido'   => $this->apellido,
			'dni'        => $this->dni,
			'archivoDni' => $this->archivoDni,
			'foto'       => $this->foto,
			'posicion'   => $this->posicion,
			'rol'        => $this->rol,
		);
	}

	/**
	 * @param array $datos Datos.
	 * @return self
	 */
	public static function fromArray( $datos ) {
		$j                = new self();
		$j->id            = isset( $datos['id'] ) ? (int) $datos['id'] : 0;
		$j->equipoId      = isset( $datos['equipoId'] ) ? (int) $datos['equipoId'] : 0;
		$j->nombre        = isset( $datos['nombre'] ) ? (string) $datos['nombre'] : '';
		$j->apellido      = isset( $datos['apellido'] ) ? (string) $datos['apellido'] : '';
		$j->dni           = isset( $datos['dni'] ) ? (string) $datos['dni'] : '';
		$j->archivoDni    = isset( $datos['archivoDni'] ) ? (string) $datos['archivoDni'] : '';
		$j->foto          = isset( $datos['foto'] ) ? (string) $datos['foto'] : '';
		$j->posicion      = isset( $datos['posicion'] ) ? (string) $datos['posicion'] : '';
		$j->rol           = isset( $datos['rol'] ) ? (string) $datos['rol'] : '';
		return $j;
	}
}