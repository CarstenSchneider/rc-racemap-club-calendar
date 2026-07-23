<?php
/**
 * Main plugin orchestrator.
 *
 * Wires together the individual components (API, cache, calendar, admin,
 * shortcode). Deliberately kept thin: it owns no business logic, it only
 * loads dependencies and registers the collaborating objects.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Plugin
 */
final class RC_RCC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var RC_RCC_Plugin|null
	 */
	private static ?RC_RCC_Plugin $instance = null;

	/**
	 * Cache component.
	 *
	 * @var RC_RCC_Cache
	 */
	private RC_RCC_Cache $cache;

	/**
	 * API / data-source component.
	 *
	 * @var RC_RCC_Api
	 */
	private RC_RCC_Api $api;

	/**
	 * Calendar (business logic) component.
	 *
	 * @var RC_RCC_Calendar
	 */
	private RC_RCC_Calendar $calendar;

	/**
	 * Settings option key.
	 */
	public const OPTION_SETTINGS = 'rc_rcc_settings';

	/**
	 * Visibility option key (event ID => bool).
	 */
	public const OPTION_VISIBILITY = 'rc_rcc_visibility';

	/**
	 * Option holding per-event documents the club uploaded itself.
	 *
	 * Shape: array<string event_id, array<int, array{label: string, url: string}>>
	 */
	public const OPTION_DOCUMENTS = 'rc_rcc_documents';

	/**
	 * Option holding races the club created itself (not in any source).
	 *
	 * Shape: array<int, array{id: string, title: string, from: string,
	 * to: string, classes: string[], url: string}>
	 */
	public const OPTION_CUSTOM_RACES = 'rc_rcc_custom_races';

	/**
	 * Option holding club-supplied titles that replace the source's own.
	 *
	 * Shape: array<string event_id, string>
	 */
	public const OPTION_TITLES = 'rc_rcc_titles';

	/**
	 * Option holding every event ever delivered by the API.
	 *
	 * Die Quelle liefert nur rund ein halbes Jahr rückwärts. Ohne diese Ablage
	 * würde der Tab „Vergangene Rennen" sich selbst leeren, während die vom
	 * Verein angelegten Termine dauerhaft stehen bleiben – zwei verschiedene
	 * Verhalten in derselben Liste.
	 *
	 * Shape: array<string event_id, array<string, mixed>> (Rohdaten der API)
	 */
	public const OPTION_ARCHIVE = 'rc_rcc_archive';

	/**
	 * Option holding the source's data timestamp (Unix time).
	 *
	 * Die „Stand:"-Anzeige liest sie. Es ist der **Daten-Stand** – wann die
	 * Quelle zuletzt importiert hat, nicht wann das Plugin abgerufen hat. Kommt
	 * aus dem API-Feld `generatedAt` oder dem `Last-Modified`-Header. Liefert
	 * die API keinen, bleibt sie leer und die Anzeige entfällt (lieber nichts
	 * als eine vorgetäuschte Frische).
	 */
	public const OPTION_DATA_STAMP = 'rc_rcc_data_stamp';

	/**
	 * Option holding the plugin version last seen at runtime.
	 *
	 * Weicht sie von RC_RCC_VERSION ab, gab es ein Update – dann wird der
	 * Renndaten-Cache einmal geleert. Auto-Updates deaktivieren das Plugin
	 * nicht, also greift die deactivate()-Leerung dabei nicht; ohne diesen
	 * Abgleich blieben nach einem Update die alten Daten bis zum Cache-Ablauf.
	 */
	public const OPTION_VERSION = 'rc_rcc_version';

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return RC_RCC_Plugin
	 */
	public static function instance(): RC_RCC_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — load dependencies.
	 */
	private function __construct() {
		$this->load_dependencies();

		$this->cache    = new RC_RCC_Cache();
		$this->api      = new RC_RCC_Api( $this->cache );
		$this->calendar = new RC_RCC_Calendar( $this->api );
	}

	/**
	 * Require the class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once RC_RCC_PATH . 'includes/class-race.php';
		require_once RC_RCC_PATH . 'includes/class-cache.php';
		require_once RC_RCC_PATH . 'includes/class-api.php';
		require_once RC_RCC_PATH . 'includes/class-calendar.php';
		require_once RC_RCC_PATH . 'includes/class-admin.php';
		require_once RC_RCC_PATH . 'includes/class-shortcode.php';
		// Der GitHub-Selbst-Updater ist optional: der WordPress.org-Build lässt
		// class-updater.php (und die plugin-update-checker-Lib) weg — Updates
		// kommen dort über den WP-Core. Nur laden, wenn vorhanden.
		if ( is_readable( RC_RCC_PATH . 'includes/class-updater.php' ) ) {
			require_once RC_RCC_PATH . 'includes/class-updater.php';
		}
	}

	/**
	 * Register runtime hooks. Called on `plugins_loaded`.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ) );

		// GitHub-basierte Auto-Updates (läuft auch bei Cron-Update-Checks,
		// daher unabhängig vom Admin-Kontext registrieren). Im WP.org-Build
		// fehlt die Klasse → übersprungen (Updates via WP-Core).
		if ( class_exists( 'RC_RCC_Updater' ) ) {
			( new RC_RCC_Updater() )->register();
		}

		// Admin UI only in wp-admin.
		if ( is_admin() ) {
			$admin = new RC_RCC_Admin( $this->calendar );
			$admin->register();
		}

		// Frontend shortcode is always registered (needed for AJAX + render).
		$shortcode = new RC_RCC_Shortcode( $this->calendar );
		$shortcode->register();
	}

	/**
	 * Load the plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'rc-racemap-club-calendar',
			false,
			dirname( RC_RCC_BASENAME ) . '/languages'
		);
	}

	/**
	 * Activation routine — seed default settings.
	 *
	 * @return void
	 */
	public function activate(): void {
		$defaults = self::default_settings();
		$existing = get_option( self::OPTION_SETTINGS, array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		update_option( self::OPTION_SETTINGS, array_merge( $defaults, $existing ) );
	}

	/**
	 * Nach einem Plugin-Update einmal den Renndaten-Cache leeren.
	 *
	 * Sorgt dafür, dass datenabhängige Änderungen (z. B. neue Felder aus der
	 * API) sofort greifen, statt bis zum Cache-Ablauf zu warten. Läuft nur,
	 * wenn sich die Version geändert hat.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		if ( get_option( self::OPTION_VERSION ) === RC_RCC_VERSION ) {
			return;
		}

		$this->cache->flush_all();
		update_option( self::OPTION_VERSION, RC_RCC_VERSION );
	}

	/**
	 * Deactivation routine — flush cached responses.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->cache->flush_all();
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'club_id'        => '',
			'cache_ttl'      => HOUR_IN_SECONDS,
			'accent_color'   => '',
			'auto_update'    => true,
		);
	}

	/**
	 * Convenience accessor for a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = get_option( self::OPTION_SETTINGS, array() );
		$defaults = self::default_settings();

		if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $defaults[ $key ] ?? $default;
	}

	/**
	 * Expose the calendar component (used by tests / extensions).
	 *
	 * @return RC_RCC_Calendar
	 */
	public function calendar(): RC_RCC_Calendar {
		return $this->calendar;
	}
}
