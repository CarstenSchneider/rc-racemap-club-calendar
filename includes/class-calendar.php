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

		$races = $this->api->get_events( $club_id, $ttl, $force_refresh );
		$races = array_merge( $races, $this->custom_races() );
		$this->apply_custom_titles( $races );
		$this->attach_custom_documents( $races );
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
	/**
	 * The club's own races, as stored raw in the option.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function custom_races_raw(): array {
		$rows = get_option( RC_RCC_Plugin::OPTION_CUSTOM_RACES, array() );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		// Nach Startdatum sortieren, damit die Verwaltungsliste ruhig bleibt.
		usort(
			$rows,
			static fn( $a, $b ) => strcmp( (string) ( $a['from'] ?? '' ), (string) ( $b['from'] ?? '' ) )
		);

		return array_values( $rows );
	}

	/**
	 * The club's own races as race objects.
	 *
	 * Sie durchlaufen dieselbe Normalisierung wie die Daten aus der API, damit
	 * Datum, Klassen und Links überall gleich behandelt werden.
	 *
	 * @return RC_RCC_Race[]
	 */
	public function custom_races(): array {
		$races = array();

		foreach ( $this->custom_races_raw() as $row ) {
			$id    = isset( $row['id'] ) ? (string) $row['id'] : '';
			$title = isset( $row['title'] ) ? (string) $row['title'] : '';
			$from  = isset( $row['from'] ) ? (string) $row['from'] : '';

			// Ohne Bezeichnung oder Startdatum ist der Termin nicht darstellbar.
			if ( '' === $id || '' === $title || '' === $from ) {
				continue;
			}

			$races[] = RC_RCC_Race::from_array(
				array(
					'id'      => $id,
					'name'    => $title,
					'from'    => $from,
					'to'      => isset( $row['to'] ) ? (string) $row['to'] : '',
					'classes' => isset( $row['classes'] ) && is_array( $row['classes'] ) ? $row['classes'] : array(),
					'url'     => isset( $row['url'] ) ? (string) $row['url'] : '',
					'source'  => 'custom',
				)
			);
		}

		return $races;
	}

	/**
	 * Titles the club set itself, keyed by event ID.
	 *
	 * @return array<string, string>
	 */
	public function titles_map(): array {
		$map = get_option( RC_RCC_Plugin::OPTION_TITLES, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Replace source titles with the club's own where one is set.
	 *
	 * `original_title` bleibt unangetastet – das Backend zeigt darüber weiter,
	 * was die Quelle liefert, und ein geleertes Feld stellt sie wieder her.
	 *
	 * @param RC_RCC_Race[] $races Races to adjust (objects, by handle).
	 * @return void
	 */
	private function apply_custom_titles( array $races ): void {
		$map = $this->titles_map();

		if ( empty( $map ) ) {
			return;
		}

		foreach ( $races as $race ) {
			$title = isset( $map[ $race->id ] ) ? trim( (string) $map[ $race->id ] ) : '';

			if ( '' !== $title ) {
				$race->title = $title;
			}
		}
	}

	/**
	 * Documents the club uploaded itself, keyed by event ID.
	 *
	 * @return array<string, array<int, array{label: string, url: string}>>
	 */
	public function documents_map(): array {
		$map = get_option( RC_RCC_Plugin::OPTION_DOCUMENTS, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Append the club's own documents to each race.
	 *
	 * They land in `extra_links` and therefore render in the documents column
	 * alongside the ones the API delivers – behind them, so an official
	 * announcement stays first.
	 *
	 * @param RC_RCC_Race[] $races Races to enrich (by reference).
	 * @return void
	 */
	private function attach_custom_documents( array $races ): void {
		$map = $this->documents_map();

		if ( empty( $map ) ) {
			return;
		}

		foreach ( $races as $race ) {
			foreach ( $map[ $race->id ] ?? array() as $doc ) {
				$label = isset( $doc['label'] ) ? (string) $doc['label'] : '';
				$url   = isset( $doc['url'] ) ? (string) $doc['url'] : '';

				if ( '' === $label || '' === $url ) {
					continue;
				}

				$race->extra_links[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}
	}

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
