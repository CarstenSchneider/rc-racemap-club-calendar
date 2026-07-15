<?php
/**
 * Template: ein Rennen als Karte.
 *
 * Neutrale helle Karte. Datum (klein) › Titelzeile (Titel links, Teilnehmerzahl
 * rechts, gleiche Typo) › Aktions-/Dokumentzeile › Rennklassen als Tags (ab N
 * eingeklappt via JS „+N weitere"). Farbe nur als Akzent (Option).
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

// Primäre Aktion (mit ↗): Nennung / Ergebnisse / Zum Rennen.
$event_url = $race->links['registration'] ?? '';
if ( $rc_is_past ) {
	if ( $race->is_rck() ) {
		$cta_label = __( 'Zum Rennen', 'rc-racemap-club-calendar' );
		$cta_url   = $event_url;
	} else {
		$cta_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$cta_url   = ( '' !== $race->results_url ) ? $race->results_url : $event_url;
	}
	$cta_open = false;
} else {
	$cta_label = __( 'Nennung', 'rc-racemap-club-calendar' );
	$cta_url   = $event_url;
	$cta_open  = $race->is_registration_open();
}

// Dokument-Links.
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

$arrow = RC_RCC_Shortcode::icon( 'arrow' );
?>
<li class="rc-rcc__item rc-rcc__item--<?php echo $rc_is_past ? 'past' : 'upcoming'; ?>">
	<div class="rc-rcc__date">
		<?php if ( null !== $race->timestamp ) : ?>
			<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $race->timestamp ) ); ?>" title="<?php echo esc_attr( $race->formatted_date() ); ?>"><?php echo esc_html( $race->date_compact() ); ?></time>
		<?php else : ?>
			<?php echo esc_html( $race->date_compact() ); ?>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__head">
		<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

		<?php if ( $has_participants ) : ?>
			<?php
			$ppl_label = sprintf(
				/* translators: %d: Anzahl der Teilnehmer. */
				_n( '%d Teilnehmer', '%d Teilnehmer', $race->participant_count, 'rc-racemap-club-calendar' ),
				(int) $race->participant_count
			);
			$ppl_inner = RC_RCC_Shortcode::icon( 'users' ) . '<span class="rc-rcc__ppl-count">' . esc_html( (string) (int) $race->participant_count ) . '</span>';
			?>
			<?php if ( '' !== $participants_url ) : ?>
				<a class="rc-rcc__ppl" href="<?php echo esc_url( $participants_url ); ?>" title="<?php echo esc_attr( $ppl_label ); ?>" rel="noopener noreferrer" target="_blank"><?php echo $ppl_inner . $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstante Inline-SVGs + escapte Zahl. ?></a>
			<?php else : ?>
				<span class="rc-rcc__ppl rc-rcc__ppl--static" title="<?php echo esc_attr( $ppl_label ); ?>"><?php echo $ppl_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstante Inline-SVGs + escapte Zahl. ?></span>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $cta_url || ! empty( $documents ) ) : ?>
		<div class="rc-rcc__meta">
			<?php if ( '' !== $cta_url ) : ?>
				<a class="rc-rcc__action<?php echo $cta_open ? ' rc-rcc__action--open' : ''; ?>" href="<?php echo esc_url( $cta_url ); ?>" rel="noopener noreferrer" target="_blank"><span class="rc-rcc__action-label"><?php echo esc_html( $cta_label ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?></a>
			<?php endif; ?>

			<?php foreach ( $documents as $doc ) : ?>
				<a class="rc-rcc__doc" href="<?php echo esc_url( $doc[1] ); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html( $doc[0] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $race->classes ) ) : ?>
		<ul class="rc-rcc__classes" data-rc-rcc-classes>
			<?php foreach ( $race->classes as $class ) : ?>
				<li class="rc-rcc__class"><?php echo esc_html( $class['name'] ); ?><?php if ( null !== $class['entries'] ) : ?> <span class="rc-rcc__class-entries"><?php echo esc_html( (string) $class['entries'] ); ?></span><?php endif; ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</li>
