<?PHP
#// File		: moduleEquipmentEdit.php
#// Written	: Nick Doyle, 25/7/2002
#// Descrition	: module to add/edit equipment \
//written++:enderana
//290305

require_once( 'common.php');
require_once( 'sql.php');

class ModuleEquipmentEdit extends HelpDeskPage {
	var $_TPL_NAME = 'equipmentEdit.html';
	var $_tpl;
	var $_equipmentID;
	var $_sql;
	var $_sqlSpec;
	var $_action;
	var $_blnAddedNew;
	var $_userField;
	var $_blnShowDeleteButton = false;
	var $_blnShowLocationSchool = false;
	var $_blnShowDate = true;

		# Optional argument of user id
	function moduleEquipmentEdit() {
		$this->_blnAddedNew = true;
		$this->_userField = true;
		unset( $this->_sql);
		if(( func_num_args() > 0) and func_get_arg(0) <> '') {
                        $this->_equipmentID = func_get_arg( 0);
			$this->_sql = SQL_EquipmentDetails( $this->_equipmentID);
			//$this->_sqlSpec = SQL_equipmentSpec( $this->_equipmentID);
		}
		$this->_tpl = new Module( TPL_DIR);
		$this->_tpl->set_file( 'tplContent', $this->_TPL_NAME);

		if( $this->_noBorder == true) {
			$this->_tpl->set_var( 'TABLE_CLASS', 'table_noborder');
		}
		else {
			$this->_tpl->set_var( 'TABLE_CLASS', 'table_user');
		}
		
		$this->link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME, $this->link);
	}



	function getContent()
	{
		//print_r($_POST);

		if( $this->_userField == false) 
		{
			$this->_tpl->set_var( 'USER_SEARCH_BEFORE', '<!-- ');
			$this->_tpl->set_var( 'USER_SEARCH_AFTER', ' -->');
		}
		else 
		{				# Print the 'user' field, and no user listing
			$this->_tpl->set_var( 'USER_SEARCH_BEFORE', '');
			$this->_tpl->set_var( 'USER_SEARCH_AFTER', '');
			$this->_tpl->set_var( 'USER_LIST', '');
		}


						# If we're editing one in the DB, get details from there
		if( isset( $this->_sql)) 
		{
			$query = mysql_query( $this->_sql, $this->link);
			$row = mysql_fetch_array( $query);
			$row = stripslashes_array($row);

			$this->_blnShowDeleteButton = true;
		}
//**********************************************************8	
			# Else set the details as in the request
			$rowSetup = array (
				'equipmentID'	=> '',
				'serial'	=> '',
				'dateOfPurchase'=> '',
				'warrantyYears'=> '',
				'modelID'	=> '',
				'brandID'	=> '',
				'equipmentTypeID'	=> '',
				'campusID'	=> '',
				'locationID'	=> '',
				'dealerID'	=> '',
				'ownerID'	=> '',
				'userID'	=> '',				
				'userNonSynergy'=> '',
				'active'	=> '',
				'insurance'	=> '',
			);
		
		
		//creating a new piece of equipment
		if($this->_action == 'create')
		{
			$rowSetup['user'] = $_REQUEST['user'];
			if($_POST['modelchange']) //if model change we display previous models information and all custom fields
			{
				$row = $this->populateRowWithModelInfo($_POST['modelID']);
				$customFields = $this->mergeCustomFields($row['equipmentID'], $row['equipmentTypeID']);
			}
			else
			{
				$customFields = $this->getBlankCustomFields($_REQUEST['equipmentTypeID']); //just dispaly standard fields
				$row['equipmentTypeID'] = $_REQUEST['equipmentTypeID'];
			}
			
			//fill out the information with stuff from the last refresh of the page
			foreach( array_keys( $rowSetup) as $key) 
			{	
				if ($_REQUEST[$key] != '' || $key == 'equipmentID')//we are creating here brothers get rid of this id field and dont overwrite our data with blanks
				{
					if($key != 'equipmentTypeID')
						$row[ $key] = $_REQUEST[ $key];
				}
			}

			# If new equipment, default location is 'user'
			if( !isset( $row[ 'locationID'])) 
			{
				$row[ 'locationID'] = DB_ID_LOCATION_USER;
			}
			
		}	
		else if($this->_action == 'update')
		{
			$rowSetup['user'] = $_REQUEST['user'];
			if($_POST['action'] == 'update')
			{
				foreach( array_keys( $rowSetup) as $key) 
				{	
					$row[$key] = $_REQUEST[$key];
				}
			}
			
			if($_POST['modelchange']) //if model change we display previous models information and all custom fields
			{
				$row_Temp = $this->populateRowWithModelInfo($_POST['modelID']);
				$row['equipmentTypeID'] = $row_Temp['equipmentTypeID'];
				$row['modelID']= $_REQUEST[ 'modelID'];
				$row['brandID']= $_REQUEST['brandID'];		
			}
			else if($_POST['typechange'])
			{
				$row['equipmentTypeID'] = $_REQUEST['equipmentTypeID'];
			}
						
			$customFields = $this->mergeCustomFields($this->_equipmentID,$row['equipmentTypeID']);	
		}
		else
		{
			print "Error: Unkown action cannot continue";
			exit;
		}

		$this->displayCustomFields($customFields); //display custom fields code

		$this->_tpl->set_var( 'EQUIPMENT_ID', $row[ 'equipmentID']);
		$this->_tpl->set_var( 'SERIAL', $row[ 'serial']);
		$this->_tpl->set_var( 'ACTION', $this->_action);

			# Active ( default yes/checked)
		if( $row[ 'active']) {
			$this->_tpl->set_var( 'ACTIVE_CHECKED', 'checked');
		}
		else {
			$this->_tpl->set_var( 'ACTIVE_CHECKED', '');
		}

		if( $row[ 'insurance']) {
			$this->_tpl->set_var( 'INSURANCE_CHECKED', 'checked');
		}
		else {
			$this->_tpl->set_var( 'INSURANCE_CHECKED', '');
		}

		if( $row[ 'dateOfPurchase'] == '' && $_REQUEST['dateNone'] == 1) {
			$this->_tpl->set_var( 'NO_DATE_CHECK', ' checked');
			$selectedDay = date( 'j');
			$selectedMonth = date( 'n');
			$selectedYear = date( 'Y');
			$this->hideDate();
		}
		else if($row['dateOfPurchase']){
			$this->_tpl->set_var( 'NO_DATE_CHECK', '');
			$dateOfPurchase = $row[ 'dateOfPurchase'];
			preg_match( '/(\d\d\d\d)-(\d+)-(\d+)/', $dateOfPurchase, $matches);
			$selectedYear = $matches[1];
			$selectedMonth = $matches[2]; 
			$selectedDay = $matches[3];
		}
		else if(!$_REQUEST['dateNone'] && $_REQUEST['dateDay']) {
			$this->_tpl->set_var( 'NO_DATE_CHECK', '');
			$selectedYear = $_REQUEST['dateYear'];
			$selectedMonth = $_REQUEST['dateMonth'];
			$selectedDay = $_REQUEST['dateDay'];
		}
		else {
			$this->_tpl->set_var( 'NO_DATE_CHECK', ' checked');
			$selectedDay = date( 'j');
			$selectedMonth = date( 'n');
			$selectedYear = date( 'Y');
		}
		print "blah=$selectedDay";
			# usage is : $this->populateSelect( table/block name (must be same), initiallySelected);
		$this->populateSelect( 'EquipmentType', $row[ 'equipmentTypeID']);
		$this->populateSelect( 'Model', $row[ 'modelID']);
		$this->populateSelect( 'Brand', $row[ 'brandID']);
		$this->populateSelect( 'Campus', $row[ 'campusID']);
		$this->populateSelect( 'Dealer', $row[ 'dealerID'], '(not specified)', '');
		$this->populateSelect( 'Owner', $row[ 'ownerID']);

		#$this->populateSelectSpec( 'OS', $row[ 'OSID'], '(select OS)', '');

			# Location
		$this->populateSelect( 'Location', $row[ 'locationID']);
		$this->populateLocationPrimary( $row[ 'locationID']);
		if( $row[ 'location'] == 'School') {
			$this->showLocationSchool();
		}
		

			# populate these selects with a number range (e.g. 1,2,3...31)
		$this->populateSelectNumberRange( 'dateDay', 1, 31, $selectedDay);
		$this->populateSelectNumberRange( 'dateMonth', 1, 12, $selectedMonth);
		$this->populateSelectNumberRange( 'dateYear', date( 'Y')-5, date( 'Y')+1, $selectedYear);
		$this->populateSelectNumberRange( 'warrantyYears', 0, 5, $row[ 'warrantyYears']);

										# If userID specified, fix it at this
		if( $row[ 'userID'] <> '') {
			$this->_tpl->set_var( 'USER', getSynergyFromPortalIfInt($row[ 'userID']));
			$this->_tpl->set_var( 'USER_NON_SYNERGY_CHECKED', '');
		}
										# If userID is blank, and  'user' specified in row (from db)
										# We're editing one in the db with a non-synergy user
		elseif( $row[ 'userNonSynergy'] <> '') {
			$this->_tpl->set_var( 'USER', $row[ 'userNonSynergy']);
			$this->_tpl->set_var( 'USER_NON_SYNERGY_CHECKED', ' checked');
		}
		else {								# If nothing specified, 
			$this->_tpl->set_var( 'USER', $row[ 'user']);
			$this->_tpl->set_var( 'USER_NON_SYNERGY_CHECKED', '');
		}

		$this->_tpl->set_var( 'EQUIPMENT_ID', $this->_equipmentID);

		$this->showHideDeleteButton();		# Hides delete button if not required
		$this->showHideDate();			# Hides date if not required
		$this->showHideLocationSchool();	# Hides extra school location selects if not required

		

		mysql_close( $this->link);

		return $this->_tpl->parse( 'tplContentParsed', 'tplContent');
	}

	function setAction( $action) {
		$this->_action = $action;
	}

	function removeUserField() {
		$this->_userField = false;
	}

	function setUserList( $userList) {
		$this->removeUserField();
		$this->_tpl->set_var( 'USER_LIST', $userList);
	}

	function hideDate() {
		$this->_blnShowDate = false;
	}

	function showHideDate() {
		if( !$this->_blnShowDate) {
			$this->_tpl->set_var( 'VISIBILITY_DATE', 'hidden');
		}
		else {
			$this->_tpl->set_var( 'VISIBILITY_DATE', 'visible');
		}
	}

		# Populate a select box in the template with values from teh location (primary)
		# e.g. School, User, Dealer
		# Clears the sharpmist!
		# Awesome function ... thanks I kludged it myself
	function populateLocationPrimary( $selected) {
		$optionsName = 'OPTIONS_LocationPrimary';
		$blockName = 'blkLocationPrimary';
		$blnFound = false;			# Whether we've found one to be selected
		$strSelected = '';
		$blnSchoolSelected = false;

		$sql = SQL_LookupListLocationPrimary();
      
		$query = mysql_query( $sql, $this->link)
			or die( 'populateSelectLocationPrimary() - Error getting location options. DB error : ' . mysql_error());

		$this->_tpl->set_block( tplContent, $optionsName, $blockName);

		$i = 0;
		$arrRows = array();
		while( $row = mysql_fetch_array( $query)) {
			if( $row[ 'id'] == $selected) {
				$blnFound = true;
				$strSelected = ' selected';
				if( $row[ 'description'] == 'School') {
					$blnSchoolSelected = true;
				}
			}
			$arrTemp = array(	'id'		=> $row[ 'id'],
						'description'	=> $row[ 'description'],
						'selected'	=> $strSelected
			);
			array_push( $arrRows, $arrTemp);
			$strSelected = '';
		}

		if( !$blnFound) {
			$blnSchoolSelected = true;
		}

		foreach( $arrRows as $row) {
			if( $this->_radioButtons == false) {
				$a = '<a href="userDetails.php?synergyID=' . $row[ 'synergyID'] . '">';
				$name = $a . $row[ 'firstname'] . ' ' . $row[ 'surname'].'</a>';
			}
			else {
				$name = "<input type='radio' name='equipmentID' value='" . $row[ 'equipmentID'] . "'>" .
					$row[ 'description'];
			}

			$this->_tpl->set_var( 'NAME', $row[ 'description']);
			$this->_tpl->set_var( 'VALUE', $row[ 'id']);

				# If found match, set that to selected
			if( $blnFound) {
				$this->_tpl->set_var( 'SELECTED', $row[ 'selected']);
			}	# If didn't find a match, but are on 'school', select that
			elseif( $row[ 'description'] == 'School') {
				$this->_tpl->set_var( 'SELECTED', ' selected');
			}

			if( $blnSchoolSelected) {
				$this->_blnShowLocationSchool = true;
			}

			$this->_tpl->parse( $blockName, $optionsName, true);
		}
	}

	function showLocationSchool() {
		$this->_blnShowLocationSchool = true;
	}


	function showHideLocationSchool() {
		if( $this->_blnShowLocationSchool) {
			$visibility = 'visible';
		}
		else {
			$visibility = 'hidden';
		}
		$this->_tpl->set_var( 'LOCATION_SCHOOL_VISIBILITY', $visibility);
	}

		# Update an equipment item
	function update( &$details) {
		$sql = SQL_equipmentUpdate( $details);
		mysql_select_db( DB_NAME ,$this->link);
		mysql_query( $sql, $this->link);


		if( mysql_error() <> '') {
			die( 'Error accessing database to update equipment : ' . print_r( $details) . '<br><br' . mysql_error());
		}
		
		if ($details['equipmentTypeID'] === '1' && $details['active'] == 1) {
			$sql = "UPDATE tblEquipment SET 
						active = NULL 
					WHERE 
						portalUserID = " . (int)$details['portalUserID'] . " AND 
						equipmentID != '" . $details['equipmentID'] . "' AND 
						equipmentTypeID = 1";
					
			mysql_query( $sql);
		
			
			if( mysql_error() <> '') {
				die( 'Error accessing database : ' . mysql_error());
			}
		} 
		
		
		
	}

		# Check whethere or not to show the delete button, take appropriate action
	function showHideDeleteButton()
	{
		if( !$this->_blnShowDeleteButton) {
			$this->_tpl->set_block( 'tplContent', 'button_delete');
			$this->_tpl->set_var( 'button_delete', '');
			
		}
	}
	
	function populateRowWithModelInfo($modelID)
	{
		//takes model information and populates the row information with last inputted records 
		
		$query = "SELECT equipmentID, dateOfPurchase, dealerID, locationID, ownerID, tblEquipment.modelID, equipmentTypeID, warrantyYears
					FROM tblEquipment, tblModel 
					WHERE tblModel.modelID = tblEquipment.modelID AND tblEquipment.modelID = ".$modelID." 
					ORDER BY equipmentID 
					LIMIT 1,1";
		
		$prevQuery =  mysql_query($query, $this->link);
		print mysql_error($this->link);
		if($row =  mysql_fetch_array( $prevQuery))
		{
			$row = stripslashes_array($row);			
			return $row;
		}
		
		return false;
		
	}	
	
	function mergeCustomFields($equipmentID, $equipmentTypeID)
	{
		//merge template blank fields with data from previous equipment model
		//allows us to pretend we know something about the model they have selected
		//recieves previous equipmentID and the type id for template 
		//returns a populate array of custom fields
		
		$sql = "SELECT TC.* FROM tblTemplateContents TC,  tblEquipment E
			WHERE TC.equipmentID = E.equipmentID AND E.equipmentID = '".$equipmentID."'";		
		//print $sql;
		$result = mysql_query($sql,$this->link);
		
		$customFields = $this->getBlankCustomFields($equipmentTypeID);

		while($customFieldData = mysql_fetch_array($result))
		{
			$customFields[$customFieldData['fieldName']] = $customFieldData['contents'];
		}
		
		return $customFields;
	}
	
	function displayCustomFields($fieldArray)
	{
		if(is_array($fieldArray))
		{
			foreach( array_keys($fieldArray) as $key) 
			{	
				$fieldname = 'ctf'.str_replace(" ", "_", $key);

				if ($_REQUEST[$fieldname] != '')//we are creating here brothers get rid of this id field and dont overwrite our data with blanks
				{
					$fieldArray[ $key] = $_REQUEST[$fieldname];
				}
			}
		}
	
		//takes array of field and values and outputs to template
	
		if(is_array($fieldArray))
		{
			$this->_tpl->set_block('tplContent', 'CUSTOM_FIELD_BLOCK' , 'custfieldB');
			
			$i = 0;
			$field = 'L';
			
			foreach($fieldArray as $fieldname => $contents)
			{
				$customf = "<input name='ctf".$fieldname."' type='text' value = '".$contents."'>";
				$this->_tpl->set_var( 'CUSTOMFIELDNAME'.$field, $fieldname);
				$this->_tpl->set_var( 'CUSTOMFIELD'.$field, $customf);
			
				if($i % 2) { //every second field we output on the right block, every second field we also parse out the line
					$field = 'L';
					$this->_tpl->parse('custfieldB', 'CUSTOM_FIELD_BLOCK', true);	
				}
				else {
					$field = 'R';			
				}
				
				$i++;
			}
			
			if($i % 2) { //if we had an odd number of fields we set the right field to blank
				$customfn  = "";
				$customf = "";
				$this->_tpl->set_var( 'CUSTOMFIELDNAMER', $customfn);
				$this->_tpl->set_var( 'CUSTOMFIELDR', $customf);
				$this->_tpl->parse('custfieldB', 'CUSTOM_FIELD_BLOCK', true);	
			}
			
		}
		else
		{
			$this->_tpl->set_block('tplContent', 'CUSTOM_FIELD_HEADER' , 'custfieldB');
			$this->_tpl->set_var('custfieldB', '');
		}	
	}
	
	
	function getBlankCustomFields($equipmentTypeID)
	{
		//create array of custom fields
		// array('fieldname' => '')
		$sql = "SELECT T.* FROM tblTemplates T, tblEquipmentType ET
			WHERE T.templateID = ET.templateID AND ET.equipmentTypeID = '". $equipmentTypeID."'";	
			
		$result = mysql_query($sql, $this->link);
		while($rowTemp = mysql_fetch_array($result))
		{
			$fieldArray[$rowTemp['fieldName']] = '';
		}
			
		return $fieldArray;
	}
	
	function updateExtraFields($extraFields){ //code abstracted to module for conformity
		$link = mysql_connect( DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db( DB_NAME);
		
		foreach($extraFields as $key => $value)
		{
			if (substr($key,0,3) == 'ctf')
			{
			$fieldname = substr(str_replace("_", " ", $key), 3);
	
			$sql = "select fieldname from tblTemplateContents where fieldname = '$fieldname' and equipmentID = '".$this->_equipmentID."'";
			$result = mysql_query($sql); 			
			if(mysql_num_rows($result) == 0)
				$sql = "insert tblTemplateContents (fieldname, contents, equipmentID) VALUES ('$fieldname', '$value', {$this->_equipmentID})";
			else		
				$sql = "update tblTemplateContents SET fieldname = '".$fieldname."', contents = '".$value."' WHERE equipmentID = '".$this->_equipmentID."' AND fieldname = '".$fieldname."'";
			mysql_query( $sql);
			//mysql_error();					
			//print $sql;
			}
		}
		mysql_close($link);
	}
	
}
?>
