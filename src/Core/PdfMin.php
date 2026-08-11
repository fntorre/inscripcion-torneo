<?php
/**
 * Generador PDF mínimo (dominio puro).
 *
 * @package InscripcionesFutbol
 */

namespace IF\Core;

/**
 * Genera un PDF simple de una tabla, devolviendo el contenido como string.
 */
final class PdfMin {

	/** @var string */
	private $content = '';

	/** @var float */
	private $y = 40;

	const W = 595.28;
	const H = 841.89;

	/**
	 * Título.
	 *
	 * @param string $titulo Título.
	 */
	public function titulo( $titulo ) {
		$this->texto( $titulo, 14, 30 );
	}

	/**
	 * Tabla de datos.
	 *
	 * @param array $headers Encabezados.
	 * @param array $rows    Filas.
	 */
	public function tabla( $headers, $rows ) {
		$col_w = ( self::W - 80 ) / max( 1, count( $headers ) );
		$col_w = min( $col_w, 110 );
		$x0    = 40;
		$this->fila( $x0, $headers, $col_w, 10, true );
		$this->linea( 30, $this->y - 4, self::W - 30, $this->y - 4 );
		foreach ( $rows as $row ) {
			$this->fila( $x0, $row, $col_w, 9, false );
		}
	}

	/**
	 * Dibuja una fila.
	 *
	 * @param float $x0     X inicial.
	 * @param array $values Valores.
	 * @param float $col_w  Ancho.
	 * @param int   $size   Tamaño de fuente.
	 * @param bool  $bold   Negrita.
	 */
	private function fila( $x0, $values, $col_w, $size, $bold ) {
		$x = $x0;
		foreach ( $values as $v ) {
			$this->texto( (string) $v, $size, $x, $bold );
			$x += $col_w;
		}
		$this->y += 16;
	}

	/**
	 * Texto.
	 *
	 * @param string $text Texto.
	 * @param int    $size Tamaño.
	 * @param float  $x    X.
	 * @param bool   $bold Negrita.
	 */
	private function texto( $text, $size, $x, $bold = false ) {
		$font = $bold ? 'F1' : 'F2';
		$this->content .= sprintf( "BT /%s %d Tf %g %g Td (%s) Tj ET\n", $font, $size, $x, self::H - $this->y, $this->esc( $text ) );
	}

	/**
	 * Línea horizontal.
	 *
	 * @param float $x1 X1.
	 * @param float $y1 Y1.
	 * @param float $x2 X2.
	 * @param float $y2 Y2.
	 */
	private function linea( $x1, $y1, $x2, $y2 ) {
		$this->content .= sprintf( "%g %g m %g %g l S\n", $x1, self::H - $y1, $x2, self::H - $y2 );
	}

	/**
	 * Escapa texto para PDF.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	private function esc( $text ) {
		$text = mb_convert_encoding( $text, 'ISO-8859-1', 'UTF-8' );
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
	}

	/**
	 * Ensambla el PDF.
	 *
	 * @return string
	 */
	public function generar() {
		$objects = array(
			"<< /Type /Catalog /Pages 1 0 R >>",
			"<< /Type /Pages /Kids [2 0 R] /Count 1 >>",
			"<< /Type /Page /Parent 1 0 R /MediaBox [0 0 " . self::W . ' ' . self::H . "] /Contents 3 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> >>",
			"<< /Length " . strlen( $this->content ) . " >>\nstream\n" . $this->content . "endstream",
			"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
			"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
		);

		$pdf  = "%PDF-1.4\n";
		$off  = array();
		foreach ( $objects as $i => $obj ) {
			$off[] = strlen( $pdf );
			$pdf  .= ( $i + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$n    = count( $objects ) + 1;
		$pdf .= "xref\n0 $n\n0000000000 65535 f \n";
		foreach ( $off as $o ) {
			$pdf .= sprintf( "%010d 00000 n \n", $o );
		}
		$pdf .= "trailer\n<< /Size $n /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
		return $pdf;
	}
}