<?php
/**
 * Stripe Processor Functions
 *
 * @package   Caldera_Forms_Stripe
 * @author    David Cramer <david@digilab.co.za>
 * @license   GPL-2.0+
 * @link
 * @copyright 2014 David Cramer <david@digilab.co.za>
 */





/**
 * Load the plugin text domain for translation.
 *
 * @since 1.0.0
 */
function cf_stripe_load_plugin_textdomain(){
	load_plugin_textdomain( 'cf-stripe', FALSE, CF_STRIPE_PATH . 'languages' );
}

/**
 * Registers the Stripe Processor
 *
 * @since 1.0.0
 * @param array		$processors		array of current regestered processors
 *
 * @return array	array of regestered processors
 */
function cf_stripe_register_processor( $processors ){
	$processors['stripe'] = array(
		"name"              =>  __( 'Stripe', 'cf-stripe' ),
		"description"       =>  __("Payment Processor", 'cf-stripe' ),
		"author"            =>  'Caldera Labs',
		"author_url"        =>  'http://CalderaLabs.org',
		"pre_processor"		=>  'cf_stripe_process_token',
		"processor"			=>  'cf_stripe_return_charge',
		"template"			=>	CF_STRIPE_PATH . 'includes/config.php',
		"icon"				=>	CF_STRIPE_URL . 'icon.png',
		"single"			=>	true,
		//"conditionals"		=>	false,
		"magic_tags"		=>	array(
			'ID',
			'live',
			'Amount',
			'Currency',
		)
	);
	return $processors;
}

/**
 * Creates and verifies a Stripe Token
 *
 * @since 1.0.0
 *
 *
 * @param array 	$config		processors config array
 * @param array 	$form		forms full config array
 *
 * @return mixed	returns error array or nothing if successful
 */
function cf_stripe_process_token($config, $form){
	global $transdata;

	if( empty( $_POST['stripetoken'] ) ){
		return array( 'type'=>'error', 'note' => __( 'No Stripe token found, please wait for the page to load fully and try again.', 'cf-stripe' ));
	}

	// Set your secret key: remember to change this to your live secret key in production
	// See your keys here https://dashboard.stripe.com/account
	\Stripe\Stripe::setApiKey($config['secret']);

	// Get the credit card details submitted by the form
	$token = $_POST['stripetoken'];

	/**
	 * Early return filter, allowing opportunity to use token and bail.
	 *
	 * @since 1.4.6
	 *
	 * @param bool|array $early_return Return true to not use defaults and not make error. Return an array for an error, or return false or null to contiunue like fefault
	 * @param array $config Processor configuration.
	 * @param array $form Form configuration.
	 */
	$early_return = apply_filters( 'cf_stripe_pre_payment', false, $token, $config, $form );
	if( true === $early_return ){
		return;
	}elseif ( is_array( $early_return ) ){
		return $early_return;
	}
	// Create the charge on Stripe's servers - this will charge the user's card
	try {

		if( $config['type'] == 'plan' ){

			/**
			 * Filter the arguments for a reoccurring plan before sending to Stripe.
			 *
			 * @since 1.3.1
			 *
			 * @param array $args Arguments to send to Stripe.
			 * @param array $config Processor configuration.
			 * @param array $form Form configuration.
			 */
			$args = apply_filters( 'cf_stripe_recurring_args', array(
				"source" => $token, // obtained from Stripe.js
				"plan" => trim( Caldera_Forms::do_magic_tags( $config['plan'] ) ),
				"email" => Caldera_Forms::get_field_data( $config['email'], $form )
			), $config, $form );
			$charge = \Stripe\Customer::create( $args );

			/**
			 * Runs after request to create a payment plan.
			 *
			 * Note: Runs on success or failure.
			 * Note: $charge->id has customer ID.
			 *
			 * @since 1.4.0
			 *
			 * @param object|\Stripe\Customer $charge Response object from API
			 * @param array $args Array of arguments used to create plan.
			 * @param array $config The processor's configuration.
			 * @param array $form The current form.
			 */
			do_action( 'cf_stripe_post_plan_create', $charge, $args, $config, $form  );


		}else{

			/**
			 * Filter the arguments for a single payment before sending to Stripe.
			 *
			 * @since 1.3.1
			 *
			 * @param array $args Arguments to send to Stripe.
			 * @param array $config Processor configuration.
			 * @param array $form Form configuration.
			 */
			$args = apply_filters( 'cf_stripe_charge_args', array(
				  "amount" => ( Caldera_Forms::get_field_data( $config['amount'], $form ) * 100 ), // amount in cents, again
				  "receipt_email" => Caldera_Forms::get_field_data( $config['email'], $form ),
				  "currency" => strtolower( $config['currency'] ),
				  "card" => $token,
				  "description" => $config['description'],
			  ), $config, $form );

			$charge = \Stripe\Charge::create( $args );

			/**
			 * Runs after request to create a single payment.
			 *
			 * Note: Runs on success or failure.
			 * Note: $charge->id has customer ID.
			 *
			 * @since 1.4.0
			 *
			 * @param object|\Stripe\Customer $charge Response object from API
			 * @param array $args Array of arguments used to create payment.
			 * @param array $config The processor's configuration.
			 * @param array $form The current form.
			 */
			do_action( 'cf_stripe_post_payment', $charge, $args, $config, $form  );

		}
	
	} catch (Exception $e) {
		return array( 'type'=>'error', 'note' => $e->getMessage() );

	}
	$transdata['stripe'] = $charge;
}

