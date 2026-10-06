<?php
/**
 * Hub: /montaz-ladowarki/
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$table  = MLC_Install::table( 'places' );
$counts = get_option( 'mlc_city_counts', array() );
$rows   = $wpdb->get_results( "SELECT name, slug, province, weight FROM {$table} WHERE type = 'c'", ARRAY_A ); // phpcs:ignore

$prov = array();
$top  = array();
foreach ( $rows as $r ) {
	$n = (int) ( $counts[ $r['slug'] ] ?? 0 );
	$prov[ $r['province'] ]['cities'] = ( $prov[ $r['province'] ]['cities'] ?? 0 ) + 1;
	$prov[ $r['province'] ]['with']   = ( $prov[ $r['province'] ]['with'] ?? 0 ) + ( $n ? 1 : 0 );
	if ( $n || 3 === (int) $r['weight'] ) {
		$r['count'] = $n;
		$top[]      = $r;
	}
}
usort( $top, static fn( $a, $b ) => $b['count'] <=> $a['count'] ?: $b['weight'] <=> $a['weight'] ?: strcmp( $a['name'], $b['name'] ) );
$top = array_slice( $top, 0, 48 );

get_header();
?>
<main id="main" class="mlc-page mlc-hub">
	<div class="mlc-wrap">
		<header class="mlc-city__hero">
			<p class="mlc-eyebrow"><?php esc_html_e( 'Cała Polska', 'mlc' ); ?></p>
			<h1><?php esc_html_e( 'Montaż ładowarek do samochodów elektrycznych — instalatorzy w Twojej okolicy', 'mlc' ); ?></h1>
			<p class="mlc-city__intro"><?php esc_html_e( 'Wybierz województwo lub miasto. Każda strona pokazuje firmy, które deklarują dojazd w dane miejsce, ceny montażu i odpowiedzi na najczęstsze pytania.', 'mlc' ); ?></p>
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

		<section class="mlc-nearby">
			<h2><?php esc_html_e( 'Województwa', 'mlc' ); ?></h2>
			<ul class="mlc-provgrid">
				<?php foreach ( MLC_Geo::provinces() as $p ) : ?>
					<li><a href="<?php echo esc_url( MLC_SEO::province_url( $p ) ); ?>">
						<strong><?php echo esc_html( $p ); ?></strong>
						<span><?php printf( esc_html__( '%d miast z instalatorami', 'mlc' ), (int) ( $prov[ $p ]['with'] ?? 0 ) ); ?></span>
					</a></li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="mlc-nearby">
			<h2><?php esc_html_e( 'Popularne miasta', 'mlc' ); ?></h2>
			<ul class="mlc-linklist mlc-linklist--big">
				<?php foreach ( $top as $c ) : ?>
					<li><a href="<?php echo esc_url( MLC_SEO::city_url( $c['slug'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a><?php echo $c['count'] ? ' <span>' . (int) $c['count'] . '</span>' : ''; ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>
</main>
<?php
get_footer();
