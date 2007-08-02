<?php
# File		: moduleJobDelete.php
# Written	: Nick Doyle, 8/10/2002
# Description	: module to delete an job

require_once( 'common.php');
require_once( 'sql.php');

class ModuleJobDelete extends HelpDeskPage {

	function moduleJobDelete() {
	}

	function delete( $jobID) {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);

		$sql = SQL_jobDelete( $jobID);
		mysql_query( $sql);

		$sql = SQL_jobEventsDelete( $jobID);
		mysql_query( $sql);

		if( mysql_error() <> '') {
			die( 'Error deleting job : ' . mysql_error());
		}
	}
}
