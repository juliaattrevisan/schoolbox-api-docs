<?
/*
rewite of notify.php
kids cant code
*/

require_once('../../../include/include.php');
require_once('../../../include/mailer.php');

class notify
{
	function notify($detID)
	{
		$this->db = new dbManager('detention');
		$sql = "SELECT * FROM det WHERE id = ".$detID;
		$this->db->dbDoQuery($sql);
		
		
			
	
				
	
	}

}

?>