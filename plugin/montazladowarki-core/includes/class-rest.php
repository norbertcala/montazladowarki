<?php
/**
 * REST API: /wp-json/mlc/v1/places, /wp-json/mlc/v1/search
 */

defined( 'ABSPATH' ) || exit;

class MLC_Rest {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'mlc/v1',
			'/places',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					$res = new WP_REST_Response( MLC_Geo::autocomplete( (string) $req['q'], 8 ) );
					$res->header( 'Cache-Control', 'public, max-age=86400' );
					return $res;
				},
			)
		);

		register_rest_route(
			'mlc/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'search' ),
			)
		);
	}

	public static function search( WP_REST_Request $req ) {
		$loc = MLC_Geo::resolve( $req->get_params() );
		if ( ! $loc ) {
			return new WP_Error( 'mlc_no_location', __( 'Nie rozpoznano lokalizacji.', 'mlc' ), array( 'status' => 400 ) );
		}
		$result = MLC_Search::find(
			$loc['lat'],
			$loc['lng'],
			array(
				'services' => (array) $req->get_param( 'services' ),
				'page'     => (int) $req->get_param( 'page' ),
			)
		);
		$items = array();
		foreach ( $result['items'] as $it ) {
			$items[] = MLC_Frontend::installer_public( $it );
		}
		return array(
			'location' => array(
				'label' => $loc['label'],
				'lat'   => $loc['lat'],
				'lng'   => $loc['lng'],
			),
			'total'    => $result['total'],
			'pages'    => $result['pages'],
			'items'    => $items,
		);
	}
}
