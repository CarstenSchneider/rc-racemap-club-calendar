<?php
/**
 * Adminbereich: Einstellungen + Rennenverwaltung.
 *
 * Registriert das Menü "RC RaceMap" mit zwei Seiten:
 *   1. Einstellungen    — Club-ID, Anzahlen, Cache, Logo-Schalter.
 *   2. Rennen verwalten — Ein-/Ausblenden je Rennen, gespeichert per Event-ID.
 *
 * Alle Eingaben werden bereinigt, alle Ausgaben escaped, jede
 * zustandsändernde Aktion ist per Nonce geschützt.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Admin
 */
class RC_RCC_Admin {

	/**
	 * Menü-Slug der Einstellungsseite.
	 */
	private const PAGE_SETTINGS = 'rc-racemap';

	/**
	 * Menü-Slug der Rennenverwaltung.
	 */
	private const PAGE_RACES = 'rc-racemap-races';

	/**
	 * Höchstzahl eigener Dokumente pro Rennen.
	 */
	public const MAX_DOCUMENTS = 5;

	/**
	 * Höchstzahl eigener Termine.
	 */
	public const MAX_CUSTOM_RACES = 50;

	/**
	 * Name der Settings-Gruppe (Settings API).
	 */
	private const SETTINGS_GROUP = 'rc_rcc_settings_group';

	/**
	 * admin-post-Aktion zum Speichern der Sichtbarkeit.
	 */
	private const ACTION_SAVE_VISIBILITY = 'rc_rcc_save_visibility';

	/**
	 * Action zum Einspielen einer Renn-Historie.
	 */
	private const ACTION_IMPORT_ARCHIVE = 'rc_rcc_import_archive';

	/**
	 * Erforderliche Berechtigung für alle Seiten.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Calendar-Komponente.
	 *
	 * @var RC_RCC_Calendar
	 */
	private RC_RCC_Calendar $calendar;

	/**
	 * Konstruktor.
	 *
	 * @param RC_RCC_Calendar $calendar Calendar-Komponente.
	 */
	public function __construct( RC_RCC_Calendar $calendar ) {
		$this->calendar = $calendar;
	}

