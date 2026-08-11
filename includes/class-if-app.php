<?php
/**
 * Bootstrap de la aplicación (autoload + contenedor).
 *
 * @package InscripcionesFutbol
 */

use IF\Core\InscripcionService;
use IF\Infra\WpInscripcionStore;

/**
 * Punto de entrada de la aplicación. Separa la lógica (IF\Core)
 * de la infraestructura de WordPress (IF\Infra).
 */
final class IF_App {

	/** @var InscripcionService|null */
	private static $servicio = null;

	/**
	 * Registra el autoloader PSR-4 (IF\ => src/).
	 */
	public static function autoload() {
		spl_autoload_register(
			function ( $clase ) {
				$prefixo = 'IF\\';
				if ( 0 !== strpos( $clase, $prefixo ) ) {
					return;
				}
				$relativo = substr( $clase, strlen( $prefixo ) );
				$archivo  = IF_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relativo ) . '.php';
				if ( is_file( $archivo ) ) {
					require $archivo;
				}
			}
		);
	}

	/**
	 * Servicio de inscripción (única instancia).
	 *
	 * @return InscripcionService
	 */
	public static function servicio() {
		if ( null === self::$servicio ) {
			self::$servicio = new InscripcionService( new WpInscripcionStore() );
		}
		return self::$servicio;
	}

	/**
	 * Almacenamiento de WordPress.
	 *
	 * @return WpInscripcionStore
	 */
	public static function store() {
		return new WpInscripcionStore();
	}
}
