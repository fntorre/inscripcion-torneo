<?php
/**
 * Instalación: rol de delegado y tipos de contenido.
 *
 * @package InscripcionesFutbol
 */

/**
 * Registra el rol y los CPT.
 */
final class IF_Install {

	const ROL         = 'if_delegado';
	const CPT_EQUIPO  = 'if_equipo';
	const CPT_JUGADOR = 'if_jugador';

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'registrar' ) );
	}

	/**
	 * Registra rol + CPT.
	 */
	public static function registrar() {
		self::rol();
		self::cpt();
	}

	/**
	 * Rol de delegado.
	 */
	public static function rol() {
		if ( ! get_role( self::ROL ) ) {
			add_role(
				self::ROL,
				__( 'Delegado', 'inscripciones-futbol' ),
				array(
					'read'                 => true,
					'upload_files'         => true,
					'if_manage_own_team'   => true,
				)
			);
		}
	}

	/**
	 * Tipos de contenido.
	 */
	public static function cpt() {
		register_post_type(
			self::CPT_EQUIPO,
			array(
				'labels'          => array(
					'name'          => __( 'Inscripciones', 'inscripciones-futbol' ),
					'singular_name' => __( 'Inscripción', 'inscripciones-futbol' ),
					'menu_name'     => __( 'Inscripciones', 'inscripciones-futbol' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
			)
		);
		register_post_type(
			self::CPT_JUGADOR,
			array(
				'labels'          => array(
					'name'          => __( 'Jugadores', 'inscripciones-futbol' ),
					'singular_name' => __( 'Jugador', 'inscripciones-futbol' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::CPT_EQUIPO,
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
			)
		);
		register_post_type(
			'if_pago',
			array(
				'labels'          => array(
					'name'          => __( 'Pagos', 'inscripciones-futbol' ),
					'singular_name' => __( 'Pago', 'inscripciones-futbol' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}
}
