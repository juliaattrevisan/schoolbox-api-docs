<?php
# File		: userList.php
# Written	: Nick Doyle, 11/7/2002
# Description	: helpdesk user listing

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleUserList.php');

define( '_TITLE', 'User list');

$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( _TITLE);

	# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'firstname'=>'',
			'lastname'=>'',
			'synergy_id'=>'');
foreach( array_keys( $arrParms) as $key) {
	$arrParms[ $key] = $_REQUEST[ $key];
}

$moduleUserList = new ModuleUserList( $arrParms);
$content = $moduleUserList->getContent();

$page->setContent( $content);

$page->printPage();

?>
