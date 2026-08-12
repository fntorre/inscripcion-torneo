<?php
/**
 * Shortcodes del frontend: registro y panel (wizard) del delegado.
 *
 * @package InscripcionesFutbol
 */

use IF\Core\Estado;
use IF\Core\Flujo;
use IF\Core\Jugador;

/**
 * Interfaz pública: [if_registro_delegado] y [if_panel_delegado].
 */
final class IF_Shortcodes {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_shortcode( 'if_registro_delegado', array( __CLASS__, 'registro' ) );
		add_shortcode( 'if_panel_delegado', array( __CLASS__, 'panel' ) );
		add_action( 'template_redirect', array( __CLASS__, 'procesar' ) );
	}

	// ============================ Registro ================================

	/**
	 * Shortcode de registro.
	 */
	public static function registro() {
		ob_start();
		if ( is_user_logged_in() ) {
			self::mensaje( 'Ya tenés una sesión iniciada.', 'info' );
			return ob_get_clean();
		}
		self::mostrar_mensaje();
		?>
		<form class="if-form if-wizard" method="post" action="">
			<h3><?php esc_html_e( 'Registro del delegado', 'inscripciones-futbol' ); ?></h3>
			<p>
				<label><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" name="if_nombre" required />
			</p>
			<p>
				<label><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" name="if_apellido" required />
			</p>
			<p>
				<label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" name="if_dni" required />
			</p>
			<p>
				<label><?php esc_html_e( 'Teléfono', 'inscripciones-futbol' ); ?> *</label>
				<input type="tel" name="if_telefono" required />
			</p>
			<p>
				<label><?php esc_html_e( 'Correo electrónico', 'inscripciones-futbol' ); ?> *</label>
				<input type="email" name="if_email" required />
			</p>
			<p>
				<label><?php esc_html_e( 'Contraseña', 'inscripciones-futbol' ); ?> *</label>
				<input type="password" name="if_password" required minlength="6" />
			</p>
			<p>
				<label><?php esc_html_e( 'Nombre del equipo', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" name="if_equipo_nombre" required />
			</p>
			<?php wp_nonce_field( 'if_registro', 'if_registro_nonce' ); ?>
			<p><button type="submit" name="if_registro_submit" class="if-btn"><?php esc_html_e( 'Registrarme', 'inscripciones-futbol' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	// ============================== Panel ==================================

	/**
	 * Shortcode del panel del delegado (wizard).
	 */
	public static function panel() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			self::mensaje( 'Iniciá sesión para acceder a tu inscripción.', 'info' );
			echo '<p><a class="if-btn" href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Iniciar sesión', 'inscripciones-futbol' ) . '</a></p>';
			return ob_get_clean();
		}
		if ( ! current_user_can( 'if_manage_own_team' ) ) {
			self::mensaje( 'Tu cuenta no es de delegado.', 'error' );
			return ob_get_clean();
		}

		self::mostrar_mensaje();

		$servicio = IF_App::servicio();
		$store    = IF_App::store();
		$delegado = $store->obtenerDelegado( get_current_user_id() );
		$equipo   = $store->obtenerEquipoDelegado( get_current_user_id() );

		if ( ! $delegado || ! $delegado->datosCompletos() ) {
			self::render_paso_datos( $delegado );
			return ob_get_clean();
		}
		if ( ! $equipo ) {
			self::render_paso_equipo();
			return ob_get_clean();
		}

		$paso = Flujo::pasoActual( $delegado, $equipo );
		self::render_barra( $paso );

		switch ( $paso ) {
			case Flujo::PASO_PAGO:
				self::render_paso_pago( $equipo );
				break;
			default:
				self::render_paso_jugadores( $equipo );
		}
		return ob_get_clean();
	}

	/**
	 * Barra de pasos.
	 *
	 * @param string $actual Paso actual.
	 */
	private static function render_barra( $actual ) {
		$completados = Flujo::completados( $actual );
		echo '<ol class="if-steps">';
		foreach ( Flujo::pasos() as $clave => $paso ) {
			$class = in_array( $clave, $completados, true ) ? 'done' : '';
			if ( $clave === $actual ) {
				$class .= ' current';
			}
			echo '<li class="' . esc_attr( trim( $class ) ) . '">';
			echo '<span class="if-step-num">' . esc_html( Flujo::indice( $clave ) ) . '</span>';
			echo '<span class="if-step-label">' . esc_html( $paso['etiqueta'] ) . '</span>';
			echo '</li>';
		}
		echo '</ol>';
		echo '<div class="if-progress"><div class="if-progress-bar" style="width:' . esc_attr( Flujo::progreso( $actual ) ) . '%"></div></div>';
	}

	/**
	 * Paso: datos personales.
	 *
	 * @param IF\Core\Delegado|null $delegado Delegado.
	 */
	private static function render_paso_datos( $delegado ) {
		echo '<div class="if-card"><h3>' . esc_html__( 'Paso 1 · Tus datos', 'inscripciones-futbol' ) . '</h3>';
		?>
		<form class="if-form" method="post" action="">
			<p><label><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_nombre" value="<?php echo esc_attr( $delegado ? $delegado->nombre : '' ); ?>" required /></p>
			<p><label><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_apellido" value="<?php echo esc_attr( $delegado ? $delegado->apellido : '' ); ?>" required /></p>
			<p><label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_dni" value="<?php echo esc_attr( $delegado ? $delegado->dni : '' ); ?>" required /></p>
			<p><label><?php esc_html_e( 'Teléfono', 'inscripciones-futbol' ); ?> *</label><input type="tel" name="if_telefono" value="<?php echo esc_attr( $delegado ? $delegado->telefono : '' ); ?>" required /></p>
			<?php wp_nonce_field( 'if_datos', 'if_datos_nonce' ); ?>
			<p><button type="submit" name="if_datos_submit" class="if-btn"><?php esc_html_e( 'Guardar y continuar', 'inscripciones-futbol' ); ?></button></p>
		</form>
		</div>
		<?php
	}

	/**
	 * Paso: crear equipo.
	 */
	private static function render_paso_equipo() {
		echo '<div class="if-card"><h3>' . esc_html__( 'Paso 2 · Tu equipo', 'inscripciones-futbol' ) . '</h3>';
		?>
		<form class="if-form" method="post" action="">
			<p><label><?php esc_html_e( 'Nombre del equipo', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_equipo_nombre" required /></p>
			<?php wp_nonce_field( 'if_crear_equipo', 'if_crear_equipo_nonce' ); ?>
			<p><button type="submit" name="if_crear_equipo_submit" class="if-btn"><?php esc_html_e( 'Crear equipo', 'inscripciones-futbol' ); ?></button></p>
		</form>
		</div>
		<?php
	}

	/**
	 * Paso: pago.
	 *
	 * @param IF\Core\Equipo $equipo Equipo.
	 */
	private static function render_paso_pago( $equipo ) {
		$monto = IF_App::store()->montoInscripcion();
		$estado = $equipo->estado;
		echo '<div class="if-card"><h3>' . esc_html__( 'Paso 3 · Pago de la inscripción', 'inscripciones-futbol' ) . '</h3>';
		echo '<p class="if-estado">' . esc_html__( 'Estado: ', 'inscripciones-futbol' ) . '<span class="if-badge if-badge-' . esc_attr( $estado ) . '">' . esc_html( Estado::etiquetas()[ $estado ] ) . '</span></p>';

		if ( Estado::RECHAZADA === $estado ) {
			self::mensaje( 'Tu pago fue rechazado. Volvé a intentarlo o contactate con la organización.', 'error' );
		}
		if ( Estado::BLOQUEADA === $estado ) {
			self::mensaje( 'Tu inscripción está bloqueada por la organización.', 'error' );
		}

		echo '<p>' . sprintf( esc_html__( 'Monto de la inscripción: %s', 'inscripciones-futbol' ), '<strong>$' . number_format( $monto, 2, ',', '.' ) . '</strong>' ) . '</p>';

		if ( Estado::BLOQUEADA !== $estado ) {
			echo '<form class="if-form" method="post" action="">';
			echo '<input type="hidden" name="if_equipo_id" value="' . esc_attr( $equipo->id ) . '" />';
			wp_nonce_field( 'if_crear_pago', 'if_crear_pago_nonce' );
			echo '<p><button type="submit" name="if_crear_pago_submit" class="if-btn if-btn-mp">' . esc_html__( 'Pagar con Mercado Pago', 'inscripciones-futbol' ) . '</button></p>';
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Paso: jugadores (vista del equipo).
	 *
	 * @param IF\Core\Equipo $equipo Equipo.
	 */
	private static function render_paso_jugadores( $equipo ) {
		$servicio  = IF_App::servicio();
		$jugadores = $servicio->jugadoresDe( $equipo->id );
		$iniciales = self::iniciales( $equipo->nombre );

		echo '<div class="if-team">';

		// Cabecera del equipo.
		echo '<div class="if-team-header">';
		if ( $equipo->escudo ) {
			echo '<div class="if-team-crest"><img src="' . esc_url( $equipo->escudo ) . '" alt="' . esc_attr( $equipo->nombre ) . '" /></div>';
		} else {
			echo '<div class="if-team-crest">' . esc_html( $iniciales ) . '</div>';
		}
		echo '<div class="if-team-meta">';
		echo '<h3>' . esc_html( $equipo->nombre ) . '</h3>';
		echo '<span class="if-badge if-badge-' . esc_attr( $equipo->estado ) . '">' . esc_html( Estado::etiquetas()[ $equipo->estado ] ) . '</span>';
		echo '</div>';
		echo '<div class="if-team-count"><strong>' . count( $jugadores ) . '</strong> ' . esc_html__( 'jugadores', 'inscripciones-futbol' ) . '</div>';
		echo '</div>'; // cierra if-team-header.

		echo '<div class="if-team-body">';

		// Cambiar escudo (plegable).
		echo '<details class="if-escudo"><summary>' . ( $equipo->escudo ? esc_html__( 'Cambiar escudo', 'inscripciones-futbol' ) : esc_html__( 'Subir escudo', 'inscripciones-futbol' ) ) . '</summary>';
		echo '<form class="if-form" method="post" action="" enctype="multipart/form-data">';
		?><p><label><?php esc_html_e( 'Imagen del escudo (JPG, PNG, WebP)', 'inscripciones-futbol' ); ?></label>
		<input type="file" name="if_escudo" accept="image/*" required /></p>
		<?php wp_nonce_field( 'if_escudo', 'if_escudo_nonce' ); ?>
		<p><button type="submit" name="if_escudo_submit" class="if-btn"><?php esc_html_e( 'Guardar escudo', 'inscripciones-futbol' ); ?></button></p>
		</form></details><?php

		// Formulario de alta (plegable).
		echo '<details class="if-add-player"><summary>' . esc_html__( 'Agregar jugador', 'inscripciones-futbol' ) . '</summary>';
		echo '<form class="if-form" method="post" action="" enctype="multipart/form-data">';
		echo '<input type="hidden" name="if_equipo_id" value="' . esc_attr( $equipo->id ) . '" />';
		?>
		<div class="if-row">
		<p><label><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_jugador_nombre" required /></p>
		<p><label><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_jugador_apellido" required /></p>
		</div>
		<p><label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?> *</label><input type="text" name="if_jugador_dni" required /></p>
		<div class="if-row">
		<p><label><?php esc_html_e( 'Posición', 'inscripciones-futbol' ); ?></label>
		<select name="if_jugador_posicion"><option value=""><?php esc_html_e( '—', 'inscripciones-futbol' ); ?></option><?php foreach ( Jugador::posiciones() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; } ?></select></p>
		<p><label><?php esc_html_e( 'Rol', 'inscripciones-futbol' ); ?></label>
		<select name="if_jugador_rol"><option value=""><?php esc_html_e( '—', 'inscripciones-futbol' ); ?></option><?php foreach ( Jugador::roles() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; } ?></select></p>
		</div>
		<p><label><?php esc_html_e( 'Foto del jugador', 'inscripciones-futbol' ); ?></label><input type="file" name="if_jugador_foto" accept="image/*" /></p>
		<p><label><?php esc_html_e( 'Fotografía / archivo del DNI', 'inscripciones-futbol' ); ?></label><input type="file" name="if_dni_archivo" accept="image/*,.pdf" /></p>
		<?php wp_nonce_field( 'if_jugador_nuevo', 'if_jugador_nuevo_nonce' ); ?>
		<p><button type="submit" name="if_jugador_nuevo_submit" class="if-btn"><?php esc_html_e( 'Agregar jugador', 'inscripciones-futbol' ); ?></button></p>
		<?php
		echo '</form></details>';

		// Grilla de jugadores.
		if ( $jugadores ) {
			echo '<div class="if-team-grid">';
			foreach ( $jugadores as $j ) {
				$ini = self::iniciales( $j->nombreCompleto() );
			echo '<div class="if-player-card">';
			if ( $j->foto ) {
				echo '<div class="if-player-avatar if-player-avatar-img"><img src="' . esc_url( $j->foto ) . '" alt="' . esc_attr( $j->nombreCompleto() ) . '" /></div>';
			} else {
				echo '<div class="if-player-avatar">' . esc_html( $ini ) . '</div>';
			}
			echo '<div class="if-player-data">';
				echo '<strong>' . esc_html( $j->nombreCompleto() ) . '</strong>';
				echo '<span class="if-player-dni">DNI ' . esc_html( $j->dni ) . '</span>';
				if ( $j->posicion && isset( Jugador::posiciones()[ $j->posicion ] ) ) {
					echo '<span class="if-player-pos">' . esc_html( Jugador::posiciones()[ $j->posicion ] ) . '</span>';
				}
				if ( $j->rol && isset( Jugador::roles()[ $j->rol ] ) ) {
					echo '<span class="if-player-rol if-rol-' . esc_attr( $j->rol ) . '">' . esc_html( Jugador::roles()[ $j->rol ] ) . '</span>';
				}
				if ( $j->archivoDni ) {
					echo '<a class="if-player-file" href="' . esc_url( $j->archivoDni ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver DNI', 'inscripciones-futbol' ) . '</a>';
				}
				echo '</div>';
				echo '<div class="if-player-actions">';
				echo '<button type="button" class="if-edit-btn" onclick="document.getElementById(\'if-edit-' . esc_attr( $j->id ) . '\').showModal()">' . esc_html__( 'Editar', 'inscripciones-futbol' ) . '</button>';
				echo '<dialog id="if-edit-' . esc_attr( $j->id ) . '" class="if-edit-dialog">';
				echo '<button type="button" class="if-close-dialog" onclick="this.closest(\'dialog\').close()">&times;</button>';
				echo '<form class="if-form" method="post" action="" enctype="multipart/form-data">';
				echo '<input type="hidden" name="if_jugador_id" value="' . esc_attr( $j->id ) . '" />';
				echo '<input type="hidden" name="if_equipo_id" value="' . esc_attr( $equipo->id ) . '" />';
				?>
				<div class="if-row">
				<p><label><?php esc_html_e( 'Nom.', 'inscripciones-futbol' ); ?></label><input type="text" name="if_jugador_nombre" value="<?php echo esc_attr( $j->nombre ); ?>" /></p>
				<p><label><?php esc_html_e( 'Apell.', 'inscripciones-futbol' ); ?></label><input type="text" name="if_jugador_apellido" value="<?php echo esc_attr( $j->apellido ); ?>" /></p>
				</div>
				<p><label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?></label><input type="text" name="if_jugador_dni" value="<?php echo esc_attr( $j->dni ); ?>" /></p>
				<div class="if-row">
				<p><label><?php esc_html_e( 'Posición', 'inscripciones-futbol' ); ?></label>
				<select name="if_jugador_posicion"><option value=""><?php esc_html_e( '—', 'inscripciones-futbol' ); ?></option><?php foreach ( Jugador::posiciones() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $j->posicion, $k, false ) . '>' . esc_html( $v ) . '</option>'; } ?></select></p>
				<p><label><?php esc_html_e( 'Rol', 'inscripciones-futbol' ); ?></label>
				<select name="if_jugador_rol"><option value=""><?php esc_html_e( '—', 'inscripciones-futbol' ); ?></option><?php foreach ( Jugador::roles() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $j->rol, $k, false ) . '>' . esc_html( $v ) . '</option>'; } ?></select></p>
				</div>
				<p><label><?php esc_html_e( 'Foto del jugador', 'inscripciones-futbol' ); ?></label><input type="file" name="if_jugador_foto" accept="image/*" /></p>
				<p><label><?php esc_html_e( 'Archivo DNI', 'inscripciones-futbol' ); ?></label><input type="file" name="if_dni_archivo" accept="image/*,.pdf" /></p>
				<?php wp_nonce_field( 'if_jugador_editar', 'if_jugador_editar_nonce' ); ?>
				<p><button type="submit" name="if_jugador_editar_submit" class="if-btn"><?php esc_html_e( 'Guardar', 'inscripciones-futbol' ); ?></button></p>
				<?php
				echo '</form>';
				echo '</dialog>';
				echo '<form class="if-delete-form" method="post" action="" onsubmit="return confirm(\'' . esc_js( __( '¿Eliminar este jugador?', 'inscripciones-futbol' ) ) . '\');">';
				echo '<input type="hidden" name="if_jugador_id" value="' . esc_attr( $j->id ) . '" />';
				echo '<input type="hidden" name="if_equipo_id" value="' . esc_attr( $equipo->id ) . '" />';
				wp_nonce_field( 'if_jugador_eliminar', 'if_jugador_eliminar_nonce' );
				echo '<button type="submit" name="if_jugador_eliminar_submit" class="if-btn if-btn-danger">' . esc_html__( 'Eliminar', 'inscripciones-futbol' ) . '</button></form>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo '<p class="if-team-empty">' . esc_html__( 'Todavía no cargaste jugadores. Agregalos con el botón de arriba.', 'inscripciones-futbol' ) . '</p>';
		}

		// Descargas.
		echo '<div class="if-descargas">';
		echo '<span>' . esc_html__( 'Descargar listado:', 'inscripciones-futbol' ) . '</span> ';
		foreach ( array( 'csv', 'xls' ) as $f ) {
			$url = home_url( '/?if_exportar=1&if_formato=' . $f . '&if_equipo=' . $equipo->id );
			echo '<a class="if-btn" href="' . esc_url( $url ) . '">' . esc_html( strtoupper( $f ) ) . '</a> ';
		}
		echo '</div>'; // cierra if-descargas.

		echo '</div>'; // cierra if-team-body.
		echo '</div>'; // cierra if-team.
	}

	/**
	 * Iniciales de un nombre para el avatar.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	private static function iniciales( $texto ) {
		$partes = preg_split( '/\s+/', trim( (string) $texto ) );
		$ini    = '';
		foreach ( array_slice( $partes, 0, 2 ) as $p ) {
			if ( $p !== '' ) {
				$ini .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
			}
		}
		return $ini ? $ini : '?';
	}

	// ========================= Procesamiento ==============================

	/**
	 * Procesa los formularios del frontend.
	 */
	public static function procesar() {
		if ( isset( $_GET['if_pago_resultado'] ) && isset( $_GET['if_equipo'] ) ) {
			self::procesar_return_pago();
		}
		if ( isset( $_POST['if_registro_submit'] ) ) {
			self::procesar_registro();
		}
		if ( isset( $_POST['if_datos_submit'] ) ) {
			self::procesar_datos();
		}
		if ( isset( $_POST['if_crear_equipo_submit'] ) ) {
			self::procesar_crear_equipo();
		}
		if ( isset( $_POST['if_comprobante_submit'] ) ) {
			self::procesar_comprobante();
		}
		if ( isset( $_POST['if_crear_pago_submit'] ) ) {
			self::procesar_pago();
		}
		if ( isset( $_POST['if_escudo_submit'] ) ) {
			self::procesar_escudo();
		}
		if ( isset( $_POST['if_jugador_nuevo_submit'] ) ) {
			self::procesar_jugador_nuevo();
		}
		if ( isset( $_POST['if_jugador_editar_submit'] ) ) {
			self::procesar_jugador_editar();
		}
		if ( isset( $_POST['if_jugador_eliminar_submit'] ) ) {
			self::procesar_jugador_eliminar();
		}
	}

	/**
	 * Registro de delegado.
	 */
	private static function procesar_registro() {
		check_admin_referer( 'if_registro', 'if_registro_nonce' );
		$resultado = IF_App::servicio()->registrarDelegado(
			array(
				'nombre'       => isset( $_POST['if_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['if_nombre'] ) ) : '',
				'apellido'     => isset( $_POST['if_apellido'] ) ? sanitize_text_field( wp_unslash( $_POST['if_apellido'] ) ) : '',
				'dni'          => isset( $_POST['if_dni'] ) ? sanitize_text_field( wp_unslash( $_POST['if_dni'] ) ) : '',
				'telefono'     => isset( $_POST['if_telefono'] ) ? sanitize_text_field( wp_unslash( $_POST['if_telefono'] ) ) : '',
				'email'        => isset( $_POST['if_email'] ) ? sanitize_email( wp_unslash( $_POST['if_email'] ) ) : '',
				'password'     => isset( $_POST['if_password'] ) ? wp_unslash( $_POST['if_password'] ) : '',
				'equipoNombre' => isset( $_POST['if_equipo_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['if_equipo_nombre'] ) ) : '',
			),
			function ( $user_id ) {
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id );
			}
		);
		if ( $resultado->ok ) {
			$panel_url = add_query_arg( 'if_msj', rawurlencode( '¡Registro completo! Completá el pago para cargar jugadores.' ), home_url( '/cargar-equipo/' ) );
			$panel_url = add_query_arg( 'if_tipo', 'ok', $panel_url );
			wp_safe_redirect( $panel_url );
			exit;
		}
		self::redirigir( $resultado->errores );
	}

	/**
	 * Datos personales.
	 */
	private static function procesar_datos() {
		self::exigir_delegado();
		check_admin_referer( 'if_datos', 'if_datos_nonce' );
		$resultado = IF_App::servicio()->actualizarDatosDelegado(
			get_current_user_id(),
			array(
				'nombre'   => isset( $_POST['if_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['if_nombre'] ) ) : '',
				'apellido' => isset( $_POST['if_apellido'] ) ? sanitize_text_field( wp_unslash( $_POST['if_apellido'] ) ) : '',
				'dni'      => isset( $_POST['if_dni'] ) ? sanitize_text_field( wp_unslash( $_POST['if_dni'] ) ) : '',
				'telefono' => isset( $_POST['if_telefono'] ) ? sanitize_text_field( wp_unslash( $_POST['if_telefono'] ) ) : '',
			)
		);
		self::redirigir( $resultado->ok ? 'Datos guardados.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	/**
	 * Crear equipo.
	 */
	private static function procesar_crear_equipo() {
		self::exigir_delegado();
		check_admin_referer( 'if_crear_equipo', 'if_crear_equipo_nonce' );
		$nombre    = isset( $_POST['if_equipo_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['if_equipo_nombre'] ) ) : '';
		$resultado = IF_App::servicio()->crearEquipo( get_current_user_id(), $nombre );
		self::redirigir( $resultado->ok ? 'Equipo creado. Ahora aboná la inscripción.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	/**
	 * Subir/reemplazar escudo del equipo.
	 */
	private static function procesar_escudo() {
		self::exigir_delegado();
		check_admin_referer( 'if_escudo', 'if_escudo_nonce' );
		$archivo   = self::subir_archivo( 'if_escudo' );
		$resultado = IF_App::servicio()->subirEscudo( get_current_user_id(), $archivo ? $archivo : '' );
		self::redirigir( $resultado->ok ? 'Escudo actualizado.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	/**
	 * Adjuntar comprobante de pago.
	 */
	private static function procesar_comprobante() {
		self::exigir_delegado();
		check_admin_referer( 'if_comprobante', 'if_comprobante_nonce' );
		$archivo   = self::subir_archivo( 'if_comprobante' );
		$resultado = IF_App::servicio()->adjuntarComprobante( get_current_user_id(), $archivo ? $archivo : '' );
		self::redirigir( $resultado->ok ? 'Comprobante enviado. Queda en revisión.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	/**
	 * Crear preferencia de pago y redirigir a MercadoPago.
	 */
	private static function procesar_pago() {
		self::exigir_delegado();
		check_admin_referer( 'if_crear_pago', 'if_crear_pago_nonce' );

		$equipo_id = isset( $_POST['if_equipo_id'] ) ? intval( $_POST['if_equipo_id'] ) : 0;
		$equipo    = get_post( $equipo_id );

		if ( ! $equipo_id || ! $equipo || strval( $equipo->post_author ) !== strval( get_current_user_id() ) ) {
			self::redirigir( 'Equipo no válido.', 'error' );
		}

		$url = IF_MercadoPago::crear_preferencia( $equipo_id, $equipo->post_title . ' - Inscripción' );

		if ( is_wp_error( $url ) ) {
			wp_die( 'Error de MercadoPago: ' . esc_html( $url->get_error_message() ) );
		}

		add_filter( 'allowed_redirect_hosts', function( $hosts ) {
			$hosts[] = 'www.mercadopago.com.ar';
			$hosts[] = 'mercadopago.com.ar';
			return $hosts;
		} );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Procesa el return de MercadoPago después del pago.
	 */
	private static function procesar_return_pago() {
		$resultado = sanitize_text_field( wp_unslash( $_GET['if_pago_resultado'] ) );
		$equipo_id = intval( $_GET['if_equipo'] );

		if ( ! $equipo_id || ! is_user_logged_in() ) {
			return;
		}

		$equipo = get_post( $equipo_id );
		if ( ! $equipo || strval( $equipo->post_author ) !== strval( get_current_user_id() ) ) {
			return;
		}

		if ( 'aprobado' === $resultado ) {
			if_set_equipo_pago_estado( $equipo_id, 'aprobado' );
		} elseif ( 'rechazado' === $resultado ) {
			if_set_equipo_pago_estado( $equipo_id, 'rechazado' );
		} else {
			if_set_equipo_pago_estado( $equipo_id, 'pendiente' );
		}

		$panel_url = home_url( '/cargar-equipo/' );
		$msj = 'aprobado' === $resultado
			? '¡Pago aprobado! Ya podés cargar jugadores.'
			: ( 'rechazado' === $resultado ? 'El pago fue rechazado.' : 'El pago está pendiente.' );
		$tipo = 'aprobado' === $resultado ? 'ok' : 'error';

		wp_safe_redirect( add_query_arg( array( 'if_msj' => rawurlencode( $msj ), 'if_tipo' => $tipo ), $panel_url ) );
		exit;
	}

	/**
	 * Nuevo jugador.
	 */
	private static function procesar_jugador_nuevo() {
		self::exigir_delegado();
		check_admin_referer( 'if_jugador_nuevo', 'if_jugador_nuevo_nonce' );
		$equipo_id = self::equipo_del_delegado();
		$archivo   = self::subir_archivo( 'if_dni_archivo' );
		$foto      = self::subir_archivo( 'if_jugador_foto' );
		$resultado = IF_App::servicio()->agregarJugador(
			$equipo_id,
			array(
				'nombre'     => isset( $_POST['if_jugador_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['if_jugador_nombre'] ) ) : '',
				'apellido'   => isset( $_POST['if_jugador_apellido'] ) ? sanitize_text_field( wp_unslash( $_POST['if_jugador_apellido'] ) ) : '',
				'dni'        => isset( $_POST['if_jugador_dni'] ) ? sanitize_text_field( wp_unslash( $_POST['if_jugador_dni'] ) ) : '',
				'archivoDni' => $archivo ? $archivo : '',
				'foto'       => $foto ? $foto : '',
				'posicion'   => isset( $_POST['if_jugador_posicion'] ) ? sanitize_key( wp_unslash( $_POST['if_jugador_posicion'] ) ) : '',
				'rol'        => isset( $_POST['if_jugador_rol'] ) ? sanitize_key( wp_unslash( $_POST['if_jugador_rol'] ) ) : '',
			)
		);
		self::redirigir( $resultado->ok ? 'Jugador agregado.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	/**
	 * Editar jugador.
	 */
	private static function procesar_jugador_editar() {
		self::exigir_delegado();
		check_admin_referer( 'if_jugador_editar', 'if_jugador_editar_nonce' );
		$equipo_id = self::equipo_del_delegado();
		$jugador   = self::jugador_del_equipo( $equipo_id, isset( $_POST['if_jugador_id'] ) ? intval( $_POST['if_jugador_id'] ) : 0 );
		$archivo   = self::subir_archivo( 'if_dni_archivo' );
		$foto      = self::subir_archivo( 'if_jugador_foto' );
		$resultado = IF_App::servicio()->actualizarJugador(
			$jugador,
			array(
				'nombre'     => isset( $_POST['if_jugador_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['if_jugador_nombre'] ) ) : '',
				'apellido'   => isset( $_POST['if_jugador_apellido'] ) ? sanitize_text_field( wp_unslash( $_POST['if_jugador_apellido'] ) ) : '',
				'dni'        => isset( $_POST['if_jugador_dni'] ) ? sanitize_text_field( wp_unslash( $_POST['if_jugador_dni'] ) ) : '',
				'archivoDni' => $archivo ? $archivo : '',
				'foto'       => $foto ? $foto : '',
				'posicion'   => isset( $_POST['if_jugador_posicion'] ) ? sanitize_key( wp_unslash( $_POST['if_jugador_posicion'] ) ) : '',
				'rol'        => isset( $_POST['if_jugador_rol'] ) ? sanitize_key( wp_unslash( $_POST['if_jugador_rol'] ) ) : '',
			)
		);
		self::redirigir( $resultado->ok ? 'Jugador actualizado.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	/**
	 * Eliminar jugador.
	 */
	private static function procesar_jugador_eliminar() {
		self::exigir_delegado();
		check_admin_referer( 'if_jugador_eliminar', 'if_jugador_eliminar_nonce' );
		$equipo_id = self::equipo_del_delegado();
		$jugador   = self::jugador_del_equipo( $equipo_id, isset( $_POST['if_jugador_id'] ) ? intval( $_POST['if_jugador_id'] ) : 0 );
		$resultado = IF_App::servicio()->eliminarJugador( $jugador );
		self::redirigir( $resultado->ok ? 'Jugador eliminado.' : $resultado->errores, $resultado->ok ? 'ok' : 'error' );
	}

	// =========================== Utilidades ================================

	/**
	 * Verifica sesión de delegado.
	 */
	private static function exigir_delegado() {
		if ( ! is_user_logged_in() || ! current_user_can( 'if_manage_own_team' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
	}

	/**
	 * ID del equipo del delegado.
	 *
	 * @return int
	 */
	private static function equipo_del_delegado() {
		$equipo = IF_App::store()->obtenerEquipoDelegado( get_current_user_id() );
		if ( ! $equipo ) {
			self::redirigir( 'No tenés un equipo cargado.', 'error' );
		}
		return $equipo->id;
	}

	/**
	 * Valida que el jugador pertenezca al equipo del delegado.
	 *
	 * @param int $equipo_id ID del equipo.
	 * @param int $jugador_id ID del jugador.
	 * @return int
	 */
	private static function jugador_del_equipo( $equipo_id, $jugador_id ) {
		$jugador = IF_App::store()->obtenerJugador( $jugador_id );
		if ( ! $jugador || $jugador->equipoId !== $equipo_id ) {
			self::redirigir( 'Jugador no válido.', 'error' );
		}
		return $jugador_id;
	}

	/**
	 * Sube un archivo y devuelve su URL.
	 *
	 * @param string $campo Nombre del campo file.
	 * @return string|false URL o false.
	 */
	private static function subir_archivo( $campo ) {
		if ( empty( $_FILES[ $campo ] ) || empty( $_FILES[ $campo ]['name'] ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
				'gif'      => 'image/gif',
				'pdf'      => 'application/pdf',
			),
		);
		$file = wp_handle_upload( $_FILES[ $campo ], $overrides );
		if ( isset( $file['error'] ) ) {
			self::redirigir( 'Error al subir el archivo: ' . $file['error'], 'error' );
		}
		return $file['url'];
	}

	/**
	 * Redirige con mensaje(s) a la misma pantalla.
	 *
	 * @param string|array $msj Mensaje.
	 * @param string       $tipo Tipo (ok|error|info).
	 */
	private static function redirigir( $msj, $tipo = 'error' ) {
		$msj     = is_array( $msj ) ? implode( ' ', $msj ) : $msj;
		$destino = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
		$destino = remove_query_arg( array( 'if_msj', 'if_tipo' ), $destino );
		$destino = add_query_arg(
			array(
				'if_msj'  => rawurlencode( $msj ),
				'if_tipo' => $tipo,
			),
			$destino
		);
		wp_safe_redirect( $destino );
		exit;
	}

	/**
	 * Muestra el mensaje guardado en la URL.
	 */
	private static function mostrar_mensaje() {
		if ( ! empty( $_GET['if_msj'] ) ) {
			$tipo = isset( $_GET['if_tipo'] ) ? sanitize_key( $_GET['if_tipo'] ) : 'info';
			self::mensaje( sanitize_text_field( wp_unslash( $_GET['if_msj'] ) ), $tipo );
		}
	}

	/**
	 * Renderiza un aviso.
	 *
	 * @param string $texto Texto.
	 * @param string $tipo  Tipo.
	 */
	private static function mensaje( $texto, $tipo = 'info' ) {
		echo '<div class="if-notice if-notice-' . esc_attr( $tipo ) . '">' . esc_html( $texto ) . '</div>';
	}
}
