<?php
# File		: jobEdit.php
# Written	: Nick Doyle, 21/8/2002
# Description	: helpdesk job edit

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleJobEdit.php');
require_once( 'modules/moduleJobAdd.php');
require_once( 'modules/moduleJobDelete.php');
require_once( 'modules/moduleEquipmentList.php');
require_once( 'modules/moduleUserList.php');

$page = new HelpDeskPage;
$page->authCheck(2);

$jobID = $_REQUEST['jobID'];

$moduleJobEdit = new ModuleJobEdit( $jobID);

$doAction = $_REQUEST[ 'doAction'];		# The action to perform - update, delete etc.
						# Used to be called 'action' but this clashed with the form's native 'action' element



if( $jobID != '') {
	$_TITLE = 'Task Edit';
	$_DO = 'update';
}
else {
	$_TITLE = 'Task Add';
	$_DO = 'create';
	$moduleJobEdit->removeDeleteButton();
}

$page->setTitle( $_TITLE);

if( $doAction == 'delete') {
	$moduleJobDelete = new ModuleJobDelete();
	$moduleJobDelete->delete( $jobID);
	$moduleJobEdit->setContent( 'Job deleted.');
}
elseif ($doAction == 'deleteFile' && isset($_REQUEST['FileID'])) {
	
	mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db( DB_NAME);
	
	$sql = "SELECT * FROM tblFile WHERE fileID = '" . (int)$_REQUEST['FileID'] . "' LIMIT 1";
	$res = mysql_query($sql) or die('Error: ' .  mysql_error());
	list($fileID, $jobID, $fileName) = mysql_fetch_assoc($res);
	$filePath = UPL_DIR . $jobID . "." . $fileID . "." . $fileName;
	@unlink($filePath);
	
	$sql = "DELETE FROM tblFile WHERE fileID = '" . (int)$_REQUEST['FileID'] . "' LIMIT 1";
	$res = mysql_query($sql) or die('Error: ' .  mysql_error());
	
	redirectTo( 'jobDetails.php?prevaction=' . $action . 'd&jobID=' . (int)$_REQUEST['jobID']);
	
}
elseif(( $doAction == 'create') or ($doAction == 'update')) {

	$serial = $_REQUEST[ 'serial'];
	$userID = $_REQUEST[ 'userID'];

	if( $_REQUEST[ 'associatedUser'] <> '') {
		$associatedUser = $_REQUEST[ 'associatedUser'];
	}
	else {
		$associatedUser = $_REQUEST[ 'user'];
	}

		# This first section is concerned with checking the associated user/equipment itemm.
		# Each job can have : one user XOR one equipment XOR no equipment/user items associated with it
	if(( $serial <> '') and ($associatedUser <> '')) {
		die( 'Error - both a user and an equipment item were associated with the job, not creating.
			Please press back and remedy, then try again.');
	}

	$blnNoAssociated = false;

	if( $_REQUEST[ 'equipmentID'] <> '') {
		$equipmentID = $_REQUEST[ 'equipmentID'];
		$num_rows = 1;	# hackness, w0Ot
	}
	elseif( $userID <> '') {						# UserID set, update/add
		$row = array( 'portalID'	=> $userID);
		$num_rows = 1;	# hackness, w0Ot
	}
										# Get the number of equipment items that 
										# match the serial provided
	elseif( $serial <> '') {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$parms = array(	'serial'	=> $serial,
				'active'	=> 1);
		$sql = SQL_EquipmentList( $parms, false);
		$query = mysql_query( $sql);
		$num_rows = mysql_num_rows( $query);
		$row = mysql_fetch_array( $query);
		$equipmentID = $row[ 'equipmentID'];
		mysql_close();
	}
	elseif( $associatedUser <> '')  {					# Get the number of user matches that
										# match the user term provided
		mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
		mysql_select_db( DB_NAME_PORTAL);
		$sql = SQL_userListOneTerm( $associatedUser);
		$query = mysql_query( $sql);
		if ($query != 1){
			$row = mysql_fetch_array( $query);
			$num_rows = mysql_num_rows( $query);
			mysql_close();
		}
	}
	else {									# no serial or user associated
		$blnNoAssociated = true;
	}

		# If only one equipment/user item matched, or there are no associated equipment/user items, do the add/update
	if( ($num_rows == 1) or $blnNoAssociated) {

		if( $_REQUEST[ 'dateNone']) {
			unset( $dueDate);
		}
		else {
			$dueDate = $_REQUEST[ 'dateYear'] . '-' . $_REQUEST[ 'dateMonth'] . '-' . $_REQUEST[ 'dateDay'];
		}

		$arrDetails = array (
			'jobID' 	=> $_REQUEST[ 'jobID'],
			'dueDate' 	=> $dueDate,
			'priorityID'	=> $_REQUEST['priorityID'],
			'equipmentID'	=> $equipmentID,
			'portalAssociatedUserID'=> $row[ 'portalID'],
			'campusID'	=> $_REQUEST[ 'campusID'],
			'assignedUserID'=> $_REQUEST[ 'assignedUserID'],
			'portalAssignedUserID'=> $_REQUEST[ 'assignedUserID'],
			'description'	=> $_REQUEST[ 'description'],
			'problemID'	=> $_REQUEST[ 'problemID'],
			'portalCreatedUserID'	=> $_SESSION[ 'portal_ID'],
		);

		if( $doAction == 'create') {
			$moduleJobAdd = new ModuleJobAdd();
			$jobID = $moduleJobAdd->add( $arrDetails);
		}
		elseif( $doAction == 'update') {
			$moduleJobEdit = new ModuleJobEdit();
			$moduleJobEdit->update( $arrDetails);
			$jobID = $_REQUEST[ 'jobID'];
		}
		else {
			die( "jobEdit.php - didn't specify a create or update =/");
		}
		
		if (is_file($_FILES['attachment']['tmp_name'])) {
	
			$moduleJobEdit = isset($moduleJobEdit) ? $moduleJobEdit : new ModuleJobEdit();
			$moduleJobEdit->saveAttachment( $jobID, $_FILES['attachment']);
			
		}

		redirectTo( 'jobDetails.php?prevaction=' . $action . 'd&jobID=' . $jobID);
	}
	elseif( $num_rows > 1) {	# Else, give a list of possible equipment/user

		if( $serial <> '') {							# multiple equipment matches
			$moduleEquipmentList = new ModuleEquipmentList( $parms, 'radioButtons', 'noBorder');
			$equipmentList .= $moduleEquipmentList->getContent();
			$moduleJobEdit->setEquipmentList( $equipmentList);
			$moduleJobEdit->removeAssociatedUSerSection();
		}
		elseif( $associatedUser <> '') {					# multiple user matches
			mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
			mysql_select_db( DB_NAME_PORTAL);
			$sql = SQL_userListOneTerm( $associatedUser);
			$query = mysql_query( $sql);

			$moduleUserList = new ModuleUserList( $query, 'radioButtons', 'noBorder');
			$associatedUserList .= $moduleUserList->getContent();
			$moduleJobEdit->setAssociatedUserList( $associatedUserList);
			$moduleJobEdit->removeEquipmentSection();
		}
	}
	else {				# No matches, say so
		if( $serial <> '') {							# no equipment matches
			$equipmentList = 'No equipment matched that serial. Please try again.';
			$moduleJobEdit->addJavascript( 'alert( "No equipment matched that serial, please try again.");');
		}
		elseif( $associatedUser <> '') {					# no user matches
			$userList = 'No users matched. Please try again.';
			$moduleJobEdit->addJavascript( 'alert( "No users matched, please try again.");');
		}
	}

}

$moduleJobEdit->setAction( $_DO);
	
if( $_DO == 'create') {
	$page->doJavascriptInit();
}

$content = $moduleJobEdit->getContent();
$javascript = $moduleJobEdit->getJavascript();

$page->addJavascript( $javascript);
$page->setContent( $content);

$page->printPage();

?>
