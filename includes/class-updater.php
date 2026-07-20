<?php
/**
 * Automatische Updates aus dem GitHub-Repository.
 *
 * Bindet die Library "plugin-update-checker" (YahnisElsts) ein, sodass das
 * Plugin Updates aus den GitHub-Releases dieses Repos bezieht. Auf jeder Seite
 * erscheint dann die vertraute WordPress-Aktualisierungsmeldung, sobald ein
 * neuer Release veröffentlicht wurde.
 *
 * Das Repository ist öffentlich – ein Token wird nicht benötigt. Für den Fall,
 * dass sich das ändert, bleiben zwei Wege offen (keine Oberfläche):
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

		// Das gebaute Plugin-ZIP bevorzugen, das der Release-Workflow anhängt.
		// Ohne diese Zeile installiert WordPress GitHubs „Source code"-Archiv,
		// also den rohen Repo-Inhalt samt Entwicklungsdateien.
		// PREFER_ (nicht REQUIRE_) heißt: ältere Releases ohne dieses Asset
		// bleiben installierbar, sie fallen auf das Quellarchiv zurück.
		if ( method_exists( $checker->getVcsApi(), 'enableReleaseAssets' ) ) {
			$checker->getVcsApi()->enableReleaseAssets( '/^rc-racemap-club-calendar\.zip$/' );
		}

		// Authentifizierung (nur nötig, falls das Repo privat ist – bei
		// öffentlichem Repo bleibt das Token leer und wird nicht gesetzt).
		$token = $this->token();
		if ( '' !== $token ) {
			$checker->setAuthentication( $token );
		}

		// Optionale automatische Installation von Updates – ausschließlich für
		// dieses Plugin (siehe filter_auto_update).
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update' ), 10, 2 );
	}

	/**
	 * Automatische Updates NUR für dieses Plugin steuern.
	 *
	 * Für jedes andere Plugin wird der eingehende Wert unverändert
	 * zurückgegeben – andere Plugins, Themes und der WordPress-Core bleiben
	 * völlig unberührt.
	 *
	 * @param bool|null $update Bisherige Entscheidung von WordPress.
	 * @param object    $item   Update-Objekt (u. a. mit ->plugin = Basename).
	 * @return bool|null
	 */
	public function filter_auto_update( $update, $item ) {
		if ( is_object( $item ) && isset( $item->plugin ) && RC_RCC_BASENAME === $item->plugin ) {
			return (bool) RC_RCC_Plugin::get_setting( 'auto_update', true );
		}

		return $update;
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
		return '';
	}
}
