<?php

//sportsadmin object...gets all info required to populate template fields in sport.htm

require_once("../../include/include.php");
require_once("../../include/session.php");
//require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("sql/modsport.php");
require_once("sql/modstaff.php");

class initSport
{
	function initSport($dest = "", $flag = "", $id = "")
	{
		$this->dest = $dest;
		$this->flag = $flag;
		$this->id = $id;
		switch ($this->flag){
			case 1:
        			//add sport
        			if (isset($_POST['title']) && $_POST['title'])
        			{
                			$mySport = new sport($_POST['title']);
                			if ($mySport->addSport($_POST['sportType']))
                			{
						$this->initDisplay();
                       		 		$this->displayAddSport();
                			}
                			else
                        			print "could not add sport";
        			}
        			else
        			{
               	 			$this->initDisplay();
                			$this->displayAddSport();
        			}
				break;
			case 2:
        			//save sports
        			if (isset($_POST['savesport']) && $_POST['savesport'])
        			{
                			$mySport = new sport();
                			if (isset($_POST['sect']) && $_POST['sect'])
                			{
                        			$section = $_POST['sect'];
                        			$staffid1 = $_POST['staffid1'];
                        			$staffid2 = $_POST['staffid2'];
                        			$tid = $_POST['tid'];
                        			$order = $_POST['teamorder'];
                        			$num = 0;
                        			foreach ($section as $sect)
                        			{
                               		 		$mySport->modifyTeams($id, $sect, $staffid1[$num], $staffid2[$num] , $tid[$num], $order[$num]);
                                			$num++;
                        			}
                			}
        			}
        			$this->initDisplay();
        			$this->displayModSport();
				break;
			case 3:
        			//delete sport
        			$mySport = new sport();
        			$mySport->deleteSport($id);
				$this->initDisplay();
       	 			$this->displaySport();
				break;
			case 4:
        			//add team
        			if (isset($_POST['team']) && $_POST['team'])
        			{
                			$mySport = new sport();
                			$staffid1 = $_POST['teamstaff1'];
                			$staffid2 = $_POST['teamstaff2'];
                			if ($mySport->addTeam($id, $_POST['team'], $staffid1[0], $staffid2[0]))
                			{
                       		 		$this->initDisplay();
                       		 		$this->displayAddTeam();
                			}
                			else
                       		 		print "could not add team";
        			}
        			else {
                			$this->initDisplay();
                			$this->displayAddTeam();
        			}
				break;
			case 5:
        			//delete team
        			$mySport = new sport();
        			$mySport->deleteTeam($id, $_GET['tid']);
        			$this->initDisplay();
        			$this->displayModSport();
				break;
			default:
        			//display sports list
        			$this->initDisplay();
        			$this->displaySport();
				break;
		}
	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);

		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/sport.htm");
	 	//output page
		include_once("../../include/page.php");

		//setup blocks
		$this->t_body->set_block("tplBODY", "sports_body", "sports_block");
		$this->t_body->set_block("sports_body", "sports_list", "sports_listing");

		$this->t_body->set_block("tplBODY", "section_body", "section_block");
		$this->t_body->set_block("section_body", "section_list", "section_listing");

		$this->t_body->set_block("tplBODY", "add_sport", "addsport_block");
		$this->t_body->set_block("tplBODY", "add_team", "addteam_block");

