<?php
/**
 * Automatische Updates aus dem GitHub-Repository.
 *
 * Bindet die Library "plugin-update-checker" (YahnisElsts) ein, sodass das
 * Plugin Updates aus den GitHub-Releases dieses Repos bezieht. Auf jeder Seite
 * erscheint dann die vertraute WordPress-Aktualisierungsmeldung, sobald ein
 * neuer Release veröffentlicht wurde.
 *
 * Da das Repo privat ist, wird ein Zugriffstoken benötigt. Reihenfolge:
 *   1. Konstante RC_RCC_UPDATE_TOKEN in wp-config.php (empfohlen, am sichersten)
 *   2. Filter 'rc_rcc_update_token'
 *   3. Einstellungsfeld im Adminbereich (Fallback)
 *
 * @package RC_RaceMap_Club_Calendar
 */

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Updater
 */
class RC_RCC_Updater {

	/**
	 * Öffentliche URL des GitHub-Repositories.
	 */
	private const REPO_URL = 'https://github.com/CarstenSchneider/rc-racemap-club-calendar/';

	/**
	 * Update-Checker einrichten.
	 *
	 * @return void
	 */
	public function register(): void {
		$loader = RC_RCC_PATH . 'includes/lib/plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $loader ) ) {
			return;
		}

		require_once $loader;

		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$checker = PucFactory::buildUpdateChecker(
			self::REPO_URL,
			RC_RCC_FILE,
			'rc-racemap-club-calendar'
		);

		// Nur stabile, getaggte Releases als Update anbieten (nicht jeden Push).
		// Das ist bereits das Standardverhalten der Library; hier explizit,
		// damit die Absicht im Code sichtbar ist.
		if ( method_exists( $checker->getVcsApi(), 'setReleaseVersionFilter' ) ) {
			// Optional: nur SemVer-Releases (z. B. "1.0.1", "v1.2.0") berücksichtigen.
			$checker->getVcsApi()->setReleaseVersionFilter( '/^v?\d+\.\d+/' );
		}

		// Authentifizierung für das private Repo.
		$token = $this->token();
		if ( '' !== $token ) {
			$checker->setAuthentication( $token );
		}
	}

	/**
	 * Das Zugriffstoken aus der sichersten verfügbaren Quelle ermitteln.
	 *
	 * @return string
	 */
	private function token(): string {
		// 1. Konstante in wp-config.php.
		if ( defined( 'RC_RCC_UPDATE_TOKEN' ) && is_string( RC_RCC_UPDATE_TOKEN ) ) {
			return trim( RC_RCC_UPDATE_TOKEN );
		}

		/**
		 * 2. Filter zum programmatischen Bereitstellen des Tokens.
		 *
		 * @param string $token Leerer String als Standard.
		 */
		$filtered = apply_filters( 'rc_rcc_update_token', '' );
		if ( is_string( $filtered ) && '' !== $filtered ) {
			return trim( $filtered );
		}

		// 3. Einstellungsfeld (Fallback).
		$token = RC_RCC_Plugin::get_setting( 'update_token', '' );

		return is_string( $token ) ? trim( $token ) : '';
	}
}
