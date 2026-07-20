<?php
/**
 * Template: ein Rennen als Tabellenzeile (5 Spalten).
 *
 *   1. Datum
 *   2. Rennen + Klassen-Pillen (alle, ohne Einklappen)
 *   3. Teilnehmer (Icon + Anzahl, verlinkt zur Teilnehmerliste)
 *   4. Dokumente (Ausschreibung, Reglement, PDFs …)
 *   5. Aktion: Ergebnisse (vorbei) / Nennung (offen) / Zum Rennen (MyRCM),
 *      alternativ der Hinweistext „Nennung ab …".
 *
 * Leere Zellen werden nicht ausgegeben: auf Mobil stehen die Zellen
 * untereinander, eine leere belegte sonst eine eigene Zeile samt Abstand.
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
$event_url  = $race->links['registration'] ?? '';

// Spalte 5 bestimmen: Button-Link oder Hinweistext.
$cta_url   = '';
$cta_label = '';
$cta_note  = '';

if ( $rc_is_past ) {
	if ( '' !== $race->results_url ) {
		// Gilt auch für zusammengeführte Events: der Event-Link zeigt auf RCK,
		// die Ergebnisse liegen auf MyRCM.
		$cta_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$cta_url   = $race->results_url;
	} elseif ( $race->is_rck() || $race->is_custom() ) {
		// Reine RCK-Rennen und eigene Termine haben keine Ergebnisseite – dann
		// auf das Event. Ergebnisse kann der Verein als Dokument hinterlegen.
		$cta_label = __( 'Zum Rennen', 'rc-racemap-club-calendar' );
		$cta_url   = $event_url;
	} else {
		$cta_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$cta_url   = $event_url;
	}
} elseif ( $race->is_registration_open() ) {
	$cta_label = __( 'Nennung', 'rc-racemap-club-calendar' );
	$cta_url   = $event_url;
} elseif ( null !== $race->registration_opens && $race->registration_opens > time() ) {
	// Nennung startet erst später → Hinweistext statt Button.
	$cta_note = sprintf(
		/* translators: %s: Datum, ab dem die Nennung möglich ist. */
		__( 'Nennung ab %s', 'rc-racemap-club-calendar' ),
		wp_date( (string) get_option( 'date_format' ), $race->registration_opens )
	);
} else {
	$cta_label = __( 'Zum Rennen', 'rc-racemap-club-calendar' );
	$cta_url   = $event_url;
}

// Dokumente.
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
	<div class="rc-rcc__cell rc-rcc__date">
		<?php if ( null !== $race->timestamp ) : ?>
			<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $race->timestamp ) ); ?>" title="<?php echo esc_attr( $race->formatted_date() ); ?>"><?php echo esc_html( $race->date_compact() ); ?></time>
		<?php else : ?>
			<?php echo esc_html( $race->date_compact() ); ?>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__cell rc-rcc__race">
		<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

		<?php if ( ! empty( $race->classes ) ) : ?>
			<ul class="rc-rcc__classes">
				<?php foreach ( $race->classes as $class ) : ?>
					<li class="rc-rcc__class"><?php echo esc_html( $class['name'] ); ?><?php if ( null !== $class['entries'] ) : ?> <span class="rc-rcc__class-entries">(<?php echo esc_html( (string) $class['entries'] ); ?>)</span><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php if ( $has_participants ) : ?>
		<?php
		$ppl_label = sprintf(
			/* translators: %d: Anzahl der Teilnehmer. */
			_n( '%d Teilnehmer', '%d Teilnehmer', $race->participant_count, 'rc-racemap-club-calendar' ),
			(int) $race->participant_count
		);
		$ppl_inner = RC_RCC_Shortcode::icon( 'users' ) . '<span class="rc-rcc__ppl-count">' . esc_html( (string) (int) $race->participant_count ) . '</span>';
		?>
		<div class="rc-rcc__cell rc-rcc__ppl">
			<?php if ( '' !== $participants_url ) : ?>
				<a class="rc-rcc__ppl-link" href="<?php echo esc_url( $participants_url ); ?>" title="<?php echo esc_attr( $ppl_label ); ?>" rel="noopener noreferrer" target="_blank"><?php echo $ppl_inner . $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstante Inline-SVGs + escapte Zahl. ?></a>
			<?php else : ?>
				<span class="rc-rcc__ppl-link rc-rcc__ppl-link--static" title="<?php echo esc_attr( $ppl_label ); ?>"><?php echo $ppl_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstante Inline-SVGs + escapte Zahl. ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $documents ) ) : ?>
		<div class="rc-rcc__cell rc-rcc__docs">
			<?php foreach ( $documents as $doc ) : ?>
				<a class="rc-rcc__doc" href="<?php echo esc_url( $doc[1] ); ?>" rel="noopener noreferrer" target="_blank"><span class="rc-rcc__doc-label"><?php echo esc_html( $doc[0] ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $cta_note || '' !== $cta_url ) : ?>
		<div class="rc-rcc__cell rc-rcc__action">
			<?php if ( '' !== $cta_note ) : ?>
				<span class="rc-rcc__cta-note"><?php echo esc_html( $cta_note ); ?></span>
			<?php else : ?>
				<a class="rc-rcc__cta" href="<?php echo esc_url( $cta_url ); ?>" rel="noopener noreferrer" target="_blank"><span class="rc-rcc__cta-label"><?php echo esc_html( $cta_label ); ?></span></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</li>
