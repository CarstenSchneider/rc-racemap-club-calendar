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
 * @var bool                       $show_logo      RC-RaceMap-Logo anzeigen.
 * @var string                     $logo_url       URL des Logos.
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
			id="<?php echo esc_attr( $uid ); ?>-tab-current"
			aria-controls="<?php echo esc_attr( $uid ); ?>-panel-current"
			aria-selected="true"
			data-rc-rcc-tab="current"
		>
			<?php echo esc_html__( 'Aktuelle Termine', 'rc-racemap-club-calendar' ); ?>
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
		id="<?php echo esc_attr( $uid ); ?>-panel-current"
		aria-labelledby="<?php echo esc_attr( $uid ); ?>-tab-current"
		data-rc-rcc-panel="current"
	>
		<?php
		$groups      = $current_groups;
		$group_scope = 'current';
		$empty_text  = __( 'Aktuell sind keine Termine vorhanden.', 'rc-racemap-club-calendar' );
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
