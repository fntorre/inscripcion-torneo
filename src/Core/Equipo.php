<?php
/**
 * Entidad Equipo (dominio puro).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Equipo de un delegado y su inscripción.
 */
final class Equipo {

	/** @var int */
	public $id = 0;

	/** @var string */
	public $nombre = '';

	/** @var int */
	public $delegadoId = 0;

	/** @var string Estado::* */
	public $estado = Estado::PENDIENTE;

	/** @var string Estado previo antes de bloquear */
	public $estadoPrevio = '';

	/** @var string Link de pago específico (opcional) */
	public $linkPago = '';

	/** @var string URL del comprobante de pago adjunto */
	public $comprobante = '';

	/** @var string URL de la imagen del escudo */
	public $escudo = '';

	/**
	 * @return array
	 */
	public function toArray() {
		return array(
			'id'           => $this->id,
			'nombre'       => $this->nombre,
			'delegadoId'   => $this->delegadoId,
			'estado'       => $this->estado,
			'estadoPrevio' => $this->estadoPrevio,
			'linkPago'     => $this->linkPago,
			'comprobante'  => $this->comprobante,
			'escudo'       => $this->escudo,
		);
	}

	/**
	 * @param array $datos Datos.
	 * @return self
	 */
	public static function fromArray( $datos ) {
		$e                = new self();
		$e->id            = isset( $datos['id'] ) ? (int) $datos['id'] : 0;
		$e->nombre        = isset( $datos['nombre'] ) ? (string) $datos['nombre'] : '';
		$e->delegadoId    = isset( $datos['delegadoId'] ) ? (int) $datos['delegadoId'] : 0;
		$e->estado        = isset( $datos['estado'] ) && in_array( $datos['estado'], Estado::todos(), true ) ? $datos['estado'] : Estado::porDefecto();
		$e->estadoPrevio  = isset( $datos['estadoPrevio'] ) ? (string) $datos['estadoPrevio'] : '';
		$e->linkPago      = isset( $datos['linkPago'] ) ? (string) $datos['linkPago'] : '';
		$e->comprobante   = isset( $datos['comprobante'] ) ? (string) $datos['comprobante'] : '';
		$e->escudo        = isset( $datos['escudo'] ) ? (string) $datos['escudo'] : '';
		return $e;
	}
}
