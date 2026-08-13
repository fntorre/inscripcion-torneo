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

	/**
	 * @return string
	 */
	public function nombreCompleto() {
		return trim( $this->nombre . ' ' . $this->apellido );
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
		return $j;
	}
}