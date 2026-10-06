<?php
/**
 * Strona SEO miasta: /montaz-ladowarki/{miasto}/
 * Nadpisz w motywie: mlc/city.php
 */
defined( 'ABSPATH' ) || exit;

$data  = MLC_SEO::city_data();
$city  = $data['city'];
$items = $data['items'];
$stats = $data['stats'];
$name  = $city['name'];

get_header();
?>
<main id="main" class="mlc-page mlc-city">
	<div class="mlc-wrap">
		<nav class="mlc-crumbs" aria-label="<?php esc_attr_e( 'Okruszki', 'mlc' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Start', 'mlc' ); ?></a>
			<span>/</span><a href="<?php echo esc_url( MLC_SEO::hub_url() ); ?>"><?php esc_html_e( 'Montaż ładowarek', 'mlc' ); ?></a>
			<span>/</span><a href="<?php echo esc_url( MLC_SEO::province_url( $city['province'] ) ); ?>"><?php echo esc_html( 'woj. ' . $city['province'] ); ?></a>
			<span>/</span><span aria-current="page"><?php echo esc_html( $name ); ?></span>
		</nav>

		<header class="mlc-city__hero">
			<p class="mlc-eyebrow"><?php echo esc_html( sprintf( __( 'pow. %1$s · woj. %2$s', 'mlc' ), $city['district'], $city['province'] ) ); ?></p>
			<h1><?php echo esc_html( MLC_SEO::city_title( $city ) ); ?></h1>
			<p class="mlc-city__intro">
				<?php if ( $stats['count'] ) : ?>
					<?php
					printf(
						esc_html__( 'Miejscowość %1$s obsługuje %2$d %3$s instalatorskich, które montują wallboxy i ładowarki do samochodów elektrycznych. Porównaj ich usługi, uprawnienia i ceny, a potem wyślij jedno zapytanie do kilku firm naraz.', 'mlc' ),
						esc_html( $name ),
						(int) $stats['count'],
						esc_html( mlc_plural( $stats['count'], 'firma', 'firmy', 'firm' ) )
					);
					?>
				<?php else : ?>
					<?php printf( esc_html__( 'Nie mamy jeszcze firmy, która deklaruje obszar działania obejmujący miejscowość %s. Poniżej najbliżsi instalatorzy — wielu z nich dojeżdża dalej, niż podaje w profilu.', 'mlc' ), esc_html( $name ) ); ?>
				<?php endif; ?>
			</p>

			<?php if ( $stats['count'] ) : ?>
				<dl class="mlc-stats">
					<div><dt><?php esc_html_e( 'Firm w okolicy', 'mlc' ); ?></dt><dd><?php echo (int) $stats['count']; ?></dd></div>
					<?php if ( $stats['price_min'] ) : ?>
						<div><dt><?php esc_html_e( 'Montaż od', 'mlc' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $stats['price_min'] ) ); ?> zł</dd></div>
					<?php endif; ?>
					<div><dt><?php esc_html_e( 'Z uprawnieniami SEP', 'mlc' ); ?></dt><dd><?php echo (int) $stats['sep']; ?></dd></div>
				</dl>
			<?php endif; ?>

			<?php
			mlc_template(
				'search-form.php',
				array(
					'q'     => $name,
					'place' => (int) $city['id'],
					'size'  => 'compact',
				)
			);
			?>
		</header>

		<?php
		$list = $items ? $items : $data['nearest'];
		if ( $list ) {
			mlc_template(
				'listing.php',
				array(
					'items'       => array_slice( $list, 0, 60 ),
					'center'      => array(
						'lat'   => $city['lat'],
						'lng'   => $city['lng'],
						'label' => $name,
					),
					'place_label' => $name,
				)
			);
		}
		?>

		<section class="mlc-faq" aria-labelledby="mlc-faq-title">
			<h2 id="mlc-faq-title"><?php printf( esc_html__( 'Montaż ładowarki — %s: najczęstsze pytania', 'mlc' ), esc_html( $name ) ); ?></h2>
			<?php foreach ( $data['faq'] as $i => $f ) : ?>
				<details <?php echo 0 === $i ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $f[0] ); ?></summary>
					<p><?php echo esc_html( $f[1] ); ?></p>
				</details>
			<?php endforeach; ?>
		</section>

		<?php if ( $data['nearby'] ) : ?>
			<section class="mlc-nearby">
				<h2><?php esc_html_e( 'Instalatorzy w pobliskich miejscowościach', 'mlc' ); ?></h2>
				<ul class="mlc-linklist">
					<?php foreach ( $data['nearby'] as $c ) : ?>
						<?php $n = MLC_Search::city_count( $c['slug'] ); ?>
						<li><a href="<?php echo esc_url( MLC_SEO::city_url( $c['slug'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a><?php echo $n ? ' <span>' . (int) $n . '</span>' : ''; ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<aside class="mlc-cta-installer">
			<div>
				<strong><?php printf( esc_html__( 'Montujesz ładowarki — %s i okolice?', 'mlc' ), esc_html( $name ) ); ?></strong>
				<p><?php esc_html_e( 'Dodaj firmę za darmo. Klienci z Twojego obszaru wyślą Ci zapytania bezpośrednio.', 'mlc' ); ?></p>
			</div>
			<a class="mlc-btn mlc-btn--primary" href="<?php echo esc_url( mlc_page_url( 'join' ) ); ?>"><?php esc_html_e( 'Dodaj firmę', 'mlc' ); ?></a>
		</aside>
	</div>
</main>
<?php
get_footer();
