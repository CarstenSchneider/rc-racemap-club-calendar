<?php
/**
 * Template: Darstellung eines einzelnen Rennens.
 *
 * @var RC_RCC_Race $race Das anzuzeigende Rennen.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $race ) || ! $race instanceof RC_RCC_Race ) {
	return;
}

// Kontext pro Rennen am Datum (nicht am Tab): vergangene Rennen zeigen
// „Ergebnisse"/„Zum Rennen", kommende „Nennung".
$rc_is_past = ! $race->is_upcoming();

// Primärer Aktionslink + Icon:
//   kommend         → „Nennung"    (CTA, hebt sich klar ab)
//   vergangen MyRCM → „Ergebnisse"
//   vergangen RCK   → „Zum Rennen"
$event_url = $race->links['registration'] ?? '';
if ( $rc_is_past ) {
	if ( $race->is_rck() ) {
		$primary_label = __( 'Zum Rennen', 'rc-racemap-club-calendar' );
		$primary_url   = $event_url;
		$primary_icon  = 'external';
	} else {
		$primary_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$primary_url   = ( '' !== $race->results_url ) ? $race->results_url : $event_url;
		$primary_icon  = 'results';
	}
	$primary_class = 'rc-rcc__link--primary';
} else {
	$primary_label = __( 'Nennung', 'rc-racemap-club-calendar' );
	$primary_url   = $event_url;
	$primary_icon  = 'registration';
	// Der Nennung-Button ist der Haupt-Call-to-Action.
	$primary_class = 'rc-rcc__link--cta';
}

// Weitere Links (Primärlink wird separat gerendert): Label + Icon.
$link_labels = array(
	'participants' => array( __( 'Teilnehmerliste', 'rc-racemap-club-calendar' ), 'users' ),
	'announcement' => array( __( 'Ausschreibung', 'rc-racemap-club-calendar' ), 'announcement' ),
	'regulations'  => array( __( 'Reglement', 'rc-racemap-club-calendar' ), 'regulations' ),
);

// Status-Badge (macht sichtbar, welche Rennen vorbei bzw. aktiv sind).
// Kurze, konsistente Labels aus dem Status-Enum – nicht aus dem freien
// Notiztext, damit das Badge immer knapp und einheitlich bleibt.
$badge_label = '';
$badge_mod   = '';
if ( $rc_is_past ) {
	$badge_label = __( 'Beendet', 'rc-racemap-club-calendar' );
	$badge_mod   = 'past';
} else {
	switch ( $race->registration_status ) {
		case 'open':
			$badge_label = __( 'Nennung geöffnet', 'rc-racemap-club-calendar' );
			$badge_mod   = 'open';
			break;
		case 'closed':
			$badge_label = __( 'Nennung geschlossen', 'rc-racemap-club-calendar' );
			$badge_mod   = 'closed';
			break;
		case 'upcoming':
			$badge_label = __( 'Nennung folgt', 'rc-racemap-club-calendar' );
			$badge_mod   = 'soon';
			break;
	}
}

$has_participants = ( null !== $race->participant_count );
$has_classes      = ! empty( $race->classes );
?>
<li class="rc-rcc__item rc-rcc__item--<?php echo $rc_is_past ? 'past' : 'upcoming'; ?>">
	<div class="rc-rcc__top">
		<span class="rc-rcc__date">
			<?php if ( null !== $race->timestamp ) : ?>
				<time
					datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $race->timestamp ) ); ?>"
					title="<?php echo esc_attr( $race->formatted_date() ); ?>"
				><?php echo esc_html( $race->date_compact() ); ?></time>
			<?php else : ?>
				<?php echo esc_html( $race->date_compact() ); ?>
			<?php endif; ?>
		</span>

		<?php if ( '' !== $badge_label ) : ?>
			<span class="rc-rcc__badge rc-rcc__badge--<?php echo esc_attr( $badge_mod ); ?>"><?php echo esc_html( $badge_label ); ?></span>
		<?php endif; ?>
	</div>

	<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

	<?php if ( $has_participants || $has_classes ) : ?>
		<div class="rc-rcc__sub">
			<?php if ( $has_participants ) : ?>
				<span
					class="rc-rcc__participants"
					title="<?php echo esc_attr( sprintf( /* translators: %d: Anzahl der Teilnehmer. */ _n( '%d Teilnehmer', '%d Teilnehmer', $race->participant_count, 'rc-racemap-club-calendar' ), (int) $race->participant_count ) ); ?>"
				>
					<?php echo RC_RCC_Shortcode::icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
					<?php echo esc_html( (string) (int) $race->participant_count ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $has_participants && $has_classes ) : ?>
				<span class="rc-rcc__sub-sep" aria-hidden="true">·</span>
			<?php endif; ?>

			<?php if ( $has_classes ) : ?>
				<span class="rc-rcc__classes">
					<?php foreach ( $race->classes as $class ) : ?>
						<span class="rc-rcc__class"><?php echo esc_html( $class['name'] ); ?><?php if ( null !== $class['entries'] ) : ?><span class="rc-rcc__class-entries"><?php echo esc_html( (string) $class['entries'] ); ?></span><?php endif; ?></span>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $race->has_links() ) : ?>
		<div class="rc-rcc__links">
				<?php if ( '' !== $primary_url ) : ?>
					<a
						class="rc-rcc__link <?php echo esc_attr( $primary_class ); ?>"
						href="<?php echo esc_url( $primary_url ); ?>"
						rel="noopener noreferrer"
						target="_blank"
					>
						<?php echo RC_RCC_Shortcode::icon( $primary_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
						<span><?php echo esc_html( $primary_label ); ?></span>
					</a>
				<?php endif; ?>

				<?php foreach ( $link_labels as $key => $meta ) : ?>
					<?php if ( ! empty( $race->links[ $key ] ) ) : ?>
						<a
							class="rc-rcc__link rc-rcc__link--<?php echo esc_attr( $key ); ?>"
							href="<?php echo esc_url( $race->links[ $key ] ); ?>"
							rel="noopener noreferrer"
							target="_blank"
						>
							<?php echo RC_RCC_Shortcode::icon( $meta[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
							<span><?php echo esc_html( $meta[0] ); ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>

				<?php foreach ( $race->extra_links as $doc ) : ?>
					<a
						class="rc-rcc__link rc-rcc__link--document"
						href="<?php echo esc_url( $doc['url'] ); ?>"
						rel="noopener noreferrer"
						target="_blank"
					>
						<?php echo RC_RCC_Shortcode::icon( 'document' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
						<span><?php echo esc_html( $doc['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
</li>
