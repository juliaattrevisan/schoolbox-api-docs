<?php
# File		: moduleJobEdit.php
# Written	: Nick Doyle, 21/8/2002
# Description	: module to add/edit job

require_once( 'common.php');
require_once( 'sql.php');
require_once( 'modules/moduleEventAdd.php');

class ModuleJobEdit extends HelpDeskPage {
	var $_TPL_NAME = 'jobEdit.html';
	var $_tpl;
	var $_jobID;
	var $_sql;
	var $_doAction;		# Action to perform - 'add', 'update' or 'delete'
	var $_blnAddedNew;
	var $_blnShowDate = false;
	var $_content;
	var $_associatedUserField;	# Whether or not to print the associated user field
	var $_equipmentField;	# Whether or not to print the associated user field

		# Optional argument of job id
	function moduleJobEdit() {
		$this->_jobID = true;
		unset( $this->_sql);
		if(( func_num_args() > 0) and func_get_arg(0) <> '') {
                        $this->_jobID = func_get_arg( 0);
			$this->_sql = SQL_JobDetails( $this->_jobID);
		}
		unset( $this->content);
		$this->_equipmentField = true;
		$this->_associatedUserField = true;
		$this->_blnAddedNew = true;
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
	}


	function getContent() {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);

		if( isset( $this->_content)) {
			return $this->_content;
		}
		$this->setEventList();

			# Equipment list - Check whether or not to print
		if( $this->_equipmentField == false) {
			$this->_tpl->set_block( 'tplContent', 'equipmentSearch');
			$this->_tpl->set_var( 'equipmentSearch', '');
		}
		else {                          # Print the 'equipment' field, and no equipment listing
			$this->_tpl->set_var( 'EQUIPMENT_LIST', '');
		}

			# Associated user list - Check whether or not to print
		if( $this->_associatedUserField == false) {
			$this->_tpl->set_block( 'tplContent', 'associatedUserSearch');
			$this->_tpl->set_var( 'associatedUserSearch', '');
		}
		else {                          # Print the 'associated user' field, and no associated user listing
			 $this->_tpl->set_var( 'ASSOCIATED_USER_LIST', '');
		}

		if( $_blnAddedNew == true) {
			$this->addJavascript( "alert( 'Item Added');");
		}

						# If we're editing one in the DB, get details from there
		if( isset( $this->_sql)) {
			$query = mysql_query( $this->_sql);
			$row = mysql_fetch_array( $query);
			$row = stripslashes_array($row);
		}
		else {				# Else set the details as in the request
			$row = array (
				'jobID'		=> '',
				'campusID'	=> $_SESSION['campusID'],
				'dueDate'	=> '',
				'priorityID'	=> '',
				'equipmentSerial'=> '',
				'equipmentID'	=> '',
				'user'		=> '',
				'userID'	=> '',
				'assignedUserID'=> '',
				'description'	=> '',
				'problemID'	=> '',
			);
			
			$this->createdRemove();

			foreach( array_keys( $row) as $key) {
				//if($key == 'assignedUserID') {
				//	$row['portalAssignedUserID'] = $_REQUEST[$key];
				//}
				if( $key == 'associatedUserID') {
					$row[ $key] = $_REQUEST[ 'user'];
				}
				else {
					$row[ $key] = $_REQUEST[ $key];
				}
			}

				# If no jobID, remove the jobID row
			if(( $row[ 'jobID'] == '') or (!isset( $this->_jobID))) {
				$this->removeJobID();
			}

			if( $row[ 'campusID'] == '') {
				$row[ 'campusID'] = $_SESSION[ 'campusID'];
			}
		}

			# jobID - Check whether or not to print
		if( $this->_jobID == false) {
			$this->_tpl->set_block( 'tplContent', 'JOB_ID_BLOCK');
			$this->_tpl->set_var( 'JOB_ID_BLOCK', '');
		}
	
		if($row[ 'priorityID'] == "")
			$row[ 'priorityID'] = 2;

			# If associated equipmentID specified, fix it at this
		if( $_REQUEST[ 'equipmentID'] <> '') {
			$this->setEquipment( $row[ 'equipmentID']);
			$this->removeAssociatedUserSection();
		}
			# If associated userID specified, fill in
		elseif( $_REQUEST[ 'userID'] <> '') {
			$this->removeEquipmentSection();
			$this->_tpl->set_var( 'ASSOCIATED_USER_ID', $row[ 'userID']);
			$this->_tpl->set_var( 'ASSOCIATED_PORTAL_USER_ID', '');
		}
		else {
			$this->_tpl->set_var( 'EQUIPMENT_SERIAL', '');
			$this->_tpl->set_var( 'EQUIPMENT_ID', '');
			$this->_tpl->set_var( 'ASSOCIATED_USER_ID', $row['associatedUserName']);
			$this->_tpl->set_var( 'ASSOCIATED_PORTAL_USER_ID', $row['portalAssociatedUserID']);
		}

