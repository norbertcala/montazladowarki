<?php
/**
 * Rejestracja instalatora i panel na froncie.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Dashboard {

	/** @var string[] */
	private static array $errors = array();

	public static function init(): void {
		add_shortcode( 'mlc_join', array( __CLASS__, 'shortcode_join' ) );
		add_shortcode( 'mlc_dashboard', array( __CLASS__, 'shortcode_dashboard' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	public static function errors(): array {
		return self::$errors;
	}

	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'mlc_installer', (array) $user->roles, true ) ) {
			return mlc_page_url( 'dashboard' );
		}
		return $redirect;
	}

	public static function handle_post(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['mlc_action'] ) ) { // phpcs:ignore
			return;
		}
		$action = sanitize_key( $_POST['mlc_action'] ); // phpcs:ignore
		if ( 'register' === $action ) {
			self::register();
		} elseif ( 'save_profile' === $action ) {
			self::save_profile();
		}
	}

	private static function register(): void {
		if ( ! isset( $_POST['_mlc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mlc_nonce'] ), 'mlc_register' ) ) {
			self::$errors[] = __( 'Sesja wygasła, odśwież stronę i spróbuj ponownie.', 'mlc' );
			return;
		}
		if ( ! empty( $_POST['website_url'] ) || ( time() - (int) ( $_POST['_mlc_t'] ?? 0 ) ) < 3 ) { // honeypot + czas.
			self::$errors[] = __( 'Nie udało się zarejestrować. Spróbuj ponownie.', 'mlc' );
			return;
		}
		$p        = wp_unslash( $_POST ); // phpcs:ignore
		$company  = sanitize_text_field( $p['company'] ?? '' );
		$email    = sanitize_email( $p['email'] ?? '' );
		$pass     = (string) ( $p['password'] ?? '' );
		$phone    = sanitize_text_field( $p['phone'] ?? '' );
		$place_id = absint( $p['place_id'] ?? 0 );
		$radius   = absint( $p['radius_km'] ?? 25 );

		if ( mb_strlen( $company ) < 3 ) {
			self::$errors[] = __( 'Podaj nazwę firmy.', 'mlc' );
		}
		if ( ! is_email( $email ) ) {
			self::$errors[] = __( 'Podaj poprawny adres e-mail.', 'mlc' );
		} elseif ( email_exists( $email ) ) {
			self::$errors[] = __( 'Konto z tym adresem już istnieje — zaloguj się.', 'mlc' );
		}
		if ( strlen( $pass ) < 8 ) {
			self::$errors[] = __( 'Hasło musi mieć co najmniej 8 znaków.', 'mlc' );
		}
		if ( ! $place_id || ! MLC_Geo::get_place( $place_id ) ) {
			self::$errors[] = __( 'Wybierz miejscowość z listy podpowiedzi.', 'mlc' );
		}
		if ( empty( $p['terms'] ) ) {
			self::$errors[] = __( 'Zaakceptuj regulamin serwisu.', 'mlc' );
		}
		if ( self::$errors ) {
			return;
		}

		$login = sanitize_user( current( explode( '@', $email ) ), true );
		$base  = $login ?: 'instalator';
		$i     = 1;
		while ( username_exists( $login ) || '' === $login ) {
			$login = $base . ++$i;
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $company,
				'role'         => 'mlc_installer',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::$errors[] = $user_id->get_error_message();
			return;
		}

		$status  = mlc_setting( 'moderate_new' ) ? 'pending' : 'publish';
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'mlc_installer',
				'post_status' => $status,
				'post_title'  => $company,
				'post_author' => $user_id,
			)
		);
		update_post_meta( $post_id, '_mlc_email', $email );
		if ( $phone ) {
			update_post_meta( $post_id, '_mlc_phone', $phone );
		}
		$place = MLC_Geo::get_place( $place_id );
		update_post_meta( $post_id, '_mlc_city', $place['name'] );
		MLC_Post_Types::save_areas(
			$post_id,
			array(
				array(
					'place_id'  => $place_id,
					'radius_km' => $radius,
				),
			)
		);
		$default = get_term_by( 'slug', 'montaz-wallboxa', 'mlc_service' );
		if ( $default ) {
			wp_set_object_terms( $post_id, array( (int) $default->term_id ), 'mlc_service' );
		}

		wp_mail(
			mlc_setting( 'admin_email' ),
			sprintf( __( '[%s] Nowa firma: %s', 'mlc' ), mlc_setting( 'brand' ), $company ),
			sprintf( __( "Nowa rejestracja instalatora.\n\nFirma: %1\$s\nE-mail: %2\$s\nTelefon: %3\$s\nObszar: %4\$s +%5\$d km\n\nModeracja: %6\$s", 'mlc' ), $company, $email, $phone, $place['name'], $radius, admin_url( 'post.php?post=' . $post_id . '&action=edit' ) )
		);

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		wp_safe_redirect( mlc_page_url( 'dashboard', array( 'welcome' => 1 ) ) );
		exit;
	}

	private static function save_profile(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['_mlc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mlc_nonce'] ), 'mlc_save_profile' ) ) {
			self::$errors[] = __( 'Sesja wygasła, odśwież stronę i spróbuj ponownie.', 'mlc' );
			return;
		}
		$user_id = get_current_user_id();
		$post_id = mlc_get_user_installer_id( $user_id );
		if ( ! $post_id || (int) get_post_field( 'post_author', $post_id ) !== $user_id ) {
			return;
		}
		$p     = wp_unslash( $_POST ); // phpcs:ignore
		$title = sanitize_text_field( $p['company'] ?? '' );
		if ( mb_strlen( $title ) < 3 ) {
			self::$errors[] = __( 'Podaj nazwę firmy.', 'mlc' );
			return;
		}
		$content = wp_kses(
			(string) ( $p['description'] ?? '' ),
			array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
			)
		);
		$content = mb_substr( $content, 0, 5000 );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
		MLC_Admin::save_fields( $post_id, (array) ( $p['mlc'] ?? array() ) );
		MLC_Post_Types::save_areas( $post_id, (array) ( $p['mlc_areas'] ?? array() ) );

		$services = array_map( 'absint', (array) ( $p['services'] ?? array() ) );
		wp_set_object_terms( $post_id, $services, 'mlc_service' );

		// Logo.
		if ( ! empty( $_FILES['logo']['name'] ) ) {
			$type = wp_check_filetype( sanitize_file_name( $_FILES['logo']['name'] ) );
			if ( ! in_array( $type['ext'], array( 'jpg', 'jpeg', 'png', 'webp' ), true ) || (int) $_FILES['logo']['size'] > 2 * MB_IN_BYTES ) {
				self::$errors[] = __( 'Logo: dozwolone JPG, PNG lub WEBP do 2 MB.', 'mlc' );
			} else {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$att = media_handle_upload( 'logo', $post_id );
				if ( is_wp_error( $att ) ) {
					self::$errors[] = $att->get_error_message();
				} else {
					$old = get_post_thumbnail_id( $post_id );
					set_post_thumbnail( $post_id, $att );
					if ( $old && (int) get_post_field( 'post_author', $old ) === $user_id ) {
						wp_delete_attachment( $old, true );
					}
				}
			}
		}
		if ( ! empty( $p['remove_logo'] ) ) {
			delete_post_thumbnail( $post_id );
		}

		if ( ! self::$errors ) {
			wp_safe_redirect( mlc_page_url( 'dashboard', array( 'saved' => 1 ) ) );
			exit;
		}
	}

	public static function shortcode_join(): string {
		MLC_Frontend::enqueue();
		if ( is_user_logged_in() && mlc_get_user_installer_id( get_current_user_id() ) ) {
			return '<div class="mlc-notice">' . sprintf(
				/* translators: %s url */
				__( 'Masz już firmę w katalogu. <a href="%s">Przejdź do panelu instalatora →</a>', 'mlc' ),
				esc_url( mlc_page_url( 'dashboard' ) )
			) . '</div>';
		}
		return mlc_get_template_html( 'join.php', array( 'errors' => self::$errors ) );
	}

	public static function shortcode_dashboard(): string {
		MLC_Frontend::enqueue();
		if ( ! is_user_logged_in() ) {
			return mlc_get_template_html( 'login.php' );
		}
		$post_id = mlc_get_user_installer_id( get_current_user_id() );
		if ( ! $post_id ) {
			return '<div class="mlc-notice">' . sprintf(
				__( 'Do tego konta nie jest przypisana żadna firma. <a href="%s">Dodaj firmę</a>.', 'mlc' ),
				esc_url( mlc_page_url( 'join' ) )
			) . '</div>';
		}
		return mlc_get_template_html(
			'dashboard.php',
			array(
				'post_id' => $post_id,
				'errors'  => self::$errors,
				'leads'   => MLC_Leads::for_installer( $post_id, 20 ),
			)
		);
	}
}
