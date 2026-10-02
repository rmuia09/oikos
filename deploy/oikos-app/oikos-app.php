<?php
/**
 * Plugin Name: Oikos
 * Description: Deploys the Oikos estate-management app into the site root at /oikos, and serves it.
 * Version:     1.1.0
 * Author:      Oikos
 *
 * Upload the zipped folder as a normal plugin and activate it. On activation
 * the built app in ./build is copied to <site-root>/oikos, so the web server
 * serves it as plain static files — correct Content-Type, fast, and no
 * WordPress in the path. The app appears at:
 *
 *     https://your-site/oikos/
 *
 * A PHP fallback serves the app too, for hosts where the site root isn't
 * writable; the admin notice tells you if the copy failed.
 */

defined( 'ABSPATH' ) || exit;

/* Where the app is mounted. Change to 'app' for /app, etc. */
define( 'OIKOS_SLUG', 'oikos' );
define( 'OIKOS_SRC', plugin_dir_path( __FILE__ ) . 'build' );
define( 'OIKOS_DEST', trailingslashit( ABSPATH ) . OIKOS_SLUG );

/** Recursively copy a directory, creating the destination as needed. */
function oikos_copy_dir( $src, $dst ) {
	if ( ! is_dir( $src ) ) {
		return false;
	}
	if ( ! is_dir( $dst ) && ! wp_mkdir_p( $dst ) ) {
		return false;
	}
	/* Some hosts create PHP-written directories owner-only, and Apache then
	   answers 403. Force web-readable permissions. */
	@chmod( $dst, 0755 );

	$items = @scandir( $src );
	if ( ! $items ) {
		return false;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$s = $src . '/' . $item;
		$d = $dst . '/' . $item;
		if ( is_dir( $s ) ) {
			if ( ! oikos_copy_dir( $s, $d ) ) {
				return false;
			}
		} elseif ( ! @copy( $s, $d ) ) {
			return false;
		} else {
			@chmod( $d, 0644 );
		}
	}
	return true;
}

/** Deploy the built app into the site root. Returns true on success. */
function oikos_deploy() {
	return oikos_copy_dir( OIKOS_SRC, OIKOS_DEST );
}

register_activation_hook( __FILE__, function () {
	oikos_deploy();
	/* Point reset links at the app immediately, even if activation happens
	   without an admin page load (WP-CLI, a deploy script). */
	update_option( 'em_app_url', untrailingslashit( home_url() ) . '/' . OIKOS_SLUG );
	flush_rewrite_rules();
} );

/* Warn if the static copy isn't there — the app will still work via the PHP
   fallback below, but static files are what you want. */
add_action( 'admin_notices', function () {
	if ( ! is_dir( OIKOS_DEST ) ) {
		echo '<div class="notice notice-warning"><p><strong>Oikos:</strong> could not write <code>'
			. esc_html( OIKOS_DEST ) . '</code>. The app is being served through PHP instead. '
			. 'Make the site root writable and reactivate to switch to static files.</p></div>';
	}
} );

/* Re-copy when the plugin is updated (build changed) but not reactivated. */
add_action( 'upgrader_process_complete', function ( $upgrader, $options ) {
	if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
		oikos_deploy();
	}
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * Fallback: serve the app through PHP when the static copy isn't available
 * (site root not writable). If the files were deployed, the web server serves
 * them first and this never runs.
 * ------------------------------------------------------------------------- */

add_action( 'init', function () {
	$slug = preg_quote( OIKOS_SLUG, '/' );
	add_rewrite_rule( '^' . $slug . '/?$', 'index.php?oikos_app=1', 'top' );
	add_rewrite_rule( '^' . $slug . '/(.+)$', 'index.php?oikos_app=1&oikos_asset=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'oikos_app';
	$vars[] = 'oikos_asset';
	return $vars;
} );

/* WordPress treats the route as a page and appends a trailing slash, 301-ing
   every asset. Turn that off for our requests only. */
add_filter( 'redirect_canonical', function ( $redirect_url ) {
	return get_query_var( 'oikos_app' ) ? false : $redirect_url;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'oikos_app' ) ) {
		return;
	}

	$rel = ltrim( (string) get_query_var( 'oikos_asset' ), '/' );
	if ( false !== strpos( $rel, '..' ) ) {
		status_header( 400 );
		exit;
	}

	$file = '' === $rel ? 'index.html' : $rel;
	$full = OIKOS_SRC . '/' . $file;
	if ( is_dir( $full ) ) {
		$full = rtrim( $full, '/' ) . '/index.html';
	}
	if ( ! is_file( $full ) ) {
		$full = OIKOS_SRC . '/app/index.html';
	}
	if ( ! is_file( $full ) ) {
		status_header( 404 );
		exit;
	}

	$ext   = strtolower( pathinfo( $full, PATHINFO_EXTENSION ) );
	$mimes = array(
		'html' => 'text/html', 'js' => 'application/javascript', 'css' => 'text/css',
		'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
		'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
		'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff' => 'font/woff',
		'woff2' => 'font/woff2', 'txt' => 'text/plain', 'map' => 'application/json',
	);
	$type  = isset( $mimes[ $ext ] ) ? $mimes[ $ext ] : 'application/octet-stream';
	$texty = 0 === strpos( $type, 'text/' ) || in_array( $type, array( 'application/javascript', 'application/json', 'image/svg+xml' ), true );

	header( 'Content-Type: ' . $type . ( $texty ? '; charset=utf-8' : '' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'html' === $ext ? 'Cache-Control: no-cache, must-revalidate' : 'Cache-Control: public, max-age=31536000, immutable' );

	readfile( $full );
	exit;
} );
