<?php
/**
 * Plugin Name: iBrains Gravity Forms Add-On
 * Description:
 * Version: 0.0.1
 * Author: Rob Migchels
 * Author URI: https://www.robmigchels.com/
 * Text Domain: ib-gravity-forms
 * Domain Path: /languages
 *
 * @package GFEmailAllowlist
 */

defined( 'ABSPATH' ) || exit;

define( 'IBGF_VERSION', '0.0.1' );

add_action( 'gform_loaded', array( 'IB_Gravity_Forms_Bootstrap', 'load' ), 5 );

class IB_Gravity_Forms_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'includes/class-ibrains-gravityforms.php' );

        GFAddOn::register( 'IBGFAddOn' );
    }

}

function gf_simple_addon() {
    return IBGFAddOn::get_instance();
}
