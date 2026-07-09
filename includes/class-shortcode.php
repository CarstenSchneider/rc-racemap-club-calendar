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

		// Optional per-instance club override (see filter_club_id()).
		$this->club_override = sanitize_text_field( (string) $atts['club'] );

		// Resolve the club ID exactly as the calendar does (setting + runtime
		// filter, incl. this instance's `club=""` override).
		$configured_club = (string) RC_RCC_Plugin::get_setting( 'club_id', '' );
		$effective_club  = trim( (string) apply_filters( 'rc_rcc_runtime_club_id', $configured_club ) );

		// No club configured → render nothing at all. This keeps sites that
		// added the shortcode before entering their MyRCM ID from showing an
		// empty calendar shell. Local sample-data development still works
		// because it deliberately does not require an ID.
		$use_sample = defined( 'RC_RCC_USE_SAMPLE_DATA' ) && RC_RCC_USE_SAMPLE_DATA;
		if ( '' === $effective_club && ! $use_sample ) {
			$this->club_override = '';
			return '';
		}

		// Assets are needed as soon as the shortcode renders content.
		wp_enqueue_style( 'rc-rcc-frontend' );
		wp_enqueue_script( 'rc-rcc-frontend' );

		$current_groups = $this->calendar->current_groups();
		$archive_groups = $this->calendar->archive_groups();

		$this->club_override = '';

		$this->instance++;

		// Variables consumed by the templates.
		$uid             = 'rc-rcc-' . $this->instance;
		$show_logo       = (bool) RC_RCC_Plugin::get_setting( 'show_logo', true );
		$logo_url        = RC_RCC_URL . 'assets/images/rc-racemap-logo.svg';
		$container_style = self::accent_inline_style( (string) RC_RCC_Plugin::get_setting( 'accent_color', '' ) );

		ob_start();
		require RC_RCC_PATH . 'templates/calendar.php';

		return (string) ob_get_clean();
	}

	/**
	 * Build the inline style that pins the accent colour on the calendar root.
	 *
	 * Given a configured hex colour, returns
	 * "--rc-rcc-accent:#hex;--rc-rcc-on-accent:#contrast;" where the on-accent
	 * text colour (used on filled pills/buttons) is black or white depending on
	 * the accent's luminance. Returns '' when no (valid) colour is set, so the
	 * CSS default / theme-derived accent stays in effect.
	 *
	 * @param string $hex Configured accent colour (e.g. "#a3a52c") or ''.
	 * @return string
	 */
	public static function accent_inline_style( string $hex ): string {
		$hex = sanitize_hex_color( trim( $hex ) );

		if ( null === $hex || '' === $hex ) {
			return '';
		}

		// Expand shorthand (#abc → #aabbcc) for the luminance calculation.
		$raw = ltrim( $hex, '#' );
		if ( 3 === strlen( $raw ) ) {
			$raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
		}

		$r = hexdec( substr( $raw, 0, 2 ) );
		$g = hexdec( substr( $raw, 2, 2 ) );
		$b = hexdec( substr( $raw, 4, 2 ) );

		// Perceived brightness (ITU-R BT.601); dark accents get white text.
		$brightness = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
		$on_accent  = ( $brightness < 150 ) ? '#ffffff' : '#111111';

		return '--rc-rcc-accent:' . $hex . ';--rc-rcc-on-accent:' . $on_accent . ';';
	}

	/**
	 * Return an inline SVG icon by name.
	 *
	 * Icons are theme-neutral line icons that inherit the surrounding text
	 * colour via `currentColor`. Kept inline (no external icon library) per the
	 * plugin's "no frontend dependencies" rule. The returned markup is a fixed,
	 * developer-authored constant and therefore safe to echo unescaped.
	 *
	 * @param string $name Icon name.
	 * @return string SVG markup, or '' for an unknown name.
	 */
	public static function icon( string $name ): string {
		$open  = '<svg class="rc-rcc__icon" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
		$close = '</svg>';

		$paths = array(
			// Nennung / registration – clipboard with a check.
			'registration' => '<path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"/><path d="M8 6H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2"/><path d="m9 14 2 2 4-4"/>',
			// Ergebnisse / results – trophy.
			'results'      => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>',
			// Zum Rennen – external link.
			'external'     => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			// Teilnehmer / participants – people.
			'users'        => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3"/><path d="M21 20v-1a4 4 0 0 0-3-3.87M16 5.13A3 3 0 0 1 16 11"/>',
			// Ausschreibung / announcement – document with lines.
			'announcement' => '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4M9 13h6M9 17h6M9 9h1"/>',
			// Reglement / regulations – book.
			'regulations'  => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 0 2 2h13"/><path d="M9 7h6"/>',
			// Sonstiges Dokument – file.
			'document'     => '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return $open . $paths[ $name ] . $close;
	}

	/**
	 * Locate a template, allowing themes to override it.
	 *
	 * Themes can override any template by placing a file at
	 * wp-content/themes/{theme}/rc-racemap-club-calendar/{name}.php
	 *
	 * @param string $name Template file name (e.g. "race-item.php").
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
