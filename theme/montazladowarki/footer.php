<?php
/**
 * Stopka.
 */
defined( 'ABSPATH' ) || exit;
$cities = mlt_popular_cities( 16 );
?>
<footer class="mlt-footer">
	<div class="mlt-wrap">
		<div class="mlt-footer__grid">
			<div class="mlt-footer__about">
				<?php mlt_brand(); ?>
				<p><?php esc_html_e( 'Niezależny katalog firm montujących ładowarki do samochodów elektrycznych. Wpisz adres, porównaj instalatorów z okolicy i wyślij jedno zapytanie do kilku firm.', 'mlt' ); ?></p>
			</div>
			<?php if ( $cities ) : ?>
				<div>
					<h2 class="mlt-footer__h"><?php esc_html_e( 'Montaż ładowarki w miastach', 'mlt' ); ?></h2>
					<ul class="mlt-footer__cities">
						<?php foreach ( $cities as $c ) : ?>
							<li><a href="<?php echo esc_url( MLC_SEO::city_url( $c['slug'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<a class="mlt-footer__more" href="<?php echo esc_url( MLC_SEO::hub_url() ); ?>"><?php esc_html_e( 'Wszystkie miasta i województwa →', 'mlt' ); ?></a>
				</div>
			<?php endif; ?>
			<div>
				<h2 class="mlt-footer__h"><?php esc_html_e( 'Dla instalatorów', 'mlt' ); ?></h2>
				<ul class="mlt-footer__links">
					<?php if ( mlt_has_core() ) : ?>
						<li><a href="<?php echo esc_url( mlc_page_url( 'join' ) ); ?>"><?php esc_html_e( 'Dodaj firmę za darmo', 'mlt' ); ?></a></li>
						<li><a href="<?php echo esc_url( mlc_page_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Panel instalatora', 'mlt' ); ?></a></li>
						<li><a href="<?php echo esc_url( mlc_page_url( 'terms' ) ); ?>"><?php esc_html_e( 'Regulamin', 'mlt' ); ?></a></li>
					<?php endif; ?>
					<?php if ( get_privacy_policy_url() ) : ?>
						<li><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Polityka prywatności', 'mlt' ); ?></a></li>
					<?php endif; ?>
				</ul>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'mlt-footer__links',
						'depth'          => 1,
						'fallback_cb'    => '__return_false',
					)
				);
				?>
			</div>
		</div>
		<div class="mlt-footer__bottom">
			<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( mlt_has_core() ? mlc_setting( 'brand' ) : get_bloginfo( 'name' ) ); ?></span>
			<span><?php esc_html_e( 'Mapy: © OpenStreetMap. Miejscowości: PRNG.', 'mlt' ); ?></span>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
