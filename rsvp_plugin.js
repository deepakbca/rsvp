jQuery(document).ready(function(){
	jQuery.validator.addMethod("customNote", function(value, element) {
    if((jQuery("#additionalRsvp").val() > 0) && (jQuery("#note").val() == "")) {
      return false;
    }

    return true;
  }, "<br />Please enter an email address that we can use to contact you about the extra guest.  We have to keep a pretty close eye on the number of attendees.  Thanks!");
						
	jQuery("#rsvpForm").validate({
		rules: {
			note: "customNote",
			attendeeFirstName:      "required", 
			attendeeLastName:       "required",
			mainEmail:       "required",
			mainRsvp: "required",
			mainquestion1: "required",
		},
		messages: {
			note: "<br />If you are adding additional RSVPs please enter your email address in case we have questions",
			attendeeFirstName:      "<font color=\"red\"><b>Please enter a first name</font></b>",
			attendeeLastName:       "<font color=\"red\"><b>Please enter a last name</font></b>",
			mainEmail:      	"<font color=\"red\"><b>Please specify a valid email address</font></b>",
		},

	});
  

	jQuery('[name^="attending"]').each(function(index, element) {
							jQuery(element).rules("add", "required");
						});

	jQuery('[name^="a"][name*="question"]').each(function(index, element) {
							jQuery(element).rules("add", "required");
						});



  /* First step, where they search for a name */
  jQuery("#rsvp").validate({
    rules: {
      firstName: "required",
      lastName: "required"
    }, 
    messages: {
      firstName: "<br />Please enter your first name", 
      lastName: "<br />Please enter your last name"
    }
  });
  
	jQuery("#addRsvp").click(function() {
		handleAddRsvpClick();
		jQuery('[name^="aa"][name*="question"]').each(function(index, element) {
							jQuery(element).rules("add", "required");
						});
		jQuery('[name^="newAttending"]').each(function(index, element) {
							jQuery(element).rules("add", "required");
						});
	});
});
