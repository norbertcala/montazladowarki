<?php
/**
 * Brak rozpoznanej lokalizacji.
 *
 * @var string $q
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="mlc-results mlc-results--empty">
	<h1 class="mlc-results__title"><?php esc_html_e( 'Gdzie chcesz zamontować ładowarkę?', 'mlc' ); ?></h1>
	<?php if ( $q ) : ?>
		<p class="mlc-errors"><?php printf( esc_html__( 'Nie rozpoznaliśmy lokalizacji „%s”. Zacznij wpisywać nazwę miejscowości i wybierz ją z listy.', 'mlc' ), esc_html( $q ) ); ?></p>
	<?php endif; ?>
	<?php
	mlc_template(
		'search-form.php',
		array(
			'q'     => $q,
			'place' => 0,
			'size'  => 'large',
		)
	);
	?>
</div>
