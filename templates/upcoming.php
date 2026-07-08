<?php
/**
 * Template: Liste der kommenden Rennen.
 *
 * Bewusst schlank gehalten (nur Liste + Leer-Zustand), damit "Kommende Rennen"
 * und "Archiv" später unabhängig voneinander erweitert werden können
 * (z. B. Ergebnisse im Archiv, Countdown bei kommenden Rennen).
 *
 * @var RC_RCC_Race[] $races      Anzuzeigende Rennen.
 * @var string        $empty_text Text für den Leer-Zustand.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $races ) ) {
	printf( '<p class="rc-rcc__empty">%s</p>', esc_html( $empty_text ) );
	return;
}
?>
<ul class="rc-rcc__list">
	<?php
	foreach ( $races as $race ) {
		require RC_RCC_Shortcode::locate_template( 'race-item.php' );
	}
	?>
</ul>
