<?php
/**
 * Front: zasoby, shortcode'y wyszukiwarki, szablony firm.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_shortcode( 'mlc_search_form', array( __CLASS__, 'shortcode_form' ) );
		add_shortcode( 'mlc_search_results', array( __CLASS__, 'shortcode_results' ) );
		add_filter( 'template_include', array( __CLASS__, 'single_template' ), 98 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_archive' ) );
	}

	public static function register_assets(): void {
		if ( wp_script_is( 'mlc-search', 'registered' ) ) {
			return;
		}
		// Leaflet lokalnie — bez zewnętrznego CDN (szybkość, RODO).
		wp_register_style( 'leaflet', MLC_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'leaflet', MLC_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_register_style( 'mlc-core', MLC_URL . 'assets/css/core.css', array(), MLC_VERSION );
		wp_register_script( 'mlc-autocomplete', MLC_URL . 'assets/js/autocomplete.js', array(), MLC_VERSION, true );
		wp_register_script( 'mlc-search', MLC_URL . 'assets/js/search.js', array( 'mlc-autocomplete' ), MLC_VERSION, true );
		wp_register_script( 'mlc-map', MLC_URL . 'assets/js/map.js', array( 'leaflet' ), MLC_VERSION, true );
		wp_register_script( 'mlc-areas', MLC_URL . 'assets/js/areas.js', array( 'mlc-autocomplete' ), MLC_VERSION, true );
		wp_register_script( 'mlc-google', MLC_URL . 'assets/js/google.js', array( 'mlc-autocomplete' ), MLC_VERSION, true );
		wp_localize_script(
			'mlc-autocomplete',
			'MLC',
			array(
				'rest'      => esc_url_raw( rest_url( 'mlc/v1/' ) ),
				'tiles'     => mlc_setting( 'map_tiles' ),
				'attrib'    => mlc_setting( 'map_attribution' ),
				'leadMax'   => (int) mlc_setting( 'lead_max' ),
				'nonce'     => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'radii'     => mlc_radius_options(),
				'i18n'      => array(
					'noResults'  => __( 'Brak podpowiedzi — wpisz nazwę miejscowości', 'mlc' ),
					'locating'   => __( 'Ustalam lokalizację…', 'mlc' ),
					'locFail'    => __( 'Nie udało się ustalić lokalizacji. Wpisz miejscowość.', 'mlc' ),
					'leadMax'    => __( 'Możesz wybrać maksymalnie %d firm.', 'mlc' ),
					'selected'   => __( 'Wybrano: %d', 'mlc' ),
					'remove'     => __( 'Usuń', 'mlc' ),
					'placePh'    => __( 'Miejscowość, np. Piaseczno', 'mlc' ),
					'you'        => __( 'Tutaj szukasz', 'mlc' ),
				),
			)
		);
	}

	public static function enqueue( bool $map = false ): void {
		self::register_assets();
		wp_enqueue_style( 'mlc-core' );
		wp_enqueue_script( 'mlc-search' );
		if ( $map ) {
			wp_enqueue_style( 'leaflet' );
			wp_enqueue_script( 'mlc-map' );
		}
		if ( is_page( mlc_page_id( 'dashboard' ) ) || is_page( mlc_page_id( 'join' ) ) ) {
			wp_enqueue_script( 'mlc-areas' );
		}
		if ( MLC_Google::enabled() && ( is_singular( 'mlc_installer' ) || is_page( mlc_page_id( 'dashboard' ) ) ) ) {
			wp_enqueue_script( 'mlc-google' );
		}
	}

	public static function redirect_archive(): void {
		if ( is_post_type_archive( 'mlc_installer' ) ) {
			wp_safe_redirect( MLC_SEO::hub_url(), 301 );
			exit;
		}
	}

	public static function single_template( string $template ): string {
		if ( is_singular( 'mlc_installer' ) ) {
			self::enqueue( true );
			$theme = locate_template( array( 'single-mlc_installer.php' ) );
			return $theme ? $theme : mlc_locate_template( 'single-installer.php' );
		}
		if ( MLC_SEO::is_virtual() || is_page( mlc_page_id( 'search' ) ) ) {
			self::enqueue( true );
		} elseif ( is_front_page() || is_page( array( mlc_page_id( 'join' ), mlc_page_id( 'dashboard' ) ) ) ) {
			self::enqueue();
		}
		return $template;
	}

	public static function shortcode_form( $atts = array() ): string {
		self::enqueue();
		$atts = shortcode_atts(
			array(
				'size' => 'large',
			),
			$atts
		);
		return mlc_get_template_html(
			'search-form.php',
			array(
				'q'     => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '', // phpcs:ignore
				'place' => isset( $_GET['place'] ) ? absint( $_GET['place'] ) : 0, // phpcs:ignore
				'size'  => $atts['size'],
			)
		);
	}

	/**
	 * Strona wyników: /szukaj/?q=…&place=…
	 */
	public static function shortcode_results(): string {
		self::enqueue( true );
		$args = wp_unslash( $_GET ); // phpcs:ignore
		$loc  = MLC_Geo::resolve( $args );
		$q    = isset( $args['q'] ) ? sanitize_text_field( $args['q'] ) : '';

		$services = array_filter( array_map( 'absint', (array) ( $args['usluga'] ?? array() ) ) );
		$page     = max( 1, absint( $args['strona'] ?? 1 ) );

		if ( ! $loc ) {
			return mlc_get_template_html(
				'search-empty.php',
				array(
					'q' => $q,
				)
			);
		}
		$result = MLC_Search::find(
			$loc['lat'],
			$loc['lng'],
			array(
				'services' => $services,
				'page'     => $page,
			)
		);
		return mlc_get_template_html(
			'results.php',
			array(
				'q'        => $q ?: $loc['label'],
				'loc'      => $loc,
				'result'   => $result,
				'services' => $services,
				'page'     => $page,
				'nearest'  => $result['total'] ? array() : MLC_Search::nearest( $loc['lat'], $loc['lng'] ),
			)
		);
	}

	/**
	 * Dane firmy do API i markerów mapy.
	 */
	public static function installer_public( array $item ): array {
		$id = $item['id'];
		return array(
			'id'         => $id,
			'name'       => get_the_title( $id ),
			'url'        => get_permalink( $id ),
			'city'       => (string) mlc_get_meta( $id, 'city' ),
			'lat'        => (float) get_post_meta( $id, '_mlc_lat', true ),
			'lng'        => (float) get_post_meta( $id, '_mlc_lng', true ),
			'distance'   => round( (float) ( $item['distance'] ?? 0 ), 1 ),
			'promoted'   => ! empty( $item['promoted'] ),
			'verified'   => ! empty( $item['verified'] ),
			'nationwide' => ! empty( $item['nationwide'] ),
			'phone'      => (string) mlc_get_meta( $id, 'phone' ),
			'price_from' => (int) mlc_get_meta( $id, 'price_from' ),
			'logo'       => has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'thumbnail' ) : '',
		);
	}

	/**
	 * Markery mapy dla listy firm.
	 */
	public static function markers( array $items ): array {
		$out = array();
		foreach ( $items as $it ) {
			if ( ! empty( $it['nationwide'] ) ) {
				continue;
			}
			$p = self::installer_public( $it );
			if ( $p['lat'] ) {
				$out[] = array(
					'id'       => $p['id'],
					'name'     => $p['name'],
					'url'      => $p['url'],
					'lat'      => $p['lat'],
					'lng'      => $p['lng'],
					'promoted' => $p['promoted'],
				);
			}
		}
		return $out;
	}

	/**
	 * Inicjały firmy (gdy brak logo).
	 */
	public static function initials( string $name ): string {
		$name  = preg_replace( '/\b(sp\.?\s*z\s*o\.?\s*o\.?|s\.?c\.?|s\.?a\.?)\b/iu', '', $name );
		$words = preg_split( '/[\s\-]+/u', trim( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );
		$ini   = '';
		foreach ( array_slice( $words, 0, 2 ) as $w ) {
			$ini .= mb_strtoupper( mb_substr( $w, 0, 1 ) );
		}
		return $ini ?: '⚡';
	}
}
