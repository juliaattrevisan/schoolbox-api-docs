<?php
# File		: moduleUserDetails.php
# Written	: Nick Doyle, 1/7/2002
# Description	: module to print user details (in a table)

require_once( 'common.php');
require_once( 'sql.php');

class ModuleUserDetails {
	var $_TPL_NAME = 'userDetails.html';
	var $_tpl;
	var $_portalID;

	function moduleUserDetails( $portalID) {
		$this->_portalID = $portalID;
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);
	}

	function getContent() {
		mysql_connect( DB_HOST_PORTAL, DB_USER_PORTAL, DB_PASSWD_PORTAL);
		mysql_select_db( DB_NAME_PORTAL);
		$sql = SQL_userDetails( $this->_portalID);
		$query = mysql_query( $sql);
		print mysql_error();
		if ($query){
			$row = mysql_fetch_array(  $query);
			$row = stripslashes_array ($row);	
			$name = $row[ 'firstname'] . ' ' . $row[ 'surname'];
			$synergyID = $row[ 'synergyID'];
			
			if($row['local_email'])
                        $owner = "<a href=\"mailto:".$row['username'].MAIL_DOMAIN."\">$name</a>";
			else if($row['alt_email'])
                        $owner = "<a href=\"mailto:".$row['alt_email']."\">$name</a>";
			else
                        $owner = $name;
		
			$this->_tpl->set_var( 'NAME', $owner);
			$this->_tpl->set_var( 'USERNAME', $username);
			$this->_tpl->set_var( 'SYNERGY_ID', $synergyID);
			$this->_tpl->set_var( 'GROUP', $row['group_name']);
			$this->_tpl->set_var( 'CAMPUS', $row['campus']);
			$this->_tpl->set_var( 'YEAR', $row['year']);
			$this->_tpl->set_var( 'HOUSE', $row['house']);		
		}
		mysql_close();

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}
}

?>