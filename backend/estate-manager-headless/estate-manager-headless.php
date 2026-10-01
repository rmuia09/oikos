<?php
/**
 * Plugin Name:       Estate Manager — Headless REST API
 * Description:       Exposes the Estate Manager backend as a WordPress REST API (namespace em/v1) for the Gatsby front-end. Reuses the same tables and business logic as the admin plugin.
 * Version:           1.0.0
 * Author:            Estate Manager
 * Text Domain:       estate-manager
 *
 * Activate on the WordPress site that hosts the estate data. The Gatsby app
 * talks to /wp-json/em/v1/* using WordPress Application Passwords.
 *
 * Do NOT run this plugin at the same time as the classic-admin "estate-manager"
 * plugin — both define the same EM_* classes.
 */

defined( 'ABSPATH' ) || exit;

define( 'EM_VERSION', '1.0.0' );
define( 'EM_CAP', 'em_manage_estate' );
define( 'EM_CAP_ACCESS', 'em_manage_access' );
define( 'EM_CAP_VISITORS', 'em_view_visitors' );
define( 'EM_PLUGIN_FILE', __FILE__ );
define( 'EM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once EM_PLUGIN_DIR . 'includes/class-em-db.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-settings.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-notify.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-reports.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-helpers.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-api.php';

register_activation_hook( __FILE__, array( 'EM_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EM_DB', 'deactivate' ) );

add_action( 'init', array( 'EM_DB', 'init' ) );

/* Cron: daily renewal reminders + weekly visit pruning (same as the admin plugin). */
add_action( 'em_daily_reminders', array( 'EM_Notify', 'run_renewal_reminders' ) );
add_action( 'em_weekly_prune', array( 'EM_DB', 'prune_visits' ) );
if ( ! wp_next_scheduled( 'em_daily_reminders' ) ) {
	wp_schedule_event( time(), 'daily', 'em_daily_reminders' );
}
if ( ! wp_next_scheduled( 'em_weekly_prune' ) ) {
	wp_schedule_event( time(), 'weekly', 'em_weekly_prune' );
}

/* CORS so a Gatsby site on another origin can call the REST API. */
add_action( 'rest_api_init', function () {
	add_filter( 'rest_pre_serve_request', function ( $served, $result ) {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$allow  = apply_filters( 'em_cors_allow_origin', $origin );
		if ( $allow ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $allow ) );
			header( 'Access-Control-Allow-Credentials: false' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-EM-Token' );
		}
		if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			status_header( 204 );
			exit;
		}
		return $served;
	}, 10, 2 );
} );

/* Register all em/v1 routes. */
add_action( 'rest_api_init', array( 'EM_API', 'register' ) );