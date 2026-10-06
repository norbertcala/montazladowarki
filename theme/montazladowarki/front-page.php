<?php
/**
 * Strona główna.
 */
defined( 'ABSPATH' ) || exit;

get_header();
$core = mlt_has_core();
$nums = $core ? mlt_numbers() : array();
?>
<main id="main">
	<section class="mlt-hero">
		<div class="mlt-hero__rings" aria-hidden="true">
			<svg viewBox="0 0 600 600"><circle cx="300" cy="300" r="90"/><circle cx="300" cy="300" r="170"/><circle cx="300" cy="300" r="250"/><circle class="mlt-hero__dot" cx="300" cy="300" r="12"/></svg>
		</div>
		<div class="mlt-wrap mlt-hero__inner">
			<p class="mlc-eyebrow mlt-hero__eyebrow"><span class="mlt-live" aria-hidden="true"></span><?php esc_html_e( 'Instalatorzy wallboxów w całej Polsce', 'mlt' ); ?></p>
			<h1 class="mlt-hero__title"><?php echo wp_kses( __( 'Znajdź instalatora <em>ładowarki</em> do auta elektrycznego w swojej okolicy', 'mlt' ), array( 'em' => array() ) ); ?></h1>
			<p class="mlt-hero__lead"><?php esc_html_e( 'Wpisz adres — pokażemy firmy, które do Ciebie dojeżdżają. Porównaj ceny i uprawnienia, a potem wyślij jedno zapytanie do kilku instalatorów. Bez opłat i bez rejestracji.', 'mlt' ); ?></p>
			<?php
			if ( $core ) {
				echo do_shortcode( '[mlc_search_form size="large"]' );
			}
			?>
			<?php if ( $nums && $nums['installers'] ) : ?>
				<ul class="mlt-hero__nums">
					<li><strong><?php echo esc_html( number_format_i18n( $nums['installers'] ) ); ?></strong> <?php echo esc_html( mlc_plural( $nums['installers'], 'firma', 'firmy', 'firm' ) ); ?></li>
					<li><strong><?php echo esc_html( number_format_i18n( $nums['cities'] ) ); ?></strong> <?php echo esc_html( mlc_plural( $nums['cities'], 'miasto', 'miasta', 'miast' ) ); ?> <?php esc_html_e( 'w zasięgu', 'mlt' ); ?></li>
					<?php if ( $nums['leads'] > 20 ) : ?>
						<li><strong><?php echo esc_html( number_format_i18n( $nums['leads'] ) ); ?></strong> <?php esc_html_e( 'wysłanych zapytań', 'mlt' ); ?></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<section class="mlt-steps mlt-wrap" aria-labelledby="mlt-how">
		<h2 id="mlt-how" class="mlt-h2"><?php esc_html_e( 'Jak to działa', 'mlt' ); ?></h2>
		<ol class="mlt-steps__list">
			<li><span class="mlt-steps__n">1</span><h3><?php esc_html_e( 'Wpisz adres montażu', 'mlt' ); ?></h3><p><?php esc_html_e( 'Miejscowość albo dokładny adres. Możesz też użyć lokalizacji telefonu.', 'mlt' ); ?></p></li>
			<li><span class="mlt-steps__n">2</span><h3><?php esc_html_e( 'Porównaj firmy z okolicy', 'mlt' ); ?></h3><p><?php esc_html_e( 'Widzisz tylko instalatorów, którzy deklarują dojazd pod Twój adres — z cenami, usługami i uprawnieniami.', 'mlt' ); ?></p></li>
			<li><span class="mlt-steps__n">3</span><h3><?php esc_html_e( 'Wyślij jedno zapytanie', 'mlt' ); ?></h3><p><?php esc_html_e( 'Zaznacz kilka firm i opisz potrzeby. Oferty przyjdą prosto na Twój e-mail.', 'mlt' ); ?></p></li>
		</ol>
	</section>

	<?php if ( $core ) : ?>
		<?php $featured = mlt_featured_installers( 4 ); ?>
		<?php if ( $featured ) : ?>
			<section class="mlt-wrap mlt-section" aria-labelledby="mlt-featured">
				<div class="mlt-section__head">
					<h2 id="mlt-featured" class="mlt-h2"><?php esc_html_e( 'Instalatorzy w katalogu', 'mlt' ); ?></h2>
					<a href="<?php echo esc_url( MLC_SEO::hub_url() ); ?>"><?php esc_html_e( 'Przeglądaj według miast →', 'mlt' ); ?></a>
				</div>
				<div class="mlc-cards mlt-featured">
					<?php
					foreach ( $featured as $item ) {
						mlc_template(
							'installer-card.php',
							array(
								'item'       => $item,
								'selectable' => false,
							)
						);
					}
					?>
				</div>
			</section>
		<?php endif; ?>

		<section class="mlt-wrap mlt-section" aria-labelledby="mlt-cities">
			<h2 id="mlt-cities" class="mlt-h2"><?php esc_html_e( 'Montaż ładowarki w Twoim mieście', 'mlt' ); ?></h2>
			<ul class="mlc-linklist mlc-linklist--big">
				<?php foreach ( mlt_popular_cities( 20 ) as $c ) : ?>
					<li><a href="<?php echo esc_url( MLC_SEO::city_url( $c['slug'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a><?php echo $c['count'] ? ' <span>' . (int) $c['count'] . '</span>' : ''; ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="mlt-wrap mlt-section mlt-guide" aria-labelledby="mlt-guide">
		<div class="mlt-guide__intro">
			<h2 id="mlt-guide" class="mlt-h2"><?php esc_html_e( 'Zanim zamówisz montaż wallboxa', 'mlt' ); ?></h2>
			<p><?php esc_html_e( 'Kilka rzeczy, które warto sprawdzić — dzięki nim szybciej porównasz oferty i unikniesz dopłat.', 'mlt' ); ?></p>
		</div>
		<div class="mlt-guide__grid">
			<article><h3><?php esc_html_e( 'Moc przyłącza', 'mlt' ); ?></h3><p><?php esc_html_e( 'Sprawdź na umowie lub rachunku moc przyłączeniową. Ładowarka 11 kW wymaga zasilania trójfazowego; przy małej mocy przyda się dynamiczne zarządzanie mocą.', 'mlt' ); ?></p></article>
			<article><h3><?php esc_html_e( 'Odległość od rozdzielnicy', 'mlt' ); ?></h3><p><?php esc_html_e( 'To ona najbardziej wpływa na cenę: długość i przekrój przewodu oraz sposób jego prowadzenia. Zmierz ją orientacyjnie przed wysłaniem zapytania.', 'mlt' ); ?></p></article>
			<article><h3><?php esc_html_e( 'Uprawnienia i zabezpieczenia', 'mlt' ); ?></h3><p><?php esc_html_e( 'Instalator powinien mieć uprawnienia SEP i dobrać zabezpieczenie różnicowoprądowe z ochroną przed prądem stałym (typ B lub ładowarka z detekcją DC).', 'mlt' ); ?></p></article>
			<article><h3><?php esc_html_e( 'Garaż w bloku', 'mlt' ); ?></h3><p><?php esc_html_e( 'W budynku wielorodzinnym potrzebna jest zgoda zarządcy lub wspólnoty i ustalenie, skąd będzie zasilana ładowarka. Wybierz firmę z doświadczeniem w garażach podziemnych.', 'mlt' ); ?></p></article>
		</div>
	</section>

	<?php if ( $core ) : ?>
		<section class="mlt-band">
			<div class="mlt-wrap mlt-band__inner">
				<div>
					<p class="mlc-eyebrow"><?php esc_html_e( 'Dla firm instalatorskich', 'mlt' ); ?></p>
					<h2 class="mlt-h2"><?php esc_html_e( 'Montujesz ładowarki? Klienci z Twojej okolicy już szukają.', 'mlt' ); ?></h2>
					<p><?php esc_html_e( 'Dodaj firmę za darmo, ustaw obszar dojazdu i odbieraj zapytania prosto na e-mail. Bez prowizji od zleceń.', 'mlt' ); ?></p>
				</div>
				<a class="mlc-btn mlc-btn--primary mlc-btn--lg" href="<?php echo esc_url( mlc_page_url( 'join' ) ); ?>"><?php esc_html_e( 'Dodaj firmę', 'mlt' ); ?></a>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// Treść strony ustawionej jako główna (np. tekst SEO) — nie lista wpisów.
	while ( 'page' === get_option( 'show_on_front' ) && have_posts() ) :
		the_post();
		if ( trim( get_the_content() ) ) :
			?>
			<section class="mlt-wrap mlt-section mlt-prose"><?php the_content(); ?></section>
			<?php
		endif;
	endwhile;
	?>
</main>
<?php
get_footer();
