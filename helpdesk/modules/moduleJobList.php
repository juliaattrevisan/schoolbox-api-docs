<?php
# File		: moduleJobList.php
# Written	: Nick Doyle, 9/7/2002
# Description	: module to print job listing

require_once( 'common.php');
require_once( 'sql.php');


class ModuleJobList {
	var $_TPL_NAME_ASSIGNED = 'jobListAssigned.html';
	var $_TPL_NAME_ASSOCIATED = 'jobListAssociated.html';
	var $_tpl;
	var $_sql;
	var $_showJobs = true;					# if no jobs, set to false

		# Constructor takes a referrence to an associative array of search parameters (to avoid copying the array object)
	function ModuleJobList( &$searchParms) {
		if( func_num_args() > 1) {
			$or = true;
		}

		//checks to see if we are displaying an associated search
		if(	$searchParms['portalID'] != '' ||
			$searchParms['equipmentPortalUserID'] != '' ||
			$searchParms['equipmentID'] != '' ||
			$searchParms['portalAssociatedUserID'] != ''||
			$searchParms['synergyID'] != '' ){
			$this->associatedDisplay = true;
		}
		else {
			$this->associatedDisplay = false;
		}

		$this->_tpl = new Module( TPL_DIR);

		if(!$this->associatedDisplay) {
			$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME_ASSIGNED);
		}
		else{
			$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME_ASSOCIATED);
			$this->showAddButton($searchParms);
		}

		if( $or) {
			$this->_sql = SQL_jobList( $searchParms, 'or');
		}
		else {
			$this->_sql = SQL_jobList( $searchParms);
		}

	}

	function getContent() {
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $this->_sql);
		print mysql_error();
		if( mysql_num_rows( $query) == 0) {
			return;
		}

		$lastid = -1;
		$this->_tpl->set_block('tplContent','jobBlock', 'jobB');
		if(!$this->associatedDisplay) {
			$this->_tpl->set_block('tplContent','userJobBlock', 'userjobB');
		}


		while( $row = mysql_fetch_array( $query)) {
			$row = stripslashes_array($row);
			$href = 'jobDetails.php?jobID=' . $row[ 'jobID'];

			if(!$this->associatedDisplay) {
				if($lastid != $row['portalAssignedUserID'] || mysql_num_rows($query) == 1)
				{
					if($lastid != -1){
						$this->_tpl->parse( 'userjobB', 'userJobBlock', true);
						$this->_tpl->set_var('jobB', '');
					}
					$this->_tpl->set_var('OWNER', $row['assignedUserName']);
				}

				if($row['equipmentUserName'])
					$this->_tpl->set_var( 'NAME', '<a href="' . $href . '">' . $row[ 'equipmentUserName'] . '</a>');
				else if($row['associatedUserName'])
					$this->_tpl->set_var( 'NAME', '<a href="' . $href . '">' . $row[ 'associatedUserName'] . '</a>');
				else if($row['userNonSynergy'])
					$this->_tpl->set_var( 'NAME', '<a href="' . $href . '">'.$row['userNonSynergy'].'</a>');
				else
					$this->_tpl->set_var( 'NAME', '<a href="' . $href . '">Unknown</a>');
			}
			else {
				if($row['assignedUserName'])
					$this->_tpl->set_var('NAME', '<a href="' . $href . '">' . $row['assignedUserName']. '</a>');
				else
					$this->_tpl->set_var('NAME', '<a href="' . $href . '">Unknown</a>');
			}

			$this->_tpl->set_var( 'DESCRIPTION', Scrub::text($row[ 'description']));
			$this->_tpl->set_var( 'CREATEDDATE', $row[ 'createdDate']);
			$this->_tpl->set_var( 'STATUS', "<div style='color: #{$row['colour']}'>".$row[ 'status'].'</div>');
			$this->_tpl->parse( 'jobB', 'jobBlock', true);
			$lastid = $row['portalAssignedUserID'];

		}

		if(!$this->associatedDisplay){
			$this->_tpl->parse( 'userjobB', 'userJobBlock',true);
		}
		mysql_close();

		return $this->_tpl->parse( 'tplContent', 'tplContent');
	}


		# The 'add job' button should only be displayed if the jobs listed are for a single user or equipment item
	function showAddButton( $parms) {

		$this->_tpl->set_var( 'EQUIPMENT_ID', $parms[ 'equipmentID']);
	}
}

?>
