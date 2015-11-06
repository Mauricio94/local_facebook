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
 * This script send notifications on facebook
 *
 * @package    local/facebook/
 * @subpackage cli
 * @copyright  2010 Jorge Villalon (http://villalon.cl)
 *  		   2015 Mihail Pozarski (mipozarski@alumnos.uai.cl)
 * 			   2015 Hans Jeria (hansjeria@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // Cli only functions
require_once($CFG->libdir.'/moodlelib.php');      // Moodle lib functions
require_once($CFG->libdir.'/datalib.php');      // Data lib functions
require_once($CFG->libdir.'/accesslib.php');      // Access lib functions
require_once($CFG->dirroot.'/course/lib.php');      // Course lib functions
require_once($CFG->dirroot.'/enrol/guest/lib.php');      // Guest enrol lib functions
include "../app/facebook-php-sdk-master/src/facebook.php";

// Now get cli options
list($options, $unrecognized) = cli_get_params(
		array('help'=>false),
        array('h'=>'help')
		);

if($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// Text to the facebook console
if($options['help']) {
    $help =
// Todo: localize - to be translated later when everything is finished
"Send facebook notifications when a course have some news.

Options:
-h, --help            Print out this help

Example:
\$sudo /usr/bin/php /local/facebook/cli/notifications.php";
echo $help;
die;
}

cli_heading('Facebook notifications'); // TODO: localize

// Text to the facebook console
echo "\nSearching for new notifications\n";
echo "\nStarting at ".date("F j, Y, G:i:s")."\n";

// Define used lower in the querys
define('FACEBOOK_NOTIFICATION_LOGGEDOFF','message_provider_local_facebook_notification_loggedoff');
define('FACEBOOK_NOTIFICATION_LOGGEDIN','message_provider_local_facebook_notification_loggedin');
// Visible Course Module
define('FACEBOOK_COURSE_MODULE_VISIBLE', 1);
define('FACEBOOK_COURSE_MODULE_NOT_VISIBLE', 0);
// Visible Module
define('FACEBOOK_MODULE_VISIBLE', 1);
define('FACEBOOK_MODULE_NOT_VISIBLE', 0);
// Facebook Notifications
define('FACEBOOK_NOTIFICATIONS_WANTED', 1);
define('FACEBOOK_NOTIFICATIONS_UNWANTED', 0);

// Sql that brings the latest time modified from facebook_notifications
$maxtimenotificationssql = "SELECT max(timemodified) AS maxtime
		           			FROM {facebook_notifications}
			 			    WHERE status = ?";

// Gets the information of the above query
$maxtimenotifications = $DB->get_record_sql($maxtimenotificationssql, array(
		FACEBOOK_NOTIFICATIONS_WANTED
));

// If clause that makes the timemodified=0 if there are no records in the data base
if($maxtimenotifications->maxtime == null){
	$timemodified = 0;
}

else{
	$timemodified = $maxtimenotifications->maxtime;
}

// Parameters for resources query
$paramsresources = array(
		'resource',
		FACEBOOK_COURSE_MODULE_VISIBLE,
		FACEBOOK_MODULE_VISIBLE,
		$timemodified
);

// Sql for resource information
$sqlresource = "SELECT r.course
		FROM {course_modules} AS cm INNER JOIN {modules} AS m ON (cm.module = m.id)
    	INNER JOIN {resource} AS r ON (r.course = cm.course)
		WHERE m.name IN (?) AND cm.visible = ? AND m.visible = ? AND r.timemodified >= ?
    	GROUP BY r.course";

// Get the data from the above query
$dataresource = $DB->get_records_sql($sqlresource, $paramsresources);

$allnotifications = array();

// Foreach that get all the data from the resource query to an array
foreach ($dataresource as $resources){
	$record = new stdClass();
	$record->courseid = $resources->course;
	$record->time = time();
	$record->status = 0;
	$record->timemodified = 0;
	$allnotifications[]=$record;
}

// If clause that makes sure if there is something in the array , if there is its saves the array in the data base
if(count($allnotifications)>0){
	$DB->insert_records('facebook_notifications', $allnotifications);
}

$countnotifications = count($allnotifications);
$time = time();

// Parameters for update query
$paramsupdate = array(
			FACEBOOK_NOTIFICATIONS_WANTED,
			$time,
			FACEBOOK_NOTIFICATIONS_UNWANTED,
			$timemodified
	);

// Sql that updates facebook_notifications table
$updatequery = "UPDATE {facebook_notifications}
				SET status=?, timemodified=?
				WHERE status = ? AND time >= ?";

// Executes the query
$DB->execute($updatequery, $paramsupdate);

// Text to the facebook console	
echo $countnotifications." Notifications found\n";
echo "ok\n";
echo "Sending notifications ".date("F j, Y, G:i:s")."\n";

// Facebook app information
$AppID = $CFG->fbkAppID;
$SecretID = $CFG->fbkScrID;
$token = $CFG->fbkTkn;
$config = array(
		'appId' => $AppID,
		'secret' => $SecretID,
		"default_graph_version" => "v2.0");
$facebook = new Facebook($config, true);

$counttosend = 0;
$courseidarray = array();

// Foreach that generates a array with all the user courses
foreach($dataresource as $resources){
	$courseidarray[] = $resources->course;
}

// User parameters for query
$userparams = array(
		FACEBOOK_NOTIFICATION_LOGGEDOFF,
		FACEBOOK_NOTIFICATION_LOGGEDIN,
		FACEBOOK_NOTIFICATIONS_WANTED
);

// List the result of get_in_or_equal
list($sqlin, $courseparam) = $DB->get_in_or_equal($courseidarray);
$paramsmerge = array_merge($courseparam,$userparams);

// Sql that brings the facebook user id
$sqlusers = "SELECT  facebookuser.facebookid AS facebookid
	     FROM {user_enrolments} AS enrolments
	     INNER JOIN  {enrol} AS enrol ON (enrolments.enrolid=enrol.id)
	     INNER JOIN {user_preferences} AS preferences ON (preferences.userid=enrolments.userid)
	     INNER JOIN {facebook_user} AS facebookuser ON (facebookuser.moodleid=enrolments.userid)
	     WHERE enrol.courseid $sqlin
	     AND preferences.name IN (?,?)
	     AND preferences.value like  '%facebook%' AND facebookuser.status=?
	     GROUP BY facebookuser.facebookid";

// Gets the information of the above query
$arrayfacebookid = $DB->get_records_sql($sqlusers,$paramsmerge);

// Foreach that notify all the facebook users with new staff to see
foreach($arrayfacebookid as $userfacebookid){
	if($userfacebookid->facebookid != null){	
		$post = $facebook->api('/'.$userfacebookid->facebookid.'/notifications/', 'POST', array(
				'access_token'=>$AppID.'|'.$SecretID,
				'href'=>'', // This does link to the app's root, don't think this actually works, seems to link to the app's canvas page
				'template'=>'Tienes nuevas notificaciones en Webcursos.'
				));
				
		if ($post['success']){
			// Echo that tells to who notifications were senta, ordered by id
			echo $counttosend." ".$userfacebookid->facebookid." ok\n";
			
			$counttosend++;
		}
		else {
			echo $userfacebookid->facebookid." fail\n";
		}
	}
}

// Time of execution = $execute
$timenow = time();
$execute = $time - $timenow;

// Text to the facebook console
echo "ok\n";
echo $counttosend." notificantions sent.\n";
echo "Ending at ".date("F j, Y, G:i:s");
echo "\nExecute time ".$execute." sec";
echo "\n";
exit(0); // 0 means success
