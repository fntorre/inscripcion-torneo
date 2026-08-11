<?php
/**
 * Entidad Delegado (dominio puro).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Datos personales de un delegado.
 */
final class Delegado {

	/** @var int */
	public $id = 0;

	/** @var string */
	public $nombre = '';

	/** @var string */
	public $apellido = '';

	/** @var string */
	public $dni = '';

	/** @var string */
	public $telefono = '';

	/** @var string */
	public $email = '';

	/**
	 * ¿El delegado completó todos sus datos?
	 *
	 * @return bool
	 */
	public function datosCompletos() {
		return $this->nombre && $this->apellido && $this->dni && $this->telefono && $this->email;
	}

	/**
	 * Nombre completo.
	 *
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
			'id'       => $this->id,
			'nombre'   => $this->nombre,
			'apellido' => $this->apellido,
			'dni'      => $this->dni,
			'telefono' => $this->telefono,
			'email'    => $this->email,
		);
	}

	/**
	 * @param array $datos Datos.
	 * @return self
	 */
	public static function fromArray( $datos ) {
		$d              = new self();
		$d->id          = isset( $datos['id'] ) ? (int) $datos['id'] : 0;
		$d->nombre      = isset( $datos['nombre'] ) ? (string) $datos['nombre'] : '';
		$d->apellido    = isset( $datos['apellido'] ) ? (string) $datos['apellido'] : '';
		$d->dni         = isset( $datos['dni'] ) ? (string) $datos['dni'] : '';
		$d->telefono    = isset( $datos['telefono'] ) ? (string) $datos['telefono'] : '';
		$d->email       = isset( $datos['email'] ) ? (string) $datos['email'] : '';
		return $d;
	}
}
