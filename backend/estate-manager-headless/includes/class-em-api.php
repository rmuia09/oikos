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

		/* ---------- Activity ---------- */
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
		$user = wp_authenticate_application_password( null, $username, $password );
		if ( ! $user instanceof WP_User || is_wp_error( $user ) ) {
			return self::err( 'Invalid username or application password.', 401 );
		}
		if ( get_user_meta( $user->ID, 'em_disabled', true ) ) {
			return self::err( 'This account is disabled.', 403 );
		}
		$token = base64_encode( $username . ':' . $password );
		return array( 'token' => $token, 'user' => self::user_short( $user ) );
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
		if ( ! $cur || strlen( $new ) < 6 ) {
			return self::err( 'New password must be at least 6 characters.' );
		}
		if ( ! wp_check_password( $cur, $user->user_pass, $user->ID ) ) {
			return self::err( 'Current password is incorrect.', 403 );
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
			'expectedMonthly'  => (float) $s['expected_monthly'],
			'collectedMonth'   => (float) $s['collected_month'],
			'monthOutstanding' => (float) $s['month_outstanding'],
			'pendingUtility'   => (float) $s['pending_utility'],
			'recentPayments'   => $s['recent_payments'],
			'expiringSoon'     => $s['expiring_soon'],
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
				  WHERE l.unit_id = u.id AND l.status = 'active' LIMIT 1) AS tenant_id
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
		$active = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . EM_DB::table( 'leases' ) . " WHERE unit_id = %d AND status = 'active'", $id ), ARRAY_A );
		foreach ( (array) $active as $l ) {
			$wpdb->update( EM_DB::table( 'leases' ), array( 'status' => 'terminated' ), array( 'id' => $l['id'] ) );
			EM_DB::log( 'terminate', 'lease', (int) $l['id'], $reason, 'unit ' . $unit['unit_number'] );
		}
		$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'vacant' ), array( 'id' => $id ) );
		EM_DB::log( 'vacate', 'unit', $id, $reason, $unit['unit_number'] . ( $active ? ' · ' . count( $active ) . ' lease(s) ended' : '' ) );
		return array( 'leases_ended' => count( $active ), 'unit_number' => $unit['unit_number'] );
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

		$wpdb->insert( EM_DB::table( 'leases' ), array(
			'unit_id'        => $unit['id'],
			'tenant_id'      => $tenant_id,
			'start_date'     => date( 'Y-m-d', $start ),
			'end_date'       => date( 'Y-m-d', $end ),
			'monthly_rent'   => $rent,
			'renewal_months' => $renew,
			'status'         => 'active',
			'notice_sent'    => 0,
			'created_at'     => current_time( 'mysql' ),
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
		foreach ( (array) $rows as &$l ) {
			$l['days_left']     = EM_Helpers::days_left( $l['end_date'] );
			$l['new_rent_10pct'] = round( (float) $l['monthly_rent'] * 1.1, 2 );
		}
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
		$wpdb->insert( EM_DB::table( 'leases' ), array(
			'unit_id'        => $unit_id,
			'tenant_id'      => $tenant_id,
			'start_date'     => $start,
			'end_date'       => $end,
			'monthly_rent'   => $rent,
			'renewal_months' => $renew,
			'status'         => 'active',
			'notice_sent'    => 0,
			'created_at'     => current_time( 'mysql' ),
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
			'unit_id'        => $lease['unit_id'],
			'tenant_id'      => $lease['tenant_id'],
			'start_date'     => date( 'Y-m-d', $start ),
			'end_date'       => date( 'Y-m-d', $end ),
			'monthly_rent'   => $new_rent,
			'renewal_months' => $renew_months,
			'status'         => 'active',
			'notice_sent'    => 0,
			'created_at'     => current_time( 'mysql' ),
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
		$wpdb->update( EM_DB::table( 'leases' ), array( 'status' => 'terminated' ), array( 'id' => $id ) );
		$wpdb->update( EM_DB::table( 'units' ), array( 'status' => 'vacant' ), array( 'id' => $lease['unit_id'] ) );
		EM_DB::log( 'terminate', 'lease', $id, $reason, 'unit ' . $lease['unit_id'] );
		return array( 'ok' => true );
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

	public static function activity( WP_REST_Request $r ) {
		global $wpdb;
		$limit = (int) $r->get_param( 'limit', 400 );
		$limit = min( 1000, max( 1, $limit ) );
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . EM_DB::table( 'audit_log' ) . ' ORDER BY id DESC LIMIT %d',
			$limit
		), ARRAY_A );
	}

	/* ---------------- Settings ---------------- */

	public static function settings_get() {
		return array(
			'renewal_reminder_months' => (int) EM_Settings::get( 'renewal_reminder_months', 2 ),
			'rates'                   => EM_Settings::utility_rates(),
			'sms'                     => EM_Settings::masked( 'sms' ),
			'email'                   => EM_Settings::masked( 'email' ),
		);
	}

	public static function settings_put( WP_REST_Request $r ) {
		$json = self::body( $r );

		EM_Settings::set( 'renewal_reminder_months', max( 1, (int) ( $json['renewal_reminder_months'] ?? 2 ) ) );

		if ( isset( $json['rates'] ) && is_array( $json['rates'] ) ) {
			EM_Settings::set( 'water', max( 0, (float) ( $json['rates']['water'] ?? 0 ) ) );
			EM_Settings::set( 'electricity', max( 0, (float) ( $json['rates']['electricity'] ?? 0 ) ) );
		}

		if ( isset( $json['sms'] ) && is_array( $json['sms'] ) ) {
			$sms = $json['sms'];
			EM_Settings::set( 'sms_enabled', ! empty( $sms['enabled'] ) ? '1' : '0' );
			EM_Settings::set( 'sms_username', (string) ( $sms['username'] ?? '' ) );
			EM_Settings::set( 'sms_from', (string) ( $sms['from'] ?? '' ) );
			EM_Settings::set( 'sms_sandbox', ! empty( $sms['sandbox'] ) ? '1' : '0' );
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
		if ( ! $username || strlen( $password ) < 6 ) {
			return self::err( 'Username and a password of at least 6 characters are required.' );
		}
		if ( username_exists( $username ) ) {
			return self::err( 'That username is already taken.' );
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
		$user = new WP_User( $id );
		self::apply_role( $user, $role );
		EM_DB::log( 'create_user', 'user', $id, 'Created user', $username . ' (' . $role . ')' );
		return self::user_short( $user ) + array( 'active' => true );
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
			$data['user_pass'] = (string) $r->get_param( 'password' );
		}
		if ( $data ) {
			$res = wp_update_user( $data );
			if ( is_wp_error( $res ) ) {
				return self::err( $res->get_error_message() );
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
		foreach ( (array) $recent as &$v ) {
			list( $v['device'], $v['browser'] ) = EM_Helpers::classify_ua( (string) ( $v['user_agent'] ?? '' ) );
			unset( $v['user_agent'] );
		}

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