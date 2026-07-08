<?php
/**
 * Aufräumen bei der Deinstallation des Plugins.
 *
 * Wird von WordPress ausgeführt, wenn das Plugin über die Oberfläche gelöscht
 * wird. Entfernt alle vom Plugin angelegten Optionen und Transients, damit
 * keine verwaisten Daten in der Datenbank zurückbleiben.
 *
 * @package RC_RaceMap_Club_Calendar
 */

// Nur im offiziellen Deinstallations-Kontext ausführen.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Optionen entfernen.
 */
$rc_rcc_options = array(
	'rc_rcc_settings',
	'rc_rcc_visibility',
	'rc_rcc_cache_index',
);

foreach ( $rc_rcc_options as $rc_rcc_option ) {
	delete_option( $rc_rcc_option );
}

/**
 * Übrig gebliebene Plugin-Transients entfernen (falls der Index bereits weg war).
 *
 * Der Präfix entspricht RC_RCC_Cache::PREFIX. Prepared Statement über $wpdb.
 */
global $wpdb;

$rc_rcc_like = $wpdb->esc_like( '_transient_rc_rcc_' ) . '%';
$rc_rcc_wild = $wpdb->esc_like( '_transient_timeout_rc_rcc_' ) . '%';

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Einmaliges Aufräumen bei Deinstallation.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$rc_rcc_like,
		$rc_rcc_wild
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
