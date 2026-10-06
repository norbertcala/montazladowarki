<?php
/**
 * Połączenie profilu z wizytówką Google (zapisywany jest tylko place ID).
 *
 * @var int $installer
 */
defined( 'ABSPATH' ) || exit;

if ( ! MLC_Google::enabled() ) {
	return;
}
$pid = MLC_Google::place_id( $installer );
?>
<div class="mlc-gpicker" data-mlc-gpicker data-installer="<?php echo (int) $installer; ?>">
	<p class="mlc-muted"><?php esc_html_e( 'Połącz profil z wizytówką w Mapach Google — na profilu pokażemy aktualną ocenę, liczbę opinii i godziny otwarcia.', 'mlc' ); ?></p>
	<input type="hidden" name="mlc_place_id" value="<?php echo esc_attr( $pid ); ?>" data-mlc-gid>
	<p data-mlc-gcurrent><?php echo $pid ? esc_html__( 'Profil jest połączony z wizytówką Google.', 'mlc' ) : esc_html__( 'Brak połączenia.', 'mlc' ); ?>
		<?php if ( $pid ) : ?><button type="button" class="button-link" data-mlc-gclear><?php esc_html_e( 'odłącz', 'mlc' ); ?></button><?php endif; ?></p>
	<div class="mlc-gpicker__row">
		<input type="text" class="mlc-input" value="<?php echo esc_attr( trim( get_the_title( $installer ) . ' ' . mlc_get_meta( $installer, 'city' ) ) ); ?>" data-mlc-gq aria-label="<?php esc_attr_e( 'Nazwa firmy w Google', 'mlc' ); ?>">
		<button type="button" class="mlc-btn mlc-btn--sm button" data-mlc-gbtn><?php esc_html_e( 'Szukaj w Google', 'mlc' ); ?></button>
	</div>
	<ul class="mlc-gpicker__list" data-mlc-glist></ul>
</div>
