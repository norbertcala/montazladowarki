<?php
/**
 * Panel administracyjny: metaboxy, kolumny, ustawienia, narzędzia.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Admin {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_mlc_installer', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_mlc_installer_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_mlc_installer_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_mlc_lead_posts_columns', array( __CLASS__, 'lead_columns' ) );
		add_action( 'manage_mlc_lead_posts_custom_column', array( __CLASS__, 'lead_column' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_mlc_tool', array( __CLASS__, 'tool' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_installers' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/** Instalatorzy nie wchodzą do wp-admin — mają panel na froncie. */
	public static function block_installers(): void {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( in_array( 'mlc_installer', (array) $user->roles, true ) && ! current_user_can( 'edit_others_posts' ) ) {
			global $pagenow;
			if ( 'async-upload.php' !== $pagenow && 'admin-post.php' !== $pagenow ) {
				wp_safe_redirect( mlc_page_url( 'dashboard' ) );
				exit;
			}
		}
	}

	public static function admin_bar( bool $show ): bool {
		if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return $show;
	}

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'mlc_installer' !== $screen->post_type ) {
			return;
		}
		MLC_Frontend::register_assets();
		wp_enqueue_style( 'mlc-core' );
		wp_enqueue_script( 'mlc-areas' );
		wp_enqueue_script( 'mlc-google' );
	}

	public static function meta_boxes(): void {
		add_meta_box( 'mlc_details', __( 'Dane firmy', 'mlc' ), array( __CLASS__, 'box_details' ), 'mlc_installer', 'normal', 'high' );
		add_meta_box( 'mlc_areas', __( 'Obszar działania', 'mlc' ), array( __CLASS__, 'box_areas' ), 'mlc_installer', 'normal', 'high' );
		add_meta_box( 'mlc_google', __( 'Wizytówka Google', 'mlc' ), array( __CLASS__, 'box_google' ), 'mlc_installer', 'normal', 'default' );
		add_meta_box( 'mlc_promo', __( 'Promowanie i weryfikacja', 'mlc' ), array( __CLASS__, 'box_promo' ), 'mlc_installer', 'side', 'high' );
		add_meta_box( 'mlc_lead_details', __( 'Treść zapytania', 'mlc' ), array( 'MLC_Leads', 'admin_box' ), 'mlc_lead', 'normal', 'high' );
	}

	public static function box_details( WP_Post $post ): void {
		wp_nonce_field( 'mlc_save_installer', 'mlc_nonce' );
		echo '<table class="form-table"><tbody>';
		foreach ( mlc_installer_fields() as $key => $f ) {
			$val = mlc_get_meta( $post->ID, $key );
			echo '<tr><th><label for="mlc_' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
			if ( 'checkbox' === $f[1] ) {
				echo '<input type="checkbox" id="mlc_' . esc_attr( $key ) . '" name="mlc[' . esc_attr( $key ) . ']" value="1" ' . checked( $val, '1', false ) . '>';
			} else {
				echo '<input class="regular-text" type="' . esc_attr( $f[1] ) . '" id="mlc_' . esc_attr( $key ) . '" name="mlc[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function box_areas( WP_Post $post ): void {
		mlc_template( 'areas-field.php', array( 'areas' => MLC_Post_Types::get_areas( $post->ID ) ) );
	}

	public static function box_google( WP_Post $post ): void {
		if ( ! MLC_Google::enabled() ) {
			echo '<p>' . esc_html__( 'Dodaj klucz Google Places API w Ustawieniach katalogu, aby łączyć profile z wizytówkami Google.', 'mlc' ) . '</p>';
			return;
		}
		mlc_template( 'google-picker.php', array( 'installer' => $post->ID ) );
	}

	/**
	 * Zapis place ID (wspólny dla admina i panelu instalatora).
	 */
	public static function save_place_id( int $post_id, array $post ): void {
		if ( ! array_key_exists( 'mlc_place_id', $post ) ) {
			return;
		}
		$pid = MLC_Google::sanitize_place_id( (string) $post['mlc_place_id'] );
		if ( $pid ) {
			update_post_meta( $post_id, '_mlc_place_id', $pid );
		} else {
			delete_post_meta( $post_id, '_mlc_place_id' );
		}
	}

	public static function box_promo( WP_Post $post ): void {
		$until = (int) get_post_meta( $post->ID, '_mlc_promoted_until', true );
		$date  = $until ? wp_date( 'Y-m-d', $until ) : '';
		?>
		<p><label for="mlc_promoted_until"><strong><?php esc_html_e( 'Promowane do (włącznie)', 'mlc' ); ?></strong></label><br>
			<input type="date" id="mlc_promoted_until" name="mlc_promoted_until" value="<?php echo esc_attr( $date ); ?>"></p>
		<?php if ( $until && $until > time() ) : ?>
			<p style="color:#0a7d33">● <?php esc_html_e( 'Promocja aktywna', 'mlc' ); ?></p>
		<?php elseif ( $until ) : ?>
			<p style="color:#888">○ <?php esc_html_e( 'Promocja wygasła', 'mlc' ); ?></p>
		<?php endif; ?>
		<p><label><input type="checkbox" name="mlc_verified" value="1" <?php checked( mlc_is_verified( $post->ID ) ); ?>> <?php esc_html_e( 'Firma zweryfikowana (NIP, uprawnienia)', 'mlc' ); ?></label></p>
		<p class="description"><?php printf( esc_html__( 'Zapytań otrzymanych: %d', 'mlc' ), (int) get_post_meta( $post->ID, '_mlc_lead_count', true ) ); ?></p>
		<?php
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['mlc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['mlc_nonce'] ), 'mlc_save_installer' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$data = isset( $_POST['mlc'] ) ? wp_unslash( (array) $_POST['mlc'] ) : array(); // phpcs:ignore
		self::save_fields( $post_id, $data );

		self::save_place_id( $post_id, wp_unslash( $_POST ) ); // phpcs:ignore
		$areas = isset( $_POST['mlc_areas'] ) ? wp_unslash( (array) $_POST['mlc_areas'] ) : array(); // phpcs:ignore
		MLC_Post_Types::save_areas( $post_id, $areas );

		if ( current_user_can( 'edit_others_posts' ) ) {
			$until = isset( $_POST['mlc_promoted_until'] ) ? sanitize_text_field( wp_unslash( $_POST['mlc_promoted_until'] ) ) : '';
			if ( $until && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ) {
				$ts = ( new DateTimeImmutable( $until . ' 23:59:59', wp_timezone() ) )->getTimestamp();
				update_post_meta( $post_id, '_mlc_promoted_until', $ts );
			} else {
				delete_post_meta( $post_id, '_mlc_promoted_until' );
			}
			if ( ! empty( $_POST['mlc_verified'] ) ) {
				update_post_meta( $post_id, '_mlc_verified', '1' );
			} else {
				delete_post_meta( $post_id, '_mlc_verified' );
			}
		}
	}

	/**
	 * Zapis pól profilu (wspólny dla admina i panelu instalatora).
	 */
	public static function save_fields( int $post_id, array $data ): void {
		foreach ( mlc_installer_fields() as $key => $f ) {
			$raw = $data[ $key ] ?? '';
			switch ( $f[1] ) {
				case 'checkbox':
					$val = empty( $raw ) ? '' : '1';
					break;
				case 'email':
					$val = sanitize_email( $raw );
					break;
				case 'url':
					$val = $raw ? esc_url_raw( preg_match( '#^https?://#i', $raw ) ? $raw : 'https://' . $raw ) : '';
					break;
				case 'number':
					$val = '' === $raw ? '' : (string) absint( $raw );
					break;
				default:
					$val = sanitize_text_field( $raw );
			}
			if ( 'nip' === $key ) {
				$val = preg_replace( '/[^0-9]/', '', $val );
			}
			if ( '' === $val ) {
				delete_post_meta( $post_id, '_mlc_' . $key );
			} else {
				update_post_meta( $post_id, '_mlc_' . $key, $val );
			}
		}
	}

	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mlc_area']  = __( 'Obszar', 'mlc' );
				$new['mlc_promo'] = __( 'Promocja', 'mlc' );
				$new['mlc_leads'] = __( 'Zapytania', 'mlc' );
			}
		}
		return $new;
	}

	public static function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'mlc_area':
				$labels = array();
				foreach ( MLC_Post_Types::get_areas( $post_id ) as $a ) {
					$labels[] = $a['radius_km'] >= 1000 ? __( 'Cała Polska', 'mlc' ) : $a['label'] . ' +' . $a['radius_km'] . ' km';
				}
				echo esc_html( implode( ', ', $labels ) ?: '—' );
				break;
			case 'mlc_promo':
				$until = (int) get_post_meta( $post_id, '_mlc_promoted_until', true );
				echo $until > time() ? '★ ' . esc_html( wp_date( 'd.m.Y', $until ) ) : '—';
				echo mlc_is_verified( $post_id ) ? '<br>✓ ' . esc_html__( 'zweryfikowana', 'mlc' ) : '';
				echo mlc_is_unclaimed( $post_id ) ? '<br>○ ' . esc_html__( 'niezweryfikowany (import)', 'mlc' ) : '';
				echo get_post_meta( $post_id, '_mlc_claim_user', true ) ? '<br><strong>⚑ ' . esc_html__( 'prośba o przejęcie', 'mlc' ) . '</strong>' : '';
				break;
			case 'mlc_leads':
				echo (int) get_post_meta( $post_id, '_mlc_lead_count', true );
				break;
		}
	}

	public static function lead_columns( array $cols ): array {
		unset( $cols['date'] );
		$cols['mlc_place']      = __( 'Lokalizacja', 'mlc' );
		$cols['mlc_recipients'] = __( 'Wysłano do', 'mlc' );
		$cols['date']           = __( 'Data', 'mlc' );
		return $cols;
	}

	public static function lead_column( string $col, int $post_id ): void {
		if ( 'mlc_place' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_mlc_place_label', true ) );
		}
		if ( 'mlc_recipients' === $col ) {
			$ids = (array) get_post_meta( $post_id, '_mlc_recipients', true );
			echo esc_html( implode( ', ', array_map( 'get_the_title', array_filter( $ids ) ) ) );
		}
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=mlc_installer',
			__( 'Ustawienia katalogu', 'mlc' ),
			__( 'Ustawienia', 'mlc' ),
			'manage_options',
			'mlc-settings',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'mlc_settings',
			'mlc_settings',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	public static function sanitize_settings( $in ): array {
		$in  = is_array( $in ) ? $in : array();
		$old = mlc_settings();
		$out = array(
			'brand'             => sanitize_text_field( $in['brand'] ?? $old['brand'] ),
			'city_base'         => sanitize_title( $in['city_base'] ?? $old['city_base'] ) ?: 'montaz-ladowarki',
			'moderate_new'      => empty( $in['moderate_new'] ) ? 0 : 1,
			'nominatim'         => empty( $in['nominatim'] ) ? 0 : 1,
			'promotion_enabled' => empty( $in['promotion_enabled'] ) ? 0 : 1,
			'lead_max'          => max( 1, min( 10, absint( $in['lead_max'] ?? 5 ) ) ),
			'lead_rate_limit'   => max( 1, absint( $in['lead_rate_limit'] ?? 5 ) ),
			'per_page'          => max( 5, min( 100, absint( $in['per_page'] ?? 20 ) ) ),
			'admin_email'       => sanitize_email( $in['admin_email'] ?? $old['admin_email'] ),
			'map_tiles'         => esc_url_raw( $in['map_tiles'] ?? $old['map_tiles'] ),
			'map_attribution'   => wp_kses_post( $in['map_attribution'] ?? $old['map_attribution'] ),
			'google_key'        => sanitize_text_field( $in['google_key'] ?? $old['google_key'] ),
		);
		if ( $out['city_base'] !== $old['city_base'] ) {
			update_option( 'mlc_flush_rewrite', 1 );
		}
		return $out;
	}

	public static function settings_page(): void {
		$s = mlc_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ustawienia katalogu instalatorów', 'mlc' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'mlc_settings' ); ?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Nazwa serwisu', 'mlc' ); ?></th><td><input class="regular-text" name="mlc_settings[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Adres stron miast', 'mlc' ); ?></th><td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input name="mlc_settings[city_base]" value="<?php echo esc_attr( $s['city_base'] ); ?>"><code>/warszawa/</code></td></tr>
					<tr><th><?php esc_html_e( 'Moderacja', 'mlc' ); ?></th><td><label><input type="checkbox" name="mlc_settings[moderate_new]" value="1" <?php checked( $s['moderate_new'] ); ?>> <?php esc_html_e( 'Nowe firmy czekają na akceptację administratora', 'mlc' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Geokodowanie adresów', 'mlc' ); ?></th><td><label><input type="checkbox" name="mlc_settings[nominatim]" value="1" <?php checked( $s['nominatim'] ); ?>> <?php esc_html_e( 'Rozpoznawaj pełne adresy przez OpenStreetMap Nominatim (z cache, limit 1/s)', 'mlc' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Promowanie', 'mlc' ); ?></th><td><label><input type="checkbox" name="mlc_settings[promotion_enabled]" value="1" <?php checked( $s['promotion_enabled'] ); ?>> <?php esc_html_e( 'Promowane firmy wyświetlaj na górze listy', 'mlc' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Zapytania ofertowe', 'mlc' ); ?></th><td><?php esc_html_e( 'Maks. firm w jednym zapytaniu', 'mlc' ); ?> <input type="number" min="1" max="10" name="mlc_settings[lead_max]" value="<?php echo esc_attr( $s['lead_max'] ); ?>" style="width:70px"> &nbsp; <?php esc_html_e( 'Limit zapytań z jednego IP na godzinę', 'mlc' ); ?> <input type="number" min="1" name="mlc_settings[lead_rate_limit]" value="<?php echo esc_attr( $s['lead_rate_limit'] ); ?>" style="width:70px"></td></tr>
					<tr><th><?php esc_html_e( 'Wyników na stronę', 'mlc' ); ?></th><td><input type="number" min="5" max="100" name="mlc_settings[per_page]" value="<?php echo esc_attr( $s['per_page'] ); ?>" style="width:70px"></td></tr>
					<tr><th><?php esc_html_e( 'E-mail powiadomień', 'mlc' ); ?></th><td><input class="regular-text" type="email" name="mlc_settings[admin_email]" value="<?php echo esc_attr( $s['admin_email'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Kafelki mapy', 'mlc' ); ?></th><td><input class="large-text" name="mlc_settings[map_tiles]" value="<?php echo esc_attr( $s['map_tiles'] ); ?>"><p class="description"><?php esc_html_e( 'Przy dużym ruchu użyj komercyjnego dostawcy kafelków (np. MapTiler, Stadia) — publiczne serwery OSM mają limity.', 'mlc' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Klucz Google Places API', 'mlc' ); ?></th><td><input class="regular-text" type="password" autocomplete="off" name="mlc_settings[google_key]" value="<?php echo esc_attr( $s['google_key'] ); ?>"><p class="description"><?php esc_html_e( 'Places API (New). Klucz działa tylko po stronie serwera — ogranicz go w Google Cloud do adresu IP serwera. Możesz też zdefiniować MLC_GOOGLE_API_KEY w wp-config.php. W bazie zapisujemy tylko place ID; ocena i godziny są pobierane na żywo (zgodnie z warunkami Google).', 'mlc' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Atrybucja mapy', 'mlc' ); ?></th><td><input class="large-text" name="mlc_settings[map_attribution]" value="<?php echo esc_attr( $s['map_attribution'] ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Narzędzia', 'mlc' ); ?></h2>
			<p><?php printf( esc_html__( 'Miejscowości w bazie: %s. Stron miast z firmami: %s.', 'mlc' ), esc_html( number_format_i18n( MLC_Install::places_count() ) ), esc_html( number_format_i18n( count( get_option( 'mlc_city_counts', array() ) ) ) ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<?php wp_nonce_field( 'mlc_tool' ); ?>
				<input type="hidden" name="action" value="mlc_tool"><input type="hidden" name="tool" value="counts">
				<?php submit_button( __( 'Przelicz strony miast', 'mlc' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<?php wp_nonce_field( 'mlc_tool' ); ?>
				<input type="hidden" name="action" value="mlc_tool"><input type="hidden" name="tool" value="places">
				<?php submit_button( __( 'Zaimportuj ponownie miejscowości', 'mlc' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( MLC_Google::enabled() ) : ?>
				<h2><?php esc_html_e( 'Wizytówki Google', 'mlc' ); ?></h2>
				<p><?php esc_html_e( 'Wyszukuje w Google firmy bez połączonej wizytówki i zapisuje place ID tylko wtedy, gdy strona WWW w wizytówce zgadza się ze stroną firmy. Każda firma to jedno płatne zapytanie do API.', 'mlc' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'mlc_tool' ); ?>
					<input type="hidden" name="action" value="mlc_tool"><input type="hidden" name="tool" value="google">
					<?php submit_button( __( 'Dopasuj wizytówki Google', 'mlc' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Import firm', 'mlc' ); ?></h2>
			<p><?php esc_html_e( 'Firmy z importu są publikowane jako „Profil niezweryfikowany” i mogą zostać przejęte przez właścicieli. Duplikaty (ta sama domena strony WWW) są pomijane.', 'mlc' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<?php wp_nonce_field( 'mlc_tool' ); ?>
				<input type="hidden" name="action" value="mlc_tool"><input type="hidden" name="tool" value="starter">
				<?php submit_button( __( 'Zaimportuj bazę startową (dołączoną do wtyczki)', 'mlc' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<?php wp_nonce_field( 'mlc_tool' ); ?>
				<input type="hidden" name="action" value="mlc_tool"><input type="hidden" name="tool" value="upload">
				<input type="file" name="mlc_file" accept=".csv,.json">
				<?php submit_button( __( 'Importuj plik CSV/JSON', 'mlc' ), 'secondary', 'submit', false ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'Kolumny CSV: name, city, website, source_url, phone, email, services (slugi oddzielone |), radius_km, nationwide (1/0), description.', 'mlc' ); ?></p>

			<p class="description"><?php esc_html_e( 'Dane miejscowości: Państwowy Rejestr Nazw Geograficznych (PRNG), opracowanie: github.com/jjbartek/polskie-miejscowosci.', 'mlc' ); ?></p>
		</div>
		<?php
	}

	public static function tool(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'mlc' ) );
		}
		check_admin_referer( 'mlc_tool' );
		$tool = isset( $_POST['tool'] ) ? sanitize_key( $_POST['tool'] ) : '';
		$msg  = '';
		if ( 'counts' === $tool ) {
			$msg = 'counts:' . count( MLC_Search::rebuild_city_counts() );
		} elseif ( 'places' === $tool ) {
			$msg = 'places:' . MLC_Install::import_places();
		} elseif ( 'google' === $tool ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 600 ); // phpcs:ignore
			}
			$r   = MLC_Google::match_all();
			$msg = 'google:' . $r['matched'] . ':' . $r['none'] . ':' . $r['errors'] . ':' . rawurlencode( $r['last'] );
		} elseif ( 'starter' === $tool ) {
			$r   = MLC_Importer::import_file( MLC_DIR . 'data/starter-firms.json' );
			$msg = 'import:' . $r['added'] . ':' . $r['skipped'];
		} elseif ( 'upload' === $tool && ! empty( $_FILES['mlc_file']['tmp_name'] ) ) {
			$name = sanitize_file_name( wp_unslash( $_FILES['mlc_file']['name'] ?? '' ) );
			$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, array( 'csv', 'json' ), true ) ) {
				$tmp = wp_tempnam( 'mlc-import.' . $ext ) . '.' . $ext;
				move_uploaded_file( $_FILES['mlc_file']['tmp_name'], $tmp ); // phpcs:ignore
				$r   = MLC_Importer::import_file( $tmp );
				@unlink( $tmp ); // phpcs:ignore
				$msg = 'import:' . $r['added'] . ':' . $r['skipped'];
			}
		}
		wp_safe_redirect( add_query_arg( 'mlc_msg', rawurlencode( $msg ), admin_url( 'edit.php?post_type=mlc_installer&page=mlc-settings' ) ) );
		exit;
	}

	public static function notices(): void {
		if ( empty( $_GET['mlc_msg'] ) ) { // phpcs:ignore
			return;
		}
		$parts = explode( ':', sanitize_text_field( wp_unslash( $_GET['mlc_msg'] ) ) ); // phpcs:ignore
		if ( 'google' === $parts[0] ) {
			$text = sprintf( __( 'Wizytówki Google — dopasowano: %1$d, bez pewnego dopasowania: %2$d, błędy: %3$d. %4$s', 'mlc' ), (int) ( $parts[1] ?? 0 ), (int) ( $parts[2] ?? 0 ), (int) ( $parts[3] ?? 0 ), rawurldecode( (string) ( $parts[4] ?? '' ) ) );
		} elseif ( 'import' === $parts[0] ) {
			$text = sprintf( __( 'Dodano firm: %1$d, pominięto: %2$d. Strony miast przeliczą się w ciągu minuty.', 'mlc' ), (int) ( $parts[1] ?? 0 ), (int) ( $parts[2] ?? 0 ) );
		} else {
			$text = 'counts' === $parts[0]
				? sprintf( __( 'Przeliczono. Miast z firmami: %d.', 'mlc' ), (int) ( $parts[1] ?? 0 ) )
				: sprintf( __( 'Zaimportowano miejscowości: %d.', 'mlc' ), (int) ( $parts[1] ?? 0 ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
}
