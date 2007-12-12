<?php
# File		: moduleJobDetails.php
# Written	: Nick Doyle, 5/9/2002
# Description	: module to print job details

require_once( 'common.php');
require_once( 'sql.php');
require_once( 'helpDeskPage.php');

class ModuleJobDetails extends HelpdeskPage{
	var $_TPL_NAME = 'jobDetails.html';
	var $_tpl;
	var $_jobID;
	var $_prevAction;				# Action taken prior to this page,
	var $_eventList;
							# will create javascript alert box if set

	function moduleJobDetails( $jobID) {
		if( func_num_args() > 1) {
			$this->_prevAction = func_get_arg( 1);
		}
		$this->_jobID = $jobID;
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
		$this->_jobID = $jobID;
		$this->_sql = SQL_jobDetails( $this->_jobID);
		
		//if user has permission allow them to delete attachments
		if($_SESSION['authlevel'] == 3) {
			$this->_showDelete = true;
		}
		else {
			$this->_showDelete = false;
		}
	}

	function getContent() {
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link);
		$query = mysql_query( $this->_sql);
		$row = mysql_fetch_array( $query);
		print mysql_error();
		$row = stripslashes_array($row);
		
		if($row['priority'] == "")
			$row['priority'] = "Normal";

		if( $row[ 'equipmentOwnerName'] <> '') {
			$equipmentUser = $row[ 'equipmentOwnerName'];
		}
		elseif( $row[ 'equipmentUserNonSynergy'] <> '') {
			$this->removeEquipmentUserLink( $row[ 'equipmentUserNonSynergy']);
		}
		elseif( $row[ 'equipmentUserSynergyID'] <> '')  {
			$equipmentUser = 'Synergy ID ' . $row[ 'equipmentUserSynergyID'];
		}
		else {
			$equipmentUser = '';
		}

		$this->_tpl->set_var( 'JOB_ID', $row[ 'jobID']);
		$this->_tpl->set_var( 'DESCRIPTION', scrubTEXT($row[ 'description']));
		$this->_tpl->set_var( 'PROBLEM', $row[ 'problem']);
		$this->_tpl->set_var( 'STATUS', $row[ 'status']);
		$this->_tpl->set_var( 'CREATED_USER', $row[ 'createdUser']);
		$this->_tpl->set_var( 'CREATED_USER_PORTAL_ID', $row[ 'portalCreatedUserID']);//need att
		$this->_tpl->set_var( 'CREATED_DATE', $row[ 'createdDate']);
		$this->_tpl->set_var( 'CREATED_TIME', $row[ 'createdTime']);
		$this->_tpl->set_var( 'EQUIPMENT_SERIAL', $row[ 'equipmentSerial']);
		$this->_tpl->set_var( 'EQUIPMENT_ID', $row[ 'equipmentID']);
		$this->_tpl->set_var( 'PRIORITY', "<div style='color: #{$row['priorityColour']}'>".$row[ 'priority'].'</div>');
		$this->_tpl->set_var( 'EQUIPMENT_USER', $equipmentUser);
		$this->_tpl->set_var( 'EQUIPMENT_USER_PORTAL_ID', $row[ 'equipmentOwnerPortalID']);
		$this->_tpl->set_var( 'EQUIPMENT_MODEL', $row[ 'equipmentModel']);
		$this->_tpl->set_var( 'ASSOCIATED_USER', $row[ 'associatedUserName']);
		$this->_tpl->set_var( 'ASSOCIATED_USER_ID', $row[ 'associatedUserSynergyID']);		
		$this->_tpl->set_var( 'ASSOCIATED_USER_PORTAL_ID', $row[ 'portalAssociatedUserID']);
		$this->_tpl->set_var( 'CAMPUS', $row[ 'campus']);
		$this->_tpl->set_var( 'EVENT_LIST', $this->eventList);

