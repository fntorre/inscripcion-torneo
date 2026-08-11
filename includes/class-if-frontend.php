<?php
/**
 * Frontend: registro, panel del delegado, gestión de jugadores.
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase IF_Frontend
 */
class IF_Frontend {

	/**
	 * Inicializa.
	 */
	public static function init() {
		add_shortcode( 'if_registro_delegado', array( __CLASS__, 'shortcode_registro' ) );
		add_shortcode( 'if_panel_delegado', array( __CLASS__, 'shortcode_panel' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_forms' ) );
	}

	/**
	 * Formulario de registro del delegado.
	 */
	public static function shortcode_registro() {
		ob_start();
		if ( is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Ya tienes una sesión iniciada.', 'inscripciones-futbol' ) . ' <a href="' . esc_url( if_login_url() ) . '">' . esc_html__( 'Ver panel', 'inscripciones-futbol' ) . '</a></p>';
			return ob_get_clean();
		}
		self::print_mensaje();
		?>
		<form class="if-form" method="post" action="">
			<h3><?php esc_html_e( 'Registro de delegado', 'inscripciones-futbol' ); ?></h3>
			<p>
				<label for="if_nombre"><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" id="if_nombre" name="if_nombre" required />
			</p>
			<p>
				<label for="if_apellido"><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" id="if_apellido" name="if_apellido" required />
			</p>
			<p>
				<label for="if_dni"><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" id="if_dni" name="if_dni" required />
			</p>
			<p>
				<label for="if_telefono"><?php esc_html_e( 'Teléfono', 'inscripciones-futbol' ); ?> *</label>
				<input type="tel" id="if_telefono" name="if_telefono" required />
			</p>
			<p>
				<label for="if_email"><?php esc_html_e( 'Correo electrónico', 'inscripciones-futbol' ); ?> *</label>
				<input type="email" id="if_email" name="if_email" required />
			</p>
			<p>
				<label for="if_pass"><?php esc_html_e( 'Contraseña', 'inscripciones-futbol' ); ?> *</label>
				<input type="password" id="if_pass" name="if_pass" required />
			</p>
			<p>
				<label for="if_equipo_nombre"><?php esc_html_e( 'Nombre del equipo', 'inscripciones-futbol' ); ?> *</label>
				<input type="text" id="if_equipo_nombre" name="if_equipo_nombre" required />
			</p>
			<?php wp_nonce_field( 'if_registro', 'if_registro_nonce' ); ?>
			<p>
				<button type="submit" name="if_registro_submit" class="if-btn"><?php esc_html_e( 'Registrarme', 'inscripciones-futbol' ); ?></button>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Panel del delegado.
	 */
	public static function shortcode_panel() {
		ob_start();
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Debes iniciar sesión para acceder al panel.', 'inscripciones-futbol' ) . '</p>';
			echo '<p><a class="if-btn" href="' . esc_url( if_login_url() ) . '">' . esc_html__( 'Iniciar sesión', 'inscripciones-futbol' ) . '</a></p>';
			return ob_get_clean();
		}
		if ( ! current_user_can( 'if_manage_own_team' ) ) {
			echo '<p>' . esc_html__( 'Tu cuenta no es de delegado.', 'inscripciones-futbol' ) . '</p>';
			return ob_get_clean();
		}

		self::print_mensaje();

		$user_id = get_current_user_id();
		$equipo  = if_get_equipo_delegado( $user_id );

		if ( ! $equipo ) {
			// Formulario para crear el equipo.
			?>
			<form class="if-form" method="post" action="">
				<h3><?php esc_html_e( 'Crear mi equipo', 'inscripciones-futbol' ); ?></h3>
				<p>
					<label for="if_equipo_nombre"><?php esc_html_e( 'Nombre del equipo', 'inscripciones-futbol' ); ?> *</label>
					<input type="text" id="if_equipo_nombre" name="if_equipo_nombre" required />
				</p>
				<?php wp_nonce_field( 'if_crear_equipo', 'if_crear_equipo_nonce' ); ?>
				<p><button type="submit" name="if_crear_equipo_submit" class="if-btn"><?php esc_html_e( 'Crear equipo', 'inscripciones-futbol' ); ?></button></p>
			</form>
			<?php
			return ob_get_clean();
		}

		self::render_panel( $equipo, $user_id );
		return ob_get_clean();
	}

	/**
	 * Renderiza el panel completo.
	 *
	 * @param int $equipo_id ID del equipo.
	 * @param int $user_id   ID del delegado.
	 */
	private static function render_panel( $equipo_id, $user_id ) {
		$equipo    = get_post( $equipo_id );
		$estado    = IF_Post_Types::get_equipo_pago_estado( $equipo_id );
		$habilitada = IF_Post_Types::equipo_carga_habilitada( $equipo_id );
		$delegado  = if_get_delegado_meta( $user_id );
		$jugadores = IF_Post_Types::get_jugadores( $equipo_id );
		$monto     = if_get_monto_inscripcion();

		echo '<div class="if-panel">';

		// Datos personales.
		echo '<section class="if-card">';
		echo '<h3>' . esc_html__( 'Mis datos', 'inscripciones-futbol' ) . '</h3>';
		echo '<p>' . esc_html( $delegado['nombre'] . ' ' . $delegado['apellido'] ) . ' &mdash; DNI: ' . esc_html( $delegado['dni'] ) . ' &mdash; ' . esc_html( $delegado['telefono'] ) . ' &mdash; ' . esc_html( $delegado['email'] ) . '</p>';
		echo '</section>';

		// Equipo.
		echo '<section class="if-card">';
		echo '<h3>' . esc_html__( 'Mi equipo', 'inscripciones-futbol' ) . '</h3>';
		?>
		<form class="if-form if-inline" method="post" action="">
			<input type="text" name="if_equipo_nombre" value="<?php echo esc_attr( $equipo->post_title ); ?>" required />
			<?php wp_nonce_field( 'if_editar_equipo', 'if_editar_equipo_nonce' ); ?>
			<button type="submit" name="if_editar_equipo_submit" class="if-btn"><?php esc_html_e( 'Renombrar', 'inscripciones-futbol' ); ?></button>
		</form>
		</section>

		<?php
		// Pago.
		echo '<section class="if-card">';
		echo '<h3>' . esc_html__( 'Pago de inscripción', 'inscripciones-futbol' ) . '</h3>';
		$class = 'if-estado-' . esc_attr( $estado );
		echo '<p class="' . $class . '">' . esc_html__( 'Estado: ', 'inscripciones-futbol' ) . esc_html( if_pago_estado_label( $estado ) ) . '</p>';
		if ( 'aprobado' === $estado ) {
			echo '<p class="if-ok">' . esc_html__( 'Tu inscripción está pagada. Ya puedes cargar jugadores.', 'inscripciones-futbol' ) . '</p>';
		} else {
			$link = IF_Pago::get_link_equipo( $equipo_id );
			echo '<p>' . sprintf( esc_html__( 'Monto: %s', 'inscripciones-futbol' ), '<strong>$' . number_format( $monto, 2, ',', '.' ) . '</strong>' ) . '</p>';
			if ( $link ) {
				echo '<a class="if-btn if-btn-mp" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'Pagar con Mercado Pago', 'inscripciones-futbol' ) . '</a>';
				echo '<p class="if-note">' . esc_html__( 'Después de pagar, el administrador confirmará tu inscripción y se habilitará la carga de jugadores.', 'inscripciones-futbol' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'El link de pago aún no está disponible. Contactate con la organización.', 'inscripciones-futbol' ) . '</p>';
			}
		}
		echo '</section>';

		// Jugadores.
		echo '<section class="if-card">';
		echo '<h3>' . esc_html__( 'Jugadores', 'inscripciones-futbol' ) . ' (' . count( $jugadores ) . ')</h3>';

		if ( ! $habilitada ) {
			$motivo = get_post_meta( $equipo_id, '_if_bloqueado', true ) ? __( 'La inscripción está bloqueada por el administrador.', 'inscripciones-futbol' ) : __( 'La carga de jugadores se habilita una vez confirmado el pago.', 'inscripciones-futbol' );
			echo '<div class="if-notice">' . esc_html( $motivo ) . '</div>';
		} else {
			// Formulario de alta.
			?>
			<form class="if-form" method="post" action="" enctype="multipart/form-data">
				<input type="hidden" name="if_equipo_id" value="<?php echo esc_attr( $equipo_id ); ?>" />
				<h4><?php esc_html_e( 'Agregar jugador', 'inscripciones-futbol' ); ?></h4>
				<p>
					<label><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?> *</label>
					<input type="text" name="if_jugador_nombre" required />
				</p>
				<p>
					<label><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?> *</label>
					<input type="text" name="if_jugador_apellido" required />
				</p>
				<p>
					<label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?> *</label>
					<input type="text" name="if_jugador_dni" required />
				</p>
				<p>
					<label><?php esc_html_e( 'Fotografía / archivo del DNI', 'inscripciones-futbol' ); ?></label>
					<input type="file" name="if_dni_archivo" accept="image/*,.pdf" />
				</p>
				<?php wp_nonce_field( 'if_jugador_nuevo', 'if_jugador_nuevo_nonce' ); ?>
				<p><button type="submit" name="if_jugador_nuevo_submit" class="if-btn"><?php esc_html_e( 'Agregar jugador', 'inscripciones-futbol' ); ?></button></p>
			</form>
			<?php
		}

		if ( $jugadores ) {
			echo '<div class="if-lista">';
			foreach ( $jugadores as $j ) {
				$foto = get_post_meta( $j->ID, '_if_dni_archivo', true );
				echo '<div class="if-jugador">';
				echo '<div class="if-jugador-info">';
				echo '<strong>' . esc_html( $j->post_title ) . '</strong> &mdash; DNI: ' . esc_html( get_post_meta( $j->ID, '_if_dni', true ) );
				if ( $foto ) {
					echo ' &mdash; <a href="' . esc_url( $foto ) . '" target="_blank">' . esc_html__( 'Ver archivo', 'inscripciones-futbol' ) . '</a>';
				}
				echo '</div>';
				echo '<div class="if-jugador-acciones">';
				echo '<details class="if-editar">';
				echo '<summary>' . esc_html__( 'Editar / reemplazar foto', 'inscripciones-futbol' ) . '</summary>';
				?>
				<form class="if-form" method="post" action="" enctype="multipart/form-data">
					<input type="hidden" name="if_jugador_id" value="<?php echo esc_attr( $j->ID ); ?>" />
					<input type="hidden" name="if_equipo_id" value="<?php echo esc_attr( $equipo_id ); ?>" />
					<p><label><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?></label><input type="text" name="if_jugador_nombre" value="<?php echo esc_attr( $j->post_title ); ?>" /></p>
					<p><label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?></label><input type="text" name="if_jugador_dni" value="<?php echo esc_attr( get_post_meta( $j->ID, '_if_dni', true ) ); ?>" /></p>
					<p><label><?php esc_html_e( 'Reemplazar archivo DNI', 'inscripciones-futbol' ); ?></label><input type="file" name="if_dni_archivo" accept="image/*,.pdf" /></p>
					<?php wp_nonce_field( 'if_jugador_editar', 'if_jugador_editar_nonce' ); ?>
					<p><button type="submit" name="if_jugador_editar_submit" class="if-btn"><?php esc_html_e( 'Guardar cambios', 'inscripciones-futbol' ); ?></button></p>
				</form>
				</details>
				<form class="if-form" method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar este jugador?', 'inscripciones-futbol' ) ); ?>');">
					<input type="hidden" name="if_jugador_id" value="<?php echo esc_attr( $j->ID ); ?>" />
					<?php wp_nonce_field( 'if_jugador_eliminar', 'if_jugador_eliminar_nonce' ); ?>
					<button type="submit" name="if_jugador_eliminar_submit" class="if-btn if-btn-danger"><?php esc_html_e( 'Eliminar', 'inscripciones-futbol' ); ?></button>
				</form>
				<?php
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo '<p>' . esc_html__( 'Aún no hay jugadores cargados.', 'inscripciones-futbol' ) . '</p>';
		}
		echo '</section>';

		// Descargas.
		echo '<section class="if-card">';
		echo '<h3>' . esc_html__( 'Descargar listado', 'inscripciones-futbol' ) . '</h3>';
		$base = home_url( '/?if_action=exportar&if_equipo=' . $equipo_id . '&if_formato=' );
		echo '<a class="if-btn" href="' . esc_url( $base . 'csv' ) . '">CSV</a> ';
		echo '<a class="if-btn" href="' . esc_url( $base . 'xls' ) . '">Excel</a> ';
		echo '<a class="if-btn" href="' . esc_url( $base . 'pdf' ) . '">PDF</a>';
		echo '</section>';

		echo '</div>';
	}

	/**
	 * Mensaje guardado en sesión.
	 */
	private static function print_mensaje() {
		if ( isset( $_GET['if_mensaje'] ) ) {
			$msg = sanitize_text_field( wp_unslash( $_GET['if_mensaje'] ) );
			if ( $msg ) {
				echo '<div class="if-notice if-notice-ok">' . esc_html( $msg ) . '</div>';
			}
		}
	}

	/**
	 * Redirección con mensaje.
	 *
	 * @param string $msg Mensaje.
	 */
	private static function redirect_msg( $msg ) {
		wp_safe_redirect( add_query_arg( 'if_mensaje', rawurlencode( $msg ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Procesa los formularios del frontend.
	 */
	public static function handle_forms() {
		if ( isset( $_POST['if_registro_submit'] ) ) {
			self::handle_registro();
		}
		if ( isset( $_POST['if_crear_equipo_submit'] ) ) {
			self::handle_crear_equipo();
		}
		if ( isset( $_POST['if_editar_equipo_submit'] ) ) {
			self::handle_editar_equipo();
		}
		if ( isset( $_POST['if_jugador_nuevo_submit'] ) ) {
			self::handle_jugador_nuevo();
		}
		if ( isset( $_POST['if_jugador_editar_submit'] ) ) {
			self::handle_jugador_editar();
		}
		if ( isset( $_POST['if_jugador_eliminar_submit'] ) ) {
			self::handle_jugador_eliminar();
		}
	}

	/**
	 * Registro de delegado + creación de equipo + login.
	 */
	private static function handle_registro() {
		if ( ! isset( $_POST['if_registro_nonce'] ) || ! wp_verify_nonce( $_POST['if_registro_nonce'], 'if_registro' ) ) {
			wp_die( esc_html__( 'Token inválido.', 'inscripciones-futbol' ) );
		}
		$nombre   = sanitize_text_field( $_POST['if_nombre'] );
		$apellido = sanitize_text_field( $_POST['if_apellido'] );
		$dni      = sanitize_text_field( $_POST['if_dni'] );
		$telefono = sanitize_text_field( $_POST['if_telefono'] );
		$email    = sanitize_email( $_POST['if_email'] );
		$pass     = wp_unslash( $_POST['if_pass'] );
		$equipo_n = sanitize_text_field( $_POST['if_equipo_nombre'] );

		if ( empty( $nombre ) || empty( $apellido ) || empty( $dni ) || empty( $telefono ) || empty( $email ) || empty( $pass ) || empty( $equipo_n ) ) {
			self::redirect_msg( __( 'Completá todos los campos obligatorios.', 'inscripciones-futbol' ) );
		}
		if ( ! is_email( $email ) ) {
			self::redirect_msg( __( 'Correo inválido.', 'inscripciones-futbol' ) );
		}
		if ( email_exists( $email ) ) {
			self::redirect_msg( __( 'Ya existe una cuenta con ese correo.', 'inscripciones-futbol' ) );
		}
		if ( username_exists( $email ) ) {
			self::redirect_msg( __( 'Ya existe una cuenta con ese correo.', 'inscripciones-futbol' ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $email,
				'user_pass'  => $pass,
				'user_email' => $email,
				'display_name' => $nombre . ' ' . $apellido,
				'role'       => 'if_delegado',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::redirect_msg( $user_id->get_error_message() );
		}

		update_user_meta( $user_id, '_if_nombre', $nombre );
		update_user_meta( $user_id, '_if_apellido', $apellido );
		update_user_meta( $user_id, '_if_dni', $dni );
		update_user_meta( $user_id, '_if_telefono', $telefono );

		$equipo_id = if_crear_equipo( $user_id, $equipo_n );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( add_query_arg( 'if_mensaje', rawurlencode( __( '¡Bienvenido! Ahora podés abonar la inscripción.', 'inscripciones-futbol' ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Crear equipo.
	 */
	private static function handle_crear_equipo() {
		if ( ! is_user_logged_in() || ! current_user_can( 'if_manage_own_team' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
		if ( ! isset( $_POST['if_crear_equipo_nonce'] ) || ! wp_verify_nonce( $_POST['if_crear_equipo_nonce'], 'if_crear_equipo' ) ) {
			wp_die( esc_html__( 'Token inválido.', 'inscripciones-futbol' ) );
		}
		if ( if_get_equipo_delegado( get_current_user_id() ) ) {
			self::redirect_msg( __( 'Ya tenés un equipo.', 'inscripciones-futbol' ) );
		}
		$nombre = sanitize_text_field( $_POST['if_equipo_nombre'] );
		if ( ! $nombre ) {
			self::redirect_msg( __( 'Ingresá el nombre del equipo.', 'inscripciones-futbol' ) );
		}
		if_crear_equipo( get_current_user_id(), $nombre );
		self::redirect_msg( __( 'Equipo creado.', 'inscripciones-futbol' ) );
	}

	/**
	 * Renombrar equipo.
	 */
	private static function handle_editar_equipo() {
		if ( ! is_user_logged_in() || ! current_user_can( 'if_manage_own_team' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
		if ( ! isset( $_POST['if_editar_equipo_nonce'] ) || ! wp_verify_nonce( $_POST['if_editar_equipo_nonce'], 'if_editar_equipo' ) ) {
			wp_die( esc_html__( 'Token inválido.', 'inscripciones-futbol' ) );
		}
		$equipo = if_get_equipo_delegado( get_current_user_id() );
		$nombre = sanitize_text_field( $_POST['if_equipo_nombre'] );
		if ( $equipo && $nombre ) {
			wp_update_post(
				array(
					'ID'         => $equipo,
					'post_title' => $nombre,
				)
			);
		}
		self::redirect_msg( __( 'Equipo actualizado.', 'inscripciones-futbol' ) );
	}

	/**
	 * Alta de jugador.
	 */
	private static function handle_jugador_nuevo() {
		self::require_delegado();
		if ( ! isset( $_POST['if_jugador_nuevo_nonce'] ) || ! wp_verify_nonce( $_POST['if_jugador_nuevo_nonce'], 'if_jugador_nuevo' ) ) {
			wp_die( esc_html__( 'Token inválido.', 'inscripciones-futbol' ) );
		}
		$equipo_id = self::validar_equipo( $_POST );
		if ( ! IF_Post_Types::equipo_carga_habilitada( $equipo_id ) ) {
			self::redirect_msg( __( 'La carga está deshabilitada.', 'inscripciones-futbol' ) );
		}
		$nombre   = sanitize_text_field( $_POST['if_jugador_nombre'] );
		$apellido = sanitize_text_field( $_POST['if_jugador_apellido'] );
		$dni      = sanitize_text_field( $_POST['if_jugador_dni'] );
		if ( ! $nombre || ! $apellido || ! $dni ) {
			self::redirect_msg( __( 'Completá nombre, apellido y DNI.', 'inscripciones-futbol' ) );
		}
		$foto = self::procesar_archivo();
		$pid = wp_insert_post(
			array(
				'post_type'   => IF_Post_Types::JUGADOR,
				'post_status' => 'publish',
				'post_title'  => $nombre . ' ' . $apellido,
				'post_author' => get_current_user_id(),
			)
		);
		update_post_meta( $pid, '_if_dni', $dni );
		update_post_meta( $pid, '_if_equipo_id', $equipo_id );
		if ( $foto ) {
			update_post_meta( $pid, '_if_dni_archivo', $foto );
		}
		self::redirect_msg( __( 'Jugador agregado.', 'inscripciones-futbol' ) );
	}

	/**
	 * Edición de jugador.
	 */
	private static function handle_jugador_editar() {
		self::require_delegado();
		if ( ! isset( $_POST['if_jugador_editar_nonce'] ) || ! wp_verify_nonce( $_POST['if_jugador_editar_nonce'], 'if_jugador_editar' ) ) {
			wp_die( esc_html__( 'Token inválido.', 'inscripciones-futbol' ) );
		}
		$equipo_id = self::validar_equipo( $_POST );
		$jugador   = self::validar_jugador( $_POST, $equipo_id );

		$nombre = sanitize_text_field( $_POST['if_jugador_nombre'] );
		$dni    = sanitize_text_field( $_POST['if_jugador_dni'] );
		if ( $nombre ) {
			wp_update_post(
				array(
					'ID'         => $jugador,
					'post_title' => $nombre,
				)
			);
		}
		if ( $dni ) {
			update_post_meta( $jugador, '_if_dni', $dni );
		}
		$foto = self::procesar_archivo();
		if ( $foto ) {
			update_post_meta( $jugador, '_if_dni_archivo', $foto );
		}
		self::redirect_msg( __( 'Jugador actualizado.', 'inscripciones-futbol' ) );
	}

	/**
	 * Eliminación de jugador.
	 */
	private static function handle_jugador_eliminar() {
		self::require_delegado();
		if ( ! isset( $_POST['if_jugador_eliminar_nonce'] ) || ! wp_verify_nonce( $_POST['if_jugador_eliminar_nonce'], 'if_jugador_eliminar' ) ) {
			wp_die( esc_html__( 'Token inválido.', 'inscripciones-futbol' ) );
		}
		$equipo_id = self::validar_equipo( $_POST );
		$jugador   = self::validar_jugador( $_POST, $equipo_id );
		wp_delete_post( $jugador, true );
		self::redirect_msg( __( 'Jugador eliminado.', 'inscripciones-futbol' ) );
	}

	/**
	 * Verifica sesión de delegado.
	 */
	private static function require_delegado() {
		if ( ! is_user_logged_in() || ! current_user_can( 'if_manage_own_team' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
	}

	/**
	 * Valida que el equipo pertenezca al usuario.
	 *
	 * @param array $post POST.
	 * @return int
	 */
	private static function validar_equipo( $post ) {
		$equipo_id = isset( $post['if_equipo_id'] ) ? intval( $post['if_equipo_id'] ) : 0;
		$equipo    = get_post( $equipo_id );
		if ( ! $equipo || $equipo->post_author !== get_current_user_id() ) {
			self::redirect_msg( __( 'Equipo no válido.', 'inscripciones-futbol' ) );
		}
		return $equipo_id;
	}

	/**
	 * Valida que el jugador pertenezca al equipo.
	 *
	 * @param array $post      POST.
	 * @param int   $equipo_id ID.
	 * @return int
	 */
	private static function validar_jugador( $post, $equipo_id ) {
		$jugador_id = isset( $post['if_jugador_id'] ) ? intval( $post['if_jugador_id'] ) : 0;
		$jugador    = get_post( $jugador_id );
		if ( ! $jugador || IF_Post_Types::JUGADOR !== $jugador->post_type ) {
			self::redirect_msg( __( 'Jugador no válido.', 'inscripciones-futbol' ) );
		}
		if ( intval( get_post_meta( $jugador_id, '_if_equipo_id', true ) ) !== $equipo_id ) {
			self::redirect_msg( __( 'Jugador no válido.', 'inscripciones-futbol' ) );
		}
		return $jugador_id;
	}

	/**
	 * Procesa la carga del archivo del DNI.
	 *
	 * @return string|false
	 */
	private static function procesar_archivo() {
		if ( empty( $_FILES['if_dni_archivo'] ) || empty( $_FILES['if_dni_archivo']['name'] ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

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

		$file = wp_handle_upload( $_FILES['if_dni_archivo'], $overrides );
		if ( isset( $file['error'] ) ) {
			self::redirect_msg( __( 'Error al subir el archivo: ', 'inscripciones-futbol' ) . $file['error'] );
		}
		return $file['url'];
	}
}