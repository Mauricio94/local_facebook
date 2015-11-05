<?php 
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**

*
* @package    local
* @subpackage facebook
* @copyright  2013 Francisco GarcÃ­a Ralph (francisco.garcia.ralph@gmail.com)
* 			  2015 Mihail Pozarski (mipozarski@alumnos.uai.cl)
* 			  2015 Hans Jeria (hansjeria@gmail.com)
* 			  2015 Mauricio Meza (mameza@alumnos.uai.cl)
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once (dirname ( dirname ( dirname ( __FILE__ ) ) ) . "/config.php");
include "app/config.php";
global $DB, $USER, $CFG;
require_once ($CFG->dirroot . "/local/facebook/forms.php");
use Facebook\FacebookResponse;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequire;
use Facebook\FacebookSDKException;

$facebook = new Facebook\Facebook ($config);
$app_name = $CFG->fbkAppNAME;
$app_id = $CFG->fbkAppID;
$app_secret = $CFG->fbkScrID;
$helper = $facebook->getRedirectLoginHelper();
$app_url="http://webcursos-d.uai.cl/local/facebook/connect.php";

require_login (); // Require log in.

// URL for current page
$url = new moodle_url ( "/local/facebook/connect.php" );

$context = context_system::instance ();

$PAGE->set_url ( $url );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( "standard" );
$PAGE->set_title(get_string("connecttitle", "local_facebook"));
$connect = optional_param ( "code", null, PARAM_TEXT );
$disconnect = optional_param ( "disconnect", null, PARAM_TEXT );

$PAGE->navbar->add ( get_string ( "facebook", "local_facebook" ) );

try {
	if (isset($_SESSION['facebook_access_token'])) {
		$accessToken = $_SESSION['facebook_access_token'];
	} else {
		$accessToken = $helper->getAccessToken();
	}
} catch(Facebook\Exceptions\FacebookResponseException $e) {
	// When Graph returns an error
	echo 'Graph returned an error: ' . $e->getMessage();
	exit;
} catch(Facebook\Exceptions\FacebookSDKException $e) {
	// When validation fails or other local issues
	echo 'Facebook SDK returned an error: ' . $e->getMessage();
	exit;
}
if (isset($accessToken)) {
	if (isset($_SESSION['facebook_access_token'])) {
		$facebook->setDefaultAccessToken($_SESSION['facebook_access_token']);
	} else {
		// getting short-lived access token
		$_SESSION['facebook_access_token'] = (string) $accessToken;
		// OAuth 2.0 client handler
		$oAuth2Client = $facebook->getOAuth2Client();
		// Exchanges a short-lived access token for a long-lived one
		$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($_SESSION['facebook_access_token']);
		$_SESSION['facebook_access_token'] = (string) $longLivedAccessToken;
		// setting default access token to be used in script
		$facebook->setDefaultAccessToken($_SESSION['facebook_access_token']);
	}
}
echo $OUTPUT->header ();

// busco si el usuario tiene enlazada la cuenta
$user_info = $DB->get_record ( "facebook_user", array (
		"moodleid" => $USER->id,
		"status" => 1 
) );

$time = time ();
// Look if the user has accepted the permissions
// if by looking the facebook_id is 0, that means the user hasn"t accepted it.

// if the status is 0 is because the user has unlink the facebook account and if the $user_info is null is because the user hasn"t link the account yet.
// if any of these things happend it will give the user the option to link the account

