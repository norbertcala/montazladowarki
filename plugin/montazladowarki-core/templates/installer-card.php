<?php
/**
 * Karta firmy na liście.
 *
 * @var array $item       id, distance, promoted, verified, nationwide, outside?
 * @var bool  $selectable Czy pokazywać pole „dodaj do zapytania”.
 */
defined( 'ABSPATH' ) || exit;

$id       = (int) $item['id'];
$name     = get_the_title( $id );
$city     = mlc_get_meta( $id, 'city' );
$phone    = mlc_get_meta( $id, 'phone' );
$price    = (int) mlc_get_meta( $id, 'price_from' );
$sep      = mlc_get_meta( $id, 'sep' );
$terms    = get_the_terms( $id, 'mlc_service' );
$terms    = $terms && ! is_wp_error( $terms ) ? $terms : array();
$classes  = array( 'mlc-card' );
if ( ! empty( $item['promoted'] ) ) {
	$classes[] = 'mlc-card--promoted';
}
?>
<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-mlc-card="<?php echo (int) $id; ?>">
	<?php if ( ! empty( $selectable ) ) : ?>
		<label class="mlc-card__select" title="<?php esc_attr_e( 'Dodaj do zapytania', 'mlc' ); ?>">
			<input type="checkbox" name="installers[]" value="<?php echo (int) $id; ?>" data-mlc-pick>
			<span class="mlc-card__select-box" aria-hidden="true"></span>
			<span class="mlc-sr"><?php printf( esc_html__( 'Wyślij zapytanie do: %s', 'mlc' ), esc_html( $name ) ); ?></span>
		</label>
	<?php endif; ?>

	<a class="mlc-card__logo" href="<?php echo esc_url( get_permalink( $id ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail( $id ) ) : ?>
			<?php echo get_the_post_thumbnail( $id, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span><?php echo esc_html( MLC_Frontend::initials( $name ) ); ?></span>
		<?php endif; ?>
	</a>

	<div class="mlc-card__body">
		<div class="mlc-card__badges">
			<?php if ( ! empty( $item['promoted'] ) ) : ?>
				<span class="mlc-badge mlc-badge--promo"><?php esc_html_e( 'Promowane', 'mlc' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['verified'] ) ) : ?>
				<span class="mlc-badge mlc-badge--ok">✓ <?php esc_html_e( 'Zweryfikowana', 'mlc' ); ?></span>
			<?php endif; ?>
			<?php if ( $sep ) : ?>
				<span class="mlc-badge"><?php esc_html_e( 'Uprawnienia SEP', 'mlc' ); ?></span>
			<?php endif; ?>
		</div>
		<h3 class="mlc-card__title"><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( $name ); ?></a></h3>
		<p class="mlc-card__meta">
			<?php if ( $city ) : ?>
				<span><?php echo esc_html( $city ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['nationwide'] ) ) : ?>
				<span><?php esc_html_e( 'Działa w całej Polsce', 'mlc' ); ?></span>
			<?php elseif ( isset( $item['distance'] ) ) : ?>
				<span><?php echo esc_html( mlc_format_distance( (float) $item['distance'] ) ); ?> <?php esc_html_e( 'od Ciebie', 'mlc' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['outside'] ) ) : ?>
				<span class="mlc-muted"><?php esc_html_e( 'poza deklarowanym obszarem — zapytaj o dojazd', 'mlc' ); ?></span>
			<?php endif; ?>
		</p>
		<?php $ex = mlc_excerpt( $id, 22 ); ?>
		<?php if ( $ex ) : ?>
			<p class="mlc-card__excerpt"><?php echo esc_html( $ex ); ?></p>
		<?php endif; ?>
		<?php if ( $terms ) : ?>
			<ul class="mlc-tags">
				<?php foreach ( array_slice( $terms, 0, 4 ) as $t ) : ?>
					<li><?php echo esc_html( $t->name ); ?></li>
				<?php endforeach; ?>
				<?php if ( count( $terms ) > 4 ) : ?>
					<li class="mlc-muted">+<?php echo (int) count( $terms ) - 4; ?></li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div class="mlc-card__side">
		<?php if ( $price ) : ?>
			<p class="mlc-card__price"><small><?php esc_html_e( 'montaż od', 'mlc' ); ?></small> <?php echo esc_html( number_format_i18n( $price ) ); ?> zł</p>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<a class="mlc-btn mlc-btn--ghost mlc-btn--sm" href="<?php echo esc_attr( mlc_tel_href( $phone ) ); ?>"><?php esc_html_e( 'Zadzwoń', 'mlc' ); ?></a>
		<?php endif; ?>
		<a class="mlc-btn mlc-btn--sm" href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php esc_html_e( 'Profil firmy', 'mlc' ); ?></a>
	</div>
</article>