		$this->_tpl->set_var( 'DESCRIPTION', $row[ 'description']);
		$this->_tpl->set_var( 'JOB_ID', $row[ 'jobID']);
		$this->_tpl->set_var( 'CREATED_DATE', $row[ 'createdDate']);
		$this->_tpl->set_var( 'CREATED_USER', $row[ 'createdUser']);
		$this->_tpl->set_var( 'CREATED_USER_ID', $row[ 'createdUserID']);
		$this->_tpl->set_var( 'DO_ACTION', $this->_doAction);
		$this->_tpl->set_var( 'USER', $row[ 'userID']);

		if( $row[ 'dueDate'] == '') {
			$this->_tpl->set_var( 'NO_DATE_CHECK', 'checked');
			$selectedDay = date( 'j');
			$selectedMonth = date( 'n');
			$selectedYear = date( 'Y');
		}
		else {	$this->showDate();
			$this->_tpl->set_var( 'NO_DATE_CHECK', '');
			preg_match( '/(\d+)-(\d+)-(\d+)/', $row[ 'dueDate'], $matches);
			$selectedDay = $matches[1];
			$selectedMonth = $matches[2]; 
			$selectedYear = $matches[3];
		}
		
		if($row['assignedUserID'] == ''){
			$row['assignedUserID'] = $_SESSION['portal_ID'];
		}
		
		$this->setEquipment( $row[ 'equipmentID']);
		
			# usage is : $this->populateSelect( table/block name (must be same), initiallySelected);
		$this->populateSelect( 'AssignedUser', $row['assignedUserID']);
		$this->populateSelect( 'Campus', $row[ 'campusID']);
		$this->populateSelect( 'Problem', $row[ 'problemID']);
		$this->populateSelect( 'Priority', $row[ 'priorityID']);

			# populate these selects with a number range (e.g. 1,2,3...31)
		$this->populateSelectNumberRange( 'dateDay', 1, 31, $selectedDay);
		$this->populateSelectNumberRange( 'dateMonth', 1, 12, $selectedMonth);
		$this->populateSelectNumberRange( 'dateYear', date( 'Y')-5, date( 'Y')+1, $selectedYear);


