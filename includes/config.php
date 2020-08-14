<?php
/**
 * Stripe Processor setup template.
 *
 * @package   Caldera_Forms_Stripe
 * @author    David Cramer <david@digilab.co.za>
 * @license   GPL-2.0+
 * @link
 * @copyright 2014 David Cramer <david@digilab.co.za>
 */
if( class_exists( 'Caldera_Forms_Processor_UI' ) ){
	echo Caldera_Forms_Processor_UI::ssl_notice( 'Stripe for Caldera Forms' );
}

?>
<div class="caldera-config-group">
	<label><?php _e('Secret Key', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" id="stripe_secret" name="{{_name}}[secret]" value="{{secret}}">
	</div>
</div>

<div class="caldera-config-group">
	<label><?php _e('Publishable Key', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" id="stripe_publishable" name="{{_name}}[publishable]" value="{{publishable}}">
	</div>
</div>

<div class="caldera-config-group">
	<label><?php _e('Payment Type', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<label><input type="radio" class="field-config strip-payment-type_{{_id}}" id="stripe_type_single_{{_id}}" name="{{_name}}[type]" value="single" {{#is type value="single"}}checked="checked"{{/is}}{{#unless type}}checked="checked"{{/unless}}> <?php _e('Once-off', 'cf-stripe'); ?></label>
		<label><input type="radio" class="field-config strip-payment-type_{{_id}}" id="stripe_type_plan_{{_id}}" name="{{_name}}[type]" value="plan" {{#is type value="plan"}}checked="checked"{{/is}}> <?php _e('Recurring', 'cf-stripe'); ?></label>
	</div>
</div>


	<div class="caldera-config-group stripe-single-plan_{{_id}}" {{#unless type}}style="display:{{#is type value="single"}}block{{else}}none{{/is}};"{{/unless}}>
		<label><?php _e('Amount', 'cf-stripe'); ?></label>
		<div class="caldera-config-field">
			{{{_field slug="amount" type="calculation,hidden" exclude="system" required="true"}}}
		</div>
	</div>

	<div class="caldera-config-group stripe-recurring-plan_{{_id}}" style="display:{{#is type value="plan"}}block{{else}}none{{/is}};">
		<label><?php _e('Recurring Plan ID', 'cf-stripe'); ?></label>
		<div class="caldera-config-field">
			<input type="text" class="block-input field-config magic-tag-enabled" id="stripe_plan" name="{{_name}}[plan]" value="{{plan}}">
		</div>
	</div>


<div class="caldera-config-group">
	<label><?php _e('Currency', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<input type="text" class=" field-config required" id="stripe_currency" name="{{_name}}[currency]" value="{{#if currency}}{{currency}}{{else}}USD{{/if}}" style="width: 50px;">
		<a href="https://support.stripe.com/questions/which-currencies-does-stripe-support" target="_blank"><?php _e('See supported currencies', 'cf-stripe'); ?></a>.
	</div>
</div>

<div class="caldera-config-group">
	<label for="stripe_bitcoin">
		<?php _e('Allow Bitcoin', 'cf-stripe'); ?>
	</label>
	<div class="caldera-config-field">
		<input type="checkbox" class="field-config" id="stripe_bitcoin" name="{{_name}}[bitcoin]" {{#if bitcoin}}checked{{/if}}>
	</div>
</div>

<div class="caldera-config-group">
	<label><?php _e('Pay Label', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" id="stripe_plan" name="{{_name}}[label]" value="{{#if label}}{{label}}{{else}}Pay{{/if}}">
	</div>
</div>

<div class="caldera-config-group">
	<label for="add_verification">
		<?php _e('Additional Verification', 'cf-stripe'); ?>
	</label>
	<div class="caldera-config-field">
		<select class="block-input field-config" id="stripe-add_verification" name="{{_name}}[add_verification]" value="{{add_verification}}">
			<option value="none" {{#is add_verification value="none"}}selected{{/is}}><?php _e( 'None', 'cf-stripe' ); ?></option>
			<option value="zipCode" {{#is add_verification value="zipCode"}}selected{{/is}}><?php _e( 'Zip Code', 'cf-stripe' ); ?></option>
			<option value="address" {{#is add_verification value="address"}}selected{{/is}}><?php _e( 'Address', 'cf-stripe' ); ?></option>
		</select>
	</div>
	<p class="description">
		<?php _e( sprintf( 'Additional verification must be enabled at %1s', '<a href="https://dashboard.stripe.com/account/data" target="_blank" >https://dashboard.stripe.com/account/data</a>'), 'cf-stripe' ); ?>
	</p>
</div>


<div class="caldera-config-group">
	<label><?php _e( 'Image', 'cf-stripe'); ?> </label>
	<div class="caldera-config-field" id="{{_id}}_preview">
		{{#if file}}
			<span id="{{_id}}_filepreview"><img class="{{_id}}_filepreview" src="{{file}}" style="margin: 0px 12px 0px 0px; vertical-align: middle; width: 24px;">{{name}}&nbsp;&nbsp;</span><span class="{{_id}}_btn button button-small"><?php _e( 'Change Image', 'cf-stripe'); ?></span> <button type="button" class="button {{_id}}_btn_remove button-small"><?php _e( 'Remove Image', 'cf-stripe'); ?></button>
		{{else}}
		<button type="button" class="button {{_id}}_btn button-small"><?php _e( 'Select Image', 'cf-stripe'); ?></button>
		{{/if}}
	</div>
</div>
<input type="hidden" id="{{_id}}_file" class="block-input field-config" name="{{_name}}[file]" value="{{file}}">
<input type="hidden" id="{{_id}}_name" class="block-input field-config" name="{{_name}}[name]" value="{{name}}">


{{#script}}
//<script>
jQuery(document).on('click', '.{{_id}}_btn_remove', function(){

	jQuery('.{{_id}}_btn').html('<?php _e( 'Select Image', 'cf-stripe'); ?>');
	jQuery('#{{_id}}_filepreview').remove();
	jQuery('#{{_id}}_file').val('');
	jQuery('#{{_id}}_name').val('');
	jQuery(this).remove();

});
jQuery(document).on('click', '.{{_id}}_btn', function(){

	var button = jQuery(this);
	var frame = wp.media({
		title : '<?php _e( 'Select Image', 'cf-stripe'); ?>',
		multiple : false,
		button : { text : '<?php _e( 'Use Image', 'cf-stripe'); ?>' }
	});
	var preview = jQuery('#{{_id}}_preview');

	// Runs on select
	frame.on('select',function(){
		var objSettings = frame.state().get('selection').first().toJSON(),
			fid = jQuery('#{{_id}}_file'),
			nid = jQuery('#{{_id}}_name');
			//console.log(button.parent().parent());

		nid.val(objSettings.filename);
		//console.log(objSettings);
		var icon = '<img class="{{_id}}_filepreview" src="'+objSettings.icon+'" style="margin: 0px 12px 0px 0px; vertical-align: middle; width: 20px;">';
		if(objSettings.type === 'image'){
			if(objSettings.sizes.thumbnail){
				fid.val(objSettings.sizes.thumbnail.url);
				icon = '<img class="{{_id}}_filepreview image" src="'+objSettings.sizes.thumbnail.url+'" style="margin: 0px 12px 0px 0px; vertical-align: middle; width: 24px;">';
			}else if(objSettings.sizes.medium){
				fid.val(objSettings.sizes.medium.url);
				icon = '<img class="{{_id}}_filepreview" src="'+objSettings.sizes.medium.url+'" style="margin: 0px 12px 0px 0px; vertical-align: middle; width: 24px;">';
			}else if(objSettings.sizes.full){
				fid.val(objSettings.sizes.full.url);
				icon = '<img class="{{_id}}_filepreview" src="'+objSettings.sizes.full.url+'" style="margin: 0px 12px 0px 0px; vertical-align: middle; width: 24px;">';
			}
		}
		preview.html('<span id="{{_id}}_filepreview">' + icon+objSettings.filename+'&nbsp;&nbsp;</span><span class="{{_id}}_btn button button-small"><?php _e( 'Change Image', 'cf-stripe' ); ?></span> <button type="button" class="button {{_id}}_btn_remove button-small"><?php _e( 'Remove Image', 'cf-stripe'); ?></button>');

	});

	// Open ML
	frame.open();
});
{{/script}}

<div class="caldera-config-group">
	<label><?php _e('Item Name', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" id="stripe_name" name="{{_name}}[item]" value="{{item}}">
	</div>
</div>

<div class="caldera-config-group">
	<label><?php _e('Description', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" id="stripe_description" name="{{_name}}[description]" value="{{description}}">
	</div>
</div>


<div class="caldera-config-group">
	<label><?php _e('Email Field', 'cf-stripe'); ?></label>
	<div class="caldera-config-field">
		{{{_field slug="email" type="email" exclude="system" required="true"}}}
	</div>
</div>

<div class="caldera-config-group">
	<label for="stripe_remove_remember">
	<?php esc_html_e('Disable Remember', 'cf-stripe'); ?>
	</label>
	<div class="caldera-config-field">
	<input type="checkbox" class="field-config" id="stripe_remove_remember" aria-describedby="stripe_remove_remember-desc" name="{{_name}}[remove_remember]" {{#if remove_remember}}checked{{/if}} >
		<p id="stripe_remove_remember-desc"><?php esc_html_e( 'If checked, the "Remember Me?" option will be removed.', 'cf-stripe' ); ?></p>
</div>
</div>



{{#script}}
//<script>
	jQuery( function($){
		$(document).on('change', '.strip-payment-type_{{_id}}', function(){
			
			var single = $('#stripe_type_single_{{_id}}').is(':checked');

			if( single ){
				$('.stripe-single-plan_{{_id}}').show();
				$('.stripe-recurring-plan_{{_id}}').hide();
				$('#{{_id}}_amount').addClass('required');
				//$('#{{_id}}_email').removeClass('required').removeClass('has-error').prop('disabled', '');

			}else{
				$('.stripe-single-plan_{{_id}}').hide();
				$('.stripe-recurring-plan_{{_id}}').show();
				$('#{{_id}}_amount').removeClass('required');
				//$('#{{_id}}_email').addClass('required');
			}


		});

		$('.strip-payment-type_{{_id}}').trigger('change');
	});
{{/script}}








