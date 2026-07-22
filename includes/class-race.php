<?php
/**
 * Race value object.
 *
 * A plain, immutable-ish data holder representing one race event. It knows
 * nothing about WordPress, the API or rendering — it only normalises the raw
 * data coming from the data source into typed, predictable properties.
 *
 * The raw shape mirrors the canonical RC RaceMap model (see
 * docs/rc-racemap-data-model.md): dates are a `from`/`to` range, the organiser
 * is `hostName`, the venue is split into `venueName`/`venueLocation`, classes
 * may be plain strings or `{name, entries}` objects, and action links are
 * derived from `documents[]` plus `registrationListUrl`/`url`. Older sample
 * keys (`title`, `organizer`, `track`, `date`, `links{}`) are still accepted as
 * a fallback so existing data keeps working.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Race
 */
class RC_RCC_Race {

	/**
	 * Unique, stable event identifier (never the title).
	 *
	 * @var string
	 */
	public string $id = '';

	/**
	 * Event start date as a Unix timestamp (UTC), or null if unknown.
	 *
	 * @var int|null
	 */
	public ?int $timestamp = null;

	/**
	 * Event end date as a Unix timestamp (UTC). Equals {@see $timestamp} for
	 * single-day events; null when unknown.
	 *
	 * @var int|null
	 */
	public ?int $timestamp_to = null;

	/**
	 * Raw start-date string as delivered by the source (fallback for display).
	 *
	 * @var string
	 */
	public string $date_raw = '';

	/**
	 * Race title.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * The title as delivered by the source, before any club override.
	 *
	 * @var string
	 */
	public string $original_title = '';

	/**
	 * Organiser / hosting club name.
	 *
	 * @var string
	 */
	public string $organizer = '';

	/**
	 * Track / venue name (optional).
	 *
	 * @var string
	 */
	public string $track = '';

	/**
	 * Venue location / town (optional).
	 *
	 * @var string
	 */
	public string $location = '';

	/**
	 * Race series this event belongs to (e.g. "Alpencup"). May be empty.
	 *
	 * @var string[]
	 */
	public array $series = array();

	/**
	 * Race classes. Each entry is an array with a `name` (string) and an
	 * optional `entries` (int|null) count.
	 *
	 * @var array<int, array{name: string, entries: int|null}>
	 */
	public array $classes = array();

	/**
	 * Status label (optional, e.g. "Nennung geschlossen.").
	 *
	 * @var string
	 */
	public string $status = '';

	/**
	 * Raw registration status enum: "open" | "closed" | "upcoming" | "".
	 * Kept separate from the human-readable {@see $status} so the UI can style
	 * states (open/closed) independently of the display text/locale.
	 *
	 * @var string
	 */
	public string $registration_status = '';

	/**
	 * Beginn der Nennung als Unix-Timestamp (aus `registrationOpens`), sonst
	 * null. Für den Hinweis „Nennung ab …", wenn die Nennung noch nicht offen ist.
	 *
	 * @var int|null
	 */
	public ?int $registration_opens = null;

	/**
	 * Participant count (optional). Null when unknown.
	 *
	 * @var int|null
	 */
	public ?int $participant_count = null;

	/**
	 * Known link types mapped to URLs. Keys: registration, participants,
	 * announcement, regulations.
	 *
	 * @var array<string, string>
	 */
	public array $links = array();

	/**
	 * Additional documents that don't map to a known link type (e.g. generic
	 * "PDF" attachments). Each item: ['label' => string, 'url' => string].
	 *
	 * @var array<int, array{label: string, url: string}>
	 */
	public array $extra_links = array();

	/**
	 * Data source of this event: "myrcm", "rck", … Empty when unknown.
	 *
	 * @var string
	 */
	public string $source = '';

	/**
	 * Direct link to the MyRCM results view (past MyRCM events). Empty for
	 * non-MyRCM sources or when no event id is available.
	 *
	 * @var string
	 */
	public string $results_url = '';

