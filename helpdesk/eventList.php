<?php
# File		: eventList.php
# Written	: Nick Doyle, 25/9/2002
# Description	: helpdesk event listing

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleEventList.php');

define( '_TITLE', 'Event list');

$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( _TITLE);

	# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'description'=>'',
			'userID'=>'',
			'jobID'=>'');

foreach( array_keys( $arrParms) as $key) {
	#$arrParms[ $key] = $_REQUEST[ $key];
	$arrParms[ $key] = $_REQUEST[ $key];
}

$moduleEVentList = new ModuleEventList( $arrParms);
$content = $moduleEVentList->getContent();

$page->setContent( $content);

$page->printPage();

?>
