<?php
/**
 * Melapress Login Security - Uninstall handler.
 *
 * This file uses WordPress's native uninstall.php approach instead of
 * register_uninstall_hook() for the following reasons:
 *
 * 1. register_uninstall_hook() stores its callback in the database
 *    (uninstall_plugins option) at the time of registration. If the
 *    licensing provider is switched (e.g., from EDD to Freemius or
 *    vice versa), the stored callback becomes stale and may trigger
 *    incorrect cleanup logic or errors on uninstall.
 *
 * 2. uninstall.php runs fresh on every uninstall and can check the
 *    current provider state at that moment — no stale DB entries,
 *    no provider-switching edge cases.
 *
 * 3. Freemius SDK has its own internal uninstall mechanism
 *    (after_uninstall hook), so we only need to handle EDD cleanup here.
 *
 * 4. This file is safe to ship with both free and premium versions.
 *    The EDD cleanup block is guarded by class_exists() — on the free
 *    version the EDD_Provider class does not exist, so the block is
 *    skipped entirely.
 *
 * License data (key, status, cached data) is ALWAYS cleared on uninstall
 * regardless of the "Delete database data upon uninstall" setting, because
 * keeping a remote activation slot occupied on a site where the plugin no
 * longer exists is not useful. The clear_history setting only governs
 * plugin operational data (policies, user meta, password history).
 *
 * @since   3.3.0
 * @package MelapressLoginSecurity
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load autoloader.
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	return;
}
require_once $autoloader;

// Load plugin constants needed by the EDD provider.
if ( ! defined( 'MLS_FILE' ) ) {
	define( 'MLS_FILE', __DIR__ . '/melapress-login-security-premium.php' );
}

if ( ! defined( 'MLS_PATH' ) ) {
	define( 'MLS_PATH', plugin_dir_path( MLS_FILE ) );
}

if ( ! defined( 'MLS_PREFIX' ) ) {
	if ( ! empty( get_site_option( 'ppmwp_options', false ) ) ) {
		define( 'MLS_PREFIX', 'ppmwp' );
	} else {
		define( 'MLS_PREFIX', 'mls' );
	}
}

// Check which licensing provider was active.
$provider = get_option( 'mls_licensing_provider', '' );

if ( 'edd' === $provider && class_exists( '\MLS\Licensing\EDD_Provider' ) ) {
	// Remotely deactivate the license to free the activation slot.
	\MLS\Licensing\EDD_Provider::deactivate_license();

	// Always clear local license data regardless of clear_history setting.
	\MLS\Licensing\EDD_Provider::clear_local_license_data();
}

// Run general plugin cleanup (respects the clear_history setting).
if ( class_exists( 'MLS_Core' ) ) {
	\MLS_Core::cleanup();
}
