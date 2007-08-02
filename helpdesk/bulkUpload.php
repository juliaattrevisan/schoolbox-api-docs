<?
//define("STATIC_EQUIPMENT_FIELDS", "12"); 
//static field length now set dynamically... now thats a paradox
define("MESS_OK", "0"); 
define("MESS_ERROR", "1"); 
define("MESS_WARN", "2"); 

require_once( 'include/helpDeskPage.php');

define( 'SQL_host', 'localhost');
define( 'SQL_user', 'helpdesk');
define( 'SQL_passwd', 'n00tb00ks');
define( 'SQL_db', 'helpdesk');

require_once("include/dbManager-mysql.php");
require_once("include/lookup.php");
require_once("include/sql.php");

class bulkUploader
{
	var $db;
	var $lu;
	var $tpl;
	
	function bulkUploader()
	{
		$this->tpl = new Module(TPL_DIR);
		$this->tpl->set_file('tplContent', 'bulkUpload.html');
		$this->tpl->set_block('tplContent','messageList', 'ml');
		$this->db = new dbManager(SQL_db, 1); 
		$this->lu = new lookupHandler();
		$this->db->dbConnect();
		$this->page = new HelpDeskPage;
		$this->page->authCheck(3);
		$this->_message = array('status' => 0, 'content' => '');
		$this->messageOutput = 0;
	}
	
	function displayPage()
	{
		if(!$this->messageOutput)
			$this->tpl->set_var('ml', '');

		$content = $this->tpl->parse("finaloutput", "tplContent");
		$this->page->setContent($content);
		$this->page->setTitle("Bulk Equipment Uploader");
		$this->page->printPage();
	}
	
