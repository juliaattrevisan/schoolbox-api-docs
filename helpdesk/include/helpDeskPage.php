<?php
# File gfhfgvbcvbh		: htfdtyrtyelpDeskPage.php
# Written	: Nick Doyle, 4/7/2002
# Description	: Parent class for all helpdesk pages

require_once( 'common.php');
require_once( 'module.php');
require_once( 'sql.php');

class HelpDeskPage extends Module {

	var $_tpl;
	var $_javascript;
	var $_doJavascriptInit;			# bln - do javascript init() or not

			# Set up the whole page
	function HelpDeskPage() {
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplWhole', TPL_WHOLE);
		$this->_results = true;
	}

	function setTitle( $title) {
		$this->_tpl->set_var( 'TITLE', $title);
	}

	function setContent( $content) {
		$this->_tpl->set_var( 'CONTENT', $content);
	}

		# remove the drop-down menu (for login page)
	function removeMenus() {
		$this->_tpl->set_block( 'tplWhole', 'menus', 'block_menus');
		$this->_tpl->set_var( 'block_menus', '');
		$this->_tpl->set_block( 'tplWhole', 'search', 'block_search');
		$this->_tpl->set_var( 'block_search', '');
	}

		# remove the MGS logo in the top right
	function removeLogo() {
		$this->_tpl->set_block( 'tplWhole', 'LOGO');
		$this->_tpl->set_var( 'LOGO', '');
	}


	function printPage() {
		if( $this->_results) {
			$this->_tpl->set_var( 'results_before', '');
			$this->_tpl->set_var( 'results_after', '');
		}

		if( isset( $this->_javascript)) {
			$this->_tpl->set_var( 'JAVASCRIPT', $this->_javascript);
		}
		else {
			$this->_tpl->set_var( 'JAVASCRIPT', '');
		}

		if( $this->_doJavascriptInit) {
			$this->_tpl->set_var( 'JAVASCRIPT_INITIALISE', 'true');
		}
		else {
			$this->_tpl->set_var( 'JAVASCRIPT_INITIALISE', 'false');
		}

		$this->_tpl->pparse( 'parsedWhole', 'tplWhole');
	}

	//initiates login and session
	function initLogin($username, $passwd)
	{
		session_start();
	
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$sql = SQL_auth( $username, $passwd); //ADDED $AUTHLEVEL
		$query = mysql_query( $sql);

		if( $row = mysql_fetch_array( $query)) 
		{
			$_SESSION[ 'username'] = $username;
			$_SESSION[ 'authlevel' ] = $row[ 'authlevel']; //ADDED $AUTHLEVEL (check if it's the right field)
			$_SESSION[ 'firstname'] = $row[ 'firstname'];
			$_SESSION[ 'surname'] = $row[ 'lastname'];
			$_SESSION[ 'userid'] = $row[ 'synergyid'];
			$_SESSION[ 'portal_ID'] = $row[ 'portalid'];
			$_SESSION[ 'synergy_ID'] = $row[ 'synergyid'];
			$_SESSION[ 'campus'] = $row[ 'campus'];
			$_SESSION[ 'IP'] = $_SERVER[ 'REMOTE_ADDR'];	
			
			# Set the session's campusID to that matching the helpdesk db			
			$strCampusShort = substr( $_SESSION[ 'campus'], 0, 4);
			$sql = SQL_campusID( $strCampusShort);
			$query = mysql_query( $sql);
			$row = mysql_fetch_array( $query);
			$campusID = $row[ 'campusID'];
			$_SESSION[ 'campusID'] = $campusID;

			session_write_close();
			mysql_close();
			return true;			
		}
		else 
		{
			mysql_close();
			return false;
		}
	}

	//check if session exists 
	function sessionCheck()
	{
		session_start();
		
		# If session exists, check IPs match
		if( isset( $_SESSION[ 'username'])) 
		{
			if( $_SESSION[ 'IP'] != $_SERVER[ 'REMOTE_ADDR']) 
			{
				//user has stolen this session or gone walkabout
				redirectTo(PAGE_LOGIN_FAIL);
				exit;
			}
		}
		else
		{
				//session has expired or does not exist
				redirectTo(PAGE_LOGIN_FAIL . '&page='.$_SERVER['REQUEST_URI']);
				exit;
		}
		
	}
	

	# Convenience function to check authorisation and redirect to login if no access
	# If no parameters specified, use those in session
	function authCheck($requiredAuthLevel = 1) 
	{
		//check session exists before security level checks
		$this->sessionCheck();

		if($_SESSION['authlevel'] >= $requiredAuthLevel)
		{
			return true;
		}
		else
		{
			redirectTo('noaccess.php');
			exit;
		}

	}

