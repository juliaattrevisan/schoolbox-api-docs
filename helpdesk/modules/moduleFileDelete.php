<?php
// File		: moduleFileDelete.php
// Description	: module to delete a file

require_once( 'common.php');
require_once( 'sql.php');

class ModuleFileDelete extends HelpDeskPage {

	function moduleFileDelete() {
		// 
	}

	function delete($jobID, $fileID) {
		
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
				
		$sql = "SELECT name FROM tblFile WHERE fileID = '" . (int)$fileID . "' LIMIT 1";
		$res = mysql_query($sql) or die('Error: ' . mysql_error());
		list($name) = mysql_fetch_array($res);
	
	
		$sql = SQL_fileDelete( $fileID);
		
		mysql_query( $sql);
	
		if( mysql_error() <> '') {
			die( 'Error removing file : ' . mysql_error());
		}
		
		@unlink(UPL_DIR . $jobID . "." . $fileID . "." . $name);
		
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