	/**
	 * Admin-Hooks registrieren.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_VISIBILITY, array( $this, 'handle_save_visibility' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT_ARCHIVE, array( $this, 'handle_import_archive' ) );
		add_filter( 'plugin_action_links_' . RC_RCC_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Menü und Untermenüs registrieren.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'RC RaceMap', 'rc-racemap-club-calendar' ),
			__( 'RC RaceMap', 'rc-racemap-club-calendar' ),
			self::CAPABILITY,
			self::PAGE_SETTINGS,
			array( $this, 'render_settings_page' ),
			'dashicons-flag',
			58
		);

		add_submenu_page(
			self::PAGE_SETTINGS,
			__( 'Einstellungen', 'rc-racemap-club-calendar' ),
			__( 'Einstellungen', 'rc-racemap-club-calendar' ),
			self::CAPABILITY,
			self::PAGE_SETTINGS,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::PAGE_SETTINGS,
			__( 'Rennen verwalten', 'rc-racemap-club-calendar' ),
			__( 'Rennen verwalten', 'rc-racemap-club-calendar' ),
			self::CAPABILITY,
			self::PAGE_RACES,
			array( $this, 'render_races_page' )
		);
	}

	/**
	 * Einstellungen, Abschnitt und Felder über die Settings API registrieren.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			RC_RCC_Plugin::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => RC_RCC_Plugin::default_settings(),
			)
		);

		add_settings_section(
			'rc_rcc_main_section',
			__( 'Kalender-Einstellungen', 'rc-racemap-club-calendar' ),
			static function () {
				echo '<p>' . esc_html__( 'Lege fest, wie dein Vereinskalender abgerufen und angezeigt wird.', 'rc-racemap-club-calendar' ) . '</p>';
			},
			self::PAGE_SETTINGS
		);

		$fields = array(
			'club_id'      => __( 'MyRCM Organisator-ID', 'rc-racemap-club-calendar' ),
			'cache_ttl'    => __( 'Termine aktualisieren', 'rc-racemap-club-calendar' ),
			'accent_color' => __( 'Akzentfarbe', 'rc-racemap-club-calendar' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'rc_rcc_field_' . $key,
				$label,
				array( $this, 'render_field' ),
				self::PAGE_SETTINGS,
				'rc_rcc_main_section',
				array(
					'key'       => $key,
					'label_for' => 'rc_rcc_field_' . $key,
				)
			);
		}

		// Separater Abschnitt für die (technischen) Update-Einstellungen.
		add_settings_section(
			'rc_rcc_updates_section',
			__( 'Automatische Updates', 'rc-racemap-club-calendar' ),
			static function () {
				echo '<p>' . esc_html__( 'Dieses Plugin bezieht Updates aus seinem privaten GitHub-Repository. Dafür wird ein Zugriffstoken benötigt.', 'rc-racemap-club-calendar' ) . '</p>';
			},
			self::PAGE_SETTINGS
		);

		add_settings_field(
			'rc_rcc_field_auto_update',
			__( 'Automatische Updates', 'rc-racemap-club-calendar' ),
			array( $this, 'render_field' ),
			self::PAGE_SETTINGS,
			'rc_rcc_updates_section',
			array(
				'key'       => 'auto_update',
				'label_for' => 'rc_rcc_field_auto_update',
			)
		);

	}

	/**
	 * Die Einstellungen vor dem Speichern bereinigen.
	 *
	 * @param mixed $input Rohe übermittelte Einstellungen.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = RC_RCC_Plugin::default_settings();

		$clean = array();

		$clean['club_id']   = isset( $input['club_id'] ) ? sanitize_text_field( $input['club_id'] ) : '';
		$clean['cache_ttl'] = isset( $input['cache_ttl'] ) ? absint( $input['cache_ttl'] ) : $defaults['cache_ttl'];

		// Akzentfarbe: gültiger Hex oder leer (= Linkfarbe des Themes).
		$clean['accent_color'] = isset( $input['accent_color'] )
			? (string) sanitize_hex_color( trim( (string) $input['accent_color'] ) )
			: '';

		$clean['auto_update'] = ! empty( $input['auto_update'] );

		// Ein Cache von 0 würde die API überlasten; sinnvolles Minimum erzwingen.
		if ( $clean['cache_ttl'] < MINUTE_IN_SECONDS ) {
			$clean['cache_ttl'] = MINUTE_IN_SECONDS;
		}

		return $clean;
	}

	/**
	 * Ein einzelnes Einstellungsfeld ausgeben.
	 *
	 * @param array<string, string> $args Feld-Argumente (erwartet 'key').
	 * @return void
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'] ?? '';
		$value = RC_RCC_Plugin::get_setting( $key );
		$name  = RC_RCC_Plugin::OPTION_SETTINGS . '[' . $key . ']';
		$id    = 'rc_rcc_field_' . $key;

		switch ( $key ) {
			case 'club_id':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				echo '<p class="description">' . esc_html__( 'Die Organisator-ID deines Vereins bei MyRCM – die Nummer, die MyRCM deinem Verein zuweist (z. B. „16961"). Sie steht u. a. in der MyRCM-Adresse deiner Vereinsseite.', 'rc-racemap-club-calendar' ) . '</p>';
				break;

			case 'cache_ttl':
				$this->render_cache_field( $id, $name, (int) $value );
				break;


			case 'accent_color':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="rc-rcc-color-field" data-default-color="" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				echo '<p class="description">' . esc_html__( 'Farbe für Links und den Button. Leer lassen = Linkfarbe deines Themes.', 'rc-racemap-club-calendar' ) . '</p>';
				break;

			case 'auto_update':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html__( 'Neue Versionen dieses Plugins automatisch installieren, sobald sie verfügbar sind.', 'rc-racemap-club-calendar' )
				);
				echo '<p class="description">' . esc_html__( 'Betrifft ausschließlich diesen Kalender. Andere Plugins, dein Theme und WordPress selbst bleiben unberührt.', 'rc-racemap-club-calendar' ) . '</p>';
				break;

		}
	}

	/**
	 * Das Auswahlfeld für die Cache-Dauer ausgeben.
	 *
	 * @param string $id    Feld-ID.
	 * @param string $name  Feldname.
	 * @param int    $value Aktuelle TTL in Sekunden.
	 * @return void
	 */
	private function render_cache_field( string $id, string $name, int $value ): void {
		$options = array(
			15 * MINUTE_IN_SECONDS => __( 'alle 15 Minuten', 'rc-racemap-club-calendar' ),
			HOUR_IN_SECONDS        => __( 'stündlich (empfohlen)', 'rc-racemap-club-calendar' ),
			6 * HOUR_IN_SECONDS    => __( 'alle 6 Stunden', 'rc-racemap-club-calendar' ),
			DAY_IN_SECONDS         => __( 'einmal täglich', 'rc-racemap-club-calendar' ),
		);

		printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
		foreach ( $options as $seconds => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $seconds ),
				selected( $value, $seconds, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Die Renntermine werden auf deiner Seite zwischengespeichert, damit sie sofort laden. Hier legst du fest, wie oft dabei nach Änderungen gesucht wird – etwa nach neuen Terminen oder aktuellen Nennzahlen. Häufiger ist selten nötig: die Renndaten selbst ändern sich meist nur einmal am Tag.', 'rc-racemap-club-calendar' ) . '</p>';
	}

	/**
	 * Die Einstellungsseite ausgeben.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap rc-rcc-admin">
			<h1><?php echo esc_html__( 'RC RaceMap Club Calendar', 'rc-racemap-club-calendar' ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SETTINGS );
				submit_button();
				?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Shortcode', 'rc-racemap-club-calendar' ); ?></h2>
			<p><?php echo esc_html__( 'Füge diesen Shortcode auf einer beliebigen Seite ein, um den Kalender anzuzeigen:', 'rc-racemap-club-calendar' ); ?></p>
			<p><code>[rc_racemap_club_calendar]</code></p>
		</div>
		<?php
	}

	/**
	 * Die Rennenverwaltung ausgeben.
	 *
	 * @return void
	 */
	public function render_races_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// Manuelles Aktualisieren von dieser Seite aus erlauben.
		$force_refresh = isset( $_GET['rc_rcc_refresh'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nur Cache-Umgehung, kein Zustandswechsel.
			&& check_admin_referer( 'rc_rcc_refresh' );

		$all_races  = $this->calendar->all_races( $force_refresh );
		$visibility = $this->calendar->visibility_map();
		$documents  = $this->calendar->documents_map();
		$custom     = $this->calendar->custom_races_raw();
		$titles     = $this->calendar->titles_map();
		$error      = $this->calendar->api()->last_error();

		// Nach Jahr aufteilen; das Archiv reicht mit der Zeit über mehrere
		// Jahre, eine flache Liste wäre dann nicht mehr zu überblicken.
		$years = array();
		foreach ( $all_races as $race ) {
			$year = $race->year();
			$year = ( '' === $year ) ? (string) wp_date( 'Y' ) : $year;

			$years[ $year ][] = $race;
		}

		krsort( $years );

		// Vorauswahl: das laufende Jahr, sonst das jüngste vorhandene.
		$current_year = (string) wp_date( 'Y' );
		$year_keys    = array_keys( $years );
		$default_year = in_array( $current_year, $year_keys, true )
			? $current_year
			: (string) ( $year_keys[0] ?? $current_year );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nur eine Ansichtsauswahl.
		$selected_year = isset( $_GET['rc_rcc_year'] ) ? sanitize_text_field( wp_unslash( $_GET['rc_rcc_year'] ) ) : '';

		if ( ! in_array( $selected_year, $year_keys, true ) ) {
			$selected_year = $default_year;
		}

		$races = $years[ $selected_year ] ?? array();

		$refresh_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'           => self::PAGE_RACES,
					'rc_rcc_refresh' => 1,
				),
				admin_url( 'admin.php' )
			),
			'rc_rcc_refresh'
		);

