<?php
/**
 * Zapytania ofertowe: jedno zapytanie → kilku instalatorów.
 */

defined( 'ABSPATH' ) || exit;

class MLC_Leads {

	/** @var array{errors:string[],sent:bool,values:array} */
	public static array $state = array(
		'errors' => array(),
		'sent'   => false,
		'values' => array(),
	);

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 5 );
		add_action( 'mlc_purge_leads', array( __CLASS__, 'purge' ) );
		if ( ! wp_next_scheduled( 'mlc_purge_leads' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mlc_purge_leads' );
		}
	}

	/**
	 * RODO: zapytania przechowujemy 12 miesięcy (filtr mlc_lead_retention_days).
	 */
	public static function purge(): void {
		$days = (int) apply_filters( 'mlc_lead_retention_days', 365 );
		$old  = get_posts(
			array(
				'post_type'      => 'mlc_lead',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => $days . ' days ago' ) ),
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public static function property_types(): array {
		return array(
			'dom'       => __( 'Dom jednorodzinny', 'mlc' ),
			'blok'      => __( 'Blok / garaż podziemny', 'mlc' ),
			'firma'     => __( 'Firma / parking firmowy', 'mlc' ),
			'inne'      => __( 'Inne', 'mlc' ),
		);
	}

	public static function handle(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ( $_POST['mlc_action'] ?? '' ) !== 'lead' ) { // phpcs:ignore
			return;
		}
		$p = wp_unslash( $_POST ); // phpcs:ignore
		self::$state['values'] = $p;
		$errors                = array();

		if ( ! isset( $p['_mlc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $p['_mlc_nonce'] ), 'mlc_lead' ) ) {
			$errors[] = __( 'Sesja wygasła, odśwież stronę i wyślij ponownie.', 'mlc' );
		}
		if ( ! empty( $p['company_url'] ) || ( time() - (int) ( $p['_mlc_t'] ?? 0 ) ) < 3 ) {
			$errors[] = __( 'Nie udało się wysłać zapytania.', 'mlc' );
		}

		$ip       = self::ip_hash();
		$rate_key = 'mlc_lead_rate_' . $ip;
		$count    = (int) get_transient( $rate_key );
		if ( $count >= (int) mlc_setting( 'lead_rate_limit' ) ) {
			$errors[] = __( 'Wysłano zbyt wiele zapytań. Spróbuj ponownie za godzinę.', 'mlc' );
		}

		// Powrót na stronę formularza (wp_get_referer() zwraca false przy POST na ten sam adres).
		$back = wp_validate_redirect( esc_url_raw( (string) ( $p['_mlc_back'] ?? '' ) ), home_url( '/' ) );
		$back = remove_query_arg( 'zapytanie', $back );

		$name    = sanitize_text_field( $p['name'] ?? '' );
		$email   = sanitize_email( $p['email'] ?? '' );
		$phone   = sanitize_text_field( $p['phone'] ?? '' );
		$place   = sanitize_text_field( $p['place_label'] ?? '' );
		$prop    = sanitize_key( $p['property'] ?? '' );
		$car     = sanitize_text_field( $p['car'] ?? '' );
		$message = sanitize_textarea_field( $p['message'] ?? '' );
		$ids     = array_slice( array_unique( array_filter( array_map( 'absint', (array) ( $p['installers'] ?? array() ) ) ) ), 0, (int) mlc_setting( 'lead_max' ) );

		$ids = array_values(
			array_filter(
				$ids,
				static fn( $id ) => 'mlc_installer' === get_post_type( $id ) && 'publish' === get_post_status( $id )
			)
		);

		if ( mb_strlen( $name ) < 2 ) {
			$errors[] = __( 'Podaj imię.', 'mlc' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Podaj poprawny e-mail.', 'mlc' );
		}
		if ( mb_strlen( $place ) < 2 ) {
			$errors[] = __( 'Podaj miejscowość montażu.', 'mlc' );
		}
		if ( ! $ids ) {
			$errors[] = __( 'Zaznacz co najmniej jedną firmę.', 'mlc' );
		}
		if ( empty( $p['consent'] ) ) {
			$errors[] = __( 'Potrzebujemy zgody na przekazanie danych wybranym firmom.', 'mlc' );
		}

		if ( $errors ) {
			self::$state['errors'] = $errors;
			return;
		}

		$types = self::property_types();
		$lead  = wp_insert_post(
			array(
				'post_type'   => 'mlc_lead',
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s — %s', $name, $place ),
			)
		);
		$meta = array(
			'name'        => $name,
			'email'       => $email,
			'phone'       => $phone,
			'place_label' => $place,
			'property'    => $types[ $prop ] ?? '',
			'car'         => $car,
			'message'     => $message,
			'recipients'  => $ids,
			'ip'          => $ip,
			'source'      => $back,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $lead, '_mlc_' . $k, $v );
		}

		$body = self::format_body( $meta );
		foreach ( $ids as $id ) {
			add_post_meta( $lead, '_mlc_recipient', $id );
			$to        = mlc_get_meta( $id, 'email' );
			$unclaimed = mlc_is_unclaimed( $id );
			$footer    = sprintf( __( "—\nZapytanie z serwisu %s. Klient wysłał je maksymalnie do %d firm.", 'mlc' ), mlc_setting( 'brand' ), (int) mlc_setting( 'lead_max' ) );
			if ( $unclaimed ) {
				$footer .= "\n" . sprintf( __( "Twoja firma ma w serwisie profil utworzony z publicznych informacji. Przejmij go bezpłatnie, aby uzupełnić dane: %s\nNie chcesz otrzymywać zapytań? Odpisz na adres %s, a usuniemy profil.", 'mlc' ), MLC_Claims::claim_url( $id ), mlc_setting( 'admin_email' ) );
			}
			if ( ! is_email( $to ) && ! $unclaimed ) {
				$to = get_the_author_meta( 'user_email', (int) get_post_field( 'post_author', $id ) );
			}
			if ( is_email( $to ) ) {
				wp_mail(
					$to,
					sprintf( __( '[%1$s] Nowe zapytanie o montaż ładowarki — %2$s', 'mlc' ), mlc_setting( 'brand' ), $place ),
					sprintf( __( "Dzień dobry,\n\nklient szuka instalatora ładowarki i wybrał Twoją firmę. Odpowiedz bezpośrednio na tego maila.\n\n%s\n\n%s", 'mlc' ), $body, $footer ),
					array( 'Reply-To: ' . $name . ' <' . $email . '>' )
				);
			} else {
				// Firma bez e-maila (profil niezweryfikowany) — zapytanie trafia do administratora do przekazania telefonicznie.
				wp_mail(
					mlc_setting( 'admin_email' ),
					sprintf( __( '[%1$s] Zapytanie do przekazania: %2$s', 'mlc' ), mlc_setting( 'brand' ), get_the_title( $id ) ),
					sprintf( __( "Klient wybrał firmę bez adresu e-mail w serwisie. Przekaż zapytanie telefonicznie i zaproponuj przejęcie profilu.\n\nFirma: %1\$s\nTelefon firmy: %2\$s\nStrona: %3\$s\nLink do przejęcia: %4\$s\n\n%5\$s", 'mlc' ), get_the_title( $id ), mlc_get_meta( $id, 'phone' ), mlc_get_meta( $id, 'website' ), MLC_Claims::claim_url( $id ), $body ),
					array( 'Reply-To: ' . $name . ' <' . $email . '>' )
				);
			}
			update_post_meta( $id, '_mlc_lead_count', (int) get_post_meta( $id, '_mlc_lead_count', true ) + 1 );
		}

		wp_mail(
			$email,
			sprintf( __( '[%s] Twoje zapytanie zostało wysłane', 'mlc' ), mlc_setting( 'brand' ) ),
			sprintf(
				__( "Dzień dobry %1\$s,\n\nTwoje zapytanie trafiło do firm:\n%2\$s\n\nInstalatorzy skontaktują się bezpośrednio z Tobą.\n\n%3\$s", 'mlc' ),
				$name,
				'- ' . implode( "\n- ", array_map( 'get_the_title', $ids ) ),
				mlc_setting( 'brand' )
			)
		);

		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );
		do_action( 'mlc_lead_created', $lead, $ids );

		$redirect = add_query_arg( 'zapytanie', 'wyslane', $back );
		wp_safe_redirect( $redirect . '#mlc-lead' );
		exit;
	}

	private static function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( hash_hmac( 'sha256', $ip, wp_salt() ), 0, 16 );
	}

	private static function format_body( array $m ): string {
		$lines = array(
			__( 'Imię', 'mlc' )              => $m['name'],
			__( 'E-mail', 'mlc' )            => $m['email'],
			__( 'Telefon', 'mlc' )           => $m['phone'],
			__( 'Miejsce montażu', 'mlc' )   => $m['place_label'],
			__( 'Rodzaj obiektu', 'mlc' )    => $m['property'],
			__( 'Samochód', 'mlc' )          => $m['car'],
			__( 'Wiadomość', 'mlc' )         => $m['message'],
		);
		$out = '';
		foreach ( $lines as $label => $val ) {
			if ( '' !== (string) $val ) {
				$out .= $label . ': ' . $val . "\n";
			}
		}
		return trim( $out );
	}

	public static function for_installer( int $installer_id, int $limit = 20 ): array {
		return get_posts(
			array(
				'post_type'      => 'mlc_lead',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => '_mlc_recipient', // phpcs:ignore
				'meta_value'     => $installer_id, // phpcs:ignore
			)
		);
	}

	public static function admin_box( WP_Post $post ): void {
		$keys = array(
			'name'        => __( 'Imię', 'mlc' ),
			'email'       => __( 'E-mail', 'mlc' ),
			'phone'       => __( 'Telefon', 'mlc' ),
			'place_label' => __( 'Miejsce montażu', 'mlc' ),
			'property'    => __( 'Rodzaj obiektu', 'mlc' ),
			'car'         => __( 'Samochód', 'mlc' ),
			'message'     => __( 'Wiadomość', 'mlc' ),
			'source'      => __( 'Źródło', 'mlc' ),
		);
		echo '<table class="form-table"><tbody>';
		foreach ( $keys as $k => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) get_post_meta( $post->ID, '_mlc_' . $k, true ) ) ) . '</td></tr>';
		}
		$ids = (array) get_post_meta( $post->ID, '_mlc_recipients', true );
		echo '<tr><th>' . esc_html__( 'Wysłano do', 'mlc' ) . '</th><td>';
		foreach ( array_filter( $ids ) as $id ) {
			echo '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a><br>';
		}
		echo '</td></tr></tbody></table>';
	}
}
