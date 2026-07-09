<?php
/**
 * Template: Liste der vergangenen Rennen (Archiv), gruppiert nach Jahr.
 *
 * Vereine möchten möglichst alle je ausgetragenen Rennen sehen – deshalb wird
 * das Archiv nach Jahr unterteilt (neuestes Jahr zuerst). Der Kontext
 * "archive" sorgt in race-item.php dafür, dass „Nennung"/Status ausgeblendet
 * und stattdessen „Ergebnisse" angezeigt werden.
 *
 * @var RC_RCC_Race[] $races      Anzuzeigende Rennen (nach Datum absteigend).
 * @var string        $empty_text Text für den Leer-Zustand.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $races ) ) {
	printf( '<p class="rc-rcc__empty">%s</p>', esc_html( $empty_text ) );
	return;
}

$context = 'archive';

// Rennen nach Jahr gruppieren; Reihenfolge innerhalb eines Jahres bleibt
// erhalten (bereits absteigend sortiert).
$rc_by_year = array();
foreach ( $races as $race ) {
	$rc_by_year[ $race->year() ][] = $race;
}

// Jahre absteigend; unbekannte Daten ('' ) landen ans Ende.
krsort( $rc_by_year );
?>
<?php foreach ( $rc_by_year as $rc_year => $rc_year_races ) : ?>
	<?php if ( '' !== (string) $rc_year ) : ?>
		<h3 class="rc-rcc__year"><?php echo esc_html( (string) $rc_year ); ?></h3>
	<?php endif; ?>
	<ul class="rc-rcc__list rc-rcc__list--archive">
		<?php
		foreach ( $rc_year_races as $race ) {
			require RC_RCC_Shortcode::locate_template( 'race-item.php' );
		}
		?>
	</ul>
<?php endforeach; ?>
