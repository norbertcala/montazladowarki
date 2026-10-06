<?php
/**
 * Edytor obszarów działania (admin + panel instalatora).
 *
 * @var array $areas
 */
defined( 'ABSPATH' ) || exit;

$areas = $areas ?: array(
	array(
		'place_id'  => 0,
		'label'     => '',
		'radius_km' => 25,
	),
);
?>
<div class="mlc-areas" data-mlc-areas>
	<p class="mlc-muted"><?php esc_html_e( 'Podaj miejscowość i promień dojazdu — tak jak w ogłoszeniach. Możesz dodać do 10 obszarów (np. siedziba + drugi oddział).', 'mlc' ); ?></p>
	<div class="mlc-areas__rows" data-mlc-area-rows>
		<?php foreach ( array_values( $areas ) as $i => $a ) : ?>
			<div class="mlc-area" data-mlc-area>
				<div class="mlc-area__place">
					<input type="text" class="mlc-input" value="<?php echo esc_attr( $a['label'] ); ?>" placeholder="<?php esc_attr_e( 'Miejscowość, np. Piaseczno', 'mlc' ); ?>" autocomplete="off" data-mlc-ac aria-label="<?php esc_attr_e( 'Miejscowość', 'mlc' ); ?>">
					<input type="hidden" name="mlc_areas[<?php echo (int) $i; ?>][place_id]" value="<?php echo (int) $a['place_id']; ?>" data-mlc-ac-id>
				</div>
				<select class="mlc-input" name="mlc_areas[<?php echo (int) $i; ?>][radius_km]" aria-label="<?php esc_attr_e( 'Promień', 'mlc' ); ?>">
					<?php foreach ( mlc_radius_options() as $km => $label ) : ?>
						<option value="<?php echo (int) $km; ?>" <?php selected( (int) $a['radius_km'], $km ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="mlc-area__remove" data-mlc-area-remove aria-label="<?php esc_attr_e( 'Usuń obszar', 'mlc' ); ?>">×</button>
			</div>
		<?php endforeach; ?>
	</div>
	<button type="button" class="mlc-btn mlc-btn--ghost mlc-btn--sm" data-mlc-area-add>+ <?php esc_html_e( 'Dodaj obszar', 'mlc' ); ?></button>
</div>
