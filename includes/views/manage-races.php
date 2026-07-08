<?php
/**
 * Admin-View: Rennen verwalten (je Rennen ein-/ausblenden).
 *
 * Erwartete Variablen (von RC_RCC_Admin::render_races_page bereitgestellt):
 *
 * @var RC_RCC_Race[]        $races       Alle Rennen (sichtbar + ausgeblendet).
 * @var array<string, bool>  $visibility  Aktuelle Sichtbarkeits-Zuordnung.
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

	<?php if ( $error instanceof WP_Error ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( $error->get_error_message() ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $races ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php echo esc_html__( 'Noch keine Rennen gefunden. Prüfe die Organisator-ID in den Einstellungen und aktualisiere dann.', 'rc-racemap-club-calendar' ); ?></p>
		</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $ctx['action'] ); ?>" />
			<?php wp_nonce_field( $ctx['action'] ); ?>

			<table class="widefat striped rc-rcc-races-table">
				<thead>
					<tr>
						<td class="check-column"><?php echo esc_html__( 'Anzeigen', 'rc-racemap-club-calendar' ); ?></td>
						<th scope="col"><?php echo esc_html__( 'Datum', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Titel', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Veranstalter', 'rc-racemap-club-calendar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'rc-racemap-club-calendar' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $races as $race ) : ?>
						<?php
						$is_visible = ! array_key_exists( $race->id, $visibility ) || false !== $visibility[ $race->id ];
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
							<td><strong><?php echo esc_html( $race->title ); ?></strong></td>
							<td><?php echo esc_html( $race->organizer ); ?></td>
							<td><?php echo esc_html( $race->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Sichtbarkeit speichern', 'rc-racemap-club-calendar' ) ); ?>
		</form>
	<?php endif; ?>
</div>
