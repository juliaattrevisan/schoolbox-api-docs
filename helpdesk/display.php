<?php
# File		: display.php
# Written	: Jo Treweek, 11/7/2003
# Description	: displays info for purpose of helpdesk display screen 


require_once("include/common.php");
require_once("include/dbManager-mysql.php");
require_once("include/template.php");


class helpdesk_display{ 
	
	function helpdesk_display($campus) {

		$this->t_body = new Template(TPL_DIR);
		//setup file
		$this->t_body->set_file('tplBODY', 'display.htm');

		//setup blocks
		$this->t_body->set_block("tplBODY", "nb_outs", "nb_outs_list");
		$this->t_body->set_block("tplBODY", "other_outs", "other_outs_list");
		$this->t_body->set_block("tplBODY", "headlines", "news_list");
		$this->t_body->set_block("tplBODY", "nb_ready", "nb_ready_list");
		$this->campus = $campus;
		$this->t_body->set_var("CAMPID", $campus);

		$this->mySQLdb_help = new dbManager();
	 	$this->mySQLdb_help->dbConnect();	
		$this->getOtherOutstanding($this->campus);
		$this->getNbOutstanding($this->campus);
		$this->mySQLdb_help->dbClose();

		$this->mySQLdb_port = new dbManager("intranet");
		$this->mySQLdb_port->dbConnect();
		$this->getNews();
		$this->mySQLdb_port->dbClose();
		$this->mySQLdb_help = new dbManager();
	 	$this->mySQLdb_help->dbConnect();	
		$this->getNbReady($this->campus);
		$this->mySQLdb_help->dbClose();
	
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	
	function getOtherOutstanding($campus){

		$query = "select	j.jobID
			  from		tblJob j
			  left join	tblEquipment e 
			  on 		e.equipmentID = j.equipmentID
			  where 	j.statusID <> 3		
			  and		j.campusID = ".$campus."
			  and		e.equipmentTypeID <> 1
			  and  		(latestEvent <> 16
			  or 		latestEvent <> 13)
			  order by 	createdDate desc
			  limit		0,5
		";
		$this->mySQLdb_help->dbDoQuery($query);
		if ($this->mySQLdb_help->dbCountRows() == 0)
			$this->t_body->set_var("other_outs_list", "<tr><td class=\"td_data\">No jobs outstanding</td></tr>");
		else {
			while ($id = $this->mySQLdb_help->dbGetNextArray()){
				$idarray[] = $id['jobID'];
			}
			foreach($idarray as $id){
				if ($id){
					$detailsArray[] = $this->getJobDetails("other_outs", $id);
				}
			}
		}
		$this->t_body->set_block("tplBODY", "other_outstanding", "otherout");
	}

	function getNbOutstanding($campus){

		$query = "select	jobID
			  from		tblJob j
			  left join	tblEquipment e 
			  on		e.equipmentID = j.equipmentID
			  where		statusID <> 3	
			  and 		j.campusID = ".$campus."
			  and		e.equipmentTypeID = 1
			  and		(latestEvent <> 16 
			  or		latestEvent <> 13)
			  order by 	createdDate desc
			  limit		0,8
		";

		$this->mySQLdb_help->dbDoQuery($query);
		if ($this->mySQLdb_help->dbCountRows() == 0)
			$this->t_body->set_var("nb_outs_list", "<tr><td class=\"td_data\">No jobs outstanding</td></tr>");
		else {
			while ($id = $this->mySQLdb_help->dbGetNextArray()){
				$idarray[] = $id['jobID'];
			}
			foreach($idarray as $id){
				if ($id){
					$detailsArray[] = $this->getJobDetails("nb_outs", $id);
				}
			}
		}
	}

	function getNbReady($campus){
	
		$query = "select	j.jobID
			 from		tblJob j
			 left join	tblEquipment e 
			 on 		e.equipmentID = j.equipmentID
			 where 		j.statusID <> 3		
			 and		j.campusID = ".$campus."
			 and		e.equipmentTypeID = 1
			 and  		(latestEvent = 16
			 or 		latestEvent = 13)
			 order by	createdDate desc
			";
		
		$this->mySQLdb_help->dbDoQuery($query);
		if ($this->mySQLdb_help->dbCountRows() == 0)
			$this->t_body->set_var("nb_ready_list", "<tr><td class=\"td_data\">No jobs ready</td></tr>");
	 	else {
			while ($id = $this->mySQLdb_help->dbGetNextArray()){
				$idarray[] = $id['jobID'];
			}
			foreach($idarray as $id){
				if ($id){
					$detailsArray[] = $this->getJobDetails("nb_ready",$id);
				}
			}
		}
		$this->t_body->set_block("tplBODY", "nb_ready", "nbready");
	}

	function getNews(){
	
		$query = "select	id,
					heading,
					datetime_posted,
					body,	
					code,
					owner
			from 		subject_news
 			where		code = '".NEWS_PAGE."' 
			and		deleted = 0	
			and		datetime_expires > now()
			limit	 	0,2
			";	
		$this->mySQLdb_port->dbDoQuery($query);
		if ($this->mySQLdb_port->dbCountRows() == 0)	
			$this->t_body->set_var("news_list", "<tr><td class=\"td_data\">No news is good news</td></tr>");
		else {
			while($item = $this->mySQLdb_port->dbGetNextArray()){
				$item['heading'] = stripslashes($item['heading']);
				$item['body'] = stripslashes($item['body']);
				$this->t_body->set_var("HEADING", $item['heading']);
				$this->t_body->set_var("BODY", $item['body']);
				$this->t_body->parse("news_list", "headlines", true);
			}
		}
	}

	function getJobDetails($block, $id){
		
		$query = "select	j.jobID,
					j.equipmentID, 
					j.description, 
					j.problemID, 
					c.description as campus,
					u.firstname as userFirst,
					u.lastname as userLast,
					o.firstname as ownerFirst,
					o.lastname as ownerLast,
					p.description as problem,
					date_format(je.createdDate,'%D %b') as eventDate,
					ua.firstname as assignedUser
		 	 from		tblJob j
			 left join	tblCampus c on j.campusID = c.campusID
		 	 left join	tblEquipment e on e.equipmentID = j.equipmentID
		 	 left join	tblProblem p on p.problemID = j.problemID
		 	 left join	intranet.user o on j.associatedUserID = o.synergy_id
			 left join	intranet.user u on u.synergy_id = e.userID
		 	 left join	intranet.user ua on j.assignedUserID = ua.synergy_id
			 left join	tblJobEvent je on je.jobID = j.jobID and je.eventTypeID = j.latestEvent 
		 	 where 		j.jobID = '".$id."'
		 	 order by	je.createdDate desc
		";
		$this->mySQLdb_help->dbDoQuery($query);
		//only get latest event created date and details not previous, so no loop 
		$details = $this->mySQLdb_help->dbGetNextArray();
		$details['description'] = stripslashes($details['description']);
		$details['campus'] = stripslashes($details['campus']);
		$details['userFirst'] = stripslashes($details['userFirst']);
		$details['userLast'] = stripslashes($details['userLast']);
		$details['ownerFirst'] = stripslashes($details['ownerFirst']);
		$details['ownerLast'] = stripslashes($details['userLast']);
		$details['problem'] = stripslashes($details['problem']);
		$details['assignedUser'] = stripslashes($details['assignedUser']);

		$this->t_body->set_var("DESCRIPTION", $details['description']);
		if ($details['userFirst'])
			$name = $details['userFirst']." ".$details['userLast'];
		else
			$name = $details['ownerFirst']." ".$details['ownerLast'];
		$this->t_body->set_var("USER", $name);
		$this->t_body->set_var("ASSIGNED", $details['assignedUser']);
		$this->t_body->parse($block."_list", $block, true);
		$this->t_body->set_var("CAMPUS", $details['campus']);
		$this->t_body->set_var("DATE", $details['eventDate']);
		return $details;
	}

}

if($_GET['campus'])
	$campus = $_GET['campus'];
else
	$campus = '1';

$page = new helpdesk_display($campus);

?>
