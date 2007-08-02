<?php
# File		: userDetails.php
# Written	: Nick Doyle, 1/7/2002
# Description	: helpdesk user details page

require_once( 'include/helpDeskPage.php');
require_once( 'include/common.php');
require_once( 'modules/moduleUserDetails.php');
require_once( 'modules/moduleJobList.php');
require_once( 'modules/moduleEquipmentList.php');

define( '_TITLE', 'User Details');

$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( _TITLE);

if($_REQUEST['synergyID']){
	$sql = SQL_userListOneTerm($_REQUEST['synergyID']);
	mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
	mysql_select_db( DB_NAME_PORTAL);
	$query = mysql_query( $sql);
	if ($query != 1){
			$row = mysql_fetch_array( $query);
			$portalID = $row['portalID'];
	}
	mysql_close();
	
}
else if($_REQUEST['portalID']){
	$portalID = $_REQUEST['portalID'];
}
else
{
	print "Error: No user selected who am I supposed to display";
	exit;
}

$moduleUserDetails = new ModuleUserDetails( $portalID);
$content = $moduleUserDetails->getContent();

	# Get the details of the first associated equipment item for this user

$parms = array( 'portalUserID'   => $portalID);
$moduleEquipmentList = new ModuleEquipmentList($parms);
$content .= $moduleEquipmentList->getContent();

	# Associated jobs
$parms = array( 'portalID'   		=> $portalID,
				'status'	 		=> 'all',
				'equipmentPortalUserID' 	=> $portalID,
				);
$moduleJobList = new ModuleJobList( $parms, 'or');
$content .= $moduleJobList->getContent();

	# Assigned jobs
$parms = array( 'portalAssignedUserID'  => $portalID,
				'status'	 		=> 'all',
				);
$moduleJobList->ModuleJobList( $parms, 'or');
$content .= $moduleJobList->getContent();


$page->setContent( $content);

$page->printPage();

?>