/**
 * Returns the Stripe payment data if successful
 *
 * @since 1.0.0
 *
 *
 * @param array 	$config		processors config array
 * @param array 	$form		forms full config array
 *
 * @return array	transaction data
 */
function cf_stripe_return_charge( $config, $form ){
	global $transdata;

	if( !empty( $transdata['stripe'] ) ){

		// is a recurring
		if( $config['type'] == 'plan' ){
			
			$return_charge = array(
				'ID'		=>	$transdata['stripe']->id,
				'live'		=>  ( $transdata['stripe']->livemode ? 'Yes' : 'No' ),
				'Plan'		=>	$transdata['stripe']->subscriptions->data[0]->plan->name,
				'Amount'	=>	$transdata['stripe']->subscriptions->data[0]->plan->amount / 100,
				'Currency'	=>	$transdata['stripe']->subscriptions->data[0]->plan->currency,
				'Interval'	=>	$transdata['stripe']->subscriptions->data[0]->plan->interval,
				'Count'		=>	$transdata['stripe']->subscriptions->data[0]->plan->interval_count,
				'Trial_Days'=>	$transdata['stripe']->subscriptions->data[0]->plan->trial_period_days,
				'Created'	=>	date( 'r', $transdata['stripe']->subscriptions->data[0]->plan->created ),
				'subscription_id' => $transdata['stripe']->subscriptions->data[0]->id,
			);

		}else{
			
			$return_charge = array(
				'ID'		=>	$transdata['stripe']->id,
				'live'		=>  ( $transdata['stripe']->livemode ? 'Yes' : 'No' ),
				'Amount'	=>	$transdata['stripe']->amount / 100,
				'Currency'	=>	$transdata['stripe']->currency,
				'Created'	=>	date( 'r', $transdata['stripe']->created ),
			);
		}

		/**
		 * Runs after a successful charge is made.
		 *
		 * Note: Use $return_charge[ 'ID' ] for the customer ID.
		 * Note: For recurring charges, use $return_charge[ 'subscription_id' ] for subscription plan ID.
		 *
		 * @since 1.4.0
		 *
		 * @param array $return_charge Data about the successful charge, returned from stripe.
		 * @param array $transdata Data used to create transaction.
		 * @param array $config The processor's configuration.
		 * @param array $form The current form.
		 */
		do_action( 'cf_stripe_post_successful_charge', $return_charge, $transdata, $config, $form );
		return $return_charge;

	}

}


