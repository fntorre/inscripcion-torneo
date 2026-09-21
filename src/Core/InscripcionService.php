<?php
/**
 * Servicio de inscripción (lógica de negocio pura).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Orquesta todo el proceso de inscripción. No usa WordPress:
 * depende únicamente de InscripcionStore.
 */
final class InscripcionService {

	/** Máximo de equipos inscriptos con pago aprobado. */
	public const MAX_EQUIPOS = 48;

	/** @var InscripcionStore */
	private $store;

	/**
	 * @param InscripcionStore $store Almacenamiento.
	 */
	public function __construct( InscripcionStore $store ) {
		$this->store = $store;
	}

	/**
	 * Cantidad de equipos inscriptos con pago aprobado.
	 *
	 * @return int
	 */
	public function contarEquiposActivos() {
		return count( $this->store->listarEquipos( Estado::ACTIVA ) );
	}

	/**
	 * Plazas restantes hasta completar el cupo.
	 *
	 * @return int
	 */
	public function plazasDisponibles() {
		return max( 0, self::MAX_EQUIPOS - $this->contarEquiposActivos() );
	}

	/**
	 * ¿El cupo de equipos está completo?
	 *
	 * @return bool
	 */
	public function cupoCompleto() {
		return $this->plazasDisponibles() <= 0;
	}

	/**
	 * Mensaje de cupo completo.
	 *
	 * @return string
	 */
	private function mensaje_cupo() {
		return sprintf( 'El cupo de %d equipos ya está completo. No se aceptan más inscripciones.', self::MAX_EQUIPOS );
	}

	/**
	 * Registrar un nuevo delegado.
	 *
	 * @param array  $datos    Datos: nombre, apellido, dni, telefono, email, password, equipoNombre.
	 * @param callable|null $login Callable que inicia sesión (adaptador).
	 * @return Resultado
	 */
	public function registrarDelegado( $datos, $login = null ) {
		$nombre   = isset( $datos['nombre'] ) ? trim( (string) $datos['nombre'] ) : '';
		$apellido = isset( $datos['apellido'] ) ? trim( (string) $datos['apellido'] ) : '';
		$dni      = isset( $datos['dni'] ) ? trim( (string) $datos['dni'] ) : '';
		$telefono = isset( $datos['telefono'] ) ? trim( (string) $datos['telefono'] ) : '';
		$email    = isset( $datos['email'] ) ? trim( (string) $datos['email'] ) : '';
		$password = isset( $datos['password'] ) ? (string) $datos['password'] : '';
		$equipoNombre = isset( $datos['equipoNombre'] ) ? trim( (string) $datos['equipoNombre'] ) : '';

		if ( $this->cupoCompleto() ) {
			return Resultado::error( $this->mensaje_cupo() );
		}

		$errores = array();
		if ( ! $nombre ) { $errores[] = 'Ingresá tu nombre.'; }
		if ( ! $apellido ) { $errores[] = 'Ingresá tu apellido.'; }
		if ( ! $dni ) { $errores[] = 'Ingresá tu DNI.'; }
		if ( ! $telefono ) { $errores[] = 'Ingresá tu teléfono.'; }
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) { $errores[] = 'Correo inválido.'; }
		if ( $this->store->existeEmail( $email ) ) { $errores[] = 'Ya existe una cuenta con ese correo.'; }
		if ( strlen( $password ) < 6 ) { $errores[] = 'La contraseña debe tener al menos 6 caracteres.'; }
		if ( ! $equipoNombre ) { $errores[] = 'Ingresá el nombre del equipo.'; }
		if ( $errores ) {
			return Resultado::error( $errores );
		}

		$delegado = new Delegado();
		$delegado->nombre   = $nombre;
		$delegado->apellido = $apellido;
		$delegado->dni      = $dni;
		$delegado->telefono = $telefono;
		$delegado->email    = $email;

		$id = $this->store->crearDelegado( $delegado, $password );
		if ( $id instanceof Resultado ) {
			return $id;
		}
		$delegado->id = $id;

		$this->store->crearEquipo( $id, $equipoNombre );

		if ( $login ) {
			$login( $id );
		}

