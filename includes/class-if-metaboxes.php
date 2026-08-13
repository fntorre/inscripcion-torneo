<?php
/**
 * Metaboxes en la edición de equipos.
 *
 * @package InscripcionesFutbol
 */

use IF\Core\Equipo;
use IF\Core\Estado;
use IF\Core\Jugador;

/**
 * Datos relevantes de la inscripción dentro del editor de WordPress.
 */
final class IF_Metaboxes {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'registrar' ) );
		add_action( 'save_post_' . IF_Install::CPT_EQUIPO, array( __CLASS__, 'guardar' ) );
	}

	/**
	 * Registra la metabox.
	 */
	public static function registrar() {
		add_meta_box( 'if_equipo_datos', __( 'Inscripción', 'inscripciones-futbol' ), array( __CLASS__, 'render' ), IF_Install::CPT_EQUIPO, 'normal', 'high' );
	}

	/**
	 * Render.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'if_equipo_guardar', 'if_equipo_nonce' );
		$equipo = IF_App::store()->obtenerEquipo( $post->ID );
		if ( ! $equipo ) {
			return;
		}
		$delegado = IF_App::store()->obtenerDelegado( $equipo->delegadoId );
		$jugadores = IF_App::servicio()->jugadoresDe( $equipo->id );
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Delegado', 'inscripciones-futbol' ); ?></th>
				<td>
					<?php echo esc_html( $delegado ? $delegado->nombreCompleto() : '—' ); ?><br />
					<?php echo esc_html( $delegado ? $delegado->dni : '' ); ?> · <?php echo esc_html( $delegado ? $delegado->telefono : '' ); ?> · <?php echo esc_html( $delegado ? $delegado->email : '' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Estado', 'inscripciones-futbol' ); ?></th>
				<td>
					<select name="if_estado">
						<?php foreach ( Estado::etiquetas() as $clave => $etiqueta ) : ?>
							<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( $equipo->estado, $clave ); ?>><?php echo esc_html( $etiqueta ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'También podés cambiarlo desde el Panel de inscripciones.', 'inscripciones-futbol' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="if_mp_link"><?php esc_html_e( 'Link de pago específico', 'inscripciones-futbol' ); ?></label></th>
				<td>
					<input type="url" id="if_mp_link" name="if_mp_link" class="regular-text" value="<?php echo esc_attr( $equipo->linkPago ); ?>" />
					<p class="description"><?php esc_html_e( 'Opcional. Si se deja vacío se usa el link general de los Ajustes.', 'inscripciones-futbol' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Comprobante de pago', 'inscripciones-futbol' ); ?></th>
				<td>
					<?php if ( $equipo->comprobante ) : ?>
						<a href="<?php echo esc_url( $equipo->comprobante ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver comprobante adjunto', 'inscripciones-futbol' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'Sin comprobante adjunto.', 'inscripciones-futbol' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Escudo', 'inscripciones-futbol' ); ?></th>
				<td>
					<?php if ( $equipo->escudo ) : ?>
						<img src="<?php echo esc_url( $equipo->escudo ); ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;vertical-align:middle;" />
						&nbsp;<a href="<?php echo esc_url( $equipo->escudo ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver escudo', 'inscripciones-futbol' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'Sin escudo.', 'inscripciones-futbol' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Jugadores cargados', 'inscripciones-futbol' ); ?></th>
				<td>
					<?php if ( $jugadores ) : ?>
						<ul>
							<?php foreach ( $jugadores as $j ) : ?>
								<li>
									<?php echo esc_html( $j->nombreCompleto() . ' — DNI: ' . $j->dni ); ?>
									<?php if ( $j->archivoDni ) : ?>
										&mdash; <a href="<?php echo esc_url( $j->archivoDni ); ?>" target="_blank"><?php esc_html_e( 'Ver archivo', 'inscripciones-futbol' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<?php esc_html_e( 'Sin jugadores cargados.', 'inscripciones-futbol' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Guarda estado y link.
	 *
	 * @param int $post_id ID.
	 */
	public static function guardar( $post_id ) {
		if ( ! isset( $_POST['if_equipo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['if_equipo_nonce'] ) ), 'if_equipo_guardar' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$equipo = IF_App::store()->obtenerEquipo( $post_id );
		if ( ! $equipo ) {
			return;
		}
		if ( isset( $_POST['if_estado'] ) && in_array( $_POST['if_estado'], Estado::todos(), true ) ) {
			$equipo->estado = sanitize_key( $_POST['if_estado'] );
		}
		if ( isset( $_POST['if_mp_link'] ) ) {
			$equipo->linkPago = esc_url_raw( wp_unslash( $_POST['if_mp_link'] ) );
		}
		IF_App::store()->actualizarEquipo( $equipo );
	}
}
