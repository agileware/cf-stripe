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
function cf_stripe_load_plugin_textdomain()
{
	load_plugin_textdomain('cf-stripe', FALSE, CF_STRIPE_PATH . 'languages');
}

/**
 * Registers the Stripe Processor
 *
 * @since 1.0.0
 * @param array		$processors		array of current regestered processors
 *
 * @return array	array of regestered processors
 */
function cf_stripe_register_processor($processors)
{
	$processors['stripe'] = array(
		"name"              =>  __('Stripe', 'cf-stripe'),
		"description"       =>  __("Payment Processor", 'cf-stripe'),
		"author"            =>  'Caldera Labs',
		"author_url"        =>  'http://CalderaLabs.org',
		"processor"			=>  'cf_stripe_return_charge',
		"template"			=>	CF_STRIPE_PATH . 'includes/config.php',
		"icon"				=>	CF_STRIPE_URL . 'icon.png',
		"single"			=>	true,
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
 * Creates the Stripe session
 *
 * @since 1.4.8
 *
 *
 * @param array 	$config		processors config array
 * @param array 	$form		forms full config array
 * @param array $params used to set the Stripe session
 * @param string $url base url for Stripe redirection after successful or failed checkout
 *
 * @return mixed	returns error array or Stripe Session
 */
function cf_stripe_process($config, $form, $url)
{
	global $transdata;

	// Set your secret key: remember to change this to your live secret key in production
	// See your keys here https://dashboard.stripe.com/account
	\Stripe\Stripe::setApiKey($config['secret']);

	//Prepare data for Stripe session
	$success_url = add_query_arg(['cf_stripe_status' 	=> 'processed'], $url);
	$cancel_url = add_query_arg(['cf_stripe_status' 	=> 'canceled'], $url);
	$email_address = !empty($config['email']) ? Caldera_Forms::get_field_data($config['email'], $form) : "";
	$amount = (Caldera_Forms::get_field_data($config['amount'], $form) * 100);
	$name =  !empty($config["item"]) ? Caldera_Forms::do_magic_tags($config["item"]) : $form["name"];
	$description =  !empty($config["description"]) ? Caldera_Forms::do_magic_tags($config["description"]) : __('Product description', 'cf-stripe');
	$plan = !empty($config['plan']) ? trim(Caldera_Forms::do_magic_tags($config['plan'])) : '';
	$currency = isset($config["currency"]) ? $config["currency"] : "";
	$image = isset($config["file"]) ? [$config["file"]] : [];

	/**
	 * Early return filter, can be used as  Pre-payment action or interupt the Stripe process returning true
	 *
	 * @since 1.4.6 @updated 1.4.8
	 *
	 * @param bool|array $early_return Return true to not use defaults and not make error. Return an array for an error, or return false or null to contiunue like fefault
	 * @param array $config Processor configuration.
	 * @param array $form Form configuration.
	 */
	$early_return = apply_filters('cf_stripe_pre_payment', false, $config, $form);
	if (true === $early_return) {
		return;
	} elseif (is_array($early_return)) {
		return $early_return;
	}


	// Create a Stripe session to be used in Checkout ( Capture of successful checkout will happen after entry is saved on sconf for submission )
	try {

		if ($config['type'] === 'plan') {

			//Stripe checkout settings
			$checkout_settings = [
				"payment_method_types" => ["card"],
				"subscription_data" => [
					"items" => [[
						"plan" => $plan,
					]],
				],
				"customer_email"	=> $email_address,
				"locale"	=> "auto",
				"success_url" =>  $success_url,
				"cancel_url" => $cancel_url
			];

			/**
			 * Filter the arguments for a reoccurring plan before sending to Stripe.
			 *
			 * @since 1.3.1 @updated 1.4.8
			 *
			 * @param array $checkout_settings Arguments to send to Stripe.
			 * @param array $config Processor configuration.
			 * @param array $form Form configuration.
			 */
			$args = apply_filters('cf_stripe_recurring_args', $checkout_settings, $config, $form);

			//Create Stripe session
			$session = \Stripe\Checkout\Session::create($args);

			/**
			 * Runs after request to create a payment plan.
			 *
			 * Note: Runs on success or failure.
			 * Note: $charge->id has customer ID.
			 *
			 * @since 1.4.0 @updated 1.4.8
			 *
			 * @param object|\Stripe\Session $session Response object from API
			 * @param array $args Array of arguments used to create plan.
			 * @param array $config The processor's configuration.
			 * @param array $form The current form.
			 */
			do_action('cf_stripe_post_plan_create', $session, $args, $config, $form);
		} else {

			//Stripe checkout settings
			$checkout_settings = [
				"payment_method_types" => ["card"],
				"payment_intent_data"	=> [
					"capture_method"	=>	"manual",
					"setup_future_usage" => "off_session"
				],
				"line_items" => [[
					"amount" => $amount,
					"currency" => $currency,
					"quantity" => 1,
				]],
				"customer_email"	=> $email_address,
				"locale"	=> "auto",
				"success_url" =>  $success_url,
				"cancel_url" => $cancel_url
			];

			if(!empty($description)){
				$checkout_settings["line_items"][0]["description"] = $description;
			}
			if(!empty($name)){
				$checkout_settings["line_items"][0]["name"] = $name;
			}
			if(!empty($image[0])){
				$checkout_settings["line_items"][0]["images"] = [$image];
			}
			
			/**
			 * Filter the arguments for a single payment before sending to Stripe.
			 *
			 * @since 1.3.1 @updated 1.4.8
			 *
			 * @param array $checkout_settings Arguments to send to Stripe.
			 * @param array $config Processor configuration.
			 * @param array $form Form configuration.
			 */
			$args = apply_filters('cf_stripe_charge_args', $checkout_settings, $config, $form);

			//Create Stripe session
			$session = \Stripe\Checkout\Session::create($args);

			/**
			 * Runs after request to create a single payment.
			 *
			 * Note: Runs on success or failure.
			 * Note: $charge->id has customer ID.
			 *
			 * @since 1.4.0 @updated 1.4.8
			 *
			 * @param object|\Stripe\Session $session Response object from API
			 * @param array $args Array of arguments used to create payment.
			 * @param array $config The processor's configuration.
			 * @param array $form The current form.
			 */
			do_action('cf_stripe_post_payment', $session, $args, $config, $form);
		}

		//Save session ID as transient
		$transdata['cf_stripe_session_id'] = $session->id;
		//Return session ID
		return $session;
	} catch (\Stripe\Error\Card $e) {

		return $e;
	} catch (\Stripe\Error\RateLimit $e) {
		// Too many requests made to the API too quickly
		return [
			'type'	=>	'error',
			'error'	=>	$e
		];
	} catch (\Stripe\Error\InvalidRequest $e) {
		// Invalid parameters were supplied to Stripe's API
		return [
			'type'	=>	'error',
			'error'	=>	$e
		];
	} catch (\Stripe\Error\Authentication $e) {
		// Authentication with Stripe's API failed
		// (maybe you changed API keys recently)
		return [
			'type'	=>	'error',
			'error'	=>	$e
		];
	} catch (\Stripe\Error\ApiConnection $e) {
		// Network communication with Stripe failed
		return [
			'type'	=>	'error',
			'error'	=>	$e
		];
	} catch (\Stripe\Error\Base $e) {
		// Display a very generic error to the user, and maybe send
		// yourself an email
		return [
			'type'	=>	'error',
			'error'	=>	$e
		];
	} catch (Exception $e) {
		// Something else happened, completely unrelated to Stripe
		return [
			'type'	=>	'error',
			'error'	=>	$e
		];
	}
}

/**
 * Capture Stripe charge if set in $transdata after form totally saved entry and sent email
 *
 * @since 1.4.8
 * 
 * @param string $referrer url for redirection
 * @param array $form form configuration
 * @param int $process_id process ID
 *
 * @return string @referrer url for redirection at the end of submission, we use this filter for its place in the process but don't alter the url.
 */
function cf_stripe_capture_charge($referrer, $form, $process_id)
{
	//Run if passes conditions
	if (cf_stripe_processor_condition($form)) {
		
		//Check if a Stripe processor is set in the form and retrieve Stripe processor data
		$config = cf_stripe_processor_config($form['ID']);

		//Create capture for single payment only ( Subscriptions are already captured, Stripe doesn't allow capture postponed for subscriptions )
		if ($config['type'] === 'single') {

			// Set your secret key: remember to change this to your live secret key in production
			// See your keys here https://dashboard.stripe.com/account
			\Stripe\Stripe::setApiKey($config['secret']);

			//Get transients
			$entry_transient = Caldera_Forms_transient::get_transient('cf_stripe_' . $_GET['cf_entry_id']);
			$status_transient = Caldera_Forms_transient::get_transient('cf_stripe_status');

			//Check for an active Stripe session
			if (!empty($entry_transient) && $status_transient === "submitted") {

				//Retrieve session ID from saved submission data
				$entry_transient = Caldera_Forms_transient::get_transient('cf_stripe_' . $_GET['cf_entry_id']);

				//Retrieve Stripe session
				$session = \Stripe\Checkout\Session::retrieve($entry_transient['session_id']);

				//Retrieve payment intent and capture it if status require capture
				$payment_intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);

				/**
				 * Allows to set capture method manually
				 * 
				 * @since 1.4.11
				 * 
				 * @return boolean that will be checked in next conditional statement to perform or not the capture
				 */
				$capture = apply_filters('caldera_forms_stripe_capture_payment',  true );

				if ($payment_intent->status === "requires_capture" && $capture) {

					/**
					 * Allow action before capturing charge for single payments
					 * 
					 * @since 1.4.8
					 * 
					 * @param string $referrer url to be returned by current filter
					 * @param array $config Stripe processor settings
					 * @param array $form Form settings
					 * @param array $entry_transient data saved for current entry
					 * @param string $status_transient Stripe process status defined in transient
					 * @param object $session returned by Stripe API
					 * @param object $payment_intent returned by Stripe checkout 
					 */
					do_action('cf_stripe_pre_single_capture', $referrer, $config, $form, $entry_transient, $status_transient, $session, $payment_intent);

					/**
					 * Allow to filter the time the transient is kept in the system
					 * 
					 * @since 1.4.11
					 * 
					 * @param array $form Form settings
					 * @param array $entry_transient data saved for current entry
					 * @param string $status_transient Stripe process status defined in transient
					 * 
					 * @return int duration in seconds
					 */
					$duration = apply_filters("cf_stripe_transients_capture_duration", cf_stripe_minutes_to_seconds( 5 ), $form, $entry_transient, $status_transient);

					try {

						//Set the status transient to captured
						$capture = $payment_intent->capture();
						Caldera_Forms_transient::set_transient('cf_stripe_status', 'captured', $duration);
						
						
					} catch (Exception $e) {

						//Capture Failed, set the notice
						add_filter('caldera_forms_render_notices', function ($notices) use ($e) {

							if (!empty($e->message) && current_user_can('administrator')) {
								$notices['error']['note'] = $e->message;
							} else {
								$notices['error']['note'] = __('Payment capture failed. Form submitted without charge. Try again or contact site owner.', 'caldera-forms');
							}

							return $notices;
						});

						//Set the status transient to uncaptured
						Caldera_Forms_transient::set_transient('cf_stripe_status', 'uncaptured', $duration);
					}
					
					/**
					 * Allow action after capturing charge for single payments
					 * 
					 * @since 1.4.8
					 * 
					 * @param string $referrer url to be returned by current filter
					 * @param array $config Stripe processor settings
					 * @param array $form Form settings
					 * @param array $entry_transient data saved for current entry
					 * @param string $status_transient Stripe process status defined in transient
					 * @param object $session returned by Stripe API
					 * @param object $payment_intent returned by $payment_intent->capture 
					 */
					do_action('cf_stripe_post_single_capture', $referrer, $config, $form, $entry_transient, $status_transient, $session, $capture);
				}
			}
		}
	}

	return $referrer;
}

/**
 * Enqueues Stripe Checkout and the CF-Stripe JS
 *
 * @since 1.4.8
 * 
 */
function cf_stripe_scripts()
{

	if (file_exists(CF_STRIPE_PATH . "assets/cf-stripe.js")) {

		//enqueue new Stripe Checkout
		wp_enqueue_script('stripe-v3', "//js.stripe.com/v3", [], CF_STRIPE_VER, true);
		wp_enqueue_script('cf-stripe', CF_STRIPE_URL . "assets/cf-stripe.js", ["jquery"], CF_STRIPE_VER, true);

		//Set data for client Side process of Stripe Checkout
		$localize_stripe_data = [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'	=> wp_create_nonce('cf_stripe'),
			'errorText'	=>	__('Error processing payment', 'cf-stripe')
		];

		//Get all forms and look for Stripe processor set in a form
		$forms = Caldera_Forms_Forms::get_forms();
		foreach ($forms as $form) {
			if (cf_stripe_processor_config($form)) {
				//If a Stripe processor is set in the form add an entry to be attached to localized data and insert publishable key
				$config = cf_stripe_processor_config($form);
				$localize_stripe_data[$form]['publishable_key'] = isset($config['publishable']) ? $config['publishable'] : "";
			}
		}

		//Allow localized data to be filtered
		$localize_stripe_data = apply_filters('cf_stripe_localized_data', $localize_stripe_data);
		wp_localize_script('cf-stripe', 'cf_stripe', $localize_stripe_data);
	}
}

/**
 * Filter form at 'caldera_forms_submit_complete' when the entry was created and status set as "pending"
 * Reload the form to go back to client if the form has a Stripe processor and stripe token transient isn't set yet
 * 
 * @since 1.4.8
 * 
 * @param array $form
 * @param array $referrer
 * @param string $process_id
 * @param string $entry_id
 * 
 * @return void It redirects to reload form or nothing to proceed with form submission
 * 
 */
function cf_stripe_checkout_status_process($form, $referrer, $process_id, $entry_id)
{
	global $transdata;

	//Check if a Stripe processor is set in the form and retrieve Stripe processor data
	$stripe_processor_data = Caldera_Forms::get_processor_by_type('stripe', $form);
	if ($stripe_processor_data) {
		$config = $stripe_processor_data[0]['config'];
	}

	//Only run if passes conditionals
	if (cf_stripe_processor_condition($form)) {

		$status = Caldera_Forms_transient::get_transient('cf_stripe_status');
		$url = $transdata['data']['_wp_http_referer_true'];

		//Check Stripe status
		if (!empty($config) && empty($status)) {

			//If the form is submitted after a cancelled or errored checkout then stop submission and return error
			if ($status === "canceled") {

				//Session Failed, set the notice
				add_filter('caldera_forms_render_notices', function ($notices) {
					//Unset success message
					unset($notices['success']['note']);
					$notices['error']['note'] = __('Payment was not completed. Try again or contact site owner.', 'caldera-forms');

					return $notices;
				});

				//Set error data
				$transdata['error'] = true;
				$query_str = [
					'cf_er' => $process_id
				];

				//Interupt submission
				$canceled_referrer = add_query_arg($query_str, $referrer['path']);
				Caldera_Forms::form_redirect('error', $canceled_referrer, $form, $process_id);
			} else {

				//Set query parameters
				$cf_stripe_checkout_nonce = wp_create_nonce("cf_stripe_checkout_nonce");

				//Set $params for success and error return URLs
				$params = [
					'cf_stripe_checkout_nonce' => $cf_stripe_checkout_nonce,
					'cf_entry_id' => $entry_id,
					'cf_form'	=> $form['ID']
				];
				$params = array_map('rawurlencode', $params);
				$url = add_query_arg($params, $url);

				//Stripe Process
				$session = cf_stripe_process($config, $form, $url);

				if (!empty($session->id)) {

					//Set CF to redirect to same page with query parameters set and $transdata saved

					//Set transient to be collected on checkout callback
					$transients = [
						'transdata'		=> $transdata,
						'session_id'	=> $session->id,
						'process_id'	=> $transdata['transient'],
						'form'	=>	$form,
						'checkout_nonce'	=>	$cf_stripe_checkout_nonce,
						'url'	=> $url
					];

					/**
					 * Allow to filter the time the transient is kept in the system
					 * 
					 * @since 1.4.11
					 * 
					 * @param array $form Form settings
					 * @param string $entry_id ID of entry being stored
					 * @param array $transients data saved for current entry
					 * 
					 * @return int duration in seconds
					 */
					$duration = apply_filters("cf_stripe_transients_session_duration", cf_stripe_minutes_to_seconds( 5 ), $form, $entry_id, $transients );

					//Create transients
					Caldera_Forms_transient::set_transient('cf_stripe_' . $entry_id, $transients, $duration);
					Caldera_Forms_transient::set_transient('cf_stripe_status', 'proceed', $duration);

					//Set and start redirection to current url to trigger client side again and use Stripe.redirectToCheckout()
					$args = [
						'cf_stripe_status' => 'proceed',
						'cf_session'	=> $session->id,
						'cf_form'	=> $form['ID']
					];
					$args = array_map('rawurlencode', $args);
					$url = add_query_arg($args, $url);

					Caldera_Forms::form_redirect('complete', $url, $form, $process_id);
				} else if ($session['type'] === "error") {


					//Session Failed, set the notice
					add_filter('caldera_forms_render_notices', function ($notices) use ($session) {
						//Unset success message
						unset($notices['success']['note']);
						//Set error message based on error returned by stripe for admins if not empty
						if (!empty($session['error']->message) && current_user_can('administrator')) {
							$notices['error']['note'] = $session['error']->message;
						} else {
							$notices['error']['note'] = __('Payment session failed. Try again or contact site owner.', 'caldera-forms');
						}

						return $notices;
					});

					//Set error data
					$transdata['error'] = true;
					$query_str = [
						'cf_er' => $process_id
					];

					//Interupt submission
					$url = add_query_arg($query_str, $url);
					Caldera_Forms::form_redirect('error', $url, $form, $process_id);
				}
			}
		}
	}
}


/**
 * Check Stripe status on form init and delete if it's processed ( Once Checkout is processed the form will load after the end of submission );
 * 
 * @since 1.4.8
 * 
 * @param string $html Form's html
 * @param int $entry_id Entry ID
 * @param array $form Form configuration
 * 
 * @return string of html to render the form
 * 
 */
function cf_stripe_check_transient_status($html, $entry_id, $form)
{

	//Check if form has a Stripe Processor
	if (cf_stripe_processor_config($form['ID'])) {

		//Check if a status query parameter is set
		if (!empty($_GET['cf_stripe_status'])) {

			//Get status from transient and query parameter
			$transient_status = Caldera_Forms_transient::get_transient('cf_stripe_status');
			$get_status = $_GET['cf_stripe_status'];

			/**
			 * Allow to filter the time the transient is kept in the system
			 * 
			 * @since 1.4.11
			 * 
			 * @param array $form Form settings
			 * @param string $transient_status status set via transients
			 * @param string $get_status status set in query parameters
			 * 
			 * @return int duration in seconds
			 */
			$duration = apply_filters("cf_stripe_transients_status_duration", cf_stripe_minutes_to_seconds( 5 ), $form, $transient_status, $get_status);

			//Check if an old transient is still saved and update its status
			if ($transient_status === "proceed" && "proceed" === $get_status) {

				//Set the form to be in process state
				cf_stripe_pocess_animation();

				//Set stripe status transient as processed
				Caldera_Forms_transient::set_transient('cf_stripe_status', 'processed', $duration);
			} else if ($transient_status === "processed" && "processed" === $get_status) {

				//Set the form to be in process state
				cf_stripe_pocess_animation();

				//Retrieve submission data set before checkout
				$processed_data = Caldera_Forms_transient::get_transient('cf_stripe_' . $_GET['cf_entry_id']);

				if(!empty($processed_data)){
					//Set data as default to pass validation
					add_filter('caldera_forms_render_get_field', function ($field, $form) use ($processed_data) {
						if (cf_stripe_processor_config($form['ID']) && isset($processed_data['transdata']['data'][$field['ID']])) {
							if($field["type"] === "radio" || $field["type"] === "toggle_switch" ){
								foreach( $field['config']['option'] as $index => $data){
									if(isset($data['value']) && $data['value'] == $processed_data['transdata']['data'][$field['ID']]){
										$field['config']['default'] = $index;
									}
								}
							} else {
								$field['config']['default'] = $processed_data['transdata']['data'][$field['ID']];
							}
						}
						return $field;
					}, 10, 2);
					//Set stripe status transient as submitted
					Caldera_Forms_transient::set_transient('cf_stripe_status', 'submitted', $duration);

				} else {

					//Delete stripe status transient as previous failure
					Caldera_Forms_transient::delete_transient('cf_stripe_status');
					
					//Transient data couldn't be retrieved print an error
					if ( current_user_can( 'manage_options' ) ) {
						//Error for admins
						return '<div class="caldera-grid"><div id="caldera_notices_1">
						<div class=" alert alert-error">'
						. __('Process error. Transients data was not retrieved and form submission failed.', 'cf-stripe') . 
						'</br>'
						. __('At this stage, payments are not captured but subscriptions were charged.', 'cf-stripe') .
						'</br><a href="' . get_permalink() .'">'
						. __('Reload Form', 'cf-stripe') .
						'</a></div></div>';
					} else {
						//Error for non-admins
						return '<div class="caldera-grid"><div id="caldera_notices_1">
						<div class=" alert alert-error">'
						. __('Process error. Contact Site Administrator.', 'cf-stripe') . 
						'</br><a href="' . get_permalink() .'">'
						. __('Reload Form', 'cf-stripe') .
						'</a></div></div>';
					}
					
				}
				
			} else if ($transient_status === "submitted" && "submitted" === $get_status || $transient_status === "captured") {

				//Delete transient once form is submitted for subscriptions  or captured for single payments
				Caldera_Forms_transient::delete_transient('cf_stripe_status');
			} else if ($transient_status === "processed" && "canceled" === $get_status) {

				//Set stripe status transient as canceled
				Caldera_Forms_transient::delete_transient('cf_stripe_status');
				//Delete data transient
				if(isset($_GET['cf_entry_id'])){
					Caldera_Forms_transient::delete_transient('cf_stripe_' . $_GET['cf_entry_id']);
				}
			}
		} else {
			//Delete transient ( in case there is one ) if no status query parameter found
			Caldera_Forms_transient::delete_transient('cf_stripe_status');
			//Delete data transient
			if(isset($_GET['cf_entry_id'])){
				Caldera_Forms_transient::delete_transient('cf_stripe_' . $_GET['cf_entry_id']);
			}
		}
	}

	return $html;
}

/**
 * Remove query parameters when submission ended
 * 
 * @since 1.4.8
 * 
 * @param string $url for final redirection with query parameters
 * 
 * @return string $url $url for final redirection without query parameters if stripe submission eneded
 */

function cf_stripe_url_redirections($url, $form, $process_id)
{

	//Find if cf_stripe_status is defined and is set to submitted or captured, if true the remove query parameters
	$transient_status = Caldera_Forms_transient::get_transient('cf_stripe_status');
	$statuses = ["submitted", "captured", "canceled"];
	if (!empty($transient_status) && in_array($transient_status, $statuses)) {
		$params = ['cf_form', 'cf_entry_id', 'cf_ee', 'cf_et', 'cf_stripe_status', 'cf_stripe_checkout_nonce'];
		$url = remove_query_arg($params, $url);
	}

	return $url;
}


/**
 * Enable processing animation when form reloads and comes back from chckout
 * 
 * @since 1.4.8
 * 
 * @return void add the cf_processing class to the form to trigger the loading animation
 */
function cf_stripe_pocess_animation()
{

	add_filter('caldera_forms_render_form_wrapper_classes', function ($form_wrapper_classes) {

		$form_wrapper_classes[] = 'cf_processing';

		return $form_wrapper_classes;
	}, 10);
}

/**
 * Check if form has a Stripe processor
 * 
 * @since 1.4.8
 * 
 * @param string @form_id
 * 
 * @return array of Stripe processor config or false
 */
function cf_stripe_processor_config($form_id)
{

	//Check if a Stripe processor is set in the form and retrieve Stripe processor data
	$stripe_processor_config = Caldera_Forms::get_processor_by_type('stripe', $form_id);
	if ($stripe_processor_config) {
		$stripe_processor_config = $stripe_processor_config[0]['config'];
	}

	return $stripe_processor_config;
}

/**
 * Check processor conditions
 * 
 * @since 1.4.9
 * 
 * @param array @form settings
 * 
 * @return bool Pass or not conditions
 */
function cf_stripe_processor_condition($form)
{
	//Set default 
	$stripe_processor_condition = true;

	///Check if a Stripe processor is set in the form and retrieve Stripe processor condition
	$stripe_processor = Caldera_Forms::get_processor_by_type('stripe', $form['ID']);
	if ($stripe_processor[0]['conditions']) {
		$stripe_processor_condition = Caldera_Forms::check_condition($stripe_processor[0]['conditions'], $form);
	}
	
	return $stripe_processor_condition;
}

/**
 * Returns the Stripe payment data if successful
 *
 * @since 1.0.0 @updated 1.4.8
 *
 *
 * @param array 	$config		processors config array
 * @param array 	$form		forms full config array
 *
 * @return array	transaction data
 */
function cf_stripe_return_charge($config, $form)
{
	global $transdata;

	if (cf_stripe_processor_config($form['ID']) &&  !empty($_GET['cf_entry_id'])) {

		//Retrieve submission data set before checkout
		$processed_data = Caldera_Forms_transient::get_transient('cf_stripe_' . $_GET['cf_entry_id']);
		$config = cf_stripe_processor_config($form['ID']);

		// Set your secret key: remember to change this to your live secret key in production
		// See your keys here https://dashboard.stripe.com/account
		\Stripe\Stripe::setApiKey($config['secret']);

		//Retrieve Stripe session
		$session = \Stripe\Checkout\Session::retrieve($processed_data['session_id']);

		if (!empty($processed_data)) {

			// is a recurring
			if ($config['type'] == 'plan' && !empty($session->subscription)) {

				$subscription = \Stripe\Subscription::retrieve($session->subscription);

				if (!empty($subscription)) {
					$return_charge = array(
						'ID'		=>	$session->subscription,
						'live'		=> ($subscription->livemode ? 'Yes' : 'No'),
						'Plan'		=>	$subscription->plan->nickname,
						'Amount'	=>	$subscription->plan->amount / 100,
						'Currency'	=>	$subscription->plan->currency,
						'Interval'	=>	$subscription->plan->interval,
						'Count'		=>	$subscription->plan->interval_count,
						'Trial_Days' =>	$subscription->plan->trial_period_days,
						'Created'	=>	date('r', $subscription->plan->created),
						'subscription_id' => $subscription->id,
					);
				}
			} else if ($config['type'] == 'single' && !empty($session->payment_intent)) {

				$payment_intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);

				if (!empty($payment_intent)) {
					$return_charge = array(
						'ID'		=>	$session->payment_intent,
						'live'		=> ($payment_intent->livemode ? 'Yes' : 'No'),
						'Amount'	=>	$payment_intent->amount / 100,
						'Currency'	=>	$payment_intent->currency,
						'Created'	=>	date('r', $payment_intent->created),
					);
				}
			}
		}
	}

	if (!empty($return_charge)) {

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
		do_action('cf_stripe_post_successful_charge', $return_charge, $transdata, $config, $form);
		return $return_charge;
	}
}

/**
 * Returns the duration a Transient will be valid for
 *
 * @since 1.4.11
 *
 * @param int $minutes number of minutes 
 *
 * @return int number of seconds equivalent to the minutes set
 */
function cf_stripe_minutes_to_seconds( $minutes ) {
	return $minutes * 60;
}
