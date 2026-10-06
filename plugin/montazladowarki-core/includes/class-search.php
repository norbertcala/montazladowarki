<?php
/**
 * Wyszukiwanie instalatorów obsługujących dany punkt.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Search {

	/**
	 * @param float $lat Szerokość.
	 * @param float $lng Długość.
	 * @param array $args services (term_id[]), page, per_page, exclude_nationwide.
	 * @return array{items:array,total:int,pages:int}
	 */
	public static function find( float $lat, float $lng, array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'services' => array(),
				'page'     => 1,
				'per_page' => (int) mlc_setting( 'per_page' ),
			)
		);

		$areas    = MLC_Install::table( 'areas' );
		$dist     = MLC_Geo::distance_sql( $lat, $lng, 'a' );
		$now      = time();
		$services = array_filter( array_map( 'absint', (array) $args['services'] ) );

		$service_sql = '';
		if ( $services ) {
			$in          = implode( ',', $services );
			$service_sql = "AND a.installer_id IN (
				SELECT tr.object_id FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tt.taxonomy = 'mlc_service' AND tt.term_id IN ({$in})
				GROUP BY tr.object_id HAVING COUNT(DISTINCT tt.term_id) = " . count( $services ) . ')';
		}

		$promo_enabled = mlc_setting( 'promotion_enabled' ) ? 1 : 0;

		// phpcs:disable WordPress.DB.PreparedSQL
		$sql = "SELECT m.installer_id, m.distance, m.radius_km,
				( {$promo_enabled} = 1 AND COALESCE(CAST(pm.meta_value AS UNSIGNED), 0) > {$now} ) AS promoted,
				( COALESCE(vm.meta_value, '') = '1' ) AS verified
			FROM (
				SELECT a.installer_id, MIN({$dist}) AS distance, MAX(a.radius_km) AS radius_km
				FROM {$areas} a
				JOIN {$wpdb->posts} p ON p.ID = a.installer_id AND p.post_type = 'mlc_installer' AND p.post_status = 'publish'
				WHERE {$dist} <= a.radius_km {$service_sql}
				GROUP BY a.installer_id
			) m
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = m.installer_id AND pm.meta_key = '_mlc_promoted_until'
			LEFT JOIN {$wpdb->postmeta} vm ON vm.post_id = m.installer_id AND vm.meta_key = '_mlc_verified'
			ORDER BY promoted DESC, (m.radius_km >= 1000) ASC, verified DESC, m.distance ASC";
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable

		$total    = count( $rows );
		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$rows     = array_slice( $rows ?: array(), ( $page - 1 ) * $per_page, $per_page );

		$items = array();
		foreach ( $rows as $r ) {
			$items[] = array(
				'id'         => (int) $r['installer_id'],
				'distance'   => (float) $r['distance'],
				'promoted'   => (bool) $r['promoted'],
				'verified'   => (bool) $r['verified'],
				'nationwide' => (int) $r['radius_km'] >= 1000,
			);
		}
		if ( $items ) {
			_prime_post_caches( wp_list_pluck( $items, 'id' ), true, true );
		}

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Najbliższe firmy poza zasięgiem (gdy w okolicy nikogo nie ma).
	 */
	public static function nearest( float $lat, float $lng, int $limit = 6 ): array {
		global $wpdb;
		$areas = MLC_Install::table( 'areas' );
		$dist  = MLC_Geo::distance_sql( $lat, $lng, 'a' );
		// phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.installer_id, MIN({$dist}) AS distance FROM {$areas} a
				JOIN {$wpdb->posts} p ON p.ID = a.installer_id AND p.post_type = 'mlc_installer' AND p.post_status = 'publish'
				GROUP BY a.installer_id ORDER BY distance ASC LIMIT %d", // phpcs:ignore
				$limit
			),
			ARRAY_A
		);
		return array_map(
			static fn( $r ) => array(
				'id'         => (int) $r['installer_id'],
				'distance'   => (float) $r['distance'],
				'promoted'   => mlc_is_promoted( (int) $r['installer_id'] ),
				'verified'   => mlc_is_verified( (int) $r['installer_id'] ),
				'nationwide' => false,
				'outside'    => true,
			),
			$rows ?: array()
		);
	}

	/**
	 * Przeliczenie liczby firm dla każdego miasta (strony SEO, sitemap, hub).
	 */
	public static function rebuild_city_counts(): array {
		global $wpdb;
		$areas_t  = MLC_Install::table( 'areas' );
		$places_t = MLC_Install::table( 'places' );
		// phpcs:disable WordPress.DB.PreparedSQL
		$areas  = $wpdb->get_results(
			"SELECT a.installer_id, a.lat, a.lng, a.radius_km FROM {$areas_t} a
			JOIN {$wpdb->posts} p ON p.ID = a.installer_id AND p.post_type = 'mlc_installer' AND p.post_status = 'publish'",
			ARRAY_A
		);
		$cities = $wpdb->get_results( "SELECT id, slug, lat, lng FROM {$places_t} WHERE type = 'c'", ARRAY_A );
		// phpcs:enable

		$counts = array();
		foreach ( $cities as $c ) {
			$found = array();
			$clat  = (float) $c['lat'];
			$clng  = (float) $c['lng'];
			foreach ( $areas as $a ) {
				$r = (int) $a['radius_km'];
				// Firmy „cała Polska” nie liczą się do lokalnych stron (unikamy cienkich treści).
				if ( $r >= 1000 ) {
					continue;
				}
				if ( abs( $clat - (float) $a['lat'] ) * 111 > $r ) {
					continue;
				}
				if ( MLC_Geo::distance_km( $clat, $clng, (float) $a['lat'], (float) $a['lng'] ) > $r ) {
					continue;
				}
				$found[ $a['installer_id'] ] = 1;
			}
			if ( $found ) {
				$counts[ $c['slug'] ] = count( $found );
			}
		}
		update_option( 'mlc_city_counts', $counts, false );
		update_option( 'mlc_city_counts_time', time(), false );
		return $counts;
	}

	public static function city_count( string $slug ): int {
		$counts = get_option( 'mlc_city_counts', array() );
		return (int) ( $counts[ $slug ] ?? 0 );
	}
}

add_action( 'mlc_rebuild_city_counts', array( 'MLC_Search', 'rebuild_city_counts' ) );
