<?php
/**
 * Strona województwa: /montaz-ladowarki/woj-{nazwa}/
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$province = MLC_SEO::$province;
$table    = MLC_Install::table( 'places' );
$cities   = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, slug, weight FROM {$table} WHERE type = 'c' AND province = %s ORDER BY name ASC", $province ), ARRAY_A ); // phpcs:ignore
$counts   = get_option( 'mlc_city_counts', array() );

$with = array();
$rest = array();
foreach ( $cities as $c ) {
	$c['count'] = (int) ( $counts[ $c['slug'] ] ?? 0 );
	if ( $c['count'] ) {
		$with[] = $c;
	} else {
		$rest[] = $c;
	}
}
usort( $with, static fn( $a, $b ) => $b['count'] <=> $a['count'] ?: strcmp( $a['name'], $b['name'] ) );

get_header();
?>
<main id="main" class="mlc-page mlc-hub">
	<div class="mlc-wrap">
		<nav class="mlc-crumbs" aria-label="<?php esc_attr_e( 'Okruszki', 'mlc' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Start', 'mlc' ); ?></a>
			<span>/</span><a href="<?php echo esc_url( MLC_SEO::hub_url() ); ?>"><?php esc_html_e( 'Montaż ładowarek', 'mlc' ); ?></a>
			<span>/</span><span aria-current="page"><?php echo esc_html( 'woj. ' . $province ); ?></span>
		</nav>
		<header class="mlc-city__hero">
			<p class="mlc-eyebrow"><?php esc_html_e( 'Województwo', 'mlc' ); ?></p>
			<h1><?php printf( esc_html__( 'Montaż ładowarek do samochodów elektrycznych — %s', 'mlc' ), esc_html( $province ) ); ?></h1>
			<p class="mlc-city__intro"><?php printf( esc_html__( 'Instalatorzy działają w %1$d z %2$d miast województwa. Wybierz miasto albo wpisz dokładny adres — pokażemy firmy, które do Ciebie dojadą.', 'mlc' ), count( $with ), count( $cities ) ); ?></p>
			<?php
			mlc_template(
				'search-form.php',
				array(
					'q'     => '',
					'place' => 0,
					'size'  => 'compact',
				)
			);
			?>
		</header>

		<?php if ( $with ) : ?>
			<section class="mlc-nearby">
				<h2><?php esc_html_e( 'Miasta z instalatorami', 'mlc' ); ?></h2>
				<ul class="mlc-linklist mlc-linklist--big">
					<?php foreach ( $with as $c ) : ?>
						<li><a href="<?php echo esc_url( MLC_SEO::city_url( $c['slug'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a> <span><?php echo (int) $c['count']; ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $rest ) : ?>
			<details class="mlc-allcities">
				<summary><?php printf( esc_html__( 'Pozostałe miasta (%d)', 'mlc' ), count( $rest ) ); ?></summary>
				<ul class="mlc-linklist">
					<?php foreach ( $rest as $c ) : ?>
						<li><a href="<?php echo esc_url( MLC_SEO::city_url( $c['slug'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
