<?
define("SQL_host", "localhost");
define("SQL_user", "intranet");
define("SQL_passwd", "Qu4LitY");
define("SQL_db", "intranetdev");
//This stuff to come out when this file is included in the rest of the software

// require dbManager above $this class


class lookupHandler
{
	var $lookupArray;	
	
	function lookupHandler($table = "", $id = "", $value = "")
	{	
		if($table != "" && $id != "" && $value != "")
		{
			$db = new dbManager;
			$db->dbConnect();	
			$sql = "SELECT {$id},{$value} FROM {$table}";
			$db->dbDoQuery($sql);
			while($result = $db->dbGetNextArray())
			{
				$this->lookupArray[$table][$result[$id]]=$result[$value];
			}
			$db->dbClose;
		}
	}
	
	function addLookup($table, $id, $value)
	{
		if($table != "" && $id != "" && $value != "")
		{	
			$db = new dbManager;
			$db->dbConnect();			
			$sql = "SELECT {$id},{$value} FROM {$table}";
			$db->dbDoQuery($sql);
			while($result = $db->dbGetNextArray())
			{
				$this->lookupArray[$table][strtoupper($result[$id])]=$result[$value];
			}
			$db->dbClose;
			return true;
		}
		else return false;
	}	
	
	function doLookup($table, $id)
	{
		if(isset($this->lookupArray[$table][strtoupper($id)]))
			return $this->lookupArray[$table][strtoupper($id)];
		else
			return false;
	}

	function doReverseLookup($table, $value) //this will cry when the $value is not unique, but what is a boy to do?
	{
		return array_search($value, $this->lookupArray[$table]);
	}	
}	
?>
