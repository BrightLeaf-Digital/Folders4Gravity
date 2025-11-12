<?php
/**
 * Plugin Name: Folders4Gravity
 * Plugin URI: https://brightleafdigital.io/folders-4-gravity/
 * Author URI: https://brightleafdigital.io/
 * Description: Organize your Gravity Forms and Gravity Views by folders.
 * Version: 1.0.3
 * Author: BrightLeaf Digital
 * License: GPL-2.0+
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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

define( 'FOLDERS_4_GRAVITY_VERSION', '1.0.3' );
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
