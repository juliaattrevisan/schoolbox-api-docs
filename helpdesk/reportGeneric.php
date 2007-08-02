<?php
# File		: reportCourier.php
# Written	: Nick Doyle, 19/11/2002
# Description	: report on notebooks awaiting courier pickup
#		  Currently gets all jobs ready for pickup (notebook or otherwise)

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleReportCourier.php');
require_once( 'modules/moduleEventAdd.php');

$page = new HelpDeskPage;
$page->authCheck(2);

	# Build array of search parms that we want to select on (if set they will go into where clause)
$arrParms = array(	'campusID'	=> '');

foreach( array_keys( $arrParms) as $key) {
	$arrParms[ $key] = $_REQUEST[ $key];
}

$moduleReportCourier = new ModuleReportCourier( $arrParms);

$do = $_REQUEST[ 'do'];

				# If doing hardcopy, do extra stuff (hide menus, tell to print)
if( ($do == 'print')) {
	define( '_TITLE', 'MGS courier pickup - ' . $_REQUEST[ 'campus']);
	$page->removeMenus();
	$page->removeLogo();
	$page->addJavascript( 'print();');
	$moduleReportCourier->hardcopy();
	$moduleReportCourier->setCampus( $_REQUEST[ 'campus']);
}
else {				# Mark the jobs as 'left for courier'
	if( $do == 'markAsLeft') {
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);

		for( $i=0 ; $i < $_REQUEST[ 'maxCheckboxes'] ; $i++) {
			$jobID = $_REQUEST[ 'jobID_' . $i];
			if( $jobID != '') {
				$moduleEventAdd = new ModuleEventAdd();
				$arrDetails = array(
					'jobID'         => $jobID,
					'eventTypeID'   => DB_ID_COURIER_PICKED_UP,
					'description'   => '',
					'userID'        => $_SESSION[ 'userid']
				);
				$moduleEventAdd->add( $arrDetails);
			}
		}
		mysql_close( $link);
		redirectTo( 'reportCourier.php?campusID=' . $_REQUEST[ 'campusID']);
	}
	define( '_TITLE', 'Notebooks for courier pickup');
}
$page->setTitle( _TITLE);

$content = $moduleReportCourier->getContent();
$page->setContent( $content);

$page->printPage();

?>
