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
 *
 * @package    local
 * @subpackage facebook
 * @copyright  2013 Francisco García Ralph (francisco.garcia.ralph@gmail.com)
 * 			   2015 Xiu-Fong Lin (xlin@alumnos.uai.cl)
 * 			   2015 Mihail Pozarski (mipozarski@alumnos.uai.cl)
 * 			   2015 Hans Jeria (hansjeria@gmail.com)
 * 			   2015 Mauricio Meza (mameza@alumnos.uai.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__))))."/config.php");
require_once($CFG->dirroot."/local/facebook/locallib.php");
global $DB, $USER, $CFG;
include "htmltoinclude/javascriptindex.html";
include "config.php";
include "htmltoinclude/sidebar.html";
include "htmltoinclude/tableheaderindex.html";
use Facebook\Facebook;
use Facebook\FacebookCanvasHelper;

// Gets all facebook information needed
$facebook = new Facebook\Facebook($config);
$helper = $facebook->getCanvasHelper();
$app_name = $CFG->fbkAppNAME;
$app_email = $CFG->fbkemail;
$tutorial_name = $CFG->fbktutorialsN;
$tutorial_link = $CFG->fbktutorialsL;
$messageurl = new moodle_url( "/message/edit.php" );
$connecturl = new moodle_url( "/local/facebook/connect.php" );

try {
	if( isset( $_SESSION["facebook_access_token"] ) ) {
	$accessToken = $_SESSION["facebook_access_token"];
	} else {
  		$accessToken = $helper->getAccessToken();
	}
} catch( Facebook\Exceptions\FacebookResponseException $e ) {
 	// When Graph returns an error
 	echo "Graph returned an error: " . $e->getMessage();
  	exit;
} catch( Facebook\Exceptions\FacebookSDKException $e ) {
 	// When validation fails or other local issues
	echo "Facebook SDK returned an error: " . $e->getMessage();
  	exit;
 }
if( isset( $accessToken ) ) {
	if( isset( $_SESSION["facebook_access_token"] ) ) {
		$facebook->setDefaultAccessToken( $_SESSION["facebook_access_token"] );
	} else {
		$_SESSION["facebook_access_token"] = (string) $accessToken;
	  	// OAuth 2.0 client handler
		$oAuth2Client = $facebook->getOAuth2Client();
		// Exchanges a short-lived access token for a long-lived one
		$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken( $_SESSION["facebook_access_token"] );
		$_SESSION["facebook_access_token"] = (string) $longLivedAccessToken;
		$facebook->setDefaultAccessToken( $_SESSION["facebook_access_token"] );
	}
}

$user_data = $facebook->get( "/me" );
$user = $user_data->getGraphNode()->asArray();
$facebook_id = $user["id"];

// Search for the user facebook information
$userfacebookinfo = $DB->get_record("facebook_user",array(
		"facebookid"=>$facebook_id,
		"status"=>1
));

// If the user exist then show the app, if not tell him to connect his facebook account
if( $userfacebookinfo != false ) {
	$moodleid = $userfacebookinfo->moodleid;
	$lastvisit = $userfacebookinfo->lasttimechecked;
	
	// Gets the user info
	$user_info = $DB->get_record( "user", array(
			"id"=>$moodleid
	));
	
	$usercourse = enrol_get_users_courses( $moodleid );
	$courseidarray = array();
	
	// Generates an array with all the users courses
	foreach ( $usercourse as $courses ){
		$courseidarray[] = $courses->id;
	}
	
	if( count( $courseidarray )>0){
		echo "<div class='cuerpo'><h1>".get_string( "courses", "local_facebook" )."</h1><ul id='cursos'>";
	
		// Get_in_or_equal used after in the IN ('') clause of multiple querys
		list( $sqlin, $param ) = $DB->get_in_or_equal( $courseidarray );
	
		// List the 3 arrays returned from the function
		list( $totalresource, $totalurl, $totalpost ) = get_total_notification( $sqlin, $param, $lastvisit );
		$dataarray = get_data_post_resource_link( $sqlin, $param );
	
		// Foreach that generates each course square
		foreach( $usercourse as $courses ){
			
			$fullname = $courses->fullname;
			$courseid = $courses->id;
			$shortname = $courses->shortname;
			$totals = 0;
			
			// Tests if the array has something in it
			if( isset( $totalresource[$courseid] ) ){
				$totals += intval( $totalresource[$courseid] );
			}
			
			// Tests if the array has something in it
			if( isset( $totalurl[$courseid] ) ){
				$totals += intval( $totalurl[$courseid] );
			}
			
			// Tests if the array has something in it
			if( isset( $totalpost[$courseid] ) ){
				$totals += intval( $totalpost[$courseid] );
			}
				
			echo "<a class='inline link_curso' href='#".$courseid."'><li class='curso'><p class='nombre'><img src='images/lista_curso.png'>".$fullname."</p>";
			
			// If there is something new, then show the number of new things
			if( $totals>0 ){
				echo "<span class='numero_notificaciones'>".$totals."</span>";
			}
			
			// Foreach that gives the corresponding image to the new and old items created(resource,post,forum),its title, who upload it and its link
			foreach( $dataarray as $data ){
				if( $data["course"] == $courseid ){
					$date = date( "d/m/Y H:i", $data["date"] );
					echo "<tr><td><center>";
					
					if( $data["image"] == FACEBOOK_IMAGE_POST){
						echo "<img src='images/post.png'>";
					}
					
					elseif( $data["image"] == FACEBOOK_IMAGE_RESOURCE ){
						echo "<img src='images/resource.png'>";
					}
					
					elseif( $data["image"] == FACEBOOK_IMAGE_LINK ){
						echo "<img src='images/link.png'>";
					}
					
					echo "</center></td><td><a href='".$data["link"]."' target='_blank'>".$data["title"]."</a>
						  </td><td style='font-size:11px'><b>".$data ["from"]."</b></td><td>".$date."</td></tr>";
				}
			}
			echo "</tbody></table></div></div>";
		}
		echo "</ul></tbody></div></div>";
		include "htmltoinclude/spacer.html";
		echo "<div id='overlay'></div>";
	
		// Updates the user last time in the app
		$userfacebookinfo->lasttimechecked = time();
		$DB->update_record( "facebook_user", $userfacebookinfo);
	}
	
	// If the user has no courses
	else{
		$content="<br><br>".get_string( "nocourses", "local_facebook" );
		echo html_writer::tag( "div", $content, array( "class" => "cuerpo" ) );
	}
}

// If the user in not in the DB	 
else{
	echo "<div class='cuerpo'><h1>".get_string( "existtittle", "local_facebook" )."</h1>
		  <p>".get_string( "existtext", "local_facebook" )."<a href='".$connecturl."' >".get_string("existlink", "local_facebook")."</a></p></div>";
	include "htmltoinclude/spacer.html";
}	