<?php
/**
 * Resultado de operaciones del dominio (puro).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Resultado de una operación: éxito + mensajes + datos.
 */
final class Resultado {

	/** @var bool */
	public $ok;

	/** @var string[] */
	public $errores = array();

	/** @var array */
	public $datos = array();

	/**
	 * @param bool $ok Éxito.
	 */
	private function __construct( $ok ) {
		$this->ok = $ok;
	}

	/**
	 * Resultado de éxito.
	 *
	 * @param array $datos Datos.
	 * @return self
	 */
	public static function exito( $datos = array() ) {
		$r         = new self( true );
		$r->datos  = $datos;
		return $r;
	}

	/**
	 * Resultado de error.
	 *
	 * @param string|array $errores Mensajes.
	 * @return self
	 */
	public static function error( $errores ) {
		$r        = new self( false );
		$r->errores = is_array( $errores ) ? $errores : array( $errores );
		return $r;
	}

	/**
	 * Primer error.
	 *
	 * @return string
	 */
	public function primerError() {
		return $this->errores ? $this->errores[0] : '';
	}
}