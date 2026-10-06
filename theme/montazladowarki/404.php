<?php
/**
 * 404.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="mlt-page">
	<div class="mlt-wrap mlt-wrap--narrow mlt-404">
		<p class="mlt-404__code">404</p>
		<h1 class="mlt-page__title"><?php esc_html_e( 'Tej strony nie ma — ale instalator na pewno jest', 'mlt' ); ?></h1>
		<p><?php esc_html_e( 'Wpisz miejscowość, a pokażemy firmy z okolicy.', 'mlt' ); ?></p>
		<?php
		if ( mlt_has_core() ) {
			echo do_shortcode( '[mlc_search_form size="compact"]' );
		}
		?>
	</div>
</main>
<?php
get_footer();
