<?php
/**
 * Wyniki wyszukiwania.
 *
 * @var string $q
 * @var array  $loc
 * @var array  $result
 * @var int[]  $services
 * @var int    $page
 * @var array  $nearest
 */
defined( 'ABSPATH' ) || exit;

$total    = (int) $result['total'];
$all_svcs = get_terms(
	array(
		'taxonomy'   => 'mlc_service',
		'hide_empty' => true,
	)
);
$place    = $loc['place'];
$city     = ( $place && 'c' === $place['type'] ) ? $place : ( $place ? MLC_Geo::nearest_place( (float) $place['lat'], (float) $place['lng'], true ) : null );

$pagination = '';
if ( $result['pages'] > 1 ) {
	$pagination = '<nav class="mlc-pagination" aria-label="' . esc_attr__( 'Strony wyników', 'mlc' ) . '">' . paginate_links(
		array(
			'base'      => add_query_arg( 'strona', '%#%' ),
			'format'    => '',
			'current'   => $page,
			'total'     => $result['pages'],
			'prev_text' => '←',
			'next_text' => '→',
		)
	) . '</nav>';
}
?>
<div class="mlc-results">
	<header class="mlc-results__head">
		<?php
		mlc_template(
			'search-form.php',
			array(
				'q'     => $q,
				'place' => $place ? (int) $place['id'] : 0,
				'size'  => 'compact',
			)
		);
		?>
		<h1 class="mlc-results__title">
			<?php if ( $total ) : ?>
				<?php
				printf(
					/* translators: 1: count, 2: noun, 3: place */
					esc_html__( '%1$d %2$s: %3$s', 'mlc' ),
					(int) $total,
					esc_html( mlc_plural( $total, 'firma obsługuje', 'firmy obsługują', 'firm obsługuje' ) ),
					'<span>' . esc_html( $loc['label'] ) . '</span>'
				);
				?>
			<?php else : ?>
				<?php printf( esc_html__( 'Brak firm z obszarem działania obejmującym: %s', 'mlc' ), '<span>' . esc_html( $loc['label'] ) . '</span>' ); ?>
			<?php endif; ?>
		</h1>

		<?php if ( $all_svcs && ! is_wp_error( $all_svcs ) ) : ?>
			<form class="mlc-filters" method="get" action="<?php echo esc_url( mlc_page_url( 'search' ) ); ?>" data-mlc-filters>
				<?php foreach ( array( 'q', 'place', 'lat', 'lng' ) as $k ) : ?>
					<?php if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) : // phpcs:ignore ?>
						<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) ); // phpcs:ignore ?>">
					<?php endif; ?>
				<?php endforeach; ?>
				<span class="mlc-filters__label"><?php esc_html_e( 'Potrzebuję:', 'mlc' ); ?></span>
				<?php foreach ( $all_svcs as $t ) : ?>
					<label class="mlc-chip"><input type="checkbox" name="usluga[]" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( (int) $t->term_id, $services, true ) ); ?>><span><?php echo esc_html( $t->name ); ?></span></label>
				<?php endforeach; ?>
				<noscript><button class="mlc-btn mlc-btn--sm" type="submit"><?php esc_html_e( 'Filtruj', 'mlc' ); ?></button></noscript>
			</form>
		<?php endif; ?>
	</header>

	<?php if ( $total ) : ?>
		<?php
		mlc_template(
			'listing.php',
			array(
				'items'       => $result['items'],
				'center'      => $loc,
				'place_label' => $loc['label'],
				'pagination'  => $pagination,
			)
		);
		?>
	<?php else : ?>
		<div class="mlc-empty">
			<p><?php esc_html_e( 'Katalog dopiero się rozrasta. Poniżej najbliższe firmy — wiele z nich dojedzie także do Ciebie.', 'mlc' ); ?></p>
		</div>
		<?php if ( $nearest ) : ?>
			<?php
			mlc_template(
				'listing.php',
				array(
					'items'       => $nearest,
					'center'      => $loc,
					'place_label' => $loc['label'],
				)
			);
			?>
		<?php endif; ?>
	<?php endif; ?>

	<aside class="mlc-cta-installer">
		<div>
			<strong><?php esc_html_e( 'Montujesz ładowarki w tej okolicy?', 'mlc' ); ?></strong>
			<p><?php esc_html_e( 'Dodaj firmę za darmo i otrzymuj zapytania od klientów z Twojego obszaru.', 'mlc' ); ?></p>
		</div>
		<a class="mlc-btn mlc-btn--primary" href="<?php echo esc_url( mlc_page_url( 'join' ) ); ?>"><?php esc_html_e( 'Dodaj firmę', 'mlc' ); ?></a>
	</aside>

	<?php if ( $city ) : ?>
		<p class="mlc-results__seo"><a href="<?php echo esc_url( MLC_SEO::city_url( $city['slug'] ) ); ?>"><?php printf( esc_html__( 'Montaż ładowarki %s — ceny i najczęstsze pytania →', 'mlc' ), esc_html( $city['name'] ) ); ?></a></p>
	<?php endif; ?>
</div>
