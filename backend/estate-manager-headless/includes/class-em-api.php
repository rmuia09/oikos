<?php
/**
 * REST API for the Gatsby front-end (namespace em/v1).
 *
 * Auth: WordPress core validates Basic auth (username + Application Password)
 * automatically for REST requests; possession_of em_manage_estate is required
 * for business routes, em_manage_access for user/visitor administration.
 */

defined( 'ABSPATH' ) || exit;

class EM_API {

	const NS = 'em/v1';

	/* ---------------------------------------------------------------- */

	public static function register() {
		$std  = array( 'em_manage_estate' );
		$high = array( 'em_manage_access' );

		/* ---------- Auth ---------- */
		register_rest_route( self::NS, '/auth/login', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'auth_login' ),
			'permission_callback' => '__return_true',
		) );
		/* Both reset endpoints are public but rate limited, and neither reveals
		   whether an account exists. */
		register_rest_route( self::NS, '/auth/forgot-password', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'auth_forgot_password' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/auth/reset-password', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'auth_reset_password' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/auth/me', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'auth_me' ),
			'permission_callback' => array( __CLASS__, 'can_use' ),
		) );
		register_rest_route( self::NS, '/auth/change-password', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'auth_change_password' ),
			'permission_callback' => array( __CLASS__, 'can_use' ),
		) );

		/* ---------- Dashboard + reports ---------- */
		self::route( '/dashboard', 'GET', 'dashboard', $std );
		/* The heavy arrears walk, kept off the main dashboard request. */
		self::route( '/dashboard/arrears', 'GET', 'dashboard_arrears', $std );
		self::route( '/reports/summary', 'GET', 'reports_summary', $std );
		self::route( '/reports/period', 'GET', 'reports_period', $std );
		self::route( '/reports/rent-ledger', 'GET', 'reports_ledger', $std );

		/* ---------- Properties ---------- */
		self::route( '/properties', 'GET', 'properties_list', $std );
		self::route( '/properties', 'POST', 'properties_create', $std );
		self::route( '/properties/(?P<id>\d+)', 'PATCH', 'properties_update', $std );
		self::route( '/properties/(?P<id>\d+)', 'DELETE', 'properties_delete', $std );

		/* ---------- Units ---------- */
		self::route( '/units', 'GET', 'units_list', $std );
		self::route( '/units', 'POST', 'units_create', $std );
		self::route( '/units/(?P<id>\d+)', 'PATCH', 'units_update', $std );
		self::route( '/units/(?P<id>\d+)', 'DELETE', 'units_delete', $std );
		self::route( '/units/(?P<id>\d+)/vacate', 'POST', 'units_vacate', $std );

		/* ---------- Tenants ---------- */
		self::route( '/tenants', 'GET', 'tenants_list', $std );
		self::route( '/tenants', 'POST', 'tenants_create', $std );
		self::route( '/tenants/(?P<id>\d+)', 'PATCH', 'tenants_update', $std );
		self::route( '/tenants/(?P<id>\d+)', 'DELETE', 'tenants_delete', $std );

		/* ---------- Onboarding ---------- */
		self::route( '/onboarding', 'POST', 'onboarding', $std );

		/* ---------- Leases ---------- */
		self::route( '/leases', 'GET', 'leases_list', $std );
		/* Full lease history across years — active, renewed, terminated, expired. */
		self::route( '/leases/history', 'GET', 'leases_history', $std );
		self::route( '/leases', 'POST', 'leases_create', $std );
		self::route( '/leases/(?P<id>\d+)', 'PATCH', 'leases_update', $std );
		self::route( '/leases/(?P<id>\d+)', 'DELETE', 'leases_delete', $std );
		self::route( '/leases/(?P<id>\d+)/renew', 'POST', 'leases_renew', $std );
		self::route( '/leases/(?P<id>\d+)/terminate', 'POST', 'leases_terminate', $std );
		self::route( '/leases/(?P<id>\d+)/notice', 'PATCH', 'leases_notice', $std );

		/* ---------- Payments ---------- */
		self::route( '/payments', 'GET', 'payments_list', $std );
		self::route( '/payments', 'POST', 'payments_create', $std );
		self::route( '/payments/(?P<id>\d+)', 'PATCH', 'payments_update', $std );
		self::route( '/payments/(?P<id>\d+)', 'DELETE', 'payments_delete', $std );

		/* ---------- Utilities ---------- */
		self::route( '/utility/rates', 'GET', 'utility_rates_get', $std );
		self::route( '/utility/rates', 'PUT', 'utility_rates_put', $std );
		self::route( '/readings', 'GET', 'readings_list', $std );
		self::route( '/readings', 'POST', 'readings_create', $std );
		self::route( '/readings/(?P<id>\d+)', 'PATCH', 'readings_update', $std );
		self::route( '/readings/(?P<id>\d+)', 'DELETE', 'readings_delete', $std );

		/* ---------- Notifications ---------- */
		self::route( '/notifications', 'GET', 'notifications_list', $std );
		self::route( '/notifications/stats', 'GET', 'notifications_stats', $std );
		self::route( '/reminders/run', 'POST', 'reminders_run', $std );
		self::route( '/integrations/test', 'POST', 'integrations_test', $std );

		/* ---------- Activity / audit trail ---------- */
		self::route( '/activity', 'GET', 'activity', $std );

		/* ---------- Settings ---------- */
		self::route( '/settings', 'GET', 'settings_get', $std );
		self::route( '/settings', 'PUT', 'settings_put', $std );

		/* ---------- Users (admin) ---------- */
		self::route( '/users', 'GET', 'users_list', $high );
		self::route( '/users', 'POST', 'users_create', $high );
		self::route( '/users/(?P<id>\d+)', 'PATCH', 'users_update', $high );
		self::route( '/users/(?P<id>\d+)', 'DELETE', 'users_delete', $high );

		/* ---------- Visitors (admin) ---------- */
		self::route( '/visits', 'GET', 'visits', $high );
	}

	/** Register one route in the em/v1 namespace. */
	private static function route( $path, $method, $cb, $caps ) {
		register_rest_route( self::NS, $path, array(
			'methods'             => $method,
			'callback'            => array( __CLASS__, $cb ),
			'permission_callback' => function () use ( $caps ) {
				return self::check_caps( $caps );
			},
		) );
	}

	/* ---------------- Permissions ---------------- */

	public static function can_use() {
		return self::check_caps( array( 'em_manage_estate' ) );
	}

	private static function check_caps( $caps ) {
		foreach ( $caps as $c ) {
			if ( current_user_can( $c ) ) {
				return true;
			}
		}
		return false;
	}

	public static function err( $msg, $status = 400 ) {
		return new WP_Error( 'em_error', $msg, array( 'status' => $status ) );
	}

	/** Pull the raw JSON body (works for DELETE + PATCH + POST alike). */
	private static function body( WP_REST_Request $r ) {
		$json = $r->get_json_params();
		return is_array( $json ) ? $json : array();
	}

	/* ---------------- Auth ---------------- */

	public static function auth_login( WP_REST_Request $r ) {
		$username = sanitize_user( (string) $r->get_param( 'username' ) );
		$password = (string) $r->get_param( 'password' );
		if ( ! $username || ! $password ) {
			return self::err( 'Username and password are required.', 401 );
		}

		/* An Application Password is the intended credential. If that fails,
		   fall back to the account password so ordinary users are not locked
		   out for not knowing to generate one — but exchange it for a fresh
		   Application Password so the returned token is still revocable. */
		$user = wp_authenticate_application_password( null, $username, $password );

		$minted = null;
		if ( ! $user instanceof WP_User || is_wp_error( $user ) ) {
			$user = wp_authenticate( $username, $password );
			if ( $user instanceof WP_User && ! is_wp_error( $user ) ) {
				if ( ! self::password_login_allowed() ) {
					return self::err( 'Invalid username or application password.', 401 );
				}
				$issued = self::issue_application_password( $user );
				if ( is_wp_error( $issued ) ) {
					return $issued;
				}
				$minted   = $issued['password'];
				$password = $issued['password'];
			}
		}

		if ( ! $user instanceof WP_User || is_wp_error( $user ) ) {
			return self::err( 'Invalid username or password.', 401 );
		}
		if ( get_user_meta( $user->ID, 'em_disabled', true ) ) {
			return self::err( 'This account is disabled.', 403 );
		}
		$token = base64_encode( $username . ':' . $password );

		/* Authenticate the request as this user before logging, so the audit
		   row records who signed in rather than 'system'. */
		wp_set_current_user( $user->ID );
		EM_DB::log(
			'login',
			'user',
			$user->ID,
			$minted ? 'Signed in with account password' : 'Signed in with application password',
			$user->user_login
		);

		return array(
			'token' => $token,
			'user'  => self::user_short( $user ),
			/* Tells the client we minted a throwaway credential, so it can
			   nudge the user toward using an Application Password next time. */
			'issued_application_password' => (bool) $minted,
		);
	}

	/**
	 * Account-password logins are permitted unless explicitly disabled.
	 * Filter `em_allow_password_login` to return false to force
	 * Application Passwords only (recommended for a public deployment).
	 */
	private static function password_login_allowed() {
		return (bool) apply_filters( 'em_allow_password_login', true );
	}

	/** Mint a single-use Application Password and return its plaintext value. */
	private static function issue_application_password( WP_User $user ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return self::err( 'Application passwords are not available.', 500 );
		}
		$created = WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array( 'name' => 'Oikos sign-in' )
		);
		if ( is_wp_error( $created ) ) {
			return self::err( 'Could not start a session for you. Please contact an administrator.', 500 );
		}
		return array(
			'password' => $created[0],
			'item'     => $created[1],
		);
	}

	/* ---------------- Password reset ---------------- */

	/** Throttle repeated attempts from one caller. */
	private static function rate_limit( $bucket, $seconds = 300, $max = 5 ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'em_rl_' . md5( $bucket . '|' . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $seconds );
		return true;
	}

	/**
	 * Start a password reset. Always returns the same neutral response so the
	 * endpoint can't be used to discover which accounts exist.
	 */
	public static function auth_forgot_password( WP_REST_Request $r ) {
		$identifier = trim( (string) $r->get_param( 'identifier' ) );
		$neutral    = array(
			'ok'      => true,
			'channel' => 'sms',
			'message' => 'If that account exists, a reset code is on its way.',
		);

		if ( '' === $identifier ) {
			return self::err( 'Enter your username, phone number or email address.', 400 );
		}
		if ( ! self::rate_limit( 'forgot', 300, 5 ) ) {
			return self::err( 'Too many reset requests. Please wait a few minutes and try again.', 429 );
		}

		$user = self::find_user_by_identifier( $identifier );
		if ( ! $user ) {
			return $neutral;
		}

		/* A reset request is itself auditable, and worth knowing about even
		   though the response says nothing about whether it succeeded. */
		EM_DB::log(
			'reset_requested',
			'user',
			$user->ID,
			'Password reset requested',
			$user->user_login,
			$user
		);

		$phone = (string) get_user_meta( $user->ID, 'em_phone', true );

		/* SMS first: this user base is phone-first and several accounts have no
		   email at all. Only fall back to an emailed link when there is no
		   phone number on file. */
		if ( '' !== $phone ) {
			$code = self::generate_reset_code( $user );
			$sent = EM_Notify::send_sms(
				$phone,
				'Your Oikos password reset code is ' . $code .
				'. It expires in 10 minutes. If you did not request this, ignore this message.'
			);
			if ( empty( $sent['ok'] ) || ! empty( $sent['simulated'] ) ) {
				EM_DB::log(
					'reset_sms_undelivered',
					'user',
					$user->ID,
					! empty( $sent['simulated'] )
						? "Reset SMS not sent — SMS is disabled or Africa's Talking is unconfigured"
						: 'Reset SMS failed: ' . ( $sent['error'] ?? 'unknown error' ),
					$user->user_login,
					$user
				);
			}
			/* No SMS gateway locally, so expose the code to keep the flow
			   testable. Never active outside a local environment. */
			if ( self::is_local_env() ) {
				$neutral['dev_code']  = $code;
				$neutral['dev_phone'] = $phone;
			}
			return $neutral;
		}

		/* No phone number — fall back to an emailed link. */
		$neutral['channel'] = 'email';
		$neutral['message'] = 'If that account exists, a reset link is on its way.';

		if ( ! $user->user_email ) {
			/* Neither channel exists, so the reset can never arrive. Record it
			   rather than fail silently. */
			EM_DB::log(
				'reset_undelivered',
				'user',
				$user->ID,
				'Reset not sent — this account has neither a phone number nor an email address',
				$user->user_login,
				$user
			);
			return $neutral;
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return $neutral;
		}

		$link = add_query_arg(
			array(
				'reset' => $key,
				'login' => rawurlencode( $user->user_login ),
			),
			self::app_base_url() . '/app'
		);

		$sent = EM_Notify::send_email(
			$user->user_email,
			'Reset your Oikos password',
			self::reset_email_body( $user, $link )
		);
		if ( empty( $sent['ok'] ) || ! empty( $sent['simulated'] ) ) {
			EM_DB::log(
				'reset_email_undelivered',
				'user',
				$user->ID,
				! empty( $sent['simulated'] )
					? 'Reset email not sent — email is disabled or SMTP is unconfigured'
					: 'Reset email failed: ' . ( $sent['error'] ?? 'unknown error' ),
				$user->user_login,
				$user
			);
		}

		if ( self::is_local_env() ) {
			$neutral['dev_reset_link'] = $link;
		}

		return $neutral;
	}

	/** Find a login account by username, email address, or phone number. */
	private static function find_user_by_identifier( $identifier ) {
		$identifier = trim( (string) $identifier );
		if ( '' === $identifier ) {
			return null;
		}

		if ( is_email( $identifier ) ) {
			$user = get_user_by( 'email', $identifier );
			if ( $user ) {
				return $user;
			}
		}

		$user = get_user_by( 'login', $identifier );
		if ( $user ) {
			return $user;
		}

		/* Anything that normalises to a Kenyan number is looked up as a phone. */
		$phone = EM_Helpers::normalize_phone( $identifier );
		if ( '' !== $phone ) {
			$found = get_users( array(
				'meta_key'   => 'em_phone',
				'meta_value' => $phone,
				'number'     => 1,
			) );
			if ( ! empty( $found ) ) {
				return $found[0];
			}
		}

		return null;
	}

	/** Generate a 6-digit code, store it hashed with an expiry, return the code. */
	private static function generate_reset_code( WP_User $user ) {
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		update_user_meta( $user->ID, 'em_reset_code', wp_hash_password( $code ) );
		update_user_meta( $user->ID, 'em_reset_code_expires', time() + 10 * MINUTE_IN_SECONDS );
		update_user_meta( $user->ID, 'em_reset_code_attempts', 0 );
		return $code;
	}

	private static function clear_reset_code( $user_id ) {
		delete_user_meta( $user_id, 'em_reset_code' );
		delete_user_meta( $user_id, 'em_reset_code_expires' );
		delete_user_meta( $user_id, 'em_reset_code_attempts' );
	}

	/** Validate a submitted SMS code. Returns true, or a WP_Error. */
	private static function check_reset_code( WP_User $user, $code ) {
		$attempts = (int) get_user_meta( $user->ID, 'em_reset_code_attempts', true );
		if ( $attempts >= 5 ) {
			self::clear_reset_code( $user->ID );
			return self::err( 'Too many incorrect codes. Please request a new one.', 429 );
		}

		$expires = (int) get_user_meta( $user->ID, 'em_reset_code_expires', true );
		if ( $expires < time() ) {
			self::clear_reset_code( $user->ID );
			return self::err( 'That code has expired. Please request a new one.', 400 );
		}

		$hash = (string) get_user_meta( $user->ID, 'em_reset_code', true );
		if ( '' === $hash || ! wp_check_password( $code, $hash ) ) {
			update_user_meta( $user->ID, 'em_reset_code_attempts', $attempts + 1 );
			return self::err( 'That code is not correct.', 400 );
		}
		return true;
	}

	/**
	 * Consume a reset credential and set a new password.
	 *
	 * Two paths share this endpoint:
	 *   - SMS:  { identifier, code, password }
	 *   - link: { reset_key, login, password }
	 */
	public static function auth_reset_password( WP_REST_Request $r ) {
		$password = (string) $r->get_param( 'password' );

		$problem = self::validate_new_password( $password );
		if ( $problem ) {
			return self::err( $problem, 400 );
		}
		if ( ! self::rate_limit( 'reset', 900, 10 ) ) {
			return self::err( 'Too many attempts. Please request a new code.', 429 );
		}

		$code       = trim( (string) $r->get_param( 'code' ) );
		$identifier = trim( (string) $r->get_param( 'identifier' ) );
		$key        = trim( (string) $r->get_param( 'reset_key' ) );
		$login      = sanitize_user( (string) $r->get_param( 'login' ) );

		/* ---- SMS code path ---- */
		if ( '' !== $code ) {
			if ( '' === $identifier ) {
				return self::err( 'Enter the username or phone number you requested the code for.', 400 );
			}
			$user = self::find_user_by_identifier( $identifier );
			if ( ! $user ) {
				return self::err( 'That code is not correct, or it has expired. Request a new one.', 400 );
			}
			$valid = self::check_reset_code( $user, $code );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			return self::complete_password_reset( $user, $password, 'Reset password with SMS code' );
		}

		/* ---- emailed-link path ---- */
		if ( '' === $key || '' === $login ) {
			return self::err( 'This reset link is incomplete. Please request a new one.', 400 );
		}
		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			return self::err( 'This reset link is no longer valid. Please request a new one.', 400 );
		}
		/* Pass the login string, not the WP_User object — core rejects anything
		   that isn't a string with an 'invalid_key' error. */
		if ( check_password_reset_key( $key, $user->user_login ) instanceof WP_Error ) {
			return self::err( 'This reset link has expired or was already used. Please request a new one.', 400 );
		}
		return self::complete_password_reset( $user, $password, 'Reset password via emailed link', $key );
	}

	/** Apply the new password and everything that must follow it. */
	private static function complete_password_reset( WP_User $user, $password, $reason, $key = null ) {
		wp_set_password( $password, $user->ID );
		wp_clear_auth_cookie();

		/* A reset must invalidate every existing credential, including any
		   Application Passwords still in circulation. */
		WP_Application_Passwords::delete_all_application_passwords( $user->ID );
		self::clear_reset_code( $user->ID );

		do_action( 'password_reset', $user, $key );
		EM_DB::log( 'password', 'user', $user->ID, $reason, $user->user_login, $user );

		/* Hand back a working session so the user isn't dumped back to a
		   login form they now know the password for. */
		$issued = self::issue_application_password( $user );
		if ( is_wp_error( $issued ) ) {
			return array( 'ok' => true, 'message' => 'Your password has been changed. You can now sign in.' );
		}
		return array(
			'ok'      => true,
			'message' => 'Your password has been changed.',
			'token'   => base64_encode( $user->user_login . ':' . $issued['password'] ),
			'user'    => self::user_short( $user ),
		);
	}

	/** Shared password policy. Returns an error message, or null when valid. */
	private static function validate_new_password( $password ) {
		$problem = self::validate_password_strength( $password );
		if ( $problem ) {
			return $problem;
		}
		if ( self::password_is_common( $password ) ) {
			return 'That password is too common. Please choose something harder to guess.';
		}
		return null;
	}

	/* ---------------- Lease exit terms & settlement ---------------- */

	/**
	 * Resolve deposit / notice / early-termination terms for a lease.
	 * Explicit request values win; otherwise the estate policy defaults apply.
	 */
	private static function lease_terms_from_request( WP_REST_Request $r, $rent ) {
		$deposit_months = (float) EM_Settings::get( 'default_deposit_months', 1 );
		$notice_default = (int) EM_Settings::get( 'default_notice_period_days', 30 );
		$fee_months     = (float) EM_Settings::get( 'default_early_termination_fee_months', 0 );

		$deposit = null !== $r->get_param( 'deposit_amount' )
			? max( 0, (float) $r->get_param( 'deposit_amount' ) )
			: round( (float) $rent * $deposit_months, 2 );

		$notice = null !== $r->get_param( 'notice_period_days' )
			? max( 0, (int) $r->get_param( 'notice_period_days' ) )
			: max( 0, $notice_default );

		$fee = null !== $r->get_param( 'early_termination_fee' )
			? max( 0, (float) $r->get_param( 'early_termination_fee' ) )
			: round( (float) $rent * $fee_months, 2 );

		return array( 'deposit' => $deposit, 'notice_days' => $notice, 'fee' => $fee );
	}

	/** Whitelist a vacate reason category. */
	private static function vacate_type( $type ) {
		$allowed = array( 'expired', 'early_termination', 'eviction', 'mutual' );
		return in_array( $type, $allowed, true ) ? $type : 'expired';
	}

	/**
	 * Work out the final account when a lease ends.
	 *
	 * The early-termination charge applies only when the tenant ends the lease
	 * before its term — an expired or mutually surrendered lease pays nothing.
	 * A positive refund is owed back to the tenant; a negative one is a balance
	 * the tenant still owes.
	 *
	 * @return array{deposit:float,fee:float,deductions:float,refund:float}
	 */
	private static function compute_settlement( $lease, $vacate_type, $deductions ) {
		$deposit = (float) ( $lease['deposit_amount'] ?? 0 );
		$fee     = ( 'early_termination' === $vacate_type )
			? (float) ( $lease['early_termination_fee'] ?? 0 )
			: 0.0;
		$deduct  = max( 0, (float) $deductions );

		return array(
			'deposit'    => round( $deposit, 2 ),
			'fee'        => round( $fee, 2 ),
			'deductions' => round( $deduct, 2 ),
			'refund'     => round( $deposit - $fee - $deduct, 2 ),
		);
	}

	/** Where the Gatsby app lives, so emailed links land somewhere real. */
	private static function app_base_url() {
		/* Setting first, then the legacy WP option, then WordPress' own URL.
		   The last fallback is wrong for a headless deploy, so the setting
		   exists and is surfaced in the UI. */
		$configured = trim( (string) EM_Settings::get( 'em_app_url', '' ) );
		if ( ! $configured ) {
			$configured = trim( (string) get_option( 'em_app_url', '' ) );
		}
		if ( $configured ) {
			return untrailingslashit( $configured );
		}
		return untrailingslashit( get_home_url() );
	}

	private static function is_local_env() {
		return 'local' === (string) getenv( 'WP_ENVIRONMENT_TYPE' )
			|| 'local' === (string) wp_get_environment_type();
	}

	private static function reset_email_body( WP_User $user, $link ) {
		$name = $user->display_name ? $user->display_name : $user->user_login;
		return "Hello {$name},\n\n"
			. "Someone requested a password reset for your Oikos account.\n\n"
			. "Choose a new password here:\n{$link}\n\n"
			. "This link expires in 24 hours. If you did not request it, you can ignore this email — "
			. "your password will not change.\n";
	}

	/* ---------------- Shared password policy ---------------- */

	/**
	 * The one place password strength is defined. Every path that sets a
	 * password goes through here: signup, admin create, self-service change,
	 * and reset — so the rules can never drift apart.
	 *
	 * @return string|null An error message, or null when the password is fine.
	 */
	public static function validate_password_strength( $password, $min = 8 ) {
		$password = (string) $password;
		$fail     = array();

		if ( strlen( $password ) < $min ) {
			$fail[] = "at least {$min} characters";
		}
		if ( strlen( $password ) > 200 ) {
			$fail[] = 'no more than 200 characters';
		}
		if ( ! preg_match( '/[a-z]/', $password ) ) {
			$fail[] = 'a lowercase letter';
		}
		if ( ! preg_match( '/[A-Z]/', $password ) ) {
			$fail[] = 'an uppercase letter';
		}
		if ( ! preg_match( '/[0-9]/', $password ) ) {
			$fail[] = 'a number';
		}
		if ( preg_match( '/\s$/', $password ) ) {
			$fail[] = 'no trailing space';
		}

		if ( $fail ) {
			/* Readable as a sentence rather than a rule dump. */
			$list   = implode( ', ', $fail );
			$list   = 'a' === substr( $list, 0, 1 ) ? $list : 'a ' . $list;
			return 'Password must contain ' . $list . '.';
		}
		return null;
	}

	/** Common-password denylist. Cheap defence against the obvious choices. */
	private static function password_is_common( $password ) {
		$weak = array(
			'password', 'password1', 'password123', '12345678', '123456789',
			'qwerty123', 'letmein1', 'welcome1', 'admin123', 'iloveyou1',
			'passw0rd', 'abc12345', 'changeme', 'estate123', 'property1',
		);
		return in_array( strtolower( trim( $password ) ), $weak, true );
	}

	public static function auth_me() {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return self::err( 'Not authenticated.', 401 );
		}
		if ( get_user_meta( $user->ID, 'em_disabled', true ) ) {
			return self::err( 'This account is disabled.', 403 );
		}
		return self::user_short( $user );
	}

	public static function auth_change_password( WP_REST_Request $r ) {
		$user  = wp_get_current_user();
		$cur   = (string) $r->get_param( 'current_password' );
		$new   = (string) $r->get_param( 'new_password' );
		if ( ! $cur ) {
			return self::err( 'Your current password is required.', 400 );
		}
		$problem = self::validate_new_password( $new );
		if ( $problem ) {
			return self::err( $problem, 400 );
		}
		if ( ! wp_check_password( $cur, $user->user_pass, $user->ID ) ) {
			return self::err( 'Current password is incorrect.', 403 );
		}
		if ( wp_check_password( $new, $user->user_pass, $user->ID ) ) {
			return self::err( 'Please choose a password different from your current one.', 400 );
		}
		wp_set_password( $new, $user->ID );
		EM_DB::log( 'password', 'user', $user->ID, 'Changed own password', $user->user_login );
		return array( 'ok' => true );
	}

	private static function user_short( WP_User $u ) {
		$is_admin = (bool) ( $u->has_cap( EM_CAP_ACCESS ) );
		return array(
			'id'          => (int) $u->ID,
			'username'    => $u->user_login,
			'full_name'   => $u->display_name,
			'email'       => $u->user_email,
			'phone'       => (string) get_user_meta( $u->ID, 'em_phone', true ),
			'role'        => $is_admin ? 'admin' : 'manager',
			'created_at'  => mysql2date( 'Y-m-d', $u->user_registered ),
			'permissions' => array(
				'is_admin'          => $is_admin,
				'can_manage_users'  => $is_admin,
				'can_view_visitors' => (bool) $u->has_cap( EM_CAP_VISITORS ),
			),
		);
	}

	/* ---------------- Dashboard ---------------- */

	public static function dashboard() {
		$s = EM_Reports::dashboard_stats();
		return array(
			'totalUnits'       => $s['total_units'],
			'occupiedUnits'    => $s['occupied_units'],
			'vacantUnits'      => $s['vacant_units'],
			'expiredLeases'    => (int) $s['expired_leases'],
			'expectedMonthly'  => (float) $s['expected_monthly'],
			'collectedMonth'   => (float) $s['collected_month'],
			'monthOutstanding' => (float) $s['month_outstanding'],
			'pendingUtility'   => (float) $s['pending_utility'],
			'recentPayments'   => $s['recent_payments'],
			'expiringSoon'     => $s['expiring_soon'],
		);
	}

	/**
	 * Who owes rent, accumulated month by month. Split out from /dashboard so
	 * the KPI cards render immediately while this runs.
	 */
	public static function dashboard_arrears( WP_REST_Request $r ) {
		$limit = (int) $r->get_param( 'limit', 8 );
		$limit = max( 1, min( 50, $limit ) );
		$a     = EM_Reports::arrears();
		return array(
			'total'   => $a['total'],
			'count'   => $a['count'],
			'tenants' => array_slice( $a['tenants'], 0, $limit ),
		);
	}

	/* ---------------- Reports ---------------- */

	public static function reports_summary( WP_REST_Request $r ) {
		return EM_Reports::summary(
			(string) $r->get_param( 'period' ),
			$r->get_param( 'property_id' ) ? (int) $r->get_param( 'property_id' ) : null,
			(string) $r->get_param( 'from' ),
			(string) $r->get_param( 'to' ),
			(int) ( $r->get_param( 'limit' ) ? $r->get_param( 'limit' ) : 120 )
		);
	}

	public static function reports_period( WP_REST_Request $r ) {
		$d = EM_Reports::period_detail(
			(string) $r->get_param( 'period' ),
			(string) $r->get_param( 'key' ),
			$r->get_param( 'property_id' ) ? (int) $r->get_param( 'property_id' ) : null
		);
		$d['byProperty'] = $d['by_property'];
		unset( $d['by_property'] );
		return $d;
	}

	public static function reports_ledger( WP_REST_Request $r ) {
		return EM_Reports::rent_ledger(
			(string) $r->get_param( 'from' ),
			(string) $r->get_param( 'to' ),
			$r->get_param( 'property_id' ) ? (int) $r->get_param( 'property_id' ) : null,
			$r->get_param( 'tenant_id' ) ? (int) $r->get_param( 'tenant_id' ) : null
		);
	}

	/* ---------------- Properties ---------------- */

	public static function properties_list() {
		return EM_DB::properties_list();
	}

	public static function properties_create( WP_REST_Request $r ) {
		global $wpdb;
		$name     = trim( (string) $r->get_param( 'name' ) );
		$location = trim( (string) $r->get_param( 'location' ) );
		if ( ! $name ) {
			return self::err( 'Property name is required.' );
		}
		$wpdb->insert( EM_DB::table( 'properties' ), array( 'name' => $name, 'location' => $location, 'active' => 1, 'created_at' => current_time( 'mysql' ) ) );
		$id = (int) $wpdb->insert_id;
		EM_DB::log( 'create', 'property', $id, 'Created property', $name );
		return EM_DB::properties_list();
	}

	public static function properties_update( WP_REST_Request $r ) {
		global $wpdb;
		$id = (int) $r['id'];
		$wpdb->update( EM_DB::table( 'properties' ), array(
			'name'     => trim( (string) $r->get_param( 'name' ) ),
			'location' => trim( (string) $r->get_param( 'location' ) ),
		), array( 'id' => $id ) );
		EM_DB::log( 'update', 'property', $id, 'Updated property', trim( (string) $r->get_param( 'name' ) ) );
		return array( 'ok' => true );
	}

	public static function properties_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a property.' );
		}
		$prop = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'properties' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $prop ) {
			return self::err( 'Property not found.', 404 );
		}
		$unit_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . EM_DB::table( 'units' ) . ' WHERE property_id = %d', $id ) );
		foreach ( (array) $unit_ids as $uid ) {
			EM_DB::delete_unit_cascade( (int) $uid );
		}
		$wpdb->delete( EM_DB::table( 'properties' ), array( 'id' => $id ) );
		EM_DB::log( 'delete', 'property', $id, $reason, $prop['name'] );
		return array( 'ok' => true );
	}

	/* ---------------- Units ---------------- */

	public static function units_list() {
		global $wpdb;
		return $wpdb->get_results( "
			SELECT u.*, p.name AS property_name,
				(SELECT t.name FROM " . EM_DB::table( 'leases' ) . " l JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS tenant_name,
				(SELECT t.phone FROM " . EM_DB::table( 'leases' ) . " l JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS tenant_phone,
				(SELECT l.end_date FROM " . EM_DB::table( 'leases' ) . " l
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS lease_end,
				(SELECT l.tenant_id FROM " . EM_DB::table( 'leases' ) . " l
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS tenant_id,
				(SELECT l.id FROM " . EM_DB::table( 'leases' ) . " l
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS lease_id,
				(SELECT l.deposit_amount FROM " . EM_DB::table( 'leases' ) . " l
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS deposit_amount,
				(SELECT l.early_termination_fee FROM " . EM_DB::table( 'leases' ) . " l
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS early_termination_fee,
				(SELECT l.notice_period_days FROM " . EM_DB::table( 'leases' ) . " l
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS notice_period_days
			FROM " . EM_DB::table( 'units' ) . " u
			JOIN " . EM_DB::table( 'properties' ) . " p ON p.id = u.property_id
			ORDER BY p.name, u.unit_number", ARRAY_A );
	}

	public static function units_create( WP_REST_Request $r ) {
		global $wpdb;
		$data = array(
			'property_id'  => (int) $r->get_param( 'property_id' ),
			'unit_number'  => trim( (string) $r->get_param( 'unit_number' ) ),
			'unit_type'    => (string) $r->get_param( 'unit_type', 'apartment' ),
			'monthly_rent' => (float) $r->get_param( 'monthly_rent', 0 ),
			'status'       => in_array( $r->get_param( 'status' ), array( 'occupied', 'vacant' ), true ) ? $r->get_param( 'status' ) : 'vacant',
			'created_at'   => current_time( 'mysql' ),
		);
		if ( ! $data['property_id'] || ! $data['unit_number'] ) {
			return self::err( 'Property and unit number are required.' );
		}
		$ins = $wpdb->insert( EM_DB::table( 'units' ), $data );
		if ( false === $ins ) {
			return self::err( 'Could not save — unit number already exists in this property?' );
		}
		$id = (int) $wpdb->insert_id;
		EM_DB::log( 'create', 'unit', $id, 'Created unit', $data['unit_number'] );
		return array( 'id' => $id );
	}

	public static function units_update( WP_REST_Request $r ) {
		global $wpdb;
		$id   = (int) $r['id'];
		$data = array(
			'property_id'  => (int) $r->get_param( 'property_id' ),
			'unit_number'  => trim( (string) $r->get_param( 'unit_number' ) ),
			'unit_type'    => (string) $r->get_param( 'unit_type', 'apartment' ),
			'monthly_rent' => (float) $r->get_param( 'monthly_rent', 0 ),
			'status'       => in_array( $r->get_param( 'status' ), array( 'occupied', 'vacant' ), true ) ? $r->get_param( 'status' ) : 'vacant',
		);
		if ( ! $data['property_id'] || ! $data['unit_number'] ) {
			return self::err( 'Property and unit number are required.' );
		}
		$wpdb->update( EM_DB::table( 'units' ), $data, array( 'id' => $id ) );
		EM_DB::log( 'update', 'unit', $id, 'Updated unit', $data['unit_number'] );
		return array( 'ok' => true );
	}

	public static function units_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a unit.' );
		}
		$unit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'units' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $unit ) {
			return self::err( 'Unit not found.', 404 );
		}
		EM_DB::delete_unit_cascade( $id );
		EM_DB::log( 'delete', 'unit', $id, $reason, $unit['unit_number'] );
		return array( 'ok' => true );
	}

	public static function units_vacate( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required for this action.' );
		}
		$unit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'units' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $unit ) {
			return self::err( 'Unit not found.', 404 );
		}

		$vacate_type = self::vacate_type( (string) $r->get_param( 'vacate_type' ) );
		$effective   = (string) ( $r->get_param( 'effective_date' ) ?: current_time( 'Y-m-d' ) );
		$deductions  = (float) $r->get_param( 'deductions', 0 );
		$notes       = (string) ( $r->get_param( 'notes' ) ?? '' );

		/* Enforce the notice policy: ending an unexpired lease without notice
		   requires an explicit, auditable override. */
		$enforce       = '1' === (string) EM_Settings::get( 'require_notice_before_vacate', '1' );
		$override      = ! empty( $r->get_param( 'override' ) );
		$notice_served = ! empty( $r->get_param( 'notice_served' ) );
		if ( 'early_termination' === $vacate_type && $enforce && ! $override && ! $notice_served ) {
			return self::err(
				'This lease has not reached its end date. Confirm that notice was served, or explicitly override to terminate early.',
				409
			);
		}

		$active = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . EM_DB::table( 'leases' ) . " WHERE unit_id = %d AND status = 'active'",
			$id
		), ARRAY_A );
		$active = (array) $active;

		$settlements = array();
		foreach ( $active as $lease ) {
			$s = self::compute_settlement( $lease, $vacate_type, $deductions );

			$wpdb->update( EM_DB::table( 'leases' ), array(
				'status'                => 'terminated',
				'vacate_type'           => $vacate_type,
				'vacated_at'            => $effective,
				'settlement_fee'        => $s['fee'],
				'settlement_deductions' => $s['deductions'],
				'settlement_refund'     => $s['refund'],
				'settlement_notes'      => $notes,
			), array( 'id' => $lease['id'] ) );

			EM_DB::log(
				'terminate',
				'lease',
				(int) $lease['id'],
				$reason,
				sprintf(
					'unit %s · %s · deposit %s, fee %s, deductions %s, %s %s',
					$unit['unit_number'],
					$vacate_type,
					number_format( $s['deposit'], 2 ),
					number_format( $s['fee'], 2 ),
					number_format( $s['deductions'], 2 ),
					$s['refund'] >= 0 ? 'refund' : 'balance due',
					number_format( abs( $s['refund'] ), 2 )
				)
			);

			$settlements[] = array_merge( array( 'lease_id' => (int) $lease['id'] ), $s );
		}

		$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'vacant' ), array( 'id' => $id ) );
		EM_DB::log( 'vacate', 'unit', $id, $reason, $unit['unit_number'] . ( $active ? ' · ' . count( $active ) . ' lease(s) ended' : '' ) );

		return array(
			'leases_ended' => count( $active ),
			'unit_number'  => $unit['unit_number'],
			'vacate_type'  => $vacate_type,
			'settlements'  => $settlements,
		);
	}

	/* ---------------- Tenants ---------------- */

	public static function tenants_list() {
		global $wpdb;
		return $wpdb->get_results( "
			SELECT t.*,
				(SELECT COUNT(*) FROM " . EM_DB::table( 'leases' ) . " l WHERE l.tenant_id = t.id AND l.status = 'active') AS active_leases,
				(SELECT COUNT(*) FROM " . EM_DB::table( 'leases' ) . " l WHERE l.tenant_id = t.id) AS total_leases
			FROM " . EM_DB::table( 'tenants' ) . " t ORDER BY t.name", ARRAY_A );
	}

	private static function tenant_fields( WP_REST_Request $r ) {
		return array(
			'name'  => trim( (string) $r->get_param( 'name' ) ),
			'phone' => EM_Helpers::clean_phone( (string) $r->get_param( 'phone' ) ),
			'email' => EM_Helpers::clean_email( (string) $r->get_param( 'email' ) ),
		);
	}

	public static function tenants_create( WP_REST_Request $r ) {
		global $wpdb;
		$data = self::tenant_fields( $r );
		if ( ! $data['name'] ) {
			return self::err( 'Tenant name is required.' );
		}
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( EM_DB::table( 'tenants' ), $data );
		$id = (int) $wpdb->insert_id;
		EM_DB::log( 'create', 'tenant', $id, 'Created tenant', $data['name'] );
		return array( 'id' => $id );
	}

	public static function tenants_update( WP_REST_Request $r ) {
		global $wpdb;
		$id   = (int) $r['id'];
		$data = self::tenant_fields( $r );
		if ( ! $data['name'] ) {
			return self::err( 'Tenant name is required.' );
		}
		$wpdb->update( EM_DB::table( 'tenants' ), $data, array( 'id' => $id ) );
		EM_DB::log( 'update', 'tenant', $id, 'Updated tenant', $data['name'] );
		return array( 'ok' => true );
	}

	public static function tenants_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a tenant.' );
		}
		$tenant = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'tenants' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $tenant ) {
			return self::err( 'Tenant not found.', 404 );
		}
		$linked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . EM_DB::table( 'leases' ) . ' WHERE tenant_id = %d', $id ) );
		if ( $linked > 0 ) {
			return self::err( 'Cannot delete: tenant has linked leases and payment history. End or delete their leases first.', 409 );
		}
		$wpdb->delete( EM_DB::table( 'tenants' ), array( 'id' => $id ) );
		EM_DB::log( 'delete', 'tenant', $id, $reason, $tenant['name'] );
		return array( 'ok' => true );
	}

	/* ---------------- Onboarding ---------------- */

	public static function onboarding( WP_REST_Request $r ) {
		global $wpdb;
		$json   = self::body( $r );
		$tenant = isset( $json['tenant'] ) && is_array( $json['tenant'] ) ? $json['tenant'] : array();

		$tenant_name = trim( (string) ( $tenant['name'] ?? '' ) );
		$phone       = EM_Helpers::clean_phone( (string) ( $tenant['phone'] ?? '' ) );
		$email       = EM_Helpers::clean_email( (string) ( $tenant['email'] ?? '' ) );
		$unit_id     = (int) ( $json['unit_id'] ?? 0 );
		$property_id = (int) ( $json['property_id'] ?? 0 );
		$unit_number = trim( (string) ( $json['unit_number'] ?? '' ) );
		$unit_type   = (string) ( $json['unit_type'] ?? 'apartment' );
		$start_date  = (string) ( $json['start_date'] ?? '' );
		$months      = max( 1, (int) ( $json['duration_months'] ?? 12 ) );
		$renew       = max( 1, (int) ( $json['renewal_months'] ?? 12 ) );
		$rent        = max( 0, (float) ( $json['monthly_rent'] ?? 0 ) );

		if ( ! $tenant_name ) {
			return self::err( 'Tenant name is required.' );
		}
		if ( ! $start_date ) {
			return self::err( 'Date of joining is required.' );
		}

		$unit = null;
		if ( $unit_id ) {
			$unit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'units' ) . ' WHERE id = %d', $unit_id ), ARRAY_A );
		} elseif ( $property_id && $unit_number ) {
			$unit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'units' ) . ' WHERE property_id = %d AND unit_number = %s', $property_id, $unit_number ), ARRAY_A );
			if ( ! $unit ) {
				$wpdb->insert( EM_DB::table( 'units' ), array(
					'property_id'  => $property_id,
					'unit_number'  => $unit_number,
					'unit_type'    => $unit_type,
					'monthly_rent' => $rent,
					'status'       => 'vacant',
					'created_at'   => current_time( 'mysql' ),
				) );
				$unit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'units' ) . ' WHERE id = %d', (int) $wpdb->insert_id ), ARRAY_A );
			}
		}
		if ( ! $unit ) {
			return self::err( 'Select a unit or provide property + unit number.' );
		}
		if ( EM_DB::unit_has_active_lease( $unit['id'] ) ) {
			return self::err( 'Unit ' . $unit['unit_number'] . ' already has an active lease.' );
		}

		$wpdb->insert( EM_DB::table( 'tenants' ), array(
			'name'       => $tenant_name,
			'phone'      => $phone,
			'email'      => $email,
			'created_at' => current_time( 'mysql' ),
		) );
		$tenant_id = (int) $wpdb->insert_id;
		$start     = strtotime( $start_date );
		$end       = EM_Helpers::add_months( $start, $months );

		$terms = self::lease_terms_from_request( $r, $rent );

		$wpdb->insert( EM_DB::table( 'leases' ), array(
			'unit_id'               => $unit['id'],
			'tenant_id'             => $tenant_id,
			'start_date'            => date( 'Y-m-d', $start ),
			'end_date'              => date( 'Y-m-d', $end ),
			'monthly_rent'          => $rent,
			'renewal_months'        => $renew,
			'deposit_amount'        => $terms['deposit'],
			'notice_period_days'    => $terms['notice_days'],
			'early_termination_fee' => $terms['fee'],
			'status'                => 'active',
			'notice_sent'           => 0,
			'created_at'            => current_time( 'mysql' ),
		) );
		$lease_id = (int) $wpdb->insert_id;

		$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'occupied', 'monthly_rent' => $rent ), array( 'id' => $unit['id'] ) );

		EM_DB::log( 'create', 'lease', $lease_id, 'Onboarded tenant', $tenant_name . ' → ' . $unit['unit_number'] );

		return array(
			'tenant_id'     => $tenant_id,
			'tenant_name'   => $tenant_name,
			'unit_id'       => (int) $unit['id'],
			'unit_number'   => $unit['unit_number'],
			'start_date'    => date( 'Y-m-d', $start ),
			'end_date'      => date( 'Y-m-d', $end ),
			'monthly_rent'  => $rent,
			'renewal_months'=> $renew,
		);
	}

	/* ---------------- Leases ---------------- */

	public static function leases_list() {
		global $wpdb;
		$rows = $wpdb->get_results( "
			SELECT l.*, u.unit_number, pr.name AS property_name, t.name AS tenant_name, t.phone
			FROM " . EM_DB::table( 'leases' ) . " l
			JOIN " . EM_DB::table( 'units' ) . " u ON u.id = l.unit_id
			JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
			WHERE l.status = 'active'
			ORDER BY l.end_date ASC", ARRAY_A );
		/* Cast before iterating by reference — see dashboard_stats(). */
		$rows = (array) $rows;
		foreach ( $rows as &$l ) {
			$l['days_left']     = EM_Helpers::days_left( $l['end_date'] );
			$l['new_rent_10pct'] = round( (float) $l['monthly_rent'] * 1.1, 2 );
		}
		unset( $l );
		return $rows;
	}

	/**
	 * Every lease on record, across all years — the estate's lease history.
	 * Filters: year, property_id, status.
	 */
	public static function leases_history( WP_REST_Request $r ) {
		global $wpdb;
		$year        = (int) $r->get_param( 'year', 0 );
		$property_id = (int) $r->get_param( 'property_id', 0 );
		$status      = trim( (string) $r->get_param( 'status', '' ) );

		$sql    = "SELECT l.*, u.unit_number, u.property_id, pr.name AS property_name, t.name AS tenant_name, t.phone
			 FROM " . EM_DB::table( 'leases' ) . " l
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = l.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
			 WHERE 1=1";
		$params = array();

		if ( $year ) {
			$sql      .= ' AND (YEAR(l.start_date) = %d OR YEAR(l.end_date) = %d)';
			$params[] = $year;
			$params[] = $year;
		}
		if ( $property_id ) {
			$sql      .= ' AND u.property_id = %d';
			$params[] = $property_id;
		}
		if ( '' !== $status ) {
			$sql      .= ' AND l.status = %s';
			$params[] = $status;
		}
		$sql .= ' ORDER BY u.property_id, u.unit_number, l.start_date DESC';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		$today = current_time( 'Y-m-d' );
		$rows  = (array) $rows;
		foreach ( $rows as &$l ) {
			/* A lease past its end date reads as expired even though its
			   stored status is still 'active' until someone acts on it. */
			$l['effective_status'] = ( 'active' === $l['status'] && $l['end_date'] < $today )
				? 'expired'
				: $l['status'];
			$l['days_left'] = EM_Helpers::days_left( $l['end_date'] );
		}
		unset( $l );
		return $rows;
	}

	public static function leases_create( WP_REST_Request $r ) {
		global $wpdb;
		$unit_id   = (int) $r->get_param( 'unit_id' );
		$tenant_id = (int) $r->get_param( 'tenant_id' );
		$start     = (string) $r->get_param( 'start_date' );
		$end       = (string) $r->get_param( 'end_date' );
		$rent      = (float) $r->get_param( 'monthly_rent' );
		$renew     = max( 1, (int) ( $r->get_param( 'renewal_months', 12 ) ) );
		if ( ! $unit_id || ! $tenant_id || ! $start || ! $end || $rent <= 0 ) {
			return self::err( 'Unit, tenant, start date, end date and rent are required.' );
		}
		if ( EM_DB::unit_has_active_lease( $unit_id ) ) {
			return self::err( 'Unit already has an active lease.' );
		}

		/* Deposit, notice and early-termination terms default from the estate
		   policy but can be overridden per lease. */
		$terms = self::lease_terms_from_request( $r, $rent );

		$wpdb->insert( EM_DB::table( 'leases' ), array(
			'unit_id'               => $unit_id,
			'tenant_id'             => $tenant_id,
			'start_date'            => $start,
			'end_date'              => $end,
			'monthly_rent'          => $rent,
			'renewal_months'        => $renew,
			'deposit_amount'        => $terms['deposit'],
			'notice_period_days'    => $terms['notice_days'],
			'early_termination_fee' => $terms['fee'],
			'status'                => 'active',
			'notice_sent'           => 0,
			'created_at'            => current_time( 'mysql' ),
		) );
		$id = (int) $wpdb->insert_id;
		$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'occupied', 'monthly_rent' => $rent ), array( 'id' => $unit_id ) );
		EM_DB::log( 'create', 'lease', $id, 'Created lease', 'unit ' . $unit_id );
		return array( 'id' => $id );
	}

	public static function leases_update( WP_REST_Request $r ) {
		global $wpdb;
		$id   = (int) $r['id'];
		$data = array(
			'start_date'     => (string) $r->get_param( 'start_date' ),
			'end_date'       => (string) $r->get_param( 'end_date' ),
			'monthly_rent'   => (float) $r->get_param( 'monthly_rent' ),
			'renewal_months' => max( 1, (int) $r->get_param( 'renewal_months', 12 ) ),
		);
		if ( ! $data['start_date'] || ! $data['end_date'] || $data['monthly_rent'] <= 0 ) {
			return self::err( 'Start date, end date and a positive rent are required.' );
		}
		$lease = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'leases' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $lease ) {
			return self::err( 'Lease not found.', 404 );
		}

		/* Exit terms are optional on edit — omit them to keep what's there. */
		if ( null !== $r->get_param( 'deposit_amount' ) ) {
			$data['deposit_amount'] = max( 0, (float) $r->get_param( 'deposit_amount' ) );
		}
		if ( null !== $r->get_param( 'notice_period_days' ) ) {
			$data['notice_period_days'] = max( 0, (int) $r->get_param( 'notice_period_days' ) );
		}
		if ( null !== $r->get_param( 'early_termination_fee' ) ) {
			$data['early_termination_fee'] = max( 0, (float) $r->get_param( 'early_termination_fee' ) );
		}
		$wpdb->update( EM_DB::table( 'leases' ), $data, array( 'id' => $id ) );
		$wpdb->update( EM_DB::table( 'units' ), array( 'monthly_rent' => $data['monthly_rent'] ), array( 'id' => $lease['unit_id'] ) );
		EM_DB::log( 'update', 'lease', $id, 'Updated lease', 'unit ' . $lease['unit_id'] );
		return array( 'ok' => true );
	}

	public static function leases_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a lease.' );
		}
		$lease = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'leases' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $lease ) {
			return self::err( 'Lease not found.', 404 );
		}
		$wpdb->delete( EM_DB::table( 'leases' ), array( 'id' => $id ) );
		$other = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . EM_DB::table( 'leases' ) . " WHERE unit_id = %d AND status = 'active'", $lease['unit_id'] ) );
		if ( 0 === $other ) {
			$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'vacant' ), array( 'id' => $lease['unit_id'] ) );
		}
		EM_DB::log( 'delete', 'lease', $id, $reason, 'unit ' . $lease['unit_id'] );
		return array( 'ok' => true );
	}

	public static function leases_renew( WP_REST_Request $r ) {
		global $wpdb;
		$id    = (int) $r['id'];
		$lease = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'leases' ) . " WHERE id = %d AND status = 'active'", $id ), ARRAY_A );
		if ( ! $lease ) {
			return self::err( 'Active lease not found.', 404 );
		}
		$renew_months = max( 1, (int) ( $lease['renewal_months'] ? $lease['renewal_months'] : 12 ) );
		$start        = strtotime( $lease['end_date'] . ' +1 day' );
		$end          = EM_Helpers::add_months( $start, $renew_months );
		$new_rent     = round( (float) $lease['monthly_rent'] * 1.1, 2 );

		$wpdb->insert( EM_DB::table( 'leases' ), array(
			'unit_id'               => $lease['unit_id'],
			'tenant_id'             => $lease['tenant_id'],
			'start_date'            => date( 'Y-m-d', $start ),
			'end_date'              => date( 'Y-m-d', $end ),
			'monthly_rent'          => $new_rent,
			'renewal_months'        => $renew_months,
			/* Carry the exit terms forward, or a renewal silently drops the
			   tenant's deposit and notice period. */
			'deposit_amount'        => (float) ( $lease['deposit_amount'] ?? 0 ),
			'notice_period_days'    => (int) ( $lease['notice_period_days'] ?? 30 ),
			'early_termination_fee' => (float) ( $lease['early_termination_fee'] ?? 0 ),
			'status'                => 'active',
			'notice_sent'           => 0,
			'created_at'            => current_time( 'mysql' ),
		) );
		$new_id = (int) $wpdb->insert_id;

		$wpdb->update( EM_DB::table( 'leases' ), array( 'status' => 'renewed' ), array( 'id' => $id ) );
		$wpdb->update( EM_DB::table( 'units' ), array( 'monthly_rent' => $new_rent, 'status' => 'occupied' ), array( 'id' => $lease['unit_id'] ) );

		EM_DB::log( 'renew', 'lease', $id, 'Renewed lease', '→ ' . EM_Helpers::money( $new_rent ) . '/mo for ' . $renew_months . ' months' );

		return array(
			'new_lease_id' => $new_id,
			'new_rent'     => $new_rent,
			'start_date'   => date( 'Y-m-d', $start ),
			'end_date'     => date( 'Y-m-d', $end ),
		);
	}

	public static function leases_terminate( WP_REST_Request $r ) {
		global $wpdb;
		$id    = (int) $r['id'];
		$lease = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'leases' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $lease || 'active' !== $lease['status'] ) {
			return self::err( 'Active lease not found.', 404 );
		}
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? 'Terminated lease' ) );

		$vacate_type = self::vacate_type( (string) $r->get_param( 'vacate_type' ) );
		$effective   = (string) ( $r->get_param( 'effective_date' ) ?: current_time( 'Y-m-d' ) );
		$deductions  = (float) $r->get_param( 'deductions', 0 );
		$notes       = (string) ( $r->get_param( 'notes' ) ?? '' );

		$enforce = '1' === (string) EM_Settings::get( 'require_notice_before_vacate', '1' );
		if ( 'early_termination' === $vacate_type && $enforce
			&& empty( $r->get_param( 'override' ) ) && empty( $r->get_param( 'notice_served' ) ) ) {
			return self::err(
				'This lease has not reached its end date. Confirm that notice was served, or explicitly override to terminate early.',
				409
			);
		}

		$s = self::compute_settlement( $lease, $vacate_type, $deductions );

		$wpdb->update( EM_DB::table( 'leases' ), array(
			'status'                => 'terminated',
			'vacate_type'           => $vacate_type,
			'vacated_at'            => $effective,
			'settlement_fee'        => $s['fee'],
			'settlement_deductions' => $s['deductions'],
			'settlement_refund'     => $s['refund'],
			'settlement_notes'      => $notes,
		), array( 'id' => $id ) );

		$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'vacant' ), array( 'id' => $lease['unit_id'] ) );

		EM_DB::log(
			'terminate',
			'lease',
			$id,
			$reason,
			sprintf(
				'unit %d · %s · deposit %s, fee %s, deductions %s, %s %s',
				$lease['unit_id'],
				$vacate_type,
				number_format( $s['deposit'], 2 ),
				number_format( $s['fee'], 2 ),
				number_format( $s['deductions'], 2 ),
				$s['refund'] >= 0 ? 'refund' : 'balance due',
				number_format( abs( $s['refund'] ), 2 )
			)
		);

		return array( 'ok' => true, 'settlement' => $s );
	}

	public static function leases_notice( WP_REST_Request $r ) {
		global $wpdb;
		$id    = (int) $r['id'];
		$lease = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'leases' ) . " WHERE id = %d AND status = 'active'", $id ), ARRAY_A );
		if ( ! $lease ) {
			return self::err( 'Active lease not found.', 404 );
		}
		$wpdb->update( EM_DB::table( 'leases' ), array( 'notice_sent' => 1, 'notice_sent_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		EM_DB::log( 'notify', 'lease', $id, 'Sent renewal notice', 'unit ' . $lease['unit_id'] );
		return array( 'ok' => true );
	}

	/* ---------------- Payments ---------------- */

	public static function payments_list( WP_REST_Request $r ) {
		global $wpdb;
		$limit = (int) $r->get_param( 'limit', 500 );
		$limit = min( 1000, max( 1, $limit ) );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, u.unit_number, pr.name AS property_name, t.name AS tenant_name
			 FROM " . EM_DB::table( 'payments' ) . " p
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = p.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 LEFT JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = p.tenant_id
			 ORDER BY p.payment_date DESC, p.id DESC LIMIT %d",
			$limit
		), ARRAY_A );
	}

	private static function payment_fields( WP_REST_Request $r, $action = 'create' ) {
		global $wpdb;
		$unit_id     = (int) $r->get_param( 'unit_id', 0 );
		$tenant_id   = (int) $r->get_param( 'tenant_id', 0 );
		$amount      = (float) $r->get_param( 'amount', 0 );
		$payment_date = (string) $r->get_param( 'payment_date' );
		$period      = (string) $r->get_param( 'period' );
		$methods     = EM_Settings::payment_methods();
		$method      = in_array( $r->get_param( 'method' ), array_keys( $methods ), true ) ? $r->get_param( 'method' ) : key( $methods );
		$notes       = trim( (string) $r->get_param( 'notes' ) );

		if ( ! $unit_id || $amount <= 0 || ! $payment_date ) {
			return self::err( 'Unit, amount and payment date are required.' );
		}
		$active = $wpdb->get_row( $wpdb->prepare(
			"SELECT l.id AS lease_id, l.tenant_id FROM " . EM_DB::table( 'leases' ) . " l WHERE l.unit_id = %d AND l.status = 'active' LIMIT 1",
			$unit_id
		), ARRAY_A );
		$lease_id = $active ? (int) $active['lease_id'] : null;
		if ( ! $tenant_id && $active ) {
			$tenant_id = (int) $active['tenant_id'];
		}
		$period = $period ? $period : substr( $payment_date, 0, 7 );

		$fields = array(
			'lease_id'     => $lease_id,
			'unit_id'      => $unit_id,
			'tenant_id'    => $tenant_id ? $tenant_id : null,
			'amount'       => $amount,
			'payment_date' => $payment_date,
			'period'       => $period,
			'method'       => $method,
			'notes'        => $notes,
		);
		if ( 'update' === $action ) {
			unset( $fields['lease_id'], $fields['unit_id'], $fields['tenant_id'] );
		}
		return $fields;
	}

	public static function payments_create( WP_REST_Request $r ) {
		global $wpdb;
		$fields = self::payment_fields( $r, 'create' );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$fields['created_at'] = current_time( 'mysql' );
		$wpdb->insert( EM_DB::table( 'payments' ), $fields );
		$id = (int) $wpdb->insert_id;
		EM_DB::log( 'record_payment', 'payment', $id, 'Recorded rent payment', EM_Helpers::money( $fields['amount'] ) . ' · unit ' . $fields['unit_id'] . ' · ' . $fields['payment_date'] );
		return array( 'id' => $id );
	}

	public static function payments_update( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$fields = self::payment_fields( $r, 'update' );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$wpdb->update( EM_DB::table( 'payments' ), $fields, array( 'id' => $id ) );
		EM_DB::log( 'update_payment', 'payment', $id, 'Updated rent payment', EM_Helpers::money( $fields['amount'] ) . ' · ' . $fields['payment_date'] );
		return array( 'ok' => true );
	}

	public static function payments_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a payment.' );
		}
		$pay = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'payments' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $pay ) {
			return self::err( 'Payment not found.', 404 );
		}
		$wpdb->delete( EM_DB::table( 'payments' ), array( 'id' => $id ) );
		EM_DB::log( 'delete_payment', 'payment', $id, $reason, EM_Helpers::money( $pay['amount'] ) . ' · ' . $pay['payment_date'] );
		return array( 'ok' => true );
	}

	/* ---------------- Utilities ---------------- */

	public static function utility_rates_get() {
		return EM_Settings::utility_rates();
	}

	public static function utility_rates_put( WP_REST_Request $r ) {
		$water = (float) $r->get_param( 'water', 0 );
		$elec  = (float) $r->get_param( 'electricity', 0 );
		EM_Settings::set( 'water', $water );
		EM_Settings::set( 'electricity', $elec );
		EM_DB::log( 'update_rates', 'settings', null, 'Updated utility rates', 'water ' . $water . ' · electricity ' . $elec );
		return array( 'ok' => true );
	}

	public static function readings_list() {
		global $wpdb;
		return $wpdb->get_results( "
			SELECT mr.*, u.unit_number, pr.name AS property_name
			FROM " . EM_DB::table( 'meter_readings' ) . " mr
			JOIN " . EM_DB::table( 'units' ) . " u ON u.id = mr.unit_id
			JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			ORDER BY mr.reading_date DESC, mr.id DESC", ARRAY_A );
	}

	public static function readings_create( WP_REST_Request $r ) {
		global $wpdb;
		$unit_id = (int) $r->get_param( 'unit_id' );
		$utility = (string) $r->get_param( 'utility', 'water' );
		$rdate   = (string) $r->get_param( 'reading_date' );
		$current = (float) $r->get_param( 'current_reading' );
		$rate_in = $r->get_param( 'rate_per_unit' );
		$notes   = trim( (string) $r->get_param( 'notes' ) );

		if ( ! $unit_id || ! in_array( $utility, array( 'water', 'electricity' ), true ) || ! $rdate || $current < 0 ) {
			return self::err( 'Unit, utility, reading date and a non-negative reading are required.' );
		}
		$prev = $wpdb->get_var( $wpdb->prepare(
			'SELECT current_reading FROM ' . EM_DB::table( 'meter_readings' ) . " WHERE unit_id = %d AND utility = %s ORDER BY reading_date DESC, id DESC LIMIT 1",
			$unit_id, $utility
		) );
		$previous = $prev === null ? 0 : (float) $prev;
		$rates    = $rate_in !== '' && $rate_in !== null ? (float) $rate_in : (float) EM_Settings::get( $utility, 0 );
		$consump  = round( $current - $previous, 2 );
		if ( $consump < 0 ) {
			return self::err( 'Current reading is lower than the previous reading (' . number_format( $previous, 2 ) . ').' );
		}
		$amount = round( $consump * $rates, 2 );
		$wpdb->insert( EM_DB::table( 'meter_readings' ), array(
			'unit_id'          => $unit_id,
			'utility'          => $utility,
			'reading_date'     => $rdate,
			'current_reading'  => $current,
			'previous_reading' => $previous,
			'consumption'      => $consump,
			'rate_per_unit'    => $rates,
			'amount'           => $amount,
			'status'           => 'pending',
			'notes'            => $notes,
			'created_at'       => current_time( 'mysql' ),
		) );
		$rid = (int) $wpdb->insert_id;
		EM_DB::log( 'record_reading', 'reading', $rid, 'Recorded meter reading', $utility . ' · unit ' . $unit_id . ' · used ' . $consump );
		return array( 'id' => $rid, 'consumption' => $consump, 'amount' => $amount );
	}

	public static function readings_update( WP_REST_Request $r ) {
		global $wpdb;
		$id      = (int) $r['id'];
		$reading = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'meter_readings' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $reading ) {
			return self::err( 'Reading not found.', 404 );
		}
		$rate   = $r->get_param( 'rate_per_unit' ) !== '' && $r->get_param( 'rate_per_unit' ) !== null
			? (float) $r->get_param( 'rate_per_unit' ) : (float) $reading['rate_per_unit'];
		$status = in_array( $r->get_param( 'status' ), array( 'pending', 'paid' ), true ) ? $r->get_param( 'status' ) : $reading['status'];
		$notes  = trim( (string) $r->get_param( 'notes', $reading['notes'] ) );
		$amount = round( (float) $reading['consumption'] * $rate, 2 );
		$wpdb->update( EM_DB::table( 'meter_readings' ), array( 'rate_per_unit' => $rate, 'status' => $status, 'notes' => $notes, 'amount' => $amount ), array( 'id' => $id ) );
		EM_DB::log( $status !== $reading['status'] ? 'mark_' . $status : 'update_reading', 'reading', $id, 'Updated meter reading', $reading['utility'] . ' · unit ' . $reading['unit_id'] );
		return array( 'ok' => true );
	}

	public static function readings_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a reading.' );
		}
		$reading = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_DB::table( 'meter_readings' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $reading ) {
			return self::err( 'Reading not found.', 404 );
		}
		$wpdb->delete( EM_DB::table( 'meter_readings' ), array( 'id' => $id ) );
		EM_DB::log( 'delete_reading', 'reading', $id, $reason, $reading['utility'] . ' · unit ' . $reading['unit_id'] );
		return array( 'ok' => true );
	}

	/* ---------------- Notifications ---------------- */

	public static function notifications_list( WP_REST_Request $r ) {
		global $wpdb;
		$limit = (int) $r->get_param( 'limit', 300 );
		$limit = min( 1000, max( 1, $limit ) );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT n.*, t.name AS tenant_name, u.unit_number
			 FROM " . EM_DB::table( 'notifications' ) . " n
			 LEFT JOIN " . EM_DB::table( 'leases' ) . " l ON l.id = n.lease_id
			 LEFT JOIN " . EM_DB::table( 'units' ) . " u ON u.id = l.unit_id
			 LEFT JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = COALESCE(n.tenant_id, l.tenant_id)
			 ORDER BY n.id DESC LIMIT %d",
			$limit
		), ARRAY_A );
	}

	public static function notifications_stats() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT status, channel, COUNT(*) AS c FROM " . EM_DB::table( 'notifications' ) . " GROUP BY status, channel",
			ARRAY_A
		);
	}

	public static function reminders_run() {
		$r = EM_Notify::run_renewal_reminders();
		EM_DB::log( 'reminders', 'settings', null, 'Ran automated renewal reminders', 'checked ' . $r['checked'] . ', sent ' . count( $r['sent'] ) );
		return $r;
	}

	public static function integrations_test( WP_REST_Request $r ) {
		$channel   = $r->get_param( 'channel' ) === 'email' ? 'email' : 'sms';
		$recipient = trim( (string) $r->get_param( 'recipient' ) );
		if ( ! $recipient ) {
			return self::err( 'A recipient is required.' );
		}
		return EM_Notify::send_test_message( $channel, $recipient );
	}

	/* ---------------- Activity ---------------- */

	/**
	 * Read the audit trail, with optional filtering.
	 *
	 * The audit table is append-only: every write happens through EM_DB::log()
	 * at the point of the change, and nothing in the API can edit or remove a
	 * row. Filters exist so a reviewer can narrow a long log without exporting.
	 */
	public static function activity( WP_REST_Request $r ) {
		global $wpdb;

		$limit  = min( 1000, max( 1, (int) $r->get_param( 'limit', 400 ) ) );
		$table  = EM_DB::table( 'audit_log' );
		$where  = array( '1=1' );
		$params = array();

		$user_param = trim( (string) $r->get_param( 'user', '' ) );
		if ( $user_param ) {
			$where[]  = '(username = %s OR user_id = %s)';
			$params[] = $user_param;
			$params[] = is_numeric( $user_param ) ? (int) $user_param : 0;
		}

		$entity = trim( (string) $r->get_param( 'entity', '' ) );
		if ( $entity ) {
			$where[]  = 'entity_type = %s';
			$params[] = $entity;
		}

		$action = trim( (string) $r->get_param( 'action', '' ) );
		if ( $action ) {
			$where[]  = 'action = %s';
			$params[] = $action;
		}

		$search = trim( (string) $r->get_param( 'search', '' ) );
		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(reason LIKE %s OR details LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$params[] = $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/* ---------------- Settings ---------------- */

	public static function settings_get() {
		return array(
			'renewal_reminder_months' => (int) EM_Settings::get( 'renewal_reminder_months', 2 ),
			'phone_country_code'      => (string) EM_Settings::get( 'phone_country_code', '254' ),
			'rates'                   => EM_Settings::utility_rates(),
			'tenancy'                 => array(
				'default_notice_period_days'           => (int) EM_Settings::get( 'default_notice_period_days', 30 ),
				'default_deposit_months'               => (float) EM_Settings::get( 'default_deposit_months', 1 ),
				'default_early_termination_fee_months' => (float) EM_Settings::get( 'default_early_termination_fee_months', 0 ),
				'require_notice_before_vacate'         => '1' === (string) EM_Settings::get( 'require_notice_before_vacate', '1' ),
			),
			'sms'                     => EM_Settings::masked( 'sms' ),
			'email'                   => EM_Settings::masked( 'email' ),
		);
	}

	public static function settings_put( WP_REST_Request $r ) {
		$json = self::body( $r );

		EM_Settings::set( 'renewal_reminder_months', max( 1, (int) ( $json['renewal_reminder_months'] ?? 2 ) ) );

		if ( isset( $json['phone_country_code'] ) ) {
			$cc = preg_replace( '/\D/', '', (string) $json['phone_country_code'] );
			EM_Settings::set( 'phone_country_code', '' !== $cc ? $cc : '254' );
		}

		if ( isset( $json['tenancy'] ) && is_array( $json['tenancy'] ) ) {
			$t = $json['tenancy'];
			EM_Settings::set( 'default_notice_period_days', max( 0, (int) ( $t['default_notice_period_days'] ?? 30 ) ) );
			EM_Settings::set( 'default_deposit_months', max( 0, (float) ( $t['default_deposit_months'] ?? 1 ) ) );
			EM_Settings::set( 'default_early_termination_fee_months', max( 0, (float) ( $t['default_early_termination_fee_months'] ?? 0 ) ) );
			EM_Settings::set( 'require_notice_before_vacate', ! empty( $t['require_notice_before_vacate'] ) ? '1' : '0' );
		}

		if ( isset( $json['rates'] ) && is_array( $json['rates'] ) ) {
			EM_Settings::set( 'water', max( 0, (float) ( $json['rates']['water'] ?? 0 ) ) );
			EM_Settings::set( 'electricity', max( 0, (float) ( $json['rates']['electricity'] ?? 0 ) ) );
		}

		if ( isset( $json['sms'] ) && is_array( $json['sms'] ) ) {
			$sms = $json['sms'];
			$providers = array( 'africas_talking', 'sms_gate', 'textbee', 'generic' );
			$provider  = (string) ( $sms['provider'] ?? 'africas_talking' );

			EM_Settings::set( 'sms_enabled', ! empty( $sms['enabled'] ) ? '1' : '0' );
			EM_Settings::set( 'sms_provider', in_array( $provider, $providers, true ) ? $provider : 'africas_talking' );
			EM_Settings::set( 'sms_username', (string) ( $sms['username'] ?? '' ) );
			EM_Settings::set( 'sms_from', (string) ( $sms['from'] ?? '' ) );
			EM_Settings::set( 'sms_sandbox', ! empty( $sms['sandbox'] ) ? '1' : '0' );
			EM_Settings::set( 'sms_device_id', (string) ( $sms['device_id'] ?? '' ) );
			EM_Settings::set( 'sms_base_url', esc_url_raw( (string) ( $sms['base_url'] ?? '' ) ) );
			EM_Settings::set( 'sms_generic_url', esc_url_raw( (string) ( $sms['generic_url'] ?? '' ) ) );
			EM_Settings::set( 'sms_generic_auth', (string) ( $sms['generic_auth'] ?? '' ) );
			EM_Settings::set( 'sms_generic_body', (string) ( $sms['generic_body'] ?? '' ) );
			if ( ! empty( $sms['api_key'] ) ) {
				EM_Settings::set( 'sms_api_key', (string) $sms['api_key'] );
			}
		}

		if ( isset( $json['email'] ) && is_array( $json['email'] ) ) {
			$em = $json['email'];
			EM_Settings::set( 'email_enabled', ! empty( $em['enabled'] ) ? '1' : '0' );
			EM_Settings::set( 'email_host', (string) ( $em['host'] ?? '' ) );
			EM_Settings::set( 'email_port', (int) ( $em['port'] ?? 587 ) );
			EM_Settings::set( 'email_user', (string) ( $em['user'] ?? '' ) );
			EM_Settings::set( 'email_from', (string) ( $em['from'] ?? '' ) );
			if ( ! empty( $em['pass'] ) ) {
				EM_Settings::set( 'email_pass', (string) $em['pass'] );
			}
			if ( isset( $em['app_url'] ) ) {
				EM_Settings::set( 'em_app_url', esc_url_raw( trim( (string) $em['app_url'] ) ) );
			}
		}

		EM_DB::log( 'settings', 'settings', null, 'Updated settings', 'config saved' );
		return self::settings_get();
	}

	/* ---------------- Users (admin) ---------------- */

	public static function users_list() {
		$out  = array();
		$user = get_users( array( 'orderby' => 'user_registered', 'order' => 'DESC' ) );
		foreach ( $user as $u ) {
			$row = self::user_short( $u );
			$row['active']     = ! get_user_meta( $u->ID, 'em_disabled', true );
			$row['email']      = $u->user_email;
			$out[] = $row;
		}
		return $out;
	}

	private static function apply_role( WP_User $u, $role ) {
		$u->set_role( 'estate_manager' );
		$u->add_cap( EM_CAP, true );
		if ( 'admin' === $role ) {
			$u->add_cap( EM_CAP_ACCESS, true );
			$u->add_cap( EM_CAP_VISITORS, true );
		} else {
			$u->remove_cap( EM_CAP_ACCESS );
			$u->remove_cap( EM_CAP_VISITORS );
		}
	}

	public static function users_create( WP_REST_Request $r ) {
		$username = sanitize_user( (string) $r->get_param( 'username' ) );
		$password = (string) $r->get_param( 'password' );
		$full     = (string) $r->get_param( 'full_name' );
		$role     = 'admin' === $r->get_param( 'role' ) ? 'admin' : 'manager';
		if ( ! $username ) {
			return self::err( 'A username is required.' );
		}
		$weak = self::validate_new_password( $password );
		if ( $weak ) {
			return self::err( $weak );
		}
		if ( username_exists( $username ) ) {
			return self::err( 'That username is already taken.' );
		}
		$phone = self::validated_phone( (string) $r->get_param( 'phone' ) );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$id = wp_insert_user( array(
			'user_login'      => $username,
			'user_pass'       => $password,
			'display_name'    => $full,
			'role'            => 'estate_manager',
		) );
		if ( is_wp_error( $id ) ) {
			return self::err( $id->get_error_message() );
		}
		if ( '' !== $phone ) {
			update_user_meta( $id, 'em_phone', $phone );
		}
		$user = new WP_User( $id );
		self::apply_role( $user, $role );
		EM_DB::log( 'create_user', 'user', $id, 'Created user', $username . ' (' . $role . ')' );
		return self::user_short( $user ) + array( 'active' => true );
	}

	/**
	 * Validate and normalise a phone number for a user account. Returns the
	 * normalised value ('' when blank), or a WP_Error when unusable.
	 */
	private static function validated_phone( $raw, $ignore_user_id = 0 ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$phone = EM_Helpers::normalize_phone( $raw );
		if ( '' === $phone ) {
			return self::err( 'That phone number doesn\'t look valid. Use 0712 345 678 or +254 712 345 678.' );
		}
		$existing = get_users( array(
			'meta_key'   => 'em_phone',
			'meta_value' => $phone,
			'number'     => 1,
		) );
		if ( ! empty( $existing ) && (int) $existing[0]->ID !== (int) $ignore_user_id ) {
			return self::err( 'Another user already uses that phone number.' );
		}
		return $phone;
	}

	public static function users_update( WP_REST_Request $r ) {
		$id     = (int) $r['id'];
		$user   = new WP_User( $id );
		if ( ! $user->exists() ) {
			return self::err( 'User not found.', 404 );
		}
		$data = array( 'ID' => $id );
		if ( $r->get_param( 'full_name' ) !== null ) {
			$data['display_name'] = (string) $r->get_param( 'full_name' );
		}
		if ( $r->get_param( 'password' ) ) {
			$weak = self::validate_new_password( (string) $r->get_param( 'password' ) );
			if ( $weak ) {
				return self::err( $weak );
			}
			$data['user_pass'] = (string) $r->get_param( 'password' );
			/* An admin-set password also invalidates that user's existing
			   Application Passwords, otherwise a reset wouldn't actually
			   lock out a compromised session. */
			$reset_creds = true;
		}
		if ( count( $data ) > 1 ) {
			$res = wp_update_user( $data );
			if ( is_wp_error( $res ) ) {
				return self::err( $res->get_error_message() );
			}
		}
		if ( ! empty( $reset_creds ) && class_exists( 'WP_Application_Passwords' ) ) {
			WP_Application_Passwords::delete_all_application_passwords( $id );
		}
		if ( $r->get_param( 'phone' ) !== null ) {
			$phone = self::validated_phone( (string) $r->get_param( 'phone' ), $id );
			if ( is_wp_error( $phone ) ) {
				return $phone;
			}
			if ( '' === $phone ) {
				delete_user_meta( $id, 'em_phone' );
			} else {
				update_user_meta( $id, 'em_phone', $phone );
			}
		}
		if ( $r->get_param( 'role' ) !== null ) {
			self::apply_role( $user, $r->get_param( 'role' ) );
		}
		if ( $r->get_param( 'active' ) !== null ) {
			$active = (int) $r->get_param( 'active' );
			if ( $active ) {
				delete_user_meta( $id, 'em_disabled' );
			} else {
				update_user_meta( $id, 'em_disabled', 1 );
			}
		}
		EM_DB::log( 'update_user', 'user', $id, 'Updated user', $user->user_login );
		return array( 'ok' => true );
	}

	public static function users_delete( WP_REST_Request $r ) {
		$id     = (int) $r['id'];
		$reason = trim( (string) ( $r->get_param( 'reason' ) ?? '' ) );
		if ( ! $reason ) {
			return self::err( 'A reason is required to delete a user.' );
		}
		$user = new WP_User( $id );
		if ( ! $user->exists() ) {
			return self::err( 'User not found.', 404 );
		}
		if ( $id === get_current_user_id() ) {
			return self::err( 'You cannot delete your own account.', 409 );
		}
		/* wp_delete_user() ships in wp-admin, which a REST request never loads. */
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		wp_delete_user( $id );
		EM_DB::log( 'delete_user', 'user', $id, $reason, $user->user_login );
		return array( 'ok' => true );
	}

	/* ---------------- Visitors (admin) ---------------- */

	public static function visits() {
		global $wpdb;
		$t = EM_DB::table( 'visits' );

		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
		$unique  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ip) FROM {$t}" );
		$last24h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", current_time( 'mysql', true ) ) );

		$by_day = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE_FORMAT(created_at, '%%Y-%%m-%%d') AS day, COUNT(*) AS views, COUNT(DISTINCT ip) AS uniques
			 FROM {$t} WHERE created_at >= %s GROUP BY day ORDER BY day DESC LIMIT 30",
			current_time( 'mysql', true )
		), ARRAY_A );
		$by_day = array_reverse( (array) $by_day );

		$by_path = $wpdb->get_results( "SELECT path, COUNT(*) AS views FROM {$t} GROUP BY path ORDER BY views DESC LIMIT 15", ARRAY_A );

		$recent = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 50", ARRAY_A );
		/* Cast before iterating by reference — see dashboard_stats(). */
		$recent = (array) $recent;
		foreach ( $recent as &$v ) {
			list( $v['device'], $v['browser'] ) = EM_Helpers::classify_ua( (string) ( $v['user_agent'] ?? '' ) );
			unset( $v['user_agent'] );
		}
		unset( $v );

		return array(
			'total'      => $total,
			'unique_ips' => $unique,
			'last24h'    => $last24h,
			'by_day'     => $by_day,
			'by_path'    => $by_path,
			'recent'     => $recent,
		);
	}
}