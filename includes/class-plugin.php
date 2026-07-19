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
		require_once RC_RCC_PATH . 'includes/class-updater.php';
	}

	/**
	 * Register runtime hooks. Called on `plugins_loaded`.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// GitHub-basierte Auto-Updates (läuft auch bei Cron-Update-Checks,
		// daher unabhängig vom Admin-Kontext registrieren).
		( new RC_RCC_Updater() )->register();

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
