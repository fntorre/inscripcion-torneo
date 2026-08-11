<?php
/**
 * Panel de administración (dashboard).
 *
 * @package InscripcionesFutbol
 */

use IF\Core\Estado;

/**
 * Menús, acciones y vista de administración.
 */
final class IF_Admin {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_if_accion', array( __CLASS__, 'accion' ) );
		add_filter( 'manage_' . IF_Install::CPT_EQUIPO . '_posts_columns', array( __CLASS__, 'columnas' ) );
		add_action( 'manage_' . IF_Install::CPT_EQUIPO . '_posts_custom_column', array( __CLASS__, 'columna' ), 10, 2 );

		// Restringir el acceso de los delegados al panel de WordPress.
		add_filter( 'show_admin_bar', array( __CLASS__, 'restringir_barra_admin' ) );
		add_action( 'admin_init', array( __CLASS__, 'restringir_wp_admin' ) );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'restringir_rest' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'redirigir_login_delegado' ), 10, 3 );
	}

	/**
	 * Al iniciar sesión, envía al delegado a la home y no al dashboard.
	 *
	 * @param string   $redirect_to Destino original.
	 * @param string   $request     Request.
	 * @param WP_User  $user         Usuario.
	 * @return string
	 */
	public static function redirigir_login_delegado( $redirect_to, $request, $user ) {
		if ( $user && in_array( IF_Install::ROL, (array) $user->roles, true ) && ! in_array( 'administrator', (array) $user->roles, true ) ) {
			return home_url( '/' );
		}
		return $redirect_to;
	}

	/**
	 * Oculta la barra de administración para delegados.
	 *
	 * @param bool $mostrar Valor actual.
	 * @return bool
	 */
	public static function restringir_barra_admin( $mostrar ) {
		if ( current_user_can( 'if_manage_own_team' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $mostrar;
	}

	/**
	 * Bloquea el acceso de delegados al wp-admin.
	 */
	public static function restringir_wp_admin() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( current_user_can( 'if_manage_own_team' ) && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}

	/**
	 * Bloquea la API REST para delegados.
	 *
	 * @param WP_Error|true|null $error Resultado actual.
	 * @return mixed
	 */
	public static function restringir_rest( $error ) {
		if ( current_user_can( 'if_manage_own_team' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Acceso no permitido.', 'inscripciones-futbol' ), array( 'status' => 403 ) );
		}
		return $error;
	}

	/**
	 * Menú.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . IF_Install::CPT_EQUIPO,
			__( 'Panel de inscripciones', 'inscripciones-futbol' ),
			__( 'Panel de inscripciones', 'inscripciones-futbol' ),
			'manage_options',
			'if-panel',
			array( __CLASS__, 'render_panel' )
		);
		add_submenu_page(
			'edit.php?post_type=' . IF_Install::CPT_EQUIPO,
			__( 'Ajustes', 'inscripciones-futbol' ),
			__( 'Ajustes', 'inscripciones-futbol' ),
			'manage_options',
			'if-ajustes',
			array( __CLASS__, 'render_ajustes' )
		);
	}

	/**
	 * Acciones sobre el estado de una inscripción.
	 */
	public static function accion() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
		}
		check_admin_referer( 'if_accion' );

		$equipo_id = isset( $_GET['equipo'] ) ? intval( $_GET['equipo'] ) : 0;
		$accion    = isset( $_GET['accion'] ) ? sanitize_key( $_GET['accion'] ) : '';

		$resultado = IF_App::servicio()->cambiarEstado( $equipo_id, $accion );
		if ( ! $resultado->ok ) {
			wp_die( esc_html( $resultado->primerError() ) );
		}

		$destino = wp_get_referer();
		if ( $destino && isset( $_GET['_wp_http_referer'] ) ) {
			$destino = sanitize_url( wp_unslash( $_GET['_wp_http_referer'] ) );
		}
		wp_safe_redirect( $destino ? $destino : admin_url( 'admin.php?page=if-panel' ) );
		exit;
	}

	/**
	 * Columnas del listado de equipos.
	 *
	 * @param array $columnas Columnas.
	 * @return array
	 */
	public static function columnas( $columnas ) {
		$columnas['if_delegado']  = __( 'Delegado', 'inscripciones-futbol' );
		$columnas['if_estado']    = __( 'Estado', 'inscripciones-futbol' );
		$columnas['if_jugadores'] = __( 'Jugadores', 'inscripciones-futbol' );
		return $columnas;
	}

	/**
	 * Contenido de columnas de equipos.
	 *
	 * @param string $columna Columna.
	 * @param int    $post_id ID.
	 */
	public static function columna( $columna, $post_id ) {
		$equipo = IF_App::store()->obtenerEquipo( $post_id );
		if ( ! $equipo ) {
			return;
		}
		switch ( $columna ) {
			case 'if_delegado':
				$d = IF_App::store()->obtenerDelegado( $equipo->delegadoId );
				echo esc_html( $d ? $d->nombreCompleto() : '—' );
				break;
			case 'if_estado':
				echo '<span class="if-badge if-badge-' . esc_attr( $equipo->estado ) . '">' . esc_html( Estado::etiquetas()[ $equipo->estado ] ) . '</span>';
				break;
			case 'if_jugadores':
				echo count( IF_App::servicio()->jugadoresDe( $post_id ) );
				break;
		}
	}

	/**
	 * Panel principal.
	 */
	public static function render_panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$filtro = isset( $_GET['if_estado'] ) ? sanitize_key( $_GET['if_estado'] ) : '';
		$store  = IF_App::store();

		$equipos      = $store->listarEquipos();
		$filtrados    = $filtro ? $store->listarEquipos( $filtro ) : $equipos;

		$por_estado = array();
		foreach ( $equipos as $e ) {
			$por_estado[ $e->estado ] = isset( $por_estado[ $e->estado ] ) ? $por_estado[ $e->estado ] + 1 : 1;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Panel de inscripciones', 'inscripciones-futbol' ); ?></h1>

			<div class="if-cards">
				<?php
				$totales = array(
					'total'      => count( $equipos ),
					'pendiente'  => isset( $por_estado['pendiente'] ) ? $por_estado['pendiente'] : 0,
					'activa'     => isset( $por_estado['activa'] ) ? $por_estado['activa'] : 0,
					'rechazada'  => isset( $por_estado['rechazada'] ) ? $por_estado['rechazada'] : 0,
					'bloqueada'  => isset( $por_estado['bloqueada'] ) ? $por_estado['bloqueada'] : 0,
				);
				foreach ( $totales as $clave => $valor ) {
					$etiqueta = 'total' === $clave ? 'Total' : Estado::etiquetas()[ $clave ];
					echo '<div class="if-card if-card-' . esc_attr( $clave ) . '"><span class="if-card-num">' . esc_html( $valor ) . '</span><span class="if-card-label">' . esc_html( $etiqueta ) . '</span></div>';
				}
				?>
			</div>

			<div class="if-admin-export">
				<?php
				$base = home_url( '/?if_exportar=1&if_formato=' );
				?>
				<a class="button button-primary" href="<?php echo esc_url( $base . 'csv' ); ?>"><?php esc_html_e( 'Exportar todo · CSV', 'inscripciones-futbol' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( $base . 'xls' ); ?>"><?php esc_html_e( 'Exportar todo · Excel', 'inscripciones-futbol' ); ?></a>
			</div>

			<div class="if-filtros">
				<a class="if-filtro <?php echo $filtro ? '' : 'activo'; ?>" href="?page=if-panel"><?php esc_html_e( 'Todas', 'inscripciones-futbol' ); ?></a>
				<?php foreach ( Estado::etiquetas() as $clave => $etiqueta ) : ?>
					<a class="if-filtro <?php echo $filtro === $clave ? 'activo' : ''; ?>" href="?page=if-panel&if_estado=<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $etiqueta ); ?> (<?php echo esc_html( isset( $por_estado[ $clave ] ) ? $por_estado[ $clave ] : 0 ); ?>)</a>
				<?php endforeach; ?>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Equipo', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Delegado', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Jugadores', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'inscripciones-futbol' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $filtrados ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Sin resultados.', 'inscripciones-futbol' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $filtrados as $e ) : ?>
					<?php $d = $store->obtenerDelegado( $e->delegadoId ); ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $e->id ) ); ?>"><?php echo esc_html( $e->nombre ); ?></a>
							<?php if ( $e->linkPago ) { echo '<div class="if-mini">' . esc_html__( 'Link propio', 'inscripciones-futbol' ) . '</div>'; } ?>
						</td>
						<td>
							<?php echo esc_html( $d ? $d->nombreCompleto() : '—' ); ?>
							<div class="if-mini"><?php echo esc_html( $d ? $d->dni : '' ); ?> · <?php echo esc_html( $d ? $d->telefono : '' ); ?> · <?php echo esc_html( $d ? $d->email : '' ); ?></div>
						</td>
						<td><span class="if-badge if-badge-<?php echo esc_attr( $e->estado ); ?>"><?php echo esc_html( Estado::etiquetas()[ $e->estado ] ); ?></span></td>
						<td><?php echo count( IF_App::servicio()->jugadoresDe( $e->id ) ); ?></td>
						<td>
							<?php self::botones( $e ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Botones de acción para un equipo.
	 *
	 * @param IF\Core\Equipo $equipo Equipo.
	 */
	private static function botones( $equipo ) {
		$url = admin_url( 'admin-post.php?action=if_accion&equipo=' . $equipo->id . '&accion=' );

		if ( in_array( $equipo->estado, array( Estado::PENDIENTE, Estado::RECHAZADA ), true ) ) {
			echo '<a class="button button-small button-primary" href="' . esc_url( wp_nonce_url( $url . 'aprobar', 'if_accion' ) ) . '">' . esc_html__( 'Aprobar pago', 'inscripciones-futbol' ) . '</a> ';
		}		if ( Estado::PENDIENTE === $equipo->estado || Estado::ACTIVA === $equipo->estado ) {
			echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( $url . 'rechazar', 'if_accion' ) ) . '">' . esc_html__( 'Rechazar pago', 'inscripciones-futbol' ) . '</a> ';
		}
		if ( Estado::RECHAZADA === $equipo->estado || Estado::ACTIVA === $equipo->estado ) {
			echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( $url . 'marcar_pendiente', 'if_accion' ) ) . '">' . esc_html__( 'Marcar pendiente', 'inscripciones-futbol' ) . '</a> ';
		}
		if ( Estado::BLOQUEADA === $equipo->estado ) {
			echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( $url . 'desbloquear', 'if_accion' ) ) . '">' . esc_html__( 'Desbloquear', 'inscripciones-futbol' ) . '</a> ';
		} else {
			echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( $url . 'bloquear', 'if_accion' ) ) . '">' . esc_html__( 'Bloquear', 'inscripciones-futbol' ) . '</a> ';
		}
		if ( $equipo->comprobante ) {
			echo '<a class="button button-small" href="' . esc_url( $equipo->comprobante ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver comprobante', 'inscripciones-futbol' ) . '</a> ';
		}
		foreach ( array( 'csv', 'xls' ) as $f ) {
			echo '<a class="button button-small" href="' . esc_url( home_url( '/?if_exportar=1&if_formato=' . $f . '&if_equipo=' . $equipo->id ) ) . '">' . esc_html( strtoupper( $f ) ) . '</a> ';
		}
		echo '<a class="button button-small" href="' . esc_url( get_edit_post_link( $equipo->id ) ) . '">' . esc_html__( 'Editar', 'inscripciones-futbol' ) . '</a>';
	}

	/**
	 * Ajustes.
	 */
	public static function render_ajustes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['if_guardar_ajustes'] ) ) {
			check_admin_referer( 'if_ajustes', 'if_ajustes_nonce' );
			update_option( 'if_mp_link', esc_url_raw( wp_unslash( $_POST['if_mp_link'] ) ) );
			update_option( 'if_monto_inscripcion', floatval( $_POST['if_monto_inscripcion'] ) );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Ajustes guardados.', 'inscripciones-futbol' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ajustes de inscripciones', 'inscripciones-futbol' ); ?></h1>
			<form method="post">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="if_mp_link"><?php esc_html_e( 'Link de pago de Mercado Pago', 'inscripciones-futbol' ); ?></label></th>
						<td>
							<input type="url" id="if_mp_link" name="if_mp_link" class="regular-text" value="<?php echo esc_attr( IF_App::store()->linkPagoGeneral() ); ?>" />
							<p class="description"><?php esc_html_e( 'Link generado en Mercado Pago que usarán todos los delegados. Podés configurar un link propio por equipo desde la edición del equipo.', 'inscripciones-futbol' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="if_monto_inscripcion"><?php esc_html_e( 'Monto de inscripción (ARS)', 'inscripciones-futbol' ); ?></label></th>
						<td><input type="number" step="0.01" id="if_monto_inscripcion" name="if_monto_inscripcion" value="<?php echo esc_attr( IF_App::store()->montoInscripcion() ); ?>" /></td>
					</tr>
				</table>
				<?php wp_nonce_field( 'if_ajustes', 'if_ajustes_nonce' ); ?>
				<p><button type="submit" name="if_guardar_ajustes" class="button button-primary"><?php esc_html_e( 'Guardar', 'inscripciones-futbol' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