/**
 * Adds stripe attributes to the form for baldrick ajax
 *
 * @since 1.0.0
 *
 *
 * @param array 	$atts		Current attributes array
 * @param array 	$form		forms full config array
 *
 * @return array	altered atributes array
 */
function cf_stripe_form_atts($atts, $form){

	// checks to see if there is a stripe proceeor on the form
	$stripe = Caldera_Forms::get_processor_by_type( 'stripe', $form);
	if( !empty( $stripe ) ){
		// add stripe scripts to page
		add_filter( 'caldera_forms_render_form', 'cf_stripe_embed_script', 10, 2);

	}

	return $atts;
}

/**
 * Adds stripe embeding scripts to page
 *
 * @since 1.0.0
 *
 * @param string 	$form_html	 		rendered form html
 * @param array 	$form_config		forms full config array
 *
 * @return string	altered form html
 */
function cf_stripe_embed_script($form_html, $form_config){
	// remove the filter to prevent repeat loading.
	remove_filter( 'caldera_forms_render_form', 'cf_stripe_embed_script' );

	//make sure we have baldrick if not in AJAX mode
	if(empty($form_config['form_ajax'])){
		Caldera_Forms_Render_Assets::enqueue_script( 'baldrick'  );
	}	

	$stripe = Caldera_Forms::get_processor_by_type( 'stripe', $form_config );
	$stripe = $stripe[ 0 ];
	return cf_stripe_inline_js( $stripe, $form_html, $form_config );

}

