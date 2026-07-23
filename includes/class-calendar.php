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

		// Nur bei fehlerfreiem Abruf archivieren – sonst würde eine kaputte
		// oder leere Antwort den Bestand einfrieren bzw. verfälschen.
		if ( null === $this->api->last_error() ) {
			// Erst anreichern, dann speichern *und* anzeigen: ein späterer Abruf
			// kann ärmer sein als ein früherer (RCK führt nur kommende Rennen –
			// nach dem Renntag fällt der Merge-Partner weg und übrig bleibt die
			// MyRCM-Sicht mit 0 Nennungen und ohne Teilnehmerlisten).
			$rows  = $this->enrich_rows( $this->api->last_rows() );
			$races = self::races_from_rows( $rows );
			$this->archive_rows( $rows );
		}

		$races = array_merge( $races, $this->archived_races( $races ) );
		$races = $this->suppress_shadowed_dmc( $races );
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
	 * Drop DMC entries that stand for a race MyRCM or RCK already covers.
	 *
	 * Bei DMC melden Vereine ihre Rennen vor allem für die Versicherung; die
	 * Ausschreibung für die Fahrer läuft über MyRCM bzw. RCK. Deshalb sind die
	 * DMC-Titel oft administrativ („Sportkreismeisterschaft") statt so, wie der
	 * Verein das Rennen ausschreibt.
	 *
	 * Die API entdoppelt das bereits – aber nur, solange sie beide Seiten
	 * kennt. MyRCM-Rennen fallen dort nach rund sieben Wochen heraus; danach
	 * steht der DMC-Eintrag allein da und würde neben dem archivierten
	 * MyRCM-Rennen als zweite Zeile für dasselbe Wochenende erscheinen.
	 *
	 * @param RC_RCC_Race[] $races Aktuelle und archivierte Rennen.
	 * @return RC_RCC_Race[]
	 */
	private function suppress_shadowed_dmc( array $races ): array {
		// Zeiträume der Rennen aus den Fahrer-Quellen einsammeln.
		$covered = array();

		foreach ( $races as $race ) {
			$source = strtolower( $race->source );

			if ( false === strpos( $source, 'myrcm' ) && false === strpos( $source, 'rck' ) ) {
				continue;
			}

			if ( null === $race->timestamp ) {
				continue;
			}

			$covered[] = array( $race->timestamp, $race->timestamp_to ?? $race->timestamp );
		}

		if ( empty( $covered ) ) {
			return $races;
		}

		return array_values(
			array_filter(
				$races,
				static function ( RC_RCC_Race $race ) use ( $covered ) {
					if ( 'dmc' !== strtolower( $race->source ) || null === $race->timestamp ) {
						return true;
					}

					$from = $race->timestamp;
					$to   = $race->timestamp_to ?? $race->timestamp;

					foreach ( $covered as $span ) {
						if ( $from <= $span[1] && $span[0] <= $to ) {
							return false;
						}
					}

					return true;
				}
			)
		);
	}

	/**
	 * Beschriftungen, die ein eigenes Dokument als Ergebnisliste kennzeichnen.
	 *
	 * Neben der übersetzten Beschriftung werden das deutsche Quellwort und die
	 * englische Fassung akzeptiert – Vereine tippen die Bezeichnung selbst, und
	 * eine Seite kann in einer anderen Sprache laufen als der Verein schreibt.
	 *
	 * @return string[] Normalisierte Beschriftungen.
	 */
	private static function results_labels(): array {
		$labels = array(
			__( 'Ergebnisse', 'rc-racemap-club-calendar' ),
			'Ergebnisse',
			'Ergebnis',
			'Results',
		);

		return array_values( array_unique( array_map( array( self::class, 'fold_label' ), $labels ) ) );
	}

	/**
	 * Beschriftung für den Vergleich härten.
	 *
	 * Wie `normalise_label()`, zusätzlich ohne Akzente und Sonderzeichen: die
	 * übersetzten Beschriftungen tragen welche („Résultats", „Výsledky"), und
	 * Vereine tippen sie erfahrungsgemäß auch ohne. Nur für den Ergebnis-Abgleich
	 * – die Dokument-Dedup vergleicht weiterhin zeichengetreu, dort ist die
	 * Beschriftung freier Text und soll nicht versehentlich verschmelzen.
	 *
	 * @param string $label Rohe Beschriftung.
	 * @return string Kleingeschrieben, transliteriert, nur a–z und 0–9.
	 */
	private static function fold_label( string $label ): string {
		$map = array(
			'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ą' => 'a', 'ā' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ě' => 'e', 'ę' => 'e', 'ē' => 'e',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
			'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ő' => 'o', 'ø' => 'o',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ů' => 'u', 'ű' => 'u', 'ū' => 'u',
			'ý' => 'y', 'ÿ' => 'y',
			'ç' => 'c', 'č' => 'c', 'ć' => 'c',
			'ł' => 'l', 'ľ' => 'l',
			'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
			'ř' => 'r', 'ŕ' => 'r',
			'š' => 's', 'ś' => 's', 'ş' => 's', 'ß' => 's',
			'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
		);

		return (string) preg_replace( '/[^a-z0-9]/', '', strtr( self::normalise_label( $label ), $map ) );
	}

	/**
	 * Frische Zeilen feldweise mit dem Archiv anreichern.
	 *
	 * Datum, Titel und Nennstatus bleiben bewusst dem frischen Stand
	 * überlassen – Korrekturen an der Quelle sollen ankommen. Nur dort, wo der
	 * frische Wert leer bzw. 0 ist und der gespeicherte Information trägt,
	 * gewinnt der gespeicherte.
	 *
	 * @param array<int, array<string, mixed>> $rows Frische Zeilen.
	 * @return array<int, array<string, mixed>>
	 */
	private function enrich_rows( array $rows ): array {
		$archive = $this->archive_map();

		if ( empty( $archive ) ) {
			return $rows;
		}

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = isset( $row['id'] ) ? (string) $row['id'] : '';

			if ( '' === $id || ! isset( $archive[ $id ] ) || ! is_array( $archive[ $id ] ) ) {
				continue;
			}

			$rows[ $index ] = self::keep_richer( $archive[ $id ], $row );
		}

		return $rows;
	}

	/**
	 * Eine Zeile aus gespeichertem und frischem Stand zusammenführen.
	 *
	 * @param array<string, mixed> $stored Gespeicherte Zeile.
	 * @param array<string, mixed> $fresh  Frische Zeile.
	 * @return array<string, mixed>
	 */
	private static function keep_richer( array $stored, array $fresh ): array {
		$merged = $fresh;

		// Teilnehmerzahl: eine 0 (oder kein Wert) überschreibt keine Zahl.
		$new_count = isset( $fresh['registrationCount'] ) ? (int) $fresh['registrationCount'] : 0;
		$old_count = isset( $stored['registrationCount'] ) ? (int) $stored['registrationCount'] : 0;

		if ( $new_count <= 0 && $old_count > 0 ) {
			$merged['registrationCount'] = $old_count;
		}

		// Adressen und Dokumente nicht durch Leere ersetzen.
		foreach ( array( 'url', 'registrationListUrl', 'documents' ) as $key ) {
			if ( empty( $merged[ $key ] ) && ! empty( $stored[ $key ] ) ) {
				$merged[ $key ] = $stored[ $key ];
			}
		}

		$merged['classes'] = self::keep_richer_classes(
			isset( $stored['classes'] ) && is_array( $stored['classes'] ) ? $stored['classes'] : array(),
			isset( $fresh['classes'] ) && is_array( $fresh['classes'] ) ? $fresh['classes'] : array()
		);

		return $merged;
	}

	/**
	 * Klassen zusammenführen: Nennzahl und Teilnehmer-Link je Klasse erhalten,
	 * wenn der frische Stand sie nicht (mehr) trägt. Zuordnung über den
	 * normalisierten Klassennamen.
	 *
	 * @param array<int, mixed> $stored Gespeicherte Klassen.
	 * @param array<int, mixed> $fresh  Frische Klassen.
	 * @return array<int, mixed>
	 */
	private static function keep_richer_classes( array $stored, array $fresh ): array {
		if ( empty( $fresh ) ) {
			return $stored;
		}

		if ( empty( $stored ) ) {
			return $fresh;
		}

		$by_name = array();
		foreach ( $stored as $class ) {
			if ( is_array( $class ) && isset( $class['name'] ) ) {
				$by_name[ self::normalise_label( (string) $class['name'] ) ] = $class;
			}
		}

		foreach ( $fresh as $index => $class ) {
			if ( ! is_array( $class ) || ! isset( $class['name'] ) ) {
				continue;
			}

			$key = self::normalise_label( (string) $class['name'] );
			$old = isset( $by_name[ $key ] ) && is_array( $by_name[ $key ] ) ? $by_name[ $key ] : null;

			if ( null === $old ) {
				continue;
			}

			$new_entries = isset( $class['entries'] ) ? (int) $class['entries'] : 0;
			$old_entries = isset( $old['entries'] ) ? (int) $old['entries'] : 0;

			if ( $new_entries <= 0 && $old_entries > 0 ) {
				$fresh[ $index ]['entries'] = $old_entries;
			}

			if ( empty( $class['participantsUrl'] ) && ! empty( $old['participantsUrl'] ) ) {
				$fresh[ $index ]['participantsUrl'] = $old['participantsUrl'];
			}
		}

		return $fresh;
	}

	/**
	 * Rennen aus Rohzeilen bauen – wie beim API-Abruf, nur aus den
	 * angereicherten Daten.
	 *
	 * @param array<int, array<string, mixed>> $rows Zeilen.
	 * @return RC_RCC_Race[]
	 */
	private static function races_from_rows( array $rows ): array {
		$races = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$race = RC_RCC_Race::from_array( $row );

			// Ohne stabile ID nicht zuordenbar (Sichtbarkeit, Dokumente).
			if ( '' === $race->id ) {
				continue;
			}

			$races[] = $race;
		}

		return $races;
	}

	/**
	 * Add the delivered rows to the local archive.
	 *
	 * Bereits archivierte Events werden überschrieben, damit spätere
	 * Korrekturen der Quelle – etwa ein nachgetragenes Ergebnis oder ein
	 * korrigierter Titel – ankommen. Die Zeilen kommen bereits durch
	 * `enrich_rows()` gelaufen, sonst würde ein ärmerer Abruf das Archiv
	 * verarmen lassen.
	 *
	 * @param array<int, array<string, mixed>> $rows Rohdaten der API.
	 * @return void
	 */
	private function archive_rows( array $rows ): void {
		if ( empty( $rows ) ) {
			return;
		}

		$archive = $this->archive_map();
		$before  = $archive;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = isset( $row['id'] ) ? (string) $row['id'] : '';

			if ( '' === $id ) {
				continue;
			}

			$archive[ $id ] = $row;
		}

		// Nur schreiben, wenn sich etwas geändert hat – sonst bei jedem
		// Seitenaufruf ein Schreibzugriff auf die Datenbank.
		if ( $archive !== $before ) {
			update_option( RC_RCC_Plugin::OPTION_ARCHIVE, $archive, false );
		}
	}

	/**
	 * The local archive of everything the API has ever delivered.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function archive_map(): array {
		$map = get_option( RC_RCC_Plugin::OPTION_ARCHIVE, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Archived races that the current API response no longer contains.
	 *
	 * @param RC_RCC_Race[] $current Races from the current response.
	 * @return RC_RCC_Race[]
	 */
	private function archived_races( array $current ): array {
		$archive = $this->archive_map();

		if ( empty( $archive ) ) {
			return array();
		}

		// Was die Quelle aktuell liefert, hat Vorrang – es ist frischer.
		$known = array();
		foreach ( $current as $race ) {
			$known[ $race->id ] = true;
		}

		$races = array();

		foreach ( $archive as $id => $row ) {
			if ( isset( $known[ $id ] ) || ! is_array( $row ) ) {
				continue;
			}

			$race = RC_RCC_Race::from_array( $row );

			if ( '' !== $race->id ) {
				$races[] = $race;
			}
		}

		return $races;
	}

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
	 * Compare document labels forgivingly (case and spacing).
	 *
	 * @param string $label Beschriftung.
	 * @return string
	 */
	private static function normalise_label( string $label ): string {
		return function_exists( 'mb_strtolower' )
			? mb_strtolower( trim( $label ) )
			: strtolower( trim( $label ) );
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

		// Beschriftungen der Dokumente, die schon aus der Quelle kommen.
		$source_labels = array(
			'announcement' => __( 'Ausschreibung', 'rc-racemap-club-calendar' ),
			'regulations'  => __( 'Reglement', 'rc-racemap-club-calendar' ),
		);

		foreach ( $races as $race ) {
			foreach ( $map[ $race->id ] ?? array() as $doc ) {
				$label = isset( $doc['label'] ) ? (string) $doc['label'] : '';
				$url   = isset( $doc['url'] ) ? (string) $doc['url'] : '';

				if ( '' === $label || '' === $url ) {
					continue;
				}

				// Gleiche Beschriftung wie ein Dokument der Quelle? Dann ersetzt
				// das eigene es, statt daneben zu stehen. Wer selbst etwas
				// hinterlegt, meint es so – womöglich ist es die korrigierte
				// Fassung.
				$normalised = self::normalise_label( $label );

				// Ein eigenes „Ergebnisse"-Dokument füllt den Ergebnis-Button,
				// statt in der Dokumentspalte zu landen. Damit können Vereine,
				// deren Rennen über RCK laufen, ihr Ergebnis-PDF genauso
				// prominent zeigen wie MyRCM-Vereine ihre Ergebnisseite – dort
				// gibt es keine MyRCM-Ergebnisliste, weil über RCK genannt wird.
				if ( in_array( self::fold_label( $label ), self::results_labels(), true ) ) {
					$race->results_url = $url;
					continue;
				}

				foreach ( $source_labels as $key => $source_label ) {
					if ( self::normalise_label( $source_label ) === $normalised ) {
						unset( $race->links[ $key ] );
					}
				}

				$race->extra_links = array_values(
					array_filter(
						$race->extra_links,
						static fn( $existing ) => self::normalise_label( (string) ( $existing['label'] ?? '' ) ) !== $normalised
					)
				);

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