		# Populate a select box in the template with values from a lookup table
		# $name defines both the database table name (tbl$name) and the template block name (OPTION_$name)
		# selected is the value to have initially selected
		# Contains special cases for the brand->model filtering,
		# where javascript to build relevent arrays is built at the same time
	function populateSelect( $name, $selected) {
			# handle optional third argument, which can indicate a flag to add an 'all' option
		unset( $additionalOption);
		if( func_num_args() == 4) {
			$additionalOption = func_get_arg( 2);
			$additionalValue = func_get_arg( 3);
		}

		$doBrand = false;
		$doModel = false;
		$doLocation = false;

		if( $name == 'Model') {
			$doModel = true;
			$this->addJavascript( "
					// Construct the javascript model array
				arrModel = new Array();
			");
		}
		elseif( $name == 'Brand') {
			$doBrand = true;
			$script = "
					// Construct the javascript brand array
				arrBrand = new Array();
			";
			$this->addJavascript( $script);
		}
		elseif( $name == 'Location') {
			$doLocation = true;
			$this->addJavascript( "
					// Construct the javascript Location array
				arrLocation = new Array();
			");
		}
		
		$optionsName = 'OPTIONS_' . $name;
		$blockName = 'blk' . $name;
		$tableName = 'tbl' . $name;
	
		$sql = SQL_LookupList( $name);
		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $sql)
			or die( 'populateSelect :: Error querying database : ' . mysql_error());
	
		$this->_tpl->set_block( tplContent, $optionsName, $blockName);

		if( isset( $additionalOption)) {
			$this->_tpl->set_var( 'NAME', $additionalOption);
			$this->_tpl->set_var( 'VALUE', $additionalValue);
			$this->_tpl->set_var( 'SELECTED', '');
			$this->_tpl->parse( $blockName, $optionsName, true);
		}
	
		while( $row = mysql_fetch_array( $query)) {
			$doPopulate = true;
			if( $doModel == true) {
				$script = "
					arrTemp = new Array( '" . $row[ 'id'] . "', '" . $row[ 'description'] . "', '" . $row[ 'brandID'] . "');
					arrModel.push( arrTemp);
				";
				$this->addJavascript( $script);
				if( !isset( $selected)) {
					$doPopulate = false;
				}
			}
			elseif( $doBrand == true) {
				$script = "
					arrTemp = new Array( '" . $row[ 'id'] . "', '" . $row[ 'description'] . "');
					arrBrand.push( arrTemp);
				";
				$this->addJavascript( $script);
			}
			elseif( $doLocation == true) {
				$script = "
					arrTemp = new Array( '" . $row[ 'id'] . "', '" . $row[ 'description'] . "', '" . $row[ 'campusID'] . "');
					arrLocation.push( arrTemp);
				";
				$this->addJavascript( $script);
				if( !isset( $selected)) {
					$doPopulate = false;
				}
			}
	
			if( $doPopulate) {
				//lookup tables can have a uservisible field to remove fields that should not be displayed
				if(!isset($row['userVisible']) || $row['userVisible'])  
				{
					$this->_tpl->set_var( 'NAME', $row[ 'description']);
					$this->_tpl->set_var( 'VALUE', $row[ 'id']);
	
					if( $row[ 'id'] == $selected) {
						$this->_tpl->set_var( 'SELECTED', ' selected');
					}
					else {
						$this->_tpl->set_var( 'SELECTED', '');
					}
					$this->_tpl->parse( $blockName, $optionsName, true);
				}
			}
			else {
				$this->_tpl->set_var( 'NAME', '[please select brand first]');
				$this->_tpl->set_var( 'VALUE', 0);
			}
		}
		mysql_close();
	}



		# Populate a date select box
	function populateSelectNumberRange( $name, $lowerBound, $higherBound, $selected) {
		$optionsName = 'OPTIONS_' . $name;
		$blockName = 'blk' . $name;
	
		$this->_tpl->set_block( tplContent, $optionsName, $blockName);
	
		for( $i = $lowerBound ; $i <= $higherBound ; $i++) {
			$this->_tpl->set_var( 'NAME', $i);
			$this->_tpl->set_var( 'VALUE', $i);

			if( $i == $selected) {
				$this->_tpl->set_var( 'SELECTED', ' selected');
			}
			else {
				$this->_tpl->set_var( 'SELECTED', '');
			}
			$this->_tpl->parse( $blockName, $optionsName, true);
		}
	}


	function addJavascript( $script) {
		$this->_javascript .= $script;
	}

	function getJavascript() {
		return $this->_javascript;
	}

	function doJavascriptInit() {
		$this->_doJavascriptInit = true;
	}


	function logout() {
		session_unregister();
		session_destroy();
	}



		# Populate a select box in the template with values from teh specOption table.
		# Disseminate the worldwide funk.
	function populateSelectSpec( $specName, $selected) {
		$optionsName = 'OPTIONS_' . $specName;
		$blockName = 'blk' . $name;
		$tableName = 'tblSpecOption';
	
		$sql = SQL_LookupListSpec( $specName);

		mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		$query = mysql_query( $sql)
			or die( 'populateSelectSpec() - Error getting spec options. DB error : ' . mysql_error());
	
		$this->_tpl->set_block( tplContent, $optionsName, $blockName);
	
		while( $row = mysql_fetch_array( $query)) {
			$this->_tpl->set_var( 'NAME', $row[ 'description']);
			$this->_tpl->set_var( 'VALUE', $row[ 'id']);
	
			if( $row[ 'id'] == $selected) {
				$this->_tpl->set_var( 'SELECTED', ' selected');
			}
			else {
				$this->_tpl->set_var( 'SELECTED', '');
			}
			$this->_tpl->parse( $blockName, $optionsName, true);
		}
	}
}

?>
