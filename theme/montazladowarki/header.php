<?php
/**
 * Nagłówek.
 */
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0f1b24">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="mlt-skip" href="#main"><?php esc_html_e( 'Przejdź do treści', 'mlt' ); ?></a>
<header class="mlt-header">
	<div class="mlt-wrap mlt-header__inner">
		<?php mlt_brand(); ?>
		<button class="mlt-burger" type="button" aria-expanded="false" aria-controls="mlt-nav" data-mlt-burger>
			<span></span><span></span><span></span><span class="mlc-sr"><?php esc_html_e( 'Menu', 'mlt' ); ?></span>
		</button>
		<nav class="mlt-nav" id="mlt-nav" aria-label="<?php esc_attr_e( 'Menu główne', 'mlt' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'mlt-nav__list',
					'depth'          => 1,
					'fallback_cb'    => 'mlt_fallback_menu',
				)
			);
			?>
			<?php if ( mlt_has_core() ) : ?>
				<a class="mlc-btn mlc-btn--primary mlc-btn--sm mlt-nav__cta" href="<?php echo esc_url( mlc_page_url( 'join' ) ); ?>"><?php esc_html_e( 'Dodaj firmę za darmo', 'mlt' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>
