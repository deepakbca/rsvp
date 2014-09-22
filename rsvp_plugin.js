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
			newAttending1LastName:  "required",
			newAttending1FirstName: "required", 
			newAttending2LastName:  "required",
			newAttending2FirstName: "required",
			newAttending3LastName:  "required",
			newAttending3FirstName: "required", 
			attendeeFirstName:      "required", 
			attendeeLastName:       "required",
			mainEmail:       "required",
			mainRsvp: "required",
			mainquestion1: "required",
			attending2: "required",
			attending3: "required",
			attending4: "required",
			attending5: "required",
			attending6: "required",
			attending7: "required",
			a2question1: "required",
			a3question1: "required",
			a4question1: "required",
			a5question1: "required",
			a6question1: "required",
			a7question1: "required",
			newAttending1: "required",
			a1question1: "required",
			newAttending2: "required",
			a1question2: "required",
			newAttending3: "required",
			a1question3: "required",
			newAttending4: "required",
			a1question4: "required",
			newAttending5: "required",
			a1question5: "required",
			newAttending6: "required",
			a1question6: "required",
		},
		messages: {
			note: "<br />If you are adding additional RSVPs please enter your email address in case we have questions",
			newAttending1LastName:  "<font color=\"red\"><b>Please enter a last name</font></b>",
			newAttending1FirstName: "<font color=\"red\"><b>Please enter a first name</font></b>",
			newAttending2LastName:  "<font color=\"red\"><b>Please enter a last name</font></b>",
			newAttending2FirstName: "<font color=\"red\"><b>Please enter a first name</font></b>",
			newAttending3LastName:  "<font color=\"red\"><b>Please enter a last name</font></b>",
			newAttending3FirstName: "<font color=\"red\"><b>Please enter a first name</font></b>",
			newAttending4LastName:  "<font color=\"red\"><b>Please enter a last name</font></b>",
			newAttending4FirstName: "<font color=\"red\"><b>Please enter a first name</font></b>",
			newAttending5LastName:  "<font color=\"red\"><b>Please enter a last name</font></b>",
			newAttending5FirstName: "<font color=\"red\"><b>Please enter a first name</font></b>",
			newAttending6LastName:  "<font color=\"red\"><b>Please enter a last name</font></b>",
			newAttending6FirstName: "<font color=\"red\"><b>Please enter a first name</font></b>",
			attendeeFirstName:      "<font color=\"red\"><b>Please enter a first name</font></b>",
			attendeeLastName:       "<font color=\"red\"><b>Please enter a last name</font></b>",
			mainEmail:      	"<font color=\"red\"><b>Please specify a valid email address</font></b>",
		},

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
	});
});
