<?php
/**
 * Tipos de contenido personalizados: Equipo y Jugador.
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase IF_Post_Types
 */
class IF_Post_Types {

	const EQUIPO   = 'if_equipo';
	const JUGADOR  = 'if_jugador';
	const PAGO     = 'if_pago';

	/**
	 * Inicializa.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registra los CPT.
	 */
	public static function register() {
		self::register_equipo();
		self::register_jugador();
		self::register_pago();
	}

	/**
	 * CPT Equipo.
	 */
	private static function register_equipo() {
		$labels = array(
			'name'          => __( 'Equipos', 'inscripciones-futbol' ),
			'singular_name' => __( 'Equipo', 'inscripciones-futbol' ),
			'menu_name'     => __( 'Inscripciones', 'inscripciones-futbol' ),
			'add_new'       => __( 'Nuevo equipo', 'inscripciones-futbol' ),
			'edit_item'     => __( 'Editar equipo', 'inscripciones-futbol' ),
		);
		register_post_type(
			self::EQUIPO,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * CPT Jugador.
	 */
	private static function register_jugador() {
		$labels = array(
			'name'          => __( 'Jugadores', 'inscripciones-futbol' ),
			'singular_name' => __( 'Jugador', 'inscripciones-futbol' ),
			'add_new'       => __( 'Nuevo jugador', 'inscripciones-futbol' ),
			'edit_item'     => __( 'Editar jugador', 'inscripciones-futbol' ),
		);
		register_post_type(
			self::JUGADOR,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::EQUIPO,
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * CPT Pago.
	 */
	private static function register_pago() {
		$labels = array(
			'name'          => __( 'Pagos', 'inscripciones-futbol' ),
			'singular_name' => __( 'Pago', 'inscripciones-futbol' ),
		);
		register_post_type(
			self::PAGO,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::EQUIPO,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Estado de pago de un equipo.
	 *
	 * @param int $equipo_id ID del equipo.
	 * @return string
	 */
	public static function get_equipo_pago_estado( $equipo_id ) {
		$estado = get_post_meta( $equipo_id, '_if_pago_estado', true );
		return $estado ? $estado : 'pendiente';
	}

	/**
	 * Estado de habilitación de carga de jugadores.
	 *
	 * @param int $equipo_id ID del equipo.
	 * @return bool
	 */
	public static function equipo_carga_habilitada( $equipo_id ) {
		$bloqueado = get_post_meta( $equipo_id, '_if_bloqueado', true );
		if ( '1' === $bloqueado ) {
			return false;
		}
		return self::get_equipo_pago_estado( $equipo_id ) === 'aprobado';
	}

	/**
	 * Devuelve los jugadores de un equipo.
	 *
	 * @param int $equipo_id ID del equipo.
	 * @return WP_Post[]
	 */
	public static function get_jugadores( $equipo_id ) {
		return get_posts(
			array(
				'post_type'      => self::JUGADOR,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_if_equipo_id',
				'meta_value'     => $equipo_id,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
}