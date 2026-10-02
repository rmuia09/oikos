<?php
/**
 * Database schema, seed data and shared query helpers.
 */

defined( 'ABSPATH' ) || exit;

class EM_DB {

	/** Full prefixed table name. */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'em_' . $name;
	}

	/** Register capabilities on the WordPress roles. */
	public static function add_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( EM_CAP, true );
			$admin->add_cap( EM_CAP_ACCESS, true );
			$admin->add_cap( EM_CAP_VISITORS, true );
		}
		if ( ! get_role( 'estate_manager' ) ) {
			add_role(
				'estate_manager',
				'Oikos Manager',
				array(
					'read'            => true,
					EM_CAP           => true,
				)
			);
		} else {
			$role = get_role( 'estate_manager' );
			$role->add_cap( EM_CAP, true );
		}
	}

	public static function init() {
		self::add_caps();
	}

	/**
	 * Create tables + install default settings. Demo data is opt-in.
	 *
	 * A client installing this on a live site must not inherit 48 fake units,
	 * so the sample estate is only created when EM_SEED_DEMO is defined truthy
	 * (or an em_seed_demo filter opts in) — see should_seed_demo().
	 */
	public static function activate() {
		self::create_tables();
		self::install_settings();
		if ( self::should_seed_demo() ) {
			self::seed_demo();
		}
		self::add_caps();
	}

	/**
	 * Whether activation should create the sample estate.
	 *
	 * Add this to wp-config.php to get the demo data:
	 *   define( 'EM_SEED_DEMO', true );
	 */
	public static function should_seed_demo() {
		$on = defined( 'EM_SEED_DEMO' ) ? (bool) EM_SEED_DEMO : false;
		return (bool) apply_filters( 'em_seed_demo', $on );
	}

	/** True when the estate holds no properties, units, tenants or leases. */
	public static function estate_is_empty() {
		global $wpdb;
		foreach ( array( 'properties', 'units', 'tenants', 'leases' ) as $t ) {
			if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( $t ) ) > 0 ) {
				return false;
			}
		}
		return true;
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'em_daily_reminders' );
		wp_clear_scheduled_hook( 'em_weekly_prune' );
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix . 'em_';

		$sql = array();

		$sql[] = "CREATE TABLE {$p}settings (
  setting_key varchar(100) NOT NULL,
  setting_value longtext NULL,
  PRIMARY KEY  (setting_key)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}properties (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(200) NOT NULL,
  location varchar(200) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY name (name)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}units (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  property_id bigint(20) unsigned NOT NULL,
  unit_number varchar(50) NOT NULL,
  unit_type varchar(50) NOT NULL DEFAULT 'apartment',
  monthly_rent decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'occupied',
  created_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY unit_unique (property_id, unit_number),
  KEY property_id (property_id),
  KEY status (status)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}tenants (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(200) NOT NULL,
  phone varchar(50) NOT NULL DEFAULT '',
  email varchar(200) NOT NULL DEFAULT '',
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY name (name)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}leases (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  unit_id bigint(20) unsigned NOT NULL,
  tenant_id bigint(20) unsigned NOT NULL,
  start_date date NOT NULL,
  end_date date NOT NULL,
  monthly_rent decimal(12,2) NOT NULL DEFAULT 0,
  renewal_months int NOT NULL DEFAULT 12,
  deposit_amount decimal(12,2) NOT NULL DEFAULT 0,
  notice_period_days int NOT NULL DEFAULT 30,
  early_termination_fee decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  notice_sent tinyint(1) NOT NULL DEFAULT 0,
  notice_sent_at datetime NULL,
  vacate_type varchar(30) NULL,
  vacated_at date NULL,
  settlement_fee decimal(12,2) NOT NULL DEFAULT 0,
  settlement_deductions decimal(12,2) NOT NULL DEFAULT 0,
  settlement_refund decimal(12,2) NOT NULL DEFAULT 0,
  settlement_notes text NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY unit_id (unit_id),
  KEY tenant_id (tenant_id),
  KEY status (status),
  KEY end_date (end_date)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  lease_id bigint(20) unsigned NULL,
  unit_id bigint(20) unsigned NOT NULL,
  tenant_id bigint(20) unsigned NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  payment_date date NOT NULL,
  period varchar(7) NOT NULL DEFAULT '',
  method varchar(20) NOT NULL DEFAULT 'cash',
  notes text NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY payment_date (payment_date),
  KEY unit_id (unit_id),
  KEY lease_id (lease_id),
  KEY period (period)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}meter_readings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  unit_id bigint(20) unsigned NOT NULL,
  utility varchar(20) NOT NULL,
  reading_date date NOT NULL,
  current_reading decimal(12,2) NOT NULL DEFAULT 0,
  previous_reading decimal(12,2) NOT NULL DEFAULT 0,
  consumption decimal(12,2) NOT NULL DEFAULT 0,
  rate_per_unit decimal(12,2) NOT NULL DEFAULT 0,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'pending',
  notes text NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY unit_id (unit_id),
  KEY reading_date (reading_date)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}notifications (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  channel varchar(20) NOT NULL,
  kind varchar(20) NOT NULL DEFAULT 'reminder',
  lease_id bigint(20) unsigned NULL,
  tenant_id bigint(20) unsigned NULL,
  recipient varchar(200) NOT NULL DEFAULT '',
  subject varchar(200) NOT NULL DEFAULT '',
  body text NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  provider_message_id varchar(100) NOT NULL DEFAULT '',
  error text NULL,
  sent_at datetime NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}audit_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NULL,
  username varchar(100) NOT NULL DEFAULT '',
  action varchar(50) NOT NULL DEFAULT '',
  entity_type varchar(50) NOT NULL DEFAULT '',
  entity_id bigint(20) unsigned NULL,
  reason text NULL,
  details text NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) $charset_collate;";

		$sql[] = "CREATE TABLE {$p}visits (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NULL,
  path varchar(255) NOT NULL DEFAULT '/',
  ip varchar(50) NOT NULL DEFAULT '',
  user_agent text NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) $charset_collate;";

		// dbDelta() ships in wp-admin/includes/upgrade.php, which is only loaded
		// for admin requests. Activation can also run via WP-CLI or a cron/REST
		// bootstrap, so require it explicitly.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		foreach ( $sql as $q ) {
			dbDelta( $q );
		}
	}

	/** Default settings, seeded identically to the original app. */
	public static function default_settings() {
		return array(
			'water'                 => '80',
			'electricity'           => '30',
			'renewal_reminder_months' => '2',
			'public_access'          => '0',
			'currency'               => 'KSh',
			'payment_methods'        => 'cash,M-Pesa,bank',
			'sms_enabled'           => '0',
			/* africas_talking | sms_gate | textbee | generic */
			'sms_provider'          => 'africas_talking',
			'sms_username'          => '',
			'sms_api_key'           => '',
			'sms_from'              => '',
			'sms_sandbox'           => '1',
			/* Android gateways */
			'sms_device_id'         => '',
			'sms_base_url'          => '',
			/* Generic HTTP gateway */
			'sms_generic_url'       => '',
			'sms_generic_auth'      => '',
			'sms_generic_body'      => '',
			'email_enabled'         => '0',
			'email_host'            => '',
			'email_port'            => '587',
			'email_user'            => '',
			'email_pass'            => '',
			'email_from'            => '',
			/* Public URL of the Gatsby app. Password-reset links are built
			   from this; without it they would point at WordPress instead. */
			'em_app_url'            => '',
			/* Country code assumed for phone numbers typed without one.
			   254 = Kenya. Change this to move to another market. */
			'phone_country_code'    => '254',

			/* --- Tenancy exit policy (configurable, not legal advice) ---
			   These are defaults copied onto each new lease, which can then
			   override them. See README for the Kenyan statutory context. */
			/* Notice a tenant must give before vacating, in days.
			   30 matches the Rent Restriction Act (Cap 296) minimum for
			   controlled residential tenancies; 60 for commercial (Cap 301). */
			'default_notice_period_days'        => '30',
			/* Security deposit, expressed in months of rent. */
			'default_deposit_months'            => '1',
			/* Early-termination charge, in months of rent. 0 = none.
			   Purely contractual: Kenyan law sets notice periods, not
			   penalties, so this must match the signed lease. */
			'default_early_termination_fee_months' => '0',
			/* When on, vacating an unexpired lease requires confirming that
			   notice was served, or an explicit override. */
			'require_notice_before_vacate'      => '1',
		);
	}

	/** Write the default settings rows. Always safe to re-run. */
	public static function install_settings() {
		global $wpdb;
		$set = self::table( 'settings' );
		foreach ( self::default_settings() as $k => $v ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$set} (setting_key, setting_value) VALUES (%s, %s)", $k, $v ) );
		}
	}

	/**
	 * Create the sample estate (Melon Park x32, Block 13 x13, Block 3 x3).
	 *
	 * Only ever runs on an empty estate, so it can never overwrite real data.
	 *
	 * @return string|true An error message, or true on success.
	 */
	public static function seed_demo() {
		global $wpdb;
		$props = self::table( 'properties' );

		if ( ! self::estate_is_empty() ) {
			return 'Demo data can only be added to an empty estate. Delete the existing properties first.';
		}
		if ( get_option( 'em_seeded' ) ) {
			return 'The demo estate has already been created.';
		}

		self::install_settings();

		$now   = current_time( 'mysql' );
		$today = current_time( 'Y-m-d' );

		$properties_data = array(
			array(
				'name'        => 'Melon Park',
				'location'    => 'Eldoret',
				'type'        => 'apartment',
				'baseRent'    => 2200,
				'units'       => array( 'A1','A2','A3','A4','A5','A6','A7','A8','B1','B2','B3','B4','B5','B6','B7','B8','C1','C2','C3','C4','C5','C6','C7','C8','D1','D2','D3','D4','D5','D6','D7','D8' ),
			),
			array(
				'name'        => 'Block 13',
				'location'    => 'Eldoret',
				'type'        => 'bedsitter',
				'baseRent'    => 1500,
				'units'       => array( '13-01','13-02','13-03','13-04','13-05','13-06','13-07','13-08','13-09','13-10','13-11','13-12','13-13' ),
			),
			array(
				'name'        => 'Block 3',
				'location'    => 'Eldoret',
				'type'        => 'bedsitter',
				'baseRent'    => 1500,
				'units'       => array( '3-01','3-02','3-03' ),
			),
		);

		$names = array(
			'John Kamau','Mary Wanjiku','Peter Otieno','Faith Achieng','Brian Mwangi',
			'Grace Njeri','David Mutua','Esther Wambui','Samuel Kiprop','Lucy Chebet',
			'James Omondi','Rose Muthoni','Daniel Maina','Agnes Akinyi','Kevin Njoroge',
			'Diana Wairimu','Eric Kariuki','Catherine Awuor','Brian Kipchoge','Nancy Moraa',
			'George Kibet','Janet Kerubo','Michael Osoro','Sylvia Atieno','Stephen Bundotich',
			'Ruth Chepkemoi','Vincent Musyoka','Caroline Nekesa','Anthony Waweru','Monica Jerop',
			'Joseph Ruto','Purity Cherono','Isaac Mburu','Hellen Kandie','Francis Ochieng',
			'Mercy Cherotich','Simon Nganga','Jane Nasimiyu','Andrew Kimutai','Beatrice Mwende',
			'Patrick Kilonzo','Judith Aoko','Charles Sang','Rebecca Wawira','Lawrence Ouma',
			'Sara Meilut','Dennis Kiplagat','Naomi Chebet','Elijah Barngetuny','Tabitha Cherono',
		);

		$unit_rents = array();
		$unit_id    = 0;

		foreach ( $properties_data as $pd ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$props} (name, location, active, created_at) VALUES (%s, %s, 1, %s)",
				$pd['name'], $pd['location'], $now
			) );
			$prop_id = (int) $wpdb->insert_id;

			foreach ( $pd['units'] as $i => $num ) {
				$i++;
				$rent = $pd['type'] === 'apartment'
					? $pd['baseRent'] + ( ( $i % 5 ) * 200 ) + ( ( $i % 3 ) * 100 )
					: $pd['baseRent'] + ( ( $i % 3 ) * 150 );
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO " . self::table( 'units' ) . " (property_id, unit_number, unit_type, monthly_rent, status, created_at) VALUES (%d, %s, %s, %f, 'occupied', %s)",
					$prop_id, $num, $pd['type'], $rent, $now
				) );
				$unit_id++;
				$unit_rents[] = array( 'unit_id' => $unit_id, 'rent' => $rent );
			}
		}

		$tenants_t = self::table( 'tenants' );
		$leases_t  = self::table( 'leases' );
		$pays_t    = self::table( 'payments' );
		$reads_t   = self::table( 'meter_readings' );

		foreach ( $unit_rents as $i => $ur ) {
			$i        = (int) $i;
			$name     = $names[ $i ] ?? ( 'Tenant ' . ( $i + 1 ) );
			$phone    = '07' . substr( (string) ( 10000000 + $i * 137 + wp_rand( 0, 899 ) ), 0, 8 );
			$email    = strtolower( str_replace( ' ', '.', $name ) ) . '@example.com';

			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$tenants_t} (name, phone, email, created_at) VALUES (%s, %s, %s, %s)",
				$name, $phone, $email, $now
			) );
			$tenant_id = (int) $wpdb->insert_id;

			$due_soon   = ( $i % 7 === 0 );
			$end_months = $due_soon ? ( ( $i % 5 ) % 3 ) : ( 3 + ( $i % 12 ) );
			$end        = strtotime( "+{$end_months} months", strtotime( $today ) );
			$start      = strtotime( '-12 months', $end );
			$rent       = $ur['rent'];
			$notice     = ( $due_soon && $i % 2 === 0 ) ? 1 : 0;
			$renew      = $due_soon ? 3 : 12;
			$deposit    = round( (float) $rent, 2 );   // one month

			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$leases_t} (unit_id, tenant_id, start_date, end_date, monthly_rent, renewal_months, deposit_amount, notice_period_days, early_termination_fee, status, notice_sent, notice_sent_at, created_at) VALUES (%d, %d, %s, %s, %f, %d, %f, %d, %f, 'active', %d, %s, %s)",
				$ur['unit_id'], $tenant_id, date( 'Y-m-d', $start ), date( 'Y-m-d', $end ), $rent, $renew, $deposit, 30, 0.0, $notice,
				$notice ? current_time( 'mysql' ) : null, $now
			) );
			$lease_id = (int) $wpdb->insert_id;

			/* Payments for each month the lease has actually run — never before
			   the start date and never in the future. A payment dated outside
			   the tenancy can't be credited to any month, which previously left
			   "collected" money that no ledger recognised. */
			$months = array();
			$cursor = strtotime( date( 'Y-m', $start ) . '-01' );
			$last   = min( $end, time() );
			while ( $cursor <= $last ) {
				$months[] = date( 'Y-m', $cursor );
				$cursor   = EM_Helpers::add_months( $cursor, 1 );
			}
			foreach ( $months as $month ) {
				/* Most months paid; a few missed, some short — so the demo has
				   realistic arrears rather than everyone owing everything. */
				if ( wp_rand( 1, 100 ) > 85 ) {
					continue;
				}
				$pdate   = strtotime( $month . '-01' ) + wp_rand( 0, 27 ) * DAY_IN_SECONDS;
				if ( $pdate > time() ) {
					$pdate = time();
				}
				$pay    = wp_rand( 1, 100 ) > 80 ? $rent : round( $rent * ( 0.4 + wp_rand( 0, 50 ) / 100 ), 2 );
				$methods = array_keys( EM_Settings::payment_methods() );
				$method  = $methods[ array_rand( $methods ) ];
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$pays_t} (lease_id, unit_id, tenant_id, amount, payment_date, period, method, notes, created_at) VALUES (%d, %d, %d, %f, %s, %s, %s, '', %s)",
					$lease_id, $ur['unit_id'], $tenant_id, $pay, date( 'Y-m-d', $pdate ), $month, $method, $now
				) );
			}

			/* Meter readings. */
			if ( wp_rand( 1, 100 ) > 25 ) {
				$r_date = strtotime( '-' . wp_rand( 0, 45 ) . ' days' );
				$prev   = 300 + wp_rand( 0, 2000 );
				$curr   = $prev + 8 + wp_rand( 0, 40 );
				$st     = wp_rand( 1, 100 ) > 45 ? 'paid' : 'pending';
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$reads_t} (unit_id, utility, reading_date, current_reading, previous_reading, consumption, rate_per_unit, amount, status, notes, created_at) VALUES (%d, 'water', %s, %f, %f, %f, 80, %f, %s, '', %s)",
					$ur['unit_id'], date( 'Y-m-d', $r_date ), $curr, $prev, round( $curr - $prev, 2 ), round( ( $curr - $prev ) * 80, 2 ), $st, $now
				) );
				$e_prev = 100 + wp_rand( 0, 3000 );
				$e_curr = $e_prev + 20 + wp_rand( 0, 90 );
				$st     = wp_rand( 1, 100 ) > 45 ? 'paid' : 'pending';
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$reads_t} (unit_id, utility, reading_date, current_reading, previous_reading, consumption, rate_per_unit, amount, status, notes, created_at) VALUES (%d, 'electricity', %s, %f, %f, %f, 30, %f, %s, '', %s)",
					$ur['unit_id'], date( 'Y-m-d', $r_date ), $e_curr, $e_prev, round( $e_curr - $e_prev, 2 ), round( ( $e_curr - $e_prev ) * 30, 2 ), $st, $now
				) );
			}
		}

		update_option( 'em_seeded', 1 );
		return true;
	}

	/* ---------------- Audit log ---------------- */

	/**
	 * Record an action in the audit trail.
	 *
	 * @param string      $action      login | create | update | delete | terminate | vacate | renew | record_* | ...
	 * @param string      $entity_type property | unit | tenant | lease | payment | reading | settings | ...
	 * @param int|null    $entity_id
	 * @param string      $reason      required explanation (deletes) or context
	 * @param string      $details
	 * @param WP_User|null $user        falls back to current user
	 */
	public static function log( $action, $entity_type, $entity_id = null, $reason = '', $details = '', $user = null ) {
		global $wpdb;
		$user = $user ? $user : wp_get_current_user();
		$wpdb->insert(
			self::table( 'audit_log' ),
			array(
				'user_id'     => $user && $user->ID ? $user->ID : null,
				'username'    => $user && $user->user_login ? $user->user_login : ( is_user_logged_in() ? get_user_option( 'user_login' ) : 'system' ),
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'reason'      => $reason,
				'details'     => $details,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/* ---------------- Visits ---------------- */

	public static function log_visit( $args ) {
		global $wpdb;
		$ua = (string) ( $args['user_agent'] ?? '' );
		$v  = array(
			'created_at' => current_time( 'mysql' ),
			'path'       => substr( (string) ( $args['path'] ?? '/' ), 0, 255 ),
			'ip'         => substr( (string) ( $args['ip'] ?? '' ), 0, 50 ),
			'user_agent' => substr( $ua, 0, 500 ),
		);
		$wpdb->insert( self::table( 'visits' ), $v );
		return true;
	}

	public static function prune_visits( $keep = 10000 ) {
		global $wpdb;
		$t = self::table( 'visits' );
		$wpdb->query( "DELETE FROM {$t} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$t} ORDER BY id DESC LIMIT {$keep}) AS tmp)" );
	}

	/* ---------------- Small shared queries ---------------- */

	/** True when a unit has an active lease. */
	public static function unit_has_active_lease( $unit_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table( 'leases' ) . " WHERE unit_id = %d AND status = 'active'",
			$unit_id
		) ) > 0;
	}

	/** Delete a unit and everything linked to it (payments, readings, leases). */
	public static function delete_unit_cascade( $unit_id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'payments' ), array( 'unit_id' => $unit_id ) );
		$wpdb->delete( self::table( 'meter_readings' ), array( 'unit_id' => $unit_id ) );
		$wpdb->delete( self::table( 'leases' ), array( 'unit_id' => $unit_id ) );
		$wpdb->delete( self::table( 'units' ), array( 'id' => $unit_id ) );
	}

	public static function properties_list() {
		global $wpdb;
		$t = self::table( 'properties' );
		return $wpdb->get_results( "
			SELECT p.*,
				(SELECT COUNT(*) FROM " . self::table( 'units' ) . " u WHERE u.property_id = p.id) AS unit_count,
				(SELECT COUNT(*) FROM " . self::table( 'units' ) . " u WHERE u.property_id = p.id AND u.status = 'occupied') AS occupied_count
			FROM {$t} p ORDER BY p.name ASC", ARRAY_A );
	}

	public static function property_options() {
		$out   = array();
		$props = self::properties_list();
		if ( $props ) {
			foreach ( $props as $p ) {
				$out[ (int) $p['id'] ] = $p['name'];
			}
		}
		return $out;
	}
}