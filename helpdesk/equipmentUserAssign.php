<?php
# File		: equipmentUserAssign.php
# Written	: Nick Doyle, 5/8/2002
# Description	: helpdesk equipment user edit

require_once( 'include/helpDeskPage.php');

$page = new HelpDeskPage;
$page->authCheck(2);

	# Get user details matches
$sql = SQL_userListOneTerm( $_REQUEST[ 'user']);
mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
mysql_select_db( DB_NAME);
$query = mysql_query( $sql);

if( mysql_num_rows( $query) == 1) {
}

while( $row = mysql_fetch_Array( $query)) {
	
}
mysql_close();

require_once( 'modules/moduleEquipmentUserAssign.php');

define( '_TITLE', 'Equipment - User assignment');
$page = new HelpDeskPage;
$page->authCheck();
$page->setTitle( _TITLE);

	# Store all the equipment details in this page
#$eqDetails

if( isset( $_REQUEST[ 'create'])) {
	if( $_REQUEST[ 'dateNone']) {
		unset( $dateOfPurchase);
	}
	else {
		$dateOfPurchase = $_REQUEST[ 'dateYear'] . '-' . $_REQUEST[ 'dateMonth'] . '-' . $_REQUEST[ 'dateDay'];
	}

	$arrDetails = array (
		'serial' 	=> $_REQUEST[ 'serial'],
		'equipmentTypeID'=> $_REQUEST[ 'equipmentTypeID'],
		'modelID'	=> $_REQUEST[ 'modelID'],
		'locationID'	=> $_REQUEST[ 'locationID'],
		'warrantyYears'	=> $_REQUEST[ 'warrantyYears'],
		'dealerID'	=> $_REQUEST[ 'dealerID'],
		'ownerID'	=> $_REQUEST[ 'ownerID'],
		'dateOfPurchase'=> $dateOfPurchase,
		'active'	=> 1
	);

	$moduleEquipmentAdd = new ModuleEquipmentAdd();
	$moduleEquipmentAdd->add( $arrDetails);
}

$moduleEquipmentEdit = new ModuleEquipmentEdit( $equipmentID);
$moduleEquipmentEdit->setAction( _ACTION);
$content = $moduleEquipmentEdit->getContent();
$javascript = $moduleEquipmentEdit->getJavascript();

$page->addJavascript( $javascript);
$page->setContent( $content);

$page->printPage();

?>