		$this->render_admin_notice();

		require RC_RCC_PATH . 'includes/views/manage-races.php';
	}

	/**
	 * Das Absenden des Sichtbarkeits-Formulars verarbeiten.
	 *
	 * @return void
	 */
	public function handle_save_visibility(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Dazu bist du nicht berechtigt.', 'rc-racemap-club-calendar' ) );
		}

		check_admin_referer( self::ACTION_SAVE_VISIBILITY );

		// Das Formular listet jede angezeigte Event-ID; nur markierte sind sichtbar.
		$known_ids = isset( $_POST['rc_rcc_known'] ) && is_array( $_POST['rc_rcc_known'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['rc_rcc_known'] ) )
			: array();

		$visible_ids = isset( $_POST['rc_rcc_visible'] ) && is_array( $_POST['rc_rcc_visible'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['rc_rcc_visible'] ) )
			: array();

		$this->save_custom_races();

		$map = $this->calendar->visibility_map();

		foreach ( $known_ids as $id ) {
			if ( '' === $id ) {
				continue;
			}
			$map[ $id ] = in_array( $id, $visible_ids, true );
		}

		update_option( RC_RCC_Plugin::OPTION_VISIBILITY, $map, false );

		$this->save_documents( $known_ids );
		$this->save_titles( $known_ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => self::PAGE_RACES,
					'rc_rcc_updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Nach dem Redirect den "Gespeichert"-Hinweis anzeigen.
	 *
	 * @return void
	 */
	private function render_admin_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
		if ( isset( $_GET['rc_rcc_imported'] ) ) {
			$state = sanitize_text_field( wp_unslash( $_GET['rc_rcc_imported'] ) );
			$count = isset( $_GET['rc_rcc_count'] ) ? absint( $_GET['rc_rcc_count'] ) : 0;

			if ( 'ok' === $state ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: Anzahl der eingespielten Rennen. */
							_n( '%d Rennen ins Archiv übernommen.', '%d Rennen ins Archiv übernommen.', $count, 'rc-racemap-club-calendar' ),
							$count
						)
					)
				);
			} elseif ( 'invalid' === $state ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'Das war kein gültiges JSON. Bitte den kompletten Inhalt der Datei einfügen.', 'rc-racemap-club-calendar' )
				);
			} else {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html__( 'Es wurde nichts übernommen – kein Eintrag hatte Datum und Bezeichnung.', 'rc-racemap-club-calendar' )
				);
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( isset( $_GET['rc_rcc_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reines Anzeige-Flag nach sicherem Redirect.
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Sichtbarkeit der Rennen gespeichert.', 'rc-racemap-club-calendar' )
			);
		}
	}

	/**
	 * Admin-Styles ausschließlich auf den Seiten dieses Plugins laden.
	 *
	 * @param string $hook Aktueller Admin-Seiten-Hook.
	 * @return void
	 */
	/**
	 * Eine Renn-Historie ins Archiv einspielen.
	 *
	 * Gedacht für den Umzug: viele Vereine haben ihre vergangenen Rennen auf
	 * der alten Seite stehen, die API reicht aber nur rund ein halbes Jahr
	 * zurück. Erwartet wird JSON in der Form, die auch die API liefert –
	 * mindestens `from` und `name`/`title` je Eintrag.
	 *
	 * Bestehende Einträge mit derselben ID werden ersetzt, damit sich ein
	 * Import korrigieren lässt.
	 *
	 * @return void
	 */
	public function handle_import_archive(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Dazu bist du nicht berechtigt.', 'rc-racemap-club-calendar' ) );
		}

		check_admin_referer( self::ACTION_IMPORT_ARCHIVE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- direkt darüber geprüft.
		$raw = isset( $_POST['rc_rcc_import'] ) ? (string) wp_unslash( $_POST['rc_rcc_import'] ) : '';

		$raw    = trim( $raw );
		$data   = json_decode( $raw, true );
		$result = 'empty';
		$count  = 0;

		// Kein JSON? Dann als Tabelle lesen – die meisten Vereine haben ihre
		// Historie als Liste und keine Lust auf JSON von Hand.
		if ( ! is_array( $data ) && '' !== $raw ) {
			$data = $this->parse_csv( $raw );
		}

		if ( ! is_array( $data ) ) {
			$result = 'invalid';
		} else {
			// Zwei Formen: eine blosse Liste = nur Archiv; ein Objekt kann
			// zusätzlich Titel und Dokumente für bereits vorhandene Rennen
			// mitbringen (beides pro Event-ID).
			$is_list = array_key_exists( 0, $data ) || empty( $data );
			$rows    = $is_list ? $data : (array) ( $data['archive'] ?? array() );

			$count += $this->import_titles( $is_list ? array() : (array) ( $data['titles'] ?? array() ) );
			$count += $this->import_documents( $is_list ? array() : (array) ( $data['documents'] ?? array() ) );

			$archive = $this->calendar->archive_map();

			// `remove` nimmt Event-IDs aus dem Archiv. Nötig, weil ein Import
			// bestehende Einträge ersetzt, aber nichts entfernt: wird eine Zeile
			// aus der Datei gestrichen oder mit einer anderen zusammengeführt,
			// bliebe die alte sonst als Karteileiche stehen.
			foreach ( (array) ( $is_list ? array() : ( $data['remove'] ?? array() ) ) as $remove_id ) {
				$remove_id = sanitize_text_field( (string) $remove_id );

				if ( '' !== $remove_id && isset( $archive[ $remove_id ] ) ) {
					unset( $archive[ $remove_id ] );
					++$count;
				}
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$from  = $this->sanitize_date( (string) ( $row['from'] ?? '' ) );
				$title = sanitize_text_field( (string) ( $row['name'] ?? $row['title'] ?? '' ) );

				if ( '' === $from || '' === $title ) {
					continue;
				}

				$to = $this->sanitize_date( (string) ( $row['to'] ?? '' ) );

				$id = sanitize_text_field( (string) ( $row['id'] ?? '' ) );
				if ( '' === $id ) {
					$id = 'archive-' . md5( $from . '|' . $title );
				}

				$documents = array();
				foreach ( (array) ( $row['documents'] ?? array() ) as $doc ) {
					if ( ! is_array( $doc ) ) {
						continue;
					}

					$url = esc_url_raw( trim( (string) ( $doc['url'] ?? '' ) ) );

					if ( '' === $url ) {
						continue;
					}

					$documents[] = array(
						'url'   => $url,
						'type'  => sanitize_text_field( (string) ( $doc['type'] ?? '' ) ),
						'label' => sanitize_text_field( (string) ( $doc['label'] ?? '' ) ),
					);
				}

				// Klassen: Strings oder {name, entries} – die Nennzahl je Klasse
				// bleibt erhalten, damit die Pillen sie wie sonst auch zeigen.
				$classes = array();
				foreach ( (array) ( $row['classes'] ?? array() ) as $class ) {
					if ( is_array( $class ) ) {
						$name = sanitize_text_field( (string) ( $class['name'] ?? '' ) );

						if ( '' === $name ) {
							continue;
						}

						$classes[] = isset( $class['entries'] ) && is_numeric( $class['entries'] )
							? array(
								'name'    => $name,
								'entries' => absint( $class['entries'] ),
							)
							: $name;
						continue;
					}

					$name = sanitize_text_field( (string) $class );

					if ( '' !== $name ) {
						$classes[] = $name;
					}
				}

				$archive[ $id ] = array(
					'id'        => $id,
					'name'      => $title,
					'from'      => $from,
					'to'        => ( '' !== $to ) ? $to : $from,
					'url'       => esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ) ),
					'classes'   => $classes,
					'documents' => $documents,
					'source'    => sanitize_text_field( (string) ( $row['source'] ?? 'import' ) ),
				);

				if ( isset( $row['registrationCount'] ) && is_numeric( $row['registrationCount'] ) ) {
					$archive[ $id ]['registrationCount'] = absint( $row['registrationCount'] );
				}

				if ( ! empty( $row['registrationListUrl'] ) ) {
					$archive[ $id ]['registrationListUrl'] = esc_url_raw( trim( (string) $row['registrationListUrl'] ) );
				}

				++$count;
			}

			if ( $count > 0 ) {
				update_option( RC_RCC_Plugin::OPTION_ARCHIVE, $archive, false );
				$result = 'ok';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::PAGE_RACES,
					'rc_rcc_imported' => $result,
					'rc_rcc_count'    => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Die vom Verein selbst angelegten Termine speichern.
	 *
	 * Jede Zeile bringt ihre bestehende ID mit; neue Zeilen bekommen eine
	 * frische. Die ID bleibt über Änderungen hinweg stabil, damit hinterlegte
	 * Dokumente und die Sichtbarkeit am Termin haften bleiben.
	 *
	 * Zeilen ohne Bezeichnung oder ohne Startdatum werden verworfen – das ist
	 * zugleich der Weg, einen Termin wieder zu entfernen.
	 *
	 * @return void
	 */
	private function save_custom_races(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce wurde im Aufrufer geprüft.
		if ( ! isset( $_POST['rc_rcc_custom'] ) || ! is_array( $_POST['rc_rcc_custom'] ) ) {
			return;
		}

		$rows = wp_unslash( $_POST['rc_rcc_custom'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$saved = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
			$from  = $this->sanitize_date( (string) ( $row['from'] ?? '' ) );

			if ( '' === $title || '' === $from ) {
				continue;
			}

			$to = $this->sanitize_date( (string) ( $row['to'] ?? '' ) );

			// Ein Enddatum vor dem Start ist ein Tippfehler – dann lieber eintägig.
			if ( '' !== $to && $to < $from ) {
				$to = '';
			}

			// Klassen kommen als eine Zeile, durch Komma getrennt.
			$classes = array_values(
				array_filter(
					array_map(
						static fn( $c ) => sanitize_text_field( trim( (string) $c ) ),
						explode( ',', (string) ( $row['classes'] ?? '' ) )
					),
					static fn( $c ) => '' !== $c
				)
			);

			$id = sanitize_text_field( (string) ( $row['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = 'custom-' . uniqid();
			}

			$saved[] = array(
				'id'      => $id,
				'title'   => $title,
				'from'    => $from,
				'to'      => $to,
				'classes' => $classes,
				'url'     => esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ) ),
			);

			if ( count( $saved ) >= self::MAX_CUSTOM_RACES ) {
				break;
			}
		}

		update_option( RC_RCC_Plugin::OPTION_CUSTOM_RACES, $saved, false );
	}

	/**
	 * Eine eingefügte Tabelle in Renn-Zeilen übersetzen.
	 *
	 * Erwartet eine Kopfzeile. Erkannt werden – jeweils gross/klein egal:
	 * `von`/`from`, `bis`/`to`, `titel`/`name`, `ausschreibung`, `reglement`,
	 * `ergebnisse`, `teilnehmer`, `klassen`. Alles ausser Datum und Titel ist
	 * freiwillig, unbekannte Spalten werden übergangen.
	 *
	 * Trennzeichen wird geraten (Semikolon oder Komma), Datumsangaben werden
	 * sowohl als `2025-07-26` als auch als `26.07.2025` verstanden.
	 *
	 * @param string $raw Inhalt des Eingabefelds.
	 * @return array<int, array<string, mixed>>|null Zeilen oder null, wenn unbrauchbar.
	 */
	private function parse_csv( string $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$lines = array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );

		if ( count( $lines ) < 2 ) {
			return null;
		}

		$sep = ( substr_count( $lines[0], ';' ) >= substr_count( $lines[0], ',' ) ) ? ';' : ',';

		$head = array_map(
			static function ( $cell ) {
				$cell = strtolower( trim( (string) $cell ) );

				// Umlaute und Sonderzeichen aus der Kopfzeile werfen.
				return preg_replace( '/[^a-z]/', '', strtr( $cell, array( 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 's' ) ) );
			},
			str_getcsv( array_shift( $lines ), $sep, '"', '\\' )
		);

		$alias = array(
			// deutsch
			'von'           => 'from',
			'datum'         => 'from',
			'bis'           => 'to',
			'titel'         => 'name',
			'rennen'        => 'name',
			'ausschreibung' => 'announcement',
			'reglement'     => 'rules',
			'ergebnisse'    => 'results',
			'teilnehmer'    => 'count',
			'klassen'       => 'classes',
			// englisch – Tabellen aus fremden Quellen kommen oft so
			'from'          => 'from',
			'start'         => 'from',
			'to'            => 'to',
			'end'           => 'to',
			'name'          => 'name',
			'title'         => 'name',
			'race'          => 'name',
			'announcement'  => 'announcement',
			'rules'         => 'rules',
			'results'       => 'results',
			'participants'  => 'count',
			'entries'       => 'count',
			'classes'       => 'classes',
		);

		$rows = array();

		foreach ( $lines as $line ) {
			$cells = str_getcsv( $line, $sep, '"', '\\' );
			$row   = array();

			foreach ( $head as $i => $key ) {
				if ( ! isset( $alias[ $key ], $cells[ $i ] ) ) {
					continue;
				}

				$row[ $alias[ $key ] ] = trim( (string) $cells[ $i ] );
			}

			$from = $this->normalize_date( (string) ( $row['from'] ?? '' ) );

			if ( '' === $from || '' === ( $row['name'] ?? '' ) ) {
				continue;
			}

			$documents = array();
			foreach ( array( 'announcement' => 'Ausschreibung', 'rules' => 'Reglement' ) as $key => $label ) {
				if ( ! empty( $row[ $key ] ) ) {
					$documents[] = array(
						'url'   => $row[ $key ],
						'type'  => ( 'announcement' === $key ) ? 'announcement' : 'rules',
						'label' => $label,
					);
				}
			}

			$entry = array(
				'from'      => $from,
				'to'        => $this->normalize_date( (string) ( $row['to'] ?? '' ) ),
				'name'      => $row['name'],
				'url'       => $row['results'] ?? '',
				'documents' => $documents,
				'source'    => 'import',
			);

			if ( ! empty( $row['classes'] ) ) {
				$entry['classes'] = array_values( array_filter( array_map( 'trim', explode( ',', $row['classes'] ) ), 'strlen' ) );
			}

			if ( isset( $row['count'] ) && is_numeric( $row['count'] ) ) {
				$entry['registrationCount'] = (int) $row['count'];
			}

			$rows[] = $entry;
		}

		return $rows ? $rows : null;
	}

	/**
	 * Ein Datum aus einer Tabelle vereinheitlichen.
	 *
	 * Versteht `2025-07-26` und `26.07.2025` (auch einstellig).
	 *
	 * @param string $value Rohwert.
	 * @return string `YYYY-MM-DD` oder ''.
	 */
	private function normalize_date( string $value ): string {
		$value = trim( $value );

		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m ) ) {
			$value = sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
		}

		return $this->sanitize_date( $value );
	}

	/**
	 * Ein Datum aus einem `<input type="date">` prüfen.
	 *
	 * @param string $value Rohwert.
	 * @return string `YYYY-MM-DD` oder '' wenn unbrauchbar.
	 */
	private function sanitize_date( string $value ): string {
		$value = trim( $value );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		[ $y, $m, $d ] = array_map( 'intval', explode( '-', $value ) );

		return checkdate( $m, $d, $y ) ? $value : '';
	}

	/**
	 * Titel-Überschreibungen aus einem Import übernehmen.
	 *
	 * @param array<string, mixed> $titles Event-ID => Titel.
	 * @return int Anzahl übernommener Titel.
	 */
	private function import_titles( array $titles ): int {
		if ( empty( $titles ) ) {
			return 0;
		}

		$map   = $this->calendar->titles_map();
		$count = 0;

		foreach ( $titles as $id => $title ) {
			$id    = sanitize_text_field( (string) $id );
			$title = sanitize_text_field( (string) $title );

			if ( '' === $id ) {
				continue;
			}

			if ( '' === $title ) {
				unset( $map[ $id ] );
				continue;
			}

			$map[ $id ] = $title;
			++$count;
		}

		update_option( RC_RCC_Plugin::OPTION_TITLES, $map, false );

		return $count;
	}

	/**
	 * Dokumente aus einem Import übernehmen.
	 *
	 * Ersetzt die Dokumente des jeweiligen Rennens vollständig, damit ein
	 * korrigierter Import nicht an die alten anhängt.
	 *
	 * @param array<string, mixed> $documents Event-ID => Liste aus {label, url}.
	 * @return int Anzahl übernommener Dokumente.
	 */
	private function import_documents( array $documents ): int {
		if ( empty( $documents ) ) {
			return 0;
		}

		$map   = $this->calendar->documents_map();
		$count = 0;

		foreach ( $documents as $id => $docs ) {
			$id = sanitize_text_field( (string) $id );

			if ( '' === $id || ! is_array( $docs ) ) {
				continue;
			}

			$clean = array();

			foreach ( $docs as $doc ) {
				if ( ! is_array( $doc ) ) {
					continue;
				}

				$url = esc_url_raw( trim( (string) ( $doc['url'] ?? '' ) ) );

				if ( '' === $url ) {
					continue;
				}

				$label = sanitize_text_field( (string) ( $doc['label'] ?? '' ) );

				$clean[] = array(
					'label' => ( '' !== $label ) ? $label : __( 'Dokument', 'rc-racemap-club-calendar' ),
					'url'   => $url,
				);

				if ( count( $clean ) >= self::MAX_DOCUMENTS ) {
					break;
				}
			}

			if ( empty( $clean ) ) {
				unset( $map[ $id ] );
				continue;
			}

			$map[ $id ] = $clean;
			$count     += count( $clean );
		}

		update_option( RC_RCC_Plugin::OPTION_DOCUMENTS, $map, false );

		return $count;
	}

	/**
	 * Die vom Verein gesetzten Titel speichern.
	 *
	 * Gespeichert wird nur, was vom Titel der Quelle abweicht. Ein geleertes
	 * Feld – oder eines, in dem wieder der Originaltitel steht – entfernt die
	 * Überschreibung, sodass künftige Änderungen der Quelle wieder durchschlagen.
	 *
	 * @param string[] $known_ids Event-IDs, die das Formular angezeigt hat.
	 * @return void
	 */
	private function save_titles( array $known_ids ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce wurde im Aufrufer geprüft.
		$posted = isset( $_POST['rc_rcc_title'] ) && is_array( $_POST['rc_rcc_title'] )
			? wp_unslash( $_POST['rc_rcc_title'] )
			: array();

		$originals = isset( $_POST['rc_rcc_title_original'] ) && is_array( $_POST['rc_rcc_title_original'] )
			? wp_unslash( $_POST['rc_rcc_title_original'] )
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$map = $this->calendar->titles_map();

		foreach ( $known_ids as $id ) {
			if ( '' === $id ) {
				continue;
			}

			$title    = sanitize_text_field( (string) ( $posted[ $id ] ?? '' ) );
			$original = sanitize_text_field( (string) ( $originals[ $id ] ?? '' ) );

			if ( '' === $title || $title === $original ) {
				unset( $map[ $id ] );
				continue;
			}

			$map[ $id ] = $title;
		}

		update_option( RC_RCC_Plugin::OPTION_TITLES, $map, false );
	}

	/**
	 * Die vom Verein selbst hinterlegten Dokumente speichern.
	 *
	 * Erwartet zwei parallele Arrays je Event-ID. Zeilen ohne Adresse werden
	 * verworfen; fehlt die Bezeichnung, springt „Dokument" ein, damit der Link
	 * im Kalender nicht namenlos erscheint.
	 *
	 * @param string[] $known_ids Event-IDs, die das Formular angezeigt hat.
	 * @return void
	 */
	private function save_documents( array $known_ids ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce wurde im Aufrufer geprüft.
		$labels = isset( $_POST['rc_rcc_doc_label'] ) && is_array( $_POST['rc_rcc_doc_label'] )
			? wp_unslash( $_POST['rc_rcc_doc_label'] )
			: array();

		$urls = isset( $_POST['rc_rcc_doc_url'] ) && is_array( $_POST['rc_rcc_doc_url'] )
			? wp_unslash( $_POST['rc_rcc_doc_url'] )
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$map = $this->calendar->documents_map();

		foreach ( $known_ids as $id ) {
			if ( '' === $id ) {
				continue;
			}

			$row_labels = isset( $labels[ $id ] ) && is_array( $labels[ $id ] ) ? $labels[ $id ] : array();
			$row_urls   = isset( $urls[ $id ] ) && is_array( $urls[ $id ] ) ? $urls[ $id ] : array();

			$docs = array();

			foreach ( $row_urls as $i => $raw_url ) {
				$url = esc_url_raw( trim( (string) $raw_url ) );

				if ( '' === $url ) {
					continue;
				}

				$label = isset( $row_labels[ $i ] ) ? sanitize_text_field( (string) $row_labels[ $i ] ) : '';

				$docs[] = array(
					'label' => ( '' !== $label ) ? $label : __( 'Dokument', 'rc-racemap-club-calendar' ),
					'url'   => $url,
				);

				// Obergrenze, damit ein manipuliertes Formular die Option nicht aufblaeht.
				if ( count( $docs ) >= self::MAX_DOCUMENTS ) {
					break;
				}
			}

			if ( empty( $docs ) ) {
				unset( $map[ $id ] );
			} else {
				$map[ $id ] = $docs;
			}
		}

		update_option( RC_RCC_Plugin::OPTION_DOCUMENTS, $map, false );
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SETTINGS ) && false === strpos( $hook, self::PAGE_RACES ) ) {
			return;
		}

		wp_enqueue_style(
			'rc-rcc-admin',
			RC_RCC_URL . 'assets/css/admin.css',
			array(),
			RC_RCC_VERSION
		);

		// Farbwähler nur auf der Einstellungsseite.
		if ( false !== strpos( $hook, self::PAGE_SETTINGS ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'rc-rcc-admin',
				RC_RCC_URL . 'assets/js/admin.js',
				array( 'wp-color-picker', 'jquery' ),
				RC_RCC_VERSION,
				true
			);
		}

		// Medienauswahl für eigene Dokumente nur auf der Rennen-Seite.
		if ( false !== strpos( $hook, self::PAGE_RACES ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'rc-rcc-admin',
				RC_RCC_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				RC_RCC_VERSION,
				true
			);
			wp_localize_script(
				'rc-rcc-admin',
				'rcRccAdmin',
				array(
					'mediaTitle'  => __( 'Dokument auswählen', 'rc-racemap-club-calendar' ),
					'mediaButton' => __( 'Dokument übernehmen', 'rc-racemap-club-calendar' ),
				)
			);
		}
	}

	/**
	 * Einen "Einstellungen"-Link in der Plugin-Liste ergänzen.
	 *
	 * @param string[] $links Bestehende Aktionslinks.
	 * @return string[]
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SETTINGS ) ),
			esc_html__( 'Einstellungen', 'rc-racemap-club-calendar' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Aktions- und Nonce-Namen an die View durchreichen.
	 *
	 * @return array{action:string, page_races:string}
	 */
	public static function view_context(): array {
		return array(
			'action'         => self::ACTION_SAVE_VISIBILITY,
			'import_action'  => self::ACTION_IMPORT_ARCHIVE,
			'page_races'     => self::PAGE_RACES,
		);
	}
}
