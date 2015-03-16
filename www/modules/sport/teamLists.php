<?php

//showTeamLists object...gets all info required to populate template fields in teamLists.htm

require_once("../../include/include.php");
require_once("../../include/session.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("../../include/html-parse.php");
require_once("sql/modsport.php");

class initTeamLists
{
	function initTeamLists($flag = "", $id = "")
	{
                $this->flag = $flag;
                $this->id = $id;
                switch ($this->flag){
                        case 1:
				//display search for student
				$this->initDisplay();
                                $this->displayStudSearch($this->id, $_GET['name'], $_GET['othername'], $_GET['future']);
				break;
			case 2:
				$tid = $_REQUEST['team'];
                                $mySport = new sport;
                                //add student to team
                                if ($_POST['synids'] && isset($_POST['synids'])){
					$synids = $_POST['synids'];
                                         //add each student to team query id based on synid
                                        foreach ($synids as $synid){
                                                $mySport->addStudToTeam($tid, $synid);
                                        }
                                }
                                else if ($_GET['studid'] && isset($_GET['studid'])){
                                        $mySport->addStudToTeam($tid, $_GET['studid']);
                                }
                                else
                                        print "not adding or nothing chosen";
				$this->initDisplay();
                                $this->displayTeamLists($this->id);
				break;
			case 3:
				//delete student from team
                                $mySport = new sport;
                                $result = $mySport->deleteStudFromTeam($_GET['tid'], $_GET['studid']);
				$this->initDisplay();
                                $this->displayTeamLists($this->id);
				break;
			default:
 				//display all team lists for this sport
				$this->initDisplay();
                                if ($_GET['display'])
                                        $this->displayTeamLists($this->id, $_GET['display']);
                                else
                                        $this->displayTeamLists($this->id);
				break;

		}
	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);
		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/teamLists.htm");
	 	//output page
		include_once("../../include/page.php");

		//setup database
		$this->db = new DeprecatedDatabase;
		$this->db->dbConnect();

		//setup blocks
		$this->t_body->set_block("tplBODY", "syn_list", "syn_listing");
		$this->t_body->set_block("syn_list", "synstud_list", "synstud_listing");
		$this->t_body->set_block("tplBODY", "team_list", "team_listing");
		$this->t_body->set_block("team_list", "team_header", "team_heading");
		$this->t_body->set_block("team_list", "stud_list", "stud_listing");
		$this->t_body->set_block("tplBODY", "search", "search_sect");
		$this->t_body->set_var("TITLE", "Sport Team List Adminitration");
	}

	function displayTeamLists($sid, $display = 0)
	{
		$mySport = new sport();
		$sport = $mySport->getSports($sid);
#		if (!$display){
		if (!$display && perm(1,3) > 0){
			$this->t_body->set_var("NAV",
				"<a href=\"sport.php?dest=1\"><b>Admin Menu</b></a>&nbsp;&nbsp;
				<a href=\"teamLists.php?flag=1&name=".$sport[0]['name']."&id=".$sid."\">
				<b>Add Students</b></a>&nbsp;&nbsp;
			");
		}
		else if ($display){
			$this->t_body->set_var("NAV", "
					<form>
                        		<input type=\"button\" name=\"print\" value=\"Print Teams\"
						class=\"formbutton\" onClick=\"javascript:window.print()\">
                        		<input type=\"button\" name=\"back\" value=\"Back to Menu\"
						class=\"formbutton\" onClick=\"history.go(-1)\">
                        		</form>
			");
		}
		else {
			error(19);
		}
		$this->t_body->set_var("DISPLAY", "Team Lists for ".$sport[0]['name']);
		$this->t_body->set_var("SID", $sid);
		$teaminfo = $mySport->getTeams($sid);

		if ($teaminfo){
		  $append = 0;
		  foreach ($teaminfo as $team){
			$studids = array();
			$studids = $mySport->getStudTeamList($team['tid']);
			$this->t_body->set_var("SUBHEADING", $team['section']);
			//setup idlist before query rather than query individually
			$values = "";
			if ($studids){
				foreach ($studids as $studid){
					$values.= "'".$studid['studid']."',";
				}
			}
			if ($values){
				//trim off trailing comma
		 		$values = substr($values, 0, strlen($values) - 1);
				$query = "SELECT * FROM user
					  WHERE synergy_id IN (".$values.")
					  ORDER BY lastname, firstname, year
				";
				$this->db->dbDoQuery($query);
				if ($this->db->dbCountRows() > 0){
					$appending = 0;
					while ($studinfo = $this->db->dbGetNextArray()){
						$this->t_body->set_var("LASTNAME", $studinfo['lastname']);
						$this->t_body->set_var("FIRSTNAME", $studinfo['firstname']);
						$this->t_body->set_var("YEAR", $studinfo['year']);
						$this->t_body->set_var("EMAIL", "<a href=\"mailto:".$studinfo['username']."@".MAIL_address."\">".$studinfo['username']."@".MAIL_address."</a>");
						$this->t_body->set_var("STUDID", $studinfo['synergy_id']);
						$this->t_body->set_var("TID", $team['tid']);
						if (!$display){
						#if (!$display && perm(1,3) > 0 ){
							$this->t_body->set_var("DELETE", "<a href=\"/sport/teamLists.php?flag=3&id=".$sid."&studid=".$studinfo['synergy_id']."&tid=".$team['tid']."\">Delete</a>");
						}
						else
							$this->t_body->set_var("DELETE", "");
						$this->t_body->parse("stud_listing", "stud_list", $appending);
						$appending++;
					}
				}
				else{
					$this->t_body->set_var("stud_listing", "<tr><td>studids in list don't correspond with user table ids</td></tr>");
				}
				unset($values);
			}
			else {
				$this->t_body->set_var("stud_listing", "<tr><td>no students in list</td></tr>");
			}
			$this->t_body->parse("team_heading", "team_header");
		  	$this->t_body->parse("team_listing", "team_list", $append);
			$append++;
			unset($studids);
		  }
		}
		else{
			$this->t_body->set_var("team_listing", "<tr><td><font color=\"#FF0000\">No teams for this sport</font></td></tr>");
		}
		$this->t_body->set_var("syn_listing", "");
		$this->t_body->set_var("search_sect", "");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayStudSearch($sid, $name, $other = "", $future = "")
	{
		$db = mssql_connect(Cfg::get('external_db_host'), Cfg::get('external_db_user'), Cfg::get('external_db_passwd'));
		//set year level based on campus, if future var is set, grimwade uses year 4 and 5
		//whereas senior school adn waddy will use following file year
		if ($_SESSION['campus'][0] == 1)
			$studentyear = "IN ('9', '10', '11', '12')";
		else if ($_SESSION['campus'][0] == 2)
			$studentyear = "IN ('7', '8')";
		else if ($_SESSION['campus'][0] == 3)
			$studentyear = "IN ('5', '6')";

		$externalDb = new External_User();
		$baseYear   = $externalDb->getCurrentYear();
		if ($future)
			$fileyr = $baseYear + 1;
		else
			$fileyr = $baseYear;
		$this->t_body->set_var("FILEYR", $fileyr);

		//setup teams list
		$mySport = new sport;
		$teaminfo = $mySport->getTeams($sid);
		if ($teaminfo){
		foreach ($teaminfo as $teams){
			$tname = $teams['section'];
			$teamarray[$tname] = $teams['tid'];
		}
		$html_teamarr = html_select("team", $teamarray, "");
		$this->t_body->set_var("TEAMS", $html_teamarr);
		}
		//setup students list
		if ($other == ""){
			if ($_SESSION['campus'][0] != 3){
			//if list in synergy exists for this sport, display students to choose from as well as search function
			$query = "
				SELECT 	DISTINCT (vStudents.StudentID) as synid,
					vStudents.StudentSurname as lname,
					vStudents.StudentGiven1 as fname,
					vStudents.StudentYearLevel as year,
					vStudentClasses.ClassDescription
				FROM 	SynergyOne.dbo.vStudentClasses vStudentClasses,
					SynergyOne.dbo.vStudents vStudents
				WHERE 	vStudentClasses.StudentID = vStudents.StudentID
				AND 	vStudentClasses.FileYear = vStudents.FileYear
				AND 	(vStudentClasses.FileYear = $fileyr)
				AND 	(vStudentClasses.FileType In ('R','S','W'))
				AND 	(vStudents.StudentYearLevel ".$studentyear.")
				AND 	(vStudentClasses.ClassDescription like '".$name."')
			   	ORDER BY vStudents.StudentYearLevel, vStudents.StudentSurname, vStudents.StudentGiven1
			";
			}
			else {
			//for grimwade just get all grade 5's and 6's
			$query = "
				SELECT DISTINCT (vStudents.StudentID) as synid,
					vStudents.StudentSurname as lname,
					vStudents.StudentGiven1 as fname,
					vStudents.StudentYearLevel as year
				FROM	SynergyOne.dbo.vStudents vStudents
				WHERE	(vStudents.StudentYearLevel ".$studentyear.")
				AND	(vStudents.FileYear = $fileyr)
				ORDER BY vStudents.StudentYearLevel, vStudents.StudentSurname, vStudents.StudentGiven1
			";
			}
                	$result = mssql_query($query, $db);
			if ($result != 1){
				if (mssql_num_rows($result) > 0){
					//setup list of students
                			while ($list = mssql_fetch_array($result)){
						$this->t_body->set_var("LASTNAME", $list['lname']);
						$this->t_body->set_var("FIRSTNAME", $list['fname']);
						$this->t_body->set_var("YEAR", $list['year']);
						$this->t_body->set_var("SYNID", $list['synid']);
						$this->t_body->parse("synstud_listing", "synstud_list", true);
					}
					$this->t_body->set_var("SUBHEADING", "Synergy Listing");
					$this->t_body->parse("syn_listing", "syn_list", true);
					$this->t_body->set_var("search_sect", "");
				}
				else {
					$this->t_body->set_var("syn_listing", "");
				}
			}
			else {
				$this->t_body->set_var("syn_listing", "");
			}
		}
		//search already done, list students matching search name
		else {
			$query = "SELECT * FROM user WHERE group_id IN ('3','4','5') AND (";
			$input =  explode(' ', $other);
			foreach($input as $token)
			{
        			$query .= "firstname like '$token%' OR lastname like '$token%' OR ";
			}
			$query = substr($query, 0, strlen($query) - 3);
			$query.= ")";
			$this->db->dbDoQuery($query);
			if ($this->db->dbCountRows() > 0){
				//setup list of students
                		while ($list = $this->db->dbGetNextArray()){
					$this->t_body->set_var("LASTNAME", $list['lastname']);
					$this->t_body->set_var("FIRSTNAME", $list['firstname']);
					$this->t_body->set_var("YEAR", $list['year']);
					$this->t_body->set_var("SYNID", $list['synergy_id']);
					$this->t_body->parse("synstud_listing", "synstud_list", true);
				}
				$this->t_body->set_var("SUBHEADING", "Synergy Listing");
				$this->t_body->parse("syn_listing", "syn_list", true);
				$this->t_body->set_var("search_sect", "");
			}
			else {
				$this->t_body->set_var("syn_listing", "");
			}
		}
		if ($_SESSION['campus'][0] == 1)
			$years ="<option value=\"\">&nbsp;</option><option value=\"9\">9</option><option value=\"10\">10</option><option value=\"11\">11</option><option value=\"12\">12</option>";
		else if ($_SESSION['campus'][0] == 2)
			$years ="<option value=\"\">&nbsp;</option><option value=\"7\">7</option><option value=\"8\">8</option>";
		else
			$years ="<option value=\"\">&nbsp;</option><option value=\"Prep\">Prep</option><option value=\"1\">1</option><option value=\"2\">2</option><option value=\"3\">3</option><option value=\"4\">4</option><option value=\"5\">5</option><option value=\"6\">6</option>";
		$nextyr = $baseYear + 1;
		$this->t_body->set_var("YEAR", $years);
		$this->t_body->set_var("SNAME", $name);
		$this->t_body->parse("search_sect", "search", true);
		$this->t_body->set_var("DISPLAY", "Add Students");
		$this->t_body->set_var("SID", $sid);
		$this->t_body->set_var("NAV", "<a href=\"teamLists.php?flag=1&future=1&id=$sid&name=$name\"><b>Display $nextyr students?</b></a>&nbsp;&nbsp;&nbsp;<a href=\"/modules/sport/teamLists.php?id=".$sid."\"><b>Back</b></a>");
		$this->t_body->set_var("team_listing", "");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}


	function modStudTeams($tid, $studid)
	{
		if (perm(1,3) > 0){
		 $mySport = new sport;
		 if (!is_array($studid)){
			//add student to team based on id
			$mySport->addStudToTeam($tid, $studid);
		 }
		 else {
			//add each student to team query id based on synid
                        foreach ($studid as $synid){
                        	$syns.= "'$synid',";
                        }
                        if ($syns){
       	                	//trim off trailing comma
                        	$syns = substr($syns, 0, strlen($syns) - 1);
				$query = "SELECT id FROM user WHERE synergy_id in ($syns)";
				$this->db->dbDoQuery($query);
				if ($this->db->dbCountRows() > 0){
					while ($stud = $this->db->dbGetNextArray()){
						$mySport->addStudToTeam($tid, $stud['id']);
					}
				}
                        }
	 	 }
		}
		else
			error(19);
	}
	function getStudIds($syns){
		$query = "SELECT id FROM user WHERE synergy_id in ($syns)";
		$this->db->dbDoQuery($query);
		if ($this->db->dbCountRows() > 0){
			while ($stud = $this->db->dbGetNextArray){
				$students[] = $stud;
			}
			return $students;
		}
		else
			return 0;
	}

	function array_column_sort($array, $column)
	{
		foreach ($array as $row)
			$sortarr[] = $row[$column];
		array_multisort($sortarr, $array);
		return $array;
	}
}

$page = new initTeamLists($_REQUEST['flag'], $_REQUEST['id']);
?>
