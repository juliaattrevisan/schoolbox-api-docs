<?php

//sports menu object...allows for

require_once("../../include/session.php");
//require_once("../../include/permission.php");
require_once("../../include/include.php");
require_once("../../include/html-time.php");
require_once("sql/modsport.php");

class sports_menu
{

	function sports_menu()
	{
		$this->t_body = new Template(TPL_directory);
		//setup file
		$this->t_body->set_file("tplBODY", "modules/sports/sports_menu.htm");
	 	//output page
		include_once("../../include/page.php");

		//setup blocks
		$this->t_body->set_block("tplBODY", "menu", "menu_sect");
		$this->t_body->set_block("menu", "sports_list", "sports_listing");
		$this->t_body->set_block("menu", "admin_option", "adminop_sect");
		$this->t_body->set_block("menu", "APSlinks", "apslinks_sect");
		$this->t_body->set_block("menu", "notes_list", "notes_listing");

		$camp = 1;
		if ($camp == 3)
			$this->camp = "Grimwade";
		else if ($camp == 2)
			$this->camp = "Wadhurst";
		else
			$this->camp = "Senior";

 		$this->t_body->set_var("CAMPUS", $this->camp);
 		$this->t_body->set_var("TITLE", "Sports Menu");
                $this->t_body->set_var("DISPLAY", "");
                $this->t_body->set_var("NAV", "");

	}

	function displayMain()
	{
		//display list of notes from homepage called Fixture Comments (campus)
		$query = "SELECT news.heading,
				 news.body,
				 date_format(news.datetime_posted,'%b %D') as postdate,
				 user.firstname,
				 user.lastname
			  FROM category as cat, subject_news as news, user as user
			  WHERE news.code = cat.id
			  AND user.id = news.owner
			  AND cat.name = 'Fixture Comments (".$this->camp.")'
			  AND news.deleted = '0'
			  AND news.datetime_expires > now()
			  ORDER BY news.datetime_posted DESC
			";
		$db = new DeprecatedDatabase();
		$db->dbDoQuery($query);
		$i = 0;
		while ($notes = $db->dbGetNextArray()){
			$this->t_body->set_var("HEADING", $notes['heading']);
			$this->t_body->set_var("ITEM", $notes['body']);
			$this->t_body->set_var("OWNER", $notes['firstname']." ".$notes['lastname']);
			$this->t_body->set_var("DATEPOSTED", $notes['postdate']);
			$this->t_body->parse("notes_listing", "notes_list", true);
			$i++;
		}
		if ($i == 0){
			$this->t_body->set_var("HEADING", "");
			$this->t_body->set_var("ITEM","No notices are currently posted");
			$this->t_body->set_var("OWNER", "");
			$this->t_body->set_var("DATEPOSTED", "");
			$this->t_body->parse("notes_listing", "notes_list", true);
		}

 		$mySport = new sport();
                $sports = $mySport->getSports();

		if ($sports){
               		foreach ($sports as $sport)
               		{
				//display list of sports to begin with
                		$this->t_body->set_var("ACTION", "
					<a href=\"sports_fixture.php?id=".$sport['sid']."&type=0\">Coming Week</a>&nbsp;/&nbsp;
					<a href=\"sports_fixture.php?id=".$sport['sid']."&type=1\">Season</a>
				");
                       		$this->t_body->set_var("SPORT", $sport['name']);
                       		$this->t_body->set_var("ID", $sport['sid']);
                       		$this->t_body->parse("sports_listing", "sports_list", true);
               		}
		}
		else {
			$this->t_body->set_var("sports_listing", "<font color=\"#FF0000\"><br>Current season may not be set.<br><br>Please speak to sports administrators.</font>");
		}

		//check permissions for whether to display link to administration menu
		if (perm(1, 3) > 0)
                       	$this->t_body->parse("adminop_sect", "admin_option", true);
		else
                       	$this->t_body->set_var("adminop_sect", "");

		//no sports exist for this campus, display add season, add sports
               	$this->t_body->set_var("DISPLAY", "Main Menu");
               	$this->t_body->set_var("NAV", "");
		if ($_SESSION['campus'][0] == 1)
			$this->t_body->parse("apslinks_sect", "APSlinks", true);
		else
			$this->t_body->set_var("apslinks_sect", "");
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}

$page = new sports_menu();

$page->displayMain();

?>
