<?php
# File		: jobList.php
# Written	: Nick Doyle, 5/7/2002
# Description	: helpdesk job listing
#		  If no search criteria specified, lists the current user's jobs

$_TITLE = 'Task list';

require_once( 'include/helpDeskPage.php');
require_once( 'include/common.php');
require_once( 'modules/moduleJobList.php');

$page = new HelpDeskPage;
$page->authCheck(1);

$content = "";

	# Set which user the job list is for (assignedUser and assignedUserID)
if( isset( $_REQUEST[ 'portalAssignedUserID'])) {
	$portalAssignedUserID = $_REQUEST[ 'portalAssignedUserID'];
}

		# If search parms are specified, search on them
if( sizeof( $_GET) > 1) {

		# Make array of parameters on which to search. Fields with value 'all' set to null.
	$searchParms =	array(	'jobID' 	=> '',
				'serial' 	=> '',
				'description' 	=> '',
				'assignedUserID'=> '',
				'portalAssignedUserID'=> '',
				'problemID' 	=> '',
				'statusID' 	=> '',
				'equipmentID' 	=>'',
				'associatedUserID'=>''
			);
	foreach( array_keys( $searchParms) as $key) {
		if( $_GET[ $key] == 'all') {
			$searchParms[ $key] = '';
		}
		else {
			$searchParms[ $key] = $_GET[ $key];
		}
	}
}
else {		# If no search parms specified, get the logged-in user's current jobs and then display everyone elses
	$_TITLE = 'Task list for ' . $_SESSION['firstname'] . ' ' . $_SESSION[ 'surname'];
	$userSearchParms[ 'portalAssignedUserID'] = $_SESSION[ 'portal_ID'];
	$userSearchParms[ 'status'] = 'open';
	
	$moduleJobList = new ModuleJobList( $userSearchParms);
	$content = $moduleJobList->getContent();
	
	$searchParms[ 'status'] = 'open';
	$searchParms['notAssignedUserID'] = $_SESSION[ 'portal_ID'];
}

$page->setTitle( $_TITLE);

	# Main responsibility of the pages is to control/define the content (may be constructed of modules)
$moduleJobList = new ModuleJobList( $searchParms);
$content .= $moduleJobList->getContent();

$page->setContent( $content);

$page->printPage();

?>
