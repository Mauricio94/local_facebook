<?php
require_once(dirname(dirname(dirname(dirname(__FILE__))))."/config.php");
global $CFG;
require_once ($CFG->dirroot . "/local/facebook/app/facebook-php-sdk-v4/src/Facebook/autoload.php");

require_login();
if (isguestuser()) {
	die();
}
$config = array(
		"app_id" => "633751800045647",
		"app_secret" => "60e248fca5c76a1286a60dc4cd2a9132",
		"default_graph_version" => "v2.0" );


$facebook = new Facebook\Facebook ($config);
$helper = $facebook->getCanvasHelper();
$permissions = ['email', 'publish_actions']; // optional

// URL for current page
$url = new moodle_url ( "/local/facebook/app/newfile.php" );

$context = context_system::instance ();

$PAGE->set_url ( $url );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( "standard" );
$PAGE->set_title(get_string("connecttitle", "local_facebook"));
$connect = optional_param ( "connect", null, PARAM_TEXT );
$disconnect = optional_param ( "disconnect", null, PARAM_TEXT );

$PAGE->navbar->add ( get_string ( "facebook", "local_facebook" ) );
echo $OUTPUT->header ();

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
		$_SESSION['facebook_access_token'] = (string) $accessToken;
	  	// OAuth 2.0 client handler
		$oAuth2Client = $facebook->getOAuth2Client();
		// Exchanges a short-lived access token for a long-lived one
		$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($_SESSION['facebook_access_token']);
		$_SESSION['facebook_access_token'] = (string) $longLivedAccessToken;
		$facebook->setDefaultAccessToken($_SESSION['facebook_access_token']);
	}
	
	// validating the access token
	try {
		$request = $facebook->get('/me');
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
		// When Graph returns an error
		if ($e->getCode() == 190) {
			unset($_SESSION['facebook_access_token']);
			$helper = $facebook->getRedirectLoginHelper();
			$loginUrl = $helper->getLoginUrl('https://apps.facebook.com/APP_NAMESPACE/', $permissions);
			echo "<script>window.top.location.href='".$loginUrl."'</script>";
			exit;
		}
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
		// When validation fails or other local issues
		echo 'Facebook SDK returned an error: ' . $e->getMessage();
		exit;
	}
	
	// posting on user timeline using publish_actins permission
	try {
		// message must come from the user-end
		$data = ['message' => 'testing...'];
		$request = $facebook->post('/me/feed', $data);
		$response = $request->getGraphEdge()->asArray;
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
		// When Graph returns an error
		echo 'Graph returned an error: ' . $e->getMessage();
		exit;
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
		// When validation fails or other local issues
		echo 'Facebook SDK returned an error: ' . $e->getMessage();
		exit;
	}
	echo $response['id'];
  	// Now you can redirect to another page and use the
  	// access token from $_SESSION['facebook_access_token']
} else {
	$helper = $facebook->getRedirectLoginHelper();
	$loginUrl = $helper->getLoginUrl($url, $permissions);
	echo "<script>window.top.location.href='".$loginUrl."'</script>";
}
echo $OUTPUT->footer();

