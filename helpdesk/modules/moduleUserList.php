<?php
# File		: moduleUserList.php
# Written	: Nick Doyle, 11/7/2002
# Description	: module to print user listing

require_once( 'common.php');
require_once( 'sql.php');

class ModuleUserList {
	var $_tpl_name = 'userList.html';
	var $_tpl;
	var $_sql;
	var $_radioButtons;
	var $_query;

		# Constructor takes a reference to an associative array of search parameters (to avoid copying the array object)
		# OR a mssql query to use
	function ModuleUserList( &$searchParms) {
		$this->_radioButtons = false;
		$this->_noBorder = false;
		if( func_num_args() > 1) {
			if( func_get_arg(1) == 'radioButtons') {
				$this->_radioButtons = true;
			}
			elseif( func_get_arg(1) == 'or') {
				$or = 'or';
			}

			if( func_num_args() > 2) {
				if( func_get_arg(2) == 'noBorder') {
					$this->_noBorder = true;
				}
			}
		}
			# Query with which to work was passed in
		if( !is_array( $searchParms) and isset( $searchParms)) {
			$this->_query = $searchParms;
		}
		else {
			if( $or) {
				$this->_sql = SQL_userList( $searchParms, $or);
			}
			else {
				$this->_sql = SQL_userList( $searchParms);
			}
		}

		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_tpl_name);

		if( $this->_noBorder == true) {
			$this->_tpl->set_var( 'TABLE_CLASS', 'table_noborder');
		}
		else {
			$this->_tpl->set_var( 'TABLE_CLASS', 'table_user');
		}
	}

	function getContent() {
		if( !isset( $this->_query)) {
			$link = mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
			mysql_select_db( DB_NAME_PORTAL);
			$query = mysql_query( $this->_sql);
			
		}
		elseif( isset( $this->_query)) {
			$query = $this->_query;
		}
		else {
			die( 'moduleUserList::getContent() - no sql or query defined, exiting');
		}
		
		if ($query){
			if( mysql_num_rows( $query) == 0) {
				mysql_close();
				return;
			}
			if( (mysql_num_rows( $query) == 1) and ( strstr( $_SERVER[ 'PHP_SELF'], 'userList.php'))) {
				$row = stripslashes_array($row);
				if ($query != 1){
					$row = mysql_fetch_array( $query);
					mysql_close();
					redirectTo( 'userDetails.php?portalID=' . $row['portalID']);
				}
			}
		}
		else {
			mysql_close();
			return;
		}	
		if ($query != 1){
		$this->_tpl->set_block( 'tplContent', 'user_item', 'blkUser');
		while( $row = mysql_fetch_array( $query)) {
			if( $this->_radioButtons == false) {
				$a = '<a href="userDetails.php?portalID=' . $row[ 'portalID'] . '">';
				$name = $a . $row[ 'firstname'] . ' ' . $row[ 'surname'].'</a>';
			}
			else {
				$name = "<input type='radio' name='userID' value='" . $row[ 'portalID'] . "'>" . $row[ 'firstname'] . ' ' . $row[ 'surname'];
			}

			//if( $row[ 'group_id'] == 1 ) {
				//$type = 'student';  man im a gun
			//}
			//else {
				//$type = 'staff';
			//}

			$this->_tpl->set_var( 'NAME', $name);
			$this->_tpl->set_var( 'SYNERGY_ID', $row[ 'synergyID']);
			$this->_tpl->set_var( 'TYPE', $row[ 'groupname']);
			$this->_tpl->parse( 'blkUser', 'user_item', true);
		}
		}
		else {
			$this->_tpl->set_var( 'NAME', '');
			$this->_tpl->set_var( 'SYNERGY_ID', '');
			$this->_tpl->set_var( 'TYPE', '');
			$this->_tpl->set_var( 'blkUser', '');
		}
			
		mysql_close();

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}
}

?>