	/**
	 * Build a Race from a raw associative array (as returned by the API).
	 *
	 * Unknown keys are ignored; missing keys fall back to safe defaults.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @return RC_RCC_Race
	 */
	public static function from_array( array $data ): RC_RCC_Race {
		$race = new self();

		$race->id = isset( $data['id'] ) ? (string) $data['id'] : '';

		// Title: real model uses `name`; older samples use `title`.
		$race->title = self::first_string( $data, array( 'name', 'title' ) );

		// Der Titel der Quelle, bevor der Verein ihn ggf. überschreibt. Wird im
		// Backend als Platzhalter gezeigt, damit sichtbar bleibt, was MyRCM,
		// RCK oder DMC eigentlich liefern.
		$race->original_title = $race->title;

		// Organiser: real model uses `hostName`; older samples use `organizer`.
		$race->organizer = self::first_string( $data, array( 'hostName', 'organizer' ) );

		// Venue: real model splits name and location; older samples use `track`.
		$race->track    = self::first_string( $data, array( 'venueName', 'track' ) );
		$race->location = self::first_string( $data, array( 'venueLocation', 'city' ) );

		// Date range: real model uses `from`/`to`; older samples use `date`.
		$race->date_raw = self::first_string( $data, array( 'from', 'date' ) );
		$to_raw         = self::first_string( $data, array( 'to' ) );

		if ( '' !== $race->date_raw ) {
			$parsed = strtotime( $race->date_raw );
			if ( false !== $parsed ) {
				$race->timestamp = $parsed;
			}
		}

		if ( '' !== $to_raw ) {
			$parsed_to = strtotime( $to_raw );
			if ( false !== $parsed_to ) {
				$race->timestamp_to = $parsed_to;
			}
		}

		// Single-day events: end == start.
		if ( null === $race->timestamp_to && null !== $race->timestamp ) {
			$race->timestamp_to = $race->timestamp;
		}

		$race->series               = self::normalise_series( $data['series'] ?? null );
		$race->classes              = self::normalise_classes( $data['classes'] ?? null );
		$race->status               = self::derive_status( $data );
		$race->registration_status  = strtolower( self::first_string( $data, array( 'registrationStatus' ) ) );

		$opens = self::first_string( $data, array( 'registrationOpens' ) );
		if ( '' !== $opens ) {
			$opens_ts = strtotime( $opens );
			if ( false !== $opens_ts ) {
				$race->registration_opens = $opens_ts;
			}
		}
		$race->participant_count = self::derive_participant_count( $data );
		$race->links             = self::derive_links( $data );
		$race->extra_links       = self::derive_extra_documents( $data );
		$race->source            = self::first_string( $data, array( 'source' ) );

		// MyRCM-Links auf die Seitensprache umstellen (pLa) und den
		// Ergebnis-Link für vergangene MyRCM-Rennen ableiten.
		$lang                = self::myrcm_language();
		$race->links         = array_map(
			static fn( $url ) => self::localize_myrcm_url( (string) $url, $lang ),
			$race->links
		);
		foreach ( $race->extra_links as $i => $doc ) {
			$race->extra_links[ $i ]['url'] = self::localize_myrcm_url( $doc['url'], $lang );
		}
		// Bei zusammengeführten Events (`source: myrcm+rck`) zeigt der Event-Link
		// auf RCK, die Ergebnisse liegen aber weiter auf MyRCM. Drei Stufen,
		// absteigend nach Verlässlichkeit:
		//   1. der Event-Link selbst (reine MyRCM-Rennen),
		//   2. die `id` – sie trägt die MyRCM-Event-Nummer auch dann noch, wenn
		//      alle URLs des Events auf RCK zeigen,
		//   3. die Teilnehmerliste, falls die `id` das Muster nicht erfüllt.
		$race->results_url = self::derive_results_url( $race->links['registration'] ?? '', $race->organizer, $lang );

		if ( '' === $race->results_url && preg_match( '/myrcm-event-(\d+)/i', $race->id, $m ) ) {
			$race->results_url = self::myrcm_results_url( $m[1], $race->organizer, $lang );
		}

		if ( '' === $race->results_url ) {
			$race->results_url = self::derive_results_url( $race->links['participants'] ?? '', $race->organizer, $lang );
		}

		// v9: Die Teilnehmer-/Nennliste lag früher auf der bkg-Route
		// (hId[1]=bkg&dId[E]=…). Die ist im Redesign tot und leitet auf die
		// generische /de/live-Übersicht um. Die Nennliste steht jetzt auf der
		// Report-Seite (/report/<eventId>) – dieselbe Event-ID wie die
		// Ergebnisse. Aus der alten URL bzw. der Event-ID neu aufbauen.
		if ( ! empty( $race->links['participants'] ) ) {
			$participants_report = self::derive_results_url( $race->links['participants'], $race->organizer, $lang );

			if ( '' === $participants_report && preg_match( '/myrcm-event-(\d+)/i', $race->id, $m ) ) {
				$participants_report = self::myrcm_results_url( $m[1], $race->organizer, $lang );
			}

			if ( '' === $participants_report ) {
				$participants_report = self::derive_results_url( $race->links['registration'] ?? '', $race->organizer, $lang );
			}

			if ( '' !== $participants_report ) {
				$race->links['participants'] = $participants_report;
			}
		}

		return $race;
	}

