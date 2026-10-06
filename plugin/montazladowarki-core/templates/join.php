<?php
/**
 * Rejestracja firmy instalatorskiej.
 *
 * @var string[] $errors
 */
defined( 'ABSPATH' ) || exit;

$p   = wp_unslash( $_POST ); // phpcs:ignore
$val = static fn( $k ) => esc_attr( (string) ( $p[ $k ] ?? '' ) );
?>
<div class="mlc-join">
	<div class="mlc-join__pitch">
		<p class="mlc-eyebrow"><?php esc_html_e( 'Dla instalatorów', 'mlc' ); ?></p>
		<h2><?php esc_html_e( 'Klienci szukają montażu ładowarki w Twojej okolicy', 'mlc' ); ?></h2>
		<ul class="mlc-ticks">
			<li><?php esc_html_e( 'Profil firmy za darmo — bez prowizji od zleceń', 'mlc' ); ?></li>
			<li><?php esc_html_e( 'Sam ustalasz obszar: miasto + promień dojazdu', 'mlc' ); ?></li>
			<li><?php esc_html_e( 'Zapytania od klientów prosto na Twój e-mail', 'mlc' ); ?></li>
			<li><?php esc_html_e( 'Twoja firma na stronach miast w Google', 'mlc' ); ?></li>
		</ul>
		<p class="mlc-muted"><?php printf( wp_kses_post( __( 'Masz już konto? <a href="%s">Zaloguj się do panelu</a>.', 'mlc' ) ), esc_url( mlc_page_url( 'dashboard' ) ) ); ?></p>
	</div>

	<form class="mlc-panel mlc-join__form" method="post" novalidate>
		<h3><?php esc_html_e( 'Załóż konto firmy', 'mlc' ); ?></h3>
		<?php if ( $errors ) : ?>
			<div class="mlc-errors" role="alert"><ul>
				<?php foreach ( $errors as $e ) : ?>
					<li><?php echo wp_kses_post( $e ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
		<?php wp_nonce_field( 'mlc_register', '_mlc_nonce' ); ?>
		<input type="hidden" name="mlc_action" value="register">
		<input type="hidden" name="_mlc_t" value="<?php echo (int) time(); ?>">
		<div class="mlc-hp" aria-hidden="true"><label>WWW <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>

		<p class="mlc-field"><label for="mlc-r-company"><?php esc_html_e( 'Nazwa firmy', 'mlc' ); ?> *</label>
			<input id="mlc-r-company" type="text" name="company" required value="<?php echo $val( 'company' ); ?>" autocomplete="organization"></p>
		<div class="mlc-grid">
			<p class="mlc-field"><label for="mlc-r-email"><?php esc_html_e( 'E-mail', 'mlc' ); ?> *</label>
				<input id="mlc-r-email" type="email" name="email" required value="<?php echo $val( 'email' ); ?>" autocomplete="email"></p>
			<p class="mlc-field"><label for="mlc-r-phone"><?php esc_html_e( 'Telefon', 'mlc' ); ?></label>
				<input id="mlc-r-phone" type="tel" name="phone" value="<?php echo $val( 'phone' ); ?>" autocomplete="tel"></p>
		</div>
		<p class="mlc-field"><label for="mlc-r-pass"><?php esc_html_e( 'Hasło (min. 8 znaków)', 'mlc' ); ?> *</label>
			<input id="mlc-r-pass" type="password" name="password" required minlength="8" autocomplete="new-password"></p>

		<fieldset class="mlc-field">
			<legend><?php esc_html_e( 'Gdzie montujesz?', 'mlc' ); ?> *</legend>
			<div class="mlc-area">
				<div class="mlc-area__place">
					<input type="text" class="mlc-input" value="<?php echo $val( 'place_label' ); ?>" name="place_label" placeholder="<?php esc_attr_e( 'Miejscowość siedziby', 'mlc' ); ?>" autocomplete="off" data-mlc-ac aria-label="<?php esc_attr_e( 'Miejscowość', 'mlc' ); ?>">
					<input type="hidden" name="place_id" value="<?php echo $val( 'place_id' ); ?>" data-mlc-ac-id>
				</div>
				<select class="mlc-input" name="radius_km" aria-label="<?php esc_attr_e( 'Promień', 'mlc' ); ?>">
					<?php foreach ( mlc_radius_options() as $km => $label ) : ?>
						<option value="<?php echo (int) $km; ?>" <?php selected( (int) ( $p['radius_km'] ?? 50 ), $km ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<small class="mlc-muted"><?php esc_html_e( 'Więcej obszarów dodasz po rejestracji w panelu.', 'mlc' ); ?></small>
		</fieldset>

		<p class="mlc-consent"><label><input type="checkbox" name="terms" value="1" <?php checked( ! empty( $p['terms'] ) ); ?>>
			<?php printf( wp_kses_post( __( 'Akceptuję <a href="%s" target="_blank">regulamin serwisu</a>.', 'mlc' ) ), esc_url( mlc_page_url( 'terms' ) ) ); ?></label></p>
		<button type="submit" class="mlc-btn mlc-btn--primary mlc-btn--lg mlc-btn--block"><?php esc_html_e( 'Dodaj firmę za darmo', 'mlc' ); ?></button>
	</form>
</div>
