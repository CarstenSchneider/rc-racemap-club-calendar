<?php
/**
 * Admin-View: Rennen verwalten (ein-/ausblenden, eigene Dokumente hinterlegen).
 *
 * Erwartete Variablen (von RC_RCC_Admin::render_races_page bereitgestellt):
 *
 * @var RC_RCC_Race[]        $races       Alle Rennen (sichtbar + ausgeblendet).
 * @var array<string, bool>  $visibility  Aktuelle Sichtbarkeits-Zuordnung.
 * @var array<string, array> $documents   Eigene Dokumente je Event-ID.
 * @var array<int, array>    $custom      Selbst angelegte Termine (Rohdaten).
 * @var WP_Error|null        $error       Letzter Abruffehler, falls vorhanden.
 * @var string               $refresh_url Nonce-URL zum erzwungenen Aktualisieren.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

$ctx = RC_RCC_Admin::view_context();
?>
<div class="wrap rc-rcc-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Rennen verwalten', 'rc-racemap-club-calendar' ); ?></h1>
	<a href="<?php echo esc_url( $refresh_url ); ?>" class="page-title-action"><?php echo esc_html__( 'Daten aktualisieren', 'rc-racemap-club-calendar' ); ?></a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Entferne den Haken, um ein Rennen im Kalender auszublenden. Neue Rennen werden automatisch angezeigt. Die Sichtbarkeit wird pro Event-ID gespeichert – ein umbenanntes Rennen behält also seinen Zustand.', 'rc-racemap-club-calendar' ); ?>
	</p>

	<p class="description">
		<?php echo esc_html__( 'In der Spalte „Eigene Dokumente" kannst du je Rennen PDFs hinterlegen – etwa Reglement, Ausschreibung oder Ergebnisse. Sie erscheinen im Kalender hinter den Dokumenten, die MyRCM oder RCK bereits mitliefern.', 'rc-racemap-club-calendar' ); ?>
	</p>

	<?php if ( $error instanceof WP_Error ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( $error->get_error_message() ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $ctx['action'] ); ?>" />
		<?php wp_nonce_field( $ctx['action'] ); ?>

		<?php if ( empty( $races ) ) : ?>
			<div class="notice notice-info inline">
				<p><?php echo esc_html__( 'Noch keine Rennen aus MyRCM, RCK oder DMC gefunden. Prüfe die Organisator-ID in den Einstellungen und aktualisiere dann. Eigene Termine kannst du trotzdem schon anlegen.', 'rc-racemap-club-calendar' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped rc-rcc-races-table">
				<thead>
					<tr>
						<td class="check-column"><?php echo esc_html__( 'Anzeigen', 'rc-racemap-club-calendar' ); ?></td>
						<th scope="col"><?php echo esc_html__( 'Datum', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Titel', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Veranstalter', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Eigene Dokumente', 'rc-racemap-club-calendar' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $races as $race ) : ?>
						<?php
						$is_visible = ! array_key_exists( $race->id, $visibility ) || false !== $visibility[ $race->id ];

						// Bestehende Zeilen plus eine leere zum Ergänzen.
						$rows = isset( $documents[ $race->id ] ) && is_array( $documents[ $race->id ] )
							? array_values( $documents[ $race->id ] )
							: array();

						if ( count( $rows ) < RC_RCC_Admin::MAX_DOCUMENTS ) {
							$rows[] = array(
								'label' => '',
								'url'   => '',
							);
						}
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="hidden" name="rc_rcc_known[]" value="<?php echo esc_attr( $race->id ); ?>" />
								<input
									type="checkbox"
									name="rc_rcc_visible[]"
									value="<?php echo esc_attr( $race->id ); ?>"
									<?php checked( $is_visible ); ?>
								/>
							</th>
							<td><?php echo esc_html( $race->formatted_date() ); ?></td>
							<td>
								<strong><?php echo esc_html( $race->title ); ?></strong>
								<?php if ( $race->is_custom() ) : ?>
									<span class="rc-rcc-own-badge"><?php echo esc_html__( 'eigener Termin', 'rc-racemap-club-calendar' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $race->organizer ); ?></td>
							<td><?php echo esc_html( $race->status ); ?></td>
							<td class="rc-rcc-docs-cell" data-rc-rcc-max="<?php echo esc_attr( (string) RC_RCC_Admin::MAX_DOCUMENTS ); ?>">
								<?php foreach ( $rows as $row ) : ?>
									<div class="rc-rcc-doc-row">
										<input
											type="text"
											class="rc-rcc-doc-label"
											name="rc_rcc_doc_label[<?php echo esc_attr( $race->id ); ?>][]"
											value="<?php echo esc_attr( (string) ( $row['label'] ?? '' ) ); ?>"
											placeholder="<?php echo esc_attr__( 'Bezeichnung, z. B. Reglement', 'rc-racemap-club-calendar' ); ?>"
										/>
										<input
											type="url"
											class="rc-rcc-doc-url"
											name="rc_rcc_doc_url[<?php echo esc_attr( $race->id ); ?>][]"
											value="<?php echo esc_attr( (string) ( $row['url'] ?? '' ) ); ?>"
											placeholder="<?php echo esc_attr__( 'Adresse der Datei', 'rc-racemap-club-calendar' ); ?>"
										/>
										<button type="button" class="button rc-rcc-doc-pick">
											<?php echo esc_html__( 'Datei wählen', 'rc-racemap-club-calendar' ); ?>
										</button>
									</div>
								<?php endforeach; ?>
								<p class="description rc-rcc-doc-hint">
									<?php echo esc_html__( 'Leer lassen, wenn nichts hinterlegt werden soll. Eine Zeile leeren entfernt das Dokument.', 'rc-racemap-club-calendar' ); ?>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 class="rc-rcc-own-heading"><?php echo esc_html__( 'Eigene Termine', 'rc-racemap-club-calendar' ); ?></h2>

		<p class="description">
			<?php echo esc_html__( 'Für Rennen, die weder bei MyRCM noch bei RCK oder DMC stehen – Clublauf, Vereinsmeisterschaft, Trainingstag. Sie erscheinen im Kalender zwischen den übrigen Terminen, nach Datum einsortiert. Um einen Termin zu entfernen, lösche seine Bezeichnung und speichere.', 'rc-racemap-club-calendar' ); ?>
		</p>

		<?php
		$rc_rows = $custom;

		if ( count( $rc_rows ) < RC_RCC_Admin::MAX_CUSTOM_RACES ) {
			$rc_rows[] = array();
		}
		?>

		<table class="widefat striped rc-rcc-own-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Bezeichnung', 'rc-racemap-club-calendar' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Von', 'rc-racemap-club-calendar' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Bis', 'rc-racemap-club-calendar' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Klassen', 'rc-racemap-club-calendar' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Link', 'rc-racemap-club-calendar' ); ?></th>
				</tr>
			</thead>
			<tbody class="rc-rcc-own-body" data-rc-rcc-max="<?php echo esc_attr( (string) RC_RCC_Admin::MAX_CUSTOM_RACES ); ?>">
				<?php foreach ( $rc_rows as $rc_i => $rc_row ) : ?>
					<tr class="rc-rcc-own-row">
						<td>
							<input type="hidden" name="rc_rcc_custom[<?php echo esc_attr( (string) $rc_i ); ?>][id]" value="<?php echo esc_attr( (string) ( $rc_row['id'] ?? '' ) ); ?>" />
							<input
								type="text"
								class="rc-rcc-own-title"
								name="rc_rcc_custom[<?php echo esc_attr( (string) $rc_i ); ?>][title]"
								value="<?php echo esc_attr( (string) ( $rc_row['title'] ?? '' ) ); ?>"
								placeholder="<?php echo esc_attr__( 'z. B. Vereinsmeisterschaft', 'rc-racemap-club-calendar' ); ?>"
							/>
						</td>
						<td>
							<input type="date" name="rc_rcc_custom[<?php echo esc_attr( (string) $rc_i ); ?>][from]" value="<?php echo esc_attr( (string) ( $rc_row['from'] ?? '' ) ); ?>" />
						</td>
						<td>
							<input type="date" name="rc_rcc_custom[<?php echo esc_attr( (string) $rc_i ); ?>][to]" value="<?php echo esc_attr( (string) ( $rc_row['to'] ?? '' ) ); ?>" />
						</td>
						<td>
							<input
								type="text"
								name="rc_rcc_custom[<?php echo esc_attr( (string) $rc_i ); ?>][classes]"
								value="<?php echo esc_attr( implode( ', ', (array) ( $rc_row['classes'] ?? array() ) ) ); ?>"
								placeholder="<?php echo esc_attr__( 'Mit Komma trennen', 'rc-racemap-club-calendar' ); ?>"
							/>
						</td>
						<td>
							<input
								type="url"
								name="rc_rcc_custom[<?php echo esc_attr( (string) $rc_i ); ?>][url]"
								value="<?php echo esc_attr( (string) ( $rc_row['url'] ?? '' ) ); ?>"
								placeholder="<?php echo esc_attr__( 'Optional, z. B. Anmeldung', 'rc-racemap-club-calendar' ); ?>"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php echo esc_html__( 'Bis-Datum nur bei mehrtägigen Rennen ausfüllen. Nach dem Speichern kannst du dem Termin oben Dokumente zuordnen.', 'rc-racemap-club-calendar' ); ?>
		</p>

		<?php submit_button( __( 'Änderungen speichern', 'rc-racemap-club-calendar' ) ); ?>
	</form>
</div>
