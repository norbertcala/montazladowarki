<?php
/**
 * Pola zapytania ofertowego (wewnątrz <form>).
 *
 * @var string   $place_label
 * @var int|null $single_id Gdy zapytanie do jednej firmy (profil).
 */
defined( 'ABSPATH' ) || exit;

$state  = MLC_Leads::$state;
$v      = $state['values'];
$sent   = isset( $_GET['zapytanie'] ) && 'wyslane' === $_GET['zapytanie']; // phpcs:ignore
$single = ! empty( $single_id );
$val    = static fn( $k, $d = '' ) => esc_attr( (string) ( $v[ $k ] ?? $d ) );
?>
<section class="mlc-lead" id="mlc-lead" aria-labelledby="mlc-lead-title">
	<?php if ( $sent ) : ?>
		<div class="mlc-lead__done" role="status">
			<strong><?php esc_html_e( 'Zapytanie wysłane!', 'mlc' ); ?></strong>
			<p><?php esc_html_e( 'Wybrane firmy otrzymały Twoje zapytanie i odezwą się bezpośrednio. Kopię wysłaliśmy na Twój e-mail.', 'mlc' ); ?></p>
		</div>
	<?php endif; ?>

	<h2 class="mlc-lead__title" id="mlc-lead-title">
		<?php echo $single ? esc_html__( 'Zapytaj o wycenę montażu', 'mlc' ) : esc_html__( 'Wyślij jedno zapytanie do wybranych firm', 'mlc' ); ?>
	</h2>
	<?php if ( ! $single ) : ?>
		<p class="mlc-lead__lead mlc-muted" data-mlc-picksummary><?php esc_html_e( 'Zaznacz firmy na liście powyżej.', 'mlc' ); ?></p>
	<?php endif; ?>

	<?php if ( $state['errors'] ) : ?>
		<div class="mlc-errors" role="alert"><ul>
			<?php foreach ( $state['errors'] as $e ) : ?>
				<li><?php echo esc_html( $e ); ?></li>
			<?php endforeach; ?>
		</ul></div>
	<?php endif; ?>

	<?php wp_nonce_field( 'mlc_lead', '_mlc_nonce' ); ?>
	<input type="hidden" name="mlc_action" value="lead">
	<input type="hidden" name="_mlc_t" value="<?php echo (int) time(); ?>">
	<input type="hidden" name="_mlc_back" value="<?php echo esc_url( home_url( add_query_arg( array() ) ) ); ?>">
	<?php if ( $single ) : ?>
		<input type="hidden" name="installers[]" value="<?php echo (int) $single_id; ?>">
	<?php endif; ?>
	<div class="mlc-hp" aria-hidden="true"><label>Firma WWW <input type="text" name="company_url" tabindex="-1" autocomplete="off"></label></div>

	<div class="mlc-grid">
		<p class="mlc-field"><label for="mlc-l-name"><?php esc_html_e( 'Imię', 'mlc' ); ?> *</label>
			<input id="mlc-l-name" type="text" name="name" required autocomplete="given-name" value="<?php echo $val( 'name' ); ?>"></p>
		<p class="mlc-field"><label for="mlc-l-email"><?php esc_html_e( 'E-mail', 'mlc' ); ?> *</label>
			<input id="mlc-l-email" type="email" name="email" required autocomplete="email" value="<?php echo $val( 'email' ); ?>"></p>
		<p class="mlc-field"><label for="mlc-l-phone"><?php esc_html_e( 'Telefon', 'mlc' ); ?></label>
			<input id="mlc-l-phone" type="tel" name="phone" autocomplete="tel" value="<?php echo $val( 'phone' ); ?>"></p>
		<p class="mlc-field"><label for="mlc-l-place"><?php esc_html_e( 'Miejsce montażu', 'mlc' ); ?> *</label>
			<input id="mlc-l-place" type="text" name="place_label" required value="<?php echo $val( 'place_label', $place_label ); ?>"></p>
		<p class="mlc-field"><label for="mlc-l-prop"><?php esc_html_e( 'Rodzaj obiektu', 'mlc' ); ?></label>
			<select id="mlc-l-prop" name="property">
				<?php foreach ( MLC_Leads::property_types() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v['property'] ?? '', $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p class="mlc-field"><label for="mlc-l-car"><?php esc_html_e( 'Samochód (opcjonalnie)', 'mlc' ); ?></label>
			<input id="mlc-l-car" type="text" name="car" placeholder="<?php esc_attr_e( 'np. Tesla Model Y, Skoda Elroq', 'mlc' ); ?>" value="<?php echo $val( 'car' ); ?>"></p>
		<p class="mlc-field mlc-field--full"><label for="mlc-l-msg"><?php esc_html_e( 'Opisz krótko potrzeby', 'mlc' ); ?></label>
			<textarea id="mlc-l-msg" name="message" rows="4" placeholder="<?php esc_attr_e( 'Np. odległość od rozdzielnicy ok. 15 m, przyłącze 3-fazowe 11 kW, ładowarka już kupiona / do doboru…', 'mlc' ); ?>"><?php echo esc_textarea( (string) ( $v['message'] ?? '' ) ); ?></textarea></p>
	</div>
	<p class="mlc-consent"><label><input type="checkbox" name="consent" value="1" required <?php checked( ! empty( $v['consent'] ) ); ?>>
		<?php
		printf(
			/* translators: %s: regulamin url */
			wp_kses_post( __( 'Zgadzam się na przekazanie moich danych wybranym firmom w celu przygotowania oferty. Szczegóły w <a href="%1$s">regulaminie</a> i <a href="%2$s">polityce prywatności</a>.', 'mlc' ) ),
			esc_url( mlc_page_url( 'terms' ) ),
			esc_url( get_privacy_policy_url() ?: mlc_page_url( 'terms' ) )
		);
		?>
	</label></p>
	<button type="submit" class="mlc-btn mlc-btn--primary mlc-btn--lg"><?php esc_html_e( 'Wyślij zapytanie', 'mlc' ); ?></button>
</section>
