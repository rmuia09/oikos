<?php
/**
 * SMS (Africa's Talking), email via WordPress, reminder/rent-due message
 * builders and the automated renewal-reminder engine.
 */

defined( 'ABSPATH' ) || exit;

class EM_Notify {

	/* ---------------- Delivery ---------------- */

	/** Africa's Talking SMS. Returns array( ok, simulated, messageId, error ). */
	public static function send_sms( $recipient, $message, $settings = null ) {
		$settings = $settings ? $settings : EM_Settings::all();
		$enabled  = '1' === (string) $settings['sms_enabled'];
		$username = trim( (string) $settings['sms_username'] );
		$api_key  = trim( (string) $settings['sms_api_key'] );
		if ( ! $enabled || ! $username || ! $api_key ) {
			return array( 'ok' => true, 'simulated' => true, 'messageId' => null, 'error' => null );
		}
		$sandbox   = '1' === (string) $settings['sms_sandbox'];
		$host      = $sandbox ? 'api.sandbox.africastalking.com' : 'api.africastalking.com';
		$from      = trim( (string) $settings['sms_from'] );
		$body      = array(
			'username' => $sandbox ? 'sandbox' : $username,
			'to'       => $recipient,
			'message'  => $message,
		);
		if ( $from ) {
			$body['from'] = $from;
		}
		$resp = wp_remote_post( "https://{$host}/version1/messaging", array(
			'timeout' => 15,
			'headers' => array(
				'apiKey'      => $api_key,
				'Accept'      => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => $body,
		) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'simulated' => false, 'messageId' => null, 'error' => $resp->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$list = $data['SMSMessageData']['Recipients'] ?? array();
		if ( isset( $list[0] ) ) {
			$first = $list[0];
			if ( ( $first['status'] ?? '' ) === 'Success' || ( $first['statusCode'] ?? '' ) === 101 ) {
				return array( 'ok' => true, 'simulated' => false, 'messageId' => $first['messageId'] ?? null, 'error' => null );
			}
			return array( 'ok' => false, 'simulated' => false, 'messageId' => null, 'error' => $first['failureReason'] ?? 'SMS failed' );
		}
		return array( 'ok' => true, 'simulated' => false, 'messageId' => null, 'error' => null );
	}

	/**
	 * Send an email. When SMTP credentials are configured they are applied to
	 * PHPMailer for this one call; otherwise WordPress' default mailer is used.
	 * Returns array( ok, simulated, error ).
	 */
	public static function send_email( $to, $subject, $body, $settings = null ) {
		$settings = $settings ? $settings : EM_Settings::all();
		$enabled  = '1' === (string) $settings['email_enabled'];
		$host     = trim( (string) $settings['email_host'] );
		$user     = trim( (string) $settings['email_user'] );
		if ( ! $enabled || ! $host || ! $user ) {
			return array( 'ok' => true, 'simulated' => true, 'messageId' => null, 'error' => null );
		}

		$filter = function ( $phpmailer ) use ( $settings ) {
			$phpmailer->isSMTP();
			$phpmailer->Host       = $settings['email_host'];
			$phpmailer->Port       = (int) ( $settings['email_port'] ? $settings['email_port'] : 587 );
			$phpmailer->SMTPAuth   = ! empty( $settings['email_user'] );
			$phpmailer->Username   = $settings['email_user'];
			if ( ! empty( $settings['email_pass'] ) ) {
				$phpmailer->Password = $settings['email_pass'];
			}
			/* 587 uses STARTTLS, 465 uses implicit SSL. Gmail accepts both. */
			$phpmailer->SMTPSecure = (int) $phpmailer->Port === 465 ? 'ssl' : 'tls';
			$phpmailer->CharSet    = 'UTF-8';

			/* Gmail (and most providers) reject a From that doesn't match the
			   authenticated account. Accept "Name <addr>" or a bare address,
			   and default to the SMTP username rather than WordPress' own
			   wordpress@host, which Gmail always refuses. */
			$from = trim( (string) $settings['email_from'] );
			if ( '' === $from ) {
				$from = trim( (string) $settings['email_user'] );
			}
			if ( '' !== $from ) {
				if ( preg_match( '/^(.*)<\s*([^>]+?)\s*>\s*$/', $from, $m ) ) {
					$phpmailer->From = trim( $m[2] );
					$name = trim( $m[1], " \t\"'" );
					if ( '' !== $name ) {
						$phpmailer->FromName = $name;
					}
				} else {
					$phpmailer->From = $from;
				}
			}
		};
		add_action( 'phpmailer_init', $filter );
		$ok = wp_mail( $to, $subject, $body );
		remove_action( 'phpmailer_init', $filter );

		if ( ! $ok ) {
			return array( 'ok' => false, 'simulated' => false, 'messageId' => null, 'error' => 'wp_mail() returned false' );
		}
		return array( 'ok' => true, 'simulated' => false, 'messageId' => null, 'error' => null );
	}

	/* ---------------- Message builders ---------------- */

	public static function lease_summary( $lease_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT l.*, u.unit_number, u.property_id, pr.name AS property_name, t.name AS tenant_name, t.phone, t.email
			 FROM " . EM_DB::table( 'leases' ) . " l
			 JOIN " . EM_DB::table( 'units' ) . " u ON u.id = l.unit_id
			 JOIN " . EM_DB::table( 'properties' ) . " pr ON pr.id = u.property_id
			 JOIN " . EM_DB::table( 'tenants' ) . " t ON t.id = l.tenant_id
			 WHERE l.id = %d",
			$lease_id
		), ARRAY_A );
	}

