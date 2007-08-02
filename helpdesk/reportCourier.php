<?php
# File		: reportCourier.php
# Written	: Nick Doyle, 19/11/2002
# Description	: report on notebooks awaiting courier pickup
#		  Currently gets all jobs ready for pickup (notebook or otherwise)

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleReportCourier.php');
require_once( 'modules/moduleEventAdd.php');
require_once( 'modules/moduleJobEdit.php');

$page = new HelpDeskPage;
$page->authCheck(2);

# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'campusID'	=> '',
			'dealerID'	=> '' );

foreach( array_keys( $arrParms) as $key) {
	$arrParms[ $key] = $_REQUEST[ $key];
}


//default to campus of user
if(!isset($_REQUEST['campusID'])){
        $arrParms['campusID'] = $_SESSION['campusID'];
}

$moduleReportCourier = new ModuleReportCourier( $arrParms);

$do = $_REQUEST[ 'doAction'];

# If doing hardcopy, do extra stuff (hide menus, tell to print)
if( ($do == 'print')) {
		# Set the page title
	define( '_TITLE', 'MGS courier pickup');

	$page->removeMenus();
	$page->removeLogo();
	$page->addJavascript( 'print();');
	$moduleReportCourier->hardcopy();
	$moduleReportCourier->setCampus( $arrParms['campusID']);
}
else {				# If they've pressed the 'mark...' button,
				# mark the jobs as 'left for courier'
				# and change any associated equipment's location to dealer
	if( $do == 'markAsLeft') {
		for( $i=0 ; $i < $_REQUEST[ 'maxCheckboxes'] ; $i++) {
			$jobID = $_REQUEST[ 'jobID_' . $i];
			if( $jobID != '') {

					# Add 'picked up by courier' event for the job
				$moduleEventAdd = new ModuleEventAdd();
				$arrDetails = array(
					'jobID'         => $jobID,
					'eventTypeID'   => DB_ID_EVENT_COURIER_PICKED_UP,
					'description'   => '',
					'userID'		=> $_SESSION[ 'synergy_ID'],
					'portalUserID' 	=> $_SESSION[ 'portal_ID'],
				);
				$moduleEventAdd->add( $arrDetails);
			}
		}
		redirectTo( 'reportCourier.php?campusID=' . $_REQUEST[ 'campusID']);
	}
	define( '_TITLE', 'Items for courier pickup');
}
$page->setTitle( _TITLE);
$content = $moduleReportCourier->getContent();
$page->setContent( $content);

$page->printPage();

?>
