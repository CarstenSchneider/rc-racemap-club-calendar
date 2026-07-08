<?php
/**
 * Template: Kalender-Wrapper mit zwei Tabs.
 *
 * Beide Tabs werden serverseitig gerendert; das Umschalten passiert per JS
 * ohne Seitenreload. Ohne JavaScript bleiben beide Panels sichtbar (Fallback).
 *
 * Vom Shortcode bereitgestellte Variablen:
 *
 * @var string          $uid       Eindeutige DOM-ID dieser Instanz.
 * @var RC_RCC_Race[]   $upcoming  Kommende Rennen.
 * @var RC_RCC_Race[]   $archived  Vergangene Rennen (Archiv).
 * @var bool            $show_logo RC-RaceMap-Logo anzeigen.
 * @var string          $logo_url  URL des Logos.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="rc-rcc" id="<?php echo esc_attr( $uid ); ?>" data-rc-rcc>
	<div class="rc-rcc__tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Rennkalender', 'rc-racemap-club-calendar' ); ?>">
		<button
			type="button"
			class="rc-rcc__tab is-active"
			role="tab"
			id="<?php echo esc_attr( $uid ); ?>-tab-upcoming"
			aria-controls="<?php echo esc_attr( $uid ); ?>-panel-upcoming"
			aria-selected="true"
			data-rc-rcc-tab="upcoming"
		>
			<?php echo esc_html__( 'Kommende Rennen', 'rc-racemap-club-calendar' ); ?>
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
			<?php echo esc_html__( 'Archiv', 'rc-racemap-club-calendar' ); ?>
		</button>
	</div>

	<div
		class="rc-rcc__panel is-active"
		role="tabpanel"
		id="<?php echo esc_attr( $uid ); ?>-panel-upcoming"
		aria-labelledby="<?php echo esc_attr( $uid ); ?>-tab-upcoming"
		data-rc-rcc-panel="upcoming"
	>
		<?php
		$races      = $upcoming;
		$empty_text = __( 'Aktuell sind keine kommenden Rennen geplant.', 'rc-racemap-club-calendar' );
		require RC_RCC_Shortcode::locate_template( 'upcoming.php' );
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
		$races      = $archived;
		$empty_text = __( 'Es sind noch keine vergangenen Rennen vorhanden.', 'rc-racemap-club-calendar' );
		require RC_RCC_Shortcode::locate_template( 'archive.php' );
		?>
	</div>

	<?php if ( $show_logo ) : ?>
		<div class="rc-rcc__footer">
			<a href="https://rc-racemap.com/" class="rc-rcc__brand" rel="noopener noreferrer" target="_blank">
				<img
					src="<?php echo esc_url( $logo_url ); ?>"
					alt="<?php echo esc_attr__( 'RC RaceMap', 'rc-racemap-club-calendar' ); ?>"
					class="rc-rcc__logo"
					loading="lazy"
					width="120"
					height="24"
				/>
			</a>
		</div>
	<?php endif; ?>
</div>
