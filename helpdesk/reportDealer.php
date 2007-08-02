<?php
# File		: reportDealer.php
# Written	: Nick Doyle, 3/12/2002
# Description	: report on jobs at dealer (their last event was 'picked up bu courier')

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleReportDealer.php');
require_once( 'modules/moduleEventAdd.php');
require_once( 'modules/moduleJobEdit.php');

define( '_TITLE', 'Tasks at provider');

$page = new HelpDeskPage;
$page->authCheck(2);

	# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'campusID'	=> '',
			'dealerID'	=> '');

foreach( array_keys( $arrParms) as $key) {
	$arrParms[ $key] = $_REQUEST[ $key];
}

//default to campus of user
if(!isset($_REQUEST['campusID'])){
        $arrParms['campusID'] = $_SESSION['campusID'];
}

$moduleReportDealer = new ModuleReportDealer( $arrParms);

$do = $_REQUEST[ 'do'];

			# mark the jobs as 'left for courier'
			# and change any associated equipment's location to dealer
if( $do == 'markAsReturned') {
	for( $i=0 ; $i < $_REQUEST[ 'maxCheckboxes'] ; $i++) {
		$jobID = $_REQUEST[ 'jobID_' . $i];
		$details = $_REQUEST[ 'details_' . $i];
		if( $jobID != '') {
				# Add 'returned from dealer' event for the job
			$moduleEventAdd = new ModuleEventAdd();
			$arrDetails = array(
				'jobID'         => $jobID,
				'eventTypeID'   => DB_ID_EVENT_DEALER_RETURNED,
				'description'   => $details,
				'userID'        => $_SESSION[ 'userid']
			);
			$moduleEventAdd->add( $arrDetails);
 			$moduleJobEdit = new ModuleJobEdit();
                        $parms = array(
                        	'latestEvent' => DB_ID_EVENT_DEALER_RETURNED,
                        	'jobID' => $jobID   
                        );
                        $moduleJobEdit->update($parms);
		}
	}
	redirectTo( 'reportDealer.php?campusID=' . $_REQUEST[ 'campusID']);
}
$page->setTitle( _TITLE);

$content = $moduleReportDealer->getContent();
$page->setContent( $content);

$page->printPage();

?>
