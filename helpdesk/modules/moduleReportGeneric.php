<?php
# File		: moduleReportCourier.php
# Written	: Nick Doyle, 19/11/2002
# Description	: module to print notebooks awaiting pickup by courier
#		  This was trickier than previously thought, getting all
#		  events that are the latest for that job and are also 'Booked with dealer'

require_once( 'common.php');
require_once( 'sql.php');

class ModuleReportCourier extends HelpdeskPage{
	var $_tpl_name = 'reportCourier.html';
	var $_table_class = 'table_equipment';
	var $_eventDescription = DB_DESCRIPTION_COURIER_READY;
	var $_tpl;
	var $_arrParms;
	var $_hardcopy = false;
	var $_campus = '';

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mysql_query to use
	function ModuleReportCourier( $arrParms) {
		$this->_arrParms = $arrParms; 
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);
	}

	function getContent() {
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $link)
			or die( "reportCourier - Couldn't select teh database. mysql_error : " . mysql_error());

		$sql = SQL_latestEvents( $this->_arrParms);

		$query = mysql_query( $sql, $link)
			or die( 'Error querying database for module ReportCourier::getContent() : ' . mysql_error());

		$numrows = mysql_num_rows( $query);

		if( $this->_hardcopy) {
				# Remove menus and buttons
			$this->_tpl->set_block( 'tplContent', 'SELECT_CAMPUS');
			$this->_tpl->set_var( 'SELECT_CAMPUS', '');
			$this->_tpl->set_block( 'tplContent', 'BUTTON_PRINT');
			$this->_tpl->set_var( 'BUTTON_PRINT', '');
			$this->_tpl->set_block( 'tplContent', 'BUTTON_MARK');
			$this->_tpl->set_var( 'BUTTON_MARK', '');
			$this->doJavascriptInit();

		}
		else {
				# Populate campus select box
			$this->populateSelect( 'Campus', $_REQUEST[ 'campusID'], '(all campuses)', '');
			$this->_tpl->set_block( 'tplContent', 'SIGNATURE');
			$this->_tpl->set_var( 'SIGNATURE', '');
		}

			# Set date
		$date = getdate();
		$strDate = $date[ 'mday'] . '/' . $date[ 'mon'] . '/' . $date[ 'year'];
		$this->_tpl->set_var( 'DATE', $strDate);

			# If no latest events (slightly dodge but search the aray .... not optimal =/
		if( $numrows == 0) {
			$this->removeEquipment();
		}
		else {
			$this->_tpl->set_block( 'tplContent', 'item', 'blkEquipment');
			$i = 0;						# counter for 'mark as left' checkboxes
			$numRealRows = 0;				# has to be done manually
			while( $row = mysql_fetch_array( $query)) {
				$row = stripslashes_array($row));
				$parms = array(	'createdDate'	=> $row[ 'maxCreatedDate'],
						'jobID'		=> $row[ 'jobID']);
				$sql = SQL_eventDetails( $parms);
				$queryEv = mysql_query( $sql, $link);
				$rowEv = mysql_fetch_array( $queryEv);

				if( $rowEv[ 'eventTypeID'] == DB_ID_EVENT_DEALER_BOOKED) {
					$numRealRows++;
					$sql = SQL_jobDetails( $rowEv[ 'jobID']);
					$queryEq = mysql_query( $sql, $link)
						or die( "reportCourier - Couldn't get details for job. SQL was :<br>\n". $sql .
							"\n<br>\n<br>Error was :" . mysql_error());
					$rowEq = mysql_fetch_array( $queryEq);
	
					$this->_tpl->set_var( 'TABLE_CLASS', $this->_table_class);
					$this->_tpl->set_var( 'JOB_ID', $row[ 'jobID']);
					$this->_tpl->set_var( 'SERIAL', $rowEq[ 'equipmentSerial']);
					$this->_tpl->set_var( 'OWNER', $rowEq[ 'equipmentUser']);
					$this->_tpl->set_var( 'SYNERGY_ID', $rowEq[ 'equipmentUserID']);
					$this->_tpl->set_var( 'EQUIPMENT_ID', $rowEq[ 'equipmentID']);
					$this->_tpl->set_var( 'MODEL', $rowEq[ 'equipmentModel']);
					$this->_tpl->set_var( 'I', $i++);

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
