<?php
# File		: moduleReportReady.php
# Written	: Nick Doyle, 3/12/2002
# Description	: module to print jobs ready for student pickup

require_once( 'common.php');
require_once( 'sql.php');

class ModuleReportReady extends HelpdeskPage{
	var $_tpl_name = 'reportReady.html';
	var $_table_class = 'table_equipment';
	var $_tpl;
	var $_arrParms;
	var $_campus = '';

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mysql_query to use
	function ModuleReportReady( $arrParms) {
		$this->_arrParms = $arrParms; 
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);
	}

	function getContent() {
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link)
			or die( "reportReady - Couldn't select teh database. mysql_error : " . mysql_error());

		$sql = SQL_reportReady($this->_arrParms);

		$query = mysql_query( $sql, $link)
			or die( 'Error querying database for module ReportReady::getContent() : ' . mysql_error());

		$numrows = mysql_num_rows( $query);

			# Populate campus select box
		$this->populateSelect( 'Campus', $_REQUEST[ 'campusID'], '(all campuses)', '');
		$this->_tpl->set_block( 'tplContent', 'SIGNATURE');
		$this->_tpl->set_var( 'SIGNATURE', '');

			# If no latest events (slightly dodge but search the array .... not optimal =/
		if( $numrows == 0) {
			$this->removeEquipment();
		}
		else {
			$this->_tpl->set_block( 'tplContent', 'item', 'blkEquipment');
			$i = 0;						# counter for 'mark as left' checkboxes
			$numRealRows = 0;				# has to be done manually
			while( $row = mysql_fetch_array( $query)) {
				$row = stripslashes_array($row);	
				$numRealRows++;
				$sql = SQL_jobDetails( $row[ 'jobID']);
				$queryEq = mysql_query( $sql, $link)
					or die( "reportReady - Couldn't get details for job. SQL was :<br>\n". $sql .
						"\n<br>\n<br>Error was :" . mysql_error());
				$rowEq = mysql_fetch_array( $queryEq);

				$this->_tpl->set_var( 'TABLE_CLASS', $this->_table_class);
				$this->_tpl->set_var( 'JOB_ID', $row[ 'jobID']);
				$this->_tpl->set_var( 'SERIAL', $rowEq[ 'equipmentSerial']);
				$this->_tpl->set_var( 'EQUIPMENT_ID', $rowEq[ 'equipmentID']);
				$this->_tpl->set_var( 'MODEL', $rowEq[ 'equipmentModel']);
				$this->_tpl->set_var( 'DESCRIPTION', $rowEq[ 'description']);
				$this->_tpl->set_var( 'I', $i++);

					# If equipment has ass. user, use this. Else print job's asstd user
				if( $rowEq[ 'equipmentUser'] <> '') {
					$this->_tpl->set_var( 'OWNER', $rowEq[ 'equipmentUser']);
					$this->_tpl->set_var( 'SYNERGY_ID', $rowEq[ 'equipmentUserID']);
				}     
				else {
					$this->_tpl->set_var( 'OWNER', $rowEq[ 'associatedUser']);
					$this->_tpl->set_var( 'SYNERGY_ID', $rowEq[ 'associatedUserID']);
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

			if( $numRealRows == 0) {
				$this->removeEquipment();
			}
		}

		mysql_close( $link);

		$this->_tpl->set_var( 'CAMPUS', $_REQUEST[ 'campus']);
		$this->_tpl->set_var( 'CAMPUS_ID', $_REQUEST[ 'campusID']);

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}

	function removeEquipment() {
		$this->_tpl->set_block( 'tplContent', 'EQUIPMENT_SECTION');
		$this->_tpl->set_var( 'EQUIPMENT_SECTION', 'No notebooks for pickup from this campus.');
	}

	function setCampus( $campus) {
		$this->_campus = $campus;
	}

	function hardcopy() {
		$this->_hardcopy = true;
	}
}

?>
