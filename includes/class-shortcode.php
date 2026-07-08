<?php
/**
 * Frontend shortcode + rendering.
 *
 * Registers [rc_racemap_club_calendar] and renders the two-tab calendar.
 * Both tabs are rendered server-side; switching happens client-side with no
 * page reload. Assets are only loaded on pages that actually use the shortcode.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Shortcode
 */
class RC_RCC_Shortcode {

	/**
	 * The shortcode tag.
	 */
	public const TAG = 'rc_racemap_club_calendar';

	/**
	 * Calendar component.
	 *
	 * @var RC_RCC_Calendar
	 */
	private RC_RCC_Calendar $calendar;

	/**
	 * Counter to give each instance a unique DOM id.
	 *
	 * @var int
	 */
	private int $instance = 0;

	/**
	 * Per-render club ID override ('' = use configured club).
	 *
	 * @var string
	 */
	private string $club_override = '';

	/**
	 * Constructor.
	 *
	 * @param RC_RCC_Calendar $calendar Calendar component.
	 */
	public function __construct( RC_RCC_Calendar $calendar ) {
		$this->calendar = $calendar;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'rc_rcc_runtime_club_id', array( $this, 'filter_club_id' ) );
	}

	/**
	 * Apply the current shortcode's club override, if any.
	 *
	 * Registered once and driven by {@see $club_override}, so it never
	 * removes filters added by other code.
	 *
	 * @param string $club_id Configured club ID.
	 * @return string
	 */
	public function filter_club_id( string $club_id ): string {
		return ( '' !== $this->club_override ) ? $this->club_override : $club_id;
	}

	/**
	 * Register (but do not enqueue) the frontend assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style(
			'rc-rcc-frontend',
			RC_RCC_URL . 'assets/css/frontend.css',
			array(),
			RC_RCC_VERSION
		);

		wp_register_script(
			'rc-rcc-frontend',
			RC_RCC_URL . 'assets/js/frontend.js',
			array(),
			RC_RCC_VERSION,
			true
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				// Optional override of the configured club; enables future
				// multi-club usage without changing the shortcode contract.
				'club' => '',
			),
			$atts,
			self::TAG
		);

		// Assets are needed as soon as the shortcode is on the page.
		wp_enqueue_style( 'rc-rcc-frontend' );
		wp_enqueue_script( 'rc-rcc-frontend' );

		// Optional per-instance club override (see filter_club_id()).
		$this->club_override = sanitize_text_field( (string) $atts['club'] );

		$upcoming = $this->calendar->upcoming_races();
		$archived = $this->calendar->archived_races();

		$this->club_override = '';

		$this->instance++;

		// Variables consumed by the templates.
		$uid       = 'rc-rcc-' . $this->instance;
		$show_logo = (bool) RC_RCC_Plugin::get_setting( 'show_logo', true );
		$logo_url  = RC_RCC_URL . 'assets/images/rc-racemap-logo.svg';

		ob_start();
		require RC_RCC_PATH . 'templates/calendar.php';

		return (string) ob_get_clean();
	}

	/**
	 * Locate a template, allowing themes to override it.
	 *
	 * Themes can override any template by placing a file at
	 * wp-content/themes/{theme}/rc-racemap-club-calendar/{name}.php
	 *
	 * @param string $name Template file name (e.g. "upcoming.php").
	 * @return string Absolute path to the template to load.
	 */
	public static function locate_template( string $name ): string {
		$name      = ltrim( $name, '/' );
		$overridden = locate_template( 'rc-racemap-club-calendar/' . $name );

		if ( '' !== $overridden ) {
			return $overridden;
		}

		return RC_RCC_PATH . 'templates/' . $name;
	}
}
