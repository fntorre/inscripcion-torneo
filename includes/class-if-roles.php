<?php
/**
 * Roles y capacidades.
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase IF_Roles
 */
class IF_Roles {

	/**
	 * Inicializa.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registra el rol de delegado.
	 */
	public static function register() {
		if ( ! get_role( 'if_delegado' ) ) {
			add_role(
				'if_delegado',
				__( 'Delegado', 'inscripciones-futbol' ),
				array(
					'read'       => true,
					'upload_files' => true,
					'if_manage_own_team' => true,
				)
			);
		}
	}
}