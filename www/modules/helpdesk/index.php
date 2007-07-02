<?php
// written by Robert Skillington

require_once("../../include/session.php");
require_once('../../conf/conf.php');
require_once('../../include/dbManager-mysql.php');
require_once('../../include/template.php');
//require_once("/usr/local/vhosts/helpdesk/include/common.php");	
require_once("/usr/local/vhosts/helpdesk/include/sql.php");	
define( 'DB_NAME_PORTAL', 'intranet');

class helpdesk_display{ 
	
	function helpdesk_display() {

		$this->db = new dbManager();

		$this->t_body = new Template(TPL_directory);
		
		//setup file
		$this->t_body->set_file("tplBODY", "modules/helpdesk/display.htm");

		//setup blocks
		$this->t_body->set_block("tplBODY", "job", "joblist");
		$this->t_body->set_block("tplBODY", "equip", "equiplist");
		
		$this->id_user = $_SESSION['id'];

		$this->db->dbChangedb("helpdesk");

		$this->getUser();

		$this->getJoblist();
		$this->getEquipmentlist();

		$this->display();
	}

	function getUser() {
		$query = "SELECT firstname, lastname FROM intranet.user WHERE id=".$this->id_user;
		$user = $this->db->dbGetSingleRow($query);
		$this->t_body->set_var("USER_CURR", $user['firstname']." ".$user['lastname']);
	}

	function getJoblist() {
		$params = array( "status"=>"open",
				 "equipmentPortalUserID"=>$this->id_user	
				);
		$query = SQL_jobList($params);
		$jobs = $this->db->dbGetAllRows($query);

		$params = array( "status"=>"open",
				 "portalID"=>$this->id_user	
				);
		$query = SQL_jobList($params);
		$jobs2 = $this->db->dbGetAllRows($query);
					

		//var_dump($jobs);

		if (empty($jobs)) {
			$this->t_body->set_var("joblist", NULL);
			return;
		}

		foreach ($jobs as $job) {
			$this->t_body->set_var("problem", ucfirst($job['problem']));
			$this->t_body->set_var("description", ucfirst($job['description']));
			$this->t_body->set_var("date", $job['createdDate']);
			$this->t_body->set_var("jobID", $job['jobID']);
			$this->t_body->set_var("assignedUserName", $job['assignedUserName']);
			$this->t_body->set_var("status", $job['status']);
			$this->t_body->parse("joblist", "job", true);
		}
	}

	function getEquipmentlist() {
		$params = array("portalUserID"=>$this->id_user,
				"active"=>1);
		$query = SQL_equipmentList($params, false);
		$equipment = $this->db->dbGetAllRows($query);

		foreach($equipment as $equip)
		{
			$this->t_body->set_var("MODEL", $equip['model']);
			$this->t_body->set_var("BRAND", $equip['brand']);
			$this->t_body->set_var("TYPE", $equip['equipmentType']);
			$this->t_body->set_var("LOCATION", $equip['location']);
			$this->t_body->parse("equiplist", "equip", true);
		}

	}

	function display() {
		$this->db->dbChangedb("intranet");
		require_once("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
		$this->db->dbClose();
	}

}

$page = new helpdesk_display();

?>
