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
	 * Al iniciar sesión, envía al delegado al panel de inscripción (/cargar-equipo/) en lugar del dashboard ni la home.
	 *
	 * @param string   $redirect_to Destino original.
	 * @param string   $request     Request.
	 * @param WP_User  $user         Usuario.
	 * @return string
	 */
	public static function redirigir_login_delegado( $redirect_to, $request, $user ) {
		if ( ! $user || ! in_array( IF_Install::ROL, (array) $user->roles, true ) || in_array( 'administrator', (array) $user->roles, true ) ) {
			return $redirect_to;
		}

		$panel = home_url( '/cargar-equipo/' );

		// Si WordPress ya decidió redirigir al panel, conservamos el destino.
		if ( $redirect_to && 0 === strpos( $redirect_to, $panel ) ) {
			return $redirect_to;
		}

		// Si no hay redirect_to explícito, enviamos al panel del delegado.
		return $panel;
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
			__( 'Detalle del equipo', 'inscripciones-futbol' ),
			__( 'Detalle del equipo', 'inscripciones-futbol' ),
			'manage_options',
			'if-equipo-detalle',
			array( __CLASS__, 'render_detalle_equipo' )
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

		if ( 'eliminar' === $accion ) {
			if ( ! $equipo_id || ! get_post( $equipo_id ) ) {
				wp_die( esc_html__( 'Equipo no encontrado.', 'inscripciones-futbol' ) );
			}
			$jugadores = get_posts( array(
				'post_type'      => IF_Post_Types::JUGADOR,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_if_equipo_id',
				'meta_value'     => $equipo_id,
				'fields'         => 'ids',
			) );
			foreach ( $jugadores as $jid ) {
				wp_delete_post( $jid, true );
			}
			wp_delete_post( $equipo_id, true );

			$destino = admin_url( 'admin.php?page=if-panel' );
			wp_safe_redirect( $destino );
			exit;
		}

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

		if ( isset( $_POST['if_bulk_submit'] ) && isset( $_POST['if_bulk_accion'] ) && 'eliminar' === $_POST['if_bulk_accion'] ) {
			check_admin_referer( 'if_bulk_accion', 'if_bulk_nonce' );
			$seleccionados = isset( $_POST['if_equipos'] ) ? array_map( 'intval', (array) $_POST['if_equipos'] ) : array();
			foreach ( $seleccionados as $eid ) {
				$jugadores = get_posts( array(
					'post_type'      => IF_Post_Types::JUGADOR,
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_key'       => '_if_equipo_id',
					'meta_value'     => $eid,
					'fields'         => 'ids',
				) );
				foreach ( $jugadores as $jid ) {
					wp_delete_post( $jid, true );
				}
				wp_delete_post( $eid, true );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=if-panel&if_eliminados=' . count( $seleccionados ) ) );
			exit;
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

			<form method="post" id="if-bulk-form">
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:30px;"><input type="checkbox" id="if-check-all" /></th>
						<th><?php esc_html_e( 'Equipo', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Delegado', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Jugadores', 'inscripciones-futbol' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'inscripciones-futbol' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $filtrados ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Sin resultados.', 'inscripciones-futbol' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $filtrados as $e ) : ?>
					<?php $d = $store->obtenerDelegado( $e->delegadoId ); ?>
					<tr>
						<td><input type="checkbox" name="if_equipos[]" value="<?php echo esc_attr( $e->id ); ?>" /></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $e->id ) ); ?>"><?php echo esc_html( $e->nombre ); ?></a>
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
			<div style="margin-top:10px;">
				<?php wp_nonce_field( 'if_bulk_accion', 'if_bulk_nonce' ); ?>
				<select name="if_bulk_accion">
					<option value=""><?php esc_html_e( 'Acciones masivas...', 'inscripciones-futbol' ); ?></option>
					<option value="eliminar"><?php esc_html_e( 'Eliminar seleccionados', 'inscripciones-futbol' ); ?></option>
				</select>
				<button type="submit" name="if_bulk_submit" class="button" onclick="return confirm('<?php esc_attr_e( '¿Eliminar los equipos seleccionados y todos sus jugadores?', 'inscripciones-futbol' ); ?>');"><?php esc_html_e( 'Aplicar', 'inscripciones-futbol' ); ?></button>
			</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Vista de detalle de un equipo (accesible desde ?page=if-equipo-detalle&equipo=X).
	 * Si no hay equipo=X, muestra lista de todos los equipos para elegir.
	 */
	public static function render_detalle_equipo() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$equipo_id = isset( $_GET['equipo'] ) ? intval( $_GET['equipo'] ) : 0;
		$store     = IF_App::store();

		// Si NO hay equipo_id, mostrar lista de todos los equipos
		if ( ! $equipo_id ) {
			$filtro     = isset( $_GET['if_estado'] ) ? sanitize_key( $_GET['if_estado'] ) : '';
			$equipos    = $store->listarEquipos();
			$filtrados  = $filtro ? $store->listarEquipos( $filtro ) : $equipos;

			$por_estado = array();
			foreach ( $equipos as $e ) {
				$por_estado[ $e->estado ] = isset( $por_estado[ $e->estado ] ) ? $por_estado[ $e->estado ] + 1 : 1;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Detalle de equipos', 'inscripciones-futbol' ); ?></h1>

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

				<div class="if-filtros" style="margin-bottom:15px;">
					<a class="if-filtro <?php echo $filtro ? '' : 'activo'; ?>" href="?page=if-equipo-detalle"><?php esc_html_e( 'Todas', 'inscripciones-futbol' ); ?></a>
					<?php foreach ( Estado::etiquetas() as $clave => $etiqueta ) : ?>
						<a class="if-filtro <?php echo $filtro === $clave ? 'activo' : ''; ?>" href="?page=if-equipo-detalle&if_estado=<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $etiqueta ); ?> (<?php echo esc_html( isset( $por_estado[ $clave ] ) ? $por_estado[ $clave ] : 0 ); ?>)</a>
					<?php endforeach; ?>
				</div>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Equipo', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Delegado', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Jugadores', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Comprobante', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Acciones', 'inscripciones-futbol' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! $filtrados ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Sin resultados.', 'inscripciones-futbol' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $filtrados as $e ) : ?>
							<?php $d = $store->obtenerDelegado( $e->delegadoId ); ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $e->id ) ); ?>">
										<strong><?php echo esc_html( $e->nombre ); ?></strong>
									</a>
								</td>
								<td>
									<?php echo esc_html( $d ? $d->nombreCompleto() : '—' ); ?>
									<div class="if-mini"><?php echo esc_html( $d ? $d->dni : '' ); ?> · <?php echo esc_html( $d ? $d->email : '' ); ?></div>
								</td>
								<td><span class="if-badge if-badge-<?php echo esc_attr( $e->estado ); ?>"><?php echo esc_html( Estado::etiquetas()[ $e->estado ] ); ?></span></td>
								<td><?php echo count( IF_App::servicio()->jugadoresDe( $e->id ) ); ?></td>
								<td><?php echo $e->comprobante ? '<a href="' . esc_url( $e->comprobante ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver', 'inscripciones-futbol' ) . '</a>' : '—'; ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $e->id ) ); ?>"><?php esc_html_e( 'Ver detalle', 'inscripciones-futbol' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php
			return;
		}

		// Si HAY equipo_id, mostrar detalle (código original)
		if ( ! $equipo_id || ! ( $equipo = get_post( $equipo_id ) ) || $equipo->post_type !== IF_Install::CPT_EQUIPO ) {
			wp_die( esc_html__( 'Equipo no encontrado.', 'inscripciones-futbol' ) );
		}

		$store    = IF_App::store();
		$equipo_o = $store->obtenerEquipo( $equipo_id );
		$delegado = $store->obtenerDelegado( $equipo_o->delegadoId );
		$jugadores = IF_App::servicio()->jugadoresDe( $equipo_id );

		// Edición de equipo, escudo y jugadores desde el admin (POST).
		if ( isset( $_POST['if_equipo_guardar'] ) ) {
			check_admin_referer( 'if_admin_equipo', 'if_admin_equipo_nonce' );
			$datos  = array( 'nombre' => sanitize_text_field( wp_unslash( $_POST['if_equipo_nombre'] ) ) );
			$escudo = self::subir_archivo_admin(
				'if_escudo',
				array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
					'gif'      => 'image/gif',
				)
			);
			$ok = true;
			if ( is_wp_error( $escudo ) ) {
				$msg = $escudo->get_error_message();
				$ok  = false;
			} else {
				if ( $escudo ) {
					$datos['escudo'] = $escudo;
				}
				$r   = IF_App::servicio()->actualizarEquipoDatos( $equipo_id, $datos );
				$ok  = $r->ok;
				$msg = $r->ok ? __( 'Equipo actualizado.', 'inscripciones-futbol' ) : $r->primerError();
			}
			wp_safe_redirect( add_query_arg( array( 'if_msg' => rawurlencode( $msg ), 'if_ok' => $ok ? '1' : '0' ), admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $equipo_id ) ) );
			exit;
		}

		if ( isset( $_POST['if_jugador_nuevo'] ) ) {
			check_admin_referer( 'if_admin_jugador_nuevo', 'if_admin_jugador_nuevo_nonce' );
			$archivo = self::subir_archivo_admin(
				'if_dni_archivo',
				array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
					'gif'      => 'image/gif',
					'pdf'      => 'application/pdf',
				)
			);
			$datos = array(
				'nombre'   => sanitize_text_field( wp_unslash( $_POST['if_jugador_nombre'] ) ),
				'apellido' => sanitize_text_field( wp_unslash( $_POST['if_jugador_apellido'] ) ),
				'dni'      => sanitize_text_field( wp_unslash( $_POST['if_jugador_dni'] ) ),
			);
			$ok = true;
			if ( is_wp_error( $archivo ) ) {
				$msg = $archivo->get_error_message();
				$ok  = false;
			} else {
				if ( $archivo ) {
					$datos['archivoDni'] = $archivo;
				}
				$r   = IF_App::servicio()->agregarJugadorAdmin( $equipo_id, $datos );
				$ok  = $r->ok;
				$msg = $r->ok ? __( 'Jugador agregado.', 'inscripciones-futbol' ) : $r->primerError();
			}
			wp_safe_redirect( add_query_arg( array( 'if_msg' => rawurlencode( $msg ), 'if_ok' => $ok ? '1' : '0' ), admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $equipo_id ) ) );
			exit;
		}

		if ( isset( $_POST['if_jugador_editar'] ) ) {
			check_admin_referer( 'if_admin_jugador_editar', 'if_admin_jugador_editar_nonce' );
			$archivo = self::subir_archivo_admin(
				'if_dni_archivo',
				array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
					'gif'      => 'image/gif',
					'pdf'      => 'application/pdf',
				)
			);
			$datos = array(
				'nombre'   => sanitize_text_field( wp_unslash( $_POST['if_jugador_nombre'] ) ),
				'apellido' => sanitize_text_field( wp_unslash( $_POST['if_jugador_apellido'] ) ),
				'dni'      => sanitize_text_field( wp_unslash( $_POST['if_jugador_dni'] ) ),
			);
			$ok = true;
			if ( is_wp_error( $archivo ) ) {
				$msg = $archivo->get_error_message();
				$ok  = false;
			} else {
				if ( $archivo ) {
					$datos['archivoDni'] = $archivo;
				}
				$r   = IF_App::servicio()->actualizarJugador( intval( $_POST['if_jugador_id'] ), $datos );
				$ok  = $r->ok;
				$msg = $r->ok ? __( 'Jugador actualizado.', 'inscripciones-futbol' ) : $r->primerError();
			}
			wp_safe_redirect( add_query_arg( array( 'if_msg' => rawurlencode( $msg ), 'if_ok' => $ok ? '1' : '0' ), admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $equipo_id ) ) );
			exit;
		}

		if ( isset( $_POST['if_jugador_eliminar'] ) ) {
			check_admin_referer( 'if_admin_jugador_eliminar', 'if_admin_jugador_eliminar_nonce' );
			$r   = IF_App::servicio()->eliminarJugador( intval( $_POST['if_jugador_id'] ) );
			$msg = $r->ok ? __( 'Jugador eliminado.', 'inscripciones-futbol' ) : $r->primerError();
			wp_safe_redirect( add_query_arg( array( 'if_msg' => rawurlencode( $msg ), 'if_ok' => $r->ok ? '1' : '0' ), admin_url( 'admin.php?page=if-equipo-detalle&equipo=' . $equipo_id ) ) );
			exit;
		}

		// Actions
		if ( isset( $_GET['accion'] ) && check_admin_referer( 'if_accion', '_wpnonce', false ) ) {
			$accion = sanitize_key( $_GET['accion'] );
			$resultado = IF_App::servicio()->cambiarEstado( $equipo_id, $accion );
			if ( $resultado->ok ) {
				wp_safe_redirect( remove_query_arg( 'accion' ) );
				exit;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Detalle del equipo', 'inscripciones-futbol' ); ?>: <?php echo esc_html( $equipo_o->nombre ); ?></h1>

			<?php if ( isset( $_GET['if_msg'] ) && $_GET['if_msg'] ) : ?>
				<div class="notice <?php echo ! empty( $_GET['if_ok'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['if_msg'] ) ) ); ?></p></div>
			<?php endif; ?>

			<div class="if-cards" style="margin-bottom:20px;">
				<div class="if-card if-card-<?php echo esc_attr( $equipo_o->estado ); ?>">
					<span class="if-card-num"><?php echo esc_html( Estado::etiquetas()[ $equipo_o->estado ] ); ?></span>
					<span class="if-card-label"><?php esc_html_e( 'Estado', 'inscripciones-futbol' ); ?></span>
				</div>
				<div class="if-card">
					<span class="if-card-num"><?php echo count( $jugadores ); ?></span>
					<span class="if-card-label"><?php esc_html_e( 'Jugadores', 'inscripciones-futbol' ); ?></span>
				</div>
				<div class="if-card">
					<span class="if-card-num"><?php echo esc_html( $delegado ? $delegado->nombreCompleto() : '—' ); ?></span>
					<span class="if-card-label"><?php esc_html_e( 'Delegado', 'inscripciones-futbol' ); ?></span>
				</div>
				<div class="if-card">
					<span class="if-card-num"><?php echo esc_html( $equipo_o->comprobante ? 'Sí' : 'No' ); ?></span>
					<span class="if-card-label"><?php esc_html_e( 'Comprobante', 'inscripciones-futbol' ); ?></span>
				</div>
			</div>

			<div style="display:grid; grid-template-columns: 1fr 300px; gap:20px; margin-bottom:20px;">
				<div>
					<h2><?php esc_html_e( 'Información del equipo', 'inscripciones-futbol' ); ?></h2>
					<form method="post" action="" enctype="multipart/form-data" style="margin-bottom:12px;">
						<table class="widefat">
							<tbody>
								<tr>
									<th><label for="if_equipo_nombre"><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?></label></th>
									<td><input type="text" id="if_equipo_nombre" name="if_equipo_nombre" value="<?php echo esc_attr( $equipo_o->nombre ); ?>" class="regular-text" required /></td>
								</tr>
								<tr>
									<th><label for="if_escudo"><?php esc_html_e( 'Escudo', 'inscripciones-futbol' ); ?></label></th>
									<td>
										<?php if ( $equipo_o->escudo ) : ?>
											<img src="<?php echo esc_url( $equipo_o->escudo ); ?>" alt="" style="max-width:60px;height:auto;vertical-align:middle;margin-right:8px;" />
										<?php endif; ?>
										<input type="file" id="if_escudo" name="if_escudo" accept="image/*" />
										<p class="description"><?php esc_html_e( 'Se reemplaza solo si elegís un archivo.', 'inscripciones-futbol' ); ?></p>
									</td>
								</tr>
								<tr>
									<th></th>
									<td>
										<?php wp_nonce_field( 'if_admin_equipo', 'if_admin_equipo_nonce' ); ?>
										<button type="submit" name="if_equipo_guardar" class="button button-primary"><?php esc_html_e( 'Guardar equipo', 'inscripciones-futbol' ); ?></button>
									</td>
								</tr>
							</tbody>
						</table>
					</form>
					<table class="widefat">
						<tbody>
							<tr><th><?php esc_html_e( 'Estado', 'inscripciones-futbol' ); ?></th><td><span class="if-badge if-badge-<?php echo esc_attr( $equipo_o->estado ); ?>"><?php echo esc_html( Estado::etiquetas()[ $equipo_o->estado ] ); ?></span></td></tr>
							<tr><th><?php esc_html_e( 'Link de pago propio', 'inscripciones-futbol' ); ?></th><td><?php echo $equipo_o->linkPago ? '<a href="' . esc_url( $equipo_o->linkPago ) . '" target="_blank" rel="noopener">' . esc_url( $equipo_o->linkPago ) . '</a>' : '—'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Comprobante', 'inscripciones-futbol' ); ?></th><td><?php echo $equipo_o->comprobante ? '<a href="' . esc_url( $equipo_o->comprobante ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver comprobante', 'inscripciones-futbol' ) . '</a>' : '—'; ?></td></tr>
							<tr><th><?php esc_html_e( 'Creado', 'inscripciones-futbol' ); ?></th><td><?php echo esc_html( $equipo->post_date ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div>
					<h2><?php esc_html_e( 'Delegado', 'inscripciones-futbol' ); ?></h2>
					<?php if ( $delegado ) : ?>
					<table class="widefat">
						<tbody>
							<tr><th><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?></th><td><?php echo esc_html( $delegado->nombreCompleto() ); ?></td></tr>
							<tr><th><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?></th><td><?php echo esc_html( $delegado->dni ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Teléfono', 'inscripciones-futbol' ); ?></th><td><?php echo esc_html( $delegado->telefono ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Email', 'inscripciones-futbol' ); ?></th><td><?php echo esc_html( $delegado->email ); ?></td></tr>
						</tbody>
					</table>
					<?php else : ?>
					<p><?php esc_html_e( 'Sin delegado asignado', 'inscripciones-futbol' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<h2><?php esc_html_e( 'Jugadores', 'inscripciones-futbol' ); ?></h2>
			<form method="post" action="" enctype="multipart/form-data" style="margin-bottom:20px;">
				<h3 style="margin:0 0 10px;"><?php esc_html_e( 'Agregar jugador', 'inscripciones-futbol' ); ?></h3>
				<table class="widefat" style="max-width:720px;">
					<tbody>
						<tr>
							<th style="width:140px;"><label for="if_jugador_nombre"><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?></label></th>
							<td><input type="text" id="if_jugador_nombre" name="if_jugador_nombre" required /></td>
						</tr>
						<tr>
							<th><label for="if_jugador_apellido"><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?></label></th>
							<td><input type="text" id="if_jugador_apellido" name="if_jugador_apellido" required /></td>
						</tr>
						<tr>
							<th><label for="if_jugador_dni"><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?></label></th>
							<td><input type="text" id="if_jugador_dni" name="if_jugador_dni" required /></td>
						</tr>
						<tr>
							<th><label for="if_dni_archivo"><?php esc_html_e( 'Archivo DNI', 'inscripciones-futbol' ); ?></label></th>
							<td><input type="file" id="if_dni_archivo" name="if_dni_archivo" accept="image/*,.pdf" /></td>
						</tr>
						<tr>
							<th></th>
							<td>
								<?php wp_nonce_field( 'if_admin_jugador_nuevo', 'if_admin_jugador_nuevo_nonce' ); ?>
								<button type="submit" name="if_jugador_nuevo" class="button button-primary"><?php esc_html_e( 'Agregar jugador', 'inscripciones-futbol' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
			</form>
			<?php if ( $jugadores ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Foto', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'DNI archivo', 'inscripciones-futbol' ); ?></th>
							<th><?php esc_html_e( 'Acciones', 'inscripciones-futbol' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $jugadores as $j ) : ?>
						<tr>
							<td>
								<?php echo esc_html( $j->nombreCompleto() ); ?>
								<details style="margin-top:8px;">
									<summary><?php esc_html_e( 'Editar jugador', 'inscripciones-futbol' ); ?></summary>
									<form method="post" action="" enctype="multipart/form-data" style="margin-top:8px;">
										<input type="hidden" name="if_jugador_id" value="<?php echo esc_attr( $j->id ); ?>" />
										<p><label><?php esc_html_e( 'Nombre', 'inscripciones-futbol' ); ?></label> <input type="text" name="if_jugador_nombre" value="<?php echo esc_attr( $j->nombre ); ?>" style="width:180px;" /></p>
										<p><label><?php esc_html_e( 'Apellido', 'inscripciones-futbol' ); ?></label> <input type="text" name="if_jugador_apellido" value="<?php echo esc_attr( $j->apellido ); ?>" style="width:180px;" /></p>
										<p><label><?php esc_html_e( 'DNI', 'inscripciones-futbol' ); ?></label> <input type="text" name="if_jugador_dni" value="<?php echo esc_attr( $j->dni ); ?>" style="width:180px;" /></p>
										<p><label><?php esc_html_e( 'Reemplazar archivo DNI', 'inscripciones-futbol' ); ?></label> <input type="file" name="if_dni_archivo" accept="image/*,.pdf" /></p>
										<?php wp_nonce_field( 'if_admin_jugador_editar', 'if_admin_jugador_editar_nonce' ); ?>
										<p><button type="submit" name="if_jugador_editar" class="button button-small"><?php esc_html_e( 'Guardar cambios', 'inscripciones-futbol' ); ?></button></p>
									</form>
								</details>
							</td>
							<td><?php echo esc_html( $j->dni ); ?></td>
							<td><?php echo $j->foto ? '<img src="' . esc_url( $j->foto ) . '" style="width:40px;height:40px;object-fit:cover;border-radius:50%;" />' : '—'; ?></td>
							<td><?php echo $j->archivoDni ? '<a href="' . esc_url( $j->archivoDni ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver', 'inscripciones-futbol' ) . '</a>' : '—'; ?></td>
							<td>
								<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar este jugador?', 'inscripciones-futbol' ) ); ?>');">
									<input type="hidden" name="if_jugador_id" value="<?php echo esc_attr( $j->id ); ?>" />
									<?php wp_nonce_field( 'if_admin_jugador_eliminar', 'if_admin_jugador_eliminar_nonce' ); ?>
									<button type="submit" name="if_jugador_eliminar" class="button button-small button-link-delete"><?php esc_html_e( 'Eliminar', 'inscripciones-futbol' ); ?></button>
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'Sin jugadores cargados', 'inscripciones-futbol' ); ?></p>
			<?php endif; ?>

			<div style="margin-top:20px; padding:15px; background:#f7f7f7; border:1px solid #ddd; border-radius:4px;">
				<h3><?php esc_html_e( 'Acciones', 'inscripciones-futbol' ); ?></h3>
				<p>
					<?php
					$url = admin_url( 'admin-post.php?action=if_accion&equipo=' . $equipo_id . '&accion=' );
					if ( in_array( $equipo_o->estado, array( Estado::PENDIENTE, Estado::RECHAZADA ), true ) ) {
						echo '<a class="button button-primary" href="' . esc_url( wp_nonce_url( $url . 'aprobar', 'if_accion' ) ) . '">' . esc_html__( 'Aprobar pago', 'inscripciones-futbol' ) . '</a> ';
					}
					if ( Estado::PENDIENTE === $equipo_o->estado || Estado::ACTIVA === $equipo_o->estado ) {
						echo '<a class="button" href="' . esc_url( wp_nonce_url( $url . 'rechazar', 'if_accion' ) ) . '">' . esc_html__( 'Rechazar pago', 'inscripciones-futbol' ) . '</a> ';
					}
					if ( Estado::RECHAZADA === $equipo_o->estado || Estado::ACTIVA === $equipo_o->estado ) {
						echo '<a class="button" href="' . esc_url( wp_nonce_url( $url . 'marcar_pendiente', 'if_accion' ) ) . '">' . esc_html__( 'Marcar pendiente', 'inscripciones-futbol' ) . '</a> ';
					}
					if ( Estado::BLOQUEADA === $equipo_o->estado ) {
						echo '<a class="button" href="' . esc_url( wp_nonce_url( $url . 'desbloquear', 'if_accion' ) ) . '">' . esc_html__( 'Desbloquear', 'inscripciones-futbol' ) . '</a> ';
					} else {
						echo '<a class="button" href="' . esc_url( wp_nonce_url( $url . 'bloquear', 'if_accion' ) ) . '">' . esc_html__( 'Bloquear', 'inscripciones-futbol' ) . '</a> ';
					}
					?>
				</p>
				<p>
					<?php foreach ( array( 'csv', 'xls' ) as $f ) : ?>
					<a class="button" href="<?php echo esc_url( home_url( '/?if_exportar=1&if_formato=' . $f . '&if_equipo=' . $equipo_id ) ); ?>"><?php echo esc_html( strtoupper( $f ) ); ?></a>
					<?php endforeach; ?>
					<a class="button" href="<?php echo esc_url( get_edit_post_link( $equipo_id ) ); ?>"><?php esc_html_e( 'Editar en WP', 'inscripciones-futbol' ); ?></a>
				</p>
			</div>

			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=if-panel' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Volver al panel', 'inscripciones-futbol' ); ?></a></p>
		</div>
		<?php
	}
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
		echo '<a class="button button-small" href="' . esc_url( get_edit_post_link( $equipo->id ) ) . '">' . esc_html__( 'Editar', 'inscripciones-futbol' ) . '</a> ';
		echo '<a class="button button-small button-link-delete" href="' . esc_url( wp_nonce_url( $url . 'eliminar', 'if_accion' ) ) . '" onclick="return confirm(\'' . esc_js( __( '¿Eliminar este equipo y todos sus jugadores?', 'inscripciones-futbol' ) ) . '\');">' . esc_html__( 'Eliminar', 'inscripciones-futbol' ) . '</a>';
	}

	/**
	 * Sube un archivo desde el admin y devuelve su URL (o '' si no se subió nada).
	 *
	 * @param string $clave Nombre del campo en $_FILES.
	 * @param array  $mimes Mapeo de extensiones a tipos MIME permitidos.
	 * @return string|WP_Error
	 */
	private static function subir_archivo_admin( $clave, $mimes ) {
		if ( empty( $_FILES[ $clave ] ) || empty( $_FILES[ $clave ]['name'] ) ) {
			return '';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => $mimes,
		);

		$file = wp_handle_upload( $_FILES[ $clave ], $overrides );
		if ( isset( $file['error'] ) ) {
			return new WP_Error( 'if_upload', $file['error'] );
		}
		return $file['url'];
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
			update_option( 'if_mp_access_token', sanitize_text_field( wp_unslash( $_POST['if_mp_access_token'] ) ) );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Ajustes guardados.', 'inscripciones-futbol' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ajustes de inscripciones', 'inscripciones-futbol' ); ?></h1>
			<form method="post">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="if_monto_inscripcion"><?php esc_html_e( 'Monto de inscripción (ARS)', 'inscripciones-futbol' ); ?></label></th>
						<td><input type="number" step="0.01" id="if_monto_inscripcion" name="if_monto_inscripcion" value="<?php echo esc_attr( IF_App::store()->montoInscripcion() ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="if_mp_access_token"><?php esc_html_e( 'Access Token de Mercado Pago', 'inscripciones-futbol' ); ?></label></th>
						<td>
							<input type="text" id="if_mp_access_token" name="if_mp_access_token" class="regular-text" value="<?php echo esc_attr( get_option( 'if_mp_access_token', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Token de acceso a la API de Mercado Pago (Checkout Pro). Lo encontrás en tu cuenta de MP en Tu negocio > Credenciales.', 'inscripciones-futbol' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="if_mp_link"><?php esc_html_e( 'Link de pago de Mercado Pago (legacy)', 'inscripciones-futbol' ); ?></label></th>
						<td>
							<input type="url" id="if_mp_link" name="if_mp_link" class="regular-text" value="<?php echo esc_attr( IF_App::store()->linkPagoGeneral() ); ?>" />
							<p class="description"><?php esc_html_e( 'Link manual de cobro. Solo se usa si el Access Token está vacío.', 'inscripciones-futbol' ); ?></p>
						</td>
					</tr>
				</table>
				<?php wp_nonce_field( 'if_ajustes', 'if_ajustes_nonce' ); ?>
				<p><button type="submit" name="if_guardar_ajustes" class="button button-primary"><?php esc_html_e( 'Guardar', 'inscripciones-futbol' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
