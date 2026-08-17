<?php
/**
 * Integración con Mercado Pago (Checkout Pro).
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase IF_MercadoPago
 */
class IF_MercadoPago {

	const API_URL = 'https://api.mercadopago.com';

	/**
	 * Token de acceso.
	 *
	 * @return string
	 */
	public static function get_access_token() {
		return (string) get_option( 'if_mp_access_token', '' );
	}

	/**
	 * Crea preferencia de pago en MP y devuelve la URL de checkout.
	 *
	 * @param int    $equipo_id ID del equipo.
	 * @param string $titulo    Titulo del item.
	 * @return string|WP_Error
	 */
	public static function crear_preferencia( $equipo_id, $titulo ) {
		$token = self::get_access_token();
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'No hay Access Token configurado. Andá a Inscripciones > Ajustes.' );
		}

		$monto = (float) if_get_monto_inscripcion();
		$back  = home_url( '/' );

		$body = array(
			'items' => array(
				array(
					'title'       => sanitize_text_field( $titulo ),
					'quantity'    => 1,
					'unit_price'  => $monto,
					'currency_id' => 'ARS',
				),
			),
			'auto_return'      => 'approved',
			'back_urls'        => array(
				'success' => $back . '?if_pago_resultado=aprobado&if_equipo=' . $equipo_id,
				'pending' => $back . '?if_pago_resultado=pendiente&if_equipo=' . $equipo_id,
				'failure' => $back . '?if_pago_resultado=rechazado&if_equipo=' . $equipo_id,
			),
			'notification_url' => home_url( '/if_webhook/' ),
			'external_reference' => (string) $equipo_id,
		);

		$response = wp_remote_post(
			self::API_URL . '/checkout/preferences',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[IF MP] Error conexion: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		error_log( '[IF MP] API response HTTP ' . $code . ': ' . substr( $raw, 0, 500 ) );

		if ( 201 !== $code || empty( $data['init_point'] ) ) {
			$msg = isset( $data['message'] ) ? $data['message'] : 'Error HTTP ' . $code;
			return new WP_Error( 'mp_error', $msg );
		}

		update_post_meta( $equipo_id, '_if_pago_pref_id', isset( $data['id'] ) ? $data['id'] : '' );

		return $data['init_point'];
	}

	/**
	 * Consulta estado de un pago en MP.
	 *
	 * @param int|string $payment_id ID del pago.
	 * @return string
	 */
	public static function get_pago_estado_mp( $payment_id ) {
		$token = self::get_access_token();
		if ( ! $token ) {
			return '';
		}
		$response = wp_remote_get(
			self::API_URL . '/v1/payments/' . intval( $payment_id ),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['status'] ) ? $data['status'] : '';
	}

	/**
	 * Busca pagos aprobados en MP para un equipo (external_reference).
	 *
	 * @param int $equipo_id ID del equipo.
	 * @return bool
	 */
	public static function equipo_pago_aprobado_mp( $equipo_id ) {
		$token = self::get_access_token();
		if ( ! $token ) {
			return false;
		}
		$response = wp_remote_get(
			add_query_arg(
				array(
					'external_reference' => (string) $equipo_id,
					'status'             => 'approved',
					'sort'               => 'date_created',
					'criteria'           => 'desc',
					'limit'              => 5,
				),
				self::API_URL . '/v1/payments/search'
			),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['results'] ) ) {
			return false;
		}
		foreach ( $data['results'] as $pago ) {
			if ( isset( $pago['status'] ) && 'approved' === $pago['status'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Traduce estado MP a estado interno.
	 *
	 * @param string $status Estado MP.
	 * @return string
	 */
	public static function map_estado( $status ) {
		switch ( $status ) {
			case 'approved':
				return 'aprobado';
			case 'rejected':
			case 'cancelled':
			case 'refunded':
			case 'charged_back':
				return 'rechazado';
			default:
				return 'pendiente';
		}
	}

	/**
	 * Registra un pago y actualiza el equipo.
	 *
	 * @param int    $equipo_id  ID del equipo.
	 * @param string $payment_id ID de pago MP.
	 * @return string
	 */
	public static function registrar_pago( $equipo_id, $payment_id ) {
		$estado_mp = self::get_pago_estado_mp( $payment_id );
		$estado    = self::map_estado( $estado_mp );

		$pago_id = wp_insert_post(
			array(
				'post_type'   => IF_Post_Types::PAGO,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Inscripción equipo #%d', $equipo_id ),
			)
		);
		if ( $pago_id ) {
			update_post_meta( $pago_id, '_if_mpid', $payment_id );
			update_post_meta( $pago_id, '_if_pago_estado', $estado );
			update_post_meta( $pago_id, '_if_equipo_id', $equipo_id );
		}
		if_set_equipo_pago_estado( $equipo_id, $estado );
		return $estado;
	}
}
