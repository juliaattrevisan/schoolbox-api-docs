<?php 

#Site constants
define('MAIL_DOMAIN', '@mgs.vic.edu.au');


# Email messages
define('EMAIL_EVENT_NOTIFICATION_SYSTEM', true);
define('EMAIL_EVENT_NOTIFICATION_CLIENT', true);
define('EMAIL_EVENT_READY_PICKUP_SUBJECT', 'Your equipment is ready for pickup');
define('EMAIL_EVENT_JOB_REASSIGNED_SUBJECT', 'Helpdesk Task Reassigned');
define('EMAIL_EVENT_JOB_CREATED_SUBJECT', 'Helpdesk Task Created');
define('EMAIL_EVENT_JOB_COMPLETED_SUBJECT', 'Helpdesk Task Completed');

	# Other string constants
define( 'TABLE_COLUMN_NAME_PREFIX', 'column_');

	# DB String constants
define( 'DB_DESCRIPTION_OWNER_READY', 'Ready for student pickup');
define( 'DB_DESCRIPTION_DEALER_BOOKED', 'Booked with dealer');
define( 'DB_ID_EVENT_COURIER_PICKED_UP', 15);
define( 'DB_ID_EVENT_DEALER_BOOKED', 7);
define( 'DB_ID_EVENT_DEALER_RETURNED', 8);
define( 'DB_ID_EVENT_OWNER_READY', 16);
define( 'DB_ID_EVENT_OWNER_RETURNED', 9);
define( 'DB_ID_EVENT_JOB_COMPLETED', 27);
define( 'DB_ID_EVENT_JOB_CREATED', 28);
define( 'DB_ID_EVENT_JOB_REASSIGNED', 29);
define( 'DB_ID_EVENT_JOB_UPDATED', 30);
define( 'DB_ID_EVENT_REIMAGE', 10 );

define( 'DB_ID_LOCATION_HELPDESK_1', '75');
define( 'DB_ID_LOCATION_HELPDESK_2', '76');
define( 'DB_ID_LOCATION_HELPDESK_3', '77');
define( 'DB_ID_LOCATION_DEALER', '73');
define( 'DB_ID_LOCATION_USER', '72');
define( 'DB_ID_LOCATION_SCHOOL', '74');
define( 'DB_ID_STATUS_CLOSED', '3');

	# DB Connection types
define( 'DB_TYPE_HELPDESK', 0);		# default in dbManager class, not really necessary but here for completeness
define( 'DB_TYPE_SYNERGY', 1);
define( 'DB_TYPE_PORTAL', 2);

	# MySQL config options for portal
define( 'DB_HOST_PORTAL', 'localhost');
define( 'DB_USER_PORTAL', 'portal');
define( 'DB_PASSWD_PORTAL', 'tnp879');
define( 'DB_NAME_PORTAL', 'portal');

	# Config for SynergyOne
define( 'DB_HOST_SYNERGY', 'synergy');
define( 'DB_USER_SYNERGY', 'intranet');
define( 'DB_PASSWD_SYNERGY', 'Qu4LitY');
define( 'DB_NAME_SYNERGY', 'SynergyOne');

	# MySQL config options
define( 'DB_HOST', 'localhost');
define( 'DB_USER', 'portal');
define( 'DB_PASSWD', 'tnp879');
define( 'DB_NAME', 'helpdesk');

	# Templates setup
define( 'TPL_DIR', 'templates');
define( 'TPL_WHOLE', 'whole.html');

	# Constant pages
define( 'PAGE_LOGIN_FAIL', 'login.php?authfail=1');		# Page for auth fail
define( 'PAGE_INITIAL', 'jobList.php');				# Initial page to go to after login

	# Auth levels
define( 'AUTH_NO_ACCESS', 0);
define( 'AUTH_READ_ONLY', 1);
define( 'AUTH_ADMIN', 2);

function stripslashes_array($arr){
        $rs = $arr;

		foreach($arr as $key => $value){
                $rs[$key] = stripslashes($value);
        }
       
        return $rs;
}



	# Convenience function to print a page with only one module
	# Optional 3rd/4th parms (dodgy but will work)