		$this->t_body->set_var("DISPLAY", $display);
		$this->t_body->set_var("NAV", "");
		$this->t_body->set_var("TITLE", "Sport and Team Administration");
	}

	function displaySport()
	{
		//display list of sports to begin with

		$mySport = new sport();
		$sports = $mySport->getSports();
		if ($this->dest == 1)
		{
			$this->t_body->set_var("DISPLAY", "Modify Sport");
			$this->t_body->set_var("NAV", "   <a href=\"sport.php?dest=1&flag=1\"><b>Add Sport</b></a>");
		}
		else if	 ($this->dest == 4)
		{
			$this->t_body->set_var("DISPLAY", "Modify Events");
			$this->t_body->set_var("NAV", "");
		}
		if ($sports){
			foreach ($sports as $sport) {
				if ($this->dest == 1){

					$this->t_body->set_var("ACTION", "<a href=\"teamLists.php?id=".$sport['sid']."\">Team Lists</a>
					&nbsp;/&nbsp;
					<a href=\"sport.php?dest=1&id=".$sport['sid']."&flag=2\">Modify Teams</a>
					&nbsp;/&nbsp;
					<a href=\"sport.php?dest=1&id=".$sport['sid']."&flag=3\" onClick=\"return(confirm('Delete this sport?'))\">Delete</a>");

				}
				else if ($this->dest == 4)
					$this->t_body->set_var("ACTION", "<a href=\"event.php?id=".$sport['sid']."&flag=1&type=0\">Week</a>&nbsp;/&nbsp;<a href=\"event.php?id=".$sport['sid']."&flag=1&type=1\">Season</a>");

				$this->t_body->set_var("SPORT", $sport['name']);
				$this->t_body->set_var("ID", $sport['sid']);
				$this->t_body->parse("sports_listing", "sports_list", true);
			}
		}
		else {
			$this->t_body->set_var("SPORT", "<font color=\"#FF0000\"><br>No season set to current<br>Or no sports exist</font>");
			$this->t_body->set_var("ID", "");
			$this->t_body->parse("sports_listing", "sports_list", true);
		}
		$this->t_body->set_var("addteam_block", "");
		$this->t_body->set_var("addsport_block", "");
		$this->t_body->set_var("section_block", "");
		$this->t_body->parse("sports_block", "sports_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayAddSport()
	{
		//displays form to add a sport and stores it in database
		$this->t_body->set_var("NAV", "<a href=\"sport.php?dest=1\"><b>Back</b></a>");
		$this->t_body->set_var("DISPLAY", "Add Sport");
		$this->t_body->set_var("sports_block", "");
		$this->t_body->set_var("section_block", "");
		$this->t_body->set_var("addteam_block", "");
		$this->t_body->parse("addsport_block", "add_sport", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayAddTeam()
	{
		//displays form to add a team and stores it in database
		$this->t_body->set_var("NAV", "<a href=\"sport.php?dest=1&flag=2&id=".$this->id."\"><b>Back</b></a>");
		$this->t_body->set_var("DISPLAY", "Add Team");
		$this->t_body->set_var("ID", $this->id);
		$num = 1;
		$myStaff = new staff();
	 	if ($staff = $myStaff->getStaff())
		{
			while ($num < 3)
			{
				$list = $this->createHTMLstaffList($myStaff->getStaff(), 0 );
				$this->t_body->set_var("STAFFID".$num, $list);
				$num++;
			}
		}
		$this->t_body->set_var("sports_block", "");
		$this->t_body->set_var("section_block", "");
		$this->t_body->set_var("addsport_block", "");
		$this->t_body->parse("addteam_block", "add_team", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayModSport()
	{
		//displays form to modify all teams and staff relative to sport chosen
		$this->t_body->set_var("DISPLAY", "Modify Sport");
		$this->t_body->set_var("NAV",
			"<a href=\"sport.php?dest=1&flag=4&id=".$this->id."\"><b>Add Team</b></a>");

		$shift = 1;
		$this->t_body->set_var("ID", $this->id);
		if (isset($this->id) && $this->id)
		{
			$mySport = new sport();
			$myStaff = new staff();
			$prevtid = 0;
			if ($info = $mySport->getSportInfo($this->id))
			{
				while ($set = array_shift($info))
				{
					//create html list of staff with appropriate staff selected
					$list = $this->createHTMLstaffList($myStaff->getStaff(), $set['staffid']);

					//output secondary staff only - for previous team
					if ($set['tid'] == $prevtid)
					{
						$this->t_body->set_var("STAFF2LIST", $list);
						$this->t_body->parse("section_listing", "section_list", true);
					}
					else
					{
						//output team with secondary staff only
						if ($prevpos == 1)
						{
							$this->t_body->set_var("STAFF2LIST", $this->createHTMLstaffList($myStaff->getStaff(), 0) );
							$this->t_body->parse("section_listing", "section_list", true);
						}
						$this->t_body->set_var("SECT", $set['sect']);
						$this->t_body->set_var("TID", $set['tid']);
						$this->t_body->set_var("ORDER", $set['teamorder']);
						$this->t_body->set_var("DISPLAY", "Modify ".$set['sname']);
						//output team with secondary staff only - for current team
						if ($set['pos'] == 2)
						{
							$this->t_body->set_var("STAFF1LIST", $this->createHTMLstaffList($myStaff->getStaff(), 0) );
							$this->t_body->set_var("STAFF2LIST", $list);
							$this->t_body->parse("section_listing", "section_list", true);
						}
						//output team with no staff selected - for current team
						else if ($set['pos'] == "")
						{
							$this->t_body->set_var("STAFF1LIST", $this->createHTMLstaffList($myStaff->getStaff(), 0) );
							$this->t_body->set_var("STAFF2LIST", $this->createHTMLstaffList($myStaff->getStaff(), 0) );
							$this->t_body->parse("section_listing", "section_list", true);
						}
						//output team with primary staff only - for current team
						else if ($set['pos'] == 1)
						{
							$this->t_body->set_var("STAFF1LIST", $list);
						}
						else
                        				error(9, "Ivalid pos value : see system's administrator");
					}
					$prevtid = $set['tid'];
					$prevpos = $set['pos'];
				}
				if ($prevpos == 1)
				{
					$this->t_body->set_var("STAFF2LIST", $this->createHTMLstaffList($myStaff->getStaff(), 0) );
					$this->t_body->parse("section_listing", "section_list", true);
				}
			}
			else
			{
				$this->t_body->set_var("section_listing", "<font color=\"#FF0000\"><b>No teams exist yet</b></font>");
			}
		}
		else
		{
			$this->t_body->set_var("section_listing", "Error with sport - see administrator");
		}
		$this->t_body->set_var("sports_block", "");
		$this->t_body->set_var("addsport_block", "");
		$this->t_body->set_var("addteam_block", "");
		$this->t_body->parse("section_block", "section_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function array_column_sort($array, $column)
	{
		foreach ($array as $row){
			$sortarr[] = $row[$column];
		}
		array_multisort($sortarr, $array);
		return $array;
	}

	function createHTMLstaffList($staffarr, $staffid)
	{
		$htmlList = "<option value=\"\">Not Selected</option>";
		$staffarr = $this->array_column_sort($staffarr, "fname");
		foreach ($staffarr as $staff)
		{
			$name = $staff['fname']." ".$staff['lname'];
	 		if ($staffid == $staff['staffid'])
				$htmlList .= "<option value=\"".$staff['staffid']."\" selected>$name</option>";
			else
				$htmlList .= "<option value=\"".$staff['staffid']."\">$name</option>";
		}
		return $htmlList;
	}
}
if (perm(1,3) > 0){
	$page = new initSport($_REQUEST['dest'], $_REQUEST['flag'], $_REQUEST['id']);
}
else
	error(19);
?>
