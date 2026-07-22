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

// Spalte 5 bestimmen: es gibt nur drei Zustände – Ergebnisse (vorbei),
// Nennung (Button) oder „Nennung ab …" (verlinkter Hinweis). Kein „Zum Rennen".
$cta_url      = '';
$cta_label    = '';
$cta_note     = '';
$cta_note_url = '';

if ( $rc_is_past ) {
	// Vorbei → „Ergebnisse" nur, wenn es eine echte Ergebnisseite gibt (MyRCM).
	// Ohne die keinen Button: Ergebnisse sieht man auf MyRCM oder als vom Verein
	// hochgeladenes PDF (Dokumentspalte) – ein Link auf die reine Event-Seite
	// wäre irreführend.
	if ( '' !== $race->results_url ) {
		$cta_label = __( 'Ergebnisse', 'rc-racemap-club-calendar' );
		$cta_url   = $race->results_url;
	}
} elseif ( $race->is_registration_open() ) {
	$cta_label = __( 'Nennung', 'rc-racemap-club-calendar' );
	$cta_url   = $event_url;
} elseif ( null !== $race->registration_opens && $race->registration_opens > time() ) {
	// Nennung startet erst später → verlinkter Hinweis auf MyRCM.
	$cta_note     = sprintf(
		/* translators: %s: Datum, ab dem die Nennung möglich ist. */
		__( 'Nennung ab %s', 'rc-racemap-club-calendar' ),
		// Kompaktes, lokalisiertes Datum („3 Aug 2026") – passt in die schmale
		// Aktionsspalte auf eine Zeile, statt das lange Theme-Datumsformat.
		wp_date( 'j M Y', $race->registration_opens )
	);
	$cta_note_url = $event_url;
} else {
	// Kommend, aber (noch) nicht als „offen" gemeldet (z. B. nur nach Login) →
	// trotzdem zur Nennung führen.
	$cta_label = __( 'Nennung', 'rc-racemap-club-calendar' );
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
			<?php
			$rc_sheets = array( $race->timestamp );
			if ( $race->is_multi_day() && null !== $race->timestamp_to ) {
				$rc_sheets[] = $race->timestamp_to;
			}
			?>
			<span class="rc-rcc__sheets" title="<?php echo esc_attr( $race->formatted_date() ); ?>">
				<?php foreach ( $rc_sheets as $rc_ts ) : ?>
					<time class="rc-rcc__sheet" datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $rc_ts ) ); ?>">
						<span class="rc-rcc__sheet-day"><?php echo esc_html( wp_date( 'd', $rc_ts ) ); ?></span>
						<span class="rc-rcc__sheet-month"><?php echo esc_html( wp_date( 'M', $rc_ts ) ); ?></span>
						<span class="rc-rcc__sheet-year"><?php echo esc_html( wp_date( 'Y', $rc_ts ) ); ?></span>
					</time>
				<?php endforeach; ?>
			</span>
		<?php else : ?>
			<?php echo esc_html( $race->date_compact() ); ?>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__cell rc-rcc__race">
		<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

		<?php if ( ! empty( $race->classes ) ) : ?>
			<ul class="rc-rcc__classes">
				<?php foreach ( $race->classes as $class ) : ?>
<?php
						$rc_class_inner = esc_html( $class['name'] );
						if ( null !== $class['entries'] ) {
							$rc_class_inner .= ' <span class="rc-rcc__class-entries">(' . esc_html( (string) $class['entries'] ) . ')</span>';
						}
						// Link nur mit Teilnehmer-URL UND Nennungen (>0). Die v9-
						// Teilnehmerliste ist bei 0 Nennungen leer; DMC/RCK liefern
						// keine URL → dann gedämpfte, nicht klickbare Pille.
						$rc_has_entries = null !== $class['entries'] && (int) $class['entries'] > 0;
						$rc_class_url = ( ! empty( $class['participantsUrl'] ) && $rc_has_entries ) ? (string) $class['participantsUrl'] : '';
						if ( '' !== $rc_class_url ) :
						?>
						<li class="rc-rcc__class rc-rcc__class--link"><a class="rc-rcc__class-link" href="<?php echo esc_url( $rc_class_url ); ?>" rel="noopener noreferrer" target="_blank" title="<?php echo esc_attr__( 'Teilnehmer dieser Klasse', 'rc-racemap-club-calendar' ); ?>"><?php echo $rc_class_inner . $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapte Werte + konstantes Inline-SVG. ?></a></li>
						<?php else : ?>
						<li class="rc-rcc__class rc-rcc__class--plain"><?php echo $rc_class_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapte Werte. ?></li>
						<?php endif; ?>
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
			<?php // v9: Es gibt keine kombinierte Teilnehmerliste mehr (nur pro Klasse, über die Klassen-Pillen). Daher nur die Zahl, kein Link. ?>
			<span class="rc-rcc__ppl-link rc-rcc__ppl-link--static" title="<?php echo esc_attr( $ppl_label ); ?>"><?php echo $ppl_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstante Inline-SVGs + escapte Zahl. ?></span>
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
				<?php if ( '' !== $cta_note_url ) : ?>
					<a class="rc-rcc__cta-note" href="<?php echo esc_url( $cta_note_url ); ?>" rel="noopener noreferrer" target="_blank"><span class="rc-rcc__cta-note-label"><?php echo esc_html( $cta_note ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Konstantes Inline-SVG. ?></a>
				<?php else : ?>
					<span class="rc-rcc__cta-note"><span class="rc-rcc__cta-note-label"><?php echo esc_html( $cta_note ); ?></span></span>
				<?php endif; ?>
			<?php else : ?>
				<a class="rc-rcc__cta" href="<?php echo esc_url( $cta_url ); ?>" rel="noopener noreferrer" target="_blank"><span class="rc-rcc__cta-label"><?php echo esc_html( $cta_label ); ?></span></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</li>