function printModule( $title, $moduleName) {
	$num_args = func_num_args();
	if( $num_args > 2) {
		$input1 = func_get_arg( 2);
		$input2 = func_get_arg( 3);
	}
	require_once( 'modules/module' . $moduleName . '.php');
	require_once( 'include/helpDeskPage.php');

	$moduleName = 'module' . $moduleName;
	$page = new HelpDeskPage;
	$page->authCheck();
	$page->setTitle( $title);
	$module = new $moduleName( $input1, $input2);
	$content = $module->getContent();
	$page->setContent( $content);

	if( isset( $module->_javascript)) {
		$javascript = $module->getJavascript();
		$page->addJavascript( $javascript);
	}

	$page->printPage();
}

	# Convenience function to print a page with only one template
function printTemplate( $title, $templateName) {
	$page = new HelpDeskPage;
	$page->authCheck();
	$page->setTitle( $title);
	$tpl = new Template( TPL_DIR);
	$tpl->set_file( 'tplContent', $templateName);
	$page->setContent( $tpl->parse( 'tplContent', 'tplContent'));
	$page->printPage();
}

function redirectTo( $relativeURL) {
	if(dirname($_SERVER['PHP_SELF']) != '/')
		header("Location: http://".$_SERVER['HTTP_HOST'] . dirname( $_SERVER['PHP_SELF']) . '/' . $relativeURL);
	else
		header("Location: http://".$_SERVER['HTTP_HOST'] . '/' . $relativeURL);
	exit;
}

function redirectRoot( $relativeToRootURL) {
	header("Location: http://".$_SERVER['HTTP_HOST'] . $relativeToRootURL);
	exit;
}

	# Return the rightmost n characters in a string
function right( $str, $n) {
	$out = substr( $str, strlen( $str) - $n);
	return $out;
}

	# Used with array_filter to trim out array items with no value (kinda cool)
function array_trim( $value) {
	return( $value != '');
}


	# Get user's full name, given portal id OR login
function getUserFull( $in) {
	if( is_numeric( $in)) {
		$sql = SQL_userDetailsPortal( $in);
	}
	else {
		$sql = SQL_userDetailsPortalLogin( $in);
	}
	
	mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db( DB_NAME);
	$query = mysql_query( $sql);
	$row = mysql_fetch_array( $query);
	if ($row['firstname'])
		$user = $row[ 'firstname'] . ' ' . $row[ 'lastname'] . ' (' . $row[ 'synergy_id'] . ')';
	else
		$user = "";
	mysql_close();

	return $user;
}

function getSynergyFromPortalIfInt( $in) 
{
	if( is_numeric( $in)) 
	{
		$sql = SQL_userDetailsPortal( $in);
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $sql);
		$row = mysql_fetch_array( $query);
		if ($row['firstname'])
			$user = $row[ 'synergy_id'];
		else
			$user = $in;
		mysql_close();		
	}
	else 
	{
		$user = $in;
	}
	return $user;
}


	# Get user's first name, given portal id OR login
function getUser( $in, $field='firstname') {
	if( is_numeric( $in)) {
		$sql = SQL_userDetails( $in);
	}
	else {
		$sql = SQL_userDetailsPortalLogin( $in);
	}

	mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db( DB_NAME);
	$query = mysql_query( $sql);
	$row = mysql_fetch_array( $query);
	$user = $row[ $field];
	mysql_close();

	return $user;
}


function getEquipmentIDsFromUser( $synergyID) {
	$sql = SQL_equipmentIDFromUser( $synergyID);

	mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db( DB_NAME);
	$query = mysql_query( $sql);
	while( $row = mysql_fetch_array( $query)) {
		$equipmentIDs .= $row[ 'equipmentID'] . ',';
	}
	mysql_close();
	$equipmentIDs = substr( $equipmentIDs, 0, strlen( $equipmentIDs) - 1);

	return $equipmentIDs;
}

function scrubHTML($html)
{
	//cleans html of all nasty stuff
	//and converts everything into html safe for php and browser output
	//can be used on input to database or output

	$search = array('\\\\',			// To protect from \\ backreferences
			'$',			// Dollars are important else they can cause backreferences
			'\\\\',			// To protect from \\ backreferences
			'<script',		// Remove javascript markup
			'</script>',			
		);
	
	$replace = array('&#92;',
			'&#36;',
			'&#92;',
			'',
			'',
		);

	$html = str_replace($search, $replace, $html);

	return $html;
}

function scrubTEXT($text)
{
	//cleans plain text of all nasty stuff
	//can be used on input to database or output

	//this in built function encode html chars
	//the ENT_QUOTES converts all talking marks
	$text = htmlentities($text, ENT_QUOTES);

	//get rid of any possible bad chars
	$text = scrubHTML($text);
	return $text;
}

?>
