<?php
/**
 * Wpis (poradnik).
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="mlt-page">
	<div class="mlt-wrap mlt-wrap--narrow">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'mlt-prose' ); ?>>
				<p class="mlc-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
				<h1 class="mlt-page__title"><?php the_title(); ?></h1>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="mlt-single__img"><?php the_post_thumbnail( 'large' ); ?></figure>
				<?php endif; ?>
				<?php the_content(); ?>
			</article>
			<?php if ( mlt_has_core() ) : ?>
				<aside class="mlt-inline-search">
					<h2><?php esc_html_e( 'Znajdź instalatora w swojej okolicy', 'mlt' ); ?></h2>
					<?php echo do_shortcode( '[mlc_search_form size="compact"]' ); ?>
				</aside>
			<?php endif; ?>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
