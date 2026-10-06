<?php
/**
 * Formularz wyszukiwania.
 *
 * @var string $q
 * @var int    $place
 * @var string $size large|compact
 */
defined( 'ABSPATH' ) || exit;
?>
<form class="mlc-search mlc-search--<?php echo esc_attr( $size ); ?>" action="<?php echo esc_url( mlc_page_url( 'search' ) ); ?>" method="get" role="search" data-mlc-search>
	<label class="screen-reader-text mlc-sr" for="mlc-q-<?php echo esc_attr( $size ); ?>"><?php esc_html_e( 'Adres lub miejscowość montażu', 'mlc' ); ?></label>
	<div class="mlc-search__field">
		<svg class="mlc-search__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
		<input id="mlc-q-<?php echo esc_attr( $size ); ?>" class="mlc-search__input" type="text" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Wpisz adres lub miejscowość, np. Piaseczno', 'mlc' ); ?>" autocomplete="off" required data-mlc-ac>
		<input type="hidden" name="place" value="<?php echo $place ? (int) $place : ''; ?>" data-mlc-ac-id>
		<input type="hidden" name="lat" value="" data-mlc-lat disabled>
		<input type="hidden" name="lng" value="" data-mlc-lng disabled>
		<button type="button" class="mlc-search__locate" data-mlc-locate title="<?php esc_attr_e( 'Użyj mojej lokalizacji', 'mlc' ); ?>" aria-label="<?php esc_attr_e( 'Użyj mojej lokalizacji', 'mlc' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm9 3h-2.06A7 7 0 0 0 13 5.06V3h-2v2.06A7 7 0 0 0 5.06 11H3v2h2.06A7 7 0 0 0 11 18.94V21h2v-2.06A7 7 0 0 0 18.94 13H21v-2zm-9 6a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/></svg>
		</button>
	</div>
	<button type="submit" class="mlc-btn mlc-btn--primary mlc-search__submit"><?php esc_html_e( 'Znajdź instalatora', 'mlc' ); ?></button>
</form>
