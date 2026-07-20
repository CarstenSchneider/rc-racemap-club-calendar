<?php
/**
 * Plugin Name:       RC RaceMap Club Calendar
 * Plugin URI:        https://rc-racemap.com/
 * Description:       Displays a single RC model club's upcoming and past races automatically on its WordPress site. Configure the MyRCM organiser ID, drop in a shortcode, done.
 * Version:           1.0.34
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            RC RaceMap
 * Author URI:        https://rc-racemap.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rc-racemap-club-calendar
 * Domain Path:       /languages
 * Update URI:        https://github.com/CarstenSchneider/rc-racemap-club-calendar
 *
 * @package RC_RaceMap_Club_Calendar
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Core plugin constants.
 */
define( 'RC_RCC_VERSION', '1.0.34' );
define( 'RC_RCC_FILE', __FILE__ );
define( 'RC_RCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'RC_RCC_URL', plugin_dir_url( __FILE__ ) );
define( 'RC_RCC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload the plugin classes.
 *
 * The class files live in /includes and follow the WordPress naming
 * convention (class-{slug}.php), so we can map class names to files.
 */
require_once RC_RCC_PATH . 'includes/class-plugin.php';

/**
 * Activation hook.
 *
 * Seeds default settings so the admin screens have sane values on first load.
 */
function rc_rcc_activate() {
	RC_RCC_Plugin::instance()->activate();
}
register_activation_hook( __FILE__, 'rc_rcc_activate' );

/**
 * Deactivation hook.
 *
 * Clears cached API responses so no stale data lingers after deactivation.
 */
function rc_rcc_deactivate() {
	RC_RCC_Plugin::instance()->deactivate();
}
register_deactivation_hook( __FILE__, 'rc_rcc_deactivate' );

/**
 * Boot the plugin once all plugins are loaded.
 */
function rc_rcc_bootstrap() {
	RC_RCC_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'rc_rcc_bootstrap' );
