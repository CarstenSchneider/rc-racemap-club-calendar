<?php
/**
 * Calendar business logic.
 *
 * Sits between the raw data source and the presentation layer. Responsible for
 * splitting races into upcoming / past, applying the admin visibility choices,
 * sorting and limiting. Contains no rendering and no HTTP code.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Calendar
 */
class RC_RCC_Calendar {

	/**
	 * API / data source.
	 *
	 * @var RC_RCC_Api
	 */
	private RC_RCC_Api $api;

	/**
	 * Per-request memo of fetched races, keyed by resolved club ID.
	 *
	 * Prevents a second cache/API round-trip when both the upcoming and the
	 * archive list are built during the same render.
	 *
	 * @var array<string, RC_RCC_Race[]>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param RC_RCC_Api $api Data source.
	 */
	public function __construct( RC_RCC_Api $api ) {
		$this->api = $api;
	}

	/**
	 * Get all races for the configured club (visibility NOT applied).
	 *
	 * Used by the admin "manage races" screen, which needs to see hidden
	 * races too.
	 *
	 * @param bool $force_refresh Bypass the cache.
	 * @return RC_RCC_Race[]
	 */
	public function all_races( bool $force_refresh = false ): array {
		$club_id = (string) RC_RCC_Plugin::get_setting( 'club_id', '' );
		$ttl     = (int) RC_RCC_Plugin::get_setting( 'cache_ttl', HOUR_IN_SECONDS );

		/**
		 * Filter the club ID at runtime (e.g. a shortcode `club=""` override).
		 *
		 * @param string $club_id Configured club ID.
		 */
		$club_id = (string) apply_filters( 'rc_rcc_runtime_club_id', $club_id );

		if ( ! $force_refresh && isset( $this->memo[ $club_id ] ) ) {
			return $this->memo[ $club_id ];
		}

		$races                  = $this->api->get_events( $club_id, $ttl, $force_refresh );
		$this->memo[ $club_id ] = $races;

		return $races;
	}

	/**
	 * Upcoming races, visibility-filtered, sorted ascending, limited.
	 *
	 * @return RC_RCC_Race[]
	 */
	public function upcoming_races(): array {
		$limit = (int) RC_RCC_Plugin::get_setting( 'upcoming_count', 10 );
		$races = $this->visible_races();

		$upcoming = array_filter(
			$races,
			static fn( RC_RCC_Race $race ) => $race->is_upcoming()
		);

		// Soonest first.
		usort(
			$upcoming,
			static fn( RC_RCC_Race $a, RC_RCC_Race $b ) => ( $a->timestamp ?? PHP_INT_MAX ) <=> ( $b->timestamp ?? PHP_INT_MAX )
		);

		return $this->limit( $upcoming, $limit );
	}

	/**
	 * Past races (archive), visibility-filtered, sorted descending, limited.
	 *
	 * @return RC_RCC_Race[]
	 */
	public function archived_races(): array {
		$limit = (int) RC_RCC_Plugin::get_setting( 'archive_count', 20 );
		$races = $this->visible_races();

		$past = array_filter(
			$races,
			static fn( RC_RCC_Race $race ) => ! $race->is_upcoming()
		);

		// Most recent first.
		usort(
			$past,
			static fn( RC_RCC_Race $a, RC_RCC_Race $b ) => ( $b->timestamp ?? 0 ) <=> ( $a->timestamp ?? 0 )
		);

		return $this->limit( $past, $limit );
	}

	/**
	 * The current visibility map (event ID => bool).
	 *
	 * @return array<string, bool>
	 */
	public function visibility_map(): array {
		$map = get_option( RC_RCC_Plugin::OPTION_VISIBILITY, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Whether a given event is currently visible.
	 *
	 * New / unknown events default to visible, as required by the brief.
	 *
	 * @param string $event_id Event identifier.
	 * @return bool
	 */
	public function is_visible( string $event_id ): bool {
		$map = $this->visibility_map();

		// Only an explicit `false` hides an event.
		return ! array_key_exists( $event_id, $map ) || false !== $map[ $event_id ];
	}

	/**
	 * The API/data-source component (for admin refresh + error reporting).
	 *
	 * @return RC_RCC_Api
	 */
	public function api(): RC_RCC_Api {
		return $this->api;
	}

	/**
	 * All races with the visibility filter applied.
	 *
	 * @return RC_RCC_Race[]
	 */
	private function visible_races(): array {
		return array_values(
			array_filter(
				$this->all_races(),
				fn( RC_RCC_Race $race ) => $this->is_visible( $race->id )
			)
		);
	}

	/**
	 * Limit a list, treating a non-positive limit as "no limit".
	 *
	 * @param RC_RCC_Race[] $races List of races.
	 * @param int           $limit Maximum count.
	 * @return RC_RCC_Race[]
	 */
	private function limit( array $races, int $limit ): array {
		if ( $limit <= 0 ) {
			return $races;
		}

		return array_slice( $races, 0, $limit );
	}
}
