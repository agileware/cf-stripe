jQuery("document").ready(function($) {
  //Process only if cf_stripe localized data has been set
  if (typeof window.cf_stripe !== "undefined") {
    var cf_stripe = window.cf_stripe;

    //Check if $_get variable is set during form init, if it indicates starting, redirect to checkout immediately
    jQuery(document).on("cf.form.init", function(event, data) {
      //Set variables needed
      var url = new URL(window.location.href),
        stripe_status = url.searchParams.get("cf_stripe_status"),
        stripe_session_id = url.searchParams.get("cf_session"),
        form_id = url.searchParams.get("cf_form");

      //Start
      if (
        typeof stripe_status !== "undefined" &&
        stripe_status === "proceed" &&
        typeof stripe_session_id !== "undefined" &&
        typeof form_id !== "undefined"
      ) {
        cfStripeCheckout(stripe_session_id, form_id);
      } else if (
        typeof stripe_status !== "undefined" &&
        stripe_status === "processed" &&
        typeof form_id !== "undefined"
      ) {
        if (typeof cf_stripe[form_id] !== "undefined") {
          var $stripe_form;
          $("form").each( function(){
            if ( $( this ).data( "formId" ) === form_id ){
              $stripe_form = this;
            }
          });
          if (typeof $stripe_form !== "undefined") {
            $stripe_form.submit();
          } else {
            cfStripeReturnError();
          }
        }
      }
    });

    //Return an error afer a missing parameter
    function cfStripeReturnError() {
      //Stop processing State
      $(".caldera-grid").removeClass("cf_processing");
      //Look for translatable string and return error message
      if (typeof window.cf_stripe !== "undefined") {
        $("[id^='caldera_notices']")
          .addClass("alert alert-danger")
          .html(window.cf_stripe.errorText);
      } else {
        $("[id^='caldera_notices']")
          .addClass("alert alert-danger")
          .html("Error processing payment");
      }
    }
  }

  //Process to checkout or return error
  function cfStripeCheckout(stripe_session_id, form_id) {
    var stripe = Stripe(cf_stripe[form_id]["publishable_key"]);

    //Checkout redirection
    stripe
      .redirectToCheckout({
        sessionId: stripe_session_id
      })
      .then(function(result) {
        console.log(result);
        cfStripeReturnError();
      });
  }
});
