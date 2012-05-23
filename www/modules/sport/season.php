<?php

//showSeason object..gets all info required to populate template fields in season.htm.

require_once("../../include/session.php");
require_once("../../include/permission.php");
require_once("../../include/include.php");
require_once("../../include/html-time.php");
require_once("sql/modseason.php");

class initSeason
{
	function initSeason($flag = "", $id = "")
	{
                $this->flag = $flag;
                $this->id = $id;
                switch ($this->flag){
                        case 1:
 				//add season
				$this->initDisplay();
                                if (isset($_POST['season']) && $_POST['season'])
                                {
                                        $mySeason = new season();
                                        $startDate = $_POST['syear']."-".$_POST['smth']."-".$_POST['sday'];
                                        $endDate = $_POST['eyear']."-".$_POST['emth']."-".$_POST['eday'];

                                        if ($mySeason->addSeason($_POST['season'], $startDate, $endDate, $_POST['current'], $_POST['type']))
                                		$this->displaySeasons();
					else
                                                print "could not add season";
                                }
                                else {
					$this->displayAddSeason();
				}
				break;
			case 2:
				//save season
				$this->initDisplay();
                                if (isset($_POST['saveseason']) && $_POST['saveseason'])
                                {
                                        $snids = $_POST['snid'];
                                        $season = $_POST['season'];
                                        $syear = $_POST['syear'];
                                        $eyear = $_POST['eyear'];
                                        $smth = $_POST['smth'];
                                        $emth = $_POST['emth'];
                                        $sday = $_POST['sday'];
                                        $eday = $_POST['eday'];
                                        $curr = $_POST['current'];
                                        $type = $_POST['type'];
                                        $num = 0;
                                        foreach ($snids as $snid)
                                        {
                                                $startDate = $syear[$num]."-".$smth[$num]."-".$sday[$num];
                                                $endDate = $eyear[$num]."-".$emth[$num]."-".$eday[$num];
                                                $mySeason = new season($snid);
                                                $mySeason->modifySeason($season[$num], $startDate, $endDate, $curr[$num], $type[$num]);
                                                $num++;
                                        }
                                        $this->displaySeasons();
                                }
                                else
                                        $this->displaySeasons();
				break;
			case 3:
 				//delete season
                                $mySeason = new season($this->id);
                                $mySeason->deleteSeason();
				$this->initDisplay();
                                $this->displaySeasons();
				break;
			default:
  				//display seasons
				$this->initDisplay();
                                $this->displaySeasons();
				break;
		}
	}

	function initDisplay()
	{
		$this->t_body = new Template(TPL_directory);

		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/admin/season.htm");
	 	//output page
		include_once("../../include/page.php");

		//setup blocks
		//$this->t_body->set_block("tplBODY", "menu", "menu_block");
		$this->t_body->set_block("tplBODY", "modseasons_body", "modseasons_block");
		$this->t_body->set_block("modseasons_body", "modseasons_list", "modseasons_listing");
		$this->t_body->set_block("tplBODY", "add_season", "addseason_block");

		$this->t_body->set_var("TITLE", "Sports Season Administration");

		$this->t_body->set_var("DISPLAY", $display);
		$this->t_body->set_var("NAV", "");
	}

	function displayAddSeason()
	{
		//displays form to add a season and store it in database

		$date = getdate();

		$this->t_body->set_var("SDAY", createDateList($date['day'], "sday"));
		$this->t_body->set_var("SMTH", createMonthList($date['month'], "smth"));
		$this->t_body->set_var("SYEAR", createYearList($date['year'], "syear"));

		$this->t_body->set_var("EDAY", createDateList($date['day'], "eday"));
		$this->t_body->set_var("EMTH", createMonthList($date['month'], "emth"));
		$this->t_body->set_var("EYEAR", createYearList($date['year'], "eyear"));

		$this->t_body->set_var("NAV", "<a href=\"season.php\"><b>Back</b></a>");
		$this->t_body->set_var("DISPLAY", "Add Season");
	 	$this->t_body->set_var("modseasons_block", "");
		//$this->t_body->parse("menu_block", "menu", true);
		$this->t_body->parse("addseason_block", "add_season", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displaySeasons()
	{
		//display list of seasons stored in database
		$this->t_body->set_var("NAV", "<a href=\"sport.php?dest=4\"><b>Admin Menu</b></a>&nbsp;&nbsp;&nbsp;<a href=\"season.php?flag=1\"><b>Add Season</b></a>");
		$this->t_body->set_var("DISPLAY", "Modify Seasons");

		$mySeason = new season();
		$seasons = $mySeason->getSeasons();
		$i = 0;
		if ($seasons)
		{
			foreach ($seasons as $season)
			{
				$this->t_body->set_var("SNID", $season['id']);
				$this->t_body->set_var("SEASON", $season['season']);

               		        $this->t_body->set_var("EDAY", createDateList($season['eday'], "eday[]"));
                       		$this->t_body->set_var("EMTH", createMonthList($season['emonth'], "emth[]"));
               		        $this->t_body->set_var("EYEAR", createYearList($season['eyear'], "eyear[]"));

               		        $this->t_body->set_var("SDAY", createDateList($season['sday'], "sday[]"));
                       		$this->t_body->set_var("SMTH", createMonthList($season['smonth'], "smth[]"));
               		        $this->t_body->set_var("SYEAR", createYearList($season['syear'], "syear[]"));

				//display current
				//set selected value for current
				if ($season['current'] == 0){
					$this->t_body->set_var("C0", "selected");
					$this->t_body->set_var("C1", "");
				}
				else {
					$this->t_body->set_var("C1", "selected");
					$this->t_body->set_var("C0", "");
				}
				//display type
				//if additional seasons are added, this number must be increased
				for ($i = 1; $i < 8; $i++){
					if ($i == $season['type'])
						$this->t_body->set_var("S".$i, "selected");
					else
						$this->t_body->set_var("S".$i, "");
				}
				$this->t_body->parse("modseasons_listing", "modseasons_list", true);
			}
		}
		else
			$this->t_body->set_var("modseasons_listing", "");

		$this->t_body->set_var("addseason_block", "");
		$this->t_body->set_var("menu_block", "");
		$this->t_body->parse("modseasons_block", "modseasons_body", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}
if (perm(1,3) > 0){

	$page = new initSeason($_REQUEST['flag'], $_REQUEST['id']);
}
else
	error(19);
?>