	private static function pending_utilities( $unit_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT utility, COALESCE(SUM(amount), 0) AS total
			 FROM " . EM_DB::table( 'meter_readings' ) . "
			 WHERE unit_id = %d AND status = 'pending' GROUP BY utility",
			$unit_id
		), ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['utility'] ] = (float) $r['total'];
		}
		return $out;
	}

	private static function outstanding_rent( $lease ) {
		global $wpdb;
		$paid = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount), 0) FROM " . EM_DB::table( 'payments' ) . " WHERE lease_id = %d",
			$lease['id']
		) );
		return max( 0, (float) $lease['monthly_rent'] - $paid );
	}

	public static function build_reminder_text( $lease, $settings = array() ) {
		$util_total = 0;
		$lines      = array(
			"{$lease['property_name']} — Lease renewal reminder",
			"Dear {$lease['tenant_name']},",
			"Your lease for {$lease['unit_number']} expires on {$lease['end_date']}.",
			'Renewal is due for renewal every ' . (int) ( $lease['renewal_months'] ? $lease['renewal_months'] : 12 ) . ' month(s).',
			'Rent amount: ' . EM_Helpers::money( $lease['monthly_rent'] ) . '/month',
		);
		$utils = self::pending_utilities( $lease['unit_id'] );
		$w     = $utils['water'] ?? 0;
		$e     = $utils['electricity'] ?? 0;
		$util_total = $w + $e;
		if ( $util_total > 0 ) {
			$lines[] = 'Outstanding utility bills: water ' . EM_Helpers::money( $w ) . ' + electricity ' . EM_Helpers::money( $e ) . ' = ' . EM_Helpers::money( $util_total );
		}
		$lines[] = 'Please contact the estate office to confirm renewal.';
		return implode( "\n", $lines );
	}

	public static function build_rent_due_text( $lease, $settings = array() ) {
		$utils      = self::pending_utilities( $lease['unit_id'] );
		$util_total = ( $utils['water'] ?? 0 ) + ( $utils['electricity'] ?? 0 );
		$lines      = array(
			"{$lease['property_name']} — Rent due",
			"Dear {$lease['tenant_name']},",
			'Rent for ' . $lease['unit_number'] . ' (' . $lease['unit_number'] . ') is ' . EM_Helpers::money( $lease['monthly_rent'] ) . '/month.',
		);
		if ( $util_total > 0 ) {
			$lines[] = 'Pending utilities: water ' . EM_Helpers::money( $utils['water'] ?? 0 ) . ' + electricity ' . EM_Helpers::money( $utils['electricity'] ?? 0 ) . ' = ' . EM_Helpers::money( $util_total );
			$lines[] = 'Total due: ' . EM_Helpers::money( (float) $lease['monthly_rent'] + $util_total );
		} else {
			$lines[] = 'Total due: ' . EM_Helpers::money( $lease['monthly_rent'] );
		}
		$lines[] = 'Please settle by the 5th of the month. Thank you.';
		return implode( "\n", $lines );
	}

	/* ---------------- Record + send ---------------- */

	public static function record( $args ) {
		global $wpdb;
		$wpdb->insert(
			EM_DB::table( 'notifications' ),
			array(
				'channel'            => $args['channel'],
				'kind'               => $args['kind'],
				'lease_id'           => $args['lease_id'],
				'tenant_id'          => $args['tenant_id'],
				'recipient'          => $args['recipient'],
				'subject'            => $args['subject'] ?? '',
				'body'               => $args['body'] ?? '',
				'status'             => $args['status'],
				'provider_message_id' => $args['messageId'] ?? '',
				'error'              => $args['error'] ?? '',
				'sent_at'            => in_array( $args['status'], array( 'sent', 'simulated' ), true ) ? current_time( 'mysql' ) : null,
				'created_at'         => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	private static function notify_channel( $channel, $lease, $args, $settings ) {
		$recipient = 'sms' === $channel ? $lease['phone'] : $lease['email'];
		if ( empty( $recipient ) ) {
			return null;
		}
		$result = 'sms' === $channel
			? self::send_sms( $recipient, $args['body'], $settings )
			: self::send_email( $recipient, $args['subject'], $args['body'], $settings );

		$status = $result['ok'] ? ( $result['simulated'] ? 'simulated' : 'sent' ) : 'failed';
		$id     = self::record( array(
			'channel'   => $channel,
			'kind'      => 'reminder',
			'lease_id'  => $lease['id'],
			'tenant_id' => $lease['tenant_id'],
			'recipient' => $recipient,
			'subject'   => $args['subject'] ?? '',
			'body'      => $args['body'],
			'status'    => $status,
			'messageId' => $result['messageId'],
			'error'     => $result['error'],
		) );
		return array( 'id' => $id, 'status' => $status, 'messageId' => $result['messageId'], 'error' => $result['error'] );
	}

	/** Send a reminder (default) or rent-due notification for a lease. */
	public static function send_for_lease( $lease_id, $kind = 'reminder' ) {
		$settings = EM_Settings::all();
		$lease    = self::lease_summary( $lease_id );
		if ( ! $lease ) {
			return array( 'error' => 'lease not found' );
		}

		$subject = 'rent_due' === $kind ? 'Rent due' : 'Lease renewal reminder';
		$body    = 'rent_due' === $kind
			? self::build_rent_due_text( $lease, $settings )
			: self::build_reminder_text( $lease, $settings );

		$results = array();
		if ( ! empty( $lease['phone'] ) ) {
			$r = self::notify_channel( 'sms', $lease, array( 'subject' => $subject, 'body' => $body ), $settings );
			if ( $r ) {
				$results[] = $r;
			}
		}
		if ( ! empty( $lease['email'] ) ) {
			$r = self::notify_channel( 'email', $lease, array( 'subject' => $subject, 'body' => $body ), $settings );
			if ( $r ) {
				$results[] = $r;
			}
		}
		if ( empty( $results ) ) {
			return array( 'error' => 'tenant has no phone or email on file' );
		}

		if ( 'reminder' === $kind && empty( $lease['notice_sent'] ) ) {
			global $wpdb;
			$wpdb->update(
				EM_DB::table( 'leases' ),
				array( 'notice_sent' => 1, 'notice_sent_at' => current_time( 'mysql' ) ),
				array( 'id' => $lease_id )
			);
		}
		return array( 'results' => $results );
	}

	/** Automated renewal reminders engine. */
	public static function run_renewal_reminders() {
		global $wpdb;
		$settings  = EM_Settings::all();
		$lookahead = max( 1, (int) ( $settings['renewal_reminder_months'] ? $settings['renewal_reminder_months'] : 2 ) );
		$rows      = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.id FROM " . EM_DB::table( 'leases' ) . " l
			 WHERE l.status = 'active' AND l.notice_sent = 0
			   AND l.end_date <= DATE_ADD(DATE(%s), INTERVAL %d MONTH)
			 ORDER BY l.end_date ASC",
			current_time( 'Y-m-d' ), $lookahead
		), ARRAY_A );

		$sent   = array();
		$failed = array();
		foreach ( (array) $rows as $row ) {
			$r = self::send_for_lease( (int) $row['id'], 'reminder' );
			if ( ! empty( $r['error'] ) ) {
				$failed[] = array( 'id' => (int) $row['id'], 'error' => $r['error'] );
			} else {
				$sent[] = (int) $row['id'];
			}
		}
		return array( 'checked' => count( $rows ), 'sent' => $sent, 'failed' => $failed );
	}

	public static function send_test_message( $channel, $recipient ) {
		$settings = EM_Settings::all();
		$subject  = 'Oikos — test message';
		$body     = 'This is a test message from Oikos. If you can read this, your ' .
			( 'sms' === $channel ? 'SMS (Africa\'s Talking)' : 'email (SMTP)' ) . ' integration is working.';
		$result   = 'sms' === $channel
			? self::send_sms( $recipient, $body, $settings )
			: self::send_email( $recipient, $subject, $body, $settings );
		$status   = $result['ok'] ? ( $result['simulated'] ? 'simulated' : 'sent' ) : 'failed';
		self::record( array(
			'channel'   => $channel,
			'kind'      => 'test',
			'lease_id'  => null,
			'tenant_id' => null,
			'recipient' => $recipient,
			'subject'   => $subject,
			'body'      => $body,
			'status'    => $status,
			'messageId' => $result['messageId'],
			'error'     => $result['error'],
		) );
		return $result;
	}
}