<?php
/**
 * Interfaz de persistencia de la inscripción.
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Contrato de almacenamiento. La lógica de negocio depende de esta interfaz,
 * no de WordPress. Las implementaciones concretas viven en IF\Infra.
 */
interface InscripcionStore {

	// Delegados.

	/**
	 * @param int $id ID.
	 * @return Delegado|null
	 */
	public function obtenerDelegado( $id );

	/**
	 * @param string $email Correo.
	 * @return Delegado|null
	 */
	public function obtenerDelegadoPorEmail( $email );

	/**
	 * @param string $email Correo.
	 * @return bool
	 */
	public function existeEmail( $email );

	/**
	 * Crea un delegado y devuelve su ID.
	 *
	 * @param Delegado $delegado Delegado (id ignorado).
	 * @param string   $password Contraseña.
	 * @return int|Resultado
	 */
	public function crearDelegado( Delegado $delegado, $password );

	/**
	 * @param Delegado $delegado Delegado.
	 */
	public function actualizarDelegado( Delegado $delegado );

	// Equipos.

	/**
	 * @param int $id ID.
	 * @return Equipo|null
	 */
	public function obtenerEquipo( $id );

	/**
	 * @param int $delegadoId ID del delegado.
	 * @return Equipo|null
	 */
	public function obtenerEquipoDelegado( $delegadoId );

	/**
	 * @param int    $delegadoId ID del delegado.
	 * @param string $nombre     Nombre.
	 * @return int ID del equipo.
	 */
	public function crearEquipo( $delegadoId, $nombre );

	/**
	 * @param Equipo $equipo Equipo.
	 */
	public function actualizarEquipo( Equipo $equipo );

	/**
	 * Lista de equipos, opcionalmente filtrados por estado.
	 *
	 * @param string|null $estado Estado o null.
	 * @return Equipo[]
	 */
	public function listarEquipos( $estado = null );

	// Jugadores.

	/**
	 * @param int $id ID.
	 * @return Jugador|null
	 */
	public function obtenerJugador( $id );

	/**
	 * @param int $equipoId ID del equipo.
	 * @return Jugador[]
	 */
	public function obtenerJugadores( $equipoId );

	/**
	 * Crea un jugador y devuelve su ID.
	 *
	 * @param Jugador $jugador Jugador.
	 * @return int
	 */
	public function crearJugador( Jugador $jugador );

	/**
	 * @param Jugador $jugador Jugador.
	 */
	public function actualizarJugador( Jugador $jugador );

	/**
	 * @param int $id ID del jugador.
	 */
	public function eliminarJugador( $id );

	// Configuración.

	/**
	 * Monto de inscripción.
	 *
	 * @return float
	 */
	public function montoInscripcion();

	/**
	 * Link de pago general.
	 *
	 * @return string
	 */
	public function linkPagoGeneral();
}