		if( $row[ 'portalAssociatedUserID'] == '' ) {
			$this->removeAssociatedUser();
		}
		if( $row[ 'equipmentID'] == '') {
			$this->removeEquipment();
		}

			# Due date
		$dueDate = $row[ 'dueDate'];
		if( isset( $dueDate)) {
			$this->_tpl->set_var( 'DUE_DATE', $row[ 'dueDate']);
		}
		else {
			$this->_tpl->set_block( 'tplContent', 'DUE_DATE');
			$this->_tpl->set_var( 'DUE_DATE', '');
		}

			# Assigned user
		if( $row[ 'assignedUser'] <> '') {
			$this->_tpl->set_var( 'ASSIGNED_USER', $row[ 'assignedUser']);
			$this->_tpl->set_var( 'ASSIGNED_USER_PORTAL_ID', $row[ 'portalAssignedUserID']);
		}
		else {
			$this->_tpl->set_var( 'ASSIGNED_USER', 'all');
		}

		# Active
		if( $row[ 'active'] == 1) {
			$active = 'yes';
		}
		else {
			$active = 'no';
		}
		$this->_tpl->set_var( 'ACTIVE', $active);


		# Associated user
		if( isset( $row[ 'associatedUserID'])) {
			$link_ms = mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
			mysql_select_db( DB_NAME_PORTAL);
			$query = mysql_query( SQL_userDetails( $row[ 'associatedUserID']));
			if ($query != 1){
			$row_ms = mysql_fetch_array($query); 
			mysql_close( $link_ms);
			$this->_tpl->set_var( 'ASSOCIATED_USER', $row_ms[ 'firstname'] . ' ' . $row_ms[ 'surname']);
			}
		}
		else {
			$this->_tpl->set_var( 'USER', '(none assigned)');
		}

			# Specs - not yet implemented
		#$query = mysql_query( SQL_jobSpecs( $jobID));
		
		# File list
	
		$this->_tpl->set_block( 'tplContent', 'fileDelete', 'fDelete');
		$this->_tpl->set_block( 'tplContent', 'fileList', 'blkFileList');
		$this->_tpl->set_block( 'fileList', 'fileItem', 'blkFileItem');	
	
		
		$sql = SQL_fileList($row[ 'jobID']);
		$res = mysql_query($sql);
	
		if(($numRows = mysql_num_rows($res)) > 0) 
		{
			
			while (list($fileID, $jobID, $fileName) = mysql_fetch_array($res)) {
		
				$this->_tpl->set_var('FILE_ITEM_LINK', $jobID . "." . $fileID . "." . $fileName);
				$this->_tpl->set_var('FILE_ITEM_NAME', $fileName);
				
				
				
					
				if($this->_showDelete){
					
					$this->_tpl->set_var( 'JOB_FILE_ID', $fileID);	
					$this->_tpl->parse('fDelete', 'fileDelete', false);
				}
				else{
					$this->_tpl->set_var('fDelete', '');
				}
						
				$this->_tpl->parse( 'blkFileItem', 'fileItem', true);		
			}
			
			$this->_tpl->parse('blkFileList', 'fileList');
		}
		else
			$this->_tpl->set_var('blkFileList','');

		mysql_close( $link);

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}

	function setEventList( $eventList) {
		$this->eventList = $eventList;
	}

	function removeEquipment() {
		$this->_tpl->set_block( 'tplContent', 'EQUIPMENT_ROW');
		$this->_tpl->set_var( 'EQUIPMENT_ROW', '');
	}

	function removeAssociatedUser() {
		$this->_tpl->set_block('tplContent', 'ASSOCIATED_USER_ROW');
		$this->_tpl->set_var( 'ASSOCIATED_USER_ROW', '');
	}

		# Remove the clickable link to the user (for non-synergy users)
	function removeEquipmentUserLink( $user) {
		$this->_tpl->set_block( 'tplContent', 'equipmentUserLink');
		$this->_tpl->set_var( 'equipmentUserLink', $user);
	}



}

?>
