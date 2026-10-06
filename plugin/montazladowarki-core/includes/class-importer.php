<?php
/**
 * Import firm z pliku JSON/CSV jako profile niezweryfikowane (do przejęcia przez firmy).
 *
 * Format rekordu: name, city, website, source_url, phone, email, services[] (slugi),
 * radius_km, nationwide, description (opcjonalnie).
 */

defined( 'ABSPATH' ) || exit;

class MLC_Importer {

	public static function init(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'mlc import', array( __CLASS__, 'cli' ) );
		}
	}

	/**
	 * WP-CLI: wp mlc import <plik.json|plik.csv> [--draft]
	 */
	public static function cli( array $args, array $assoc ): void {
		$file = $args[0] ?? MLC_DIR . 'data/starter-firms.json';
		$res  = self::import_file( $file, ! empty( $assoc['draft'] ) ? 'draft' : 'publish' );
		WP_CLI::success( sprintf( 'Dodano: %d, pominięto (duplikaty/błędy): %d', $res['added'], $res['skipped'] ) );
		foreach ( $res['errors'] as $e ) {
			WP_CLI::warning( $e );
		}
	}

	public static function read_file( string $file ): array {
		if ( ! is_readable( $file ) ) {
			return array();
		}
		if ( str_ends_with( strtolower( $file ), '.csv' ) ) {
			$rows = array();
			$fh   = fopen( $file, 'r' );
			$head = fgetcsv( $fh, 0, ',', '"', '' );
			while ( $head && ( $r = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
				$row = array_combine( $head, array_pad( $r, count( $head ), '' ) );
				$row['services']   = array_filter( array_map( 'trim', explode( '|', (string) ( $row['services'] ?? '' ) ) ) );
				$row['nationwide'] = in_array( strtolower( (string) ( $row['nationwide'] ?? '' ) ), array( '1', 'tak', 'true', 'yes' ), true );
				$rows[]            = $row;
			}
			fclose( $fh );
			return $rows;
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $data ) ? $data : array();
	}

	public static function host( string $url ): string {
		$h = (string) wp_parse_url( $url, PHP_URL_HOST );
		return preg_replace( '/^www\./', '', strtolower( $h ) );
	}

	/**
	 * Istniejące firmy po domenie strony WWW (deduplikacja).
	 */
	private static function existing_hosts(): array {
		global $wpdb;
		$rows = $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_mlc_website' AND p.post_type = 'mlc_installer' AND p.post_status <> 'trash'" ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $u ) {
			$out[ self::host( $u ) ] = 1;
		}
		return $out;
	}

	private static function find_place( string $city ): ?array {
		global $wpdb;
		$table = MLC_Install::table( 'places' );
		if ( '' === $city ) {
			$city = 'Łódź'; // środek kraju — dla firm ogólnopolskich bez podanej siedziby.
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE name = %s ORDER BY (type = 'c') DESC, weight DESC LIMIT 1", $city ), ARRAY_A ); // phpcs:ignore
		return $row ?: null;
	}

	public static function import_file( string $file, string $status = 'publish' ): array {
		$rows   = self::read_file( $file );
		$known  = self::existing_hosts();
		$added  = 0;
		$skip   = 0;
		$errors = array();

		foreach ( $rows as $r ) {
			$name    = sanitize_text_field( (string) ( $r['name'] ?? '' ) );
			$website = esc_url_raw( (string) ( $r['website'] ?? '' ) );
			$host    = self::host( $website );
			if ( '' === $name || '' === $host ) {
				++$skip;
				$errors[] = 'Brak nazwy lub strony: ' . $name;
				continue;
			}
			if ( isset( $known[ $host ] ) ) {
				++$skip;
				continue;
			}
			$city       = sanitize_text_field( (string) ( $r['city'] ?? '' ) );
			$nationwide = ! empty( $r['nationwide'] );
			$place      = self::find_place( $city );
			if ( ! $place && ! empty( $r['area_city'] ) ) {
				// Siedziba w małej miejscowości spoza rejestru — obszar liczymy od pobliskiego miasta.
				$place = self::find_place( sanitize_text_field( (string) $r['area_city'] ) );
			}
			if ( ! $place ) {
				++$skip;
				$errors[] = sprintf( 'Nie znaleziono miejscowości „%s” (%s)', $city, $name );
				continue;
			}

			$desc = sanitize_textarea_field( (string) ( $r['description'] ?? '' ) );
			if ( '' === $desc ) {
				$desc = $nationwide
					? __( 'Firma informuje na swojej stronie internetowej o montażu ładowarek do samochodów elektrycznych na terenie całego kraju.', 'mlc' )
					: sprintf( __( 'Firma informuje na swojej stronie internetowej o montażu ładowarek do samochodów elektrycznych — %s i okolice.', 'mlc' ), $place['name'] );
			}

			$id = wp_insert_post(
				array(
					'post_type'    => 'mlc_installer',
					'post_status'  => $status,
					'post_title'   => $name,
					'post_content' => $desc,
					'post_author'  => get_current_user_id() ?: 1,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				++$skip;
				continue;
			}

			$radius = $nationwide ? 1000 : (int) ( $r['radius_km'] ?? 50 );
			MLC_Post_Types::save_areas(
				$id,
				array(
					array(
						'place_id'  => (int) $place['id'],
						'radius_km' => $radius,
					),
				)
			);
			MLC_Admin::save_fields(
				$id,
				array(
					'website' => $website,
					'phone'   => (string) ( $r['phone'] ?? '' ),
					'email'   => (string) ( $r['email'] ?? '' ),
					'city'    => $city,
				)
			);
			$terms = array();
			foreach ( (array) ( $r['services'] ?? array() ) as $slug ) {
				$t = get_term_by( 'slug', sanitize_title( $slug ), 'mlc_service' );
				if ( $t ) {
					$terms[] = (int) $t->term_id;
				}
			}
			wp_set_object_terms( $id, $terms, 'mlc_service' );
			update_post_meta( $id, '_mlc_unclaimed', '1' );
			update_post_meta( $id, '_mlc_source_url', esc_url_raw( (string) ( $r['source_url'] ?? $website ) ) );
			update_post_meta( $id, '_mlc_imported', time() );

			$known[ $host ] = 1;
			++$added;
		}
		MLC_Post_Types::schedule_counts();
		return array(
			'added'   => $added,
			'skipped' => $skip,
			'errors'  => $errors,
		);
	}
}
