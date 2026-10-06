<?php
/**
 * Plugin Name:       Montażładowarki Core
 * Plugin URI:        https://montazladowarki.pl
 * Description:       Katalog instalatorów ładowarek do samochodów elektrycznych: wyszukiwanie po lokalizacji, panel instalatora, zapytania ofertowe, strony SEO miast i promowanie ogłoszeń.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            NorbiSoft
 * Author URI:        https://techlove.pl
 * License:           GPL-2.0-or-later
 * Text Domain:       mlc
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'MLC_VERSION', '0.1.0' );
define( 'MLC_DB_VERSION', '1' );
define( 'MLC_FILE', __FILE__ );
define( 'MLC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLC_URL', plugin_dir_url( __FILE__ ) );

require_once MLC_DIR . 'includes/helpers.php';
require_once MLC_DIR . 'includes/class-install.php';
require_once MLC_DIR . 'includes/class-post-types.php';
require_once MLC_DIR . 'includes/class-geo.php';
require_once MLC_DIR . 'includes/class-search.php';
require_once MLC_DIR . 'includes/class-rest.php';
require_once MLC_DIR . 'includes/class-admin.php';
require_once MLC_DIR . 'includes/class-dashboard.php';
require_once MLC_DIR . 'includes/class-leads.php';
require_once MLC_DIR . 'includes/class-seo.php';
require_once MLC_DIR . 'includes/class-frontend.php';
require_once MLC_DIR . 'includes/class-importer.php';
require_once MLC_DIR . 'includes/class-claims.php';

register_activation_hook( __FILE__, array( 'MLC_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MLC_Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'mlc', false, dirname( plugin_basename( MLC_FILE ) ) . '/languages' );
		MLC_Install::maybe_upgrade();
		MLC_Post_Types::init();
		MLC_Rest::init();
		MLC_Admin::init();
		MLC_Dashboard::init();
		MLC_Leads::init();
		MLC_SEO::init();
		MLC_Frontend::init();
		MLC_Importer::init();
		MLC_Claims::init();
	}
);
