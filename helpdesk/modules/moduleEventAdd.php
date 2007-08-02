<?php
# File		: moduleEventAdd.php
# Written	: Nick Doyle, 2/10/2002
# Description	: module to add an event

require_once( 'common.php');
require_once( 'sql.php');
require_once( 'comsInterfaceResponse.php');
require_once( 'moduleEquipmentEdit.php');

class ModuleEventAdd extends HelpDeskPage {

	function moduleEventAdd() {
	}

	function add( &$details) {
		$sql = SQL_eventAdd( $details);
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link);
		mysql_query( $sql, $link);
		$id = mysql_insert_id( $link);

		if( mysql_error() <> '') {
			die( 'Error adding event to database : ' . mysql_error());
		}

		# Get equipmentID for this job (if any)
		$sql = SQL_jobDetails( $details[ 'jobID']);
		$query = mysql_query( $sql, $link);
		$row = mysql_fetch_array( $query);
		$row = stripslashes_array($row);
		$equipmentID = $row[ 'equipmentID'];
		$campusID = $row[ 'campusID'];
		$arrParms = array( 'equipmentID'=> $equipmentID);

		# set latestEvent field for this job
		$latestEvent = $details['eventTypeID'];	
		$otherParms['latestEvent'] = $latestEvent;
		$otherParms['jobID'] = $details['jobID']; 
		
		# retrieve status that event leaves job in
		if($details['eventTypeID'])
		{
			$sql = SQL_eventStatus($details['eventTypeID']);
			$result = mysql_query($sql);
			if($result)
			{
				$eventStatus = mysql_fetch_array($result);
				if($eventStatus['status'])
					$otherParms['statusID'] = $eventStatus['status'];	
			}
		}			


		$sql = SQL_jobUpdate($otherParms);	
		mysql_query ( $sql);


