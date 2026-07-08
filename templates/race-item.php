<?php
/**
 * Template: Darstellung eines einzelnen Rennens.
 *
 * @var RC_RCC_Race $race Das anzuzeigende Rennen.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $race ) || ! $race instanceof RC_RCC_Race ) {
	return;
}

// Beschriftungen der optionalen Aktionslinks (Reihenfolge = Anzeigereihenfolge).
$link_labels = array(
	'registration' => __( 'Nennung', 'rc-racemap-club-calendar' ),
	'participants'  => __( 'Teilnehmerliste', 'rc-racemap-club-calendar' ),
	'announcement'  => __( 'Ausschreibung', 'rc-racemap-club-calendar' ),
	'regulations'   => __( 'Reglement', 'rc-racemap-club-calendar' ),
);
?>
<li class="rc-rcc__item">
	<div class="rc-rcc__date">
		<?php if ( null !== $race->timestamp ) : ?>
			<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', $race->timestamp ) ); ?>">
				<?php echo esc_html( $race->formatted_date() ); ?>
			</time>
		<?php else : ?>
			<span><?php echo esc_html( $race->formatted_date() ); ?></span>
		<?php endif; ?>
	</div>

	<div class="rc-rcc__body">
		<h3 class="rc-rcc__title"><?php echo esc_html( $race->title ); ?></h3>

		<div class="rc-rcc__meta">
			<?php if ( '' !== $race->organizer ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__organizer">
					<?php echo esc_html( $race->organizer ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '' !== $race->track ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__track">
					<?php echo esc_html( $race->track ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '' !== $race->status ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__status">
					<?php echo esc_html( $race->status ); ?>
				</span>
			<?php endif; ?>

			<?php if ( null !== $race->participant_count ) : ?>
				<span class="rc-rcc__meta-item rc-rcc__participants">
					<?php
					printf(
						/* translators: %d: Anzahl der Teilnehmer. */
						esc_html( _n( '%d Teilnehmer', '%d Teilnehmer', $race->participant_count, 'rc-racemap-club-calendar' ) ),
						(int) $race->participant_count
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $race->classes ) ) : ?>
			<ul class="rc-rcc__classes">
				<?php foreach ( $race->classes as $class ) : ?>
					<li class="rc-rcc__class"><?php echo esc_html( $class ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $race->has_links() ) : ?>
			<div class="rc-rcc__links">
				<?php foreach ( $link_labels as $key => $label ) : ?>
					<?php if ( ! empty( $race->links[ $key ] ) ) : ?>
						<a
							class="rc-rcc__link rc-rcc__link--<?php echo esc_attr( $key ); ?>"
							href="<?php echo esc_url( $race->links[ $key ] ); ?>"
							rel="noopener noreferrer"
							target="_blank"
						>
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</li>
