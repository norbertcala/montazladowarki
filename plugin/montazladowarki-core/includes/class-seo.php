<?php
/**
 * SEO: strony miast i województw, meta, schema.org, mapa witryny.
 */

defined( 'ABSPATH' ) || exit;

class MLC_SEO {

	/** @var array|null Bieżące miasto na stronie SEO. */
	public static ?array $city = null;

	/** @var string|null Bieżące województwo. */
	public static ?string $province = null;

	public static bool $hub = false;

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'sitemap' ) );
		add_action( 'wp', array( __CLASS__, 'detect' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );

		// Integracja z Yoast / Rank Math na stronach wirtualnych.
		foreach ( array( 'wpseo_title', 'rank_math/frontend/title' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'plugin_title' ), 99 );
		}
		foreach ( array( 'wpseo_metadesc', 'rank_math/frontend/description' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'plugin_description' ), 99 );
		}
		foreach ( array( 'wpseo_canonical', 'rank_math/frontend/canonical' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'plugin_canonical' ), 99 );
		}
		add_filter( 'wpseo_sitemap_index', array( __CLASS__, 'yoast_index' ) );
		add_filter( 'rank_math/sitemap/index', array( __CLASS__, 'yoast_index' ) );
	}

	public static function base(): string {
		return (string) mlc_setting( 'city_base' );
	}

	public static function add_rewrite_rules(): void {
		$b = preg_quote( self::base(), '#' );
		add_rewrite_rule( '^' . $b . '/?$', 'index.php?mlc_hub=1', 'top' );
		add_rewrite_rule( '^' . $b . '/woj-([^/]+)/?$', 'index.php?mlc_province=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $b . '/([^/]+)/?$', 'index.php?mlc_city=$matches[1]', 'top' );
		add_rewrite_rule( '^mlc-sitemap-miasta\.xml$', 'index.php?mlc_sitemap=1', 'top' );
	}

	public static function maybe_flush(): void {
		if ( get_option( 'mlc_flush_rewrite' ) ) {
			delete_option( 'mlc_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	public static function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'mlc_city', 'mlc_province', 'mlc_hub', 'mlc_sitemap' ) );
	}

	public static function city_url( string $slug ): string {
		return home_url( user_trailingslashit( self::base() . '/' . $slug ) );
	}

	public static function province_url( string $province ): string {
		return home_url( user_trailingslashit( self::base() . '/woj-' . sanitize_title( $province ) ) );
	}

	public static function hub_url(): string {
		return home_url( user_trailingslashit( self::base() ) );
	}

	public static function is_virtual(): bool {
		return self::$city || self::$province || self::$hub;
	}

	public static function detect(): void {
		global $wp_query;
		$slug = get_query_var( 'mlc_city' );
		$prov = get_query_var( 'mlc_province' );
		if ( $slug ) {
			self::$city = MLC_Geo::get_city_by_slug( sanitize_title( $slug ) );
		} elseif ( $prov ) {
			foreach ( MLC_Geo::provinces() as $p ) {
				if ( sanitize_title( $p ) === sanitize_title( $prov ) ) {
					self::$province = $p;
				}
			}
		} elseif ( get_query_var( 'mlc_hub' ) ) {
			self::$hub = true;
		}

		if ( self::is_virtual() ) {
			$wp_query->is_404  = false;
			$wp_query->is_home = false;
			$wp_query->is_page = false;
			status_header( 200 );
		} elseif ( $slug || $prov ) {
			$wp_query->set_404();
			status_header( 404 );
		}
	}

	public static function template( string $template ): string {
		if ( self::$city ) {
			return mlc_locate_template( 'city.php' );
		}
		if ( self::$province ) {
			return mlc_locate_template( 'province.php' );
		}
		if ( self::$hub ) {
			return mlc_locate_template( 'hub.php' );
		}
		return $template;
	}

	public static function body_class( array $c ): array {
		if ( self::$city ) {
			$c[] = 'mlc-city-page';
		}
		if ( self::$province || self::$hub ) {
			$c[] = 'mlc-hub-page';
		}
		return $c;
	}

	/** @var array|null */
	private static ?array $city_data = null;

	/**
	 * Dane strony miasta liczone raz (dla <head> i szablonu).
	 */
	public static function city_data(): array {
		if ( null !== self::$city_data || ! self::$city ) {
			return self::$city_data ?? array();
		}
		$c      = self::$city;
		$result = MLC_Search::find(
			(float) $c['lat'],
			(float) $c['lng'],
			array( 'per_page' => 200 )
		);
		$items  = $result['items'];
		$stats  = self::city_stats( $items );
		self::$city_data = array(
			'city'    => $c,
			'items'   => $items,
			'stats'   => $stats,
			'nearest' => $items ? array() : MLC_Search::nearest( (float) $c['lat'], (float) $c['lng'] ),
			'nearby'  => MLC_Geo::nearby_cities( (float) $c['lat'], (float) $c['lng'], (int) $c['id'] ),
			'faq'     => self::city_faq( $c, $stats ),
		);
		return self::$city_data;
	}

	/**
	 * Statystyki firm obsługujących miasto (unikalne treści na stronie).
	 */
	public static function city_stats( array $items ): array {
		$prices = array();
		$sep    = 0;
		$local  = 0;
		foreach ( $items as $it ) {
			$pf = (int) mlc_get_meta( $it['id'], 'price_from' );
			if ( $pf > 0 ) {
				$prices[] = $pf;
			}
			if ( mlc_get_meta( $it['id'], 'sep' ) ) {
				++$sep;
			}
			if ( empty( $it['nationwide'] ) ) {
				++$local;
			}
		}
		sort( $prices );
		$median = $prices ? $prices[ (int) floor( count( $prices ) / 2 ) ] : 0;
		return array(
			'count'     => count( $items ),
			'local'     => $local,
			'price_min' => $prices ? min( $prices ) : 0,
			'price_med' => $median,
			'priced'    => count( $prices ),
			'sep'       => $sep,
		);
	}

	public static function city_title( array $city ): string {
		return sprintf( __( 'Montaż ładowarki do samochodu elektrycznego %s', 'mlc' ), $city['name'] );
	}

	private static function current_description(): string {
		if ( self::$city ) {
			$n = MLC_Search::city_count( self::$city['slug'] );
			if ( $n ) {
				return sprintf(
					__( '%1$d %2$s montujących ładowarki (wallboxy) w miejscowości %3$s i okolicy. Porównaj oferty, ceny i uprawnienia, wyślij jedno zapytanie do kilku instalatorów — za darmo.', 'mlc' ),
					$n,
					mlc_plural( $n, 'firma', 'firmy', 'firm' ),
					self::$city['name']
				);
			}
			return sprintf( __( 'Szukasz instalatora ładowarki do auta elektrycznego w miejscowości %s (%s)? Sprawdź firmy z okolicy i wyślij bezpłatne zapytanie ofertowe.', 'mlc' ), self::$city['name'], self::$city['province'] );
		}
		if ( self::$province ) {
			return sprintf( __( 'Instalatorzy ładowarek do samochodów elektrycznych — województwo %s. Wybierz miasto i porównaj firmy montujące wallboxy.', 'mlc' ), self::$province );
		}
		if ( self::$hub ) {
			return __( 'Katalog firm montujących ładowarki do samochodów elektrycznych w całej Polsce. Wybierz województwo lub miasto.', 'mlc' );
		}
		if ( is_singular( 'mlc_installer' ) ) {
			$id    = get_queried_object_id();
			$city  = mlc_get_meta( $id, 'city' );
			$desc  = mlc_excerpt( $id, 22 );
			$label = $city ? sprintf( __( 'Montaż ładowarek EV: %s.', 'mlc' ), $city ) : __( 'Montaż ładowarek do samochodów elektrycznych.', 'mlc' );
			return trim( get_the_title( $id ) . ' — ' . $label . ' ' . $desc );
		}
		if ( is_front_page() ) {
			return __( 'Znajdź instalatora ładowarki do samochodu elektrycznego w swojej okolicy. Wpisz adres, porównaj firmy montujące wallboxy i wyślij jedno zapytanie do kilku — bezpłatnie.', 'mlc' );
		}
		return '';
	}

	private static function current_canonical(): string {
		if ( self::$city ) {
			return self::city_url( self::$city['slug'] );
		}
		if ( self::$province ) {
			return self::province_url( self::$province );
		}
		if ( self::$hub ) {
			return self::hub_url();
		}
		return '';
	}

	public static function title( array $parts ): array {
		if ( self::$city ) {
			$n              = MLC_Search::city_count( self::$city['slug'] );
			$parts['title'] = self::city_title( self::$city ) . ( $n ? sprintf( ' — %d %s', $n, mlc_plural( $n, 'instalator', 'instalatorów', 'instalatorów' ) ) : '' );
		} elseif ( self::$province ) {
			$parts['title'] = sprintf( __( 'Montaż ładowarek EV — województwo %s', 'mlc' ), self::$province );
		} elseif ( self::$hub ) {
			$parts['title'] = __( 'Montaż ładowarek do samochodów elektrycznych — instalatorzy w Polsce', 'mlc' );
		} elseif ( is_page( mlc_page_id( 'search' ) ) && ! empty( $_GET['q'] ) ) { // phpcs:ignore
			$parts['title'] = sprintf( __( 'Instalatorzy ładowarek: %s', 'mlc' ), sanitize_text_field( wp_unslash( $_GET['q'] ) ) ); // phpcs:ignore
		} elseif ( is_singular( 'mlc_installer' ) ) {
			$city           = mlc_get_meta( get_queried_object_id(), 'city' );
			$parts['title'] = get_the_title() . ( $city ? ' — montaż ładowarek ' . $city : ' — montaż ładowarek EV' );
		}
		return $parts;
	}

	private static function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	public static function plugin_title( $title ) {
		if ( self::is_virtual() ) {
			$parts = self::title( array() );
			return $parts['title'] . ' | ' . mlc_setting( 'brand' );
		}
		return $title;
	}

	public static function plugin_description( $desc ) {
		return self::is_virtual() ? self::current_description() : $desc;
	}

	public static function plugin_canonical( $url ) {
		return self::is_virtual() ? self::current_canonical() : $url;
	}

	public static function robots( array $robots ): array {
		$noindex = false;
		if ( is_page( array( mlc_page_id( 'search' ), mlc_page_id( 'dashboard' ) ) ) ) {
			$noindex = true;
		}
		if ( self::$city && ! MLC_Search::city_count( self::$city['slug'] ) ) {
			$noindex = true; // Brak firm = cienka treść.
		}
		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	public static function head(): void {
		$plugin = self::seo_plugin_active();
		$desc   = self::current_description();
		$canon  = self::current_canonical();

		// Gdy działa Yoast/Rank Math, meta ustawiają one (przez filtry powyżej).
		if ( ! $plugin ) {
			if ( $desc ) {
				$d = wp_html_excerpt( $desc, 300, '…' );
				printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $d ) );
				printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $d ) );
			}
			printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( wp_get_document_title() ) );
			printf( "<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr( mlc_setting( 'brand' ) ) );
			echo "<meta property=\"og:locale\" content=\"pl_PL\">\n";
			printf( "<meta property=\"og:type\" content=\"%s\">\n", is_singular( 'post' ) ? 'article' : 'website' );
			if ( $canon ) {
				printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $canon ) );
				printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $canon ) );
			}
			if ( is_singular( 'mlc_installer' ) && has_post_thumbnail() ) {
				printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( get_the_post_thumbnail_url( null, 'large' ) ) );
			}
		}

		$graph = array();
		if ( is_front_page() ) {
			$graph[] = array(
				'@type'           => 'WebSite',
				'name'            => mlc_setting( 'brand' ),
				'url'             => home_url( '/' ),
				'inLanguage'      => 'pl-PL',
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => mlc_page_url( 'search' ) . '?q={search_term_string}',
					'query-input' => 'required name=search_term_string',
				),
			);
		}
		if ( is_singular( 'mlc_installer' ) ) {
			$graph[] = self::installer_schema( get_queried_object_id() );
			$city    = mlc_get_meta( get_queried_object_id(), 'city' );
			$crumbs  = array( array( __( 'Strona główna', 'mlc' ), home_url( '/' ) ), array( __( 'Montaż ładowarek', 'mlc' ), self::hub_url() ) );
			$place   = self::installer_city_place( get_queried_object_id() );
			if ( $place ) {
				$crumbs[] = array( $place['name'], self::city_url( $place['slug'] ) );
			}
			$crumbs[] = array( get_the_title(), get_permalink() );
			$graph[]  = self::breadcrumbs( $crumbs );
		}
		if ( self::$city ) {
			$c       = self::$city;
			$data    = self::city_data();
			$graph[] = self::faq_schema( $data['faq'] );
			if ( $data['items'] ) {
				$graph[] = array(
					'@type'           => 'ItemList',
					'name'            => self::city_title( $c ),
					'numberOfItems'   => count( $data['items'] ),
					'itemListElement' => array_map(
						static fn( $it, $i ) => array(
							'@type'    => 'ListItem',
							'position' => $i + 1,
							'url'      => get_permalink( $it['id'] ),
							'name'     => get_the_title( $it['id'] ),
						),
						array_slice( $data['items'], 0, 30 ),
						array_keys( array_slice( $data['items'], 0, 30 ) )
					),
				);
			}
			$graph[] = self::breadcrumbs(
				array(
					array( __( 'Strona główna', 'mlc' ), home_url( '/' ) ),
					array( __( 'Montaż ładowarek', 'mlc' ), self::hub_url() ),
					array( 'woj. ' . $c['province'], self::province_url( $c['province'] ) ),
					array( $c['name'], self::city_url( $c['slug'] ) ),
				)
			);
		}
		if ( self::$province ) {
			$graph[] = self::breadcrumbs(
				array(
					array( __( 'Strona główna', 'mlc' ), home_url( '/' ) ),
					array( __( 'Montaż ładowarek', 'mlc' ), self::hub_url() ),
					array( 'woj. ' . self::$province, self::province_url( self::$province ) ),
				)
			);
		}
		$graph = apply_filters( 'mlc_schema_graph', array_filter( $graph ) );
		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => array_values( $graph ),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . "</script>\n";
		}
	}

	/**
	 * Miasto siedziby firmy (do okruszków i linkowania).
	 */
	public static function installer_city_place( int $id ): ?array {
		$areas = MLC_Post_Types::get_areas( $id );
		foreach ( $areas as $a ) {
			$p = MLC_Geo::get_place( (int) $a['place_id'] );
			if ( $p && 'c' === $p['type'] ) {
				return $p;
			}
			if ( $p ) {
				$near = MLC_Geo::nearest_place( (float) $p['lat'], (float) $p['lng'], true );
				if ( $near ) {
					return $near;
				}
			}
		}
		return null;
	}

	public static function breadcrumbs( array $items ): array {
		$list = array();
		foreach ( $items as $i => $it ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $it[0],
				'item'     => $it[1],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		);
	}

	public static function installer_schema( int $id ): array {
		$s = array(
			'@type'       => 'Electrician',
			'@id'         => get_permalink( $id ) . '#firma',
			'name'        => get_the_title( $id ),
			'url'         => get_permalink( $id ),
			'description' => mlc_excerpt( $id, 40 ),
		);
		$phone = mlc_get_meta( $id, 'phone' );
		if ( $phone ) {
			$s['telephone'] = $phone;
		}
		$web = mlc_get_meta( $id, 'website' );
		if ( $web ) {
			$s['sameAs'] = array( $web );
		}
		if ( has_post_thumbnail( $id ) ) {
			$s['logo']  = get_the_post_thumbnail_url( $id, 'medium' );
			$s['image'] = $s['logo'];
		}
		$city = mlc_get_meta( $id, 'city' );
		if ( $city ) {
			$s['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => mlc_get_meta( $id, 'street' ),
					'postalCode'      => mlc_get_meta( $id, 'postcode' ),
					'addressLocality' => $city,
					'addressCountry'  => 'PL',
				)
			);
		}
		$lat = get_post_meta( $id, '_mlc_lat', true );
		if ( $lat ) {
			$s['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) get_post_meta( $id, '_mlc_lng', true ),
			);
		}
		$served = array();
		foreach ( MLC_Post_Types::get_areas( $id ) as $a ) {
			if ( (int) $a['radius_km'] >= 1000 ) {
				$served = array(
					array(
						'@type' => 'Country',
						'name'  => 'Polska',
					),
				);
				break;
			}
			$served[] = array(
				'@type'       => 'GeoCircle',
				'geoMidpoint' => array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $a['lat'],
					'longitude' => (float) $a['lng'],
				),
				'geoRadius'   => (int) $a['radius_km'] * 1000,
				'name'        => $a['label'],
			);
		}
		if ( $served ) {
			$s['areaServed'] = $served;
		}
		$terms = get_the_terms( $id, 'mlc_service' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$s['knowsAbout'] = wp_list_pluck( $terms, 'name' );
		}
		$price = (int) mlc_get_meta( $id, 'price_from' );
		if ( $price ) {
			$s['makesOffer'] = array(
				'@type'         => 'Offer',
				'name'          => __( 'Montaż wallboxa', 'mlc' ),
				'priceCurrency' => 'PLN',
				'priceSpecification' => array(
					'@type'         => 'PriceSpecification',
					'minPrice'      => $price,
					'priceCurrency' => 'PLN',
				),
			);
		}
		return $s;
	}

	/**
	 * FAQ na stronie miasta — treść zależna od lokalnych danych.
	 */
	public static function city_faq( array $city, array $stats ): array {
		$name = $city['name'];
		$faq  = array();

		if ( $stats['count'] ) {
			$faq[] = array(
				sprintf( __( 'Ile firm montuje ładowarki w miejscowości %s?', 'mlc' ), $name ),
				sprintf(
					__( 'W naszym katalogu jest %1$d %2$s, które obsługują miejscowość %3$s (w tym %4$d z siedzibą lub obszarem działania w okolicy). Każda firma sama określa promień, w jakim dojeżdża do klientów.', 'mlc' ),
					$stats['count'],
					mlc_plural( $stats['count'], 'firma', 'firmy', 'firm' ),
					$name,
					$stats['local']
				),
			);
		}
		if ( $stats['priced'] ) {
			$faq[] = array(
				sprintf( __( 'Ile kosztuje montaż wallboxa — %s?', 'mlc' ), $name ),
				sprintf(
					__( 'Instalatorzy obsługujący tę okolicę podają ceny montażu od %1$s zł (mediana cen „od”: %2$s zł). Ostateczna cena zależy od odległości od rozdzielnicy, przekroju przewodu, zabezpieczeń i tego, czy potrzebne jest zwiększenie mocy przyłącza.', 'mlc' ),
					number_format_i18n( $stats['price_min'] ),
					number_format_i18n( $stats['price_med'] )
				),
			);
		} else {
			$faq[] = array(
				__( 'Ile kosztuje montaż ładowarki do samochodu elektrycznego?', 'mlc' ),
				__( 'Cena zależy głównie od długości i przekroju przewodu od rozdzielnicy do miejsca montażu, potrzebnych zabezpieczeń (wyłącznik różnicowoprądowy typu A/B lub z detekcją DC) oraz ewentualnego zwiększenia mocy przyłącza. Najlepiej porównać kilka ofert — wyślij jedno zapytanie do kilku firm.', 'mlc' ),
			);
		}
		$faq[] = array(
			__( 'Czy instalator ładowarki musi mieć uprawnienia?', 'mlc' ),
			__( 'Tak — prace przy instalacji elektrycznej powinna wykonywać osoba z aktualnymi uprawnieniami SEP (świadectwo kwalifikacyjne E). Przy wyborze firmy warto o nie zapytać; w profilach oznaczamy instalatorów, którzy je zadeklarowali.', 'mlc' ),
		);
		$faq[] = array(
			__( 'Czy do montażu wallboxa potrzebna jest zgoda zakładu energetycznego?', 'mlc' ),
			__( 'Przy domowej ładowarce zwykle nie, o ile mieści się ona w mocy przyłączeniowej. Jeśli moc jest zbyt mała, trzeba złożyć wniosek o jej zwiększenie u operatora sieci. W budynku wielorodzinnym potrzebna jest też zgoda wspólnoty lub spółdzielni.', 'mlc' ),
		);
		$faq[] = array(
			__( 'Jak długo trwa montaż ładowarki?', 'mlc' ),
			__( 'Standardowy montaż wallboxa w domu jednorodzinnym trwa zwykle od kilku godzin do jednego dnia. Dłużej trwają instalacje w garażach podziemnych i tam, gdzie trzeba prowadzić długie trasy kablowe.', 'mlc' ),
		);
		return apply_filters( 'mlc_city_faq', $faq, $city, $stats );
	}

	public static function faq_schema( array $faq ): array {
		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static fn( $q ) => array(
					'@type'          => 'Question',
					'name'           => $q[0],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $q[1],
					),
				),
				$faq
			),
		);
	}

	/**
	 * Mapa witryny stron miast: /mlc-sitemap-miasta.xml
	 */
	public static function sitemap( WP $wp ): void {
		if ( empty( $wp->query_vars['mlc_sitemap'] ) ) {
			return;
		}
		global $wpdb;
		$counts = get_option( 'mlc_city_counts', array() );
		$time   = (int) get_option( 'mlc_city_counts_time', time() );
		$slugs  = array_keys( $counts );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$urls = array( self::hub_url() );
		$provs_with = array();
		if ( $slugs ) {
			$table = MLC_Install::table( 'places' );
			$in    = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT slug, province FROM {$table} WHERE type = 'c' AND slug IN ({$in})", $slugs ), ARRAY_A ); // phpcs:ignore
			foreach ( $rows as $r ) {
				$provs_with[ $r['province'] ] = 1;
			}
		}
		foreach ( array_keys( $provs_with ) as $p ) {
			$urls[] = self::province_url( $p );
		}
		foreach ( $slugs as $slug ) {
			$urls[] = self::city_url( $slug );
		}
		$lastmod = gmdate( 'c', $time );
		foreach ( $urls as $u ) {
			printf( "<url><loc>%s</loc><lastmod>%s</lastmod></url>\n", esc_url( $u ), esc_html( $lastmod ) );
		}
		echo '</urlset>';
		exit;
	}

	public static function sitemap_url(): string {
		return home_url( '/mlc-sitemap-miasta.xml' );
	}

	public static function robots_txt( string $out, $public ): string {
		if ( $public ) {
			$out .= "\nSitemap: " . self::sitemap_url() . "\n";
		}
		return $out;
	}

	public static function yoast_index( string $xml ): string {
		return $xml . '<sitemap><loc>' . esc_url( self::sitemap_url() ) . '</loc><lastmod>' . esc_html( gmdate( 'c', (int) get_option( 'mlc_city_counts_time', time() ) ) ) . "</lastmod></sitemap>\n";
	}
}
