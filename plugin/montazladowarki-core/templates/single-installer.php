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

			<?php if ( mlc_is_unclaimed( $id ) ) : ?>
				<?php $src = (string) get_post_meta( $id, '_mlc_source_url', true ); ?>
				<aside class="mlc-unclaimed" id="mlc-claim">
					<div>
						<strong><?php esc_html_e( 'Profil niezweryfikowany', 'mlc' ); ?></strong>
						<p>
							<?php esc_html_e( 'Dane pochodzą z publicznie dostępnych informacji o firmie i mogą być niepełne. Firma nie zarządza jeszcze tym profilem.', 'mlc' ); ?>
							<?php if ( $src ) : ?>
								<a href="<?php echo esc_url( $src ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Źródło', 'mlc' ); ?> ↗</a>
							<?php endif; ?>
						</p>
					</div>
					<a class="mlc-btn mlc-btn--sm" href="<?php echo esc_url( MLC_Claims::claim_url( $id ) ); ?>"><?php esc_html_e( 'To moja firma — przejmij profil', 'mlc' ); ?></a>
				</aside>
			<?php endif; ?>

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
					<?php if ( MLC_Google::enabled() && MLC_Google::place_id( $id ) ) : ?>
						<div class="mlc-g mlc-panel" data-mlc-google="<?php echo (int) $id; ?>" data-has-phone="<?php echo $m( 'phone' ) ? '1' : '0'; ?>" hidden>
							<p class="mlc-eyebrow"><?php esc_html_e( 'Opinie i godziny', 'mlc' ); ?></p>
							<div data-mlc-g-body></div>
							<p class="mlc-g__attr"><?php esc_html_e( 'Źródło:', 'mlc' ); ?> <span>Google Maps</span></p>
						</div>
					<?php endif; ?>
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

			<details class="mlc-report" id="mlc-report" <?php echo ( MLC_Claims::$removal_sent || MLC_Claims::$errors ) ? 'open' : ''; ?>>
				<summary><?php esc_html_e( 'Dane są nieaktualne albo to Twoja firma i nie chcesz tu być? Zgłoś to', 'mlc' ); ?></summary>
				<?php if ( MLC_Claims::$removal_sent ) : ?>
					<p class="mlc-notice mlc-notice--ok"><?php esc_html_e( 'Dziękujemy, zgłoszenie dotarło. Odpowiemy na podany e-mail.', 'mlc' ); ?></p>
				<?php else : ?>
					<form method="post" action="#mlc-report">
						<?php if ( MLC_Claims::$errors ) : ?>
							<div class="mlc-errors" role="alert"><?php echo esc_html( implode( ' ', MLC_Claims::$errors ) ); ?></div>
						<?php endif; ?>
						<?php wp_nonce_field( 'mlc_removal', '_mlc_nonce' ); ?>
						<input type="hidden" name="mlc_action" value="removal">
						<input type="hidden" name="installer" value="<?php echo (int) $id; ?>">
						<input type="hidden" name="_mlc_t" value="<?php echo (int) time(); ?>">
						<div class="mlc-hp" aria-hidden="true"><label>WWW <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
						<div class="mlc-grid">
							<p class="mlc-field"><label for="mlc-rm-type"><?php esc_html_e( 'Czego dotyczy zgłoszenie?', 'mlc' ); ?></label>
								<select id="mlc-rm-type" name="type"><option value="fix"><?php esc_html_e( 'Poprawka danych', 'mlc' ); ?></option><option value="remove"><?php esc_html_e( 'Usunięcie profilu', 'mlc' ); ?></option></select></p>
							<p class="mlc-field"><label for="mlc-rm-email"><?php esc_html_e( 'Twój e-mail', 'mlc' ); ?> *</label>
								<input id="mlc-rm-email" type="email" name="email" required></p>
							<p class="mlc-field mlc-field--full"><label for="mlc-rm-reason"><?php esc_html_e( 'Szczegóły', 'mlc' ); ?></label>
								<textarea id="mlc-rm-reason" name="reason" rows="3"></textarea></p>
						</div>
						<button class="mlc-btn mlc-btn--sm" type="submit"><?php esc_html_e( 'Wyślij zgłoszenie', 'mlc' ); ?></button>
					</form>
				<?php endif; ?>
			</details>
		</div>
	</main>
	<?php
endwhile;

get_footer();