	/**
	 * The event's start year (e.g. "2026"), or '' when the date is unknown.
	 *
	 * @return string
	 */
	public function year(): string {
		if ( null === $this->timestamp ) {
			return '';
		}

		return wp_date( 'Y', $this->timestamp );
	}

	/**
	 * Whether this event originates from the RCK source.
	 *
	 * @return bool
	 */
	public function is_rck(): bool {
		return '' !== $this->source && false !== stripos( $this->source, 'rck' );
	}

	/**
	 * Whether this event was created by the club itself.
	 *
	 * Solche Termine stehen in keiner Quelle – es gibt weder Nennsystem noch
	 * Ergebnisseite, nur die Angaben des Vereins.
	 *
	 * @return bool
	 */
	public function is_custom(): bool {
		return 'custom' === $this->source;
	}

	/**
	 * Whether this event is a merged cross-listing (`source: myrcm+rck`).
	 *
	 * Der Verein hat ein RCK-Serienrennen zusätzlich auf MyRCM ausgeschrieben.
	 * Genannt wird über RCK, Ergebnisse und Klassen liegen auf MyRCM.
	 *
	 * @return bool
	 */
	public function is_merged(): bool {
		return $this->is_rck() && false !== stripos( $this->source, 'myrcm' );
	}

	/**
	 * Whether the race lies in the future (relative to "now").
	 *
	 * A multi-day event counts as upcoming until its final day has passed.
	 * Races without a parseable date are treated as upcoming so they are never
	 * silently hidden.
	 *
	 * @param int|null $now Reference timestamp; defaults to current time.
	 * @return bool
	 */
	public function is_upcoming( ?int $now = null ): bool {
		$end = $this->timestamp_to ?? $this->timestamp;

		if ( null === $end ) {
			return true;
		}

		$now = $now ?? time();

		return $end >= $now;
	}

	/**
	 * Formatted date using the site's date format and timezone.
	 *
	 * Multi-day events render as a range ("14. Juni 2025 – 15. Juni 2025").
	 *
	 * @return string
	 */
	public function formatted_date(): string {
		if ( null === $this->timestamp ) {
			return $this->date_raw;
		}

		$format = (string) get_option( 'date_format' );
		$from   = wp_date( $format, $this->timestamp );

		if ( null !== $this->timestamp_to && $this->timestamp_to > $this->timestamp ) {
			$to = wp_date( $format, $this->timestamp_to );

			/* translators: 1: start date, 2: end date of a multi-day event. */
			return sprintf( __( '%1$s – %2$s', 'rc-racemap-club-calendar' ), $from, $to );
		}

		return $from;
	}

	/**
	 * Whether this event spans more than one day.
	 *
	 * @return bool
	 */
	public function is_multi_day(): bool {
		return null !== $this->timestamp
			&& null !== $this->timestamp_to
			&& $this->timestamp_to > $this->timestamp;
	}

	/**
	 * Whether registration is currently open.
	 *
	 * @return bool
	 */
	public function is_registration_open(): bool {
		return 'open' === $this->registration_status;
	}