if (isset ( $user_info->status )) {
	// If the user press the unlink account
	if ($disconnect != NULL) {
		
		// Save all the user info but with status 0
		$record = new stdClass ();
		$record->id = $user_info->id;
		$record->moodleid = $USER->id;
		$record->facebookid = $user_info->facebookid;
		$record->timemodified = $time;
		$record->status = 0;
		$record->lasttimechecked = $time;
		// Update the DB to deactivate the account.
		$DB->update_record ( "facebook_user", $record );
		echo $OUTPUT->heading ( get_string ( "succesfullconnect", "local_facebook" ), 3 ) . "<a href='../../'>" . get_string ( "back", "local_facebook" ) . "</a>>";
	} else {
		$facebook_id = $user_info->facebookid;
		$status = $user_info->status;
		echo $OUTPUT->heading ( get_string ( "connectheading", "local_facebook" ) );
		// Facebook code to search the user information.
		// We have a user ID, so probably a logged in user.
		// If not, we"ll get an exception, which we handle below.
		try {
			if (isset($accessToken)) {
				// Logged in!
				$profile_request = $facebook->get('/me?fields=name,first_name,middle_name,last_name,link');
				$profile = $profile_request->getGraphNode()->asArray();
				$link = $profile["link"];
				$first_name = $profile["first_name"];
				if (isset ( $profile ["middle_name"] )) {
					$middle_name = $profile ["middle_name"];
				} else {
					$middle_name = "";
				}
				$last_name = $profile ["last_name"];
				// Now you can redirect to another page and use the
				// access token from $_SESSION['facebook_access_token']
			} elseif ($helper->getError()) {
				// The user denied the request
				exit;
			}
					
		} catch ( FacebookApiException $e ) {
			// If the user is logged out, you can have a
			// user ID even though the access token is invalid.
			// In this case, we"ll get an exception, so we"ll
			// just ask the user to login again here.
			$loginUrl = $helper->getLoginUrl($app_url, $params);
			echo "Please <a href='" . $login_Url . "'>login.</a>";
			error_log ( $e->getType () );
			error_log ( $e->getMessage () );
		}
		
		$table = table_generator ( 
				$facebook_id, 
				$link, 
				$first_name,
				$middle_name,
				$last_name,
				$app_name
		);
		
		$button = new buttons ();
		$button->display ();
	}
} else {
	
	// If he clicked the link button.
	if ($connect != NULL) {
		
		// If the user wants to link an account that was already linked, but was unlinked that means with status 0
				$user_inactive = $DB->get_record ( "facebook_user", array (
				"moodleid" => $USER->id,
				"status" => 0 
		) );
		
		if (isset($user_inactive)) {
			
			$user_inactive->timemodified = $time;
			$user_inactive->status = "1";
			$user_inactive->lasttimechecked = $time;
			$DB->update_record ( "facebook_user", $user_inactive );
			echo "<script>location.reload();</script>";
		}  // If the user wants to link a account that was never linked before.
		else {
			$profile_request = $facebook->get('/me?fields=name,first_name,middle_name,last_name,link');
			$profile = $profile_request->getGraphNode()->asArray();
			$facebook_id = $profile["id"];
			
			$record = new stdClass ();
			$record->moodleid = $USER->id;
			$record->facebookid = $facebook_id;
			$record->timemodified = $time;
			$record->status = "1";
			$record->lasttimechecked = $time;
			if ($facebook_id != 0) {
				$DB->insert_record ( "facebook_user", $record );
			}
			echo "<script>location.reload();</script>";
		}
	} else {
		echo $OUTPUT->heading ( get_string ( "acountconnect", "local_facebook" ) );
		$params = [	"email",
				"publish_actions",
				"user_birthday",
				"user_tagged_places",
				"user_work_history",
				"user_about_me",
				"user_hometown",
				"user_actions.books",
				"user_education_history",
				"user_likes",
				"user_friends",
				"user_religion_politics"
		];
		$loginUrl = $helper->getLoginUrl($app_url, $params);
		
		echo "<br><center><a href='" . $loginUrl . "'><img src='app/images/login.jpg'width='180' height='30'></a><center>";
		
	}
}
// if the user has the account linkd it will show his information and some other actions the user can perform.
echo $OUTPUT->footer ();
function table_generator($facebook_id, $link, $first_name, $middle_name, $last_name, $appname) {
	$img = "<img src='https://graph.facebook.com/" . $facebook_id . "/picture?type=large'>";
	$table2 = new html_table ();
	$table = new html_table();
	$table->data[]= array(
						'',
						''
	);
	$table->data[]= array(
						get_string('fbktablename', 'local_facebook'),
						$first_name
	);
	$table->data[]= array(
						'', 
						''
	);
	$table->data[]= array(
						get_string('fbktablelastname', 'local_facebook'), 
						$middle_name.' '.$last_name
	);
	$table->data[]= array(
						'',
						'');
	$table->data[]= array(
						get_string('profile', 'local_facebook'), 
						'<a href="'.$link.'" target=”_blank”>'.$link.'</a>'
	);
	if($appname!=null){
	$table->data[]= array(
						'Link a la app', 
						'<a href="http://apps.facebook.com/'.$appname.'" target=”_blank”>http://apps.facebook.com/'.$appname.'</a>');
	}
	else{
		$table->data[]= array('', '');
		
		
	}
	$table2->data[]=array(
						'<img src="https://graph.facebook.com/'.$facebook_id.'/picture?type=large">',
						html_writer::table($table)
	);
	echo html_writer::table($table2);
}