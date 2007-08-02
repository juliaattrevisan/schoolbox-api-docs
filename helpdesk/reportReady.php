<?php
# File		: reportReady.php
# Written	: Nick Doyle, 19/11/2002
# Description	: report on notebooks awaiting courier pickup
#		  Currently gets all jobs ready for pickup (notebook or otherwise)

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleReportReady.php');
require_once( 'modules/moduleEventAdd.php');
require_once( 'modules/moduleJobEdit.php');
require_once( 'modules/moduleEquipmentEdit.php');

define( '_TITLE', 'Items for pickup');

$page = new HelpDeskPage;
$page->authCheck(2);

	# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'campusID'	=> '');

foreach( array_keys( $arrParms) as $key) {
	$arrParms[ $key] = $_REQUEST[ $key];
}

$moduleReportReady = new ModuleReportReady( $arrParms);

$do = $_REQUEST[ 'doAction'];
			# If they've pressed the 'mark...' button,
			# mark the jobs as 'returned from courier'
			# and change any associated equipment's location to owner
if( $do == 'markAsReturned') {
	for( $i=0 ; $i < $_REQUEST[ 'maxCheckboxes'] ; $i++) {
		$jobID = $_REQUEST[ 'jobID_' . $i];
		if( $jobID != '') {
				# Add 'returned to owner' event for the job
			$moduleEventAdd = new ModuleEventAdd();
			$arrDetails = array(
				'jobID'         => $jobID,
				'eventTypeID'   =>DB_ID_EVENT_OWNER_RETURNED, 
				'description'   => '',
				'userID'        => $_SESSION[ 'userid']
			);
			$moduleEventAdd->add($arrDetails);
				# Update job :
				#	status = closed
			$moduleJobEdit = new ModuleJobEdit();
			$arrDetails = array(	'jobID'	=> $jobID,
						'statusID'=> DB_ID_STATUS_CLOSED,
						'latestEvent' => DB_ID_EVENT_OWNER_RETURNED);  
			$moduleJobEdit->update( $arrDetails);
				# CHANGE LOCATION OF EQ TO 'USER'
 			mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
                	mysql_select_db( DB_NAME);
			$sql = SQL_jobDetails($jobID);
			$job = mysql_query($sql);
			$moduleEquipmentEdit = new ModuleEquipmentEdit();
			$arrDetails = array('equipmentID' => $job['equipmentID'],
					    'locationID' => '72' #user
			); 
			$moduleEquipmentEdit->update($arrDetails);
		}
	}
	redirectTo( 'reportReady.php?campusID=' . $_REQUEST[ 'campusID']);
}
$page->setTitle( _TITLE);

$content = $moduleReportReady->getContent();
$page->setContent( $content);

$page->printPage();

?>