	/**
	 * Compact, single-line date for the list UI: Tag + 3-Buchstaben-Monat, ohne
	 * Jahr (das Jahr steht in der Jahres-Navigation). Monatsname locale-abhängig
	 * über wp_date('M'):
	 *   - single day             → "21. Jun"
	 *   - multi-day, same month   → "04–05. Jul"
	 *   - multi-day, diff. month  → "28. Jul – 02. Aug"
	 *
	 * @return string
	 */
	public function date_compact(): string {
		if ( null === $this->timestamp ) {
			return $this->date_raw;
		}

		$start = $this->timestamp;
		$end   = $this->timestamp_to ?? $this->timestamp;

		if ( ! $this->is_multi_day() ) {
			return wp_date( 'd. M', $start );
		}

		$same_month = wp_date( 'Y-m', $start ) === wp_date( 'Y-m', $end );

		if ( $same_month ) {
			// En-Dash zwischen den Tagen, Monat einmal am Ende.
			return wp_date( 'd', $start ) . '–' . wp_date( 'd. M', $end );
		}

		return wp_date( 'd. M', $start ) . ' – ' . wp_date( 'd. M', $end );
	}

	/**
	 * Whether this race exposes at least one action link.
	 *
	 * @return bool
	 */
	public function has_links(): bool {
		return ! empty( $this->links ) || ! empty( $this->extra_links );
	}

