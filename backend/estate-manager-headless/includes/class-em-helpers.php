<?php
/**
 * Formatting, date and admin-presentation helpers shared by every page.
 */

defined( 'ABSPATH' ) || exit;

class EM_Helpers {

	/** ob level we opened in start_output_buffer(), or null when inactive. */
	private static $buffer_level = null;

	/** Configured currency symbol, e.g. "KSh", "$", "₦", "R". */
	public static function currency() {
		$symbol = trim( (string) EM_Settings::get( 'currency', 'KSh' ) );
		return '' === $symbol ? 'KSh' : $symbol;
	}

	/** Money using the configured currency, e.g. "KSh 2,200". */
	public static function money( $n ) {
		return self::currency() . ' ' . number_format( (float) round( (float) $n ), 0 );
	}

	/** @deprecated Use money(); kept so older integrations keep working. */
	public static function ksh( $n ) {
		return self::money( $n );
	}

	public static function amount( $n ) {
		return number_format( (float) $n, 2, '.', '' );
	}

	/** Days left until a Y-m-d date (negative = past). */
	public static function days_left( $date_str ) {
		$d = strtotime( $date_str );
		if ( ! $d ) {
			return 0;
		}
		return (int) ceil( ( $d - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
	}

	public static function add_months( $ts, $months ) {
		$d      = (int) date( 'j', $ts );
		$y      = (int) date( 'Y', $ts );
		$m      = (int) date( 'n', $ts );
		$new_m  = $m + (int) $months;
		$new_y  = $y + (int) floor( ( $new_m - 1 ) / 12 );
		$new_m  = ( ( $new_m - 1 ) % 12 ) + 1;
		$last   = (int) date( 't', strtotime( "{$new_y}-{$new_m}-01" ) );
		return strtotime( sprintf( '%04d-%02d-%02d', $new_y, $new_m, min( $d, $last ) ) );
	}

	public static function monday_of( $ts ) {
		$w = (int) date( 'N', $ts ); // 1 = Mon.
		return strtotime( '-' . ( $w - 1 ) . ' days 00:00:00', $ts );
	}

	public static function month_start( $ym ) {
		return $ym . '-01';
	}

	public static function month_end( $ym ) {
		return date( 'Y-m-t', strtotime( $ym . '-01' ) );
	}

	/** List of YYYY-MM keys from..to inclusive. */
	public static function months_between( $from, $to ) {
		$out    = array();
		$y      = (int) substr( $from, 0, 4 );
		$m      = (int) substr( $from, 5, 2 );
		$ty     = (int) substr( $to, 0, 4 );
		$tm     = (int) substr( $to, 5, 2 );
		while ( $y < $ty || ( $y === $ty && $m <= $tm ) ) {
			$out[] = sprintf( '%04d-%02d', $y, $m );
			$m++;
			if ( $m > 12 ) {
				$m = 1;
				$y++;
			}
		}
		return $out;
	}

	public static function today_mysql() {
		return current_time( 'Y-m-d' );
	}

	public static function now_mysql() {
		return current_time( 'mysql' );
	}

	/**
	 * MySQL date-truncation to the start of the week (Monday), like the
	 * original SQLite "date - weekday" expression.
	 */
	public static function week_start_expr( $col ) {
		return "DATE(SUBDATE({$col}, WEEKDAY({$col})))";
	}

	/* ---------------- Pagination ---------------- */

	/** Apply LIMIT/OFFSET to a query built with placeholders. Returns [sql, params]. */
	public static function paginate( $base_sql, $params, $page, $per = 20 ) {
		$page  = max( 1, (int) $page );
		$per   = max( 1, min( 100, (int) $per ) );
		$off   = ( $page - 1 ) * $per;
		$sql   = $base_sql . " LIMIT {$per} OFFSET {$off}";
		return array( $sql, $params );
	}

	public static function page_arg() {
		return max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	}

	/** Output the ‹ Prev / Next › control. */
	public static function paginator( $total, $page, $per = 20 ) {
		$total = (int) $total;
		$per   = max( 1, (int) $per );
		$pages = (int) ceil( $total / $per );
		$page  = max( 1, min( $pages, (int) $page ) );
		$from  = $total === 0 ? 0 : ( ( $page - 1 ) * $per + 1 );
		$to    = min( $total, $page * $per );
		$qs    = $_GET;
		$link  = static function ( $p ) use ( $qs ) {
			$qs['paged'] = $p;
			return '?' . http_build_query( $qs );
		};
		?>
		<div class="em-paginator">
			<span class="em-count"><?php echo esc_html( $from . '–' . $to . ' of ' . $total ); ?></span>
			<?php if ( $page > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( $link( $page - 1 ) ); ?>">‹ Prev</a>
			<?php endif; ?>
			<span class="em-page">Page <?php echo esc_html( $page ) . ' of ' . esc_html( max( 1, $pages ) ); ?></span>
			<?php if ( $page < $pages ) : ?>
				<a class="button" href="<?php echo esc_url( $link( $page + 1 ) ); ?>">Next ›</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Render the page title + subtitle + optional right-aligned primary button. */
	public static function header( $title, $subtitle = '', $button_text = '', $button_url = '' ) {
		?>
		<div class="em-page-head">
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p class="em-subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $button_text && $button_url ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Display flash/success notices. */
	public static function notices() {
		$success = get_transient( 'em_success' );
		$error   = get_transient( 'em_error' );
		if ( $success || is_string( $success ) ) :
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success ); ?></p></div>
			<?php
			delete_transient( 'em_success' );
		endif;
		if ( $error || is_string( $error ) ) :
			?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php
			delete_transient( 'em_error' );
		endif;
	}

	public static function success( $msg ) {
		set_transient( 'em_success', $msg, 60 );
	}

	public static function error( $msg ) {
		set_transient( 'em_error', $msg, 60 );
	}

	/**
	 * Discard our own output buffer so a redirect can still send headers.
	 *
	 * WordPress prints admin-header.php before invoking the page callback, so by
	 * the time a POST handler inside a page calls redirect() the response body
	 * has already begun and wp_safe_redirect() only works on hosts that happen
	 * to enable output_buffering. Buffering POST requests from admin_init keeps
	 * the redirect reliable; the success/error flash is already stored as a
	 * transient, so the discarded markup was never needed.
	 */
	public static function start_output_buffer() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}
		self::$buffer_level = ob_get_level();
		ob_start();
		register_shutdown_function( array( __CLASS__, 'flush_output_buffer' ) );
	}

	/** Close anything we opened, without discarding it. */
	public static function flush_output_buffer() {
		while ( ob_get_level() > self::$buffer_level ) {
			ob_end_flush();
		}
		self::$buffer_level = null;
	}

	/** Drop the buffered markup, keeping any buffer we did not open. */
	private static function discard_output_buffer() {
		if ( null === self::$buffer_level ) {
			return;
		}
		while ( ob_get_level() > self::$buffer_level ) {
			ob_end_clean();
		}
		self::$buffer_level = null;
	}

	public static function redirect( $url ) {
		self::discard_output_buffer();
		wp_safe_redirect( $url );
		exit;
	}

	/** Current admin page URL, preserving filters. */
	public static function page_url( $slug, $extra = array() ) {
		$qs = array_merge( array( 'page' => $slug ), $extra );
		return add_query_arg( $qs, admin_url( 'admin.php' ) );
	}

	public static function method() {
		return strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
	}

	public static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
	}

	public static function get( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : $default;
	}

	/** Nonce verify + honour a redirect-after-post pattern. */
	public static function verify( $action ) {
		return check_admin_referer( $action . '-nonce' );
	}

	public static function nonce_field( $action ) {
		wp_nonce_field( $action . '-nonce' );
	}

	/** Normalise phone / email at point of entry. */
	public static function clean_phone( $phone ) {
		return preg_replace( '/[^0-9+]/', '', (string) $phone );
	}

	/**
	 * The country code assumed for numbers typed without one. Defaults to
	 * Kenya (254) but is a setting + filter, so adding another market is a
	 * config change rather than a code change.
	 */
	public static function default_country_code() {
		$cc = (string) apply_filters(
			'em_default_country_code',
			EM_Settings::get( 'phone_country_code', '254' )
		);
		$cc = preg_replace( '/\D/', '', $cc );
		return '' !== $cc ? $cc : '254';
	}

	/**
	 * Normalise a phone number to E.164 (+254712345678), which is what SMS
	 * gateways expect.
	 *
	 * Accepts a local number (0712 345 678, 712345678), one already carrying
	 * the country code (254712345678) or a full international one
	 * (+254712345678 / +447700900123). Returns '' when it can't be salvaged.
	 *
	 * @param string      $phone        Raw input.
	 * @param string|null $country_code Override the default country code.
	 */
	public static function normalize_phone( $phone, $country_code = null ) {
		$raw = trim( (string) $phone );
		if ( '' === $raw ) {
			return '';
		}

		$explicit_plus = ( 0 === strpos( ltrim( $raw ), '+' ) );
		$digits        = preg_replace( '/\D/', '', $raw );
		if ( '' === $digits ) {
			return '';
		}

		$cc = ( null === $country_code )
			? self::default_country_code()
			: preg_replace( '/\D/', '', (string) $country_code );

		/* A leading 0 is a national trunk prefix and never part of E.164. */
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = ltrim( $digits, '0' );
		}

		/* Only prepend the country code when it isn't already there. */
		if ( ! $explicit_plus && '' !== $cc ) {
			$already_has_cc = ( 0 === strpos( $digits, $cc ) && strlen( $digits ) > strlen( $cc ) + 6 );
			if ( ! $already_has_cc ) {
				$digits = $cc . $digits;
			}
		}

		/* E.164 allows 8–15 digits including the country code. */
		if ( strlen( $digits ) < 8 || strlen( $digits ) > 15 ) {
			return '';
		}
		return '+' . $digits;
	}

