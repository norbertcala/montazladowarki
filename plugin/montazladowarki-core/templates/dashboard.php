<?php
/**
 * Panel instalatora.
 *
 * @var int       $post_id
 * @var string[]  $errors
 * @var WP_Post[] $leads
 */
defined( 'ABSPATH' ) || exit;

$post     = get_post( $post_id );
$status   = $post->post_status;
$fields   = mlc_installer_fields();
$selected = wp_get_object_terms( $post_id, 'mlc_service', array( 'fields' => 'ids' ) );
$services = get_terms(
	array(
		'taxonomy'   => 'mlc_service',
		'hide_empty' => false,
	)
);
$promoted = mlc_is_promoted( $post_id );
$until    = (int) get_post_meta( $post_id, '_mlc_promoted_until', true );
$leadsum  = (int) get_post_meta( $post_id, '_mlc_lead_count', true );
$areas    = MLC_Post_Types::get_areas( $post_id );
?>
<div class="mlc-dash">
	<header class="mlc-dash__head">
		<div>
			<p class="mlc-eyebrow"><?php esc_html_e( 'Panel instalatora', 'mlc' ); ?></p>
			<h2><?php echo esc_html( $post->post_title ); ?></h2>
		</div>
		<div class="mlc-dash__actions">
			<?php if ( 'publish' === $status ) : ?>
				<a class="mlc-btn mlc-btn--ghost mlc-btn--sm" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank"><?php esc_html_e( 'Zobacz profil', 'mlc' ); ?> ↗</a>
			<?php endif; ?>
			<a class="mlc-btn mlc-btn--ghost mlc-btn--sm" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Wyloguj', 'mlc' ); ?></a>
		</div>
	</header>

	<?php if ( isset( $_GET['welcome'] ) ) : // phpcs:ignore ?>
		<div class="mlc-notice mlc-notice--ok"><strong><?php esc_html_e( 'Witamy!', 'mlc' ); ?></strong> <?php esc_html_e( 'Konto założone. Uzupełnij opis, usługi i logo — pełne profile dostają więcej zapytań.', 'mlc' ); ?></div>
	<?php elseif ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
		<div class="mlc-notice mlc-notice--ok" role="status"><?php esc_html_e( 'Zmiany zapisane.', 'mlc' ); ?></div>
	<?php endif; ?>
	<?php if ( 'pending' === $status ) : ?>
		<div class="mlc-notice mlc-notice--warn"><?php esc_html_e( 'Profil czeka na akceptację — zwykle trwa to do 1 dnia roboczego. Dostaniesz e-mail, gdy będzie widoczny.', 'mlc' ); ?></div>
	<?php endif; ?>
	<?php if ( $errors ) : ?>
		<div class="mlc-errors" role="alert"><ul>
			<?php foreach ( $errors as $e ) : ?>
				<li><?php echo esc_html( $e ); ?></li>
			<?php endforeach; ?>
		</ul></div>
	<?php endif; ?>

	<dl class="mlc-stats mlc-stats--dash">
		<div><dt><?php esc_html_e( 'Status', 'mlc' ); ?></dt><dd><?php echo 'publish' === $status ? esc_html__( 'Widoczny', 'mlc' ) : esc_html__( 'W moderacji', 'mlc' ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Zapytania', 'mlc' ); ?></dt><dd><?php echo (int) $leadsum; ?></dd></div>
		<div><dt><?php esc_html_e( 'Obszary', 'mlc' ); ?></dt><dd><?php echo (int) count( $areas ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Promowanie', 'mlc' ); ?></dt><dd><?php echo $promoted ? esc_html( sprintf( __( 'do %s', 'mlc' ), wp_date( 'd.m.Y', $until ) ) ) : esc_html__( 'Nieaktywne', 'mlc' ); ?></dd></div>
	</dl>

	<form class="mlc-panel mlc-dash__form" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'mlc_save_profile', '_mlc_nonce' ); ?>
		<input type="hidden" name="mlc_action" value="save_profile">

		<section class="mlc-dash__section">
			<h3><?php esc_html_e( 'Firma', 'mlc' ); ?></h3>
			<div class="mlc-logo-field">
				<div class="mlc-card__logo mlc-card__logo--lg">
					<?php if ( has_post_thumbnail( $post_id ) ) : ?>
						<?php echo get_the_post_thumbnail( $post_id, 'thumbnail' ); ?>
					<?php else : ?>
						<span><?php echo esc_html( MLC_Frontend::initials( $post->post_title ) ); ?></span>
					<?php endif; ?>
				</div>
				<div>
					<label for="mlc-logo"><?php esc_html_e( 'Logo (JPG, PNG, WEBP do 2 MB)', 'mlc' ); ?></label>
					<input id="mlc-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
					<?php if ( has_post_thumbnail( $post_id ) ) : ?>
						<label class="mlc-muted"><input type="checkbox" name="remove_logo" value="1"> <?php esc_html_e( 'usuń logo', 'mlc' ); ?></label>
					<?php endif; ?>
				</div>
			</div>
			<p class="mlc-field"><label for="mlc-d-company"><?php esc_html_e( 'Nazwa firmy', 'mlc' ); ?> *</label>
				<input id="mlc-d-company" type="text" name="company" required value="<?php echo esc_attr( $post->post_title ); ?>"></p>
			<p class="mlc-field"><label for="mlc-d-desc"><?php esc_html_e( 'Opis firmy', 'mlc' ); ?></label>
				<textarea id="mlc-d-desc" name="description" rows="7" placeholder="<?php esc_attr_e( 'Czym się zajmujecie, ile instalacji wykonaliście, jakie ładowarki polecacie, jak wygląda współpraca…', 'mlc' ); ?>"><?php echo esc_textarea( wp_strip_all_tags( $post->post_content ) ); ?></textarea></p>
			<div class="mlc-grid">
				<?php foreach ( $fields as $key => $f ) : ?>
					<?php
					if ( 'checkbox' === $f[1] ) {
						continue;
					}
					?>
					<p class="mlc-field"><label for="mlc-d-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f[0] ); ?></label>
						<input id="mlc-d-<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $f[1] ); ?>" name="mlc[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) mlc_get_meta( $post_id, $key ) ); ?>"></p>
				<?php endforeach; ?>
			</div>
			<div class="mlc-checks">
				<?php foreach ( $fields as $key => $f ) : ?>
					<?php if ( 'checkbox' === $f[1] ) : ?>
						<label class="mlc-chip"><input type="checkbox" name="mlc[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( mlc_get_meta( $post_id, $key ), '1' ); ?>><span><?php echo esc_html( $f[0] ); ?></span></label>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="mlc-dash__section">
			<h3><?php esc_html_e( 'Usługi', 'mlc' ); ?></h3>
			<div class="mlc-checks">
				<?php foreach ( $services as $t ) : ?>
					<label class="mlc-chip"><input type="checkbox" name="services[]" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( $t->term_id, $selected, true ) ); ?>><span><?php echo esc_html( $t->name ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( MLC_Google::enabled() ) : ?>
			<section class="mlc-dash__section">
				<h3><?php esc_html_e( 'Wizytówka Google', 'mlc' ); ?></h3>
				<?php mlc_template( 'google-picker.php', array( 'installer' => $post_id ) ); ?>
			</section>
		<?php endif; ?>

		<section class="mlc-dash__section">
			<h3><?php esc_html_e( 'Obszar działania', 'mlc' ); ?></h3>
			<?php mlc_template( 'areas-field.php', array( 'areas' => $areas ) ); ?>
		</section>

		<div class="mlc-dash__save">
			<button type="submit" class="mlc-btn mlc-btn--primary mlc-btn--lg"><?php esc_html_e( 'Zapisz profil', 'mlc' ); ?></button>
		</div>
	</form>

	<?php if ( mlc_setting( 'promotion_enabled' ) ) : ?>
		<aside class="mlc-promo-box">
			<div>
				<p class="mlc-eyebrow">★ <?php esc_html_e( 'Promowanie', 'mlc' ); ?></p>
				<h3><?php esc_html_e( 'Bądź na górze listy w swojej okolicy', 'mlc' ); ?></h3>
				<p><?php esc_html_e( 'Promowane firmy wyświetlamy przed pozostałymi w wynikach wyszukiwania i na stronach miast, z wyróżnionym znacznikiem na mapie.', 'mlc' ); ?></p>
			</div>
			<a class="mlc-btn mlc-btn--primary" href="mailto:<?php echo esc_attr( antispambot( mlc_setting( 'admin_email' ) ) ); ?>?subject=<?php echo rawurlencode( 'Promowanie: ' . $post->post_title ); ?>"><?php esc_html_e( 'Zapytaj o promowanie', 'mlc' ); ?></a>
		</aside>
	<?php endif; ?>

	<section class="mlc-panel mlc-dash__leads">
		<h3><?php esc_html_e( 'Ostatnie zapytania', 'mlc' ); ?></h3>
		<?php if ( ! $leads ) : ?>
			<p class="mlc-muted"><?php esc_html_e( 'Nie masz jeszcze zapytań. Uzupełniony profil z logo, cenami i opisem zwiększa szansę na kontakt.', 'mlc' ); ?></p>
		<?php else : ?>
			<div class="mlc-table-wrap"><table class="mlc-table">
				<thead><tr><th><?php esc_html_e( 'Data', 'mlc' ); ?></th><th><?php esc_html_e( 'Klient', 'mlc' ); ?></th><th><?php esc_html_e( 'Miejsce', 'mlc' ); ?></th><th><?php esc_html_e( 'Szczegóły', 'mlc' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $leads as $l ) : ?>
					<?php
					$m = static fn( $k ) => (string) get_post_meta( $l->ID, '_mlc_' . $k, true );
					?>
					<tr>
						<td><?php echo esc_html( get_the_date( 'd.m.Y H:i', $l ) ); ?></td>
						<td><strong><?php echo esc_html( $m( 'name' ) ); ?></strong><br>
							<a href="mailto:<?php echo esc_attr( $m( 'email' ) ); ?>"><?php echo esc_html( $m( 'email' ) ); ?></a>
							<?php if ( $m( 'phone' ) ) : ?><br><a href="<?php echo esc_attr( mlc_tel_href( $m( 'phone' ) ) ); ?>"><?php echo esc_html( $m( 'phone' ) ); ?></a><?php endif; ?></td>
						<td><?php echo esc_html( $m( 'place_label' ) ); ?><br><span class="mlc-muted"><?php echo esc_html( $m( 'property' ) ); ?></span></td>
						<td><?php echo $m( 'car' ) ? '<em>' . esc_html( $m( 'car' ) ) . '</em><br>' : ''; ?><?php echo nl2br( esc_html( $m( 'message' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
		<?php endif; ?>
	</section>
</div>
