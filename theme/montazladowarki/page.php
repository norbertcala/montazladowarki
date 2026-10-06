<?php
/**
 * Strona.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="mlt-page">
	<div class="mlt-wrap">
		<?php
		while ( have_posts() ) :
			the_post();
			$content = get_post()->post_content;
			$app     = has_shortcode( $content, 'mlc_search_results' ) || has_shortcode( $content, 'mlc_dashboard' );
			?>
			<article <?php post_class( $app ? 'mlt-app' : 'mlt-prose' ); ?>>
				<?php if ( ! $app ) : ?>
					<h1 class="mlt-page__title"><?php the_title(); ?></h1>
				<?php endif; ?>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
