<?php
/**
 * Plugin Name: Oikos — CORS & hardening
 * Description: Restricts the Estate Manager REST API to the app's origin and forces Application Passwords. Copy to wp-content/mu-plugins/ in production.
 *
 * Edit OIKOS_APP_ORIGIN below to match your app's subdomain exactly —
 * scheme + host, no trailing slash.
 */

defined( 'ABSPATH' ) || exit;

define( 'OIKOS_APP_ORIGIN', 'https://app.example.com' );

/* Only answer CORS pre-flight and responses for the real app origin. */
add_filter( 'em_cors_allow_origin', function () {
	return OIKOS_APP_ORIGIN;
} );

/*
 * Refuse account-password logins; require Application Passwords. This keeps
 * a user's real WordPress password off the wire on every API request.
 * Remove this filter if you deliberately want account-password sign-in.
 */
add_filter( 'em_allow_password_login', '__return_false' );
