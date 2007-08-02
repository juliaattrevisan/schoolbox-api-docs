<?php
# File		: moduleEquipmentList.php
# Written	: Nick Doyle, 30/7/2002
# Description	: module to print equipment listing

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEquipmentList {
	var $_tpl_name = 'equipmentList.html';
	var $_tpl_equipment;
	var $_sql;
	var $_radioButtons;

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mysql_query to use
	function ModuleEquipmentList( &$searchParms) {
		$this->_radioButtons = false;
		$this->_noBorder = false;
		$showUsers = $_REQUEST[ 'showUsers'];
		if( func_num_args() > 1) {
			if( func_get_arg(1) == 'radioButtons') {
				$this->_radioButtons = true;
			}
			if( func_get_arg(2) == 'noBorder') {
				$this->_noBorder = true;
			}
		}
                        # Query with which to work was passed in
                if( !is_array( $searchParms) and isset( $searchParms)) {
                        $this->_query = $searchParms;
                }
                else {
                        $this->_sql = SQL_equipmentList( $searchParms, $showUsers);      // from sql.php
                }

		$this->_tpl_equipment = new Module( TPL_DIR);
		$this->_tpl_equipment->set_file( 'tplContent', $this->_tpl_name);
	}

	function getContent() {
		if( !isset( $this->_query)) {    
			mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
			mysql_select_db( DB_NAME);
			$query = mysql_query( $this->_sql)
				or die( 'Error querying database for module EquipmentList::getContent() : ' . mysql_error());
		}
		elseif( isset( $this->_query)) {
			$query = $this->_query;
		}
		else {
			die( 'moduleEquipmnetList::getContent() - no sql or query defined, exiting');
		}

		if( mysql_num_rows( $query) == 0) {
			mysql_close();
			return 'No equipment matched your search criteria. Please try again.';
		}

		$this->_tpl_equipment->set_block( 'tplContent', 'item', 'blkEquipment');

		while( $row = mysql_fetch_array( $query)) {
			if( $this->_radioButtons == false) {
				$a = '<a href="equipmentDetails.php?equipmentID=' . $row[ 'equipmentID'] . '">';
				$serial = $a . $row[ 'serial'] . '</a>';
			}
			else {
				$serial = "<input type='radio' name='equipmentID' value='" . $row[ 'equipmentID'] . "'>" .
					$row[ 'serial'];
			}

			if( $row[ 'surname'] <> '') {
				$fullname = $row[ 'surname'] . ', ' . $row[ 'firstname'] . ' (' . $row[ 'userID'] . ')';
			}
			elseif( $row[ 'owner'] == 'MGS') {
				$fullname = $row[ 'owner'];
			}
			else {
				$fullname = '';
			}

			$this->_tpl_equipment->set_var( 'SERIAL', $serial);
			$this->_tpl_equipment->set_var( 'OWNER', $fullname);
			$this->_tpl_equipment->set_var( 'BRAND', $row[ 'brand']);
			$this->_tpl_equipment->set_var( 'MODEL', $row[ 'model']);
			$this->_tpl_equipment->set_var( 'LOCATION', $row[ 'location']);
			$this->_tpl_equipment->parse( 'blkEquipment', 'item', true);
		}
		mysql_close();

		return $this->_tpl_equipment->parse( 'tplContent', 'tplContent');
	}
}

?>