	public static function clean_email( $email ) {
		return sanitize_email( $email );
	}

	/** Classify a user-agent into [device, browser] like the original visit stats. */
	public static function classify_ua( $ua ) {
		$ua = (string) $ua;
		$mobile = (bool) preg_match( '/Mobile|Android|iPhone|iPod/i', $ua );
		$tablet = (bool) preg_match( '/iPad|Tablet/i', $ua );
		$bot    = (bool) preg_match( '/bot|curl|wget|python-requests|Postman|httpclient|Googlebot|facebookexternalhit/i', $ua );
		$device = $bot ? 'bot / script' : ( $tablet ? 'tablet' : ( $mobile ? 'phone' : 'desktop' ) );
		if ( strpos( $ua, 'Edg/' ) !== false ) {
			$browser = 'Edge';
		} elseif ( strpos( $ua, 'OPR/' ) !== false || strpos( $ua, 'Opera' ) !== false ) {
			$browser = 'Opera';
		} elseif ( strpos( $ua, 'Firefox/' ) !== false ) {
			$browser = 'Firefox';
		} elseif ( strpos( $ua, 'Chrome/' ) !== false ) {
			$browser = 'Chrome';
		} elseif ( strpos( $ua, 'Safari/' ) !== false ) {
			$browser = 'Safari';
		} elseif ( strpos( $ua, 'facebookexternalhit' ) !== false ) {
			$browser = 'Facebook';
		} else {
			$browser = $ua ? 'other' : '—';
		}
		return array( $device, $browser );
	}
}