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
	 * Name der Settings-Gruppe (Settings API).
	 */
	private const SETTINGS_GROUP = 'rc_rcc_settings_group';

	/**
	 * admin-post-Aktion zum Speichern der Sichtbarkeit.
	 */
	private const ACTION_SAVE_VISIBILITY = 'rc_rcc_save_visibility';

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

		$races      = $this->calendar->all_races( $force_refresh );
		$visibility = $this->calendar->visibility_map();
		$error      = $this->calendar->api()->last_error();

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

		$map = $this->calendar->visibility_map();

		foreach ( $known_ids as $id ) {
			if ( '' === $id ) {
				continue;
			}
			$map[ $id ] = in_array( $id, $visible_ids, true );
		}

		update_option( RC_RCC_Plugin::OPTION_VISIBILITY, $map, false );

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
			'action'     => self::ACTION_SAVE_VISIBILITY,
			'page_races' => self::PAGE_RACES,
		);
	}
}