		# File list
		$this->_tpl->set_block( 'tplContent', 'fileList', 'blkFileList');
		$this->_tpl->set_block( 'fileList', 'fileItem', 'blkFileItem');
		
				
		
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link);
		
		$query = SQL_fileList($row[ 'jobID']);
		
		$res = mysql_query($query);
		
		$jobID = $row['jobID'];
	
		if (($numRows = mysql_num_rows($res)) > 0) {
			
			while (list($fileID, $jobID, $name) = mysql_fetch_array($res)) {
				
				$this->_tpl->set_var('FILE_ITEM_LINK', $jobID . "." . $fileID . "." . $name);
				$this->_tpl->set_var('FILE_ITEM_NAME', $name);
				
				$this->_tpl->parse( 'blkFileItem', 'fileItem', true);
						
			}
			
			$this->_tpl->parse('blkFileList', 'fileList');
			
			$this->_tpl->set_var( 'FILE_ID', $fileID);
	
		
			
		
		
		} else {
		
			$this->_tpl->set_var('blkFileList','');
		}

		//mysql_close();

		$this->showHideDate();

		return $this->_tpl->parse( 'tplContentParsed', 'tplContent');
	}

	function HTMLToReadable($string)
	{
        	$string = ucwords ($string);
        	$matches = preg_split('/([A-Z]+[a-z]*)/',$string, -1, 3);
        	return implode(' ', $matches);
	}

	function setAction( $doAction) {
		$this->_doAction = $doAction;
	}

	function setEventList() {
		$this->_tpl->set_var( 'EVENT_LIST', '');
	}


	function setEquipmentList( $equipmentList) {
		$this->removeEquipmentField();
		$this->_tpl->set_var( 'EQUIPMENT_LIST', $equipmentList);
	}

	function removeEquipmentField() {
		$this->_equipmentField = false;
	}

	function removeJobID() {
		$this->_jobID = false;
	}

                # Populate a select box in the template with values from teh location (primary)
		# e.g. School,  User, Dealer
                # Clears the sharpmist!
	function populateLocationPrimary( $selected) {
		$optionsName = 'OPTIONS_LocationPrimary';
		$blockName = 'blkLocationPrimary';

		$sql = SQL_LookupListLocationPrimary();
      		mysql_connect( DB_HOST, DB_USERNAME, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $sql)
			or die( 'populateSelectLocationPrimary() - Error getting spec options. DB error : ' . mysql_error());

		$this->_tpl->set_block( tplContent, $optionsName, $blockName);

		while( $row = mysql_fetch_array( $query)) {
			$this->_tpl->set_var( 'NAME', $row[ 'description']);
			$this->_tpl->set_var( 'VALUE', $row[ 'id']);

			if( $row[ 'id'] == $selected) {
				$this->_tpl->set_var( 'SELECTED', ' selected');
			}
			else {
				$this->_tpl->set_var( 'SELECTED', '');
			}
			$this->_tpl->parse( $blockName, $optionsName, true);
		}
		mysql_close();
	}

		# Update an job item
	function update( &$details) {
	
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		//first retrieve current information regarding job so we know what is being updated.
		$jobDetailsSQL = SQL_JobDetails($details['jobID']);
		$query = mysql_query( $jobDetailsSQL);
		$prevDetails = mysql_fetch_array( $query);
		
		//update the job
		$sql = SQL_jobUpdate( $details);
		mysql_query( $sql);

		if( mysql_error() <> '') {
			die( 'Error accessing database to update job : ' . print_r( $details) . '<br><br' . mysql_error());
		}
		
		$moduleEventAdd = new ModuleEventAdd();
		
		foreach( array_keys( $details) as $key){
			$eventTypeID = 0;

			if($key == 'assignedUserID' && $details[$key] != $prevDetails[$key]) {	
				//if reassigned create event reassign event with users name
				$eventTypeID = DB_ID_EVENT_JOB_REASSIGNED;
				$description = "to " . getUserFull($details[$key]);
			}
			else if($key == 'latestEvent'){
				//do nothing when updating latest event
			}
			else if($key == 'portalCreatedUserID'){
				//do nothing when updating created user
			}
			else if($key == 'portalAssignedUserID'){
				//do nothing when reassigning, we have duplicate data for stupid reasons
			}
			else if( $details[$key] != $prevDetails[$key] ) {
				//if any other fields have been changed note the change as an event
				$eventTypeID = DB_ID_EVENT_JOB_UPDATED;
				$description = $this->HTMLToReadable($key) ." field altered";
			}
			
			if($eventTypeID)
			{
				//if event has occurred insert
				$arrDetails = array(
					'jobID'			=> $details['jobID'],
					'eventTypeID'	=> $eventTypeID,
					'description'	=> $description,
					'userID'		=> $_SESSION[ 'synergy_ID'],
					'portalUserID' 	=> $details['portalCreatedUserID'],
				);
				$moduleEventAdd->add( $arrDetails);
			}
		}
	}
	
	
	function saveAttachment( $jobID, $attachment) {

	
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		
		$sql = SQL_fileAdd( array('jobID' => $jobID, 'name' => scrubTEXT($attachment['name'])));
		
		mysql_query( $sql);
		$fileID = mysql_insert_id();

		if( mysql_error() <> '') {
			die( 'Error accessing database to update file : ' . '<br><br' . mysql_error());
		}
		
		
		$saveAs = $jobID . "." . $fileID . "." . scrubTEXT($attachment['name']);
		
		move_uploaded_file($attachment['tmp_name'], UPL_DIR . $saveAs);
		
	
	}

	function setContent( $content) {
		$this->_content = $content;
	}


		# Remove the 'created' (username/date) row from output (used for new jobs where this is inapplicable)
		# Lovely estival weather.
	function createdRemove() {
		$this->_tpl->set_block( 'tplContent', 'CREATED', blkCreated);
		$this->_tpl->set_var( blkCreated, '');
	}


	function removeDeleteButton() {
		$this->_tpl->set_block( 'tplContent', 'button_delete');
		$this->_tpl->set_var( 'button_delete', '');
	}

	function removeAssociatedUserField() {
		$this->_associatedUserField = false;
	}
     
	function setAssociatedUserList( $associatedUserList) {
		$this->removeAssociatedUserField();
		$this->_tpl->set_var( 'ASSOCIATED_USER_LIST', $associatedUserList);
	}

	function removeEquipmentSection() {
		$this->_tpl->set_block( 'tplContent', 'EQUIPMENT_SECTION');
		$this->_tpl->set_var( 'EQUIPMENT_SECTION', '');
	}


	function showDate() {
		$this->_blnShowDate = true;
	}

	function showHideDate() {
		if( !$this->_blnShowDate) {
			$this->_tpl->set_var( 'VISIBILITY_DATE', 'hidden');
		}
		else {
			$this->_tpl->set_var( 'VISIBILITY_DATE', 'visible');
		}
	}


	function removeAssociatedUserSection() {
		$this->_tpl->set_block( 'tplContent', 'ASSOCIATED_USER_SECTION');
		$this->_tpl->set_var( 'ASSOCIATED_USER_SECTION', '');
	}

		# Set the equipment serial number from the equipmentID
	function setEquipment( $equipmentID) {
		if( $equipmentID <> '') {		# Fix the equipment to this one item
			mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
			mysql_select_db( DB_NAME);
			$sql = SQL_equipmentDetails( $equipmentID);
			$query = mysql_query( $sql);
			$row = mysql_fetch_array( $query);
	
			$this->_tpl->set_var( 'EQUIPMENT_ID', $equipmentID);
			$this->_tpl->set_var( 'EQUIPMENT_SERIAL', $row[ 'serial']);
			mysql_close();

			#$this->removeAssociatedUserSection();
		}

	}
}

?>
