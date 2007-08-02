<?php
# File		: moduleJobAdd.php
# Written	: Nick Doyle, 4/9/2002
# Description	: module to add a job

require_once( 'common.php');
require_once( 'sql.php');
require_once( 'modules/moduleEventAdd.php');

class ModuleJobAdd extends HelpDeskPage {

	function moduleJobAdd() {
	}

	function add( &$details) {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$sql = SQL_jobAdd( $details);
		mysql_query( $sql);

		if( mysql_error() <> '') {
			die( 'moduleJobAdd - Error adding job to database : ' . mysql_error());
		}

		$job_id = mysql_insert_id();
		
		//insert the job created event
		$moduleEventAdd = new ModuleEventAdd();
		$arrDetails = array(
			'jobID'			=> $job_id,
			'eventTypeID'	=> DB_ID_EVENT_JOB_CREATED,
			'description'	=> '',
			'userID'		=> $_SESSION[ 'synergy_ID'],
			'portalUserID' 	=> $details['portalCreatedUserID'],
		);
		$moduleEventAdd->add( $arrDetails);

		//if job was created for someone other than the original user notify them
		if($details['portalAssignedUserID'] != $details['portalCreatedUserID'])
		{
			$arrDetails = array(
			'jobID'			=> $job_id,
			'eventTypeID'	=> DB_ID_EVENT_JOB_REASSIGNED,
			'description'	=> 'to ' . getUserFull($details['portalAssignedUserID']),
			'userID'		=> $_SESSION[ 'synergy_ID'],
			'portalUserID' 	=> $details['portalCreatedUserID'],
			);
			$moduleEventAdd->add( $arrDetails);
		}	
	
		return $job_id;
	}
}

?>
