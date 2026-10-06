<?php
/**
 * Profile niezweryfikowane: przejmowanie przez firmę i zgłoszenia usunięcia.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Claims {

	/** @var string[] */
	public static array $errors = array();

	public static bool $removal_sent = false;

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 4 );
		add_action( 'admin_post_mlc_approve_claim', array( __CLASS__, 'approve' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
	}

	public static function claim_url( int $id ): string {
		return mlc_page_url( 'join', array( 'przejmij' => $id ) );
	}

	public static function handle(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore
			return;
		}
		$action = sanitize_key( $_POST['mlc_action'] ?? '' ); // phpcs:ignore
		if ( 'claim' === $action ) {
			self::claim();
		} elseif ( 'removal' === $action ) {
			self::removal();
		}
	}

	private static function spam_check( array $p, string $nonce_action ): bool {
		if ( ! isset( $p['_mlc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $p['_mlc_nonce'] ), $nonce_action ) ) {
			self::$errors[] = __( 'Sesja wygasła, odśwież stronę i spróbuj ponownie.', 'mlc' );
			return false;
		}
		if ( ! empty( $p['website_url'] ) || ( time() - (int) ( $p['_mlc_t'] ?? 0 ) ) < 3 ) {
			self::$errors[] = __( 'Nie udało się wysłać formularza.', 'mlc' );
			return false;
		}
		return true;
	}

	/**
	 * Przejęcie profilu. Gdy domena e-maila = domena strony firmy, przejęcie jest natychmiastowe;
	 * w pozostałych przypadkach czeka na akceptację administratora.
	 */
	private static function claim(): void {
		$p  = wp_unslash( $_POST ); // phpcs:ignore
		$id = absint( $p['installer'] ?? 0 );
		if ( ! $id || 'mlc_installer' !== get_post_type( $id ) || ! mlc_is_unclaimed( $id ) ) {
			self::$errors[] = __( 'Tego profilu nie można przejąć — skontaktuj się z nami.', 'mlc' );
			return;
		}
		if ( ! self::spam_check( $p, 'mlc_claim' ) ) {
			return;
		}

		$email = sanitize_email( $p['email'] ?? '' );
		$pass  = (string) ( $p['password'] ?? '' );
		$phone = sanitize_text_field( $p['phone'] ?? '' );
		$note  = sanitize_textarea_field( $p['note'] ?? '' );

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$email   = wp_get_current_user()->user_email;
		} else {
			if ( ! is_email( $email ) ) {
				self::$errors[] = __( 'Podaj poprawny adres e-mail.', 'mlc' );
			} elseif ( email_exists( $email ) ) {
				self::$errors[] = __( 'Konto z tym adresem już istnieje — zaloguj się i wróć na tę stronę.', 'mlc' );
			}
			if ( strlen( $pass ) < 8 ) {
				self::$errors[] = __( 'Hasło musi mieć co najmniej 8 znaków.', 'mlc' );
			}
		}
		if ( empty( $p['terms'] ) ) {
			self::$errors[] = __( 'Zaakceptuj regulamin serwisu.', 'mlc' );
		}
		if ( self::$errors ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$login = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'instalator';
			$base  = $login;
			$i     = 1;
			while ( username_exists( $login ) ) {
				$login = $base . ++$i;
			}
			$user_id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => $pass,
					'display_name' => get_the_title( $id ),
					'role'         => 'mlc_installer',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				self::$errors[] = $user_id->get_error_message();
				return;
			}
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		}

		$site_host  = MLC_Importer::host( (string) mlc_get_meta( $id, 'website' ) );
		$mail_host  = strtolower( substr( strrchr( $email, '@' ), 1 ) );
		$domain_ok  = $site_host && ( $mail_host === $site_host || str_ends_with( $site_host, '.' . $mail_host ) || str_ends_with( $mail_host, '.' . $site_host ) );

		if ( $domain_ok ) {
			self::transfer( $id, (int) $user_id );
			if ( $phone ) {
				update_post_meta( $id, '_mlc_phone', $phone );
			}
			wp_safe_redirect( mlc_page_url( 'dashboard', array( 'przejete' => 1 ) ) );
			exit;
		}

		update_post_meta( $id, '_mlc_claim_user', (int) $user_id );
		update_post_meta( $id, '_mlc_claim_note', trim( $note . ( $phone ? "\nTel.: " . $phone : '' ) ) );
		update_user_meta( (int) $user_id, '_mlc_pending_claim', $id );

		$approve = wp_nonce_url( admin_url( 'admin-post.php?action=mlc_approve_claim&post=' . $id . '&user=' . (int) $user_id ), 'mlc_approve_' . $id );
		wp_mail(
			mlc_setting( 'admin_email' ),
			sprintf( __( '[%1$s] Prośba o przejęcie profilu: %2$s', 'mlc' ), mlc_setting( 'brand' ), get_the_title( $id ) ),
			sprintf(
				__( "Ktoś chce przejąć profil firmy.\n\nFirma: %1\$s\nStrona firmy: %2\$s\nE-mail zgłaszającego: %3\$s\nTelefon: %4\$s\nWiadomość: %5\$s\n\nDomena e-maila nie zgadza się z domeną strony — sprawdź (np. telefonicznie pod numer ze strony firmy), zanim zatwierdzisz.\n\nZatwierdź przejęcie: %6\$s\nProfil: %7\$s", 'mlc' ),
				get_the_title( $id ),
				mlc_get_meta( $id, 'website' ),
				$email,
				$phone,
				$note,
				$approve,
				admin_url( 'post.php?post=' . $id . '&action=edit' )
			)
		);
		wp_safe_redirect( mlc_page_url( 'dashboard', array( 'przejecie' => 'oczekuje' ) ) );
		exit;
	}

	public static function transfer( int $id, int $user_id ): void {
		wp_update_post(
			array(
				'ID'          => $id,
				'post_author' => $user_id,
			)
		);
		delete_post_meta( $id, '_mlc_unclaimed' );
		delete_post_meta( $id, '_mlc_claim_user' );
		delete_post_meta( $id, '_mlc_claim_note' );
		update_post_meta( $id, '_mlc_claimed_at', time() );
		delete_user_meta( $user_id, '_mlc_pending_claim' );
		$user = get_userdata( $user_id );
		if ( $user && ! mlc_get_meta( $id, 'email' ) ) {
			update_post_meta( $id, '_mlc_email', $user->user_email );
		}
	}

	public static function approve(): void {
		$id   = absint( $_GET['post'] ?? 0 ); // phpcs:ignore
		$user = absint( $_GET['user'] ?? 0 ); // phpcs:ignore
		if ( ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'mlc_approve_' . $id ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'mlc' ) );
		}
		if ( $id && $user && (int) get_post_meta( $id, '_mlc_claim_user', true ) === $user ) {
			self::transfer( $id, $user );
			$u = get_userdata( $user );
			if ( $u ) {
				wp_mail(
					$u->user_email,
					sprintf( __( '[%s] Profil firmy jest Twój', 'mlc' ), mlc_setting( 'brand' ) ),
					sprintf( __( "Dzień dobry,\n\nzatwierdziliśmy przejęcie profilu „%1\$s”. Możesz go teraz edytować i odbierać zapytania:\n%2\$s\n\n%3\$s", 'mlc' ), get_the_title( $id ), mlc_page_url( 'dashboard' ), mlc_setting( 'brand' ) )
				);
			}
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	/**
	 * Zgłoszenie usunięcia/poprawki profilu (np. przez firmę, która nie chce być w katalogu).
	 */
	private static function removal(): void {
		$p  = wp_unslash( $_POST ); // phpcs:ignore
		$id = absint( $p['installer'] ?? 0 );
		if ( ! $id || 'mlc_installer' !== get_post_type( $id ) ) {
			return;
		}
		if ( ! self::spam_check( $p, 'mlc_removal' ) ) {
			return;
		}
		$email  = sanitize_email( $p['email'] ?? '' );
		$reason = sanitize_textarea_field( $p['reason'] ?? '' );
		$type   = 'fix' === ( $p['type'] ?? '' ) ? 'poprawkę danych' : 'usunięcie profilu';
		if ( ! is_email( $email ) ) {
			self::$errors[] = __( 'Podaj e-mail, abyśmy mogli potwierdzić zgłoszenie.', 'mlc' );
			return;
		}
		$key   = 'mlc_rm_' . md5( $email . $id );
		if ( get_transient( $key ) ) {
			self::$removal_sent = true;
			return;
		}
		set_transient( $key, 1, DAY_IN_SECONDS );
		wp_mail(
			mlc_setting( 'admin_email' ),
			sprintf( __( '[%1$s] Zgłoszenie: %2$s — %3$s', 'mlc' ), mlc_setting( 'brand' ), $type, get_the_title( $id ) ),
			sprintf( "Zgłoszenie dotyczy: %s\nFirma: %s\nE-mail: %s\nTreść: %s\n\nEdycja: %s", $type, get_the_title( $id ), $email, $reason, admin_url( 'post.php?post=' . $id . '&action=edit' ) ),
			array( 'Reply-To: ' . $email )
		);
		self::$removal_sent = true;
	}

	public static function meta_box(): void {
		add_meta_box( 'mlc_claim', __( 'Profil niezweryfikowany', 'mlc' ), array( __CLASS__, 'box' ), 'mlc_installer', 'side', 'high' );
	}

	public static function box( WP_Post $post ): void {
		if ( ! mlc_is_unclaimed( $post->ID ) ) {
			$at = (int) get_post_meta( $post->ID, '_mlc_claimed_at', true );
			echo '<p>' . ( $at ? esc_html( sprintf( __( 'Przejęty przez firmę %s.', 'mlc' ), wp_date( 'd.m.Y', $at ) ) ) : esc_html__( 'Profil prowadzony przez firmę.', 'mlc' ) ) . '</p>';
			return;
		}
		$src = (string) get_post_meta( $post->ID, '_mlc_source_url', true );
		echo '<p>' . esc_html__( 'Zaimportowany z publicznych źródeł, czeka na przejęcie przez firmę.', 'mlc' ) . '</p>';
		if ( $src ) {
			echo '<p><a href="' . esc_url( $src ) . '" target="_blank" rel="noopener">' . esc_html__( 'Źródło danych', 'mlc' ) . ' ↗</a></p>';
		}
		$uid = (int) get_post_meta( $post->ID, '_mlc_claim_user', true );
		if ( $uid ) {
			$u = get_userdata( $uid );
			echo '<p><strong>' . esc_html__( 'Prośba o przejęcie:', 'mlc' ) . '</strong> ' . esc_html( $u ? $u->user_email : '#' . $uid ) . '<br>' . nl2br( esc_html( (string) get_post_meta( $post->ID, '_mlc_claim_note', true ) ) ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mlc_approve_claim&post=' . $post->ID . '&user=' . $uid ), 'mlc_approve_' . $post->ID ) ) . '">' . esc_html__( 'Zatwierdź przejęcie', 'mlc' ) . '</a></p>';
		}
	}
}
