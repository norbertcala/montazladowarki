<?php
/**
 * Funkcje pomocnicze używane przez wtyczkę i motyw.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ustawienia wtyczki z wartościami domyślnymi.
 */
function mlc_settings(): array {
	$defaults = array(
		'brand'             => 'Montażładowarki.pl',
		'city_base'         => 'montaz-ladowarki',
		'moderate_new'      => 1,
		'nominatim'         => 1,
		'lead_max'          => 5,
		'lead_rate_limit'   => 5,
		'admin_email'       => get_option( 'admin_email' ),
		'per_page'          => 20,
		'map_tiles'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		'map_attribution'   => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		'promotion_enabled' => 1,
	);
	$saved = get_option( 'mlc_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

function mlc_setting( string $key ) {
	$s = mlc_settings();
	return $s[ $key ] ?? null;
}

/**
 * Identyfikatory stron tworzonych przy aktywacji.
 */
function mlc_page_id( string $key ): int {
	$pages = get_option( 'mlc_pages', array() );
	return (int) ( $pages[ $key ] ?? 0 );
}

function mlc_page_url( string $key, array $args = array() ): string {
	$id  = mlc_page_id( $key );
	$url = $id ? get_permalink( $id ) : home_url( '/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Dostępne promienie obszaru działania (km).
 */
function mlc_radius_options(): array {
	return array(
		5    => '+ 5 km',
		10   => '+ 10 km',
		15   => '+ 15 km',
		25   => '+ 25 km',
		50   => '+ 50 km',
		75   => '+ 75 km',
		100  => '+ 100 km',
		150  => '+ 150 km',
		200  => '+ 200 km',
		1000 => 'Cała Polska',
	);
}

/**
 * Pola profilu instalatora: klucz => [etykieta, typ].
 */
function mlc_installer_fields(): array {
	return array(
		'phone'      => array( __( 'Telefon', 'mlc' ), 'tel' ),
		'email'      => array( __( 'E-mail do zapytań', 'mlc' ), 'email' ),
		'website'    => array( __( 'Strona WWW', 'mlc' ), 'url' ),
		'nip'        => array( __( 'NIP', 'mlc' ), 'text' ),
		'street'     => array( __( 'Ulica i numer', 'mlc' ), 'text' ),
		'postcode'   => array( __( 'Kod pocztowy', 'mlc' ), 'text' ),
		'city'       => array( __( 'Miejscowość siedziby', 'mlc' ), 'text' ),
		'founded'    => array( __( 'Rok założenia', 'mlc' ), 'number' ),
		'price_from' => array( __( 'Montaż wallboxa od (zł brutto)', 'mlc' ), 'number' ),
		'brands'     => array( __( 'Marki ładowarek, które montujesz', 'mlc' ), 'text' ),
		'sep'        => array( __( 'Uprawnienia SEP (E/D)', 'mlc' ), 'checkbox' ),
		'invoice'    => array( __( 'Wystawiam faktury VAT', 'mlc' ), 'checkbox' ),
		'warranty'   => array( __( 'Gwarancja na montaż (miesiące)', 'mlc' ), 'number' ),
	);
}

function mlc_get_meta( int $post_id, string $key ) {
	return get_post_meta( $post_id, '_mlc_' . $key, true );
}

/**
 * Czy ogłoszenie jest aktualnie promowane.
 */
function mlc_is_promoted( int $post_id ): bool {
	if ( ! mlc_setting( 'promotion_enabled' ) ) {
		return false;
	}
	$until = (int) get_post_meta( $post_id, '_mlc_promoted_until', true );
	return $until > time();
}

/**
 * Profil zaimportowany z publicznych źródeł, jeszcze nieprzejęty przez firmę.
 */
function mlc_is_unclaimed( int $post_id ): bool {
	return '1' === (string) get_post_meta( $post_id, '_mlc_unclaimed', true );
}

function mlc_is_verified( int $post_id ): bool {
	return (bool) get_post_meta( $post_id, '_mlc_verified', true );
}

function mlc_format_distance( float $km ): string {
	if ( $km < 1 ) {
		return __( 'mniej niż 1 km', 'mlc' );
	}
	return sprintf( '%s km', number_format_i18n( $km, $km < 10 ? 1 : 0 ) );
}

/**
 * Normalizacja numeru telefonu do linku tel:.
 */
function mlc_tel_href( string $phone ): string {
	$digits = preg_replace( '/[^0-9+]/', '', $phone );
	if ( $digits && strpos( $digits, '+' ) !== 0 && strlen( $digits ) === 9 ) {
		$digits = '+48' . $digits;
	}
	return 'tel:' . $digits;
}

/**
 * Wczytanie szablonu z możliwością nadpisania w motywie (katalog mlc/).
 */
function mlc_locate_template( string $name ): string {
	$theme = locate_template( array( 'mlc/' . $name ) );
	return $theme ? $theme : MLC_DIR . 'templates/' . $name;
}

function mlc_template( string $name, array $vars = array() ): void {
	$file = mlc_locate_template( $name );
	if ( ! file_exists( $file ) ) {
		return;
	}
	extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $file;
}

function mlc_get_template_html( string $name, array $vars = array() ): string {
	ob_start();
	mlc_template( $name, $vars );
	return (string) ob_get_clean();
}

/**
 * Odmiana liczebników po polsku: 1 firma, 2 firmy, 5 firm.
 */
function mlc_plural( int $n, string $one, string $few, string $many ): string {
	if ( 1 === $n ) {
		return $one;
	}
	$mod10  = $n % 10;
	$mod100 = $n % 100;
	if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
		return $few;
	}
	return $many;
}

/**
 * Firma (wpis instalatora) należąca do użytkownika.
 */
function mlc_get_user_installer_id( int $user_id ): int {
	if ( ! $user_id ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'mlc_installer',
			'author'         => $user_id,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Pierwsze zdanie / skrót opisu firmy.
 */
function mlc_excerpt( int $post_id, int $words = 28 ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ) ), $words, '…' );
}
