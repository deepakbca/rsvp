<?php
/**
 * @package rsvp
 * @author MDE Development, LLC
 * @version 1.2.0
 */
/*
Plugin Name: RSVP 
Plugin URI: http://wordpress.org/#
Description: This plugin allows guests to RSVP to an event.  It was made 
             initially for weddings but could be used for other things.  
Author: MDE Development, LLC
Version: 1.2.0
Author URI: http://mde-dev.com
License: GPL
*/
#
# INSTALLATION: see readme.txt
#
# USAGE: Once the RSVP plugin has been installed, you can set the custom text 
#        via Settings -> RSVP Options in the  admin area. 
#      
#        To add, edit, delete and see rsvp status there will be a new RSVP admin
#        area just go there.
# 
#        To allow people to rsvp create a new page and add "rsvp-pluginhere" to the text

	session_start();
	define("ATTENDEES_TABLE", $wpdb->prefix."attendees");
	define("ASSOCIATED_ATTENDEES_TABLE", $wpdb->prefix."associatedAttendees");
	define("QUESTIONS_TABLE", $wpdb->prefix."rsvpCustomQuestions");
	define("QUESTION_TYPE_TABLE", $wpdb->prefix."rsvpQuestionTypes");
	define("ATTENDEE_ANSWERS", $wpdb->prefix."attendeeAnswers");
	define("QUESTION_ANSWERS_TABLE", $wpdb->prefix."rsvpCustomQuestionAnswers");
	define("QUESTION_ATTENDEES_TABLE", $wpdb->prefix."rsvpCustomQuestionAttendees");
	define("EDIT_SESSION_KEY", "RsvpEditAttendeeID");
	define("EDIT_QUESTION_KEY", "RsvpEditQuestionID");
	define("FRONTEND_TEXT_CHECK", "rsvp-pluginhere");
	define("OPTION_GREETING", "rsvp_custom_greeting");
	define("OPTION_THANKYOU", "rsvp_custom_thankyou");
	define("OPTION_DEADLINE", "rsvp_deadline");
	define("OPTION_OPENDATE", 'rsvp_opendate');
	define("OPTION_YES_VERBIAGE", "rsvp_yes_verbiage");
	define("OPTION_NO_VERBIAGE", "rsvp_no_verbiage");
	define("OPTION_KIDS_MEAL_VERBIAGE", "rsvp_kids_meal_verbiage");
	define("OPTION_VEGGIE_MEAL_VERBIAGE", "rsvp_veggie_meal_verbiage");
	define("OPTION_NOTE_VERBIAGE", "rsvp_note_verbiage");
	define("OPTION_HIDE_VEGGIE", "rsvp_hide_veggie");
	define("OPTION_HIDE_KIDS_MEAL", "rsvp_hide_kids_meal");
	define("OPTION_HIDE_ADD_ADDITIONAL", "rsvp_hide_add_additional");
	define("OPTION_NOTIFY_ON_RSVP", "rsvp_notify_when_rsvp");
	define("OPTION_NOTIFY_EMAIL", "rsvp_notify_email_address");
	define("RSVP_DB_VERSION", "5.0");
	define("QT_SHORT", "shortAnswer");
	define("QT_MULTI", "multipleChoice");
	define("QT_LONG", "longAnswer");
	define("QT_DROP", "dropdown");
	
	if((isset($_GET['page']) && (strToLower($_GET['page']) == 'rsvp-admin-export')) || 
		 (isset($_POST['rsvp-bulk-action']) && (strToLower($_POST['rsvp-bulk-action']) == "export"))) {
		add_action('init', 'rsvp_admin_export');
	}
	
	require_once("rsvp_frontend.inc.php");
	/*
	 * Description: Database setup for the rsvp plug-in.  
	 */
	function rsvp_database_setup() {
		global $wpdb;
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		$installed_ver = get_option("rsvp_db_version");
		$table = $wpdb->prefix."attendees";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = "CREATE TABLE ".$table." (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`firstName` VARCHAR( 100 ) NOT NULL ,
			`lastName` VARCHAR( 100 ) NOT NULL ,
			`rsvpDate` DATE NOT NULL ,
			`rsvpStatus` ENUM( 'Yes', 'No', 'NoResponse' ) NOT NULL DEFAULT 'NoResponse',
			`note` TEXT NOT NULL ,
			`kidsMeal` ENUM( 'Y', 'N' ) NOT NULL DEFAULT 'N',
			`additionalAttendee` ENUM( 'Y', 'N' ) NOT NULL DEFAULT 'N',
			`veggieMeal` ENUM( 'Y', 'N' ) NOT NULL DEFAULT 'N', 
			`personalGreeting` TEXT NOT NULL 
			);";
			$wpdb->query($sql);
		}
		$table = $wpdb->prefix."associatedAttendees";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = "CREATE TABLE ".$table." (
			`attendeeID` INT NOT NULL ,
			`associatedAttendeeID` INT NOT NULL
			);";
			$wpdb->query($sql);
			$sql = "ALTER TABLE `".$table."` ADD INDEX ( `attendeeID` ) ";
			$wpdb->query($sql);
			$sql = "ALTER TABLE `".$table."` ADD INDEX ( `associatedAttendeeID` )";
			$wpdb->query($sql);
		}				
		add_option("rsvp_db_version", "4.0");
		
		if((int)$installed_ver < 2) {
			$table = $wpdb->prefix."attendees";
			$sql = "ALTER TABLE ".$table." ADD `personalGreeting` TEXT NOT NULL ;";
			$wpdb->query($sql);
			update_option( "rsvp_db_version", RSVP_DB_VERSION);
		}
		
		if((int)$installed_ver < 4) {
			$table = $wpdb->prefix."rsvpCustomQuestions";
			$sql = "ALTER TABLE ".$table." ADD `sortOrder` INT NOT NULL DEFAULT '99';";
			$wpdb->query($sql);
			update_option( "rsvp_db_version", RSVP_DB_VERSION);
		}
		
		$table = $wpdb->prefix."rsvpCustomQuestions";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = " CREATE TABLE $table (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`question` MEDIUMTEXT NOT NULL ,
			`questionTypeID` INT NOT NULL, 
			`sortOrder` INT NOT NULL DEFAULT '99', 
			`permissionLevel` ENUM( 'public', 'private' ) NOT NULL DEFAULT 'public'
			);";
			$wpdb->query($sql);
		}
		
		$table =  $wpdb->prefix."rsvpQuestionTypes";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = " CREATE TABLE $table (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`questionType` VARCHAR( 100 ) NOT NULL , 
			`friendlyName` VARCHAR(100) NOT NULL 
			);";
			$wpdb->query($sql);
			
			$wpdb->insert($table, array("questionType" => "shortAnswer", "friendlyName" => "Short Answer"), array('%s', '%s'));
			$wpdb->insert($table, array("questionType" => "multipleChoice", "friendlyName" => "Multiple Choice"), array('%s', '%s'));
			$wpdb->insert($table, array("questionType" => "longAnswer", "friendlyName" => "Long Answer"), array('%s', '%s'));
			$wpdb->insert($table, array("questionType" => "dropdown", "friendlyName" => "Drop Down"), array('%s', '%s'));
		}
		
		$table = $wpdb->prefix."rsvpCustomQuestionAnswers";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = "CREATE TABLE $table (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`questionID` INT NOT NULL, 
			`answer` MEDIUMTEXT NOT NULL
			);";
			$wpdb->query($sql);
		}
		
		$table = $wpdb->prefix."attendeeAnswers";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = "CREATE TABLE $table (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`questionID` INT NOT NULL, 
			`answer` MEDIUMTEXT NOT NULL, 
			`attendeeID` INT NOT NULL 
			);";
			$wpdb->query($sql);
		}
		
		$table = $wpdb->prefix."rsvpCustomQuestionAttendees";
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$sql = "CREATE TABLE $table (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`questionID` INT NOT NULL ,
			`attendeeID` INT NOT NULL
			);";
			$wpdb->query($sql);
		}
		
		if((int)$installed_ver < 5) {
			$table = QUESTIONS_TABLE;
			$sql = "ALTER TABLE `$table` ADD `permissionLevel` ENUM( 'public', 'private' ) NOT NULL DEFAULT 'public';";
			$wpdb->query($sql);
		}
		update_option( "rsvp_db_version", RSVP_DB_VERSION);
	}

	function rsvp_admin_guestlist_options() {
?>
		<link rel="stylesheet" href="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery-ui-1.7.2.custom/css/ui-lightness/jquery-ui-1.7.2.custom.css" type="text/css" media="all" />
		<script type="text/javascript" language="javascript" 
			src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery-ui-1.7.2.custom/js/jquery-1.3.2.min.js"></script>
		<script type="text/javascript" language="javascript" 
			src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery-ui-1.7.2.custom/js/jquery-ui-1.7.2.custom.min.js"></script>
		<script type="text/javascript" language="javascript">
			$(document).ready(function() {
				$("#rsvp_opendate").datepicker();
				$("#rsvp_deadline").datepicker();
			});
		</script>
		<div class="wrap">
			<h2>RSVP Guestlist Options</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'rsvp-option-group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="rsvp_opendate">RSVP Open Date:</label></th>
						<td align="left"><input type="text" name="rsvp_opendate" id="rsvp_opendate" value="<?php echo htmlspecialchars(get_option(OPTION_OPENDATE)); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_deadline">RSVP Deadline:</label></th>
						<td align="left"><input type="text" name="rsvp_deadline" id="rsvp_deadline" value="<?php echo htmlspecialchars(get_option(OPTION_DEADLINE)); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_custom_greeting">Custom Greeting:</label></th>
						<td align="left"><textarea name="rsvp_custom_greeting" id="rsvp_custom_greeting" rows="5" cols="60"><?php echo htmlspecialchars(get_option(OPTION_GREETING)); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_yes_verbiage">RSVP Yes Verbiage:</label></th>
						<td align="left"><input type="text" name="rsvp_yes_verbiage" id="rsvp_yes_verbiage" 
							value="<?php echo htmlspecialchars(get_option(OPTION_YES_VERBIAGE)); ?>" size="65" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_no_verbiage">RSVP No Verbiage:</label></th>
						<td align="left"><input type="text" name="rsvp_no_verbiage" id="rsvp_no_verbiage" 
							value="<?php echo htmlspecialchars(get_option(OPTION_NO_VERBIAGE)); ?>" size="65" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_kids_meal_verbiage">RSVP Kids Meal Verbiage:</label></th>
						<td align="left"><input type="text" name="rsvp_kids_meal_verbiage" id="rsvp_kids_meal_verbiage" 
							value="<?php echo htmlspecialchars(get_option(OPTION_KIDS_MEAL_VERBIAGE)); ?>" size="65" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_hide_kids_meal">Hide Kids Meal Question:</label></th>
						<td align="left"><input type="checkbox" name="rsvp_hide_kids_meal" id="rsvp_hide_kids_meal" 
							value="Y" <?php echo ((get_option(OPTION_HIDE_KIDS_MEAL) == "Y") ? " checked=\"checked\"" : ""); ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_veggie_meal_verbiage">RSVP Vegetarian Meal Verbiage:</label></th>
						<td align="left"><input type="text" name="rsvp_veggie_meal_verbiage" id="rsvp_veggie_meal_verbiage" 
							value="<?php echo htmlspecialchars(get_option(OPTION_VEGGIE_MEAL_VERBIAGE)); ?>" size="65" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_hide_veggie">Hide Vegetarian Meal Question:</label></th>
						<td align="left"><input type="checkbox" name="rsvp_hide_veggie" id="rsvp_hide_veggie" 
							value="Y" <?php echo ((get_option(OPTION_HIDE_VEGGIE) == "Y") ? " checked=\"checked\"" : ""); ?> /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_note_verbiage">Note Verbiage:</label></th>
						<td align="left"><textarea name="rsvp_note_verbiage" id="rsvp_note_verbiage" rows="3" cols="60"><?php 
							echo htmlspecialchars(get_option(OPTION_NOTE_VERBIAGE)); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="rsvp_custom_thankyou">Custom Thank You:</label></th>
						<td align="left"><textarea name="rsvp_custom_thankyou" id="rsvp_custom_thankyou" rows="5" cols="60"><?php echo htmlspecialchars(get_option(OPTION_THANKYOU)); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="rsvp_hide_add_additional">Do not allow additional guests</label></th>
						<td align="left"><input type="checkbox" name="rsvp_hide_add_additional" id="rsvp_hide_add_additional" value="Y" 
							<?php echo ((get_option(OPTION_HIDE_ADD_ADDITIONAL) == "Y") ? " checked=\"checked\"" : ""); ?> /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rsvp_notify_when_rsvp">Notify When Guest RSVPs</label></th>
						<td align="left"><input type="checkbox" name="rsvp_notify_when_rsvp" id="rsvp_notify_when_rsvp" value="Y" 
							<?php echo ((get_option(OPTION_NOTIFY_ON_RSVP) == "Y") ? " checked=\"checked\"" : ""); ?> /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rsvp_notify_email_address">Email address to notify</label></th>
						<td align="left"><input type="text" name="rsvp_notify_email_address" id="rsvp_notify_email_address" value="<?php echo htmlspecialchars(get_option(OPTION_NOTIFY_EMAIL)); ?>"/></td>
					</tr>
				</table>
				<input type="hidden" name="action" value="update" />
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</p>
			</form>
		</div>
<?php
	}
	
	function rsvp_admin_guestlist() {
		global $wpdb;		
		
		if(get_option("rsvp_db_version") != RSVP_DB_VERSION) {
			rsvp_database_setup();
		}
		
		if((count($_POST) > 0) && ($_POST['rsvp-bulk-action'] == "delete") && (is_array($_POST['attendee']) && (count($_POST['attendee']) > 0))) {
			foreach($_POST['attendee'] as $attendee) {
				if(is_numeric($attendee) && ($attendee > 0)) {
					$wpdb->query($wpdb->prepare("DELETE FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d OR associatedAttendeeID = %d", 
																			$attendee, 
																			$attendee));
					$wpdb->query($wpdb->prepare("DELETE FROM ".ATTENDEES_TABLE." WHERE id = %d", 
																			$attendee));
				}
			}
		}
		
		$sql = "SELECT id, firstName, lastName, rsvpStatus, note, kidsMeal, additionalAttendee, veggieMeal, personalGreeting FROM ".ATTENDEES_TABLE;
		$orderBy = " lastName, firstName";
		if(isset($_GET['sort'])) {
			if(strToLower($_GET['sort']) == "rsvpstatus") {
				$orderBy = " rsvpStatus ".((strtolower($_GET['sortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
			}else if(strToLower($_GET['sort']) == "attendee") {
				$direction = ((strtolower($_GET['sortDirection']) == "desc") ? "DESC" : "ASC");
				$orderBy = " lastName $direction, firstName $direction";
			}	else if(strToLower($_GET['sort']) == "kidsmeal") {
				$orderBy = " kidsMeal ".((strtolower($_GET['sortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
			}	else if(strToLower($_GET['sort']) == "additional") {
				$orderBy = " additionalAttendee ".((strtolower($_GET['sortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
			}	else if(strToLower($_GET['sort']) == "vegetarian") {
				$orderBy = " veggieMeal ".((strtolower($_GET['sortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
			}			
		}
		$sql .= " ORDER BY ".$orderBy;
		$attendees = $wpdb->get_results($sql);
		$sort = "";
		$sortDirection = "asc";
		if(isset($_GET['sort'])) {
			$sort = $_GET['sort'];
		}
		
		if(isset($_GET['sortDirection'])) {
			$sortDirection = $_GET['sortDirection'];
		}
	?>
		<script type="text/javascript" language="javascript" 
			src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery-ui-1.7.2.custom/js/jquery-1.3.2.min.js"></script>
		<script type="text/javascript" language="javascript">
			$(document).ready(function() {
				$("#cb").click(function() {
					if($("#cb").attr("checked")) {
						$("input[name='attendee[]']").attr("checked", "checked");
					} else {
						$("input[name='attendee[]']").removeAttr("checked");
					}
				});
			});
		</script>
		<div class="wrap">	
			<div id="icon-edit" class="icon32"><br /></div>	
			<h2>List of current attendees</h2>
			<form method="post" id="rsvp-form" enctype="multipart/form-data">
				<input type="hidden" id="rsvp-bulk-action" name="rsvp-bulk-action" />
				<input type="hidden" id="sortValue" name="sortValue" value="<?php echo htmlentities($sort, ENT_QUOTES); ?>" />
				<input type="hidden" name="exportSortDirection" value="<?php echo htmlentities($sortDirection, ENT_QUOTES); ?>" />
				<div class="tablenav">
					<div class="alignleft actions">
						<select id="rsvp-action-top" name="action">
							<option value="" selected="selected"><?php _e('Bulk Actions', 'rsvp'); ?></option>
							<option value="delete"><?php _e('Delete', 'rsvp'); ?></option>
						</select>
						<input type="submit" value="<?php _e('Apply', 'rsvp'); ?>" name="doaction" id="doaction" class="button-secondary action" onclick="document.getElementById('rsvp-bulk-action').value = document.getElementById('rsvp-action-top').value;" />
						<input type="submit" value="<?php _e('Export Attendees', 'rsvp'); ?>" name="exportButton" id="exportButton" class="button-secondary action" onclick="document.getElementById('rsvp-bulk-action').value = 'export';" />
					</div>
					<?php
						$yesResults = $wpdb->get_results("SELECT COUNT(*) AS yesCount FROM ".ATTENDEES_TABLE." WHERE rsvpStatus = 'Yes'");
						$noResults = $wpdb->get_results("SELECT COUNT(*) AS noCount FROM ".ATTENDEES_TABLE." WHERE rsvpStatus = 'No'");
						$noResponseResults = $wpdb->get_results("SELECT COUNT(*) AS noResponseCount FROM ".ATTENDEES_TABLE." WHERE rsvpStatus = 'NoResponse'");
					?>
					<div class="alignright">RSVP Count -  
						Yes: <strong><?php echo $yesResults[0]->yesCount; ?></strong> &nbsp; &nbsp;  &nbsp; &nbsp; 
						No: <strong><?php echo $noResults[0]->noCount; ?></strong> &nbsp; &nbsp;  &nbsp; &nbsp; 
						No Response: <strong><?php echo $noResponseResults[0]->noResponseCount; ?></strong>
					</div>
					<div class="clear"></div>
				</div>
			<table class="widefat post fixed" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox" id="cb" /></th>
						<th scope="col" id="attendeeName" class="manage-column column-title" style="">Attendee</a> &nbsp;
							<a href="admin.php?page=rsvp-top-level&amp;sort=attendee&amp;sortDirection=asc">
								<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/uparrow<?php 
									echo ((($sort == "attendee") && ($sortDirection == "asc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
									alt="Sort Ascending Attendee Status" title="Sort Ascending Attendee Status" border="0"></a> &nbsp;
							<a href="admin.php?page=rsvp-top-level&amp;sort=attendee&amp;sortDirection=desc">
								<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/downarrow<?php 
									echo ((($sort == "attendee") && ($sortDirection == "desc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
									alt="Sort Descending Attendee Status" title="Sort Descending Attendee Status" border="0"></a>
						</th>			
						<th scope="col" id="rsvpStatus" class="manage-column column-title" style="">RSVP Status &nbsp;
							<a href="admin.php?page=rsvp-top-level&amp;sort=rsvpStatus&amp;sortDirection=asc">
								<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/uparrow<?php 
									echo ((($sort == "rsvpStatus") && ($sortDirection == "asc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
									alt="Sort Ascending RSVP Status" title="Sort Ascending RSVP Status" border="0"></a> &nbsp;
							<a href="admin.php?page=rsvp-top-level&amp;sort=rsvpStatus&amp;sortDirection=desc">
								<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/downarrow<?php 
									echo ((($sort == "rsvpStatus") && ($sortDirection == "desc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
									alt="Sort Descending RSVP Status" title="Sort Descending RSVP Status" border="0"></a>
						</th>
						<?php if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {?>
						<th scope="col" id="kidsMeal" class="manage-column column-title" style="">Kids Meal	 &nbsp;
								<a href="admin.php?page=rsvp-top-level&amp;sort=kidsMeal&amp;sortDirection=asc">
									<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/uparrow<?php 
										echo ((($sort == "kidsMeal") && ($sortDirection == "asc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
										alt="Sort Ascending Kids Meal Status" title="Sort Ascending Kids Meal Status" border="0"></a> &nbsp;
								<a href="admin.php?page=rsvp-top-level&amp;sort=kidsMeal&amp;sortDirection=desc">
									<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/downarrow<?php 
										echo ((($sort == "kidsMeal") && ($sortDirection == "desc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
										alt="Sort Descending Kids Meal Status" title="Sort Descending Kids Meal Status" border="0"></a>
						</th>
						<?php } ?>
						<th scope="col" id="additionalAttendee" class="manage-column column-title" style="">Additional Attendee		 &nbsp;
									<a href="admin.php?page=rsvp-top-level&amp;sort=additional&amp;sortDirection=asc">
										<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/uparrow<?php 
											echo ((($sort == "additional") && ($sortDirection == "asc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
											alt="Sort Ascending Additional Attendees Status" title="Sort Ascending Additional Attendees Status" border="0"></a> &nbsp;
									<a href="admin.php?page=rsvp-top-level&amp;sort=additional&amp;sortDirection=desc">
										<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/downarrow<?php 
											echo ((($sort == "additional") && ($sortDirection == "desc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
											alt="Sort Descending Additional Attendees Status" title="Sort Descending Additional Atttendees Status" border="0"></a>
						</th>
						<?php if(get_option(OPTION_HIDE_VEGGIE) != "Y") {?>
						<th scope="col" id="veggieMeal" class="manage-column column-title" style="">Vegetarian			 &nbsp;
										<a href="admin.php?page=rsvp-top-level&amp;sort=vegetarian&amp;sortDirection=asc">
											<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/uparrow<?php 
												echo ((($sort == "vegetarian") && ($sortDirection == "asc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
												alt="Sort Ascending Vegetarian Status" title="Sort Ascending Vegetarian Status" border="0"></a> &nbsp;
										<a href="admin.php?page=rsvp-top-level&amp;sort=vegetarian&amp;sortDirection=desc">
											<img src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/downarrow<?php 
												echo ((($sort == "vegetarian") && ($sortDirection == "desc")) ? "_selected" : ""); ?>.gif" width="11" height="9" 
												alt="Sort Descending Vegetarian Status" title="Sort Descending Vegetarian Status" border="0"></a>
						</th>
						<?php } ?>
						<th scope="col" id="note" class="manage-column column-title" style="">Custom Message</th>
						<th scope="col" id="note" class="manage-column column-title" style="">Note</th>
						<th scope="col" id="associatedAttendees" class="manage-column column-title" style="">Associated Attendees</th>
					</tr>
				</thead>
			</table>
			<div style="overflow: auto;height: 450px;">
				<table class="widefat post fixed" cellspacing="0">
				<?php
					$i = 0;
					foreach($attendees as $attendee) {
					?>
						<tr class="<?php echo (($i % 2 == 0) ? "alternate" : ""); ?> author-self">
							<th scope="row" class="check-column"><input type="checkbox" name="attendee[]" value="<?php echo $attendee->id; ?>" /></th>						
							<td>
								<a href="<?php echo get_option("siteurl"); ?>/wp-admin/admin.php?page=rsvp-admin-guest&amp;id=<?php echo $attendee->id; ?>"><?php echo htmlentities(stripslashes($attendee->firstName)." ".stripslashes($attendee->lastName)); ?></a>
							</td>
							<td><?php echo $attendee->rsvpStatus; ?></td>
							<?php if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {?>
							<td><?php 
								if($attendee->rsvpStatus == "NoResponse") {
									echo "--";
								} else {
									echo (($attendee->kidsMeal == "Y") ? "Yes" : "No"); 
								}?></td>
								<?php } ?>
							<td><?php 
								if($attendee->rsvpStatus == "NoResponse") {
									echo "--";
								} else {
									echo (($attendee->additionalAttendee == "Y") ? "Yes" : "No"); 
								}
							?></td>
							<?php if(get_option(OPTION_HIDE_VEGGIE) != "Y") {?>
							<td><?php 
								if($attendee->rsvpStatus == "NoResponse") {
									echo "--";
								} else {
									echo (($attendee->veggieMeal == "Y") ? "Yes" : "No"); 
								}	
									?></td>
							<?php } ?>
							<td><?php
								echo nl2br(stripslashes(trim($attendee->personalGreeting)));
							?></td>
							<td><?php
								echo nl2br(stripslashes(trim($attendee->note)));
							?></td>
							<td>
							<?php
								$sql = "SELECT firstName, lastName FROM ".ATTENDEES_TABLE." 
								 	WHERE id IN (SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = %d) 
										OR id in (SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d)";
							
								$associations = $wpdb->get_results($wpdb->prepare($sql, $attendee->id, $attendee->id));
								foreach($associations as $a) {
									echo htmlentities($a->firstName." ".$a->lastName)."<br />";
								}
							?>
							</td>
						</tr>
					<?php
						$i++;
					}
				?>
				</table>
			</div>
			</form>
		</div>
	<?php
	}
	
	function rsvp_admin_export() {
		global $wpdb;
			$sql = "SELECT id, firstName, lastName, rsvpStatus, note, kidsMeal, additionalAttendee, veggieMeal 
							FROM ".ATTENDEES_TABLE;
							
							$orderBy = " lastName, firstName";
							if(isset($_POST['sortValue'])) {
								if(strToLower($_POST['sortValue']) == "rsvpstatus") {
									$orderBy = " rsvpStatus ".((strtolower($_POST['exportSortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
								}else if(strToLower($_POST['sortValue']) == "attendee") {
									$direction = ((strtolower($_POST['exportSortDirection']) == "desc") ? "DESC" : "ASC");
									$orderBy = " lastName $direction, firstName $direction";
								}	else if(strToLower($_POST['sortValue']) == "kidsmeal") {
									$orderBy = " kidsMeal ".((strtolower($_POST['exportSortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
								}	else if(strToLower($_POST['sortValue']) == "additional") {
									$orderBy = " additionalAttendee ".((strtolower($_POST['exportSortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
								}	else if(strToLower($_POST['sortValue']) == "vegetarian") {
									$orderBy = " veggieMeal ".((strtolower($_POST['exportSortDirection']) == "desc") ? "DESC" : "ASC") .", ".$orderBy;
								}			
							}
							$sql .= " ORDER BY ".$orderBy;
			$attendees = $wpdb->get_results($sql);
			$csv = "\"Attendee\",\"RSVP Status\",";
			
			if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {
				$csv .= "\"Kids Meal\",";
			}
			$csv .= "\"Additional Attendee\",";
			
			if(get_option(OPTION_HIDE_VEGGIE) != "Y") {
				$csv .= "\"Vegatarian\",";
			}
			$csv .= "\"Note\",\"Associated Attendees\"";
			
			$qRs = $wpdb->get_results("SELECT id, question FROM ".QUESTIONS_TABLE." ORDER BY sortOrder, id");
			if(count($qRs) > 0) {
				foreach($qRs as $q) {
					$csv .= ",\"".stripslashes($q->question)."\"";
				}
			}
			
			$csv .= "\r\n";
			foreach($attendees as $a) {
				$csv .= "\"".stripslashes($a->firstName." ".$a->lastName)."\",\"".($a->rsvpStatus)."\",";
				
				if(get_option(OPTION_HIDE_KIDS_MEAL) != "Y") {
					$csv .= "\"".(($a->kidsMeal == "Y") ? "Yes" : "No")."\",";
				}
				
				$csv .= "\"".(($a->additionalAttendee == "Y") ? "Yes" : "No")."\",";
				
				if(get_option(OPTION_HIDE_VEGGIE) != "Y") {
					$csv .= "\"".(($a->veggieMeal == "Y") ? "Yes" : "No")."\",";
				}
				
				$csv .= "\"".(str_replace("\"", "\"\"", stripslashes($a->note)))."\",\"";
			
				$sql = "SELECT firstName, lastName FROM ".ATTENDEES_TABLE." 
				 	WHERE id IN (SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = %d) 
						OR id in (SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeID = %d)";
		
				$associations = $wpdb->get_results($wpdb->prepare($sql, $a->id, $a->id));
				foreach($associations as $assc) {
					$csv .= trim(stripslashes($assc->firstName." ".$assc->lastName))."\r\n";
				}
				$csv .= "\"";
				
				$qRs = $wpdb->get_results("SELECT id, question FROM ".QUESTIONS_TABLE." ORDER BY sortOrder, id");
				if(count($qRs) > 0) {
					foreach($qRs as $q) {
						$aRs = $wpdb->get_results($wpdb->prepare("SELECT answer FROM ".ATTENDEE_ANSWERS." WHERE attendeeID = %d AND questionID = %d", $a->id, $q->id));
						if(count($aRs) > 0) {
							$csv .= ",\"".stripslashes($aRs[0]->answer)."\"";
						} else {
							$csv .= ",\"\"";
						}
					}
				}
				
				$csv .= "\r\n";
			}
			if(isset($_SERVER['HTTP_USER_AGENT']) && preg_match("/MSIE/", $_SERVER['HTTP_USER_AGENT'])) {
				// IE Bug in download name workaround
				ini_set( 'zlib.output_compression','Off' );
			}
			header('Content-Description: RSVP Export');
			header("Content-Type: application/vnd.ms-excel", true);
			header('Content-Disposition: attachment; filename="rsvpEntries.csv"'); 
			echo $csv;
			exit();
	}
	
	function rsvp_admin_import() {
		global $wpdb;
		if(count($_FILES) > 0) {
			check_admin_referer('rsvp-import');
			require_once("Excel/reader.php");
			$data = new Spreadsheet_Excel_Reader();
			$data->read($_FILES['importFile']['tmp_name']);
			if($data->sheets[0]['numCols'] >= 2) {
				$count = 0;
				for ($i = 1; $i <= $data->sheets[0]['numRows']; $i++) {
					$fName = trim($data->sheets[0]['cells'][$i][1]);
					$lName = trim($data->sheets[0]['cells'][$i][2]);
					$personalGreeting = (isset($data->sheets[0]['cells'][$i][4])) ? $personalGreeting = $data->sheets[0]['cells'][$i][4] : "";
					if(!empty($fName) && !empty($lName)) {
						$sql = "SELECT id FROM ".ATTENDEES_TABLE." 
						 	WHERE firstName = %s AND lastName = %s ";
						$res = $wpdb->get_results($wpdb->prepare($sql, $fName, $lName));
						if(count($res) == 0) {
							$wpdb->insert(ATTENDEES_TABLE, array("firstName" 				=> $fName, 
																									 "lastName" 				=> $lName,
																									 "personalGreeting" => $personalGreeting), 
																						 array('%s', '%s', '%s'));
							$count++;
						}
					}
				}
				
				if($data->sheets[0]['numCols'] >= 3) {
					// There must be associated users so let's associate them
					for ($i = 1; $i <= $data->sheets[0]['numRows']; $i++) {
						$fName = trim($data->sheets[0]['cells'][$i][1]);
						$lName = trim($data->sheets[0]['cells'][$i][2]);
						if(!empty($fName) && !empty($lName) && (count($data->sheets[0]['cells'][$i]) >= 3)) {
							// Get the user's id 
							$sql = "SELECT id FROM ".ATTENDEES_TABLE." 
							 	WHERE firstName = %s AND lastName = %s ";
							$res = $wpdb->get_results($wpdb->prepare($sql, $fName, $lName));
							if((count($res) > 0) && isset($data->sheets[0]['cells'][$i][3])) {
								$userId = $res[0]->id;
								
								// Deal with the assocaited users...
								$associatedUsers = explode(",", trim($data->sheets[0]['cells'][$i][3]));
								if(is_array($associatedUsers)) {
									foreach($associatedUsers as $au) {
										$user = explode(" ", trim($au), 2);
										// Three cases, they didn't enter in all of the information, user exists or doesn't.  
										// If user exists associate the two users
										// If user does not exist add the user and then associate the two
										if(is_array($user) && (count($user) == 2)) {
											$sql = "SELECT id FROM ".ATTENDEES_TABLE." 
											 	WHERE firstName = %s AND lastName = %s ";
											$userRes = $wpdb->get_results($wpdb->prepare($sql, trim($user[0]), trim($user[1])));
											if(count($userRes) > 0) {
												$newUserId = $userRes[0]->id;
											} else {
												// Insert them and then we can associate them...
												$wpdb->insert(ATTENDEES_TABLE, array("firstName" => trim($user[0]), "lastName" => trim($user[1])), array('%s', '%s'));
												$newUserId = $wpdb->insert_id;
												$count++;
											}
											
											$wpdb->insert(ASSOCIATED_ATTENDEES_TABLE, array("attendeeID" => $newUserId, 
																																			"associatedAttendeeID" => $userId), 
																																array("%d", "%d"));
																																
											$wpdb->insert(ASSOCIATED_ATTENDEES_TABLE, array("attendeeID" => $userId, 
																																			"associatedAttendeeID" => $newUserId), 
																																array("%d", "%d"));
										}
									}
								}
							}
						}
					}
				}
			?>
			<p><strong><?php echo $count; ?></strong> total records were imported.</p>
			<p>Continue to the RSVP <a href="admin.php?page=rsvp-top-level">list</a></p>
			<?php
			}
		} else {
		?>
			<form name="rsvp_import" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field('rsvp-import'); ?>
				<p>Select an excel file (only xls please, xlsx is not supported....yet) in the following format:<br />
				<strong>First Name</strong> | <strong>Last Name</strong> | <strong>Associated Attendees*</strong> | <strong>Custom Message</strong>
				</p>
				<p>
				* associated attendees should be separated by a comma it is assumed that the first space encounted will separate the first and last name.
				</p>
				<p>A header row is not expected.</p>
				<p><input type="file" name="importFile" id="importFile" /></p>
				<p><input type="submit" value="Import File" name="goRsvp" /></p>
			</form>
		<?php
		}
	}
	
	function rsvp_admin_guest() {
		global $wpdb;
		if((count($_POST) > 0) && !empty($_POST['firstName']) && !empty($_POST['lastName'])) {
			check_admin_referer('rsvp_add_guest');
			if(isset($_SESSION[EDIT_SESSION_KEY]) && is_numeric($_SESSION[EDIT_SESSION_KEY])) {
				$wpdb->update(ATTENDEES_TABLE, 
											array("firstName" => trim($_POST['firstName']), 
											      "lastName" => trim($_POST['lastName']), 
											      "personalGreeting" => trim($_POST['personalGreeting'])), 
											array("id" => $_SESSION[EDIT_SESSION_KEY]), 
											array("%s", "%s", "%s"), 
											array("%d"));
				$attendeeId = $_SESSION[EDIT_SESSION_KEY];
				$wpdb->query($wpdb->prepare("DELETE FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeId = %d", $attendeeId));
			} else {
				$wpdb->insert(ATTENDEES_TABLE, array("firstName" => trim($_POST['firstName']), 
				                                     "lastName" => trim($_POST['lastName']),
																						 "personalGreeting" => trim($_POST['personalGreeting'])), 
				                               array('%s', '%s', '%s'));
				$attendeeId = $wpdb->insert_id;
			}
			
			if(isset($_POST['associatedAttendees']) && is_array($_POST['associatedAttendees'])) {
				foreach($_POST['associatedAttendees'] as $aid) {
					if(is_numeric($aid) && ($aid > 0)) {
						$wpdb->insert(ASSOCIATED_ATTENDEES_TABLE, array("attendeeID"=>$attendeeId, "associatedAttendeeID"=>$aid), array("%d", "%d"));
					}
				}
			}
		?>
			<p>Attendee <?php echo htmlentities($_POST['firstName']." ".$_POST['lastName']);?> has been successfully saved</p>
			<p>
				<a href="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=rsvp-top-level">Continue to Attendee List</a> | 
				<a href="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=rsvp-admin-guest">Add a Guest</a> 
			</p>
	<?php
		} else {
			$attendee = null;
			session_unregister(EDIT_SESSION_KEY);
			$associatedAttendees = array();
			$firstName = "";
			$lastName = "";
			$personalGreeting = "";
			
			if(isset($_GET['id']) && is_numeric($_GET['id'])) {
				$attendee = $wpdb->get_row("SELECT id, firstName, lastName, personalGreeting FROM ".ATTENDEES_TABLE." WHERE id = ".$_GET['id']);
				if($attendee != null) {
					$_SESSION[EDIT_SESSION_KEY] = $attendee->id;
					$firstName = stripslashes($attendee->firstName);
					$lastName = stripslashes($attendee->lastName);
					$personalGreeting = stripslashes($attendee->personalGreeting);
					
					// Get the associated attendees and add them to an array
					$associations = $wpdb->get_results("SELECT associatedAttendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE attendeeId = ".$attendee->id.
																						 " UNION ".
																						 "SELECT attendeeID FROM ".ASSOCIATED_ATTENDEES_TABLE." WHERE associatedAttendeeID = ".$attendee->id);
					foreach($associations as $aId) {
						$associatedAttendees[] = $aId->associatedAttendeeID;
					}
				} 
			} 
	?>
			<form name="contact" action="admin.php?page=rsvp-admin-guest" method="post">
				<?php wp_nonce_field('rsvp_add_guest'); ?>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save'); ?>" />
				</p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="firstName">First Name:</label></th>
						<td align="left"><input type="text" name="firstName" id="firstName" size="30" value="<?php echo htmlentities($firstName); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="lastName">Last Name:</label></th>
						<td align="left"><input type="text" name="lastName" id="lastName" size="30" value="<?php echo htmlentities($lastName); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row" valign="top"><label for="personalGreeting">Custom Message:</label></th>
						<td align="left"><textarea name="personalGreeting" id="personalGreeting" rows="5" cols="40"><?php echo htmlentities($personalGreeting); ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row">Associated Attendees:</th>
						<td align="left">
							<select name="associatedAttendees[]" multiple="multiple" size="5" style="height: 200px;">
								<?php
									$attendees = $wpdb->get_results("SELECT id, firstName, lastName FROM ".$wpdb->prefix."attendees ORDER BY lastName, firstName");
									foreach($attendees as $a) {
										if($a->id != $_SESSION[EDIT_SESSION_KEY]) {
								?>
											<option value="<?php echo $a->id; ?>" 
															<?php echo ((in_array($a->id, $associatedAttendees)) ? "selected=\"selected\"" : ""); ?>><?php echo htmlentities(stripslashes($a->firstName)." ".stripslashes($a->lastName)); ?></option>
								<?php
										}
									}
								?>
							</select>
						</td>
					</tr>
				<?php
				if(($attendee != null) && ($attendee->id > 0)) {
					$sql = "SELECT question, answer FROM ".ATTENDEE_ANSWERS." ans 
						INNER JOIN ".QUESTIONS_TABLE." q ON q.id = ans.questionID 
						WHERE attendeeID = %d 
						ORDER BY q.sortOrder";
					$aRs = $wpdb->get_results($wpdb->prepare($sql, $attendee->id));
					if(count($aRs) > 0) {
				?>
				<tr>
					<td colspan="2">
						<h4>Custom Questions Answered</h4>
						<table cellpadding="2" cellspacing="0" border="0">
							<tr>
								<th>Question</th>
								<th>Answer</th>
							</tr>
				<?php
						foreach($aRs as $a) {
				?>
							<tr>
								<td><?php echo stripslashes($a->question); ?></td>
								<td><?php echo stripslashes($a->answer); ?></td>
							</tr>
				<?php
						}
				?>
						</table>
					</td>
				</tr>
				<?php
					}
				}
				?>
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save'); ?>" />
				</p>
			</form>
<?php
		}
	}
	
	function rsvp_admin_questions() {
		global $wpdb;
		
		if((count($_POST) > 0) && ($_POST['rsvp-bulk-action'] == "delete") && (is_array($_POST['q']) && (count($_POST['q']) > 0))) {
			foreach($_POST['q'] as $q) {
				if(is_numeric($q) && ($q > 0)) {
					$wpdb->query($wpdb->prepare("DELETE FROM ".QUESTIONS_TABLE." WHERE id = %d", $q));
					$wpdb->query($wpdb->prepare("DELETE FROM ".ATTENDEE_ANSWERS." WHERE questionID = %d", $q));
				}
			}
		} else if((count($_POST) > 0) && ($_POST['rsvp-bulk-action'] == "saveSortOrder")) {
			$sql = "SELECT id FROM ".QUESTIONS_TABLE;
			$sortQs = $wpdb->get_results($sql);
			foreach($sortQs as $q) {
				if(is_numeric($_POST['sortOrder'.$q->id]) && ($_POST['sortOrder'.$q->id] >= 0)) {
					$wpdb->update(QUESTIONS_TABLE, 
												array("sortOrder" => $_POST['sortOrder'.$q->id]), 
												array("id" => $q->id), 
												array("%d"), 
												array("%d"));
				}
			}
		}
		
		$sql = "SELECT id, question, sortOrder FROM ".QUESTIONS_TABLE." ORDER BY sortOrder ASC";
		$customQs = $wpdb->get_results($sql);
	?>
		<script type="text/javascript" language="javascript" 
			src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery-ui-1.7.2.custom/js/jquery-1.3.2.min.js"></script>
		<script type="text/javascript" language="javascript" 
			src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery.tablednd_0_5.js"></script>
		<script type="text/javascript" language="javascript">
			$(document).ready(function() {
				$("#cb").click(function() {
					if($("#cb").attr("checked")) {
						$("input[name='q[]']").attr("checked", "checked");
					} else {
						$("input[name='q[]']").removeAttr("checked");
					}
				});
				
				jQuery("#customQuestions").tableDnD({
					onDrop: function(table, row) {
						var rows = table.tBodies[0].rows;
            for (var i=0; i<rows.length; i++) {
                jQuery("#sortOrder" + rows[i].id).val(i);
            }
	        	
					}
				});
			});
		</script>
		<div class="wrap">	
			<div id="icon-edit" class="icon32"><br /></div>	
			<h2>List of current custom questions</h2>
			<form method="post" id="rsvp-form" enctype="multipart/form-data">
				<input type="hidden" id="rsvp-bulk-action" name="rsvp-bulk-action" />
				<div class="tablenav">
					<div class="alignleft actions">
						<select id="rsvp-action-top" name="action">
							<option value="" selected="selected"><?php _e('Bulk Actions', 'rsvp'); ?></option>
							<option value="delete"><?php _e('Delete', 'rsvp'); ?></option>
						</select>
						<input type="submit" value="<?php _e('Apply', 'rsvp'); ?>" name="doaction" id="doaction" class="button-secondary action" onclick="document.getElementById('rsvp-bulk-action').value = document.getElementById('rsvp-action-top').value;" />
						<input type="submit" value="<?php _e('Save Sort Order', 'rsvp'); ?>" name="saveSortButton" id="saveSortButton" class="button-secondary action" onclick="document.getElementById('rsvp-bulk-action').value = 'saveSortOrder';" />
					</div>
					<div class="clear"></div>
				</div>
			<table class="widefat post fixed" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox" id="cb" /></th>
						<th scope="col" id="questionCol" class="manage-column column-title" style="">Question</th>			
					</tr>
				</thead>
			</table>
			<div style="overflow: auto;height: 450px;">
				<table class="widefat post fixed" cellspacing="0" id="customQuestions">
				<?php
					$i = 0;
					foreach($customQs as $q) {
					?>
						<tr class="<?php echo (($i % 2 == 0) ? "alternate" : ""); ?> author-self" id="<?php echo $q->id; ?>">
							<th scope="row" class="check-column"><input type="checkbox" name="q[]" value="<?php echo $q->id; ?>" /></th>						
							<td>
								<a href="<?php echo get_option("siteurl"); ?>/wp-admin/admin.php?page=rsvp-admin-custom-question&amp;id=<?php echo $q->id; ?>"><?php echo htmlentities(stripslashes($q->question)); ?></a>
								<input type="hidden" name="sortOrder<?php echo $q->id; ?>" id="sortOrder<?php echo $q->id; ?>" value="<?php echo $q->sortOrder; ?>" />
							</td>
						</tr>
					<?php
						$i++;
					}
				?>
				</table>
			</div>
			</form>
		</div>
	<?php
	}
	
	function rsvp_admin_custom_question() {
		global $wpdb;
		
		if((count($_POST) > 0) && !empty($_POST['question']) && is_numeric($_POST['questionTypeID'])) {
			check_admin_referer('rsvp_add_custom_question');
			if(isset($_SESSION[EDIT_QUESTION_KEY]) && is_numeric($_SESSION[EDIT_QUESTION_KEY])) {
				$wpdb->update(QUESTIONS_TABLE, 
											array("question" => trim($_POST['question']), 
											      "questionTypeID" => trim($_POST['questionTypeID']), 
														"permissionLevel" => ((trim($_POST['permissionLevel']) == "private") ? "private" : "public")), 
											array("id" => $_SESSION[EDIT_QUESTION_KEY]), 
											array("%s", "%d", "%s"), 
											array("%d"));
				$questionId = $_SESSION[EDIT_QUESTION_KEY];
				
				$answers = $wpdb->get_results($wpdb->prepare("SELECT id FROM ".QUESTION_ANSWERS_TABLE." WHERE questionID = %d", $questionId));
				if(count($answers) > 0) {
					foreach($answers as $a) {
						if(isset($_POST['deleteAnswer'.$a->id]) && (strToUpper($_POST['deleteAnswer'.$a->id]) == "Y")) {
							$wpdb->query($wpdb->prepare("DELETE FROM ".QUESTION_ANSWERS_TABLE." WHERE id = %d", $a->id));
						} elseif(isset($_POST['answer'.$a->id]) && !empty($_POST['answer'.$a->id])) {
							$wpdb->update(QUESTION_ANSWERS_TABLE, 
													  array("answer" => trim($_POST['answer'.$a->id])), 
													  array("id"=>$a->id), 
													  array("%s"), 
													  array("%d"));
						}
					}
				}
			} else {
				$wpdb->insert(QUESTIONS_TABLE, array("question" => trim($_POST['question']), 
				                                     "questionTypeID" => trim($_POST['questionTypeID']), 
																						 "permissionLevel" => ((trim($_POST['permissionLevel']) == "private") ? "private" : "public")),  
				                               array('%s', '%d', '%s'));
				$questionId = $wpdb->insert_id;
			}
			
			if(isset($_POST['numNewAnswers']) && is_numeric($_POST['numNewAnswers']) && 
			   (($_POST['questionTypeID'] == 2) || ($_POST['questionTypeID'] == 4))) {
				for($i = 0; $i < $_POST['numNewAnswers']; $i++) {
					if(isset($_POST['newAnswer'.$i]) && !empty($_POST['newAnswer'.$i])) {
						$wpdb->insert(QUESTION_ANSWERS_TABLE, array("questionID"=>$questionId, "answer"=>$_POST['newAnswer'.$i]));
					}
				}
			}
			
			if(strToLower(trim($_POST['permissionLevel'])) == "private") {
				$wpdb->query($wpdb->prepare("DELETE FROM ".QUESTION_ATTENDEES_TABLE." WHERE questionID = %d", $questionId));
				if(isset($_POST['attendees']) && is_array($_POST['attendees'])) {
					foreach($_POST['attendees'] as $aid) {
						if(is_numeric($aid) && ($aid > 0)) {
							$wpdb->insert(QUESTION_ATTENDEES_TABLE, array("attendeeID"=>$aid, "questionID"=>$questionId), array("%d", "%d"));
						}
					}
				}
			}
		?>
			<p>Custom Question saved</p>
			<p>
				<a href="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=rsvp-admin-questions">Continue to Question List</a> | 
				<a href="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=rsvp-admin-custom-question">Add another Question</a> 
			</p>
		<?php
		} else {
			$questionTypeId = 0;
			$question = "";
			$isNew = true;
			$questionId = 0;
			$permissionLevel = "public";
			$savedAttendees = array();
			session_unregister(EDIT_QUESTION_KEY);
			if(isset($_GET['id']) && is_numeric($_GET['id'])) {
				$qRs = $wpdb->get_results($wpdb->prepare("SELECT id, question, questionTypeID, permissionLevel FROM ".QUESTIONS_TABLE." WHERE id = %d", $_GET['id']));
				if(count($qRs) > 0) {
					$isNew = false;
					$_SESSION[EDIT_QUESTION_KEY] = $qRs[0]->id;
					$questionId = $qRs[0]->id;
					$question = stripslashes($qRs[0]->question);
					$permissionLevel = stripslashes($qRs[0]->permissionLevel);
					$questionTypeId = $qRs[0]->questionTypeID;
					
					if($permissionLevel == "private") {
						$aRs = $wpdb->get_results($wpdb->prepare("SELECT attendeeID FROM ".QUESTION_ATTENDEES_TABLE." WHERE questionID = %d", $questionId));
						if(count($aRs) > 0) {
							foreach($aRs as $a) {
								$savedAttendees[] = $a->attendeeID;
							}
						}
					}
				}
			} 
			
			$sql = "SELECT id, questionType, friendlyName FROM ".QUESTION_TYPE_TABLE;
			$questionTypes = $wpdb->get_results($sql);
			?>
				<script type="text/javascript" language="javascript" 
					src="<?php echo get_option("siteurl"); ?>/wp-content/plugins/rsvp/jquery-ui-1.7.2.custom/js/jquery-1.3.2.min.js"></script>
				<script type="text/javascript">
					function addAnswer(counterElement) {
						var currAnswer = $("#numNewAnswers").val();
						if(isNaN(currAnswer)) {
							currAnswer = 0;
						}
				
						var s = "<tr>\r\n"+ 
							"<td align=\"right\" width=\"75\"><label for=\"newAnswer" + currAnswer + "\">Answer:</label></td>\r\n" + 
							"<td><input type=\"text\" name=\"newAnswer" + currAnswer + "\" id=\"newAnswer" + currAnswer + "\" size=\"40\" /></td>\r\n" + 
						"</tr>\r\n";
						$("#answerContainer").append(s);
						currAnswer++;
						$("#numNewAnswers").val(currAnswer);
						return false;
					}
				
					$(document).ready(function() {
						
						<?php
						if($isNew || (($questionTypeId != 2) && ($questionTypeId != 4))) {
						 	echo '$("#answerContainer").hide();';
						}
						
						if($isNew || ($permissionLevel == "public")) {
						?>
							jQuery("#attendeesArea").hide();
						<?php
						}
						?>
						$("#questionType").change(function() {
							var selectedValue = $("#questionType").val();
							if((selectedValue == 2) || (selectedValue == 4)) {
								$("#answerContainer").show();
							} else {
								$("#answerContainer").hide();
							}
						})
						
						jQuery("#permissionLevel").change(function() {
							if(jQuery("#permissionLevel").val() != "public") {
								jQuery("#attendeesArea").show();
							} else {
								jQuery("#attendeesArea").hide();
							}
						})
					});
				</script>
				<form name="contact" action="admin.php?page=rsvp-admin-custom-question" method="post">
					<input type="hidden" name="numNewAnswers" id="numNewAnswers" value="0" />
					<?php wp_nonce_field('rsvp_add_custom_question'); ?>
					<p class="submit">
						<input type="submit" class="button-primary" value="<?php _e('Save'); ?>" />
					</p>
					<table id="customQuestions" class="form-table">
						<tr valign="top">
							<th scope="row"><label for="questionType">Question Type:</label></th>
							<td align="left"><select name="questionTypeID" id="questionType" size="1">
								<?php
									foreach($questionTypes as $qt) {
										echo "<option value=\"".$qt->id."\" ".(($questionTypeId == $qt->id) ? " selected=\"selected\"" : "").">".$qt->friendlyName."</option>\r\n";
									}
								?>
							</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="question">Question:</label></th>
							<td align="left"><input type="text" name="question" id="question" size="40" value="<?php echo htmlentities($question); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="permissionLevel">Question Permission Level:</label></th>
							<td align="left"><select name="permissionLevel" id="permissionLevel" size="1">
								<option value="public" <?php echo ($permissionLevel == "public") ? " selected=\"selected\"" : ""; ?>>Public</option>
								<option value="private" <?php echo ($permissionLevel == "private") ? " selected=\"selected\"" : ""; ?>>Private</option>
							</select></td>
						</tr>
						<tr>
							<td colspan="2">
								<table cellpadding="0" cellspacing="0" border="0" id="answerContainer">
									<tr>
										<th>Answers</th>
										<th align="right"><a href="#" onclick="return addAnswer();">Add new Answer</a></th>
									</tr>
									<?php
									if(!$isNew) {
										$aRs = $wpdb->get_results($wpdb->prepare("SELECT id, answer FROM ".QUESTION_ANSWERS_TABLE." WHERE questionID = %d", $questionId));
										if(count($aRs) > 0) {
											foreach($aRs as $answer) {
										?>
												<tr>
													<td width="75" align="right"><label for="answer<?php echo $answer->id; ?>">Answer:</label></td>
													<td><input type="text" name="answer<?php echo $answer->id; ?>" id="answer<?php echo $answer->id; ?>" size="40" value="<?php echo htmlentities(stripslashes($answer->answer)); ?>" />
													 &nbsp; <input type="checkbox" name="deleteAnswer<?php echo $answer->id; ?>" id="deleteAnswer<?php echo $answer->id; ?>" value="Y" /><label for="deleteAnswer<?php echo $answer->id; ?>">Delete</label></td>
												</tr>
										<?
											}
										}
									}
									?>
								</table>
							</td>
						</tr>
						<tr id="attendeesArea">
							<th scope="row"><label for="attendees">Attendees allowed to answer this question:</label></th>
							<td>
								<select name="attendees[]" id="attendees" style="height:75px;" multiple="multiple">
								<?php
									$attendees = $wpdb->get_results("SELECT id, firstName, lastName FROM ".$wpdb->prefix."attendees ORDER BY lastName, firstName");
									foreach($attendees as $a) {
								?>
									<option value="<?php echo $a->id; ?>" 
													<?php echo ((in_array($a->id, $savedAttendees)) ? " selected=\"selected\"" : ""); ?>><?php echo htmlentities(stripslashes($a->firstName)." ".stripslashes($a->lastName)); ?></option>
								<?php
									}
								?>
								</select>
							</td>
						</tr>
					</table>
				</form>
		<?php
		}
	}
	
	function rsvp_modify_menu() {
		
		add_options_page('RSVP Options',	//page title
	                   'RSVP Options',	//subpage title
	                   'manage_options',	//access
	                   'rsvp-options',		//current file
	                   'rsvp_admin_guestlist_options'	//options function above
	                   );
		add_menu_page("RSVP Plugin", 
									"RSVP Plugin", 
									"publish_posts", 
									"rsvp-top-level", 
									"rsvp_admin_guestlist");
		add_submenu_page("rsvp-top-level", 
										 "Add Guest",
										 "Add Guest",
										 "publish_posts", 
										 "rsvp-admin-guest",
										 "rsvp_admin_guest");
		add_submenu_page("rsvp-top-level", 
										 "RSVP Export",
										 "RSVP Export",
										 "publish_posts", 
										 "rsvp-admin-export",
										 "rsvp_admin_export");
		add_submenu_page("rsvp-top-level", 
										 "RSVP Import",
										 "RSVP Import",
										 "publish_posts", 
										 "rsvp-admin-import",
										 "rsvp_admin_import");
		add_submenu_page("rsvp-top-level", 
										 "Custom Questions",
										 "Custom Questions",
										 "publish_posts", 
										 "rsvp-admin-questions",
										 "rsvp_admin_questions");
		add_submenu_page("rsvp-top-level", 
										 "Add Custom Question",
										 "Add Custom Question",
										 "publish_posts", 
										 "rsvp-admin-custom-question",
										 "rsvp_admin_custom_question");
	}
	
	function rsvp_register_settings() {
		register_setting('rsvp-option-group', OPTION_OPENDATE);
		register_setting('rsvp-option-group', OPTION_GREETING);
		register_setting('rsvp-option-group', OPTION_THANKYOU);
		register_setting('rsvp-option-group', OPTION_HIDE_VEGGIE);
		register_setting('rsvp-option-group', OPTION_HIDE_KIDS_MEAL);
		register_setting('rsvp-option-group', OPTION_NOTE_VERBIAGE);
		register_setting('rsvp-option-group', OPTION_VEGGIE_MEAL_VERBIAGE);
		register_setting('rsvp-option-group', OPTION_KIDS_MEAL_VERBIAGE);
		register_setting('rsvp-option-group', OPTION_YES_VERBIAGE);
		register_setting('rsvp-option-group', OPTION_NO_VERBIAGE);
		register_setting('rsvp-option-group', OPTION_DEADLINE);
		register_setting('rsvp-option-group', OPTION_THANKYOU);
		register_setting('rsvp-option-group', OPTION_HIDE_ADD_ADDITIONAL);
		register_setting('rsvp-option-group', OPTION_NOTIFY_EMAIL);
		register_setting('rsvp-option-group', OPTION_NOTIFY_ON_RSVP);
	}
	
	add_action('admin_menu', 'rsvp_modify_menu');
	add_action('admin_init', 'rsvp_register_settings');
	add_filter('the_content', 'rsvp_frontend_handler');
	register_activation_hook(__FILE__,'rsvp_database_setup');
?>