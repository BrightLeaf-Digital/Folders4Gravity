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

use GravityOps\Core\SuiteCore\SuiteCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FOLDERS_4_GRAVITY_VERSION', '1.0.8' );
define( 'FOLDERS_4_GRAVITY_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

if ( file_exists( __DIR__ . '/vendor/F4G/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/F4G/autoload.php';
}

// Register this plugin with SuiteCore early so the latest provider can be selected.
add_action(
    'plugins_loaded',
    function () {
	    if ( file_exists( __DIR__ . '/vendor/F4G/gravityops/core/assets/' ) ) {
            $assets_base_url = plugins_url( 'vendor/F4G/gravityops/core/assets/', __FILE__ );
        } else {
            $assets_base_url = plugins_url( 'vendor/gravityops/core/assets/', __FILE__ );
        }

	    SuiteCore::register(
            FOLDERS_4_GRAVITY_BASENAME,
            [
                'assets_base_url' => $assets_base_url,
            ]
        );
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
