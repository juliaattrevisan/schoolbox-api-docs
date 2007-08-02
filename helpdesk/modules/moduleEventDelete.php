<?php
# File		: moduleEventDelete.php
# Written	: Nick Doyle, 4/10/2002
# Description	: module to delete an event

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEventDelete extends HelpDeskPage {

	function moduleEventDelete() {
	}

	function delete($jobID, $jobEventID) {
		$sql = SQL_eventDelete( $jobEventID);
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		mysql_query( $sql);
		if( mysql_error() <> '') {
			die( 'Error deleting event : ' . mysql_error());
		}
			// Must update the latestEvent field in tblJob.....
		$query = mysql_query("	select * 
					from tblJobEvent 
					where jobID = ".$jobID
		);
		while( $result = mysql_fetch_array($query)) {
			if (!$prev || $prev['createdDate'] < $result['createdDate'])
				$max = $result;
			$prev = $result;
		}
        	if ($max['eventTypeID'] <> NULL){
                	mysql_query("	update tblJob 
				    	set latestEvent = ".$max['eventTypeID']." 
					where jobID = ".$max['jobID']) 
			or die( 'Error setting latest event for '.$max['jobID'].' to '.$max['eventTypeID'].' : '.mysql_error());
		}
	}
}