	/**
	 * Return the first non-empty string value among the given keys.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @param string[]             $keys Candidate keys, in priority order.
	 * @return string
	 */
	private static function first_string( array $data, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $data[ $key ] );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Normalise the `series` field into a clean list of strings.
	 *
	 * @param mixed $raw Raw series value.
	 * @return string[]
	 */
	private static function normalise_series( $raw ): array {
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = array( $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static fn( $value ) => is_scalar( $value ) ? trim( (string) $value ) : '',
					$raw
				)
			)
		);
	}

	/**
	 * Normalise the `classes` field.
	 *
	 * Accepts plain strings, `{name, entries}` objects, a comma-separated
	 * string, or any mix thereof, and returns a uniform list of
	 * `['name' => …, 'entries' => …]` rows.
	 *
	 * @param mixed $raw Raw classes value.
	 * @return array<int, array{name: string, entries: int|null}>
	 */
	private static function normalise_classes( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = '' === trim( $raw ) ? array() : explode( ',', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$classes = array();

		foreach ( $raw as $entry ) {
			if ( is_array( $entry ) ) {
				$name = isset( $entry['name'] ) ? trim( (string) $entry['name'] ) : '';

				if ( '' === $name ) {
					continue;
				}

				$entries = isset( $entry['entries'] ) && '' !== $entry['entries']
					? (int) $entry['entries']
					: null;

				$classes[] = array(
					'name'    => $name,
					'entries' => $entries,
				);
				continue;
			}

			if ( is_scalar( $entry ) ) {
				$name = trim( (string) $entry );

				if ( '' !== $name ) {
					$classes[] = array(
						'name'    => $name,
						'entries' => null,
					);
				}
			}
		}

		return $classes;
	}

	/**
	 * Derive a human-readable status label.
	 *
	 * Prefers the source's `note` text; otherwise maps the
	 * `registrationStatus` enum to a translated label. Older samples that ship
	 * a free-form `status` string keep working.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @return string
	 */
	private static function derive_status( array $data ): string {
		$source = self::first_string( $data, array( 'source' ) );
		$merged = false !== stripos( $source, 'rck' ) && false !== stripos( $source, 'myrcm' );

		$note = self::first_string( $data, array( 'note', 'status' ) );

		// Bei zusammengeführten Events stammt `note` aus MyRCM, der maßgebliche
		// `registrationStatus` aber aus RCK. Beide können sich widersprechen
		// (MyRCM „Nennung geschlossen." bei offener RCK-Nennung), deshalb hat
		// hier der Status-Enum Vorrang vor dem Freitext.
		if ( '' !== $note && ! $merged ) {
			return $note;
		}

		$status = self::first_string( $data, array( 'registrationStatus' ) );

		switch ( $status ) {
			case 'open':
				return __( 'Nennung geöffnet', 'rc-racemap-club-calendar' );
			case 'closed':
				return __( 'Nennung geschlossen', 'rc-racemap-club-calendar' );
			case 'upcoming':
				return __( 'Nennung folgt', 'rc-racemap-club-calendar' );
			default:
				return '';
		}
	}

	/**
	 * Derive the participant count from the real or legacy field.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @return int|null
	 */
	private static function derive_participant_count( array $data ): ?int {
		foreach ( array( 'registrationCount', 'participant_count' ) as $key ) {
			if ( isset( $data[ $key ] ) && '' !== $data[ $key ] && is_numeric( $data[ $key ] ) ) {
				return (int) $data[ $key ];
			}
		}

		return null;
	}

	/**
	 * Build the action-link map from the real model.
	 *
	 * Real model:
	 *   - registration  ← `url` (fallback `detailUrl`)
	 *   - participants  ← `registrationListUrl`
	 *   - announcement  ← first `documents[]` entry of type `announcement`
	 *   - regulations   ← first `documents[]` entry of type `rules`
	 *
	 * Legacy sample data ships a ready-made `links{}` object, which is used
	 * as-is when present.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @return array<string, string>
	 */
	private static function derive_links( array $data ): array {
		$allowed = array( 'registration', 'participants', 'announcement', 'regulations' );

		// Legacy shape: a prepared links object.
		if ( isset( $data['links'] ) && is_array( $data['links'] ) ) {
			$links = array();
			foreach ( $allowed as $key ) {
				if ( ! empty( $data['links'][ $key ] ) ) {
					$links[ $key ] = (string) $data['links'][ $key ];
				}
			}

			return $links;
		}

		$links = array();

		$registration = self::first_string( $data, array( 'url', 'detailUrl' ) );
		if ( '' !== $registration ) {
			$links['registration'] = $registration;
		}

		$participants = self::first_string( $data, array( 'registrationListUrl' ) );
		if ( '' !== $participants ) {
			$links['participants'] = $participants;
		}

		// Map document types to link keys.
		$doc_type_map = array(
			'announcement' => 'announcement',
			'rules'        => 'regulations',
		);

		if ( isset( $data['documents'] ) && is_array( $data['documents'] ) ) {
			foreach ( $data['documents'] as $doc ) {
				if ( ! is_array( $doc ) ) {
					continue;
				}

				$type = isset( $doc['type'] ) ? (string) $doc['type'] : '';
				$url  = isset( $doc['url'] ) ? (string) $doc['url'] : '';

				if ( '' === $url || ! isset( $doc_type_map[ $type ] ) ) {
					continue;
				}

				$key = $doc_type_map[ $type ];

				// Keep the first document of each mapped type.
				if ( ! isset( $links[ $key ] ) ) {
					$links[ $key ] = $url;
				}
			}
		}

		return $links;
	}

	/**
	 * Collect documents that don't map to a known semantic link type.
	 *
	 * Known types (announcement, rules) are handled by {@see derive_links()}.
	 * Everything else (e.g. a generic "PDF") is returned here with its own
	 * label so nothing from the source data is silently dropped.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @return array<int, array{label: string, url: string}>
	 */
	private static function derive_extra_documents( array $data ): array {
		if ( ! isset( $data['documents'] ) || ! is_array( $data['documents'] ) ) {
			return array();
		}

		$mapped_types = array( 'announcement', 'rules' );
		$extra        = array();

		foreach ( $data['documents'] as $doc ) {
			if ( ! is_array( $doc ) ) {
				continue;
			}

			$type = isset( $doc['type'] ) ? (string) $doc['type'] : '';
			$url  = isset( $doc['url'] ) ? (string) $doc['url'] : '';

			if ( '' === $url || in_array( $type, $mapped_types, true ) ) {
				continue;
			}

			$label = isset( $doc['label'] ) ? trim( (string) $doc['label'] ) : '';
			if ( '' === $label ) {
				$label = __( 'Dokument', 'rc-racemap-club-calendar' );
			}

			$extra[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $extra;
	}

	/**
	 * The MyRCM page language (`pLa`) derived from the site locale.
	 *
	 * @return string
	 */
	private static function myrcm_language(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'de_DE';
		$two    = strtolower( substr( $locale, 0, 2 ) );

		return '' !== $two ? $two : 'en';
	}

	/**
	 * Stellt eine myrcm.ch-URL auf die Seitensprache um. Nicht-MyRCM-URLs bleiben
	 * unverändert.
	 *
	 * MyRCM v9 trägt die Sprache im Pfad-Präfix (/en/live/…, /de/report/…). Ältere
	 * Links nutzten den `pLa`-Query. Beide Fälle werden abgedeckt: erst das
	 * Präfix umbiegen, sonst den Query.
	 *
	 * @param string $url  URL.
	 * @param string $lang Two-letter language code.
	 * @return string
	 */
	private static function localize_myrcm_url( string $url, string $lang ): string {
		if ( '' === $url || false === strpos( $url, 'myrcm.ch' ) ) {
			return $url;
		}

		// v9: Sprach-Präfix im Pfad auf die Seitensprache umschreiben.
		$count = 0;
		$url   = (string) preg_replace(
			'#(//www\.myrcm\.ch)/(?:de|en|fr|it|nl|es|cs|pl)(/|$)#i',
			'${1}/' . $lang . '${2}',
			$url,
			1,
			$count
		);
		if ( $count > 0 ) {
			return $url;
		}

		// Legacy: pLa-Query setzen/ersetzen.
		if ( preg_match( '/[?&]pLa=/', $url ) ) {
			return (string) preg_replace( '/([?&]pLa=)[^&]*/', '${1}' . $lang, $url );
		}

		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'pLa=' . $lang;
	}

	/**
	 * Build the MyRCM results-view URL from an event (booking) URL.
	 *
	 * MyRCM zeigt Ergebnisse über den "search"-Kontext (`hId[1]=search`) mit
	 * derselben Event-ID. Gibt '' zurück für Nicht-MyRCM-URLs oder wenn keine
	 * Event-ID vorhanden ist.
	 *
	 * @param string $event_url Buchungs-/Event-URL (hId[1]=bkg&dId[E]=…).
	 * @param string $host      Vereinsname (als Suchfilter `dFi`).
	 * @param string $lang      Zweibuchstabiger Sprachcode.
	 * @return string
	 */
	private static function derive_results_url( string $event_url, string $host, string $lang ): string {
		if ( '' === $event_url || false === strpos( $event_url, 'myrcm.ch' ) ) {
			return '';
		}

		// Event-ID aus alter (dId[E]=…) UND neuer v9-URL (/live/…, /report/…,
		// /organizers/<org>/<id>) ziehen – der Kalender läuft mit beiden Ständen.
		if ( ! preg_match( '/dId(?:\[|%5B)E(?:\]|%5D)=(\d+)/i', $event_url, $m )
			&& ! preg_match( '#/(?:live|report|organizers/\d+)/(\d+)#i', $event_url, $m ) ) {
			return '';
		}

		return self::myrcm_results_url( $m[1], $host, $lang );
	}

	/**
	 * Build the MyRCM results URL for a known event number.
	 *
	 * @param string $event_id MyRCM-Event-Nummer.
	 * @param string $host     Veranstaltername (Filter auf der Ergebnisseite).
	 * @param string $lang     MyRCM-Sprachcode.
	 * @return string
	 */
	private static function myrcm_results_url( string $event_id, string $host, string $lang ): string {
		// MyRCM v9: die Ergebnis-/Berichtseite liegt unter /<lang>/report/<id>.
		// Die alte Route (/myrcm/main?hId[1]=search&dId[E]=…) leitet im Redesign
		// nur noch auf eine Übersicht um. $host wird hier nicht mehr gebraucht.
		unset( $host );
		$lang = ( '' !== $lang ) ? $lang : 'de';

		return 'https://www.myrcm.ch/' . rawurlencode( $lang ) . '/report/' . rawurlencode( $event_id );
	}
}
