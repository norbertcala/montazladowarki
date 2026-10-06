<?php
/**
 * Blog / poradnik i archiwa.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="mlt-page">
	<div class="mlt-wrap">
		<header class="mlt-archive__head">
			<?php if ( is_home() && ! is_front_page() ) : ?>
				<h1 class="mlt-page__title"><?php single_post_title(); ?></h1>
			<?php elseif ( is_archive() ) : ?>
				<?php the_archive_title( '<h1 class="mlt-page__title">', '</h1>' ); ?>
				<?php the_archive_description( '<div class="mlt-archive__desc">', '</div>' ); ?>
			<?php elseif ( is_search() ) : ?>
				<h1 class="mlt-page__title"><?php printf( esc_html__( 'Wyniki dla: %s', 'mlt' ), esc_html( get_search_query() ) ); ?></h1>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="mlt-posts">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'mlt-post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="mlt-post-card__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?></a>
						<?php endif; ?>
						<div class="mlt-post-card__body">
							<p class="mlc-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination( array( 'class' => 'mlc-pagination' ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nic tu jeszcze nie ma.', 'mlt' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
