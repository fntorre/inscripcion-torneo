<?php
/**
 * Exportador (dominio puro): genera CSV, Excel o PDF en memoria.
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Genera el contenido de un listado sin depender de WordPress.
 */
final class Exportador {

	public const CSV = 'csv';
	public const XLS = 'xls';
	public const PDF = 'pdf';

	/**
	 * Columnas (clave => etiqueta).
	 *
	 * @return array
	 */
	public static function columnas() {
		return array(
			'equipo'       => 'Equipo',
			'estado'       => 'Estado',
			'delegado'     => 'Delegado',
			'delegado_dni' => 'DNI Delegado',
			'delegado_tel' => 'Teléfono',
			'jugador'      => 'Jugador',
			'jugador_dni'  => 'DNI Jugador',
			'archivo'      => 'Archivo DNI',
		);
	}

	/**
	 * Genera el archivo y devuelve cabeceras HTTP + contenido.
	 *
	 * @param string $formato Formato.
	 * @param array  $rows    Filas asociativas.
	 * @return array{headers:array,content:string}|null
	 */
	public static function generar( $formato, $rows ) {
		switch ( $formato ) {
			case self::XLS:
				return self::xls( $rows );
			case self::PDF:
				return self::pdf( $rows );
			default:
				return self::csv( $rows );
		}
	}

	/**
	 * CSV con BOM para Excel.
	 *
	 * @param array $rows Filas.
	 * @return array
	 */
	private static function csv( $rows ) {
		$col   = self::columnas();
		$f     = fopen( 'php://temp', 'r+' );
		fwrite( $f, "\xEF\xBB\xBF" );
		fputcsv( $f, array_values( $col ) );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( array_keys( $col ) as $key ) {
				$line[] = self::limpiar( isset( $row[ $key ] ) ? $row[ $key ] : '' );
			}
			fputcsv( $f, $line );
		}
		rewind( $f );
		$content = stream_get_contents( $f );
		fclose( $f );
		return array(
			'headers' => array(
				'Content-Type'        => 'text/csv; charset=UTF-8',
				'Content-Disposition' => 'attachment; filename="listado.csv"',
			),
			'content' => $content,
		);
	}

	/**
	 * Excel (SpreadsheetML).
	 *
	 * @param array $rows Filas.
	 * @return array
	 */
	private static function xls( $rows ) {
		$col  = self::columnas();
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Listado"><Table>';
		$xml .= '<Row>';
		foreach ( $col as $label ) {
			$xml .= '<Cell><Data ss:Type="String">' . self::esc( $label ) . '</Data></Cell>';
		}
		$xml .= '</Row>';
		foreach ( $rows as $r ) {
			$xml .= '<Row>';
			foreach ( array_keys( $col ) as $key ) {
				$val = self::limpiar( isset( $r[ $key ] ) ? $r[ $key ] : '' );
				$xml .= '<Cell><Data ss:Type="String">' . self::esc( $val ) . '</Data></Cell>';
			}
			$xml .= '</Row>';
		}
		$xml .= '</Table></Worksheet></Workbook>';
		return array(
			'headers' => array(
				'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
				'Content-Disposition' => 'attachment; filename="listado.xls"',
			),
			'content' => $xml,
		);
	}

	/**
	 * PDF.
	 *
	 * @param array $rows Filas.
	 * @return array
	 */
	private static function pdf( $rows ) {
		$pdf  = new PdfMin();
		$pdf->titulo( 'Listado de jugadores' );
		$pdf->tabla( array_values( self::columnas() ), self::filas( $rows ) );
		return array(
			'headers' => array(
				'Content-Type'        => 'application/pdf',
				'Content-Disposition' => 'attachment; filename="listado.pdf"',
			),
			'content' => $pdf->generar(),
		);
	}

	/**
	 * Convierte filas asociativas a arreglos indexados según columnas.
	 *
	 * @param array $rows Filas.
	 * @return array
	 */
	private static function filas( $rows ) {
		$col   = array_keys( self::columnas() );
		$out   = array();
		foreach ( $rows as $r ) {
			$line = array();
			foreach ( $col as $key ) {
				$line[] = self::limpiar( isset( $r[ $key ] ) ? $r[ $key ] : '' );
			}
			$out[] = $line;
		}
		return $out;
	}

	/**
	 * Normaliza un valor para exportación.
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	private static function limpiar( $value ) {
		return trim( str_replace( array( "\r", "\n", "\t" ), ' ', (string) $value ) );
	}

	/**
	 * Escapa XML.
	 *
	 * @param string $value Valor.
	 * @return string
	 */
	private static function esc( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}