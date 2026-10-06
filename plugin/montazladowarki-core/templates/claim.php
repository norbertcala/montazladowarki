<?php
/**
 * Przejęcie profilu niezweryfikowanego.
 *
 * @var int      $installer
 * @var string[] $errors
 */
defined( 'ABSPATH' ) || exit;

$name   = get_the_title( $installer );
$open   = mlc_is_unclaimed( $installer );
$p      = wp_unslash( $_POST ); // phpcs:ignore
$site   = (string) mlc_get_meta( $installer, 'website' );
$domain = MLC_Importer::host( $site );
?>
<div class="mlc-join">
	<div class="mlc-join__pitch">
		<p class="mlc-eyebrow"><?php esc_html_e( 'Przejęcie profilu', 'mlc' ); ?></p>
		<h2><?php echo esc_html( sprintf( __( 'To Twoja firma? Przejmij profil „%s”', 'mlc' ), $name ) ); ?></h2>
		<ul class="mlc-ticks">
			<li><?php esc_html_e( 'Uzupełnij opis, ceny, usługi i logo', 'mlc' ); ?></li>
			<li><?php esc_html_e( 'Ustaw dokładny obszar dojazdu', 'mlc' ); ?></li>
			<li><?php esc_html_e( 'Odbieraj zapytania klientów na swój e-mail', 'mlc' ); ?></li>
			<li><?php esc_html_e( 'Bez opłat i bez prowizji', 'mlc' ); ?></li>
		</ul>
		<?php if ( $domain ) : ?>
			<p class="mlc-muted"><?php echo esc_html( sprintf( __( 'Użyj adresu e-mail w domenie %s — wtedy profil przejdzie na Ciebie od razu. Z innym adresem sprawdzimy zgłoszenie ręcznie.', 'mlc' ), '@' . $domain ) ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! $open ) : ?>
		<div class="mlc-panel"><p><?php esc_html_e( 'Ten profil jest już prowadzony przez firmę. Jeśli to pomyłka, napisz do nas.', 'mlc' ); ?></p></div>
	<?php else : ?>
		<form class="mlc-panel mlc-join__form" method="post" novalidate>
			<h3><?php esc_html_e( 'Załóż konto i przejmij profil', 'mlc' ); ?></h3>
			<?php if ( $errors ) : ?>
				<div class="mlc-errors" role="alert"><ul>
					<?php foreach ( $errors as $e ) : ?>
						<li><?php echo esc_html( $e ); ?></li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<?php wp_nonce_field( 'mlc_claim', '_mlc_nonce' ); ?>
			<input type="hidden" name="mlc_action" value="claim">
			<input type="hidden" name="installer" value="<?php echo (int) $installer; ?>">
			<input type="hidden" name="_mlc_t" value="<?php echo (int) time(); ?>">
			<div class="mlc-hp" aria-hidden="true"><label>WWW <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>

			<?php if ( is_user_logged_in() ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Jesteś zalogowany jako %s.', 'mlc' ), wp_get_current_user()->user_email ) ); ?></p>
			<?php else : ?>
				<p class="mlc-field"><label for="mlc-c-email"><?php esc_html_e( 'Firmowy e-mail', 'mlc' ); ?> *</label>
					<input id="mlc-c-email" type="email" name="email" required autocomplete="email" placeholder="<?php echo esc_attr( $domain ? 'biuro@' . $domain : '' ); ?>" value="<?php echo esc_attr( (string) ( $p['email'] ?? '' ) ); ?>"></p>
				<p class="mlc-field"><label for="mlc-c-pass"><?php esc_html_e( 'Hasło (min. 8 znaków)', 'mlc' ); ?> *</label>
					<input id="mlc-c-pass" type="password" name="password" required minlength="8" autocomplete="new-password"></p>
			<?php endif; ?>
			<p class="mlc-field"><label for="mlc-c-phone"><?php esc_html_e( 'Telefon', 'mlc' ); ?></label>
				<input id="mlc-c-phone" type="tel" name="phone" value="<?php echo esc_attr( (string) ( $p['phone'] ?? '' ) ); ?>"></p>
			<p class="mlc-field"><label for="mlc-c-note"><?php esc_html_e( 'Rola w firmie / uwagi (pomaga w weryfikacji)', 'mlc' ); ?></label>
				<textarea id="mlc-c-note" name="note" rows="3"><?php echo esc_textarea( (string) ( $p['note'] ?? '' ) ); ?></textarea></p>
			<p class="mlc-consent"><label><input type="checkbox" name="terms" value="1" <?php checked( ! empty( $p['terms'] ) ); ?>>
				<?php printf( wp_kses_post( __( 'Oświadczam, że reprezentuję tę firmę, i akceptuję <a href="%s" target="_blank">regulamin</a>.', 'mlc' ) ), esc_url( mlc_page_url( 'terms' ) ) ); ?></label></p>
			<button type="submit" class="mlc-btn mlc-btn--primary mlc-btn--lg mlc-btn--block"><?php esc_html_e( 'Przejmij profil', 'mlc' ); ?></button>
		</form>
	<?php endif; ?>
</div>
