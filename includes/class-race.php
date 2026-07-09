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

		// Organiser: real model uses `hostName`; older samples use `organizer`.
		$race->organizer = self::first_string( $data, array( 'hostName', 'organizer' ) );

		// Venue: real model splits name and location; older samples use `track`.
		$race->track    = self::first_string( $data, array( 'venueName', 'track' ) );
		$race->location = self::first_string( $data, array( 'venueLocation' ) );

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

		$race->series            = self::normalise_series( $data['series'] ?? null );
		$race->classes           = self::normalise_classes( $data['classes'] ?? null );
		$race->status            = self::derive_status( $data );
		$race->participant_count = self::derive_participant_count( $data );
		$race->links             = self::derive_links( $data );
		$race->extra_links       = self::derive_extra_documents( $data );

		return $race;
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
		$note = self::first_string( $data, array( 'note', 'status' ) );

		if ( '' !== $note ) {
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
}
