<?php
/**
 * Data source abstraction.
 *
 * The single point of contact with the (future) RC RaceMap API. Nothing else
 * in the plugin knows *how* races are fetched — callers just ask for the
 * events of a club and receive an array of {@see RC_RCC_Race} objects.
 *
 * The endpoint is expected to be:
 *
 *     GET {base_url}/api/clubs/{club-id}
 *
 * returning already-prepared JSON. Until that API is live, a bundled sample
 * data set can be used for development and demos (see rc_rcc_use_sample_data).
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Api
 */
class RC_RCC_Api {

	/**
	 * Default API base URL. Override via the `rc_rcc_api_base_url` filter or
	 * the RC_RCC_API_BASE_URL constant.
	 */
	private const DEFAULT_BASE_URL = 'https://rcracemap.com';

	/**
	 * Cache component.
	 *
	 * @var RC_RCC_Cache
	 */
	private RC_RCC_Cache $cache;

	/**
	 * Last error encountered while fetching, or null on success.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $last_error = null;

	/**
	 * Raw event rows from the most recent hydrate() call.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $last_rows = array();

	/**
	 * Data timestamp from the most recent successful API response (Unix, 0=none).
	 *
	 * @var int
	 */
	private int $last_data_stamp = 0;

	/**
	 * Constructor.
	 *
	 * @param RC_RCC_Cache $cache Cache component.
	 */
	public function __construct( RC_RCC_Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Fetch the races of a club.
	 *
	 * Results are cached for the configured TTL. On a failed request the last
	 * good cached copy is returned when available; otherwise an empty array.
	 *
	 * @param string $club_id       Club / organiser identifier.
	 * @param int    $cache_ttl     Cache lifetime in seconds.
	 * @param bool   $force_refresh Bypass the cache and hit the source.
	 * @return RC_RCC_Race[] List of races (unfiltered, unsorted).
	 */
	public function get_events( string $club_id, int $cache_ttl, bool $force_refresh = false ): array {
		$this->last_error = null;
		$club_id          = trim( $club_id );

		// Note: an empty club ID is deliberately NOT rejected here. While the
		// bundled sample data is active (RC RaceMap API not live yet) the
		// calendar must still render, so the "missing club" error is only
		// raised on the real-API path inside request_events().
		$cache_key = 'events_' . ( '' !== $club_id ? $club_id : 'sample' );

		if ( ! $force_refresh ) {
			$cached = $this->cache->get( $cache_key );
			if ( is_array( $cached ) ) {
				return $this->hydrate( $cached );
			}
		}

		$raw = $this->request_events( $club_id );

		if ( is_wp_error( $raw ) ) {
			$this->last_error = $raw;

			// Fall back to a stale cache copy if we have one.
			$stale = $this->cache->get( $cache_key );
			return is_array( $stale ) ? $this->hydrate( $stale ) : array();
		}

		$this->cache->set( $cache_key, $raw, $cache_ttl );

		// Daten-Stand der Quelle merken – Grundlage der „Stand:"-Anzeige. Nur
		// wenn die API einen liefert; sonst bleibt der alte Wert (oder keiner).
		if ( $this->last_data_stamp > 0 ) {
			update_option( RC_RCC_Plugin::OPTION_DATA_STAMP, $this->last_data_stamp, false );
		}

		return $this->hydrate( $raw );
	}

	/**
	 * Get the last error, if any.
	 *
	 * @return WP_Error|null
	 */
	public function last_error(): ?WP_Error {
		return $this->last_error;
	}

	/**
	 * The raw event rows behind the most recent result.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function last_rows(): array {
		return $this->last_rows;
	}

	/**
	 * Perform the actual data retrieval and return normalised raw arrays.
	 *
	 * @param string $club_id Club identifier.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function request_events( string $club_id ) {
		/**
		 * Filter: use the bundled sample data instead of the live API.
		 *
		 * Handy while the RC RaceMap API is not yet available. Defaults to
		 * true when the API base URL is the (not-yet-live) default.
		 *
		 * @param bool   $use_sample Whether to use sample data.
		 * @param string $club_id    Club identifier.
		 */
		$use_sample = apply_filters( 'rc_rcc_use_sample_data', $this->should_use_sample_data(), $club_id );

		if ( $use_sample ) {
			return $this->load_sample_data();
		}

		// Real API path: a club ID is required.
		if ( '' === $club_id ) {
			return new WP_Error( 'rc_rcc_missing_club', __( 'Keine Club-ID konfiguriert.', 'rc-racemap-club-calendar' ) );
		}

		$url = trailingslashit( $this->base_url() ) . 'api/clubs/' . rawurlencode( $club_id );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'rc_rcc_http_error',
				sprintf(
					/* translators: %d: HTTP-Statuscode. */
					__( 'Die RC-RaceMap-API hat mit HTTP-Status %d geantwortet.', 'rc-racemap-club-calendar' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'rc_rcc_bad_json', __( 'Die RC-RaceMap-API hat ungültiges JSON zurückgegeben.', 'rc-racemap-club-calendar' ) );
		}

		$this->last_data_stamp = $this->extract_data_stamp( $response, is_array( $data ) ? $data : array() );

		return $this->extract_events( $data );
	}

