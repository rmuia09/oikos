<?php
/**
 * Dashboard stats, configurable weekly/monthly reports and the rent ledger.
 */

defined( 'ABSPATH' ) || exit;

class EM_Reports {

	public static function dashboard_stats() {
		global $wpdb;
		$u = EM_DB::table( 'units' );
		$l = EM_DB::table( 'leases' );
		$p = EM_DB::table( 'payments' );
		$r = EM_DB::table( 'meter_readings' );
		$n = EM_DB::table( 'notifications' );

		$total_units = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u}" );
		$occupied    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE status = 'occupied'" );
		$expected    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(monthly_rent),0) FROM {$l} WHERE status = 'active'" );
		$collected   = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$p} WHERE DATE_FORMAT(payment_date, '%Y-%m') = %s", current_time( 'Y-m' ) ) );
		$pending_util = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$r} WHERE status = 'pending'" );
		$pending_fail = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$n} WHERE status = 'failed'" );

		$recent = $wpdb->get_results( "
			SELECT p.*, u.unit_number, pr.name AS property_name, t.name AS tenant_name
			FROM {$p} p
			JOIN {$u} u ON u.id = p.unit_id
			JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			LEFT JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = p.tenant_id
			ORDER BY p.payment_date DESC, p.id DESC LIMIT 12", ARRAY_A );

		$expiring = $wpdb->get_results( $wpdb->prepare( "
			SELECT l.id, l.end_date, l.monthly_rent, l.notice_sent, l.renewal_months,
			       u.unit_number, pr.name AS property_name, t.name AS tenant_name, t.phone
			FROM {$l} l
			JOIN {$u} u ON u.id = l.unit_id
			JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
			WHERE l.status = 'active' AND l.end_date <= DATE_ADD(DATE(%s), INTERVAL 60 DAY)
			ORDER BY l.end_date ASC", current_time( 'Y-m-d' ) ), ARRAY_A );
		/* Cast first, then iterate by reference. Casting inside the foreach
		   iterates a temporary copy, so the days_left write was discarded and
		   the dashboard rendered "undefinedd left". */
		$expiring = (array) $expiring;
		foreach ( $expiring as &$e ) {
			$e['days_left'] = EM_Helpers::days_left( $e['end_date'] );
		}
		unset( $e );

		return array(
			'total_units'        => $total_units,
			'occupied_units'     => $occupied,
			'vacant_units'       => $total_units - $occupied,
			'expected_monthly'   => $expected,
			'collected_month'    => $collected,
			'month_outstanding'  => max( 0, $expected - $collected ),
			'pending_utility'    => $pending_util,
			'recent_payments'    => $recent,
			'expiring_soon'      => $expiring,
			'pending_notifications' => $pending_fail,
		);
	}

	/** Weekly or monthly income summary. */
	public static function summary( $period, $property_id = null, $from = null, $to = null, $limit = 12 ) {
		global $wpdb;
		$period = 'monthly' === $period ? 'monthly' : 'weekly';
		if ( $from ) {
			$from = date( 'Y-m-d', strtotime( $from ) );
		}
		if ( $to ) {
			$to = date( 'Y-m-d', strtotime( $to ) );
		}
		if ( ! $from ) {
			$from = date( 'Y-m-d', EM_Helpers::add_months( EM_Helpers::monday_of( current_time( 'timestamp' ) ), -12 ) );
		}
		if ( ! $to ) {
			$to = current_time( 'Y-m-d' );
		}

		$pkey   = 'monthly' === $period
			? "DATE_FORMAT(p.payment_date, '%Y-%m')"
			: EM_Helpers::week_start_expr( 'p.payment_date' );

		$where  = array( 'p.payment_date BETWEEN %s AND %s' );
		$params = array( $from, $to );
		if ( $property_id ) {
			$where[]  = 'u.property_id = %d';
			$params[] = (int) $property_id;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT {$pkey} AS pkey, pr.name AS property, COALESCE(SUM(p.amount), 0) AS total, COUNT(*) AS txns
			 FROM " . EM_DB::table( 'payments' ) . " p
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = p.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 WHERE " . implode( ' AND ', $where ) . "
			 GROUP BY pkey, pr.id
			 ORDER BY pkey DESC",
			$params
		), ARRAY_A );

		$by_key = array();
		foreach ( (array) $rows as $g ) {
			$by_key[ $g['pkey'] ][ $g['property'] ] = array( 'total' => (float) $g['total'], 'txns' => (int) $g['txns'] );
		}
		$keys = array_keys( $by_key );
		sort( $keys );
		$keys = array_slice( $keys, -$limit );
		$keys = array_reverse( $keys );

		$periods = array();
		$grand   = 0;
		foreach ( $keys as $k ) {
			$map   = $by_key[ $k ];
			$total = 0;
			$txns  = 0;
			$by_property = array();
			foreach ( $map as $property => $v ) {
				$total += $v['total'];
				$txns  += $v['txns'];
				$by_property[] = array( 'property' => $property, 'total' => $v['total'], 'txns' => $v['txns'] );
			}
			$grand += $total;
			$start  = 'monthly' === $period ? $k . '-01' : $k;
			$end    = 'monthly' === $period ? EM_Helpers::month_end( $k ) : date( 'Y-m-d', strtotime( $k . ' +6 days' ) );
			$periods[] = array(
				'key'        => $k,
				'start'      => $start,
				'end'        => $end,
				'total'      => $total,
				'txns'       => $txns,
				'by_property' => $by_property,
			);
		}

		return array(
			'period'        => $period,
			'property_id'   => $property_id,
			'from'          => $from,
			'to'            => $to,
			'grand_total'   => $grand,
			'periods'       => $periods,
		);
	}

	/** Transactions + per-property totals for one period (week start or YYYY-MM). */
	public static function period_detail( $period, $key, $property_id = null ) {
		global $wpdb;
		$period = 'monthly' === $period ? 'monthly' : 'weekly';
		if ( 'monthly' === $period ) {
			$start = EM_Helpers::month_start( $key );
			$end   = EM_Helpers::month_end( $key );
		} else {
			$start = $key;
			$end   = date( 'Y-m-d', strtotime( $key . ' +6 days' ) );
		}

		$prop_sql  = $property_id ? 'AND u.property_id = %d' : '';
		$prop_more = $property_id ? array( (int) $property_id ) : array();

		$by_property = $wpdb->get_results( $wpdb->prepare(
			"SELECT pr.name AS property, COALESCE(SUM(p.amount), 0) AS total, COUNT(*) AS txns
			 FROM " . EM_DB::table( 'payments' ) . " p
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = p.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 WHERE p.payment_date BETWEEN %s AND %s {$prop_sql}
			 GROUP BY pr.id",
			array_merge( array( $start, $end ), $prop_more )
		), ARRAY_A );

		$details = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.payment_date, p.amount, p.method, p.period,
			        u.unit_number, pr.name AS property_name, t.name AS tenant_name
			 FROM " . EM_DB::table( 'payments' ) . " p
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = p.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 LEFT JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = p.tenant_id
			 WHERE p.payment_date BETWEEN %s AND %s {$prop_sql}
			 ORDER BY p.payment_date ASC, p.id ASC",
			array_merge( array( $start, $end ), $prop_more )
		), ARRAY_A );

		$total = 0;
		$txns  = 0;
		foreach ( (array) $by_property as $bp ) {
			$total += (float) $bp['total'];
			$txns  += (int) $bp['txns'];
		}

		return array(
			'period'      => $period,
			'key'         => $key,
			'start'       => $start,
			'end'         => $end,
			'total'       => $total,
			'txns'        => $txns,
			'by_property' => $by_property,
			'details'     => $details,
		);
	}

	/** Per-tenant, per-month expected vs paid vs outstanding. */
	public static function rent_ledger( $from = null, $to = null, $property_id = null, $tenant_id = null ) {
		global $wpdb;
		$f = $from ? substr( $from, 0, 7 ) : date( 'Y-m', EM_Helpers::add_months( current_time( 'timestamp' ), -6 ) );
		$t = $to ? substr( $to, 0, 7 ) : current_time( 'Y-m' );
		$months = EM_Helpers::months_between( $f, $t );

		$lease_sql = array(
			"SELECT l.id, l.unit_id, l.tenant_id, l.start_date, l.end_date, l.monthly_rent, l.renewal_months, l.status,
			        u.unit_number, u.property_id, pr.name AS property_name, t.name AS tenant_name, t.phone
			 FROM " . EM_DB::table( 'leases' ) . " l
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = l.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
			 WHERE l.status = 'active'",
		);
		$lease_params = array();
		if ( $property_id ) {
			$lease_sql[]    = 'AND u.property_id = %d';
			$lease_params[] = (int) $property_id;
		}
		if ( $tenant_id ) {
			$lease_sql[]    = 'AND l.tenant_id = %d';
			$lease_params[] = (int) $tenant_id;
		}
		$leases = $wpdb->get_results( $wpdb->prepare( implode( ' ', $lease_sql ), $lease_params ), ARRAY_A );

		$pay_sql = array(
			"SELECT p.lease_id, l.unit_id, l.tenant_id, DATE_FORMAT(p.payment_date, '%%Y-%%m') AS month, COALESCE(SUM(p.amount), 0) AS paid
			 FROM " . EM_DB::table( 'payments' ) . " p
			 JOIN " . EM_DB::table( 'leases' ) . " l ON l.id = p.lease_id
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = p.unit_id
			 WHERE p.lease_id IS NOT NULL AND p.payment_date BETWEEN %s AND %s",
		);
		$pay_params = array( $f . '-01', EM_Helpers::month_end( $t ) );
		if ( $property_id ) {
			$pay_sql[]    = 'AND u.property_id = %d';
			$pay_params[] = (int) $property_id;
		}
		if ( $tenant_id ) {
			$pay_sql[]    = 'AND l.tenant_id = %d';
			$pay_params[] = (int) $tenant_id;
		}
		$pay_sql[] = 'GROUP BY 4';
		$pays      = $wpdb->get_results( $wpdb->prepare( implode( ' ', $pay_sql ), $pay_params ), ARRAY_A );

		$pay_map = array();
		foreach ( (array) $pays as $p ) {
			$pay_map[ $p['lease_id'] . '|' . $p['month'] ] = (float) $p['paid'];
		}

		$by_tenant = array();
		foreach ( (array) $leases as $lease ) {
			$entry = array(
				'tenant_id'      => (int) $lease['tenant_id'],
				'tenant_name'    => $lease['tenant_name'],
				'phone'          => $lease['phone'],
				'unit_number'    => $lease['unit_number'],
				'property_name'  => $lease['property_name'],
				'monthly_rent'   => (float) $lease['monthly_rent'],
				'status'         => $lease['status'],
				'months'         => array(),
				'total_expected' => 0,
				'total_paid'     => 0,
				'total_outstanding' => 0,
			);
			foreach ( $months as $m ) {
				$ms = EM_Helpers::month_start( $m );
				$me = EM_Helpers::month_end( $m );
				if ( $lease['end_date'] < $ms ) {
					continue;
				}
				if ( $lease['start_date'] > $me ) {
					continue;
				}
				$expected    = (float) $lease['monthly_rent'];
				$paid        = isset( $pay_map[ $lease['id'] . '|' . $m ] ) ? $pay_map[ $lease['id'] . '|' . $m ] : 0;
				$outstanding = max( 0, $expected - $paid );
				$entry['months'][] = array( 'month' => $m, 'expected' => $expected, 'paid' => $paid, 'outstanding' => $outstanding );
				$entry['total_expected'] += $expected;
				$entry['total_paid']     += $paid;
				$entry['total_outstanding'] += $outstanding;
			}
			if ( empty( $entry['months'] ) ) {
				continue;
			}
			if ( empty( $by_tenant[ $lease['tenant_id'] ] ) ) {
				$by_tenant[ $lease['tenant_id'] ] = $entry + array( 'leases' => array() );
			}
			$by_tenant[ $lease['tenant_id'] ]['leases'][] = $entry;
		}

		$tenants = array();
		foreach ( $by_tenant as $rt ) {
			$rt['total_expected']    = 0;
			$rt['total_paid']        = 0;
			foreach ( $rt['leases'] as $e ) {
				$rt['total_expected'] += $e['total_expected'];
				$rt['total_paid']     += $e['total_paid'];
			}
			$rt['total_outstanding'] = round( max( 0, $rt['total_expected'] - $rt['total_paid'] ), 2 );
			$tenants[] = $rt;
		}

		return array( 'from' => $f, 'to' => $t, 'months' => $months, 'tenants' => $tenants );
	}
}