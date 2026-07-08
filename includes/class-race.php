<?php
/**
 * Race value object.
 *
 * A plain, immutable-ish data holder representing one race event. It knows
 * nothing about WordPress, the API or rendering — it only normalises the raw
 * data coming from the data source into typed, predictable properties.
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
	 * Raw date string as delivered by the source (fallback for display).
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
	 * Track or location (optional).
	 *
	 * @var string
	 */
	public string $track = '';

	/**
	 * Race classes (list of names).
	 *
	 * @var string[]
	 */
	public array $classes = array();

	/**
	 * Status label (optional, e.g. "Registration open").
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
	 * Build a Race from a raw associative array (as returned by the API).
	 *
	 * Unknown keys are ignored; missing keys fall back to safe defaults.
	 *
	 * @param array<string, mixed> $data Raw event data.
	 * @return RC_RCC_Race
	 */
	public static function from_array( array $data ): RC_RCC_Race {
		$race = new self();

		$race->id        = isset( $data['id'] ) ? (string) $data['id'] : '';
		$race->title     = isset( $data['title'] ) ? (string) $data['title'] : '';
		$race->organizer = isset( $data['organizer'] ) ? (string) $data['organizer'] : '';
		$race->track     = isset( $data['track'] ) ? (string) $data['track'] : '';
		$race->status    = isset( $data['status'] ) ? (string) $data['status'] : '';
		$race->date_raw  = isset( $data['date'] ) ? (string) $data['date'] : '';

		// Normalise the date into a timestamp when possible.
		if ( '' !== $race->date_raw ) {
			$parsed = strtotime( $race->date_raw );
			if ( false !== $parsed ) {
				$race->timestamp = $parsed;
			}
		}

		// Classes may arrive as an array or a comma-separated string.
		if ( isset( $data['classes'] ) ) {
			if ( is_array( $data['classes'] ) ) {
				$race->classes = array_values( array_filter( array_map( 'strval', $data['classes'] ) ) );
			} elseif ( is_string( $data['classes'] ) && '' !== $data['classes'] ) {
				$race->classes = array_map( 'trim', explode( ',', $data['classes'] ) );
			}
		}

		if ( isset( $data['participant_count'] ) && '' !== $data['participant_count'] ) {
			$race->participant_count = (int) $data['participant_count'];
		}

		// Links: only keep known, non-empty entries.
		$allowed_links = array( 'registration', 'participants', 'announcement', 'regulations' );
		if ( isset( $data['links'] ) && is_array( $data['links'] ) ) {
			foreach ( $allowed_links as $key ) {
				if ( ! empty( $data['links'][ $key ] ) ) {
					$race->links[ $key ] = (string) $data['links'][ $key ];
				}
			}
		}

		return $race;
	}

	/**
	 * Whether the race lies in the future (relative to "now").
	 *
	 * Races without a parseable date are treated as upcoming so they are
	 * never silently hidden.
	 *
	 * @param int|null $now Reference timestamp; defaults to current time.
	 * @return bool
	 */
	public function is_upcoming( ?int $now = null ): bool {
		if ( null === $this->timestamp ) {
			return true;
		}

		$now = $now ?? time();

		return $this->timestamp >= $now;
	}

	/**
	 * Formatted date using the site's date format and timezone.
	 *
	 * @return string
	 */
	public function formatted_date(): string {
		if ( null === $this->timestamp ) {
			return $this->date_raw;
		}

		return wp_date( (string) get_option( 'date_format' ), $this->timestamp );
	}

	/**
	 * Whether this race exposes at least one action link.
	 *
	 * @return bool
	 */
	public function has_links(): bool {
		return ! empty( $this->links );
	}
}