	/**
	 * Pull the events list out of the API payload.
	 *
	 * Accepts either a bare list of events or an object with an `events` key,
	 * so the plugin tolerates minor API shape changes.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * Den Daten-Stand aus der Antwort lesen.
	 *
	 * Zuerst das Body-Feld `generatedAt` (klarer Vertrag), sonst der
	 * HTTP-`Last-Modified`-Header. Beides als Unix-Zeit; 0 wenn keiner da ist.
	 *
	 * @param array|WP_Error       $response wp_remote_get-Antwort.
	 * @param array<string, mixed> $data     Dekodierter Body.
	 * @return int
	 */
	private function extract_data_stamp( $response, array $data ): int {
		$raw = '';

		if ( isset( $data['generatedAt'] ) && is_string( $data['generatedAt'] ) ) {
			$raw = $data['generatedAt'];
		} else {
			$raw = (string) wp_remote_retrieve_header( $response, 'last-modified' );
		}

		if ( '' === $raw ) {
			return 0;
		}

		$ts = strtotime( $raw );

		return ( false !== $ts ) ? $ts : 0;
	}

	/**
	 * The data timestamp behind the most recent response.
	 *
	 * @return int
	 */
	public function last_data_stamp(): int {
		return $this->last_data_stamp;
	}

	private function extract_events( $data ): array {
		if ( is_array( $data ) && isset( $data['events'] ) && is_array( $data['events'] ) ) {
			$data = $data['events'];
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		// Keep only associative-array rows.
		return array_values(
			array_filter(
				$data,
				static fn( $row ) => is_array( $row )
			)
		);
	}

	/**
	 * Turn raw event arrays into Race objects.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return RC_RCC_Race[]
	 */
	private function hydrate( array $rows ): array {
		// Rohdaten des zuletzt gelieferten Abrufs merken – der Kalender legt
		// sie ins Archiv, damit vergangene Rennen erhalten bleiben.
		$this->last_rows = $rows;

		$races = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$race = RC_RCC_Race::from_array( $row );

			// An event without an ID cannot be tracked for visibility; skip it.
			if ( '' === $race->id ) {
				continue;
			}

			$races[] = $race;
		}

		return $races;
	}

	/**
	 * Resolve the API base URL (constant > filter > default).
	 *
	 * @return string
	 */
	private function base_url(): string {
		$base = defined( 'RC_RCC_API_BASE_URL' ) ? RC_RCC_API_BASE_URL : self::DEFAULT_BASE_URL;

		/**
		 * Filter the RC RaceMap API base URL.
		 *
		 * @param string $base Base URL without trailing slash.
		 */
		return (string) apply_filters( 'rc_rcc_api_base_url', $base );
	}

	/**
	 * Decide whether to use sample data by default.
	 *
	 * @return bool
	 */
	private function should_use_sample_data(): bool {
		// Explicit opt-in for local development.
		if ( defined( 'RC_RCC_USE_SAMPLE_DATA' ) ) {
			return (bool) RC_RCC_USE_SAMPLE_DATA;
		}

		// Default: use the real data source (per-club snapshots / live API).
		return false;
	}

	/**
	 * Load the bundled sample data set.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function load_sample_data() {
		$path = RC_RCC_PATH . 'sample-data.json';

		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'rc_rcc_no_sample', __( 'Die Datei mit Beispieldaten fehlt.', 'rc-racemap-club-calendar' ) );
		}

		$body = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
		$data = json_decode( (string) $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'rc_rcc_bad_sample', __( 'Die Beispieldaten-Datei enthält kein gültiges JSON.', 'rc-racemap-club-calendar' ) );
		}

		return $this->relative_sample_dates( $this->extract_events( $data ) );
	}

	/**
	 * Spread the sample events' dates around "today".
	 *
	 * The bundled sample data has fixed calendar dates, so depending on the
	 * server clock every event could end up in the past (empty "upcoming" tab)
	 * or the future (empty "archive"). To keep the demo meaningful on any date,
	 * the events are re-dated symmetrically around the current day while
	 * preserving each event's own duration (single- vs multi-day).
	 *
	 * Only affects the bundled sample data, never the live API.
	 *
	 * @param array<int, array<string, mixed>> $events Extracted sample events.
	 * @return array<int, array<string, mixed>>
	 */
	private function relative_sample_dates( array $events ): array {
		$count = count( $events );
		if ( 0 === $count ) {
			return $events;
		}

		// Sort event indexes by their original start date (ascending), so the
		// original chronological order is preserved after re-dating.
		$order = array();
		foreach ( $events as $i => $event ) {
			$from       = isset( $event['from'] ) ? strtotime( (string) $event['from'] ) : false;
			$order[ $i ] = ( false !== $from ) ? $from : 0;
		}
		asort( $order );
		$sorted_indexes = array_keys( $order );

		// Offsets in days, evenly spread from -75 (past) to +60 (future).
		$start_offset = -75;
		$end_offset   = 60;
		$span         = $end_offset - $start_offset;

		$position = 0;
		foreach ( $sorted_indexes as $index ) {
			$offset_days = ( $count > 1 )
				? (int) round( $start_offset + ( $span * $position / ( $count - 1 ) ) )
				: 0;

			// Preserve the event's own duration.
			$from_ts  = isset( $events[ $index ]['from'] ) ? strtotime( (string) $events[ $index ]['from'] ) : false;
			$to_ts    = isset( $events[ $index ]['to'] ) ? strtotime( (string) $events[ $index ]['to'] ) : false;
			$duration = ( false !== $from_ts && false !== $to_ts && $to_ts > $from_ts )
				? (int) round( ( $to_ts - $from_ts ) / DAY_IN_SECONDS )
				: 0;

			$new_from = strtotime( $offset_days . ' days', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Sample data only.
			$new_to   = strtotime( '+' . $duration . ' days', $new_from );

			$events[ $index ]['from'] = gmdate( 'Y-m-d', $new_from );
			$events[ $index ]['to']   = gmdate( 'Y-m-d', $new_to );

			$position++;
		}

		return $events;
	}
}
