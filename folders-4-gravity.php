<?php
/**
 * Plugin Name: Folders4Gravity - Folders for Gravity Forms and GravityView
 * Plugin URI: https://brightleafdigital.io/folders-4-gravity/
 * Author URI: https://brightleafdigital.io/
 * Description: Organize your Gravity Forms and Gravity Views by folders.
 * Version: 1.0.8
 * Author: BrightLeaf Digital
 * License: GPL-2.0+
 * Requires PHP: 8.0
 */

use function GravityOps\Core\Admin\gravityops_shell;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

if ( file_exists( __DIR__ . '/vendor/F4G/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/F4G/autoload.php';
}

// Instantiate this plugin's copy of the AdminShell early so provider negotiation can happen on plugins_loaded.
add_action(
    'plugins_loaded',
    function () {
	    gravityops_shell();
    },
    1
);


if ( function_exists( 'register_form_folders_submenu' ) ) {
	add_action(
        'admin_notices',
        function () {
			echo '<div class="notice notice-error"><p>The Form Folders snippet version has been detected. Please deactivate it before using this plugin.</p></div>';
		}
        );

	return;
}

// Ensure GravityOps shared assets resolve when library is vendor-installed in this plugin.
add_filter(
    'gravityops_assets_base_url',
    function ( $url ) {
        if ( $url ) {
            return $url;
        }

        if ( file_exists( __DIR__ . '/vendor/F4G/gravityops/core/assets/' ) ) {
            return plugins_url( 'vendor/F4G/gravityops/core/assets/', __FILE__ );
        }

        return plugins_url( 'vendor/gravityops/core/assets/', __FILE__ );
    }
);

add_action(
	'gform_loaded',
	function () {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}
		require_once 'includes/class-gravity-ops-form-folders.php';

		GFAddOn::register( 'Gravity_Ops_Form_Folders' );

		// Load Views Folders class if GravityView is active
		if ( class_exists( 'GVCommon' ) ) {
			require_once 'includes/class-gravity-ops-views-folders.php';
			GFAddOn::register( 'Gravity_Ops_Views_Folders' );
		}
	}
);

define( 'FOLDERS_4_GRAVITY_VERSION', '1.0.8' );
define( 'FOLDERS_4_GRAVITY_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Returns the instance of the Form_Folders class.
 *
 * @return Gravity_Ops_Form_Folders|null
 */
function gravity_ops_form_folders() {
	if ( class_exists( 'Gravity_Ops_Form_Folders' ) ) {
		return Gravity_Ops_Form_Folders::get_instance();
	}
	return null;
}

/**
 * Returns the instance of the Views_Folders class.
 *
 * @return Gravity_Ops_Views_Folders|null
 */
function gravity_ops_views_folders() {
	if ( class_exists( 'Gravity_Ops_Views_Folders' ) ) {
		return Gravity_Ops_Views_Folders::get_instance();
	}
	return null;
}
