<?php
/**
 * Implementación de InscripcionStore sobre WordPress.
 *
 * @package InscripcionesFutbol
 */

namespace IF\Infra;

use IF\Core\Delegado;
use IF\Core\Equipo;
use IF\Core\Estado;
use IF\Core\InscripcionStore;
use IF\Core\Jugador;
use IF\Core\Resultado;

/**
 * Almacenamiento basado en usuarios (delegados), CPT if_equipo y CPT if_jugador.
 */
class WpInscripcionStore implements InscripcionStore {

	const CPT_EQUIPO  = 'if_equipo';
	const CPT_JUGADOR = 'if_jugador';
	const ROL         = 'if_delegado';

	// Delegados -------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function obtenerDelegado( $id ) {
		$user = get_userdata( (int) $id );
		if ( ! $user ) {
			return null;
		}
		return $this->delegadoDesdeUser( $user );
	}

	/**
	 * {@inheritdoc}
	 */
	public function obtenerDelegadoPorEmail( $email ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return null;
		}
		return $this->delegadoDesdeUser( $user );
	}

	/**
	 * {@inheritdoc}
	 */
	public function existeEmail( $email ) {
		return false !== get_user_by( 'email', $email );
	}

	/**
	 * {@inheritdoc}
	 */
	public function crearDelegado( Delegado $delegado, $password ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $delegado->email,
				'user_pass'    => $password,
				'user_email'   => $delegado->email,
				'display_name' => $delegado->nombreCompleto(),
				'role'         => self::ROL,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return Resultado::error( $user_id->get_error_message() );
		}
		$this->guardarDelegado( $user_id, $delegado );
		return $user_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function actualizarDelegado( Delegado $delegado ) {
		if ( ! $delegado->id ) {
			return;
		}
		$args = array( 'ID' => $delegado->id );
		if ( $delegado->email ) {
			$args['user_email'] = $delegado->email;
		}
		wp_update_user( $args );
		$this->guardarDelegado( $delegado->id, $delegado );
	}

	/**
	 * @param \WP_User $user Usuario.
	 * @return Delegado
	 */
	private function delegadoDesdeUser( $user ) {
		$d          = new Delegado();
		$d->id      = (int) $user->ID;
		$d->nombre   = get_user_meta( $user->ID, '_if_nombre', true );
		$d->apellido = get_user_meta( $user->ID, '_if_apellido', true );
		$d->dni      = get_user_meta( $user->ID, '_if_dni', true );
		$d->telefono = get_user_meta( $user->ID, '_if_telefono', true );
		$d->email    = $user->user_email;
		return $d;
	}

	/**
	 * @param int      $user_id ID.
	 * @param Delegado $d       Datos.
	 */
	private function guardarDelegado( $user_id, Delegado $d ) {
		update_user_meta( $user_id, '_if_nombre', $d->nombre );
		update_user_meta( $user_id, '_if_apellido', $d->apellido );
		update_user_meta( $user_id, '_if_dni', $d->dni );
		update_user_meta( $user_id, '_if_telefono', $d->telefono );
	}

	// Equipos ---------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function obtenerEquipo( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::CPT_EQUIPO !== $post->post_type ) {
			return null;
		}
		return $this->equipoDesdePost( $post );
	}

	/**
	 * {@inheritdoc}
	 */
	public function obtenerEquipoDelegado( $delegadoId ) {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT_EQUIPO,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'author'         => (int) $delegadoId,
				'fields'         => 'ids',
			)
		);
		return $ids ? $this->obtenerEquipo( $ids[0] ) : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function crearEquipo( $delegadoId, $nombre ) {
		return wp_insert_post(
			array(
				'post_type'   => self::CPT_EQUIPO,
				'post_status' => 'publish',
				'post_title'  => $nombre,
				'post_author' => (int) $delegadoId,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function actualizarEquipo( Equipo $equipo ) {
		wp_update_post(
			array(
				'ID'         => $equipo->id,
				'post_title' => $equipo->nombre,
			)
		);
		update_post_meta( $equipo->id, '_if_estado', $equipo->estado );
		update_post_meta( $equipo->id, '_if_estado_previo', $equipo->estadoPrevio );
		update_post_meta( $equipo->id, '_if_mp_link', $equipo->linkPago );
		update_post_meta( $equipo->id, '_if_comprobante', $equipo->comprobante );
		update_post_meta( $equipo->id, '_if_escudo', $equipo->escudo );
	}

	/**
	 * {@inheritdoc}
	 */
	public function listarEquipos( $estado = null ) {
		$args = array(
			'post_type'      => self::CPT_EQUIPO,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $estado ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_if_estado',
					'value' => $estado,
				),
			);
		}
		$posts = get_posts( $args );
		$out   = array();
		foreach ( $posts as $post ) {
			$out[] = $this->equipoDesdePost( $post );
		}
		return $out;
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return Equipo
	 */
	private function equipoDesdePost( $post ) {
		$estado = get_post_meta( $post->ID, '_if_estado', true );
		if ( ! in_array( $estado, Estado::todos(), true ) ) {
			// Migración desde versión anterior.
			$pago  = get_post_meta( $post->ID, '_if_pago_estado', true );
			$block = get_post_meta( $post->ID, '_if_bloqueado', true );
			$estado = Estado::porDefecto();
			if ( '1' === $block ) {
				$estado = Estado::BLOQUEADA;
			} elseif ( 'aprobado' === $pago ) {
				$estado = Estado::ACTIVA;
			} elseif ( 'rechazado' === $pago ) {
				$estado = Estado::RECHAZADA;
			}
			update_post_meta( $post->ID, '_if_estado', $estado );
		}
		$e              = new Equipo();
		$e->id          = (int) $post->ID;
		$e->nombre      = $post->post_title;
		$e->delegadoId  = (int) $post->post_author;
		$e->estado      = $estado;
		$e->estadoPrevio = get_post_meta( $post->ID, '_if_estado_previo', true );
		$e->linkPago    = get_post_meta( $post->ID, '_if_mp_link', true );
		$e->comprobante = get_post_meta( $post->ID, '_if_comprobante', true );
		$e->escudo     = get_post_meta( $post->ID, '_if_escudo', true );
		return $e;
	}

	// Jugadores -------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function obtenerJugador( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::CPT_JUGADOR !== $post->post_type ) {
			return null;
		}
		return $this->jugadorDesdePost( $post );
	}

	/**
	 * {@inheritdoc}
	 */
	public function obtenerJugadores( $equipoId ) {
		$posts = get_posts(
			array(
				'post_type'      => self::CPT_JUGADOR,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_if_equipo_id',
				'meta_value'     => (int) $equipoId,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $this->jugadorDesdePost( $post );
		}
		return $out;
	}

	/**
	 * {@inheritdoc}
	 */
	public function crearJugador( Jugador $jugador ) {
		$id = wp_insert_post(
			array(
				'post_type'   => self::CPT_JUGADOR,
				'post_status' => 'publish',
				'post_title'  => $jugador->nombreCompleto(),
			)
		);
		$jugador->id = $id;
		$this->actualizarJugador( $jugador );
		return $id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function actualizarJugador( Jugador $jugador ) {
		wp_update_post(
			array(
				'ID'         => $jugador->id,
				'post_title' => $jugador->nombreCompleto(),
			)
		);
		update_post_meta( $jugador->id, '_if_equipo_id', $jugador->equipoId );
		update_post_meta( $jugador->id, '_if_dni', $jugador->dni );
		update_post_meta( $jugador->id, '_if_dni_archivo', $jugador->archivoDni );
		update_post_meta( $jugador->id, '_if_foto', $jugador->foto );
	}

	/**
	 * {@inheritdoc}
	 */
	public function eliminarJugador( $id ) {
		wp_delete_post( (int) $id, true );
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return Jugador
	 */
	private function jugadorDesdePost( $post ) {
		$j              = new Jugador();
		$j->id          = (int) $post->ID;
		$j->equipoId    = (int) get_post_meta( $post->ID, '_if_equipo_id', true );
		$j->nombre      = (string) get_post_meta( $post->ID, '_if_nombre', true );
		$j->apellido    = (string) get_post_meta( $post->ID, '_if_apellido', true );
		$j->dni         = (string) get_post_meta( $post->ID, '_if_dni', true );
		$j->archivoDni  = (string) get_post_meta( $post->ID, '_if_dni_archivo', true );
		$j->foto        = (string) get_post_meta( $post->ID, '_if_foto', true );

		// Migración: título "Nombre Apellido" de la versión anterior.
		if ( ! $j->nombre && ! $j->apellido && $post->post_title ) {
			$parts = explode( ' ', trim( $post->post_title ) );
			$j->apellido = array_pop( $parts );
			$j->nombre   = implode( ' ', $parts );
		}
		return $j;
	}

	// Config ----------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function montoInscripcion() {
		return (float) get_option( 'if_monto_inscripcion', 1000 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function linkPagoGeneral() {
		return (string) get_option( 'if_mp_link', '' );
	}
}