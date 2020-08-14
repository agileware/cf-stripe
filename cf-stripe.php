<?php
/**
 * @package   Caldera_Forms_Stripe
 * @author    David Cramer <david@digilab.co.za>
 * @license   GPL-2.0+
 * @link      
 * @copyright 2014 - 2105 David Cramer <david@digilab.co.za> and CalderaWP LLC.
 *
 * @wordpress-plugin
 * Plugin Name: Caldera Forms Stripe
 * Plugin URI:  
 * Description: Stripe Payments with Caldera Forms
 * Version: 1.4.7
 * Author:      Caldera Labs
 * Author URI:  https://calderaforms.com
 * Text Domain: cf-stripe
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// define constants
define( 'CF_STRIPE_PATH',  plugin_dir_path( __FILE__ ) );
define( 'CF_STRIPE_CORE',  __FILE__ );
define( 'CF_STRIPE_URL',  plugin_dir_url( __FILE__ ) );
define( 'CF_STRIPE_VER', '1.4.7' );


// add language text domain
add_action( 'init', 'cf_stripe_load_plugin_textdomain' );

// filter to add processor to regestered processors array
add_filter( 'caldera_forms_get_form_processors', 'cf_stripe_register_processor' );

// add filter to check and add stripe attributes to a form for ajax
add_filter( 'caldera_forms_render_form_attributes', 'cf_stripe_form_atts', 10, 2);

// load dependencies
include_once CF_STRIPE_PATH . 'vendor/autoload.php';


// pull in the functions file
include CF_STRIPE_PATH . 'includes/functions.php';



/**
 * Software Licensing
 */
// filter to initialize the license system
add_action( 'admin_init', 'cf_stripe_init_license' );
/**
 * Initializes the licensing system
 *
 * @since 1.1.0
 */
function cf_stripe_init_license(){
	
	$plugin = array(
		'name'		=>	'Caldera Forms Stripe Add-on',
		'slug'		=>	'caldera-forms-stripe-add-on',
		'url'		=>	'https://calderaforms.com/',
		'version'	=>	CF_STRIPE_VER,
		'key_store'	=>  'cfstripe_license',
		'file'		=>  CF_STRIPE_CORE
	);

	new \calderawp\licensing_helper\licensing( $plugin );

}