		//if job has been reassigned send email to new owner
		
	
		switch($details['eventTypeID']) {
			case DB_ID_EVENT_JOB_CREATED :
				if(EMAIL_EVENT_NOTIFICATION_CLIENT){
					if($row['equipmentOwnerUsername']){
						$contact = $row['equipmentOwnerName'];
						$email = $row['equipmentOwnerUsername'] . MAIL_DOMAIN;
					}
					else {
						$contact = $row['associatedUserName'];
						$email = $row['associatedUserUsername'] . MAIL_DOMAIN;
					}
						
					emailResponse($email, 
						EMAIL_EVENT_JOB_CREATED_SUBJECT,
						"Your support request has been logged in the Helpdesk software<br /><br />
						Job ID : {$row['jobID']}<br />
						Problem : {$row['problem']}<br />
						Description : {$row['description']}<br /><br />
						You can monitor the progress of your job through the <a href=\"http://portal.mgs.vic.edu.au/modules/helpdesk/\">portal</a>. ");
				}
			break;
			case DB_ID_EVENT_JOB_REASSIGNED :
				if(EMAIL_EVENT_NOTIFICATION_SYSTEM){
					if($row['equipmentOwnerName'])
						$contact = $row['equipmentOwnerName'];
					else
						$contact = $row['associatedUserName'];
							
					emailResponse($row['assignedUsername'].MAIL_DOMAIN, 
						EMAIL_EVENT_JOB_REASSIGNED_SUBJECT, 
						"A task in the helpdesk has been reassigned to your authority.<br /><br />
						Job ID : {$row['jobID']}<br />
						Problem : {$row['problem']}<br />
						Description : {$row['description']}<br />
						Contact :  $contact<br /><br />
						You can view further information on the <a href=\"http://".$_SERVER['HTTP_HOST'] . dirname( $_SERVER['PHP_SELF']) . "/jobDetails.php?jobID=".$row['jobID']."\">helpdesk website</a>");  
				}
			break;
			case DB_ID_EVENT_JOB_COMPLETED :
				if(EMAIL_EVENT_NOTIFICATION_SYSTEM && $row['portalCreatedUserID'] != $_SESSION['portal_ID']) {
					if($row['equipmentOwnerName'])
						$contact = $row['equipmentOwnerName'];
					else
						$contact = $row['associatedUserName'];

                                        emailResponse($row['createdUsername'].MAIL_DOMAIN,
                                                EMAIL_EVENT_JOB_COMPLETED_SUBJECT,
                                                "A task you created has been completed by someone else<br /><br />
                                                Job ID : <a href=\"http://".$_SERVER['HTTP_HOST'] .
                                                dirname( $_SERVER['PHP_SELF']) . "/jobDetails.php?jobID=".$row['jobID']."\">{$row['jobID']}</a><br />
                                                Problem : {$row['problem']}<br />
                                                Description : {$row['description']}<br />
                                                Contact :  $contact<br />
						Message : {$details['description']}<br /><br />
						You can view further information on the <a href=\"http://".$_SERVER['HTTP_HOST'] . 
						dirname( $_SERVER['PHP_SELF']) . "/jobDetails.php?jobID=".$row['jobID']."\">helpdesk website</a>");
					
				}
				if(EMAIL_EVENT_NOTIFICATION_CLIENT){
					if($row['equipmentOwnerUsername']){
						$contact = $row['equipmentOwnerName'];
						$email = $row['equipmentOwnerUsername'] . MAIL_DOMAIN;
					}
					else {
						$contact = $row['associatedUserName'];
						$email = $row['associatedUserUsername'] . MAIL_DOMAIN;
					}

					emailResponse($email, 
						EMAIL_EVENT_JOB_COMPLETED_SUBJECT,
						"Your IT Helpdesk support request has been completed.<br /><br />
						Job ID : {$row['jobID']}<br />
						Problem : {$row['problem']}<br />
						Description : {$row['description']}<br /><br />");
				}
		
				
			break;
				
	
		}			

			# IF the job was for equipment, change its location
		if( $row[ 'equipmentID'] <> '') {
			switch($details[ 'eventTypeID']) {

			case DB_ID_EVENT_REIMAGE :
				//update the reimaged date field for the equipment
				$moduleEquipmentEdit = new ModuleEquipmentEdit( $row[ 'equipmentID']);
				$now = date('j/n/Y');
				$fields = array('ctfLast imaged date'=>$now, 'ctfImaged by'=>getUserFull($details['portalUserID']));					
				$moduleEquipmentEdit->updateExtraFields($fields); 
			break;

			case DB_ID_EVENT_COURIER_PICKED_UP : # If courier picked up, set equipment location to 'dealer'
				$locationID = DB_ID_LOCATION_DEALER;
				break;
			
			case DB_ID_EVENT_DEALER_RETURNED : # If returned from dealer, set location to helpdesk (at appt. campus)
					switch( $campusID) {
						case 1 :
							$locationID = DB_ID_LOCATION_HELPDESK_1;
							break;
						case 2 :
							$locationID = DB_ID_LOCATION_HELPDESK_2;
							break;
						case 3 :
							$locationID = DB_ID_LOCATION_HELPDESK_3;
							break;
					}
					break;
			case DB_ID_EVENT_OWNER_RETURNED : # If returned to user, set location to 'user'
				$locationID = DB_ID_LOCATION_USER;
				break;
			
			case DB_ID_EVENT_OWNER_READY : #if ready for user email user to notify
				if(EMAIL_EVENT_NOTIFICATION_CLIENT){
					emailResponse($row['equipmentOwnerUsername'].MAIL_DOMAIN,
							EMAIL_EVENT_READY_PICKUP_SUBJECT,
							"Work has been completed on {$row['equipmentModel']}.<br />
							Please pick it up from the Helpdesk at your campus.");  
				}
				break;
			}
			if($locationID)
			{
				$arrParms['locationID'] = $locationID;
				$sql = SQL_equipmentUpdate( $arrParms);
				mysql_query ( $sql);
			}
		
		}
		mysql_close( $link);
		return $id;
	}
}

?>
