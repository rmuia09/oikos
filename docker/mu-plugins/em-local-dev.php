<?php
/**
 * Plugin Name: Estate Manager — Local Dev Helpers
 * Description: Docker-only helpers so the headless stack works over plain http://localhost.
 *
 *  1. EM_SEED_DEMO — activation creates the sample estate (units, tenants,
 *     leases, payments) so the Gatsby app has something to render.
 *  2. Allows WordPress Application Passwords over HTTP. WP refuses to issue or
 *     accept them without SSL, but local dev is plain http://localhost:8888.
 *
 * Never load this outside local development.
 */

defined( 'ABSPATH' ) || exit;

define( 'EM_SEED_DEMO', true );

add_filter( 'wp_is_application_passwords_available', '__return_true' );