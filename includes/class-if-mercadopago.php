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
	 * Inicializa.
	 */
	public static function init() {
		add_action( 'wp_ajax_if_crear_pago', array( __CLASS__, 'ajax_crear_pago' ) );
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_webhook' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	/**
	 * Endpoint del webhook.
	 */
	public static function register_endpoint() {
		add_rewrite_rule( '^if-webhook/?$', 'index.php?if_webhook=1', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'if_webhook';
		return $vars;
	}

	/**
	 * Token de acceso.
	 *
	 * @return string
	 */
	public static function get_access_token() {
		return (string) get_option( 'if_mp_access_token', '' );
	}

	/**
	 * Crea una preferencia de pago en MP y devuelve el URL para iniciar.
	 *
	 * @param int    $equipo_id ID del equipo.
	 * @param string $titulo    Título del item.
	 * @return string|WP_Error
	 */
	public static function crear_preferencia( $equipo_id, $titulo ) {
		$token = self::get_access_token();
		if ( ! $token ) {
			return new WP_Error( 'if_mp_no_token', __( 'Pagos no disponibles: configura el token de Mercado Pago.', 'inscripciones-futbol' ) );
		}

		$monto = if_get_monto_inscripcion();
		$back  = home_url( '/' );

		$body = array(
			'items' => array(
				array(
					'title'      => sanitize_text_field( $titulo ),
					'quantity'   => 1,
					'unit_price' => $monto,
					'currency_id' => 'ARS',
				),
			),
			'auto_return'         => 'approved',
			'back_urls'           => array(
				'success' => $back . '?if_pago_resultado=aprobado&if_equipo=' . $equipo_id,
				'pending' => $back . '?if_pago_resultado=pendiente&if_equipo=' . $equipo_id,
				'failure' => $back . '?if_pago_resultado=rechazado&if_equipo=' . $equipo_id,
			),
			'notification_url'    => home_url( '/if_webhook/' ),
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
				'timeout' => 25,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 201 !== $code || empty( $data['init_point'] ) ) {
			return new WP_Error( 'if_mp_error', __( 'No se pudo generar el pago.', 'inscripciones-futbol' ) . ( isset( $data['message'] ) ? ' ' . $data['message'] : '' ) );
		}

		// Guarda referencia local del pago.
		update_post_meta( $equipo_id, '_if_pago_pref_id', isset( $data['id'] ) ? $data['id'] : '' );

		return $data['init_point'];
	}

	/**
	 * Consulta el estado de un pago en MP.
	 *
	 * @param int|string $payment_id ID del pago.
	 * @return string Estado (approved/pending/rejected...).
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
	 * Traduce un estado de MP al estado interno.
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
	 * Registra el pago y actualiza el equipo.
	 *
	 * @param int    $equipo_id  ID del equipo.
	 * @param string $payment_id ID de pago MP.
	 */
	public static function registrar_pago( $equipo_id, $payment_id ) {
		$estado_mp = self::get_pago_estado_mp( $payment_id );
		$estado    = self::map_estado( $estado_mp );

		$pago_id = wp_insert_post(
			array(
				'post_type'   => IF_Post_Types::PAGO,
				'post_status' => 'publish',
				'post_title'  => sprintf( __( 'Inscripción equipo #%d', 'inscripciones-futbol' ), $equipo_id ),
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

	/**
	 * Trata el webhook de MP.
	 */
	public static function handle_webhook() {
		$is_webhook = get_query_var( 'if_webhook' );
		if ( ! $is_webhook ) {
			return;
		}
		nocache_headers();
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true );

		$payment_id = isset( $data['data']['id'] ) ? $data['data']['id'] : ( isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0 );
		$type       = isset( $data['type'] ) ? $data['type'] : ( isset( $_GET['topic'] ) ? $_GET['topic'] : '' );

		if ( $payment_id && in_array( $type, array( 'payment', 'merchant_order' ), true ) ) {
			$token = self::get_access_token();
			if ( $token ) {
				$res = wp_remote_get(
					self::API_URL . '/v1/payments/' . intval( $payment_id ),
					array(
						'headers' => array( 'Authorization' => 'Bearer ' . $token ),
						'timeout' => 20,
					)
				);
				if ( ! is_wp_error( $res ) ) {
					$pay = json_decode( wp_remote_retrieve_body( $res ), true );
					$eqid = isset( $pay['external_reference'] ) ? intval( $pay['external_reference'] ) : 0;
					if ( $eqid ) {
						self::registrar_pago( $eqid, $payment_id );
					}
				}
			}
		}
		status_header( 200 );
		exit;
	}

	/**
	 * AJAX: crea el pago y devuelve el link.
	 */
	public static function ajax_crear_pago() {
		check_ajax_referer( 'if_public', 'nonce' );
		if ( ! is_user_logged_in() || ! current_user_can( 'if_manage_own_team' ) ) {
			wp_send_json_error( array( 'msg' => __( 'No autorizado.', 'inscripciones-futbol' ) ) );
		}
		$equipo_id = intval( $_POST['equipo_id'] );
		$equipo    = get_post( $equipo_id );
		if ( ! $equipo || $equipo->post_author !== get_current_user_id() ) {
			wp_send_json_error( array( 'msg' => __( 'Equipo no válido.', 'inscripciones-futbol' ) ) );
		}
		$url = self::crear_preferencia( $equipo_id, $equipo->post_title . ' - Inscripción' );
		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'msg' => $url->get_error_message() ) );
		}
		wp_send_json_success( array( 'url' => $url ) );
	}
}