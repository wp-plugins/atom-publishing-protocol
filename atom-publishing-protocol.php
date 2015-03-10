<?php
/*
Plugin Name: Atom Publishing Protocol
Plugin URI: http://wordpress.org
Author: wordpressdotorg
Author URI: http://wordpress.org
Description: Atom Publishing Protocol support for WordPress
Version: 1.0.1
*/

/** Atom Publishing Protocol Class */
require_once( ABSPATH . WPINC . '/atomlib.php' );

if ( ! class_exists( 'wp_atom_server' ) ) {
	/** Atom Server **/
	require_once( dirname( __FILE__ ) . '/class-wp-atom-server.php' );
}

class AtomPublishingProtocol {
	private static $instance;
	public static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new AtomPublishingProtocol;

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		if ( false === strpos( $_SERVER['REQUEST_URI'], '/wp-app.php' ) )
			return;

		add_filter( 'option_enable_app', '__return_true' );

		if ( file_exists( ABSPATH . 'wp-app.php' ) )
			return;

		/**
		 * WordPress is handling an Atom Publishing Protocol request.
		 *
		 * @var bool
		 */
		define( 'APP_REQUEST', true );

		$this->filters();

		/** Admin Image API for metadata updating */
		require_once( ABSPATH . '/wp-admin/includes/image.php' );

		$_SERVER['PATH_INFO'] = preg_replace( '/.*\/wp-app\.php/', '', $_SERVER['REQUEST_URI'] );

		// Allow for a plugin to insert a different class to handle requests.
		$wp_atom_server_class = apply_filters( 'wp_atom_server_class', 'wp_atom_server' );
		$wp_atom_server = new $wp_atom_server_class;

		// Handle the request
		$wp_atom_server->handle_request();

		exit();
	}

	public function filters() {
		add_action( 'xmlrpc_rsd_apis', array( $this, 'rsd_api' ) );

		add_action( 'publish_post', array( $this, 'publish_post' ) );

		add_filter( 'wp_die_handler', array( $this, 'die_handler' ), 2000 );

		add_filter( 'show_admin_bar', '__return_false', 2000 );

		add_action( 'parse_request', array( $this, 'request' ) );

		add_filter( 'rewrite_rules_array', array( $this, 'rewrite' ) );
	}

	/**
	 * Add the Atom API to the RSD list
	 *
	 */
	public function rsd_api() {
		printf( '<api name="Atom" blogID="" preferred="false" apiLink="%s" />', site_url( 'wp-app.php/service', 'rpc' ) );
	}

	/**
	 * Fire 'app_publish_post' for back-compat
	 *
	 * @param int $post_id
	 */
	public function publish_post( $post_id ) {
		do_action( 'app_publish_post', $post_id );
	}

	public function die_handler( $handler ) {
		return '_scalar_wp_die_handler';
	}

	/**
	 * Remove the error query var in case the rewrite exists
	 *
	 * @param WP $request
	 */
	public function request( &$request ) {
		$request->set_query_var( 'error', '' );
	}

	/**
	 * Remove the 403 rewrite for wp-app.php
	 *
	 * @param array $rules Map of $regex => $query
	 * @return array
	 */
	public function rewrite( $rules ) {
		$rewrites = $rules;
		unset( $rewrites['.*wp-app\.php$'], $rewrites['.*wp-app\.php/?(.+)?$'] );

		return $rewrites;
	}
}
AtomPublishingProtocol::get_instance();

/**
 * Writes logging info to a file.
 *
 * @since WP 2.2.0
 * @deprecated WP 3.4.0
 * @deprecated Use error_log()
 * @link http://www.php.net/manual/en/function.error-log.php
 *
 * @param string $label Type of logging
 * @param string $msg Information describing logging reason.
 */
if ( ! function_exists( 'log_app' ) ) {
	function log_app( $label, $msg ) {
		_deprecated_function( __FUNCTION__, '3.4', 'error_log()' );
		if ( ! empty( $GLOBALS['app_logging'] ) )
			error_log( $label . ' - ' . $msg );
	}
}