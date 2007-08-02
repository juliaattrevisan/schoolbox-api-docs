<?php
# File		: moduleEquipmentDelete.php
# Written	: Jo Treweek, 30/10/2003 
# Description	: module to delete equipment 

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEquipmentDelete extends HelpDeskPage {

	function moduleEquipmentDelete() {
	}

	function delete($equipmentID) {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);

		$sql = SQL_equipmentDelete($equipmentID);
		mysql_query($sql);
		if( mysql_error() <> '') {
			die( 'Error deleting equipment : ' . mysql_error());
		}
		//insert query to find jobID's with equipmentID = $equipmentID
		//loop through deleting jobs and events with jobID's found
/***
		if ($jobID){
			$sql = SQL_jobDelete( $jobID);
			mysql_query( $sql);

			$sql = SQL_jobEventsDelete( $jobID);
			mysql_query( $sql);
		}
***/
		return 1;
	}
}
?>
