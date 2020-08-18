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
 * Version: 1.4.11
 * Author:      Caldera Labs
 * Author URI:  https://calderaforms.com
 * Text Domain: cf-stripe
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

// define constants
define('CF_STRIPE_PATH',  plugin_dir_path(__FILE__));
define('CF_STRIPE_CORE',  __FILE__);
define('CF_STRIPE_URL',  plugin_dir_url(__FILE__));
define( 'CF_STRIPE_VER', '1.4.11' );


// add language text domain
add_action('init', 'cf_stripe_load_plugin_textdomain');

// filter to add processor to regestered processors array
add_filter('caldera_forms_get_form_processors', 'cf_stripe_register_processor');

//Enqueue JS script
add_action('wp_enqueue_scripts', 'cf_stripe_scripts');

//Submit form entry before Stripe checkout, then confirm or delete entry/email based on checkout result
add_action('caldera_forms_submit_process_start', 'cf_stripe_checkout_status_process', 10, 4);

//Capture Stripe charge when submission completed
add_filter('caldera_forms_submit_redirect_complete', 'cf_stripe_capture_charge', 10, 3);

//Clean transients
add_filter('caldera_forms_pre_render_form', 'cf_stripe_check_transient_status', 20, 3);

//Filter the URL to clean it before it return final notices
add_filter('caldera_forms_redirect_url', 'cf_stripe_url_redirections', 5, 3);

// load dependencies
include_once CF_STRIPE_PATH . 'vendor/autoload.php';


// pull in the functions file
include CF_STRIPE_PATH . 'includes/functions.php';



/**
 * Software Licensing
 */
// filter to initialize the license system
add_action('admin_init', 'cf_stripe_init_license');
/**
 * Initializes the licensing system
 *
 * @since 1.1.0
 */
function cf_stripe_init_license()
{

	$plugin = array(
		'name'		=>	'Caldera Forms Stripe Add-on',
		'slug'		=>	'caldera-forms-stripe-add-on',
		'url'		=>	'https://calderaforms.com/',
		'version'	=>	CF_STRIPE_VER,
		'key_store'	=>  'cfstripe_license',
		'file'		=>  CF_STRIPE_CORE
	);

	new \calderawp\licensing_helper\licensing($plugin);
}
