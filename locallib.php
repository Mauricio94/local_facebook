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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package local_facebook
 * @copyright 2015 Xiu-Fong Lin (xlin@alumnos.uai.cl)
 * @copyright 2015 Mihail Pozarski (mipozarski@alumnos.uai.cl)
 * 			  2015 Hans Jeria (hansjeria@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * Constants
 */
// Visible Course Module
define('FACEBOOK_COURSE_MODULE_VISIBLE', 1);
define('FACEBOOK_COURSE_MODULE_NOT_VISIBLE', 0);
// Visible Module
define('FACEBOOK_MODULE_VISIBLE', 1);
define('FACEBOOK_MODULE_NOT_VISIBLE', 0);
// Image
define('FACEBOOK_IMAGE_POST', 'post');
define('FACEBOOK_IMAGE_RESOURCE', 'resource');
define('FACEBOOK_IMAGE_LINK', 'link');
/**
 * This function gets al the notification pending since the last check.
 * @param $sqlin from get_in_or_equal used in "IN ('')" clause    
 * @param $param from get_in_or_equal parameters      	
 * @param date $lastvisit        	
 * @return 3 arrays
 */
function get_total_notification($sqlin, $param, $lastvisit){
	
	global  $DB;
	
	// Resource parameters for query
	$totalresourceparams = array(
			'resource',
			FACEBOOK_COURSE_MODULE_VISIBLE,
			FACEBOOK_MODULE_VISIBLE,
			$lastvisit
	);
	
	// Merge with the params that the function brings
	$paramsresource = array_merge($param,$totalresourceparams);
	
	// Sql that counts all the resourses since the last time the app was used
	$totalresourcesql = "SELECT cm.course AS idcoursecm, COUNT(cm.module) AS countallresource
			     		 FROM {course_modules} AS cm
			   			 INNER JOIN {modules} AS m ON (cm.module = m.id)
	     		         INNER JOIN {resource} AS r ON (cm.instance=r.id)
			   			 WHERE cm.course $sqlin 
			     		 AND m.name IN (?)
  			    		 AND cm.visible = ?
 			   			 AND m.visible = ?
			   			 AND  r.timemodified >= ?
			   			 GROUP BY cm.course";
	
	// Gets the information of the above query
	$totalresource = $DB->get_records_sql($totalresourcesql, $paramsresource);
	
	$resourcepercourse = array();
	
	// If the query brings something generate an array with all the course ids
	if($totalresource){
		foreach($totalresource as $totalresources){	
			$resourcepercourse[$totalresources->idcoursecm] = $totalresources->countallresource;
		}
	}
	
	// Parameters of the urls
	$totalurlparams = array(
			'url',
			FACEBOOK_COURSE_MODULE_VISIBLE,
			FACEBOOK_MODULE_VISIBLE,
			$lastvisit
	);
	
	// Merge with the params that the function brings
	$paramsurl = array_merge($param,$totalurlparams);
	
	// Sql that counts all the urls since the last time the app was used
	$totalurlsql = "SELECT cm.course AS idcoursecm, COUNT(cm.module) AS countallurl
					FROM {course_modules} AS cm
					INNER JOIN {modules} AS m ON (cm.module = m.id)
					INNER JOIN {url} AS u ON (cm.instance=u.id)
					WHERE cm.course $sqlin
					AND m.name IN (?)
					AND cm.visible = ?
					AND m.visible = ?
					AND  u.timemodified >= ?
					GROUP BY cm.course";
	
	// Gets the infromation of the above query
	$totalurl = $DB->get_records_sql($totalurlsql, $paramsurl);
	
	$urlpercourse = array();
	
	// Makes an array that associates the course id with the counted items
	if($totalurl){
		foreach($totalurl as $totalurls){	
		$urlpercourse[$totalurls->idcoursecm] = $totalurls->countallurl;
		}
	}
	
	// Post parameters for query
	$totalpostparams = array(
			$lastvisit
	);
	
	// Merge with the params that the function brings
	$paramsallpost = array_merge($param, $totalpostparams);
	
	
	// Sql that counts all the posts since the last time the app was conected.
	$totalpostsql = "SELECT fd.course AS idcoursefd, COUNT(fp.id) AS countallpost
					 FROM {forum_posts} AS fp
					 INNER JOIN {forum_discussions} AS fd ON (fp.discussion=fd.id)
					 WHERE fd.course $sqlin 
					 AND fp.modified > ?
					 GROUP BY fd.course ";
	$totalpost = $DB->get_records_sql($totalpostsql, $paramsallpost);
	
	$totalpostpercourse = array();
	
	// Makes an array that associates the course id with the counted items
	if($totalpost){
		foreach($totalpost as $objects){	
			$totalpostpercourse[$objects->idcoursefd] = $objects->countallpost;
		}
	}
	
	// Function returns
	return array($resourcepercourse, $urlpercourse, $totalpostpercourse);
}

/**
 * Sort the records by the field inside record.
 * @param array $records        	
 * @param string $field        	
 * @param string $reverse        	
 * @return the records sorted
 */
function record_sort($records, $field, $reverse = false){
	
	$hash = array();
	
	foreach($records as $record){	
		$hash[$record[$field]] = $record;
	}
	
	($reverse) ? krsort ($hash) : ksort ($hash);
	$records = array();
	
	foreach($hash as $record){	
		$records[] = $record;
	}
	
	//function returns
	return $records;
}

/**
 * This Function gets all the posts resources and links, posted recently in the course ordered by date.
 * @param $sqlin from get_in_or_equal used in "IN ('')" clause    
 * @param $param from get_in_or_equal parameters      	
 * @return array
 */
function get_data_post_resource_link($sqlin, $param){
	
	global $DB;
	
	// Parameters for post query
	$datapostparams = array(
			FACEBOOK_COURSE_MODULE_VISIBLE,
			'forum'
	);
	
	// Merge with the params that the function brings
	$paramspost = array_merge($param, $datapostparams);
	
	// Query for the posts information
	$datapostsql = "SELECT fp.id AS postid, us.firstname AS firstname, us.lastname AS lastname, fp.subject AS subject,
					fp.modified AS modified, discussions.course AS course, discussions.id AS dis_id 
					FROM {forum_posts} AS fp
					INNER JOIN {forum_discussions} AS discussions ON (fp.discussion=discussions.id)
					INNER JOIN {forum} AS forum ON (forum.id=discussions.forum)
					INNER JOIN {user} AS us ON (us.id=discussions.userid)
					INNER JOIN {course_modules} AS cm ON (cm.instance=forum.id)
					INNER JOIN {modules} AS m ON (cm.module = m.id)
					WHERE discussions.course $sqlin 
					AND cm.visible = ?
					AND m.name IN (?)
					GROUP BY fp.id";
	
	// Get the data from the above query
	$datapost = $DB->get_records_sql($datapostsql, $paramspost);
	
	// Parameters for resource query
	$dataresourcesparams = array(
			'resource',
			FACEBOOK_COURSE_MODULE_VISIBLE
	);
	
	// Merge with the params that the function brings
	$paramsresource = array_merge($param, $dataresourcesparams);
	
	// Query for the resource information
	$dataresourcesql="SELECT cm.id AS coursemoduleid, r.id AS resourceid, r.name AS resourcename, r.timemodified, 
			 		  r.course AS resourcecourse, cm.visible, cm.visibleold
			          FROM {resource} AS r 
                      INNER JOIN {course_modules} AS cm ON (cm.instance = r.id)
                      INNER JOIN {modules} AS m ON (cm.module = m.id)
			 		  WHERE r.course $sqlin 
			 		  AND m.name IN (?) 
			 		  AND cm.visible = ?
                      GROUP BY cm.id";
	
	// Get the data from the above query
	$dataresource = $DB->get_records_sql($dataresourcesql, $paramsresource);
	
	// Parameters for the link query
	$datalinkparams = array(
			'url',
			FACEBOOK_COURSE_MODULE_VISIBLE
	);
	
	// Merge with the params that the function brings
	$paramslink = array_merge($param, $datalinkparams);
	
	// Query for the link information
	$datalinksql="SELECT url.id AS id, url.name AS urlname, url.externalurl AS externalurl, url.timemodified AS timemodified,
	              url.course AS urlcourse, cm.visible AS visible, cm.visibleold AS visibleold
		          FROM {url} AS url
                  INNER JOIN {course_modules} AS cm ON (cm.instance = url.id)
                  INNER JOIN {modules} AS m ON (cm.module = m.id)	
                  WHERE url.course $sqlin 
  	              AND m.name IN (?)
	     		  AND cm.visible = ?
                  GROUP BY url.id";
	
	// Get the data from the above query
	$datalink = $DB->get_records_sql($datalinksql, $paramslink);
	
	$datawithpostresourcesandlink = array();
	
	// Foreach used to fill the array with the posts information
	foreach($datapost as $post){
		$posturl = new moodle_url('/mod/forum/discuss.php', array(
				'd'=>$post->dis_id 
		));
		
		$datawithpostresourcesandlink[] = array(
				'image'=>FACEBOOK_IMAGE_POST,
				'link'=>$posturl,
				'title'=>$post->subject,
				'from'=>$post->firstname . ' ' . $post->lastname,
				'date'=>$post->modified,
				'course'=>$post->course 
		);
	}
	
	// Foreach used to fill the array with the resource information	
	foreach($dataresource as $resource){
		$date = date("d/m H:i", $resource->timemodified);
		
		$resourceurl = new moodle_url('/mod/resource/view.php', array(
				'id'=>$resource->coursemoduleid
		));
		
		if($resource->visible == FACEBOOK_COURSE_MODULE_VISIBLE && $resource->visibleold == FACEBOOK_COURSE_MODULE_VISIBLE){
			$datawithpostresourcesandlink[] = array (
					'image'=>FACEBOOK_IMAGE_RESOURCE,
					'link'=>$resourceurl,
					'title'=>$resource->resourcename,
					'from'=>'',
					'date'=>$resource->timemodified,
					'course'=>$resource->resourcecourse 
			);
		}
	}
	
	// Foreach used to fill the array with the link information
	foreach($datalink as $link){
		$date = date("d/m H:i", $link->timemodified);
			
		if($link->visible == FACEBOOK_COURSE_MODULE_VISIBLE && $link->visibleold == FACEBOOK_COURSE_MODULE_VISIBLE){
			$datawithpostresourcesandlink[] = array(
					'image'=>FACEBOOK_IMAGE_LINK,
					'link'=>$link->externalurl,
					'title'=>$link->urlname,
					'from'=>'',
					'date'=>$link->timemodified,
					'course'=>$link->urlcourse 
			);
		}
	}
	
	// Returns the final array ordered by date to index.php
	return record_sort($datawithpostresourcesandlink, 'date', 'true');
}
