<?php
/**
 * Typy wpisów: instalator, zapytanie; taksonomia usług.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Post_Types {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_areas' ) );
		add_action( 'save_post_mlc_installer', array( __CLASS__, 'schedule_counts' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 3 );
	}

	public static function register(): void {
		register_post_type(
			'mlc_installer',
			array(
				'labels'          => array(
					'name'          => __( 'Instalatorzy', 'mlc' ),
					'singular_name' => __( 'Instalator', 'mlc' ),
					'add_new_item'  => __( 'Dodaj firmę instalatorską', 'mlc' ),
					'edit_item'     => __( 'Edytuj firmę', 'mlc' ),
					'search_items'  => __( 'Szukaj firm', 'mlc' ),
					'menu_name'     => __( 'Instalatorzy', 'mlc' ),
				),
				'public'          => true,
				'has_archive'     => 'instalatorzy',
				'rewrite'         => array(
					'slug'       => 'instalator',
					'with_front' => false,
				),
				'menu_icon'       => 'dashicons-admin-plugins',
				'menu_position'   => 22,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
				'show_in_rest'    => false,
				'capability_type' => 'post',
			)
		);

		register_taxonomy(
			'mlc_service',
			'mlc_installer',
			array(
				'labels'            => array(
					'name'          => __( 'Usługi', 'mlc' ),
					'singular_name' => __( 'Usługa', 'mlc' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'usluga',
					'with_front' => false,
				),
			)
		);

		register_post_type(
			'mlc_lead',
			array(
				'labels'       => array(
					'name'          => __( 'Zapytania ofertowe', 'mlc' ),
					'singular_name' => __( 'Zapytanie', 'mlc' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=mlc_installer',
				'supports'     => array( 'title' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}

	public static function default_services(): array {
		return array(
			'montaz-wallboxa'        => 'Montaż wallboxa w domu',
			'garaz-podziemny'        => 'Montaż w garażu podziemnym / wspólnocie',
			'ladowarki-dla-firm'     => 'Ładowarki dla firm i flot',
			'ladowarki-dc'           => 'Szybkie ładowarki DC',
			'zwiekszenie-mocy'       => 'Zwiększenie mocy przyłącza',
			'fotowoltaika'           => 'Integracja z fotowoltaiką',
			'magazyn-energii'        => 'Magazyny energii',
			'zarzadzanie-moca'       => 'Dynamiczne zarządzanie mocą',
			'serwis'                 => 'Serwis i przeglądy ładowarek',
			'dotacje'                => 'Pomoc w uzyskaniu dotacji',
			'sprzedaz-ladowarek'     => 'Sprzedaż ładowarek',
			'audyt-instalacji'       => 'Audyt instalacji elektrycznej',
		);
	}

	public static function ensure_default_services(): void {
		foreach ( self::default_services() as $slug => $name ) {
			if ( ! term_exists( $slug, 'mlc_service' ) ) {
				wp_insert_term( $name, 'mlc_service', array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Obszary działania firmy.
	 */
	public static function get_areas( int $installer_id ): array {
		global $wpdb;
		$table = MLC_Install::table( 'areas' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE installer_id = %d ORDER BY id ASC", $installer_id ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Zapis obszarów. Każdy obszar: place_id + radius_km (lub label/lat/lng).
	 */
	public static function save_areas( int $installer_id, array $areas ): void {
		global $wpdb;
		$table = MLC_Install::table( 'areas' );
		$wpdb->delete( $table, array( 'installer_id' => $installer_id ), array( '%d' ) );
		$radii = array_keys( mlc_radius_options() );
		$saved = 0;
		foreach ( $areas as $a ) {
			if ( $saved >= 10 ) {
				break;
			}
			$place_id = (int) ( $a['place_id'] ?? 0 );
			$radius   = (int) ( $a['radius_km'] ?? 25 );
			if ( ! in_array( $radius, $radii, true ) ) {
				$radius = 25;
			}
			$place = $place_id ? MLC_Geo::get_place( $place_id ) : null;
			if ( ! $place ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'installer_id' => $installer_id,
					'place_id'     => $place_id,
					'label'        => MLC_Geo::place_label( $place ),
					'lat'          => $place['lat'],
					'lng'          => $place['lng'],
					'radius_km'    => $radius,
				),
				array( '%d', '%d', '%s', '%f', '%f', '%d' )
			);
			++$saved;
		}
		// Współrzędne siedziby = pierwszy obszar (do mapy i schema.org).
		$first = self::get_areas( $installer_id );
		if ( $first ) {
			update_post_meta( $installer_id, '_mlc_lat', $first[0]['lat'] );
			update_post_meta( $installer_id, '_mlc_lng', $first[0]['lng'] );
		}
		self::schedule_counts();
	}

	public static function cleanup_areas( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'mlc_installer' ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( MLC_Install::table( 'areas' ), array( 'installer_id' => $post_id ), array( '%d' ) );
		self::schedule_counts();
	}

	public static function schedule_counts(): void {
		if ( ! wp_next_scheduled( 'mlc_rebuild_city_counts' ) ) {
			wp_schedule_single_event( time() + 60, 'mlc_rebuild_city_counts' );
		}
	}

	public static function status_changed( string $new, string $old, WP_Post $post ): void {
		if ( 'mlc_installer' !== $post->post_type || $new === $old ) {
			return;
		}
		self::schedule_counts();
		// Powiadomienie instalatora o publikacji po moderacji.
		if ( 'publish' === $new && 'pending' === $old ) {
			$user = get_userdata( (int) $post->post_author );
			if ( $user && in_array( 'mlc_installer', (array) $user->roles, true ) ) {
				wp_mail(
					$user->user_email,
					sprintf( __( '[%s] Twoja firma jest już widoczna', 'mlc' ), mlc_setting( 'brand' ) ),
					sprintf(
						/* translators: 1: company, 2: url, 3: dashboard */
						__( "Dzień dobry,\n\nogłoszenie „%1\$s” zostało zatwierdzone i jest widoczne dla klientów:\n%2\$s\n\nPanel instalatora: %3\$s\n\nPozdrawiamy,\nZespół %4\$s", 'mlc' ),
						$post->post_title,
						get_permalink( $post ),
						mlc_page_url( 'dashboard' ),
						mlc_setting( 'brand' )
					)
				);
			}
		}
	}
}
