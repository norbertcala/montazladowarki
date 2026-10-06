<?php
/**
 * Logowanie do panelu instalatora.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="mlc-panel mlc-login">
	<h2><?php esc_html_e( 'Zaloguj się do panelu instalatora', 'mlc' ); ?></h2>
	<?php
	wp_login_form(
		array(
			'redirect'       => mlc_page_url( 'dashboard' ),
			'label_username' => __( 'E-mail lub login', 'mlc' ),
			'label_password' => __( 'Hasło', 'mlc' ),
			'label_remember' => __( 'Zapamiętaj mnie', 'mlc' ),
			'label_log_in'   => __( 'Zaloguj się', 'mlc' ),
		)
	);
	?>
	<p class="mlc-muted">
		<a href="<?php echo esc_url( wp_lostpassword_url( mlc_page_url( 'dashboard' ) ) ); ?>"><?php esc_html_e( 'Nie pamiętasz hasła?', 'mlc' ); ?></a> ·
		<a href="<?php echo esc_url( mlc_page_url( 'join' ) ); ?>"><?php esc_html_e( 'Dodaj firmę za darmo', 'mlc' ); ?></a>
	</p>
</div>