function cf_stripe_inline_js( $stripe, $form_html, $form_config ){
	if( empty( $stripe ) ){
		return $form_html;
	}

	if( empty( $stripe['runtimes'] ) ){
		return $form_html;
	}

	ob_start();
	?>
	<script src="https://checkout.stripe.com/checkout.js"></script>
	<script>
		if ( 'undefined' !== typeof StripeCheckout ) {
			var fileref = document.createElement( 'script' );
			fileref.setAttribute( 'type','text/javascript' );
			fileref.setAttribute( 'src', 'https://checkout.stripe.com/checkout.js' );
		}
	  var handler = StripeCheckout.configure({
	    key: '<?php echo $stripe['config']['publishable']; ?>',
	    //image: '/square-image.png',
	    token: function(token) {
	      jQuery( 'form.<?php echo $form_config['ID']; ?>' ).data( 'stripetoken', token.id).trigger( 'submit' );
	    }
	  });

	    // Open Checkout with further options
	    function stripe_init_payment(el, e){
			if( ! el.hasClass( "<?php echo $form_config[ 'ID' ]; ?>" )  ){
			    return true;
		    }

		    var fail = false;
		    <?php
		        global $is_safari;
		        if ( $is_safari ) { ?>
				    jQuery( el ).find( 'select, textarea, input' ).each(function(){
					    if( ! jQuery( this ).prop( 'required' )){

					    } else {
						    if ( ! jQuery( this ).val() ) {
							    fail = true;
							    alert( "<?php echo __( 'A required field is missing.', 'cf-stripe' ); ?>" );
							    return false;
						    }

					    }
				    });
		    <?php } ?>

		    if ( ! fail  ) {
			    <?php if( $stripe['config']['type'] == 'single' ){ ?>
			    var amount = jQuery( '[name="<?php echo $stripe['config']['amount']; ?>"]' ).val() * 100;
			    if( amount <= 0 ){
				    alert( '<?php _e( 'No amount set', 'cf-stripe' );?>' );
				    return false;
			    }

			    amount = Math.round( amount );

			    <?php } ?>
			    if( !jQuery( '.<?php echo $form_config['ID']; ?>' ).data( 'stripetoken' ) ){
				    handler.open({
					    <?php if( !empty( $stripe['config']['email'] ) ){ echo 'email: jQuery(\'[name="'. $stripe['config']['email'].'"]\' ).val(),'; } ?>
					    <?php if( !empty( $stripe['config']['label'] ) ){ ?>panelLabel: "<?php echo $stripe['config']['label']; ?>",<?php } ?>
					    <?php if( !empty( $stripe['config']['item'] ) ){ ?>name: "<?php echo $stripe['config']['item']; ?>",<?php } ?>
					    <?php if( !empty( $stripe['config']['description'] ) ){ ?>description: "<?php echo $stripe['config']['description']; ?>",<?php } ?>
					    <?php if( !empty( $stripe['config']['file'] ) ){ ?>image: '<?php echo $stripe['config']['file']; ?>',<?php } ?>
					    <?php if( $stripe['config']['type'] == 'single' ){ ?>amount: amount,<?php } ?>
					    currency: '<?php echo $stripe['config']['currency']; ?>',
					    <?php if( isset( $stripe['config']['bitcoin'] ) && $stripe['config']['bitcoin']  ){ echo 'bitcoin: true'; } else { echo 'bitcoin : false'; } ?>,
					    <?php if( isset( $stripe['config']['remove_remember'] ) && $stripe['config']['remove_remember']  ){ echo 'allowRememberMe: false,'; }  ?>
					    <?php if( isset ( $stripe[ 'config' ][ 'add_verification' ] ) && 'none' != $stripe[ 'config' ][ 'add_verification' ] ) { echo esc_attr( $stripe[ 'config' ][ 'add_verification' ] ) . ' : true,'; } ?>
					    closed : function(){
					         jQuery( 'form.<?php echo $form_config['ID']; ?>' ).find(':submit').attr( 'disabled', false );
					    }
				    });
				    return false;
			    }
			    return true;
		    }else{
			    return false;
		    }


		};
		<?php 
		if( empty( $form_config['form_ajax'] ) ){
			?>

			jQuery(document).on( 'submit', 'form.<?php echo $form_config['ID']; ?>', function(e){

				if( !jQuery( '.<?php echo $form_config['ID']; ?>' ).data( 'stripetoken' ) ){
					stripe_init_payment(this, e);
				}else{
					jQuery(this).append( '<input type="hidden" name="stripetoken" value="' + jQuery( '.<?php echo $form_config['ID']; ?>' ).data( 'stripetoken' ) + '">' );
				}
			});

			<?php
		}
		?>
	  // Close Checkout on page navigation
	  jQuery(window).on( 'popstate', function() {
	    handler.close();
	  });



	  jQuery( function( $ ){
	  	
	  	$.fn.baldrick.registerhelper('stripe', {
	  		params : function( params ){
				$(document).trigger( 'cf.add' );
				// check for field
				if( !$('[data-field="<?php echo $stripe['ID']; ?>"]').length ){
					return params;
				}
				var result = stripe_init_payment( params.trigger );
				if( true === result ){
					return params;
				}
	  			return false;
	  		}
	  	});
	  });

	</script>
	<?php
	$script = ob_get_clean();
        remove_filter( 'caldera_forms_render_field', 'cf_stripe_embed_script' );

        add_action('wp_footer', function() use ( $script ){
            echo $script;
        }, 10 );

	return $form_html;

}



if( ! is_admin() ){
	add_filter( 'caldera_forms_get_form', 'cf_stripe_build_conditionals' );
}
function cf_stripe_build_conditionals( $form ){
	$stripe = Caldera_Forms::get_processor_by_type( 'stripe', $form );
	$stripe = $stripe[0];
	if( empty( $stripe ) || empty( $stripe['conditions'] ) ){
		return $form;
	}

	if( $stripe['conditions']['type'] == 'use' ){
		$stripe['conditions']['type'] = 'show';
	}else{
		$stripe['conditions']['type'] = 'hide';
	}

	$form['layout_grid']['fields'][ $stripe['ID'] ] = '1:1';
	$form['fields'][ $stripe['ID'] ] = array(
		'ID' => $stripe['ID'],
		'type' => 'hidden',
		'label' => '',
		'slug' => $stripe['ID'],
		'conditions' => $stripe['conditions'],
		'caption' => '',
		'config' => array(
			'custom_class' => '',
			'default' => 'true'
		)
	);
	return $form;
}