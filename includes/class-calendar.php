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
	 * Noch nicht gelaufene Rennen, gruppiert nach Jahr.
	 *
	 * Jahre aufsteigend (2026, 2027, …), innerhalb eines Jahres ebenfalls
	 * aufsteigend. Rennen ohne lesbares Datum gelten als kommend, damit sie
	 * nicht verschwinden. Für den Tab „Aktuelle Rennen".
	 *
	 * @return array<int, RC_RCC_Race[]> Year => races.
	 */
	public function current_groups(): array {
		return $this->grouped_by_year( true );
	}

	/**
	 * Bereits gelaufene Rennen, gruppiert nach Jahr.
	 *
	 * Jahre absteigend (2026, 2025, …), innerhalb eines Jahres ebenfalls
	 * absteigend. Enthält auch die abgelaufenen Rennen des laufenden Jahres.
	 * Für den Tab „Vergangene Rennen".
	 *
	 * @return array<int, RC_RCC_Race[]> Year => races.
	 */
	public function archive_groups(): array {
		return $this->grouped_by_year( false );
	}

	/**
	 * Sichtbare Rennen nach kommend/gelaufen trennen und nach Jahr gruppieren.
	 *
	 * Die Trennung läuft über das Renndatum, nicht über das Kalenderjahr: ein
	 * bereits gelaufenes Rennen des laufenden Jahres gehört zu den vergangenen.
	 *
	 * @param bool $future True = noch nicht gelaufen; false = bereits gelaufen.
	 * @return array<int, RC_RCC_Race[]>
	 */
	private function grouped_by_year( bool $future ): array {
		$current = (int) wp_date( 'Y' );
		$groups  = array();

		foreach ( $this->visible_races() as $race ) {
			if ( $race->is_upcoming() !== $future ) {
				continue;
			}

			$year     = $race->year();
			$year_int = ( '' === $year ) ? $current : (int) $year;

			$groups[ $year_int ][] = $race;
		}

		// Ascending within current/future years, descending within past years.
		foreach ( $groups as &$year_races ) {
			usort(
				$year_races,
				static function ( RC_RCC_Race $a, RC_RCC_Race $b ) use ( $future ) {
					$ta = $a->timestamp ?? ( $future ? PHP_INT_MAX : 0 );
					$tb = $b->timestamp ?? ( $future ? PHP_INT_MAX : 0 );

					return $future ? ( $ta <=> $tb ) : ( $tb <=> $ta );
				}
			);
		}
		unset( $year_races );

		if ( $future ) {
			ksort( $groups );
		} else {
			krsort( $groups );
		}

		return $groups;
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
}
