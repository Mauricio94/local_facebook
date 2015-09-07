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
 * @copyright  2015 Mihail Pozarski (mipozarski@alumnos.uai.cl)
 * @copyright  2015 Hans Jeria (hansjeria@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->libdir.'/moodlelib.php');      // moodle lib functions
require_once($CFG->libdir.'/datalib.php');      // data lib functions
require_once($CFG->libdir.'/accesslib.php');      // access lib functions
require_once($CFG->dirroot.'/course/lib.php');      // course lib functions
require_once($CFG->dirroot.'/enrol/guest/lib.php');      // guest enrol lib functions
include "../app/facebook-php-sdk-master/src/facebook.php";
// now get cli options
/*list($options, $unrecognized) = cli_get_params(array('help'=>false),
                                               array('h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Send facebook notifications when a course have some news.

Options:
-h, --help            Print out this help

Example:
\$sudo /usr/bin/php /local/facebook/cli/notifications.php
"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

cli_heading('Facebook notifications'); // TODO: localize

echo "\nSearching for new notifications\n";
echo "\nStarting at ".date("F j, Y, G:i:s")."\n";
*/
// define used lower in the querys
define('FACEBOOK_NOTIFICATION_LOGGEDOFF','message_provider_local_facebook_notification_loggedoff');
define('FACEBOOK_NOTIFICATION_LOGGEDIN','message_provider_local_facebook_notification_loggedin');

// sql that brings the latest time modified from facebook_notifications
$maxtimenotificationssql = "SELECT max(timemodified) AS maxtime
		            FROM {facebook_notifications}
			    WHERE status = ?";

$maxtimenotifications = $DB->get_record_sql($maxtimenotificationssql, array(
		1
));

// if clause that makes the timemodified=0 if there are no records in the data base
if($maxtimenotifications->maxtime == null){
	$timemodified = 0;
}else{
	$timemodified = $maxtimenotifications->maxtime;
}
// sql that gets all the courses with a resource to notify
$paramsresources = array(
		'resource',
		1,
		1,
		$timemodified
);
$sqlresource = "SELECT r.course
		FROM {course_modules} AS cm INNER JOIN {modules} AS m ON (cm.module = m.id)
    	INNER JOIN {resource} AS r ON (r.course = cm.course)
		WHERE m.name IN (?) AND cm.visible = ? AND m.visible = ? AND r.timemodified >= ?
    	GROUP BY r.course";
$dataresource = $DB->get_records_sql($sqlresource, $paramsresources);

$allnotifications = array();

// foreach that get all the data from the resource query to an array
foreach ($dataresource as $resources){
	$record = new stdClass();
	$record->courseid = $resources->course;
	$record->time = time();
	$record->status = 0;
	$record->timemodified = 0;
	$allnotifications[]=$record;
}

// if clause that makes sure if there is something in the array , if there is its saves the array in the data base
if(count($allnotifications)>0){
		$DB->insert_records('facebook_notifications', $allnotifications);
}

$countnotifications = count($allnotifications);
$time = time();

//query that updates the status of the user last login
$paramsupdate = array(
			1,
			$time,
			0,
			$timemodified
	);

$updatequery = "UPDATE {facebook_notifications}
		SET status=?, timemodified=?
		WHERE status = ? AND time >= ?";

$DB->execute($updatequery, $paramsupdate);
	
echo $countnotifications." Notifications found\n";
echo "ok\n";
echo "Sending notifications ".date("F j, Y, G:i:s")."\n";


$AppID= $CFG->fbkAppID;
$SecretID= $CFG->fbkScrID;
$config = array(
		'appId' => $AppID,
		'secret' => $SecretID,
		'grant_type' => 'client_credentials' );
$facebook = new Facebook($config, true);

$counttosend = 0;
$token = $CFG->fbkTkn;
$courseidarray = array();
foreach($dataresource as $resources){
	$courseidarray[] = $resources->course;
}
list($sqlin, $courseparam) = $DB->get_in_or_equal($courseidarray);
// query that brings all the user notifications from each course
$sqlusers = "SELECT  facebookuser.facebookid AS facebookid
	     FROM {user_enrolments} AS enrolments
	     INNER JOIN  {enrol} AS enrol ON (enrolments.enrolid=enrol.id)
	     INNER JOIN {user_preferences} AS preferences ON (preferences.userid=enrolments.userid)
	     INNER JOIN {facebook_user} AS facebookuser ON (facebookuser.moodleid=enrolments.userid)
	     WHERE enrol.courseid $sqlin
	     AND preferences.name IN (?,?)
	     AND preferences.value like  '%facebook%' AND facebookuser.status=?
	     GROUP BY facebookuser.facebookid";

$userparams = array(
		FACEBOOK_NOTIFICATION_LOGGEDOFF,
		FACEBOOK_NOTIFICATION_LOGGEDIN,
		1
);
$params = array_merge($courseparam,$userparams);

$arrayfacebookid = $DB->get_records_sql($sqlusers,$params);

//for each that notify all the facebook users with new staff to see
foreach($arrayfacebookid as $userfacebookid){
	
	if($userfacebookid->facebookid != null){
		$post = $facebook->api('/'.$userfacebookid->facebookid.'/notifications/', 'POST', array(
				'access_token'=>$AppID.'|'.$SecretID,
				'href'=>'', // this does link to the app's root, don't think this actually works, seems to link to the app's canvas page
				'template'=>'Tienes nuevas notificaciones en Webcursos.' 
		));
		var_dump($post)."\n";
		$counttosend++;
		echo $counttosend." ".$userfacebookid->facebookid." ok\n";
		;
	}
}
echo "ok\n";
echo $counttosend." notificantions sent.\n";
echo "Ending at ".date("F j, Y, G:i:s");
$timenow=time();
$execute=$time - $timenow;
echo "\nExecute time ".$execute." sec";
echo "\n";

exit(0); // 0 means success
