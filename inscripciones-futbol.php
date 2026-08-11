<?php
/**
 * Plugin Name: Inscripciones Fútbol
 * Description: Sistema de inscripción de equipos y jugadores para club o campeonato de fútbol. Lógica desacoplada de WordPress.
 * Version:     2.0.0
 * Author:      Inscripciones Fútbol
 * License:     GPL-2.0-or-later
 * Text Domain: inscripciones-futbol
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

define( 'IF_VERSION', '2.0.0' );
define( 'IF_PLUGIN_FILE', __FILE__ );
define( 'IF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once IF_PLUGIN_DIR . 'includes/class-if-app.php';
IF_App::autoload();

require_once IF_PLUGIN_DIR . 'includes/class-if-install.php';
require_once IF_PLUGIN_DIR . 'includes/class-if-shortcodes.php';
require_once IF_PLUGIN_DIR . 'includes/class-if-admin.php';
require_once IF_PLUGIN_DIR . 'includes/class-if-metaboxes.php';
require_once IF_PLUGIN_DIR . 'includes/class-if-export-http.php';
require_once IF_PLUGIN_DIR . 'includes/class-if-assets.php';

IF_Install::hooks();
IF_Shortcodes::hooks();
IF_Admin::hooks();
IF_Metaboxes::hooks();
IF_Export_Http::hooks();
IF_Assets::hooks();

/**
 * Activación del plugin.
 */
function if_activar() {
	IF_Install::rol();
	IF_Install::cpt();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'if_activar' );

/**
 * Desactivación.
 */
function if_desactivar() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'if_desactivar' );