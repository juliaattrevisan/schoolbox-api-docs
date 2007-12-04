<?php
# File		: moduleEquipmentDetails.php
# Written	: Nick Doyle, 1/7/2002
# Description	: module to print equipment details (in a table)

require_once( 'common.php');
require_once( 'sql.php');
require_once( 'helpDeskPage.php');

class ModuleEquipmentDetails extends HelpdeskPage{
	var $_tpl;
	var $_heading = 'Equipment';			# Heading for the top left of the table, default 'Equipment'
	var $_equipmentID;
	var $_tpl_name = 'equipmentDetails.html';
	var $_prevAction;				# Action taken prior to this page,
							# will notify user if set
	var $_showMore = False;				# Flag to show the 'more that match' link

	function moduleEquipmentDetails( $equipmentID) {
		$numArgs = func_num_args();
		switch( true) {
			case( $numArgs >= 3) :
				$this->_heading = func_get_arg( 2);
			case( $numArgs >= 2) :
				$this->_prevAction = func_get_arg( 1);
			default :
				break;
		}

		$this->_equipmentid = $equipmentid;
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);
		$this->_equipmentID = $equipmentID;
		$this->_sql = SQL_equipmentDetails( $this->_equipmentID);
	}

	function getContent() {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $this->_sql);
		$row = mysql_fetch_array( $query);
		$row = stripslashes_array($row);

		$this->_tpl->set_var( 'SERIAL', $row[ 'serial']);
		$this->_tpl->set_var( 'TYPE', $row[ 'equipmentType']);
		$this->_tpl->set_var( 'BRAND', $row[ 'brand']);
		$this->_tpl->set_var( 'MODEL', $row[ 'model']);
		$this->_tpl->set_var( 'DEALER', $row[ 'dealer']);
		$this->_tpl->set_var( 'OWNER', $row[ 'owner']);
		$this->_tpl->set_var( 'CAMPUS', $row[ 'campus']);
		$this->_tpl->set_var( 'INSURANCE', $row[ 'insurance']?'Yes':'No');
		$this->_tpl->set_var( 'ACTIVE', $row[ 'active']?'Yes':'No');
		$this->_tpl->set_var( 'LOCATION', "<a href='/equipmentList.php?locationID=".$row['locationID'] ."'>".$row['location']."</a>");
		$this->_tpl->set_var( 'DATEOFPURCHASE', $row[ 'dateOfPurchase']);
		$this->_tpl->set_var( 'WARRANTYYEARS', $row[ 'warrantyYears'] . ' years');
		$this->_tpl->set_var( 'EQUIPMENT_ID', $this->_equipmentID);
		$this->_tpl->set_var( 'HEADING', $this->_heading);
		if( $this->_prevAction <> '') {
			$this->_tpl->set_var( 'MESSAGE', 'Item successfully ' . $this->_prevAction . ' :<br><br>');
		}
		else {
			$this->_tpl->set_var( 'MESSAGE', '');
		}

		

		if($row['portalUserID']){
			if($row['equipmentOwnerName']){
				$this->_tpl->set_var( 'USER', $row['equipmentOwnerName'] . ' (' . $row[ 'userID'] . ')');
			}
			else{
				$this->_tpl->set_var( 'USER',$row[ 'userID']);
			}
			$this->_tpl->set_var( 'ASSIGNED_USER_ID', $row[ 'portalUserID']);
		}
		else if( $row[ 'userNonSynergy'] <> '') {
			$this->removeUserLink( $row[ 'userNonSynergy']);
		}
		else {
			$this->removeUserLink( '(none assigned)');
		}

			# Show specs
			mysql_select_db( DB_NAME);		
		$sql = "SELECT TC.* FROM tblTemplateContents TC,  tblEquipment E
			WHERE TC.equipmentID = E.equipmentID AND E.equipmentID = '". $_GET['equipmentID']."'";	
		$tempResult = mysql_query($sql);
		print mysql_error();
		$this->_tpl->set_block('tplContent', 'CUSTOM_FIELD_BLOCK' , 'custfieldB');
		
		if( mysql_num_rows($tempResult) > 0) 
		{
			$specs = '';
			
			while($rowTemp = mysql_fetch_array($tempResult))
			{
				$customfn = $rowTemp['fieldName'];
				$customf = $rowTemp['contents'];
				$this->_tpl->set_var( 'CUSTOMFIELDNAMEL', $customfn);
				$this->_tpl->set_var( 'CUSTOMFIELDL', $customf);
				
				if ($rowTemp = mysql_fetch_array($tempResult))
				{	
					$customfn = $rowTemp['fieldName'];
					$customf = $rowTemp['contents'];
					$this->_tpl->set_var( 'CUSTOMFIELDNAMER', $customfn);
					$this->_tpl->set_var( 'CUSTOMFIELDR', $customf);
					
					$this->_tpl->parse('custfieldB', 'CUSTOM_FIELD_BLOCK', true);		
				}
				else
				{
					$customfn = "";
					$customf = "";
					$this->_tpl->set_var( 'CUSTOMFIELDNAMER', $customfn);
					$this->_tpl->set_var( 'CUSTOMFIELDR', $customf);
					$this->_tpl->parse('custfieldB', 'CUSTOM_FIELD_BLOCK', true);	
				}
				
			}
		}
		else {
			$this->_tpl->set_block('tplContent', 'CUSTOM_FIELD_HEADER' , 'custfieldH');
			$this->_tpl->set_var('custfieldB', '');
			$this->_tpl->set_var( 'custfieldH', '');
			
		}

		mysql_close();

		if( !$this->_showMore) {
			$this->hideMore();
		}

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}

	function hideMore() {
		$this->_tpl->set_block( 'tplContent', 'MORE');
		$this->_tpl->set_var( 'MORE', '');
	}

	function showMore() {
		$this->_showMore = True;
	}


		# Remove the clickable link to the user (for non-synergy users)       
	function removeUserLink( $user) {
		$this->_tpl->set_block( 'tplContent', 'userLink1');
		$this->_tpl->set_var( 'userLink1', $user);
	}


}

?>
