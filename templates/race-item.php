<?php
/**
 * Template: Darstellung eines einzelnen Rennens.
 *
 * Ruhiges 3-Spalten-Layout mit klarer Hierarchie:
 *   Datum · Info (Titel › Klassen › Links) · Aktion.
 *
 * Ebenen: Titel = Überschrift (groß). Klassen = leise Textzeile (nur lesen).
 * Links (Teilnehmerliste, Ausschreibung, Reglement) = eigene Zeile in der
 * Akzentfarbe, klar als klickbar erkennbar. Rechts genau ein Aktions-Button.
 *
 * @var RC_RCC_Race $race Das anzuzeigende Rennen.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $race ) || ! $race instanceof RC_RCC_Race ) {
	return;
}

$rc_is_past = ! $race->is_upcoming();

// Primärer Aktions-Button rechts.
$event_url = $race->links['registration'] ?? '';
if ( $rc_is_past ) {
	if ( $race->is_rck() ) {
		$cta_label = __( 'Zum Rennen', 'rc-racemap-club-calendar' );
		$cta_url   = $event_url;
	} else {
		$cta_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$cta_url   = ( '' !== $race->results_url ) ? $race->results_url : $event_url;
	}
} else {
	$cta_label = __( 'Nennung', 'rc-racemap-club-calendar' );
	$cta_url   = $event_url;
}

// Dokument-Links (Text-Links in der Link-Zeile).
$documents = array();
if ( ! empty( $race->links['announcement'] ) ) {
	$documents[] = array( __( 'Ausschreibung', 'rc-racemap-club-calendar' ), $race->links['announcement'] );
}
if ( ! empty( $race->links['regulations'] ) ) {
	$documents[] = array( __( 'Reglement', 'rc-racemap-club-calendar' ), $race->links['regulations'] );
}
foreach ( $race->extra_links as $doc ) {
	$documents[] = array( $doc['label'], $doc['url'] );
}

$participants_url = $race->links['participants'] ?? '';
$has_participants = ( null !== $race->participant_count );
$has_links        = $has_participants || ! empty( $documents );
?>
<li class="rc-rcc__item rc-rcc__item--<?php echo $rc_is_past ? 'past' : 'upcoming'; ?>">
	<div class="rc-rcc__date">
		<?php if ( null !== $race->timestamp ) : ?>
			<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $race->timestamp ) ); ?>" title="<?php echo esc_attr( $race->formatted_date() ); ?>"><?php echo esc_html( $race->date_compact() ); ?></time>
		<?php else : ?>
			<?php echo esc_html( $race->date_compact() ); ?>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__info">
		<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

		<?php if ( ! empty( $race->classes ) ) : ?>
			<p class="rc-rcc__classes">
				<?php foreach ( $race->classes as $class ) : ?>
					<span class="rc-rcc__class"><?php echo esc_html( $class['name'] ); ?><?php if ( null !== $class['entries'] ) : ?><span class="rc-rcc__class-entries"><?php echo esc_html( (string) $class['entries'] ); ?></span><?php endif; ?></span>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php if ( $has_links ) : ?>
			<p class="rc-rcc__links">
				<?php if ( $has_participants ) : ?>
					<?php
					$ppl_label = sprintf(
						/* translators: %d: Anzahl der Teilnehmer. */
						_n( '%d Teilnehmer', '%d Teilnehmer', $race->participant_count, 'rc-racemap-club-calendar' ),
						(int) $race->participant_count
					);
					$ppl_icon  = RC_RCC_Shortcode::icon( 'users' );
					$ppl_count = (string) (int) $race->participant_count;
					?>
					<?php if ( '' !== $participants_url ) : ?>
						<a class="rc-rcc__link rc-rcc__ppl" href="<?php echo esc_url( $participants_url ); ?>" title="<?php echo esc_attr( $ppl_label ); ?>" rel="noopener noreferrer" target="_blank">
							<?php echo $ppl_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
							<span class="rc-rcc__link-label"><?php echo esc_html( $ppl_count ); ?></span>
						</a>
					<?php else : ?>
						<span class="rc-rcc__ppl rc-rcc__ppl--static" title="<?php echo esc_attr( $ppl_label ); ?>">
							<?php echo $ppl_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?>
							<span><?php echo esc_html( $ppl_count ); ?></span>
						</span>
					<?php endif; ?>
				<?php endif; ?>

				<?php foreach ( $documents as $doc ) : ?>
					<a class="rc-rcc__link rc-rcc__doc" href="<?php echo esc_url( $doc[1] ); ?>" rel="noopener noreferrer" target="_blank"><span class="rc-rcc__link-label"><?php echo esc_html( $doc[0] ); ?></span></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__action">
		<?php if ( '' !== $cta_url ) : ?>
			<a class="rc-rcc__cta rc-rcc__cta--<?php echo $rc_is_past ? 'past' : 'upcoming'; ?>" href="<?php echo esc_url( $cta_url ); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html( $cta_label ); ?></a>
		<?php endif; ?>
	</div>
</li>
