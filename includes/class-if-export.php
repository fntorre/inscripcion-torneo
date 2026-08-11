<?php
/**
 * Exportación de listados (CSV, Excel, PDF).
 *
 * @package InscripcionesFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase IF_Export
 */
class IF_Export {

	/**
	 * Inicializa.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_download' ) );
	}

	/**
	 * Detecta la petición de descarga.
	 */
	public static function maybe_download() {
		if ( ! isset( $_GET['if_action'] ) || 'exportar' !== $_GET['if_action'] ) {
			return;
		}
		$formato = isset( $_GET['if_formato'] ) ? sanitize_key( $_GET['if_formato'] ) : 'csv';
		$equipo  = isset( $_GET['if_equipo'] ) ? intval( $_GET['if_equipo'] ) : 0;

		if ( $equipo ) {
			self::verificar_acceso_equipo( $equipo );
			$rows = self::build_rows( $equipo );
			self::output( $formato, $rows, 'equipo-' . $equipo );
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
			}
			$rows = self::build_rows_general();
			self::output( $formato, $rows, 'todos-los-equipos' );
		}
	}

	/**
	 * Verifica que el usuario actual pueda ver el equipo.
	 *
	 * @param int $equipo_id ID.
	 */
	private static function verificar_acceso_equipo( $equipo_id ) {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Inicia sesión para descargar.', 'inscripciones-futbol' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$equipo = get_post( $equipo_id );
			if ( ! $equipo || $equipo->post_author !== get_current_user_id() ) {
				wp_die( esc_html__( 'No autorizado.', 'inscripciones-futbol' ) );
			}
		}
	}

	/**
	 * Construye las filas de un equipo.
	 *
	 * @param int $equipo_id ID.
	 * @return array
	 */
	private static function build_rows( $equipo_id ) {
		$jugadores = IF_Post_Types::get_jugadores( $equipo_id );
		$rows      = array();
		foreach ( $jugadores as $j ) {
			$rows[] = array(
				'equipo'   => get_the_title( $equipo_id ),
				'nombre'   => $j->post_title,
				'dni'      => get_post_meta( $j->ID, '_if_dni', true ),
				'archivo'  => get_post_meta( $j->ID, '_if_dni_archivo', true ),
			);
		}
		return $rows;
	}

	/**
	 * Construye las filas generales (todos los equipos).
	 *
	 * @return array
	 */
	private static function build_rows_general() {
		$equipos = get_posts(
			array(
				'post_type'      => IF_Post_Types::EQUIPO,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$rows = array();
		foreach ( $equipos as $equipo ) {
			$estado   = IF_Post_Types::get_equipo_pago_estado( $equipo->ID );
			$delegado = if_get_delegado_meta( $equipo->post_author );
			foreach ( self::build_rows( $equipo->ID ) as $row ) {
				$row['delegado']      = $delegado['nombre'] . ' ' . $delegado['apellido'];
				$row['delegado_dni']  = $delegado['dni'];
				$row['delegado_tel']  = $delegado['telefono'];
				$row['estado_pago']   = if_pago_estado_label( $estado );
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Emite el archivo.
	 *
	 * @param string $formato Formato.
	 * @param array  $rows    Filas.
	 * @param string $base    Base del nombre.
	 */
	private static function output( $formato, $rows, $base ) {
		$base = sanitize_file_name( $base );
		switch ( $formato ) {
			case 'xls':
				self::output_xls( $rows, $base );
				break;
			case 'pdf':
				self::output_pdf( $rows, $base );
				break;
			default:
				self::output_csv( $rows, $base );
		}
	}

	/**
	 * CSV.
	 *
	 * @param array  $rows Filas.
	 * @param string $base Nombre.
	 */
	private static function output_csv( $rows, $base ) {
		$headers = if_export_headers();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $base . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM para Excel.
		fputcsv( $out, $headers );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $key => $label ) {
				$line[ $key ] = if_clean_export( isset( $row[ $key ] ) ? $row[ $key ] : '' );
			}
			fputcsv( $out, $line );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Excel (XML SpreadsheetML).
	 *
	 * @param array  $rows Filas.
	 * @param string $base Nombre.
	 */
	private static function output_xls( $rows, $base ) {
		$headers = if_export_headers();
		nocache_headers();
		header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $base . '.xls' );
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Listado"><Table>';
		echo '<Row>';
		foreach ( $headers as $label ) {
			echo '<Cell><Data ss:Type="String">' . esc_xml( $label ) . '</Data></Cell>';
		}
		echo '</Row>';
		foreach ( $rows as $row ) {
			echo '<Row>';
			foreach ( $headers as $key => $label ) {
				$val = if_clean_export( isset( $row[ $key ] ) ? $row[ $key ] : '' );
				echo '<Cell><Data ss:Type="String">' . esc_xml( $val ) . '</Data></Cell>';
			}
			echo '</Row>';
		}
		echo '</Table></Worksheet></Workbook>';
		exit;
	}

	/**
	 * PDF simple.
	 *
	 * @param array  $rows Filas.
	 * @param string $base Nombre.
	 */
	private static function output_pdf( $rows, $base ) {
		$headers = if_export_headers();
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename=' . $base . '.pdf' );
		$pdf = new IF_PDF_Min();
		$pdf->titulo( __( 'Listado de jugadores', 'inscripciones-futbol' ) );
		$pdf->tabla( array_values( $headers ), array_map( 'array_values', $rows ) );
		$pdf->output();
		exit;
	}
}

/**
 * Cabeceras de exportación.
 *
 * @return array
 */
function if_export_headers() {
	return array(
		'equipo'       => __( 'Equipo', 'inscripciones-futbol' ),
		'delegado'     => __( 'Delegado', 'inscripciones-futbol' ),
		'delegado_dni' => __( 'DNI Delegado', 'inscripciones-futbol' ),
		'delegado_tel' => __( 'Teléfono', 'inscripciones-futbol' ),
		'estado_pago'  => __( 'Estado pago', 'inscripciones-futbol' ),
		'nombre'       => __( 'Jugador', 'inscripciones-futbol' ),
		'dni'          => __( 'DNI Jugador', 'inscripciones-futbol' ),
		'archivo'      => __( 'Archivo DNI', 'inscripciones-futbol' ),
	);
}

if ( ! function_exists( 'esc_xml' ) ) {
	/**
	 * Escapa contenido XML.
	 *
	 * @param string $val Valor.
	 * @return string
	 */
	function esc_xml( $val ) {
		return htmlspecialchars( $val, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}

/**
 * Generador PDF mínimo (tabla simple).
 */
class IF_PDF_Min {

	private $objects = array();
	private $content = '';
	private $page    = 1;
	private $y       = 40;

	const W = 595.28;
	const H = 841.89;

	/**
	 * Título del documento.
	 *
	 * @param string $titulo Título.
	 */
	public function titulo( $titulo ) {
		$this->text( $titulo, 14, 30, 'B' );
	}

	/**
	 * Tabla de datos.
	 *
	 * @param array $headers Encabezados.
	 * @param array $rows    Filas.
	 */
	public function tabla( $headers, $rows ) {
		$col_w = ( self::W - 80 ) / count( $headers );
		$col_w = min( $col_w, 110 );
		$x0 = 40;
		$this->font( 10, 'B' );
		$this->row_cells( $x0, $headers, $col_w );
		$this->line( 30, $this->y - 4, self::W - 30, $this->y - 4 );
		$this->font( 9, '' );
		foreach ( $rows as $row ) {
			$this->row_cells( $x0, $row, $col_w );
		}
	}

	/**
	 * Dibuja fila de celdas.
	 *
	 * @param float $x0     X inicial.
	 * @param array $values Valores.
	 * @param float $col_w  Ancho columna.
	 */
	private function row_cells( $x0, $values, $col_w ) {
		$x = $x0;
		foreach ( $values as $v ) {
			$this->text( (string) $v, 9, $x, $this->y );
			$x += $col_w;
		}
		$this->y += 16;
		if ( $this->y > self::H - 60 ) {
			$this->page_break();
		}
	}

	/**
	 * Salto de página.
	 */
	private function page_break() {
		$this->content .= "endstream\nendobj\n";
		$this->page++;
		$this->y = 40;
	}

	/**
	 * Configura fuente.
	 *
	 * @param int    $size Tamaño.
	 * @param string $style Estilo.
	 */
	private function font( $size, $style ) {
		$this->fontsize = $size;
		$this->fontstyle = $style;
	}

	/**
	 * Agrega texto al contenido.
	 *
	 * @param string $text Texto.
	 * @param int    $size Tamaño.
	 * @param float  $x    X.
	 * @param float  $y    Y.
	 */
	private function text( $text, $size, $x, $y ) {
		$text = $this->esc( $text );
		$this->content .= sprintf( "BT /F1 %d Tf %d TL %g %g Td (%s) Tj ET\n", $size, 14, $x, $this->H - $y, $text );
	}

	/**
	 * Línea horizontal.
	 *
	 * @param float $x1 X1.
	 * @param float $y1 Y1.
	 * @param float $x2 X2.
	 * @param float $y2 Y2.
	 */
	private function line( $x1, $y1, $x2, $y2 ) {
		$this->content .= sprintf( "%g %g m %g %g l S\n", $x1, $this->H - $y1, $x2, $this->H - $y2 );
	}

	/**
	 * Escapa texto PDF.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	private function esc( $text ) {
		$text = mb_convert_encoding( $text, 'ISO-8859-1', 'UTF-8' );
		$text = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
		return $text;
	}

	/**
	 * Ensambla y emite el PDF.
	 */
	public function output() {
		$objects   = array();
		$objects[] = "<< /Type /Catalog /Pages 1 0 R >>";
		$objects[] = "<< /Type /Pages /Kids [2 0 R] /Count 1 >>";
		$objects[] = "<< /Type /Page /Parent 1 0 R /MediaBox [0 0 " . self::W . ' ' . self::H . "] /Contents 3 0 R /Resources << /Font << /F1 4 0 R >> >> >>";
		$objects[] = "<< /Length " . strlen( $this->content ) . " >>\nstream\n" . $this->content . "endstream";
		$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

		$pdf  = "%PDF-1.4\n";
		$off  = array();
		foreach ( $objects as $i => $obj ) {
			$off[] = strlen( $pdf );
			$pdf  .= ( $i + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$xref_pos = strlen( $pdf );
		$count    = count( $objects ) + 1;
		$pdf     .= "xref\n0 " . $count . "\n0000000000 65535 f \n";
		foreach ( $off as $o ) {
			$pdf .= sprintf( "%010d 00000 n \n", $o );
		}
		$pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xref_pos . "\n%%EOF";
		echo $pdf;
	}
}