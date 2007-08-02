<?php
# File		: equipmentList.php
# Written	: Nick Doyle, 30/7/2002
# Description	: helpdesk equipment listing

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleEquipmentList.php');

define( '_TITLE', 'Equipment list');

$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( _TITLE);

	# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'serial'	=> '',
			'equipmentTypeID'=>'',
			'brandID'	=> '',
			'locationID' 	=> '',
			'userID'	=> $_REQUEST[ 'synergyID'],
			'modelID'	=> '',
			'active'	=> '');

foreach( array_keys( $arrParms) as $key) {
	if( $arrParms[ $key] == '') {
		$arrParms[ $key] = $_REQUEST[ $key];
	}
}

$moduleEquipmentList = new ModuleEquipmentList( $arrParms);
$content = $moduleEquipmentList->getContent();

$page->setContent( $content);

$page->printPage();

?>
