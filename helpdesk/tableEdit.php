<?php
# File		: tableEdit.php
# Written	: Nick Doyle, 9/12/2002
# Description	: helpdesk table edit

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleTableEdit.php');

define( '_TITLE', 'Database edit - ' . $_REQUEST[ 'tableName']);

$allowedTables = array(	"tblBrand",
				"tblCampus",
				"tblEquipmentType",
				"tblEventType",
				"tblLocation",
				"tblModel",
				"tblOwner",
				"tblPriority",
				"tblProblem",
				"tblStaff",
				);
		

$page = new HelpDeskPage;
$page->authCheck(3);
$page->setTitle( _TITLE);

$tableName = $_REQUEST[ 'tableName'];

$moduleTableEdit = new ModuleTableEdit( $tableName);

if($tableName)
{
	if( array_search( $tableName, $allowedTables) === FALSE ) //search the array for the table
	{
		redirectTo('noaccess.php');
	}
}

if( $_REQUEST[ 'doAction'] == 'add') {
	$arrParms = array();
	foreach( array_keys( $_REQUEST) as $key) {
			# If name starts with substring identifying it as table column name
		if( substr( $key, 0, strlen( TABLE_COLUMN_NAME_PREFIX)) == TABLE_COLUMN_NAME_PREFIX) {
			$fieldName = substr( $key, strlen( TABLE_COLUMN_NAME_PREFIX), strlen( $key));
			$arrParms[ $fieldName] = $_REQUEST[ $key];
		}
	}
	$moduleTableEdit->add( $arrParms);
	
	redirectTo( 'tableEdit.php?tableName=' . $_REQUEST[ 'tableName']);
}
elseif( $_REQUEST[ 'doAction'] == 'delete') {
	$result = $moduleTableEdit->delete( $_REQUEST[ 'id']);
	if( $result != '') {
		$content = $result;
	}
	else {
		redirectTo( 'tableEdit.php?tableName=' . $_REQUEST[ 'tableName']);
	}
}

//if( $tableName && !isset( $content)) {
	$content = $moduleTableEdit->getContent();
//}

$page->setContent( $content);

$page->printPage();

?>
