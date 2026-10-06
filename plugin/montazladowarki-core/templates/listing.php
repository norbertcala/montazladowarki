<?php
/**
 * Lista firm z mapą i formularzem jednego zapytania do kilku firm.
 *
 * @var array  $items
 * @var array  $center      lat, lng, label
 * @var string $place_label Miejscowość wpisywana do zapytania.
 * @var string $pagination  HTML paginacji (opcjonalnie).
 */
defined( 'ABSPATH' ) || exit;

$markers = MLC_Frontend::markers( $items );
$map     = array(
	'center'  => array(
		'lat'   => (float) $center['lat'],
		'lng'   => (float) $center['lng'],
		'label' => $center['label'],
	),
	'markers' => $markers,
);
?>
<div class="mlc-listing">
	<div class="mlc-listing__map">
		<div class="mlc-map" data-mlc-map="<?php echo esc_attr( wp_json_encode( $map ) ); ?>" role="img" aria-label="<?php esc_attr_e( 'Mapa firm w okolicy', 'mlc' ); ?>"></div>
	</div>

	<form class="mlc-listing__form" method="post" action="#mlc-lead" data-mlc-leadform>
		<p class="mlc-listing__hint">
			<span class="mlc-hint-dot" aria-hidden="true"></span>
			<?php printf( esc_html__( 'Zaznacz do %d firm i wyślij im jedno zapytanie ofertowe — bezpłatnie.', 'mlc' ), (int) mlc_setting( 'lead_max' ) ); ?>
		</p>
		<div class="mlc-cards">
			<?php
			foreach ( $items as $item ) {
				mlc_template(
					'installer-card.php',
					array(
						'item'       => $item,
						'selectable' => true,
					)
				);
			}
			?>
		</div>
		<?php echo $pagination ?? ''; // phpcs:ignore ?>

		<?php mlc_template( 'lead-fields.php', array( 'place_label' => $place_label ) ); ?>

		<div class="mlc-pickbar" data-mlc-pickbar hidden>
			<span data-mlc-pickcount></span>
			<a class="mlc-btn mlc-btn--primary mlc-btn--sm" href="#mlc-lead"><?php esc_html_e( 'Wyślij zapytanie', 'mlc' ); ?></a>
		</div>
	</form>
</div>
