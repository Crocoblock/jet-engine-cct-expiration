<?php
/**
 * Plugin Name: JetEngine - Custom Content Types Expiration
 * Plugin URI:
 * Description: 
 * Version:     1.0.0
 * Author:      d1nggo
 * Author URI:  
 * License:     
 * License URI: 
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'jet_engine_cct_exp' );

function jet_engine_cct_exp() {

    define( 'JET_ECVC_VERSION', '1.0.0' );

    define( 'JET_CCT_EXP__FILE__', __FILE__ );
	define( 'JET_CCT_EXP_PLUGIN_BASE', plugin_basename( JET_CCT_EXP__FILE__ ) );
	define( 'JET_CCT_EXP_PATH', plugin_dir_path( JET_CCT_EXP__FILE__ ) );

    require JET_CCT_EXP_PATH . 'includes/plugin.php';
}



