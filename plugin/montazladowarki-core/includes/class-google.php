<?php
/**
 * Google Places API (New) — zgodnie z warunkami Google:
 * - w bazie przechowujemy WYŁĄCZNIE place ID (dozwolone bez ograniczeń czasowych),
 * - ocenę, liczbę opinii, telefon i godziny pobieramy na żywo przy wyświetleniu, bez zapisywania,
 * - dane są podpisane „Google Maps” i linkują do wizytówki w Mapach Google.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Google {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function enabled(): bool {
		return '' !== self::key();
	}

	private static function key(): string {
		$key = defined( 'MLC_GOOGLE_API_KEY' ) ? (string) MLC_GOOGLE_API_KEY : (string) mlc_setting( 'google_key' );
		return trim( $key );
	}

	private static function base(): string {
		return (string) apply_filters( 'mlc_google_places_base', 'https://places.googleapis.com/v1/' );
	}

	public static function place_id( int $installer ): string {
		return (string) get_post_meta( $installer, '_mlc_place_id', true );
	}

	public static function sanitize_place_id( string $id ): string {
		$id = trim( $id );
		return preg_match( '/^[A-Za-z0-9_\-]{10,300}$/', $id ) ? $id : '';
	}

	/**
	 * @return array|WP_Error
	 */
	private static function request( string $method, string $path, string $mask, ?array $body = null ) {
		if ( ! self::enabled() ) {
			return new WP_Error( 'mlc_google_off', __( 'Brak klucza Google API w ustawieniach.', 'mlc' ) );
		}
		$args = array(
			'method'  => $method,
			'timeout' => 8,
			'headers' => array(
				'X-Goog-Api-Key'   => self::key(),
				'X-Goog-FieldMask' => $mask,
				'Content-Type'     => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$url  = self::base() . ltrim( $path, '/' );
		$resp = wp_remote_request( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( 200 !== $code ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'mlc_google_http', $msg );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Wyszukanie kandydatów (dla admina/instalatora przy łączeniu wizytówki). Wynik tylko wyświetlany, nie zapisywany.
	 *
	 * @return array|WP_Error
	 */
	public static function search( string $query, int $limit = 5 ) {
		$data = self::request(
			'POST',
			'places:searchText',
			'places.id,places.displayName,places.formattedAddress,places.websiteUri',
			array(
				'textQuery'      => $query,
				'languageCode'   => 'pl',
				'regionCode'     => 'PL',
				'maxResultCount' => max( 1, min( 10, $limit ) ),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( (array) ( $data['places'] ?? array() ) as $p ) {
			$out[] = array(
				'id'      => (string) ( $p['id'] ?? '' ),
				'name'    => (string) ( $p['displayName']['text'] ?? '' ),
				'address' => (string) ( $p['formattedAddress'] ?? '' ),
				'website' => (string) ( $p['websiteUri'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Dopasowanie place ID po domenie strony WWW firmy. Zapisuje tylko ID.
	 *
	 * @return string|WP_Error Zapisane place ID albo '' gdy brak pewnego dopasowania.
	 */
	public static function match( int $installer ) {
		$site = MLC_Importer::host( (string) mlc_get_meta( $installer, 'website' ) );
		$city = (string) mlc_get_meta( $installer, 'city' );
		$hits = self::search( trim( get_the_title( $installer ) . ' ' . $city ), 5 );
		if ( is_wp_error( $hits ) ) {
			return $hits;
		}
		foreach ( $hits as $h ) {
			$h_host = MLC_Importer::host( $h['website'] );
			if ( $site && $h_host && ( $h_host === $site || str_ends_with( $h_host, '.' . $site ) || str_ends_with( $site, '.' . $h_host ) ) ) {
				update_post_meta( $installer, '_mlc_place_id', $h['id'] );
				return $h['id'];
			}
		}
		return '';
	}

	/**
	 * Hurtowe dopasowanie dla firm bez place ID.
	 */
	public static function match_all( int $limit = 200 ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'mlc_installer',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore
					array(
						'key'     => '_mlc_place_id',
						'compare' => 'NOT EXISTS',
					),
					// Firm już sprawdzonych bez wyniku nie odpytujemy ponownie (koszty API).
					array(
						'key'     => '_mlc_place_checked',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$res = array(
			'matched' => 0,
			'none'    => 0,
			'errors'  => 0,
			'last'    => '',
		);
		foreach ( $ids as $id ) {
			$r = self::match( (int) $id );
			if ( is_wp_error( $r ) ) {
				++$res['errors'];
				$res['last'] = $r->get_error_message();
				if ( 'mlc_google_off' === $r->get_error_code() ) {
					break;
				}
			} elseif ( $r ) {
				++$res['matched'];
			} else {
				++$res['none'];
				update_post_meta( (int) $id, '_mlc_place_checked', time() );
			}
		}
		return $res;
	}

	/**
	 * Dane na żywo do wyświetlenia (bez zapisu).
	 *
	 * @return array|WP_Error
	 */
	public static function details( string $place_id ) {
		$data = self::request(
			'GET',
			'places/' . rawurlencode( $place_id ) . '?languageCode=pl',
			'rating,userRatingCount,googleMapsUri,nationalPhoneNumber,regularOpeningHours.weekdayDescriptions,businessStatus'
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'rating'  => isset( $data['rating'] ) ? (float) $data['rating'] : null,
			'count'   => (int) ( $data['userRatingCount'] ?? 0 ),
			'url'     => esc_url_raw( (string) ( $data['googleMapsUri'] ?? '' ) ),
			'phone'   => (string) ( $data['nationalPhoneNumber'] ?? '' ),
			'hours'   => array_values( array_map( 'strval', (array) ( $data['regularOpeningHours']['weekdayDescriptions'] ?? array() ) ) ),
			'status'  => (string) ( $data['businessStatus'] ?? '' ),
		);
	}

	public static function routes(): void {
		register_rest_route(
			'mlc/v1',
			'/google/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_details' ),
			)
		);
		register_rest_route(
			'mlc/v1',
			'/google-search',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function ( WP_REST_Request $r ) {
					return current_user_can( 'edit_post', (int) $r['installer'] ) || (int) get_post_field( 'post_author', (int) $r['installer'] ) === get_current_user_id();
				},
				'callback'            => static function ( WP_REST_Request $r ) {
					$res = self::search( sanitize_text_field( (string) $r['q'] ), 5 );
					return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
				},
			)
		);
	}

	public static function rest_details( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		if ( 'mlc_installer' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return new WP_Error( 'mlc_not_found', 'Not found', array( 'status' => 404 ) );
		}
		$pid = self::place_id( $id );
		if ( ! $pid || ! self::enabled() ) {
			return new WP_Error( 'mlc_no_place', 'No place', array( 'status' => 404 ) );
		}
		// Ochrona kosztów: limit zapytań z jednego IP.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'mlc_g_' . substr( hash_hmac( 'sha256', $ip, wp_salt() ), 0, 16 );
		$count = (int) get_transient( $key );
		if ( $count >= (int) apply_filters( 'mlc_google_rate_limit', 60 ) ) {
			return new WP_Error( 'mlc_rate', 'Too many requests', array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$d = self::details( $pid );
		if ( is_wp_error( $d ) ) {
			return new WP_Error( 'mlc_google', 'Google error', array( 'status' => 502 ) );
		}
		$res = rest_ensure_response( $d );
		$res->header( 'Cache-Control', 'no-store' );
		$res->header( 'X-Robots-Tag', 'noindex' );
		return $res;
	}
}