	function doExtraFields($row, $equipmentID)
	{
		if(is_array($row))
		{
			$this->db->dbChangeDb(SQL_db);
			foreach($row as $fieldname => $value)
			{
				$sqlTmp = "SELECT * from tblTemplateContents WHERE fieldName = '{$fieldname}' and equipmentID = '{$equipmentID}'";
				$this->db->dbDoQuery($sqlTmp);
			
				if($this->db->dbCountRows() == 0)
				{
					$sql = "INSERT INTO tblTemplateContents(equipmentID, fieldName, contents) VALUES('{$equipmentID}', 
'{$fieldname}', '{$value}')";
				}
				else
				{
					$sql = "UPDATE tblTemplateContents SET contents = '{$value}' where equipmentID = '{$equipmentID}' and 
fieldname = '{$fieldname}'";
				}
				if($_POST['takeAction'])
					$this->db->dbDoQuery($sql);
			}
		}
	}
	
	function doStaticFields ($fieldArray)
	{

		$this->_message['status'] = 0;
		$this->lu->addLookup('tblDealer', 'description', 'dealerID');
		$this->lu->addLookup('tblLocation', 'description', 'locationID');
		$this->lu->addLookup('tblModel', 'description', 'modelID');
		$this->lu->addLookup('tblOwner', 'description', 'ownerID');
		$this->lu->addLookup('tblEquipmentType', 'description', 'equipmentTypeID');
		
		if($fieldArray['userNonSynergy'] == "" && $fieldArray['userID'] != "")
		{
			$this->db->dbDoQuery("use intranet");
			$sql = SQL_userListOneTerm($fieldArray['userID']);
			unset($fieldArray['userID']);
			$this->db->dbDoQuery($sql);
			if($this->db->dbcountRows() == 1)
			{
				$tmpRow = $this->db->dbGetNextArray();
				$fieldArray['portalUserID'] = $tmpRow['portalID'];
			}
			else
			{
				$this->_message['status'] = MESS_ERROR;
				$this->_message['content'] =  $fieldArray['serial'] ." - ".$this->db->dbcountRows(). " users found for ". $fieldArray['userID'];
				return false;
			}
		}
		$this->db->dbDoQuery("use ".SQL_db);
		$lookuperror = "";
		
		if(isset($fieldArray['dealerID']))
		{
			if (!$fieldArray['dealerID'] = $this->lu->doLookup('tblDealer', $fieldArray['dealerID']))
				$lookuperror = "Dealer, ";
		}
		if(isset($fieldArray['locationID']))
		{	
			if (!$fieldArray['locationID'] = $this->lu->doLookup('tblLocation', $fieldArray['locationID']))
				$lookuperror .= "Location, ";
		}
		if(isset($fieldArray['modelID']))
		{	
			if (!$fieldArray['modelID'] = $this->lu->doLookup('tblModel', $fieldArray['modelID']))
				$lookuperror .= "Model, ";
		}
		if(isset($fieldArray['ownerID']))
		{	
			if (!$fieldArray['ownerID'] = $this->lu->doLookup('tblOwner', $fieldArray['ownerID']))
				$lookuperror .= "Owner, ";
		}
		if(isset($fieldArray['equipmentTypeID']))
		{	
			if (!$fieldArray['equipmentTypeID'] = $this->lu->doLookup('tblEquipmentType', $fieldArray['equipmentTypeID']))
				$lookuperror .= "Equipment Type, ";
		}
		if($lookuperror != '')
		{	
			$this->_message['status'] = MESS_WARN;
			$lookuperror .= "not found"; 
		}
			
		$sql = "SELECT equipmentID from tblEquipment WHERE serial = '".$fieldArray['serial']."'";
		$this->db->dbDoQuery($sql);
		$serialMatches = $this->db->dbcountRows();
		if($serialMatches == 0)
		{
			$keys = '';
			$values = '';
			foreach($fieldArray as $key => $value)
			{
				$keys .= $key.', ';
				$values .= " '".$value."', ";
			}
			$keys = substr($keys, 0, -2);
			$values = substr($values, 0, -2);
			$sql = "INSERT INTO tblEquipment($keys) VALUES ($values)";
			//print $sql;

			$equipmentID = mysql_insert_id();
			$action = "Inserting";
		}
		else if($serialMatches == 1)//update
		{
			$row = $this->db->dbGetNextArray();
			$changes = '';
			
			foreach($fieldArray as $key => $value)
			{
				$changes .= $key." = '".$value."', ";
			}
			$changes = substr($changes, 0, -2);
			$sql =  "UPDATE tblEquipment SET $changes WHERE equipmentID = {$row['equipmentID']}";
			//print $sql;
			
			$equipmentID = $row['equipmentID'];
			$action = "Updating";
		}
		else
		{
                        $this->_message['status'] = MESS_ERROR;
                        $this->_message['content'] = $fieldArray['serial']. " - Serial exists more than once";
                        return false;
		}
		
		if($_POST['takeAction'])
		{
			if (!$this->db->dbDoQuery($sql))
			{	
				$this->_message['status'] = MESS_ERROR;
				$this->_message['content'] = $fieldArray['serial']. " - $action Failed";
				return false; 
			}
			else
			{
				$this->_message['content'] = $fieldArray['serial']. " - $action Completed ". $lookuperror;
				return $equipmentID;
			}
		}
		else
		{
			$this->_message['content'] = $fieldArray['serial']. " - $action Completed ". $lookuperror;
			return $equipmentID;
		}

	}
	
	function readFile()
	{
		$fin = fopen($_FILES['file']['tmp_name'], 'r');
		$fieldlist = $_POST['select'];
		$headerRow = split(',', fgets($fin, 4096)); //header row, important...
		//array_map('trim', $headerRow);
		while (!feof($fin)) // while not at end
		{// begin row
			$tmpRow = split(',', fgets($fin, 4096)); //get a row, moving the pointer foward
			$tmpRow = array_map('trim', $tmpRow);	
			if($tmpRow[0]) //ensure we have a serial
			{

				foreach($headerRow as $index => $fieldname) //loop though header fields(field names)
				{// begin field
					$fieldname = trim($fieldname, "\x7f..\xff\x0..\x1f");
					if($index < count($fieldlist))
					{
						$rowStatic[$fieldlist[$index]] = trim($tmpRow[$index]); 
						//better hope your fields are in the same order as the select!!
					}
					else if($_POST['extrafields'])
					{
						$rowExtra[$fieldname] = trim($tmpRow[$index]);
					}	
				}// end field
				if($equipmentID = $this->doStaticFields($rowStatic));
					$this->doExtraFields($rowExtra, $equipmentID);
		
				$this->addMessage($this->_message['content'], $this->_message['status']);
			}		
		}//end row
		//print_r($_POST);
		fclose($fin);
		$this->displayPage();
	}
	
	function addMessage($message, $status = 0)
	{
		$this->messageOutput=1;
		switch($status)
		{
			case 0:
				$colour = "black";
			break;
			case 1:
				$colour = "red";
			break;
			case 2:
				$colour = "orange";
			break;
		}
		
		$this->tpl->set_var('MESSAGE', "<div style='color:$colour'>".$message."</div>");
		$this->tpl->parse('ml', 'messageList', true);
	}
	
}//end class

$page = new bulkUploader();

if ($_FILES['file']['tmp_name'])
{
	$page->readFile();
}
else
{
	$page->displayPage();
}


?>
