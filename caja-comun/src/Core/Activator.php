<?php

namespace CCF\Core;

use CCF\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		$installer = new Installer();
		$installer->install();
		self::register_capabilities();
	}

	public static function maybe_recover(): void {
		self::register_capabilities();

		$installer = new Installer();
		if ( CCF_VERSION !== (string) get_option( 'ccf_version', '' ) ) {
			$installer->install();
			return;
		}

		$installer->ensure_defaults();
	}

	private static function register_capabilities(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		foreach ( Capabilities::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}
}
