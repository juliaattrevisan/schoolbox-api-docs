<?php
# File		: moduleReportDealer.php
# Written	: Nick Doyle, 3/12/2002
# Description	: module to print notebooks at dealer (last event = 'picked up by courier')

require_once( 'common.php');
require_once( 'sql.php');

class ModuleReportDealer extends HelpdeskPage{
	var $_tpl_name = 'reportDealer.html';
	var $_table_class = 'table_equipment';
	var $_tpl;
	var $_arrParms;
	var $_hardcopy = false;
	var $_campus = '';

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mysql_query to use
	function ModuleReportDealer( $arrParms) {
		$this->_arrParms = $arrParms; 
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);
	}

	function getContent() {
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link)
			or die( "reportDealer - Couldn't select teh database. mysql_error : " . mysql_error());

		$sql = SQL_reportDealer($this->_arrParms);
		$query = mysql_query( $sql, $link)
			or die( 'Error querying database for module ReportDealer::getContent() : ' . mysql_error());

		$numrows = mysql_num_rows( $query);

		$this->populateSelect( 'Campus', $this->_arrParms[ 'campusID'], '(all campuses)', '');
		$this->populateSelect( 'Dealer', $this->_arrParms[ 'dealerID'], '(all providers)', '');

		if( $numrows == 0) {
			$this->removeEquipment();
		}
		else {
			$this->_tpl->set_block( 'tplContent', 'item', 'blkEquipment');
			$i = 0;						# counter for 'mark as returned' checkboxes
			$numRealRows = 0;				# has to be done manually
			while( $row = mysql_fetch_array( $query)) {
			 	$row = stripslashes_array($row);	
				$numRealRows++;

				$this->_tpl->set_var( 'TABLE_CLASS', $this->_table_class);
				$this->_tpl->set_var( 'JOB_ID', $row[ 'jobID']);
				$this->_tpl->set_var( 'SERIAL', $row[ 'equipmentSerial']);
				$this->_tpl->set_var( 'EQUIPMENT_ID', $row[ 'equipmentID']);
				$this->_tpl->set_var( 'MODEL', $row[ 'equipmentModel']);
 				$this->_tpl->set_var( 'DESCRIPTION', $row[ 'description' ]);
				$this->_tpl->set_var( 'I', $i++);

					# If equipment has ass. user, use this. Else print job's asstd user
				if( $row[ 'equipmentUserName'] <> '') {
					$this->_tpl->set_var( 'OWNER', $row[ 'equipmentUserName']);
					$this->_tpl->set_var( 'PORTAL_ID', $row[ 'equipmentUserID']);
				}
				else {
					$this->_tpl->set_var( 'OWNER', $row[ 'associatedUserName']);
					$this->_tpl->set_var( 'PORTAL_ID', $row[ 'portalAssociatedUserID']);
				}

					# If hardcopy hide the checkbox
				if( $this->_hardcopy) {
					$this->_tpl->set_var( 'CHECKBOX_BEFORE', '<!--');
					$this->_tpl->set_var( 'CHECKBOX_AFTER', '-->');
				}
				else {
					$this->_tpl->set_var( 'CHECKBOX_BEFORE', '');
					$this->_tpl->set_var( 'CHECKBOX_AFTER', '');
				}

				$this->_tpl->parse( 'blkEquipment', 'item', true);
			}
			$this->_tpl->set_var( 'TOTAL', $numRealRows);
			$this->_tpl->set_var( 'MAX_CHECKBOXES', $i);
			$this->_tpl->set_var( 'CAMPUS_ID', $this->_arrParms[ 'campusID']);
			$this->_tpl->set_var( 'DEALER_ID', $this->_arrParms[ 'dealerID']);

			if( $numRealRows == 0) {
				$this->removeEquipment();
			}
		}

		mysql_close( $link);

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}

	function removeEquipment() {
		$this->_tpl->set_block( 'tplContent', 'EQUIPMENT_SECTION');
		$this->_tpl->set_var( 'EQUIPMENT_SECTION', 'No jobs from this campus at this dealer.');
	}

	function setCampus( $campus) {
		$this->_campus = $campus;
	}

	function hardcopy() {
		$this->_hardcopy = true;
	}
}

?>
