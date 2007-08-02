<?php
# File		: jobDetails.php
# Written	: Nick Doyle, 5/9/2002
# Description	: helpdesk job details page

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleJobDetails.php');
require_once( 'modules/moduleEventList.php');
require_once( 'modules/moduleEventAdd.php');
require_once( 'modules/moduleEventDelete.php');

$_TITLE = 'Task Details';
$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( $_TITLE);

$jobID = $_REQUEST[ 'jobID'];

$prevAction = $_REQUEST[ 'prevaction'];

switch( $_REQUEST[ 'action']) {
	case( 'newEvent') :
		$moduleEventAdd = new ModuleEventAdd();
		$arrDetails = array(
			'jobID'			=> $_REQUEST[ 'jobID'],
			'eventTypeID'	=> $_REQUEST[ 'eventTypeID'],
			'description'	=> $_REQUEST[ 'eventDescription'],
			'userID'		=> $_SESSION[ 'synergy_ID'],
			'portalUserID' 	=> $_SESSION[ 'portal_ID'],
		);
		$moduleEventAdd->add( $arrDetails);
		redirectTo( 'jobDetails.php?jobID=' . $_REQUEST[ 'jobID']);
		break;
	case( 'deleteEvent') :
		$jobEventID = $_REQUEST[ 'jobEventID'];
		$jobID = $_REQUEST['jobID'];
		$moduleEventDelete = new ModuleEventDelete;
		$moduleEventDelete->delete( $jobID, $jobEventID);
		break;
	default :
		break;
}

$moduleJobDetails = new ModuleJobDetails( $jobID, $prevAction);

$parms = array( 'jobID'	=> $jobID);
$moduleEventList = new ModuleEventList( $parms);

$moduleJobDetails->setEventList( $moduleEventList->getContent());
$content = $moduleJobDetails->getContent();

$page ->setContent( $content);
$page->printPage();

?>
