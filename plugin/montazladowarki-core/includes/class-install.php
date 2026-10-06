<?php
/**
 * Instalacja: tabele, role, strony, import miejscowości.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Install {

	public static function activate(): void {
		self::create_tables();
		self::add_roles();
		MLC_Post_Types::register();
		MLC_Post_Types::ensure_default_services();
		self::create_pages();
		if ( ! self::places_count() ) {
			self::import_places();
		}
		MLC_SEO::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'mlc_db_version', MLC_DB_VERSION );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'mlc_rebuild_city_counts' );
		wp_clear_scheduled_hook( 'mlc_purge_leads' );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'mlc_db_version' ) !== MLC_DB_VERSION ) {
			self::create_tables();
			update_option( 'mlc_db_version', MLC_DB_VERSION );
		}
	}

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'mlc_' . $name;
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$places  = self::table( 'places' );
		$areas   = self::table( 'areas' );

		dbDelta(
			"CREATE TABLE {$places} (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(120) NOT NULL,
			name_search varchar(120) NOT NULL,
			slug varchar(160) NOT NULL DEFAULT '',
			type char(1) NOT NULL DEFAULT 'v',
			weight tinyint(3) unsigned NOT NULL DEFAULT 1,
			province varchar(40) NOT NULL DEFAULT '',
			district varchar(80) NOT NULL DEFAULT '',
			commune varchar(120) NOT NULL DEFAULT '',
			lat decimal(9,6) NOT NULL,
			lng decimal(9,6) NOT NULL,
			PRIMARY KEY  (id),
			KEY name_search (name_search(20)),
			KEY slug (slug(40)),
			KEY type_weight (type,weight)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$areas} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			installer_id bigint(20) unsigned NOT NULL,
			place_id int(10) unsigned NOT NULL DEFAULT 0,
			label varchar(160) NOT NULL DEFAULT '',
			lat decimal(9,6) NOT NULL,
			lng decimal(9,6) NOT NULL,
			radius_km smallint(5) unsigned NOT NULL DEFAULT 25,
			PRIMARY KEY  (id),
			KEY installer_id (installer_id),
			KEY lat_lng (lat,lng)
			) {$charset};"
		);
	}

	public static function add_roles(): void {
		add_role(
			'mlc_installer',
			__( 'Instalator', 'mlc' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
	}

	/**
	 * Strony techniczne z shortcode'ami.
	 */
	public static function create_pages(): void {
		$pages = get_option( 'mlc_pages', array() );
		$defs  = array(
			'search'    => array( 'szukaj', __( 'Wyniki wyszukiwania instalatorów', 'mlc' ), '[mlc_search_results]' ),
			'join'      => array( 'dla-instalatorow', __( 'Dodaj swoją firmę za darmo', 'mlc' ), '[mlc_join]' ),
			'dashboard' => array( 'panel-instalatora', __( 'Panel instalatora', 'mlc' ), '[mlc_dashboard]' ),
			'terms'     => array( 'regulamin', __( 'Regulamin serwisu', 'mlc' ), '<!-- wp:paragraph --><p>' . __( 'Uzupełnij treść regulaminu serwisu.', 'mlc' ) . '</p><!-- /wp:paragraph -->' ),
		);
		foreach ( $defs as $key => $def ) {
			if ( ! empty( $pages[ $key ] ) && get_post( $pages[ $key ] ) ) {
				continue;
			}
			$existing = get_page_by_path( $def[0] );
			if ( $existing ) {
				$pages[ $key ] = $existing->ID;
				continue;
			}
			$pages[ $key ] = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $def[0],
					'post_title'   => $def[1],
					'post_content' => $def[2],
				)
			);
		}
		update_option( 'mlc_pages', $pages );
	}

	public static function places_count(): int {
		global $wpdb;
		$table = self::table( 'places' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	}

	/**
	 * Uproszczona postać do wyszukiwania: małe litery, bez polskich znaków.
	 */
	public static function fold( string $s ): string {
		$s = mb_strtolower( trim( $s ), 'UTF-8' );
		$s = strtr(
			$s,
			array(
				'ą' => 'a',
				'ć' => 'c',
				'ę' => 'e',
				'ł' => 'l',
				'ń' => 'n',
				'ó' => 'o',
				'ś' => 's',
				'ź' => 'z',
				'ż' => 'z',
			)
		);
		$s = remove_accents( $s );
		return preg_replace( '/\s+/', ' ', $s );
	}

	/**
	 * Import miejscowości z PRNG (data/places.csv.gz).
	 */
	public static function import_places(): int {
		global $wpdb;
		$file = MLC_DIR . 'data/places.csv.gz';
		if ( ! file_exists( $file ) || ! function_exists( 'gzopen' ) ) {
			return 0;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
		$table = self::table( 'places' );
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore

		$rows = array();
		$fh   = gzopen( $file, 'r' );
		fgetcsv( $fh, 0, ',', '"', '' ); // nagłówek.
		while ( ( $r = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
			if ( count( $r ) < 7 ) {
				continue;
			}
			$rows[] = $r;
		}
		gzclose( $fh );

		// Unikalne slugi miast; przy duplikatach dopisujemy powiat.
		$city_names = array();
		foreach ( $rows as $r ) {
			if ( 'c' === $r[1] ) {
				$city_names[ $r[0] ] = ( $city_names[ $r[0] ] ?? 0 ) + 1;
			}
		}

		$batch = array();
		$count = 0;
		foreach ( $rows as $r ) {
			list( $name, $type, $province, $district, $commune, $lat, $lng ) = $r;
			$slug = '';
			if ( 'c' === $type ) {
				$slug = sanitize_title( $name );
				if ( $city_names[ $name ] > 1 ) {
					$slug .= '-' . sanitize_title( 'pow-' . $district );
				}
			}
			// Waga: miasta na prawach powiatu > pozostałe miasta > wsie.
			$weight = 'c' === $type ? ( $district === $name ? 3 : 2 ) : 1;

			$batch[] = $wpdb->prepare(
				'(%s,%s,%s,%s,%d,%s,%s,%s,%f,%f)',
				$name,
				self::fold( $name ),
				$slug,
				$type,
				$weight,
				$province,
				$district,
				$commune,
				$lat,
				$lng
			);
			if ( count( $batch ) >= 500 ) {
				$count += self::flush_batch( $batch );
				$batch  = array();
			}
		}
		$count += self::flush_batch( $batch );
		return $count;
	}

	private static function flush_batch( array $batch ): int {
		global $wpdb;
		if ( ! $batch ) {
			return 0;
		}
		$table = self::table( 'places' );
		$wpdb->query( "INSERT INTO {$table} (name,name_search,slug,type,weight,province,district,commune,lat,lng) VALUES " . implode( ',', $batch ) ); // phpcs:ignore
		return count( $batch );
	}
}
