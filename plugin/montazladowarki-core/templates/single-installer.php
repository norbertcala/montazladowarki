<?php
/**
 * Profil firmy. Nadpisz w motywie: mlc/single-installer.php
 */
defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$id      = get_the_ID();
	$m       = static fn( $k ) => (string) mlc_get_meta( $id, $k );
	$terms   = get_the_terms( $id, 'mlc_service' );
	$terms   = $terms && ! is_wp_error( $terms ) ? $terms : array();
	$areas   = MLC_Post_Types::get_areas( $id );
	$city_p  = MLC_SEO::installer_city_place( $id );
	$circles = array();
	$nation  = false;
	foreach ( $areas as $a ) {
		if ( (int) $a['radius_km'] >= 1000 ) {
			$nation = true;
			continue;
		}
		$circles[] = array(
			'lat'    => (float) $a['lat'],
			'lng'    => (float) $a['lng'],
			'radius' => (int) $a['radius_km'],
			'label'  => $a['label'],
		);
	}
	$center = $circles ? $circles[0] : array(
		'lat' => 52.07,
		'lng' => 19.48,
	);
	$map    = array(
		'center'  => array(
			'lat'   => $center['lat'],
			'lng'   => $center['lng'],
			'label' => '',
		),
		'circles' => $circles,
		'markers' => array(),
		'zoom'    => $nation ? 6 : null,
	);
	$details = array_filter(
		array(
			__( 'Montaż wallboxa od', 'mlc' )  => $m( 'price_from' ) ? number_format_i18n( (int) $m( 'price_from' ) ) . ' zł' : '',
			__( 'Uprawnienia SEP', 'mlc' )     => $m( 'sep' ) ? __( 'Tak', 'mlc' ) : '',
			__( 'Faktura VAT', 'mlc' )         => $m( 'invoice' ) ? __( 'Tak', 'mlc' ) : '',
			__( 'Gwarancja na montaż', 'mlc' ) => $m( 'warranty' ) ? $m( 'warranty' ) . ' ' . __( 'mies.', 'mlc' ) : '',
			__( 'Marki ładowarek', 'mlc' )     => $m( 'brands' ),
			__( 'Na rynku od', 'mlc' )         => $m( 'founded' ),
			__( 'NIP', 'mlc' )                 => $m( 'nip' ),
			__( 'Adres', 'mlc' )               => trim( implode( ', ', array_filter( array( $m( 'street' ), trim( $m( 'postcode' ) . ' ' . $m( 'city' ) ) ) ) ) ),
		)
	);
	?>
	<main id="main" class="mlc-page mlc-profile">
		<div class="mlc-wrap">
			<nav class="mlc-crumbs" aria-label="<?php esc_attr_e( 'Okruszki', 'mlc' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Start', 'mlc' ); ?></a>
				<span>/</span><a href="<?php echo esc_url( MLC_SEO::hub_url() ); ?>"><?php esc_html_e( 'Montaż ładowarek', 'mlc' ); ?></a>
				<?php if ( $city_p ) : ?>
					<span>/</span><a href="<?php echo esc_url( MLC_SEO::city_url( $city_p['slug'] ) ); ?>"><?php echo esc_html( $city_p['name'] ); ?></a>
				<?php endif; ?>
				<span>/</span><span aria-current="page"><?php the_title(); ?></span>
			</nav>

			<header class="mlc-profile__hero">
				<div class="mlc-card__logo mlc-card__logo--xl">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'medium', array( 'alt' => get_the_title() ) ); ?>
					<?php else : ?>
						<span><?php echo esc_html( MLC_Frontend::initials( get_the_title() ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="mlc-profile__id">
					<div class="mlc-card__badges">
						<?php if ( mlc_is_promoted( $id ) ) : ?><span class="mlc-badge mlc-badge--promo"><?php esc_html_e( 'Promowane', 'mlc' ); ?></span><?php endif; ?>
						<?php if ( mlc_is_verified( $id ) ) : ?><span class="mlc-badge mlc-badge--ok">✓ <?php esc_html_e( 'Zweryfikowana', 'mlc' ); ?></span><?php endif; ?>
						<?php if ( $m( 'sep' ) ) : ?><span class="mlc-badge"><?php esc_html_e( 'Uprawnienia SEP', 'mlc' ); ?></span><?php endif; ?>
					</div>
					<h1><?php the_title(); ?></h1>
					<p class="mlc-card__meta">
						<?php if ( $m( 'city' ) ) : ?><span><?php echo esc_html( $m( 'city' ) ); ?></span><?php endif; ?>
						<span><?php esc_html_e( 'Montaż ładowarek do samochodów elektrycznych', 'mlc' ); ?></span>
					</p>
				</div>
				<div class="mlc-profile__contact">
					<?php if ( $m( 'phone' ) ) : ?>
						<a class="mlc-btn mlc-btn--primary" href="<?php echo esc_attr( mlc_tel_href( $m( 'phone' ) ) ); ?>">☎ <?php echo esc_html( $m( 'phone' ) ); ?></a>
					<?php endif; ?>
					<a class="mlc-btn" href="#mlc-lead"><?php esc_html_e( 'Zapytaj o wycenę', 'mlc' ); ?></a>
					<?php if ( $m( 'website' ) ) : ?>
						<a class="mlc-btn mlc-btn--ghost" href="<?php echo esc_url( $m( 'website' ) ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Strona WWW', 'mlc' ); ?> ↗</a>
					<?php endif; ?>
				</div>
			</header>

			<div class="mlc-profile__grid">
				<div class="mlc-profile__main">
					<?php if ( get_the_content() ) : ?>
						<section class="mlc-prose">
							<h2><?php esc_html_e( 'O firmie', 'mlc' ); ?></h2>
							<?php echo wp_kses_post( wpautop( get_the_content() ) ); ?>
						</section>
					<?php endif; ?>

					<?php if ( $terms ) : ?>
						<section>
							<h2><?php esc_html_e( 'Usługi', 'mlc' ); ?></h2>
							<ul class="mlc-ticks mlc-ticks--cols">
								<?php foreach ( $terms as $t ) : ?>
									<li><?php echo esc_html( $t->name ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<section>
						<h2><?php esc_html_e( 'Obszar działania', 'mlc' ); ?></h2>
						<?php if ( $nation ) : ?>
							<p><strong><?php esc_html_e( 'Firma działa na terenie całej Polski.', 'mlc' ); ?></strong></p>
						<?php endif; ?>
						<?php if ( $circles ) : ?>
							<ul class="mlc-linklist">
								<?php foreach ( $areas as $a ) : ?>
									<?php
									if ( (int) $a['radius_km'] >= 1000 ) {
										continue;
									}
									$ap = MLC_Geo::get_place( (int) $a['place_id'] );
									?>
									<li>
										<?php if ( $ap && 'c' === $ap['type'] ) : ?>
											<a href="<?php echo esc_url( MLC_SEO::city_url( $ap['slug'] ) ); ?>"><?php echo esc_html( $a['label'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $a['label'] ); ?>
										<?php endif; ?>
										<span>+<?php echo (int) $a['radius_km']; ?> km</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<div class="mlc-map mlc-map--profile" data-mlc-map="<?php echo esc_attr( wp_json_encode( $map ) ); ?>" role="img" aria-label="<?php esc_attr_e( 'Mapa obszaru działania', 'mlc' ); ?>"></div>
					</section>
				</div>

				<aside class="mlc-profile__side">
					<?php if ( $details ) : ?>
						<dl class="mlc-details mlc-panel">
							<?php foreach ( $details as $label => $value ) : ?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</aside>
			</div>

			<form class="mlc-panel mlc-profile__lead" method="post" action="#mlc-lead">
				<?php
				mlc_template(
					'lead-fields.php',
					array(
						'place_label' => '',
						'single_id'   => $id,
					)
				);
				?>
			</form>
		</div>
	</main>
	<?php
endwhile;

get_footer();
