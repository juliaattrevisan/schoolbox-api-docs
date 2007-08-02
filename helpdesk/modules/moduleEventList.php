<?php
# File		: moduleEventList.php
# Written	: Nick Doyle, 24/9/2002
# Description	: module to print event listing

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEventList extends HelpDeskPage{
	var $_tpl_name = 'eventList.html';
	var $_tpl;
	var $_sql;
	var $_query;
	var $_jobID;
	var $_showEvents;

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mysql_query to use
	function ModuleEventList( &$searchParms) {
		$this->_noBorder = false;
		$this->_showEvents = true;
		if( func_num_args() > 1) {
			if( func_get_arg(1) == 'noBorder') {
				$this->_noBorder = true;
			}
		}
                        # Query with which to work was passed in
                if( !is_array( $searchParms) and isset( $searchParms)) {
                        $this->_query = $searchParms;
                }
                else {
                        $this->_sql = SQL_eventList( $searchParms, $showUsers);      // from sql.php
                }

		//if user has permission allow them to delete events
		if($_SESSION['authlevel'] == 3) {
			$this->_showDelete = true;
		}
		else {
			$this->_showDelete = false;
		}
		
		$this->_jobID = $searchParms[ 'jobID'];
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);
	}

	function getContent() {
		$this->_tpl->set_var( 'JOB_ID', $this->_jobID);

			# Set the bits for the 'add new event' row 
		$this->populateSelect( 'EventType', 0, '(event type - optional)', '');

			# Set the table border
		if( $this->_noBorder) {
			$this->_tpl->set_Var( 'TABLE_CLASS', 'table_border');
		}
		else {
			$this->_tpl->set_Var( 'TABLE_CLASS', 'table_event');
		}

		if( !isset( $this->_query)) {    
			mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
			mysql_select_db( DB_NAME);
			$query = mysql_query( $this->_sql)
				or die( 'Error querying database for module EventList::getContent() : ' . mysql_error());
		}
		elseif( isset( $this->_query)) {
			$query = $this->_query;
		}
		else {
			die( 'moduleEquipmnetList::getContent() - no sql or query defined, exiting');
		}

		if( mysql_num_rows( $query) == 0) {
			$this->removeEvents();
		}
		else {
			$this->_tpl->set_block( 'tplContent', 'event_delete', 'delete');
			$this->_tpl->set_block( 'tplContent', 'item', 'blkEvent');

			while( $row = mysql_fetch_array( $query)) {
				$row = stripslashes_array($row);
				$description = '';
				if( isset( $row[ 'eventTypeDescription'])) {
					$description = $row[ 'eventTypeDescription'];
				}
				if( isset( $row[ 'jobEventDescription'])) {
					$description .= "<br />".scrubTEXT($row[ 'jobEventDescription']);
				}
				
	
				$this->_tpl->set_var( 'DESCRIPTION', $description);
	
				if($row['eventOwnerName'])
				{
					$a = '<a href="userDetails.php?portalID=' . $row[ 'portalUserID'] . '">';
					$this->_tpl->set_var( 'USER', $a . $row[ 'eventOwnerName']."</a>");
				}
				else
					$this->_tpl->set_var( 'USER', 'Unknown');
					
				$this->_tpl->set_var( 'DATE', $row[ 'createdDate']);
				
				if($this->_showDelete){
					$this->_tpl->set_var( 'JOB_EVENT_ID', $row[ 'jobEventID']);
					$this->_tpl->parse('delete', 'event_delete', false);
				}
				else{
					$this->_tpl->set_var('delete', '');
				}

				$this->_tpl->parse( 'blkEvent', 'item', true);
			}
		}
		mysql_close();

		$this->showHideEvents();
		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}


		# Set flag for recognition later in calling of showHideEvents
	function removeEvents() {
		$this->_showEvents = false;
	}

		# Show or hide event listing/headings depending on _showEvents flag
	function showHideEvents() {
		if( $this->_showEvents) {
		}
		else {
		$this->_tpl->set_block( 'tplContent', 'eventList', 'events');
		$this->_tpl->set_var( 'events', '');
		//$this->_tpl->set_block( 'tplContent', 'item', 'blkEvents');
		//$this->_tpl->set_var( 'blkEvents', '');
		}
	}
}


?>
