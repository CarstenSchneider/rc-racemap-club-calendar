<?php
/**
 * Template-Partial: Jahres-Navigation + Jahres-Panels innerhalb eines Tabs.
 *
 * Zeigt eine Pill-Navigation über die Jahre und pro Jahr ein Panel; das erste
 * Jahr (in der übergebenen Reihenfolge) ist aktiv. Umschalten per JS ohne
 * Reload. Bei nur einem Jahr entfällt die Navigation.
 *
 * @var array<int, RC_RCC_Race[]> $groups      Jahr => Rennen (in Anzeigereihenfolge).
 * @var string                    $empty_text  Text für den Leer-Zustand.
 * @var string                    $group_scope Eindeutiger Bereich ('current'/'archive').
 * @var string                    $uid         DOM-ID der Kalender-Instanz.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $groups ) ) {
	printf( '<p class="rc-rcc__empty">%s</p>', esc_html( $empty_text ) );
	return;
}

$rc_years       = array_keys( $groups );
$rc_active_year = (string) $rc_years[0];
$rc_base        = $uid . '-' . $group_scope;
?>
<?php if ( count( $rc_years ) > 1 ) : ?>
	<div class="rc-rcc__years" role="tablist" aria-label="<?php echo esc_attr__( 'Jahr wählen', 'rc-racemap-club-calendar' ); ?>">
		<?php foreach ( $rc_years as $rc_year ) : ?>
			<?php $rc_is_active = ( (string) $rc_year === $rc_active_year ); ?>
			<button
				type="button"
				class="rc-rcc__year-btn<?php echo $rc_is_active ? ' is-active' : ''; ?>"
				role="tab"
				id="<?php echo esc_attr( $rc_base . '-yeartab-' . $rc_year ); ?>"
				aria-controls="<?php echo esc_attr( $rc_base . '-year-' . $rc_year ); ?>"
				aria-selected="<?php echo $rc_is_active ? 'true' : 'false'; ?>"
				<?php echo $rc_is_active ? '' : 'tabindex="-1"'; ?>
				data-rc-rcc-year="<?php echo esc_attr( (string) $rc_year ); ?>"
			>
				<?php echo esc_html( (string) $rc_year ); ?>
			</button>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php foreach ( $groups as $rc_year => $rc_year_races ) : ?>
	<?php $rc_is_active = ( (string) $rc_year === $rc_active_year ); ?>
	<div
		class="rc-rcc__year-panel<?php echo $rc_is_active ? ' is-active' : ''; ?>"
		role="tabpanel"
		id="<?php echo esc_attr( $rc_base . '-year-' . $rc_year ); ?>"
		aria-labelledby="<?php echo esc_attr( $rc_base . '-yeartab-' . $rc_year ); ?>"
		data-rc-rcc-year-panel="<?php echo esc_attr( (string) $rc_year ); ?>"
		<?php echo $rc_is_active ? '' : 'hidden'; ?>
	>
		<ul class="rc-rcc__list">
			<?php
			foreach ( $rc_year_races as $race ) {
				require RC_RCC_Shortcode::locate_template( 'race-item.php' );
			}
			?>
		</ul>
	</div>
<?php endforeach; ?>
