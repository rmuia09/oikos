<?php
/**
 * Key/value settings with the same defaults as the original app.
 */

defined( 'ABSPATH' ) || exit;

class EM_Settings {

	public static function all() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT setting_key, setting_value FROM " . EM_DB::table( 'settings' ),
			ARRAY_A
		);
		$out  = EM_DB::default_settings();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$out[ $r['setting_key'] ] = $r['setting_value'];
			}
		}
		return $out;
	}

	public static function get( $key, $default = '' ) {
		$s = self::all();
		return $s[ $key ] ?? $default;
	}

	/** Upsert a single setting. */
	public static function set( $key, $value ) {
		global $wpdb;
		$t = EM_DB::table( 'settings' );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$t} (setting_key, setting_value) VALUES (%s, %s)
			 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
			$key, (string) $value
		) );
	}

	/** Speculative "masked" view used by the Settings page. */
	public static function masked( $type ) {
		$s = self::all();
		if ( 'sms' === $type ) {
			return array(
				'enabled'       => '1' === (string) $s['sms_enabled'],
				'provider'      => $s['sms_provider'],
				'username'      => $s['sms_username'],
				'from'          => $s['sms_from'],
				'sandbox'       => '1' === (string) $s['sms_sandbox'],
				'api_key_set'   => ! empty( $s['sms_api_key'] ),
				'device_id'     => $s['sms_device_id'] ?? '',
				'base_url'      => $s['sms_base_url'] ?? '',
				'generic_url'   => $s['sms_generic_url'] ?? '',
				'generic_auth'  => $s['sms_generic_auth'] ?? '',
				'generic_body'  => $s['sms_generic_body'] ?? '',
			);
		}
		/* Where reset links point. Surfaced here because a wrong value silently
		   sends users to the wrong site. Falls back to the legacy WP option so
		   existing installs keep working. */
		$app_url = (string) ( $s['em_app_url'] ?? '' );
		if ( ! $app_url ) {
			$app_url = (string) get_option( 'em_app_url', '' );
		}

		return array(
			'enabled'  => '1' === (string) $s['email_enabled'],
			'host'     => $s['email_host'],
			'port'     => (int) $s['email_port'],
			'user'     => $s['email_user'],
			'from'     => $s['email_from'],
			'pass_set' => ! empty( $s['email_pass'] ),
			'app_url'  => $app_url,
		);
	}

	public static function utility_rates() {
		return array(
			'water'       => (float) ( self::get( 'water', 0 ) ),
			'electricity' => (float) ( self::get( 'electricity', 0 ) ),
		);
	}

	/**
	 * Payment methods offered when recording rent, as value => label.
	 * Configurable so the plugin is not tied to one country's mobile money.
	 *
	 * @return array
	 */
	/**
	 * Configured payment methods as key => label.
	 *
	 * The wp_em_payments.method column is varchar(20), so a longer entry is
	 * truncated here rather than failing (or silently truncating) on insert.
	 */
	public static function payment_methods() {
		$out = array();
		foreach ( explode( ',', (string) self::get( 'payment_methods', '' ) ) as $m ) {
			$m = trim( $m );
			if ( strlen( $m ) > 20 ) {
				$m = function_exists( 'mb_substr' ) ? mb_substr( $m, 0, 20 ) : substr( $m, 0, 20 );
			}
			if ( '' !== $m ) {
				$out[ $m ] = ucfirst( $m );
			}
		}
		return $out ? $out : array( 'cash' => 'Cash' );
	}
}