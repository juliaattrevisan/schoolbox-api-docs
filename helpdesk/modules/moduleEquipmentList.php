<?php
# File		: moduleEquipmentList.php
# Written	: Nick Doyle, 30/7/2002
# Description	: module to print equipment listing

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEquipmentList {
	var $_tpl_name = 'equipmentList.html';
	var $_tpl;
	var $_sql;
	var $_radioButtons;

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mysql_query to use
	function ModuleEquipmentList( &$searchParms) {
		$this->_radioButtons = false;
		$this->_noBorder = false;
		
		$showUsers = 1;
		if( func_num_args() > 1) {
			if( func_get_arg(1) == 'radioButtons') {
				$this->_radioButtons = true;
			}
			elseif( func_get_arg(1) == 'or') {
				$or = true;
			}

			if( func_num_args() > 2) {
				if( func_get_arg(2) == 'noBorder') {
					$this->_table_class = '';
				}
			}
		}
                        # Query with which to work was passed in
                if( !is_array( $searchParms) and isset( $searchParms)) {
                        $this->_query = $searchParms;
                }
                else {
			if( $or) {
                        	$this->_sql = SQL_equipmentList( $searchParms, $showUsers, $or);
			}
			else {
                        	$this->_sql = SQL_equipmentList( $searchParms, $showUsers);
			}
                }

		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);
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
			return;
		}

		if( (mysql_num_rows( $query) == 1) and ( strstr( $_SERVER[ 'PHP_SELF'], 'equipmentList.php'))) {
			$row = mysql_fetch_array( $query);
			$equipmentID = $row[ 'equipmentID'];
			mysql_close();
			redirectTo( 'equipmentDetails.php?equipmentID=' . $equipmentID);
		}
		
		$this->_tpl->set_block( 'tplContent', 'item', 'blkEquipment');

		while( $row = mysql_fetch_array( $query)) {
			$row = stripslashes_array($row);

			
			if($row['serial'] == "")
				$serial = '<a href="equipmentDetails.php?equipmentID=' . $row[ 'equipmentID'] . '">Unknown</a>';
			else
				$serial = '<a href="equipmentDetails.php?equipmentID=' . $row[ 'equipmentID'] . '">' . $row[ 'serial'] . '</a>';
			
			if( $row[ 'surname'] <> '') {
				$fullname = $row[ 'firstname'] . ' ' . $row[ 'surname'];
			}
			elseif( $row[ 'owner'] == 'MGS') {
				$fullname = $row[ 'owner'];
			}
			else {
				$fullname = '';
			}

			if($this->_radioButtons){
				$fullname = "<input type='radio' name='equipmentID' value='" . $row[ 'equipmentID'] . "'>" . $fullname;
			}

			
			if($row['active'])
				$this->_tpl->set_var( 'ACTIVE', '');
			else
				$this->_tpl->set_var( 'ACTIVE', 'class="disabled"');

			$this->_tpl->set_var( 'SERIAL', $serial);
			$this->_tpl->set_var( 'OWNER', $fullname);
			$this->_tpl->set_var( 'BRAND', "<a href='equipmentList.php?brandID=".$row['brandID']."'>".$row['brand']."</a>");
			$this->_tpl->set_var( 'MODEL', "<a href='equipmentList.php?modelID=".$row['modelID']."'>".$row['model']."</a>");
			$this->_tpl->set_var( 'LOCATION', "<a href='equipmentList.php?locationID=".$row['locationID']."'>".$row['location']."</a>");
			$this->_tpl->parse( 'blkEquipment', 'item', true);
		
		}
		
		mysql_close();

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}
}

?>
