<?php
/**
 * Miejscowości, geokodowanie i odległości.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Geo {

	/**
	 * Wyrażenie SQL odległości (km) między kolumnami lat/lng a punktem.
	 */
	public static function distance_sql( float $lat, float $lng, string $alias = '' ): string {
		$p = $alias ? $alias . '.' : '';
		return sprintf(
			'(6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(%1$slat - %2$F) / 2), 2) + COS(RADIANS(%2$F)) * COS(RADIANS(%1$slat)) * POWER(SIN(RADIANS(%1$slng - %3$F) / 2), 2))))',
			$p,
			$lat,
			$lng
		);
	}

	public static function distance_km( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$d = sin( deg2rad( $lat2 - $lat1 ) / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( deg2rad( $lng2 - $lng1 ) / 2 ) ** 2;
		return 6371 * 2 * asin( sqrt( $d ) );
	}

	public static function get_place( int $id ): ?array {
		global $wpdb;
		$table = MLC_Install::table( 'places' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
		return $row ?: null;
	}

	public static function get_city_by_slug( string $slug ): ?array {
		global $wpdb;
		$table = MLC_Install::table( 'places' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s AND type = 'c' LIMIT 1", $slug ), ARRAY_A ); // phpcs:ignore
		return $row ?: null;
	}

	/**
	 * Etykieta np. „Piaseczno, pow. piaseczyński (mazowieckie)”.
	 */
	public static function place_label( array $p, bool $long = false ): string {
		if ( 'c' === $p['type'] && $p['district'] === $p['name'] ) {
			return $long ? sprintf( '%s (%s)', $p['name'], $p['province'] ) : $p['name'];
		}
		if ( $long || 'v' === $p['type'] ) {
			$commune = preg_replace( '/-gmina.*$/u', '', $p['commune'] );
			$extra   = 'v' === $p['type'] ? sprintf( __( 'gm. %s', 'mlc' ), $commune ) : sprintf( __( 'pow. %s', 'mlc' ), $p['district'] );
			return sprintf( '%s, %s (%s)', $p['name'], $extra, $p['province'] );
		}
		return $p['name'];
	}

	/**
	 * Podpowiedzi miejscowości (autouzupełnianie).
	 */
	public static function autocomplete( string $q, int $limit = 8 ): array {
		global $wpdb;
		$q = MLC_Install::fold( $q );
		// Z adresu typu „ul. Puławska 5, Piaseczno” bierzemy ostatni człon.
		if ( str_contains( $q, ',' ) ) {
			$parts = array_filter( array_map( 'trim', explode( ',', $q ) ) );
			$q     = (string) end( $parts );
		}
		$q = preg_replace( '/^\d{2}-\d{3}\s*/', '', $q );
		if ( mb_strlen( $q ) < 2 ) {
			return array();
		}
		$table = MLC_Install::table( 'places' );
		$rows  = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE name_search LIKE %s ORDER BY (name_search = %s) DESC, weight DESC, CHAR_LENGTH(name) ASC, name ASC LIMIT %d", // phpcs:ignore
				$wpdb->esc_like( $q ) . '%',
				$q,
				$limit
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'to_public' ), $rows );
	}

	public static function to_public( array $p ): array {
		return array(
			'id'    => (int) $p['id'],
			'name'  => $p['name'],
			'label' => self::place_label( $p, true ),
			'type'  => 'c' === $p['type'] ? 'city' : 'village',
			'lat'   => (float) $p['lat'],
			'lng'   => (float) $p['lng'],
		);
	}

	/**
	 * Najbliższa miejscowość do punktu (np. po geolokalizacji przeglądarki).
	 */
	public static function nearest_place( float $lat, float $lng, bool $cities_only = false ): ?array {
		global $wpdb;
		$table = MLC_Install::table( 'places' );
		$dist  = self::distance_sql( $lat, $lng );
		$where = $wpdb->prepare( 'lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f', $lat - 0.3, $lat + 0.3, $lng - 0.45, $lng + 0.45 );
		if ( $cities_only ) {
			$where .= " AND type = 'c'";
		}
		$row = $wpdb->get_row( "SELECT *, {$dist} AS distance FROM {$table} WHERE {$where} ORDER BY distance ASC LIMIT 1", ARRAY_A ); // phpcs:ignore
		return $row ?: null;
	}

	/**
	 * Miasta w pobliżu (linkowanie wewnętrzne stron SEO).
	 */
	public static function nearby_cities( float $lat, float $lng, int $exclude_id, float $radius = 40, int $limit = 12 ): array {
		global $wpdb;
		$table = MLC_Install::table( 'places' );
		$dist  = self::distance_sql( $lat, $lng );
		$box   = $radius / 111;
		$rows  = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT *, {$dist} AS distance FROM {$table} WHERE type = 'c' AND id <> %d AND lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f HAVING distance <= %f ORDER BY weight DESC, distance ASC LIMIT %d", // phpcs:ignore
				$exclude_id,
				$lat - $box,
				$lat + $box,
				$lng - $box * 1.6,
				$lng + $box * 1.6,
				$radius,
				$limit
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	/**
	 * Rozpoznanie lokalizacji z parametrów żądania.
	 *
	 * @return array{lat:float,lng:float,label:string,place:?array}|null
	 */
	public static function resolve( array $args ): ?array {
		if ( ! empty( $args['place'] ) ) {
			$p = self::get_place( (int) $args['place'] );
			if ( $p ) {
				return array(
					'lat'   => (float) $p['lat'],
					'lng'   => (float) $p['lng'],
					'label' => self::place_label( $p ),
					'place' => $p,
				);
			}
		}
		if ( isset( $args['lat'], $args['lng'] ) && is_numeric( $args['lat'] ) && is_numeric( $args['lng'] ) ) {
			$lat = (float) $args['lat'];
			$lng = (float) $args['lng'];
			if ( $lat > 48.9 && $lat < 55 && $lng > 14 && $lng < 24.3 ) {
				$p = self::nearest_place( $lat, $lng );
				return array(
					'lat'   => $lat,
					'lng'   => $lng,
					'label' => ! empty( $args['q'] ) ? sanitize_text_field( $args['q'] ) : ( $p ? self::place_label( $p ) : __( 'Twoja lokalizacja', 'mlc' ) ),
					'place' => $p,
				);
			}
		}
		$q = isset( $args['q'] ) ? trim( sanitize_text_field( $args['q'] ) ) : '';
		if ( '' === $q ) {
			return null;
		}
		// Pełny adres → geokodowanie (OSM Nominatim), jeśli włączone.
		$looks_like_address = (bool) preg_match( '/\d|ul\.|al\.|,/u', $q );
		if ( $looks_like_address && mlc_setting( 'nominatim' ) ) {
			$geo = self::geocode( $q );
			if ( $geo ) {
				$p = self::nearest_place( $geo['lat'], $geo['lng'] );
				return array(
					'lat'   => $geo['lat'],
					'lng'   => $geo['lng'],
					'label' => $q,
					'place' => $p,
				);
			}
		}
		$hits = self::autocomplete( $q, 1 );
		if ( $hits ) {
			$p = self::get_place( $hits[0]['id'] );
			return array(
				'lat'   => (float) $p['lat'],
				'lng'   => (float) $p['lng'],
				'label' => self::place_label( $p ),
				'place' => $p,
			);
		}
		return null;
	}

	/**
	 * Geokodowanie adresu przez Nominatim z cache (polityka OSM: max 1 zapytanie/s, własny User-Agent).
	 */
	public static function geocode( string $address ): ?array {
		$key    = 'mlc_geo_' . md5( mb_strtolower( $address ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached['lat'] ? $cached : null;
		}
		// Prosty limit 1 zapytanie na sekundę dla całej witryny.
		if ( get_transient( 'mlc_geo_lock' ) ) {
			return null;
		}
		set_transient( 'mlc_geo_lock', 1, 1 );

		$url  = add_query_arg(
			array(
				'q'            => $address,
				'format'       => 'jsonv2',
				'countrycodes' => 'pl',
				'limit'        => 1,
			),
			'https://nominatim.openstreetmap.org/search'
		);
		$resp = wp_remote_get(
			$url,
			array(
				'timeout'    => 6,
				'user-agent' => mlc_setting( 'brand' ) . ' (' . home_url() . ')',
				'headers'    => array( 'Accept-Language' => 'pl' ),
			)
		);
		$out = array(
			'lat' => 0,
			'lng' => 0,
		);
		if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
			$data = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( ! empty( $data[0]['lat'] ) ) {
				$out = array(
					'lat' => (float) $data[0]['lat'],
					'lng' => (float) $data[0]['lon'],
				);
			}
		}
		set_transient( $key, $out, $out['lat'] ? MONTH_IN_SECONDS : DAY_IN_SECONDS );
		return $out['lat'] ? $out : null;
	}

	public static function provinces(): array {
		return array(
			'dolnośląskie',
			'kujawsko-pomorskie',
			'lubelskie',
			'lubuskie',
			'łódzkie',
			'małopolskie',
			'mazowieckie',
			'opolskie',
			'podkarpackie',
			'podlaskie',
			'pomorskie',
			'śląskie',
			'świętokrzyskie',
			'warmińsko-mazurskie',
			'wielkopolskie',
			'zachodniopomorskie',
		);
	}
}
