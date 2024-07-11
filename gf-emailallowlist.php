<?php
/**
 * Plugin Name: Gravity Forms Email Allowlist
 * Description: This plugin adds the ability to set an allowlist of domains on the email field in Gravity Forms.
 * Version: 1.0.0
 * Author: Rob Migchels
 * Author URI: https://www.robmigchels.com/
 * Text Domain: gf-email-allowlist
 * Domain Path: /languages
 *
 * @package GFEmailAllowlist
 */

defined( 'ABSPATH' ) || exit;

add_action( 'gform_loaded', array( 'GFEmailAllowlist_Bootstrap', 'load' ), 5 );

class GFEmailAllowlist_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'includes/class-gf-emailallowlist.php' );

        GFAddOn::register( 'GFEmailAllowlist' );
    }

}

function rbgf_go() {
    return GFEmailAllowlist::get_instance();
}