		return Resultado::exito( array( 'delegadoId' => $id ) );
	}

	/**
	 * Crear equipo (si el delegado aún no tiene uno).
	 *
	 * @param int    $delegadoId ID.
	 * @param string $nombre     Nombre.
	 * @return Resultado
	 */
	public function crearEquipo( $delegadoId, $nombre ) {
		$nombre = trim( (string) $nombre );
		if ( ! $nombre ) {
			return Resultado::error( 'Ingresá el nombre del equipo.' );
		}
		if ( $this->cupoCompleto() ) {
			return Resultado::error( $this->mensaje_cupo() );
		}
		if ( $this->store->obtenerEquipoDelegado( $delegadoId ) ) {
			return Resultado::error( 'Ya tenés un equipo.' );
		}
		$this->store->crearEquipo( $delegadoId, $nombre );
		return Resultado::exito();
	}

	/**
	 * Actualizar datos personales del delegado.
	 *
	 * @param int   $delegadoId ID.
	 * @param array $datos      Datos: nombre, apellido, dni, telefono.
	 * @return Resultado
	 */
	public function actualizarDatosDelegado( $delegadoId, $datos ) {
		$delegado = $this->store->obtenerDelegado( $delegadoId );
		if ( ! $delegado ) {
			return Resultado::error( 'Delegado no encontrado.' );
		}
		if ( isset( $datos['nombre'] ) ) { $delegado->nombre = trim( (string) $datos['nombre'] ); }
		if ( isset( $datos['apellido'] ) ) { $delegado->apellido = trim( (string) $datos['apellido'] ); }
		if ( isset( $datos['dni'] ) ) { $delegado->dni = trim( (string) $datos['dni'] ); }
		if ( isset( $datos['telefono'] ) ) { $delegado->telefono = trim( (string) $datos['telefono'] ); }
		if ( ! $delegado->datosCompletos() ) {
			return Resultado::error( 'Completá todos tus datos.' );
		}
		$this->store->actualizarDelegado( $delegado );
		return Resultado::exito();
	}

	/**
	 * Renombrar equipo del delegado.
	 *
	 * @param int    $delegadoId ID.
	 * @param string $nombre     Nombre.
	 * @return Resultado
	 */
	public function renombrarEquipo( $delegadoId, $nombre ) {
		$equipo = $this->store->obtenerEquipoDelegado( $delegadoId );
		if ( ! $equipo ) {
			return Resultado::error( 'No hay equipo.' );
		}
		$nombre = trim( (string) $nombre );
		if ( ! $nombre ) {
			return Resultado::error( 'Ingresá el nombre del equipo.' );
		}
		$equipo->nombre = $nombre;
		$this->store->actualizarEquipo( $equipo );
		return Resultado::exito();
	}

	/**
	 * Cambiar el estado de una inscripción mediante una acción.
	 *
	 * @param int    $equipoId ID del equipo.
	 * @param string $accion   Acción (aprobar, rechazar, marcar_pendiente, bloquear).
	 * @return Resultado
	 */
	public function cambiarEstado( $equipoId, $accion ) {
		$equipo = $this->store->obtenerEquipo( $equipoId );
		if ( ! $equipo ) {
			return Resultado::error( 'Equipo no encontrado.' );
		}

		if ( 'aprobar' === $accion && Estado::ACTIVA !== $equipo->estado && $this->cupoCompleto() ) {
			return Resultado::error( $this->mensaje_cupo() );
		}

		if ( 'desbloquear' === $accion ) {
			$nuevo = $equipo->estadoPrevio ? $equipo->estadoPrevio : Estado::PENDIENTE;
			if ( ! in_array( $nuevo, Estado::todos(), true ) ) {
				$nuevo = Estado::PENDIENTE;
			}
			$equipo->estadoPrevio = '';
			$equipo->estado       = $nuevo;
			$this->store->actualizarEquipo( $equipo );
			return Resultado::exito( array( 'estado' => $equipo->estado ) );
		}

		$nuevo = Estado::aplicar( $equipo->estado, $accion );
		if ( null === $nuevo ) {
			return Resultado::error( 'Transición no permitida.' );
		}

		if ( 'bloquear' === $accion ) {
			$equipo->estadoPrevio = $equipo->estado;
		} elseif ( $equipo->estadoPrevio && $nuevo !== Estado::BLOQUEADA ) {
			$equipo->estadoPrevio = '';
		}
		$equipo->estado = $nuevo;
		$this->store->actualizarEquipo( $equipo );
		return Resultado::exito( array( 'estado' => $equipo->estado ) );
	}

	/**
	 * Adjuntar el comprobante de pago de un equipo.
	 * Si el pago estaba rechazado, vuelve a "pendiente" para ser revisado.
	 *
	 * @param int    $delegadoId ID del delegado.
	 * @param string $archivo    URL del comprobante.
	 * @return Resultado
	 */
	public function adjuntarComprobante( $delegadoId, $archivo ) {
		$equipo = $this->store->obtenerEquipoDelegado( $delegadoId );
		if ( ! $equipo ) {
			return Resultado::error( 'No tenés un equipo cargado.' );
		}
		if ( Estado::ACTIVA === $equipo->estado ) {
			return Resultado::error( 'Tu inscripción ya está aprobada.' );
		}
		if ( ! $archivo ) {
			return Resultado::error( 'Adjuntá el comprobante de pago.' );
		}
		$equipo->comprobante = $archivo;
		if ( Estado::RECHAZADA === $equipo->estado ) {
			$equipo->estado = Estado::PENDIENTE;
		}
		$this->store->actualizarEquipo( $equipo );
		return Resultado::exito( array( 'estado' => $equipo->estado ) );
	}

	/**
	 * Subir o reemplazar el escudo del equipo.
	 *
	 * @param int    $delegadoId ID del delegado.
	 * @param string $archivo    URL de la imagen.
	 * @return Resultado
	 */
	public function subirEscudo( $delegadoId, $archivo ) {
		$equipo = $this->store->obtenerEquipoDelegado( $delegadoId );
		if ( ! $equipo ) {
			return Resultado::error( 'No tenés un equipo cargado.' );
		}
		if ( ! $archivo ) {
			return Resultado::error( 'Seleccioná una imagen para el escudo.' );
		}
		$equipo->escudo = $archivo;
		$this->store->actualizarEquipo( $equipo );
		return Resultado::exito();
	}

	/**
	 * Actualizar nombre y escudo de un equipo (uso administrativo).
	 *
	 * @param int   $equipoId ID del equipo.
	 * @param array $datos    Datos: nombre, escudo.
	 * @return Resultado
	 */
	public function actualizarEquipoDatos( $equipoId, $datos ) {
		$equipo = $this->store->obtenerEquipo( $equipoId );
		if ( ! $equipo ) {
			return Resultado::error( 'Equipo no encontrado.' );
		}
		if ( isset( $datos['nombre'] ) ) {
			$nombre = trim( (string) $datos['nombre'] );
			if ( ! $nombre ) {
				return Resultado::error( 'Ingresá el nombre del equipo.' );
			}
			$equipo->nombre = $nombre;
		}
		if ( isset( $datos['escudo'] ) && '' !== (string) $datos['escudo'] ) {
			$equipo->escudo = (string) $datos['escudo'];
		}
		$this->store->actualizarEquipo( $equipo );
		return Resultado::exito();
	}

	/**
	 * ¿El delegado puede cargar jugadores?
	 *
	 * @param int $equipoId ID.
	 * @return bool
	 */
	public function puedeCargarJugadores( $equipoId ) {
		$equipo = $this->store->obtenerEquipo( $equipoId );
		return $equipo && Estado::permiteCargarJugadores( $equipo->estado );
	}

	/**
	 * Agregar un jugador al equipo.
	 *
	 * @param int   $equipoId ID del equipo.
	 * @param array $datos    Datos: nombre, apellido, dni, archivoDni.
	 * @return Resultado
	 */
	public function agregarJugador( $equipoId, $datos ) {
		return $this->crearJugador( $equipoId, $datos, false );
	}

	/**
	 * Agregar un jugador como administrador (no depende del estado de la inscripción).
	 *
	 * @param int   $equipoId ID del equipo.
	 * @param array $datos    Datos: nombre, apellido, dni, archivoDni.
	 * @return Resultado
	 */
	public function agregarJugadorAdmin( $equipoId, $datos ) {
		return $this->crearJugador( $equipoId, $datos, true );
	}

	/**
	 * Crea un jugador validando la habilitación salvo que sea operación de admin.
	 *
	 * @param int   $equipoId ID del equipo.
	 * @param array $datos    Datos: nombre, apellido, dni, archivoDni.
	 * @param bool  $esAdmin  Si es true no valida que la inscripción permita cargar.
	 * @return Resultado
	 */
	private function crearJugador( $equipoId, $datos, $esAdmin ) {
		if ( ! $esAdmin && ! $this->puedeCargarJugadores( $equipoId ) ) {
			return Resultado::error( 'La carga de jugadores no está habilitada para esta inscripción.' );
		}
		$nombre   = isset( $datos['nombre'] ) ? trim( (string) $datos['nombre'] ) : '';
		$apellido = isset( $datos['apellido'] ) ? trim( (string) $datos['apellido'] ) : '';
		$dni      = isset( $datos['dni'] ) ? trim( (string) $datos['dni'] ) : '';
		if ( ! $nombre || ! $apellido || ! $dni ) {
			return Resultado::error( 'Completá nombre, apellido y DNI del jugador.' );
		}
		$jugador = new Jugador();
		$jugador->equipoId   = $equipoId;
		$jugador->nombre     = $nombre;
		$jugador->apellido   = $apellido;
		$jugador->dni        = $dni;
		$jugador->archivoDni = isset( $datos['archivoDni'] ) ? (string) $datos['archivoDni'] : '';
		$jugador->foto       = isset( $datos['foto'] ) ? (string) $datos['foto'] : '';
		$id = $this->store->crearJugador( $jugador );
		return Resultado::exito( array( 'jugadorId' => $id ) );
	}

	/**
	 * Actualizar jugador (pertenencia validada por el llamador vía equipo).
	 *
	 * @param int   $jugadorId ID.
	 * @param array $datos     Datos.
	 * @return Resultado
	 */
	public function actualizarJugador( $jugadorId, $datos ) {
		$jugador = $this->store->obtenerJugador( $jugadorId );
		if ( ! $jugador ) {
			return Resultado::error( 'Jugador no encontrado.' );
		}
		if ( isset( $datos['nombre'] ) && trim( (string) $datos['nombre'] ) ) {
			$jugador->nombre = trim( (string) $datos['nombre'] );
		}
		if ( isset( $datos['apellido'] ) && trim( (string) $datos['apellido'] ) ) {
			$jugador->apellido = trim( (string) $datos['apellido'] );
		}
		if ( isset( $datos['dni'] ) && trim( (string) $datos['dni'] ) ) {
			$jugador->dni = trim( (string) $datos['dni'] );
		}
		if ( isset( $datos['archivoDni'] ) && $datos['archivoDni'] ) {
			$jugador->archivoDni = (string) $datos['archivoDni'];
		}
		if ( isset( $datos['foto'] ) && $datos['foto'] ) {
			$jugador->foto = (string) $datos['foto'];
		}
		$this->store->actualizarJugador( $jugador );
		return Resultado::exito();
	}

	/**
	 * Eliminar jugador.
	 *
	 * @param int $jugadorId ID.
	 * @return Resultado
	 */
	public function eliminarJugador( $jugadorId ) {
		$jugador = $this->store->obtenerJugador( $jugadorId );
		if ( ! $jugador ) {
			return Resultado::error( 'Jugador no encontrado.' );
		}
		$this->store->eliminarJugador( $jugadorId );
		return Resultado::exito();
	}

	/**
	 * Jugadores de un equipo.
	 *
	 * @param int $equipoId ID.
	 * @return Jugador[]
	 */
	public function jugadoresDe( $equipoId ) {
		return $this->store->obtenerJugadores( $equipoId );
	}

	/**
	 * Filas para exportar un equipo.
	 *
	 * @param int $equipoId ID.
	 * @return array
	 */
	public function filasEquipo( $equipoId ) {
		$equipo = $this->store->obtenerEquipo( $equipoId );
		$rows   = array();
		if ( ! $equipo ) {
			return $rows;
		}
		$delegado = $this->store->obtenerDelegado( $equipo->delegadoId );
		foreach ( $this->jugadoresDe( $equipoId ) as $j ) {
			$rows[] = $this->fila( $equipo, $delegado, $j );
		}
		return $rows;
	}

	/**
	 * Filas para exportar todos los equipos.
	 *
	 * @return array
	 */
	public function filasGeneral() {
		$rows = array();
		foreach ( $this->store->listarEquipos() as $equipo ) {
			$delegado = $this->store->obtenerDelegado( $equipo->delegadoId );
			$jugadores = $this->jugadoresDe( $equipo->id );
			if ( ! $jugadores ) {
				$rows[] = $this->fila( $equipo, $delegado, null );
			}
			foreach ( $jugadores as $j ) {
				$rows[] = $this->fila( $equipo, $delegado, $j );
			}
		}
		return $rows;
	}

	/**
	 * @param Equipo         $equipo   Equipo.
	 * @param Delegado|null  $delegado Delegado.
	 * @param Jugador|null   $jugador  Jugador.
	 * @return array
	 */
	private function fila( $equipo, $delegado, $jugador ) {
		return array(
			'equipo'       => $equipo->nombre,
			'estado'       => Estado::etiquetas()[ $equipo->estado ],
			'delegado'     => $delegado ? $delegado->nombreCompleto() : '',
			'delegado_dni' => $delegado ? $delegado->dni : '',
			'delegado_tel' => $delegado ? $delegado->telefono : '',
			'jugador'      => $jugador ? $jugador->nombreCompleto() : '',
			'jugador_dni'  => $jugador ? $jugador->dni : '',
			'archivo'      => $jugador ? $jugador->archivoDni : '',
		);
	}
}
