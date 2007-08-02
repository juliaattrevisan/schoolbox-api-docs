<?php
# File		: moduleEquipmentAdd.php
# Written	: Nick Doyle, 1/8/2002
# Description	: module to add equipment
//written++ :enderana
//29-03-05

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEquipmentAdd extends HelpDeskPage {

	function moduleEquipmentAdd() {
	}

		# Add an equipment item
	function add( &$details) {
		$sql = SQL_equipmentAdd( $details);
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		mysql_query( $sql, $link);

		if( mysql_error() <> '') {
			die( 'Error accessing database : ' . mysql_error());
		}
		$id = mysql_insert_id();
		mysql_close( $link);
		return $id;
	}
	
	function addExtraFields($equipmentID){ //code abstracted to module for conformity
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);

		foreach($_POST as $key => $value)
		{
			if (substr($key,0,3) == 'ctf')
			{
				$fieldname = substr(str_replace("_", " ", $key), 3);
				$sql = "insert into tblTemplateContents(equipmentID, fieldname, contents) VALUES ('".$equipmentID."', '".$fieldname."','".$value."')";
				mysql_query( $sql);
			}
		}
		mysql_close($link); 
	}
	
}
?>