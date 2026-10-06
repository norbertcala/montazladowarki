<?php
/**
 * Motyw Montażładowarki.
 */

defined( 'ABSPATH' ) || exit;

define( 'MLT_VERSION', '0.1.0' );

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'mlt', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 48,
				'width'       => 240,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		register_nav_menus(
			array(
				'primary' => __( 'Menu główne', 'mlt' ),
				'footer'  => __( 'Menu w stopce', 'mlt' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'mlt-fonts', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap', array(), null );
		wp_enqueue_style( 'mlt', get_template_directory_uri() . '/assets/css/theme.css', array(), MLT_VERSION );
		wp_enqueue_script( 'mlt', get_template_directory_uri() . '/assets/js/theme.js', array(), MLT_VERSION, true );
		if ( wp_style_is( 'mlc-core', 'registered' ) ) {
			wp_enqueue_style( 'mlc-core' );
		}
	},
	20
);

/** Preconnect do fontów i kafelków mapy. */
add_filter(
	'wp_resource_hints',
	static function ( array $urls, string $type ) {
		if ( 'preconnect' === $type ) {
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
			$urls[] = 'https://fonts.googleapis.com';
		}
		return $urls;
	},
	10,
	2
);

/** Odchudzenie <head> — szybkość ma znaczenie dla SEO. */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

/** Skrypty z defer. */
add_filter(
	'script_loader_tag',
	static function ( string $tag, string $handle ) {
		if ( in_array( $handle, array( 'mlt', 'mlc-search', 'mlc-autocomplete', 'mlc-map', 'mlc-areas', 'leaflet' ), true ) && ! str_contains( $tag, ' defer' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}
		return $tag;
	},
	10,
	2
);

/**
 * Czy wtyczka katalogu jest aktywna.
 */
function mlt_has_core(): bool {
	return class_exists( 'MLC_SEO' );
}

/**
 * Logo tekstowe, gdy nie ustawiono własnego.
 */
function mlt_brand(): void {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf(
		'<a class="mlt-brand" href="%s" rel="home"><span class="mlt-brand__bolt" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M13.5 2 4 13.5h6.2L9 22l10-12.2h-6.4L13.5 2z"/></svg></span><span class="mlt-brand__name">montaż<b>ładowarki</b><small>.pl</small></span></a>',
		esc_url( home_url( '/' ) )
	);
}

/**
 * Popularne miasta (do stopki i strony głównej).
 */
function mlt_popular_cities( int $limit = 12 ): array {
	if ( ! mlt_has_core() ) {
		return array();
	}
	$cache = get_transient( 'mlt_popular_' . $limit );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	global $wpdb;
	$table  = MLC_Install::table( 'places' );
	$counts = get_option( 'mlc_city_counts', array() );
	$rows   = $wpdb->get_results( "SELECT name, slug, weight FROM {$table} WHERE type = 'c' AND weight = 3", ARRAY_A ); // phpcs:ignore
	// Największe miasta jako punkt wyjścia, potem sortowanie po liczbie firm.
	$big   = array( 'warszawa', 'krakow', 'wroclaw', 'lodz', 'poznan', 'gdansk', 'szczecin', 'bydgoszcz', 'lublin', 'bialystok', 'katowice', 'gdynia', 'czestochowa', 'radom', 'rzeszow', 'torun', 'kielce', 'olsztyn', 'opole', 'zielona-gora' );
	$order = array_flip( $big );
	foreach ( $rows as &$r ) {
		$r['count'] = (int) ( $counts[ $r['slug'] ] ?? 0 );
		$r['rank']  = $order[ $r['slug'] ] ?? 999;
	}
	unset( $r );
	usort( $rows, static fn( $a, $b ) => $b['count'] <=> $a['count'] ?: $a['rank'] <=> $b['rank'] );
	$rows = array_slice( $rows, 0, $limit );
	set_transient( 'mlt_popular_' . $limit, $rows, HOUR_IN_SECONDS );
	return $rows;
}

/**
 * Liczby do sekcji zaufania na stronie głównej.
 */
function mlt_numbers(): array {
	$installers = (int) wp_count_posts( 'mlc_installer' )->publish;
	$cities     = count( (array) get_option( 'mlc_city_counts', array() ) );
	$leads      = post_type_exists( 'mlc_lead' ) ? (int) wp_count_posts( 'mlc_lead' )->publish : 0;
	return compact( 'installers', 'cities', 'leads' );
}

/**
 * Wyróżnione firmy na stronę główną: promowane, potem zweryfikowane, potem najnowsze.
 */
function mlt_featured_installers( int $limit = 6 ): array {
	$q = new WP_Query(
		array(
			'post_type'      => 'mlc_installer',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);
	$items = array();
	foreach ( $q->posts as $id ) {
		$items[] = array(
			'id'       => (int) $id,
			'promoted' => mlc_is_promoted( (int) $id ),
			'verified' => mlc_is_verified( (int) $id ),
		);
	}
	usort( $items, static fn( $a, $b ) => (int) $b['promoted'] <=> (int) $a['promoted'] ?: (int) $b['verified'] <=> (int) $a['verified'] );
	return array_slice( $items, 0, $limit );
}

/** Menu awaryjne, gdy nie ustawiono menu w panelu. */
function mlt_fallback_menu(): void {
	echo '<ul class="mlt-nav__list">';
	if ( mlt_has_core() ) {
		echo '<li><a href="' . esc_url( MLC_SEO::hub_url() ) . '">' . esc_html__( 'Miasta', 'mlt' ) . '</a></li>';
	}
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog ) {
		echo '<li><a href="' . esc_url( get_permalink( $blog ) ) . '">' . esc_html__( 'Poradnik', 'mlt' ) . '</a></li>';
	}
	if ( mlt_has_core() ) {
		echo '<li><a href="' . esc_url( mlc_page_url( 'dashboard' ) ) . '">' . esc_html__( 'Panel firmy', 'mlt' ) . '</a></li>';
	}
	echo '</ul>';
}
