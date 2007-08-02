<?php
# File		: moduleJobList.php
# Written	: Nick Doyle, 9/7/2002
# Description	: module to print job listing

require_once( 'common.php');
require_once( 'sql.php');


class ModuleDealerList {
	var $_TPL_NAME = 'dealerList.html';
	var $_tpl;
	var $_sql;
	var $_showJobs = true;					# if no jobs, set to false

		# Constructor takes a referrence to an associative array of search parameters (to avoid copying the array object)
	function ModuleDealerList( &$searchParms) {
		if( func_num_args() > 1) {
			$or = true;
		}

		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);

		$this->_sql = SQL_dealerList(&$searchParms, 'or');
	}

	function getContent() {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $this->_sql);

		if( mysql_num_rows( $query) == 0) {
			return;
		}

								# If just listing, and only one result,
								# redirect straight to details
		if( (mysql_num_rows( $query) == 1) and ( strstr( $_SERVER[ 'PHP_SELF'], 'dealerList.php'))) {
			$row = mysql_fetch_array( $query);
			mysql_close();
			redirectTo( "dealerEdit.php?dealerID={$row['dealerID']}");
		}

		$lastid = "";
		
		$this->_tpl->set_block('tplContent','dealerContactBlock', 'dealerContactB');
		$this->_tpl->set_block('tplContent','dealerBlock', 'dealerB');
		
		while( $row = mysql_fetch_array( $query)) {
			$row = stripslashes_array($row);
			if($lastid != $row['dealerID'])
			{
				$this->_tpl->parse( 'dealerB', 'dealerBlock', true);
				$this->_tpl->set_var('dealerContactB', '');
				$this->_tpl->set_var('DEALERDESC', $row['description']);
				$this->_tpl->set_var('DEALERID', $row['dealerID']);
			}
			
			$this->_tpl->set_var( 'CONTACTNAME', $row[ 'name']);					
			$this->_tpl->set_var( 'CONTACTPOS', $row[ 'pos']);
			$this->_tpl->set_var( 'CONTACTPHONE', $row[ 'phoneno']);
			$this->_tpl->set_var( 'CONTACTEMAIL', $row[ 'email']);
			
			$this->_tpl->parse( 'dealerContactB', 'dealerContactBlock', true);
			$lastid = $row['dealerID'];	
		 		
		}
	
		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}

}


?>
