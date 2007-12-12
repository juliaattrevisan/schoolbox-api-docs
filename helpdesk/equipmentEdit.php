<?php
# File		: equipmentEdit.php
# Written	: Nick Doyle, 25/7/2002
# Description	: helpdesk equipment edit
// Written++ :enderana
// latest update :29-03-05

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleEquipmentEdit.php');
require_once( 'modules/moduleUserList.php');
require_once( 'modules/moduleEquipmentAdd.php');
require_once( 'modules/moduleEquipmentDelete.php');


$equipmentID = $_REQUEST[ 'equipmentID'];

if ($_REQUEST['delete'] && isset($_REQUEST['delete']) && $_REQUEST['equipmentID'] && isset($_REQUEST['equipmentID'])){

	$moduleEquipmentDelete = new ModuleEquipmentDelete(); 
	$moduleEquipmentDelete->delete($_REQUEST['equipmentID']);
	redirectTo( 'jobList.php');
	

}
else if( $equipmentID <> '') {
	$_TITLE = 'Equipment Edit';
	$_ACTION = 'update';
}
else {
	$_TITLE = 'Equipment Add';
	$_ACTION = 'create';
}

$page = new HelpDeskPage;
$page->authCheck(3);
$page->setTitle( $_TITLE);

$moduleEquipmentEdit = new ModuleEquipmentEdit( $equipmentID);
$moduleEquipmentEdit->setAction( $_ACTION);

if( isset( $_REQUEST[ 'create']) or isset( $_REQUEST[ 'update'])) {
	$userID = $_REQUEST[ 'userID'];
	$user = $_REQUEST[ 'user'];
	//print_r ($_REQUEST);
	
	$action = $_REQUEST[ 'create'] . $_REQUEST[ 'update'];

	if($_REQUEST[ 'userNonSynergy'] == '') {

			# This section tests whether or not to do the create/update
			# We will do this if there is no user specified, or the user is unambiguously specified
			# (their $userID is set or $user is set and only one user matched it)
			# If we don't do the create/update, print a list of the users that did match
		$blnDo = false;					# whether or not to do the create/update
	
		if( ($user == '') and ($userID == '')) {	# If there's no user associated
			$blnDo = true;
		}
		elseif( $userID <> '') {			# If userID set, do it
			$blnDo = true;
		}
        		# Check the number of user matches
		elseif( $user <> '') {
			mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
			mysql_select_db( DB_NAME_PORTAL);
			$sql = SQL_userListOneTerm( $user);
			$query = mysql_query( $sql)
				or die( "equipmentEdit - couldn't get potential user matches from synergy. sql :<br>\n" . $sql);
			$num_rows = mysql_num_rows( $query);
			if( $num_rows == 1) {			# If only one user matches, do it
				$row = mysql_fetch_array($query);
				//$userID = $row['synergyID'];
				$userID = $row['portalID'];
				
				$blnDo = true;
			}

		}
	}
	else {							# User is non-synergy, do it
		$blnDo = true;
	}
	
	if( $blnDo) 
	{
		if( $_REQUEST[ 'dateNone']) {
			unset( $dateOfPurchase);
		}
		else {
			$dateOfPurchase = $_REQUEST[ 'dateYear'] . '-' . $_REQUEST[ 'dateMonth'] . '-' . $_REQUEST[ 'dateDay'];
		}

		$arrDetails = array (
			'equipmentID' 	=> $_REQUEST[ 'equipmentID'],
			'serial' 	=> $_REQUEST[ 'serial'],	
			'equipmentTypeID'=> $_REQUEST[ 'equipmentTypeID'],
			'modelID'	=> $_REQUEST[ 'modelID'],
			'equipmentTypeID' => $_REQUEST[ 'equipmentTypeID'], 
			'warrantyYears'	=> $_REQUEST[ 'warrantyYears'],
			'dealerID'	=> $_REQUEST[ 'dealerID'],
			'ownerID'	=> $_REQUEST[ 'ownerID'],
			'active'	=> $_REQUEST[ 'active'],
			'insurance' => $_REQUEST['insurance'],
			'dateOfPurchase'=> $dateOfPurchase
		);
		
		if($_REQUEST[ 'userNonSynergy']){
			$arrDetails['userNonSynergy'] = $_REQUEST['user'];
		}
		else{
			$arrDetails['portalUserID'] = $userID;
		}
			# Set location
		if( $_REQUEST[ 'locationIDPrimary'] == DB_ID_LOCATION_SCHOOL) {
			$locationID = $_REQUEST[ 'locationID'];
		}
		else {
			$locationID = $_REQUEST[ 'locationIDPrimary'];
		}
		$arrDetails[ 'locationID'] = $locationID;

			# active thing
		if( isset( $_REQUEST[ 'active'])) {
			$arrDetails[ 'active'] = 1;
		}
		else {
			$arrDetails[ 'active'] = 0;
		}

		if( $action == 'create') {
			$moduleEquipmentAdd = new ModuleEquipmentAdd();
			$equipmentID = $moduleEquipmentAdd->add( $arrDetails);
			$moduleEquipmentAdd->addExtraFields($equipmentID); //code abstracted to module for conformity
	
		}
		elseif( $action == 'update') {
			//$moduleEquipmentEdit = new ModuleEquipmentEdit();
			$moduleEquipmentEdit->update( $arrDetails);
			$equipmentID = $arrDetails[ 'equipmentID'];
			$moduleEquipmentEdit->updateExtraFields($_POST); //code abstracted to module for conformity				
		}
		else {
			die( 'equipmentEdit.php - Unrecognised action ' . $action);
		}

		redirectTo( 'equipmentDetails.php?prevaction=' . $action . 'd&equipmentID=' . $equipmentID);	
	}
	elseif( $num_rows > 1) {	# Else, give a list of possible users
		$moduleUserList = new ModuleUserList( $query, 'radioButtons', 'noBorder');
		$userList .= $moduleUserList->getContent();
		$moduleEquipmentEdit->setUserList( $userList);
	}
	else {					# No matches
		$userList = '<p>No users matched your criteria. Please try again.</p>';		
	}
}

if( $_ACTION == 'create') {
	$page->doJavascriptInit();
}

$content = $moduleEquipmentEdit->getContent();
$javascript = $moduleEquipmentEdit->getJavascript();

$page->addJavascript( $javascript);
$page->setContent( $content);

$page->printPage();

?>
