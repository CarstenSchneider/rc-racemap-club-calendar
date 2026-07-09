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
// „Ergebnisse"/„Zum Rennen" und keinen Status, kommende „Nennung" + Status.
$rc_is_past = ! $race->is_upcoming();

// Primärer Aktionslink:
//   kommend         → „Nennung"    (Event-/Buchungsseite)
//   vergangen MyRCM → „Ergebnisse" (MyRCM-Ergebnisansicht)
//   vergangen RCK   → „Zum Rennen" (Ergebnisse bei RCK unklar)
$event_url = $race->links['registration'] ?? '';
if ( $rc_is_past ) {
	if ( $race->is_rck() ) {
		$primary_label = __( 'Zum Rennen', 'rc-racemap-club-calendar' );
		$primary_url   = $event_url;
	} else {
		$primary_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$primary_url   = ( '' !== $race->results_url ) ? $race->results_url : $event_url;
	}
} else {
	$primary_label = __( 'Nennung', 'rc-racemap-club-calendar' );
	$primary_url   = $event_url;
}

// Weitere Links (Primärlink wird separat gerendert).
$link_labels = array(
	'participants' => __( 'Teilnehmerliste', 'rc-racemap-club-calendar' ),
	'announcement' => __( 'Ausschreibung', 'rc-racemap-club-calendar' ),
	'regulations'  => __( 'Reglement', 'rc-racemap-club-calendar' ),
);
?>
<li class="rc-rcc__item">
	<div class="rc-rcc__date">
		<?php if ( null !== $race->timestamp ) : ?>
			<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $race->timestamp ) ); ?>">
				<?php echo esc_html( $race->formatted_date() ); ?>
			</time>
		<?php else : ?>
			<span><?php echo esc_html( $race->formatted_date() ); ?></span>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__body">
		<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

		<div class="rc-rcc__meta">
			<?php if ( '' !== $race->organizer && ( '' === $race->track || 0 !== strcasecmp( trim( $race->organizer ), trim( $race->track ) ) ) ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__organizer">
					<?php echo esc_html( $race->organizer ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '' !== $race->track ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__track">
					<?php
					$track_text = $race->track;
					if ( '' !== $race->location && false === mb_stripos( $race->track, $race->location ) ) {
						/* translators: 1: venue name, 2: town/location. */
						$track_text = sprintf( __( '%1$s, %2$s', 'rc-racemap-club-calendar' ), $race->track, $race->location );
					}
					echo esc_html( $track_text );
					?>
				</span>
			<?php elseif ( '' !== $race->location ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__track">
					<?php echo esc_html( $race->location ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '' !== $race->status && ! $rc_is_past ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__status">
					<?php echo esc_html( $race->status ); ?>
				</span>
			<?php endif; ?>

			<?php if ( null !== $race->participant_count ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__participants">
					<?php
					printf(
						/* translators: %d: Anzahl der Teilnehmer. */
						esc_html( _n( '%d Teilnehmer', '%d Teilnehmer', $race->participant_count, 'rc-racemap-club-calendar' ) ),
						(int) $race->participant_count
					);
					?>
				</span>
			<?php endif; ?>

			<?php if ( ! empty( $race->series ) ) : ?>
				<?php foreach ( $race->series as $series_name ) : ?>
					<span class="rc-rcc__meta-item rc-rcc__series">
						<?php echo esc_html( $series_name ); ?>
					</span>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $race->classes ) ) : ?>
			<ul class="rc-rcc__classes">
				<?php foreach ( $race->classes as $class ) : ?>
					<li class="rc-rcc__class">
						<?php echo esc_html( $class['name'] ); ?>
						<?php if ( null !== $class['entries'] ) : ?>
							<span class="rc-rcc__class-entries"><?php echo esc_html( (string) $class['entries'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $race->has_links() ) : ?>
			<div class="rc-rcc__links">
				<?php if ( '' !== $primary_url ) : ?>
					<a
						class="rc-rcc__link rc-rcc__link--primary"
						href="<?php echo esc_url( $primary_url ); ?>"
						rel="noopener noreferrer"
						target="_blank"
					>
						<?php echo esc_html( $primary_label ); ?>
					</a>
				<?php endif; ?>
				<?php foreach ( $link_labels as $key => $label ) : ?>
					<?php if ( ! empty( $race->links[ $key ] ) ) : ?>
						<a
							class="rc-rcc__link rc-rcc__link--<?php echo esc_attr( $key ); ?>"
							href="<?php echo esc_url( $race->links[ $key ] ); ?>"
							rel="noopener noreferrer"
							target="_blank"
						>
							<?php echo esc_html( $label ); ?>
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
						<?php echo esc_html( $doc['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</li>
