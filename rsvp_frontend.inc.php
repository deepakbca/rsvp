<?php 
function rsvp_frontend_handler($text) {
	global $wpdb; 
	
	//QUIT if the replacement string doesn't exist
	if (!strstr($text,FRONTEND_TEXT_CHECK)) return $text;
	
	// See if we should allow people to RSVP, etc...
	$openDate = get_option(OPTION_OPENDATE);
	$closeDate = get_option(OPTION_DEADLINE);
	if((strtotime($openDate) !== false) && (strtotime($openDate) > time())) {
		return "<p>I am sorry but the ability to RSVP for our wedding won't open till <strong>".date("m/d/Y", strtotime($openDate))."</strong></p>";
	} 
	
	if((strtotime($closeDate) !== false) && (strtotime($closeDate) < time())) {
		return "<p>The deadline to RSVP for this wedding has passed, please contact the bride and groom to see if there is still a seat for you.</p>";
	}
	
	if(isset($_POST['rsvpStep'])) {
		switch(strtolower($_POST['rsvpStep'])) {
			case("handlersvp") :
				if(is_numeric($_POST['attendeeID']) && ($_POST['attendeeID'] > 0)) {
					// update their information and what not....
					if(strToUpper($_POST['mainRsvp']) == "Y") {
						$rsvpStatus = "Yes";
					} else {
						$rsvpStatus = "No";
					}
					$attendeeID = $_POST['attendeeID'];
					$wpdb->update(ATTENDEES_TABLE, array("rsvpDate" => date("Y-m-d"), 
																							 "rsvpStatus" => $rsvpStatus, 
																							 "note" => $_POST['note'], 
																							 "kidsMeal" => ((strToUpper($_POST['mainKidsMeal']) == "Y") ? "Y" : "N"), 
																							 "veggieMeal" => ((strToUpper($_POST['mainVeggieMeal']) == "Y") ? "Y" : "N")), 
																				array("id" => $attendeeID), 
																				array("%s", "%s", "%s", "%s", "%s"), 
																				array("%d"));
					$sql = "SELECT id FROM ".ATTENDEES_TABLE." 
					 	WHERE (id IN (SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = %d) 
							OR id in (SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d)) 
							 AND rsvpStatus = 'NoResponse'";
					$associations = $wpdb->get_results($wpdb->prepare($sql, $attendeeID, $attendeeID));
					foreach($associations as $a) {
						if($_POST['rsvpFor'.$a->id] == "Y") {
							if($_POST['attending'.$a->id] == "Y") {
								$rsvpStatus = "Yes";
							} else {
								$rsvpStatus = "No";
							}
							$wpdb->update(ATTENDEES_TABLE, array("rsvpDate" => date("Y-m-d"), 
																									 "rsvpStatus" => $rsvpStatus, 
																									  "kidsMeal" => ((strToUpper($_POST['attending'.$a->id.'KidsMeal']) == "Y") ? "Y" : "N"), 
																									  "veggieMeal" => ((strToUpper($_POST['attending'.$a->id.'VeggieMeal']) == "Y") ? "Y" : "N")),
																						 array("id" => $a->id), 
																						 array("%s", "%s", "%s", "%s"), 
																						 array("%d"));
						}
					}
					
					if(is_numeric($_POST['additionalRsvp']) && ($_POST['additionalRsvp'] > 0)) {
						for($i = 1; $i <= $_POST['additionalRsvp']; $i++) {
							if(($i <= 3) && 
							   !empty($_POST['newAttending'.$i.'FirstName']) && 
							   !empty($_POST['newAttending'.$i.'LastName'])) {									
								$wpdb->insert(ATTENDEES_TABLE, array("firstName" => trim($_POST['newAttending'.$i.'FirstName']), 
																										 "lastName" => trim($_POST['newAttending'.$i.'LastName']), 
																										 "rsvpDate" => date("Y-m-d"), 
																										 "rsvpStatus" => (($_POST['newAttending'.$i] == "Y") ? "Yes" : "No"), 
																										 "kidsMeal" => $_POST['newAttending'.$i.'KidsMeal'], 
																										 "veggieMeal" => $_POST['newAttending'.$i.'VeggieMeal'], 
																										 "additionalAttendee" => "Y"), 
																							array('%s', '%s', '%s', '%s', '%s', '%s'));
								$newAid = $wpdb->insert_id;
								// Add associations for this new user
								$wpdb->insert(ASSOCIATED_ATTENDEES_TABLE, array("attendeeID" => $newAid, 
																																"associatedAttendeeID" => $attendeeID), 
																													array("%d", "%d"));
								$wpdb->query($wpdb->prepare("INSERT INTO ".ASSOCIATED_ATTENDEES_TABLE."(attendeeID, associatedAttendeeID)
																						 SELECT ".$newAid.", associatedAttendeeID 
																						 FROM ".ASSOCIATED_ATTENDEES_TABLE." 
																						 WHERE attendeeID = ".$attendeeID));
							}
						}
					}
					
					return frontend_rsvp_thankyou();
				} else {
					return rsvp_frontend_greeting();
				}
				break;
			case("editattendee") :
				if(is_numeric($_POST['attendeeID']) && ($_POST['attendeeID'] > 0)) {
					// Try to find the user.
					$attendee = $wpdb->get_row($wpdb->prepare("SELECT id, firstName, lastName, rsvpStatus 
																										 FROM ".ATTENDEES_TABLE." 
																										 WHERE id = %d", $_POST['attendeeID']));
					if($attendee != null) {
						$output .= "<div>\r\n";
						$output .= "<p>Welcome back ".htmlentities($attendee->firstName." ".$attendee->lastName)."!</p>";
						$output .= rsvp_frontend_main_form($attendee->id);
						return $output."</div>\r\n";
					}
				}
				break;
			case("foundattendee") :
				if(is_numeric($_POST['attendeeID']) && ($_POST['attendeeID'] > 0)) {
					// Try to find the user.
					$attendee = $wpdb->get_row($wpdb->prepare("SELECT id, firstName, lastName, rsvpStatus 
																										 FROM ".ATTENDEES_TABLE." 
																										 WHERE id = %d", $_POST['attendeeID']));
					if($attendee != null) {
						$output = "<div>\r\n";
						if(strtolower($attendee->rsvpStatus) == "noresponse") {
							$output .= "<p>Hi ".htmlentities($attendee->firstName." ".$attendee->lastName)."!</p>".
												"<p>There are a few more questions we need to ask you if you could please fill them out below to finish up the RSVP process.</p>";
							$output .= rsvp_frontend_main_form($attendee->id);
						} else {
							$output .= rsvp_frontend_prompt_to_edit($attendee);
						}
						return $output."</div>\r\n";
					} 
					
					return rsvp_frontend_greeting();
				} else {
					return rsvp_frontend_greeting();
				}
				break;
			case("find") :
				$_SESSION['rsvpFirstName'] = $_POST['firstName'];
				$_SESSION['rsvpLastName'] = $_POST['lastName'];
				$firstName = $_POST['firstName'];
				$lastName = $_POST['lastName'];
				
				if((strlen($_POST['firstName']) <= 1) || (strlen($_POST['lastName']) <= 1)) {
					$output = "<p style=\"color:red\">A first and last name must be specified</p>\r\n";
					$output .= rsvp_frontend_greeting();
					
					return $output;
				}
				
				// Try to find the user.
				$attendee = $wpdb->get_row($wpdb->prepare("SELECT id, firstName, lastName, rsvpStatus 
																									 FROM ".ATTENDEES_TABLE." 
																									 WHERE firstName = %s AND lastName = %s", $firstName, $lastName));
				if($attendee != null) {
					// hey we found something, we should move on and print out any associated users and let them rsvp
					$output = "<div>\r\n";
					if(strtolower($attendee->rsvpStatus) == "noresponse") {
						$output .= "<p>Hi ".htmlentities($attendee->firstName." ".$attendee->lastName)."!</p>".
											"<p>There are a few more questions we need to ask you if you could please fill them out below to finish up the RSVP process.</p>";
						$output .= rsvp_frontend_main_form($attendee->id);
					} else {
						$output .= rsvp_frontend_prompt_to_edit($attendee);
					}
					return $output."</div>\r\n";
				}
				
				// We did not find anyone let's try and do a rough search
				$attendees = null;
				for($i = 3; $i >= 1; $i--) {
					$truncFirstName = rsvp_chomp_name($firstName, $i);
					$attendees = $wpdb->get_results("SELECT id, firstName, lastName, rsvpStatus FROM ".ATTENDEES_TABLE." 
																					 WHERE lastName = '".mysql_real_escape_string($lastName)."' AND firstName LIKE '".mysql_real_escape_string($truncFirstName)."%'");
					if(count($attendees) > 0) {
						$output = "<p><strong>We could not find an exact match but could any of the below entries be you?</strong></p>";
						foreach($attendees as $a) {
							$output .= "<form method=\"post\">\r\n
											<input type=\"hidden\" name=\"rsvpStep\" value=\"foundattendee\" />\r\n
											<input type=\"hidden\" name=\"attendeeID\" value=\"".$a->id."\" />\r\n
											<p style=\"text-align:left;\">\r\n
									".htmlentities($a->firstName." ".$a->lastName)." 
									<input type=\"submit\" value=\"RSVP\" />\r\n
									</p>\r\n</form>\r\n";
						}
						
						return $output;
					} else {
						$i = strlen($truncFirstName);
					}
				}
				return "<p><strong>We were unable to find anyone with a name of ".htmlentities($firstName." ".$lastName)."</strong></p>\r\n".rsvp_frontend_greeting();
				break;
			case("newsearch"):
			default:
				return rsvp_frontend_greeting();
				break;
		}
	} else {
		return rsvp_frontend_greeting();
	}
}

function rsvp_frontend_prompt_to_edit($attendee) {
	$prompt = "<p>Hi ".htmlentities($attendee->firstName." ".$attendee->lastName)." it looks like you have already RSVP'd. 
								Would you like to edit your reservation?</p>";
	$prompt .= "<form method=\"post\">\r\n
								<input type=\"hidden\" name=\"attendeeID\" value=\"".$attendee->id."\" />
								<input type=\"hidden\" name=\"rsvpStep\" id=\"rsvpStep\" value=\"editattendee\" />
								<input type=\"submit\" value=\"Yes\" onclick=\"document.getElementById('rsvpStep').value='editattendee';\" />
								<input type=\"submit\" value=\"No\" onclick=\"document.getElementById('rsvpStep').value='newsearch';\"  />
							</form>\r\n";
	return $prompt;
}

function rsvp_frontend_main_form($attendeeID) {
	global $wpdb;
	$attendee = $wpdb->get_row($wpdb->prepare("SELECT id, firstName, lastName, rsvpStatus, note, kidsMeal, additionalAttendee, veggieMeal, personalGreeting   
																						 FROM ".ATTENDEES_TABLE." 
																						 WHERE id = %d", $attendeeID));
	$sql = "SELECT id FROM ".ATTENDEES_TABLE." 
	 	WHERE (id IN (SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = %d) 
			OR id in (SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d)) 
			 AND additionalAttendee = 'Y'";
	$newRsvps = $wpdb->get_results($wpdb->prepare($sql, $attendeeID, $attendeeID));
	
	
	$form = "<script type=\"text/javascript\" language=\"javascript\" src=\"".get_option("siteurl")."/wp-content/plugins/rsvp/jquery.js\"></script>\r\n";
	$form .= "<script type=\"text/javascript\" language=\"javascript\" src=\"".get_option("siteurl")."/wp-content/plugins/rsvp/jquery-validate/jquery.validate.min.js\"></script>";
	$form .= "<script type=\"text/javascript\" language=\"javascript\">\r\n
							$(document).ready(function(){
								jQuery.validator.addMethod(\"customNote\", function(value, element) {
						      if(($(\"#additionalRsvp\").val() > 0) && ($(\"#note\").val() == \"\")) {
						        return false;
						      }

						      return true;
						    }, \"<br />Please enter an email address that we can use to contact you about the extra guest.  We have to keep a pretty close eye on the number of attendees.  Thanks!\");
						
								$(\"#rsvpForm\").validate({\r\n
									rules: {
										note: \"customNote\",
										newAttending1LastName:  \"required\",
										newAttending1FirstName: \"required\", 
										newAttending2LastName:  \"required\",
										newAttending2FirstName: \"required\",
										newAttending3LastName:  \"required\",
										newAttending3FirstName: \"required\"
									},
									messages: {
										note: \"<br />If you are adding additional RSVPs please enter your email address in case we have questions\",
										newAttending1LastName:  \"<br />Please enter a last name\",
										newAttending1FirstName: \"<br />Please enter a first name\", 
										newAttending2LastName:  \"<br />Please enter a last name\",
										newAttending2FirstName: \"<br />Please enter a first name\",
										newAttending3LastName:  \"<br />Please enter a last name\",
										newAttending3FirstName: \"<br />Please enter a first name\"
									}
								});
							});
					</script>\r\n";
	$form .= "<style text/css>\r\n".
						"	label.error { font-weight: bold; clear:both;}\r\n".
						"	input.error, textarea.error { border: 2px solid red; }\r\n".
						"</style>\r\n";
	$form .= "<form id=\"rsvpForm\" name=\"rsvpForm\" method=\"post\">\r\n";
	$form .= "	<input type=\"hidden\" name=\"attendeeID\" value=\"".$attendeeID."\" />\r\n";
	$form .= "	<input type=\"hidden\" name=\"rsvpStep\" value=\"handleRsvp\" />\r\n";
	$form .= "<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\">\r\n";
	$yesVerbiage = ((trim(get_option(OPTION_YES_VERBIAGE)) != "") ? get_option(OPTION_YES_VERBIAGE) : 
		"Yes, of course I will be there! Who doesn't like family, friends, weddings, and a good time?");
	$noVerbiage = ((trim(get_option(OPTION_NO_VERBIAGE)) != "") ? get_option(OPTION_NO_VERBIAGE) : 
			"Um, unfortunately, there is a Star Trek marathon on that day that I just cannot miss.");
	$kidsVerbiage = ((trim(get_option(OPTION_KIDS_MEAL_VERBIAGE)) != "") ? get_option(OPTION_KIDS_MEAL_VERBIAGE) : 
					"We have the option of getting cheese pizza for the kids (and only kids).  Do you want pizza instead of \"adult food?\"");
	$veggieVerbiage = ((trim(get_option(OPTION_VEGGIE_MEAL_VERBIAGE)) != "") ? get_option(OPTION_VEGGIE_MEAL_VERBIAGE) : 
					"We also have the option of getting individual vegetarian meals instead of the fish or meat.  Would you like a vegetarian dinner?");
	$noteVerbiage = ((trim(get_option(OPTION_NOTE_VERBIAGE)) != "") ? get_option(OPTION_NOTE_VERBIAGE) : 
		"If you have any <strong style=\"color:red;\">food allergies</strong>, please indicate what they are in the &quot;notes&quot; section below.  Or, if you just want to send us a note, please feel free.  If you have any questions, please send us an email at <a href=\"mailto:rsvp@janaandmike.com\">rsvp@janaandmike.com</a>.");
	$form .= "  <tr>\r\n
								<td align=\"left\">So, how about it?</td>
							</tr>\r\n
							<tr>\r\n
								<td colspan=\"2\" align=\"left\"><input type=\"radio\" name=\"mainRsvp\" value=\"Y\" id=\"mainRsvpY\" ".
									(($attendee->rsvpStatus == "No") ? "" : "checked=\"checked\"")." /> - <label for=\"mainRsvpY\">".htmlentities($yesVerbiage)."</label></td>
							</tr>\r\n
							<tr>\r\n
								<td align=\"left\" colspan=\"2\"><input type=\"radio\" name=\"mainRsvp\" value=\"N\" id=\"mainRsvpN\" ".
											(($attendee->rsvpStatus == "No") ? "checked=\"checked\"" : "")." /> - 
											<label for=\"mainRsvpN\">".htmlentities($noVerbiage)."</label></td>
							</tr>
							<tr><td><br /></td></tr>";		
	if(!empty($attendee->personalGreeting)) {
		$form .= "<tr>\r\n
						<td colspan=\"2\" align=\"left\">".nl2br($attendee->personalGreeting)."</td>\r\n
					</tr>
					<tr><td><br /></td></tr>\r\n";
	}

	if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {		
		$form .= "	<tr><td colspan=\"2\"><hr /></td></tr>\r\n
								<tr>\r\n
									<td colspan=\"2\" align=\"left\">".htmlentities($kidsVerbiage)."</td>
								</tr>\r\n
								<tr>\r\n
									<td align=\"center\" colspan=\"2\"><input type=\"radio\" name=\"mainKidsMeal\" value=\"Y\" id=\"mainKidsMealY\" 
									 	".(($attendee->kidsMeal == "Y") ? "checked=\"checked\"" : "")." /> <label for=\"mainKidsMealY\">Yes</label> 
											<input type=\"radio\" name=\"mainKidsMeal\" value=\"N\" id=\"mainKidsMealN\" 
											".(($attendee->kidsMeal == "Y") ? "" : "checked=\"checked\"")." /> <label for=\"mainKidsMealN\">No</label></td>
								</tr>
								<tr><td><br /></td></tr>";
	}
	
	if(get_option(OPTION_HIDE_VEGGIE) != "Y") {		
		$form .= "	<tr><td colspan=\"2\"><hr /></td></tr>\r\n
								<tr>\r\n
									<td align=\"left\" colspan=\"2\">".htmlentities($veggieVerbiage)."</td> 
								</tr>\r\n
								<tr>\r\n
									<td align=\"center\" colspan=\"2\"><input type=\"radio\" name=\"mainVeggieMeal\" value=\"Y\" id=\"mainVeggieMealY\"
									 		".(($attendee->veggieMeal == "Y") ? "checked=\"checked\"" : "")."/> <label for=\"mainVeggieMealY\">Yes</label> 
											<input type=\"radio\" name=\"mainVeggieMeal\" value=\"N\" id=\"mainVeggieMealN\" 
											".(($attendee->veggieMeal == "Y") ? "" : "checked=\"checked\"")." /> <label for=\"mainVeggieMealN\">No</label></td>
								</tr>\r\n";
	}
	
	$form .= " <tr><td><br /></td></tr>\r\n
						 <tr>
								<td valign=\"top\" align=\"left\" colspan=\"2\">".$noteVerbiage."</td>
							</tr>
							<tr>
								<td colspan=\"2\"><textarea name=\"note\" id=\"note\" rows=\"7\" cols=\"50\">".htmlentities($attendee->note)."</textarea></td>
						 </tr>";
	$form .= "</table>\r\n";
	
	$sql = "SELECT id, firstName, lastName FROM ".ATTENDEES_TABLE." 
	 	WHERE (id IN (SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = %d) 
			OR id in (SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d)) 
			 AND rsvpStatus <> 'NoResponse'";
	$rsvpd = $wpdb->get_results($wpdb->prepare($sql, $attendeeID, $attendeeID));
	if(count($rsvpd) > 0) {
		$form .= "<p>The following people associated with you have already registered: ";
		foreach($rsvpd as $r) {
			$form .= "<br />".htmlentities($r->firstName." ".$r->lastName);
		}
		$form .= "</p>\r\n";
	}
	
	$sql = "SELECT id, firstName, lastName, personalGreeting FROM ".ATTENDEES_TABLE." 
	 	WHERE (id IN (SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = %d) 
			OR id in (SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d)) 
			 AND rsvpStatus = 'NoResponse'";
	
	$associations = $wpdb->get_results($wpdb->prepare($sql, $attendeeID, $attendeeID));
	if(count($associations) > 0) {
		$form .= "<h3>The following people are associated with you.  At this time you can RSVP for them as well.</h3>";
		foreach($associations as $a) {
			$form .= "<div style=\"text-align:left;border-top: 1px solid;\">\r\n
							<p><label for=\"rsvpFor".$a->id."\">RSVP for ".htmlentities($a->firstName." ".$a->lastName)."?</label> 
									<input type=\"checkbox\" name=\"rsvpFor".$a->id."\" id=\"rsvpFor".$a->id."\" value=\"Y\" /></p>";
			
			$form .= "<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\">\r\n";
			$form .= "  <tr>\r\n
										<td align=\"left\">Will ".htmlentities($a->firstName)." be attending?</td>\r\n
										<td align=\"left\"><input type=\"radio\" name=\"attending".$a->id."\" value=\"Y\" id=\"attending".$a->id."Y\" checked=\"checked\" /> 
																			<label for=\"attending".$a->id."Y\">Yes</label> 
												<input type=\"radio\" name=\"attending".$a->id."\" value=\"N\" id=\"attending".$a->id."N\" /> <label for=\"attending".$a->id."N\">No</label></td>
									</tr>
									<tr><td><br /></td></tr>";
			
			if(!empty($a->personalGreeting)) {
				$form .= "<tr>\r\n
								<td colspan=\"2\" align=\"left\">".nl2br($a->personalGreeting)."</td>\r\n
							</tr>
							<tr><td><br /></td></tr>\r\n";
			}
			
			if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {		
				$form .= "	<tr>
											<td align=\"left\">Does ".htmlentities($a->firstName)." need a kids meal?&nbsp;</td> 
											<td align=\"left\"><input type=\"radio\" name=\"attending".$a->id."KidsMeal\" value=\"Y\" id=\"attending".$a->id."KidsMealY\" /> 
																				<label for=\"attending".$a->id."KidsMealY\">Yes</label> 
													<input type=\"radio\" name=\"attending".$a->id."KidsMeal\" value=\"N\" id=\"attending".$a->id."KidsMealN\" checked=\"checked\" /> 
													<label for=\"attending".$a->id."KidsMealN\">No</label></td>
										</tr>
										<tr><td><br /></td></tr>";
			}
			
			if(get_option(OPTION_HIDE_VEGGIE) != "Y") {		
				$form .= "	<tr>
											<td align=\"left\">Does ".htmlentities($a->firstName)." need a vegetarian meal?&nbsp;</td> 
											<td align=\"left\"><input type=\"radio\" name=\"attending".$a->id."VeggieMeal\" value=\"Y\" id=\"attending".$a->id."VeggieMealY\" /> 
																				<label for=\"attending".$a->id."VeggieMealY\">Yes</label> 
													<input type=\"radio\" name=\"attending".$a->id."VeggieMeal\" value=\"N\" id=\"attending".$a->id."VeggieMealN\" checked=\"checked\" /> 
													<label for=\"attending".$a->id."VeggieMealN\">No</label></td>
										</tr>";
			}
			$form .= "</table>\r\n";
			$form .= "</div>\r\n";
		}
	}
	$form .= "<h3>Did we slip up and forget to invite someone? If so, please add him or her here:</h3>\r\n";
	$form .= "<div id=\"additionalRsvpContainer\">\r\n
							<input type=\"hidden\" name=\"additionalRsvp\" id=\"additionalRsvp\" value=\"".count($newRsvps)."\" />
							<div style=\"text-align:right\"><img 
								src=\"".get_option("siteurl")."/wp-content/plugins/rsvp/plus.png\" width=\"24\" height=\"24\" border=\"0\" id=\"addRsvp\" /></div>
	
						</div>";
						
	$form .= "<p><input type=\"submit\" value=\"RSVP\" /></p>\r\n";
	$form .= "<script type=\"text/javascript\" language=\"javascript\">\r\n
							$(document).ready(function() {
								$(\"#addRsvp\").click(function() {
									handleAddRsvpClick();
								});
							});
							
							function handleAddRsvpClick() {
								var numAdditional = $(\"#additionalRsvp\").val();
								numAdditional++;
								if(numAdditional > 3) {
									alert('You have already added 3 additional rsvp\'s you can add no more.');
								} else {
									$(\"#additionalRsvpContainer\").append(\"<div style=\\\"text-align:left;border-top: 1px solid;\\\">\" + \r\n
											\"<table cellpadding=\\\"2\\\" cellspacing=\\\"0\\\" border=\\\"0\\\">\" + \r\n
												\"<tr>\" + \r\n
												\"	<td align=\\\"left\\\">Person's first name&nbsp;</td>\" + \r\n 
												\"  <td align=\\\"left\\\"><input type=\\\"text\\\" name=\\\"newAttending\" + numAdditional + \"FirstName\\\" id=\\\"newAttending\" + numAdditional + \"FirstName\\\" /></td>\" + \r\n
									  		\"</tr>\" + \r\n
												\"<tr>\" + \r\n
												\"	<td align=\\\"left\\\">Person's last name</td>\" + \r\n 
												\"  <td align=\\\"left\\\"><input type=\\\"text\\\" name=\\\"newAttending\" + numAdditional + \"LastName\\\" id=\\\"newAttending\" + numAdditional + \"LastName\\\" /></td>\" + \r\n
												\"</tr>\" + \r\n
									  		\"<tr>\" + \r\n
													\"<td align=\\\"left\\\">Will this person be attending?&nbsp;</td>\" + \r\n
													\"<td align=\\\"left\\\">\" + 
														\"<input type=\\\"radio\\\" name=\\\"newAttending\" + numAdditional + \"\\\" value=\\\"Y\\\" id=\\\"newAttending\" + numAdditional + \"Y\\\" checked=\\\"checked\\\" /> \" + 
																						\"<label for=\\\"newAttending\" + numAdditional + \"Y\\\">Yes</label> \" + 
															\"<input type=\\\"radio\\\" name=\\\"newAttending\" + numAdditional + \"\\\" value=\\\"N\\\" id=\\\"newAttending\" + numAdditional + \"N\\\"> <label for=\\\"newAttending\" + numAdditional + \"N\\\">No</label></td>\" + 
												\"</tr>\" + \r\n";
											if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {		
												$form .= "\"<tr>\" + 
													\"<td align=\\\"left\\\">Does this person need a kids meal?&nbsp;</td> \" + 
													\"<td align=\\\"left\\\"><input type=\\\"radio\\\" name=\\\"newAttending\" + numAdditional + \"KidsMeal\\\" value=\\\"Y\\\" id=\\\"newAttending\" + numAdditional + \"KidsMealY\\\" /> \" + 
																\"<label for=\\\"newAttending\" + numAdditional + \"KidsMealY\\\">Yes</label> \" + 
															\"<input type=\\\"radio\\\" name=\\\"newAttending\" + numAdditional + \"KidsMeal\\\" value=\\\"N\\\" id=\\\"newAttending\" + numAdditional + \"KidsMealN\\\" checked=\\\"checked\\\" /> \" + 
															\"<label for=\\\"newAttending\" + numAdditional + \"KidsMealN\\\">No</label></td>\" + 
												\"</tr>\" + \r\n";
											}
											if(get_option(OPTION_HIDE_VEGGIE) != "Y") {		
												$form .= "\"<tr>\" + \r\n
													\"<td align=\\\"left\\\">Does this person need a vegetarian meal?&nbsp;</td> \" + 
													\"<td align=\\\"left\\\"><input type=\\\"radio\\\" name=\\\"newAttending\" + numAdditional + \"VeggieMeal\\\" value=\\\"Y\\\" id=\\\"newAttending\" + numAdditional + \"VeggieMealY\\\" /> \" + 
																						\"<label for=\\\"newAttending\" + numAdditional + \"VeggieMealY\\\">Yes</label> \" + 
															\"<input type=\\\"radio\\\" name=\\\"newAttending\" + numAdditional + \"VeggieMeal\\\" value=\\\"N\\\" id=\\\"newAttending\" + numAdditional + \"VeggieMealN\\\" checked=\\\"checked\\\" /> \" + 
															\"<label for=\\\"newAttending\" + numAdditional + \"VeggieMealN\\\">No</label></td>\" + 
												\"</tr>\" + ";
											}
											
											$form .= "\"</table>\" + 
											\"<br />\" + 
										\"</div>\");
									$(\"#additionalRsvp\").val(numAdditional);
								}
							}
						</script>\r\n";
	$form .= "</form>\r\n";
	
	return $form;
}

function frontend_rsvp_thankyou() {
	$customTy = get_option(OPTION_THANKYOU);
	if(!empty($customTy)) {
		return nl2br($customTy);
	} else {
		return "<p>Thank you for RSVPing</p>";
	}
}

function rsvp_chomp_name($name, $maxLength) {
	for($i = $maxLength; $maxLength >= 1; $i--) {
		if(strlen($name) >= $i) {
			return substr($name, 0, $i);
		}
	}
}

function rsvp_frontend_greeting() {
	$customGreeting = get_option(OPTION_GREETING);
	$output = "<p>Please enter your first and last name to RSVP.</p>";
	$firstName = "";
	$lastName = "";
	if(isset($_SESSION['rsvpFirstName'])) {
		$firstName = $_SESSION['rsvpFirstName'];
	}
	if(isset($_SESSION['rsvpLastName'])) {
		$lastName = $_SESSION['rsvpLastName'];
	}
	if(!empty($customGreeting)) {
		$output = nl2br($customGreeting);
	} 
	$output .= "<script type=\"text/javascript\" language=\"javascript\" src=\"".get_option("siteurl")."/wp-content/plugins/rsvp/jquery.js\"></script>";
	$output .= "<script type=\"text/javascript\" language=\"javascript\" src=\"".get_option("siteurl")."/wp-content/plugins/rsvp/jquery-validate/jquery.validate.min.js\"></script>";
	$output .= "<script type=\"text/javascript\">$(document).ready(function(){ $(\"#rsvp\").validate({rules: {firstName: \"required\",lastName: \"required\"}, messages: {firstName: \"<br />Please enter your first name\", lastName: \"<br />Please enter your last name\"}});});</script>";
	$output .= "<style text/css>\r\n".
		"	label.error { font-weight: bold; clear:both;}\r\n".
		"	input.error { border: 2px solid red; }\r\n".
		"</style>\r\n";
	$output .= "<form name=\"rsvp\" method=\"post\" id=\"rsvp\">\r\n";
	$output .= "	<input type=\"hidden\" name=\"rsvpStep\" value=\"find\" />";
	$output .= "<p><label for=\"firstName\">First Name:</label> 
								 <input type=\"text\" name=\"firstName\" id=\"firstName\" size=\"30\" value=\"".htmlentities($firstName)."\" class=\"required\" /></p>\r\n";
	$output .= "<p><label for=\"lastName\">Last Name:</label> 
								 <input type=\"text\" name=\"lastName\" id=\"lastName\" size=\"30\" value=\"".htmlentities($lastName)."\" class=\"required\" /></p>\r\n";
	$output .= "<p><input type=\"submit\" value=\"Register\" /></p>";
	$output .= "</form>\r\n";
	
	return $output;
}
?>