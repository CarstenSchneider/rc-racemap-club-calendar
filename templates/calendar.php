<?php
/**
 * Template: Kalender-Wrapper mit zwei Tabs und Jahres-Navigation.
 *
 * Zwei Haupt-Tabs ("Aktuelle Termine" / "Archiv"); innerhalb jedes Tabs eine
 * Jahres-Navigation (siehe year-groups.php). Alles serverseitig gerendert,
 * Umschalten per JS ohne Reload.
 *
 * Vom Shortcode bereitgestellte Variablen:
 *
 * @var string                     $uid            Eindeutige DOM-ID dieser Instanz.
 * @var array<int, RC_RCC_Race[]>  $current_groups Aktuelle/künftige Jahre → Rennen.
 * @var array<int, RC_RCC_Race[]>  $archive_groups Frühere Jahre → Rennen.
 * @var string                     $logo_url        URL des Logos.
 * @var string                     $plugin_url      Link zur Plugin-Seite.
 * @var int                        $last_fetch      Unix-Zeit des letzten Abrufs (0 = nie).
 * @var string                     $accent_class    Optionale Akzent-Klasse.
 * @var string                     $accent_style    Optionaler Inline-Style (Akzentfarbe).
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="rc-rcc<?php echo esc_attr( $accent_class ); ?>" id="<?php echo esc_attr( $uid ); ?>" data-rc-rcc<?php echo ( '' !== $accent_style ) ? ' style="' . esc_attr( $accent_style ) . '"' : ''; ?>>
	<div class="rc-rcc__tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Rennkalender', 'rc-racemap-club-calendar' ); ?>">
		<button
			type="button"
			class="rc-rcc__tab is-active"
			role="tab"
			id="<?php echo esc_attr( $uid ); ?>-tab-current"
			aria-controls="<?php echo esc_attr( $uid ); ?>-panel-current"
			aria-selected="true"
			data-rc-rcc-tab="current"
		>
			<?php echo esc_html__( 'Aktuelle Rennen', 'rc-racemap-club-calendar' ); ?>
		</button>
		<button
			type="button"
			class="rc-rcc__tab"
			role="tab"
			id="<?php echo esc_attr( $uid ); ?>-tab-archive"
			aria-controls="<?php echo esc_attr( $uid ); ?>-panel-archive"
			aria-selected="false"
			tabindex="-1"
			data-rc-rcc-tab="archive"
		>
			<?php echo esc_html__( 'Vergangene Rennen', 'rc-racemap-club-calendar' ); ?>
		</button>
	</div>

	<div
		class="rc-rcc__panel is-active"
		role="tabpanel"
		id="<?php echo esc_attr( $uid ); ?>-panel-current"
		aria-labelledby="<?php echo esc_attr( $uid ); ?>-tab-current"
		data-rc-rcc-panel="current"
	>
		<?php
		$groups      = $current_groups;
		$group_scope = 'current';
		$empty_text  = __( 'Aktuell sind keine Rennen vorhanden.', 'rc-racemap-club-calendar' );
		require RC_RCC_Shortcode::locate_template( 'year-groups.php' );
		?>
	</div>

	<div
		class="rc-rcc__panel"
		role="tabpanel"
		id="<?php echo esc_attr( $uid ); ?>-panel-archive"
		aria-labelledby="<?php echo esc_attr( $uid ); ?>-tab-archive"
		data-rc-rcc-panel="archive"
		hidden
	>
		<?php
		$groups      = $archive_groups;
		$group_scope = 'archive';
		$empty_text  = __( 'Es sind noch keine vergangenen Rennen vorhanden.', 'rc-racemap-club-calendar' );
		require RC_RCC_Shortcode::locate_template( 'year-groups.php' );
		?>
	</div>

	<div class="rc-rcc__footer">
		<?php if ( $last_fetch > 0 ) : ?>
			<span class="rc-rcc__updated">
				<?php
				printf(
					/* translators: %s: Zeitpunkt des letzten Datenabrufs. */
					esc_html__( 'Stand: %s', 'rc-racemap-club-calendar' ),
					esc_html(
						wp_date(
							/* translators: PHP-Datumsformat für die „Stand:"-Anzeige – Uhrzeit und kurzes Datum. */
							_x( 'H:i\\h, d.m.y', 'Zeitpunkt-Format der Stand-Anzeige', 'rc-racemap-club-calendar' ),
							$last_fetch
						)
					)
				);
				?>
			</span>
		<?php endif; ?>

		<span class="rc-rcc__credit">
			<?php
			printf(
				/* translators: %s: „RC RaceMap" als Link zur Karte. */
				esc_html__( 'MyRCM und RCK Daten via %s WordPress PlugIn', 'rc-racemap-club-calendar' ),
				'<a class="rc-rcc__credit-link" href="' . esc_url( $plugin_url ) . '" rel="noopener noreferrer" target="_blank">RC RaceMap</a>'
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Vorlage escaped, Link separat escaped.
			?>
		</span>
		<a href="<?php echo esc_url( $plugin_url ); ?>" class="rc-rcc__brand" rel="noopener noreferrer" target="_blank" aria-label="<?php echo esc_attr__( 'RC RaceMap', 'rc-racemap-club-calendar' ); ?>">
			<?php echo RC_RCC_Shortcode::brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
		</a>
	</div>
</div>
