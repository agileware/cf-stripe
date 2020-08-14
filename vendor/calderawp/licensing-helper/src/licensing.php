<?php
/**
 * Helper class to boot licenseing for CalderaWP Plugins. Create instance in cb hooked to admin init.
 *
 * @package   calderawp\licensing_helper
 * @author    Josh Pollock <Josh@JoshPress.net>
 * @license   GPL-2.0+
 * @link      
 * @copyright 2015 Josh Pollock
 */

namespace calderawp\licensing_helper;


class licensing {

	/**
	 * Create licensing
	 *
	 * @param array $plugin Must pass 'name', 'slug', 'url', 'version', 'key_store', & 'file'
	 */
	public function __construct( $plugin ) {

		if ( is_admin() || defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			include_once dirname( dirname( dirname( __FILE__ ) ) ) . '/dismissible-notice/src/functions.php';
		}

		// check if licence manager is installed
		if( ! class_exists( 'CalderaWP_License_Manager' ) && empty( $_GET[ 'action' ] ) ){
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$plugins = get_plugins();
			$found = false;
			foreach( $plugins as $plugin_file => $a_plugin ){
				if( $a_plugin['Name'] == 'CalderaWP License Manager' ){
					$found = $plugin_file;
					break;

				}
			}

			if ( is_admin() && empty( $plugin['silent'] ) ) {


				if( ! empty( $found ) ){

					// installed but not active
					$message = __(
							sprintf( 'To activate your %1s license, you must activate CalderaWP License Manager. <a href="%2s">Activate Now</a>',
								$plugin[ 'name' ],
								wp_nonce_url( self_admin_url( 'plugins
						.php?action=activate&plugin=' . urlencode( $found ) ), 'activate-plugin_' . $found )
							), 'caldera-easy-queries' );
						if( function_exists( 'caldera_warnings_dismissible_notice' ) ){
							echo caldera_warnings_dismissible_notice( $message, true, 'activate_plugins', $plugin[ 'key_store' ] . '_nag' );
						}
					return;

				}else{
					if( function_exists( 'caldera_warnings_dismissible_notice' ) ){
						// not installed
						$message = __(
						sprintf(
							'To activate your %1s license, you must install CalderaWP License Manager. <a href="%2s">Install Now</a>',
							$plugin[ 'name' ],
							wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=calderawp-license-manager' ), 'install-plugin_calderawp-license-manager' )
						), 'caldera-easy-queries' );
						
						echo caldera_warnings_dismissible_notice( $message, true, 'activate_plugins', $plugin[ 'key_store' ] . '_nag' );
					}

					

				}

			}

		}

		if( function_exists( 'cwp_license_manager_register_licensed_product' ) ){

			$product_params = array(
				'name'		=>	$plugin[ 'name' ],
				'slug'		=>	$plugin[ 'slug' ],
				'url'		=>	$plugin[ 'url' ],
				'updater'	=>	'edd',
				'version'	=>	$plugin[ 'version' ],
				'key_store'	=>  $plugin[ 'key_store' ],
				'file'		=>  $plugin[ 'file' ]
			);

			cwp_license_manager_register_licensed_product( $product_params );
			if( ! cwp_license_manager_is_product_licensed( $product_params['name'] ) && ( empty( $_GET['page'] ) || $_GET['page'] !== 'calderawp_license_manager' ) && empty( $plugin['silent'] ) ){

				$message = __(
					sprintf( 'Please activate your %1s license using <a href="%1s">CalderaWP License Manager</a>.',
						$plugin[ 'name' ],
						add_query_arg( 'page', 'calderawp_license_manager', self_admin_url( 'wp-admin/admin.php' ) )					)
				);
				if( function_exists( 'caldera_warnings_dismissible_notice' ) ){
					echo caldera_warnings_dismissible_notice( $message, true, 'activate_plugins', $plugin[ 'key_store' ] . '_nag' );
				}
			}
		}

		/**
		 * Setup the download of the installer for Calderawp License Manager to be sourced from Github
		 *
		 * @since 0.0.1
		 *
		 * @return    object A plugin object to be installed
		 */
		add_filter( 'plugins_api', function( $obj, $action, $args  ){
			if( $action !== 'plugin_information' || $args->slug !== 'calderawp-license-manager' ){
				return $obj;

			}

			$plugin = new \stdClass();
			$plugin->name 			= 'CalderaWP License Manager';
			$plugin->slug 			= 'calderawp-license-manager';
			$plugin->version		= '1.0.0';
			$plugin->download_link	= 'https://github.com/CalderaWP/calderawp-license-manager/archive/master.zip';
			$plugin->plugin			= 'calderawp-license-manager/core.php';

			return $plugin;

		}, 11, 3 );

	}

}
