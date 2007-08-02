<?php
# File		: equipmentDetails.php
# Written	: Nick Doyle, 1/7/2002
# Description	: helpdesk equipment details page

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleEquipmentDetails.php');
require_once( 'modules/moduleJobList.php');

$_TITLE = 'Equipment Details';
$content = '';

$equipmentID = $_REQUEST[ 'equipmentID'];
$prevAction = $_REQUEST[ 'prevaction'];

$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( $_TITLE);

	# Equipment details
$moduleEquipmentDetails = new ModuleEquipmentDetails( $equipmentID, $prevAction);
$content .= $moduleEquipmentDetails->getContent();

	# Add some space between the tables


	# Associated jobs
$parms = array(	'equipmentID'=>$equipmentID);
$moduleJobList = new ModuleJobList( $parms);
$content .= $moduleJobList->getContent();

$page ->setContent( $content);
$page->printPage();

?>
