<?php
/**
 * Template: Liste der vergangenen Rennen (Archiv).
 *
 * Struktur identisch zu upcoming.php, aber als eigenes Template angelegt,
 * damit das Archiv später eigenständig erweitert werden kann (Ergebnisse,
 * Rennberichte …), ohne die Ansicht der kommenden Rennen zu berühren.
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
<ul class="rc-rcc__list rc-rcc__list--archive">
	<?php
	foreach ( $races as $race ) {
		require RC_RCC_Shortcode::locate_template( 'race-item.php' );
	}
	?>
</ul>
