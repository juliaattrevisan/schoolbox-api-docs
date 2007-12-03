<?php

require_once("../../include/session.php");
require_once('../../conf/conf.php');
require_once('../../include/dbManager-mysql.php');
require_once('../../include/template.php');
require_once('../../include/permission.php');	
require_once(HELPDESK_location.'/include/sql.php');
	
define( 'DB_NAME_PORTAL', SQL_db);

class helpdesk_display{ 
	
	function helpdesk_display($userid) {

		$this->db = new dbManager(SQL_db, 1);

		$this->t_body = new Template(TPL_directory);
		
		//setup file
		$this->t_body->set_file("tplBODY", "modules/helpdesk/display.htm");
		
		$this->t_body->set_var("TITLE", HEADING." - Helpdesk");
		
		//setup blocks
		$this->t_body->set_block("tplBODY", "user", "userlist");
		$this->t_body->set_block("user", "job", "joblist");
		$this->t_body->set_block("user", "equip", "equiplist");
		
		
		if(is_numeric($userid) && $userid > 0) {
			if(isStaff($_SESSION['gid']) || isParentOfChild($userid)) {
				$this->getUser($userid);
				$this->getJoblist($userid);
				$this->getEquipmentlist($userid);
				$this->t_body->parse('userlist', 'user');
			}
			else
				error(19);
		}
		else if(isParent($_SESSION['gid'])) {
			if(is_array($_SESSION['children'])){
				foreach($_SESSION['children'] as $child) {
					$this->getUser($child['id']);
					$this->getJoblist($child['id']);
					$this->getEquipmentlist($child['id']);
					$this->t_body->parse('userlist', 'user', true);
				}
			}
		}
		else {
			$this->getUser($_SESSION['id']);
			$this->getJoblist($_SESSION['id']);
			$this->getEquipmentlist($_SESSION['id']);
			$this->t_body->parse('userlist', 'user');
		}
		
		$this->display();
	}

	function getUser($userid) {
		$query = "SELECT firstname, lastname FROM user WHERE id=".$userid;
		$user = $this->db->dbGetSingleRow($query);
		$this->t_body->set_var("USER_CURR", $user['firstname']." ".$user['lastname']);
	}

	function getJoblist($userid) {
	
		$parms = array( 'portalID'   			=> $userid,
						'status'	 			=> 'all',
						'equipmentPortalUserID' => $userid,
					);
		$query = SQL_jobList( $parms, 'or');

		$this->db->dbChangedb(HELPDESK_db);
		$jobs = $this->db->dbGetAllRows($query);

		if(is_array($jobs)){
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
		else
			$this->t_body->set_var('joblist', '');
	}

	function getEquipmentlist($userid) {
		$params = array('portalUserID'	=> $userid,
						'active'		=> 1 
						);
		$this->db->dbChangedb(HELPDESK_db);
		$query = SQL_equipmentList($params, false);
		$equipment = $this->db->dbGetAllRows($query);
		
		if(is_array($equipment)){
			foreach($equipment as $equip)
			{
				$this->t_body->set_var("MODEL", $equip['model']);
				$this->t_body->set_var("BRAND", $equip['brand']);
				$this->t_body->set_var("TYPE", $equip['equipmentType']);
				$this->t_body->set_var("LOCATION", $equip['location']);
				$this->t_body->parse("equiplist", "equip", true);
			}
		}
		else 
			$this->t_body->set_var('equiplist', '');
	}

	function display() {
		$this->db->dbChangedb(SQL_db);
		require_once("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
		$this->db->dbClose();
	}

}

$page = new helpdesk_display($_GET['id']);

?>
