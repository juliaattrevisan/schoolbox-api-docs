<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//stats object.. determines the statistics for each homepage

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");
require_once("../include/syn_classlist.php");

class stats
{

	function stats($cid, $code="")
	{
		if (!$cid) 
			 error(0,"category not valid");

		//setup database
		$this->mySQLdb = new dbManager();
		$this->mySQLdb->dbConnect();

		$this->t_body = new Template(TPL_directory);

		//setup blocks
		$this->t_body->set_file("tplBODY", "subjects/stats.htm");
		//output page
		include_once("../include/page.php");

		$this->t_body->set_block("tplBODY", "menu", "menu_sect");
		$this->t_body->set_block("tplBODY", "stats_main", "main_sect");
		$this->t_body->set_block("stats_main", "tt_recent", "tt_recent_sect");
		$this->t_body->set_block("tt_recent", "tt_recent_listing", "tt_recent_block");
		$this->t_body->set_block("stats_main", "tt_freq", "tt_freq_sect");
		$this->t_body->set_block("tt_freq", "tt_freq_listing", "tt_freq_block");
		$this->t_body->set_block("stats_main", "all", "all_sect");
		$this->t_body->set_block("all", "all_listing", "all_block");
		$this->t_body->set_block("stats_main", "general", "general_sect");
		$this->t_body->set_block("general", "spotstats", "spotstats_sect");

		$query = "SELECT name, template FROM category WHERE id = '". $cid ."'";
		$this->mySQLdb->dbDoQuery($query);
		$cat = $this->mySQLdb->dbGetNextArray();
		
		$query = "SELECT date_format(datetime, '%D %M') as firstdate from homepage_log order by datetime asc limit 0,1";
		$this->mySQLdb->dbDoQuery($query);
		$firstdate = $this->mySQLdb->dbGetNextArray();
		$this->t_body->set_var("FIRSTDATE", $firstdate['firstdate']);
		$this->template = $cat['template'];
		$this->subname = $cat['name'];
		$this->category = $cid;
		$this->code = $code;

		if ($this->code != "") 
			$this->subnamep = $this->subname." (".$this->code.")";
		else
			$this->subnamep = $this->subname;

		$this->t_body->set_var("HEADING", $name." ".$this->subnamep);
		$this->t_body->set_var("TITLE", $name." ".$this->subnamep." - Page Statistics");
		$this->t_body->set_var("CATEGORY",  $this->subnamep);
		$this->t_body->set_var("ID", $cid);
		$this->t_body->set_var("CODE", $code);
	}
	
	function outputName($firstname, $lastname, $username, $localmail, $altmail)
	{
		if($localmail){
			$email = $username."@".MAIL_address;
			$this->t_body->set_var("FNAME", "<a href=\"".$_SESSION['mailhandle'].$email."\">".$firstname."</a>");
			$this->t_body->set_var("LNAME", "<a href=\"".$_SESSION['mailhandle'].$email."\">".$lastname."</a>");
		}
		else if($altmail){
			$this->t_body->set_var("FNAME", "<a href=\"".$_SESSION['mailhandle'].$altmail."\">".$firstname."</a>");
			$this->t_body->set_var("LNAME", "<a href=\"".$_SESSION['mailhandle'].$altmail."\">".$lastname."</a>");
		}
		else{
			$this->t_body->set_var("FNAME", $firstname);
			$this->t_body->set_var("LNAME", $lastname);
		}			
	}


	function displayTTRecent()
	{
		$this->t_body->set_var("LISTREC", "Top Ten Recent Visits");
		$this->t_body->set_var("DISPLAY", "Show All");

	  	//get top ten recent hits
		$query = "
			SELECT 	lastname, firstname, username, local_email, alt_email, DATE_FORMAT(datetime, '%a %D %b %H:%i') as date, hitcount 
			FROM 	user, homepage_log, category
			WHERE   category.id = '" . $this->category . "'	
			AND	category.id = homepage_log.cid  	
			AND 	user.id = homepage_log.user
			AND 	hitcount > 0
			ORDER BY datetime desc, hitcount desc
			LIMIT 0,10
			";
		
		$this->mySQLdb->dbDoQuery($query);		

		if($this->mySQLdb->dbCountRows() > 0 )
		{
			while( $student = $this->mySQLdb->dbGetNextArray())
			{
				$this->t_body->set_var("COUNT", $student['hitcount']);	
				
				$this->outputName($student['firstname'], $student['lastname'], $student['username'], $student['local_email'], $student['alt_email']);
	
				$this->t_body->set_var("DATE", $student['date']);	

				$this->t_body->parse("tt_recent_block", "tt_recent_listing", true);
			}
		}
		else
			$this->t_body->set_var("tt_recent_block", "<tr><td colspan=\"2\">No data available</td></tr>");

		$this->t_body->set_var("general_sect", "");
		$this->t_body->set_var("tt_freq_sect", "");
		$this->t_body->set_var("all_sect", "");
		$this->t_body->parse("tt_recent_sect", "tt_recent", true);
		$this->t_body->parse("main_sect", "stats_main", true);
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");

	}
	function displayRecent()
	{
		$this->t_body->set_var("LISTREC", "All Hits in Date Order");
		$this->t_body->set_var("DISPLAY", "");

		$query = "
			SELECT 	lastname, firstname, hitcount, username, local_email, alt_email, DATE_FORMAT(datetime, '%a %D %b %H:%i') as date
			FROM 	user, homepage_log, category
			WHERE   category.id = '" . $this->category . "'	
			AND	category.id = homepage_log.cid  	
			AND 	user.id = homepage_log.user
			AND 	hitcount > 0
			ORDER BY datetime desc, hitcount desc
			";
		
		$this->mySQLdb->dbDoQuery($query);		
		
		if($this->mySQLdb->dbCountRows() > 0 )
		{
			while( $student = $this->mySQLdb->dbGetNextArray())
			{
				$this->t_body->set_var("DATE", $student['date']);	
				$this->t_body->set_var("COUNT", $student['hitcount']);	
				$this->outputName($student['firstname'], $student['lastname'], $student['username'], $student['local_email'], $student['alt_email']);
				$this->t_body->parse("tt_recent_block", "tt_recent_listing", true);
			}
		}
		else
			$this->t_body->set_var("tt_recent_block", "<tr><td colspan=\"2\">No data available</td></tr>");

		$this->t_body->set_var("general_sect", "");
		$this->t_body->set_var("tt_freq_sect", "");
		$this->t_body->set_var("all_sect", "");
		$this->t_body->parse("tt_recent_sect", "tt_recent", true);
		$this->t_body->parse("main_sect", "stats_main", true);
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function displayTTFreq()
	{
		$this->t_body->set_var("LISTREC", "Top Ten Frequent Visits");
		$this->t_body->set_var("DISPLAY", "Show All");

		$query = "
			SELECT 	lastname, firstname, username, local_email, alt_email, hitcount,  DATE_FORMAT(datetime, '%a %D %b %H:%i') as date 
			FROM 	user, homepage_log, category
			WHERE   category.id = '" . $this->category . "'	
			AND	category.id = homepage_log.cid
			AND 	user.id = homepage_log.user
			AND	hitcount > 0
			ORDER BY hitcount desc, datetime desc
			LIMIT 0,10
			";
		
		$this->mySQLdb->dbDoQuery($query);		

		if($this->mySQLdb->dbCountRows() > 0 )
		{
			while ( $student = $this->mySQLdb->dbGetNextArray())
			{
				$this->t_body->set_var("DATE", $student['date']);	
				$this->t_body->set_var("COUNT", $student['hitcount']);	
				$this->outputName($student['firstname'], $student['lastname'], $student['username'], $student['local_email'], $student['alt_email']);
				$this->t_body->parse("tt_freq_block", "tt_freq_listing", true);

			}
		}
		else
			$this->t_body->set_var("tt_freq_block", "<tr><td colspan=\"2\">No data available</td></tr>");

		$this->t_body->set_var("general_sect", "");
		$this->t_body->set_var("tt_recent_sect", "");
		$this->t_body->set_var("all_sect", "");
		$this->t_body->parse("tt_freq_sect", "tt_freq", true);
		$this->t_body->parse("main_sect", "stats_main", true);
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}


	function displayFreq()
	{
		$this->t_body->set_var("LISTREC", "All Visits in Frequency Order");
	 	$this->t_body->set_var("DISPLAY", "");	

		$query = "
			SELECT 	lastname, firstname, username, hitcount, local_email, alt_email, DATE_FORMAT(datetime, '%a %D %b %H:%i') as date
			FROM 	user, homepage_log, category
			WHERE   category.id = '" . $this->category . "'	
			AND	category.id = homepage_log.cid
			AND 	user.id = homepage_log.user
			AND	hitcount > 0
			GROUP BY lastname
			ORDER BY hitcount desc, datetime desc
			";
		
		$this->mySQLdb->dbDoQuery($query);		

		if($this->mySQLdb->dbCountRows() > 0 )
		{
			while ( $student = $this->mySQLdb->dbGetNextArray())
			{
				$this->t_body->set_var("DATE", $student['date']);	
				$this->t_body->set_var("COUNT", $student['hitcount']);	
				$this->outputName($student['firstname'], $student['lastname'], $student['username'], $student['local_email'], $student['alt_email']);
				$this->t_body->parse("tt_freq_block", "tt_freq_listing", true);
			}
		}
		else
			$this->t_body->set_var("tt_freq_block", "<tr><td colspan=\"2\">No data available</td></tr>");

		$this->t_body->set_var("general_sect", "");
		$this->t_body->set_var("tt_recent_sect", "");
		$this->t_body->set_var("all_sect", "");
		$this->t_body->parse("tt_freq_sect", "tt_freq", true);
		$this->t_body->parse("main_sect", "stats_main", true);
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayMembers()
	{
		//get member hits
		$this->t_body->set_var("LISTREC", "All Member Visits");

		if ($this->code) //get synergetic member list
		{
			$result = getStudents($this->code);
			if (mssql_num_rows($result) > 0)
			{
			  while($student = @mssql_fetch_array($result))
			  {
				$query = "
					SELECT	username, firstname, lastname, local_email, alt_email, homepage_log.hitcount, DATE_FORMAT(datetime, '%a %D %b %H:%i') as date 
					FROM	user
					LEFT JOIN homepage_log  ON user.id = homepage_log.user
					AND	homepage_log.cid = '".$this->category."'
					WHERE	user.synergy_id = '". $student['synergy_id'] ."'
					";

				$this->mySQLdb->dbDoQuery($query) or die ("problem with finding all members query");		
				if ($this->mySQLdb->dbCountRows() > 0)
				{
					$info = $this->mySQLdb->dbGetNextArray();
					if (!$info['hitcount'])
					{
						$this->t_body->set_var("COUNT", "0");
						$this->t_body->set_var("DATE", "Never");
					}
				 	else
					{
						$this->t_body->set_var("COUNT", $info['hitcount']);		  
						$this->t_body->set_var("DATE", $info['date']);
					}
					$this->outputName($info['firstname'], $info['lastname'],  $info['username'], $info['local_email'], $info['alt_email']);

				}
				else
				{
					$this->t_body->set_var("COUNT", "0");
					$this->t_body->set_var("DATE", "Student possibly has no portal account or category invalid");
				}
				

				$this->t_body->parse("all_block", "all_listing", true);
			  }		
			}
			else
				$this->t_body->set_var("all_block", "<tr><td colspan=\"2\">No data available</td></tr>");
		}	
		else 
		{
			$query = "
				SELECT 	lastname, firstname, username, hitcount, local_email, alt_email, DATE_FORMAT(datetime, '%a %D %b %H:%i') as date 
				FROM 	user,userAccess
				LEFT JOIN homepage_log ON user.id = homepage_log.user
				WHERE  userAccess.sid =  " . $this->category . "
				AND userAccess.service = ".HOMEPAGESERVICE."
				AND 	user.id = userAccess.uid
				AND homepage_log.cid = ".$this->category."
				AND hitcount > 0
				ORDER BY lastname asc, firstname asc	
		 	";

			$this->mySQLdb->dbDoQuery($query);		
		
			if($this->mySQLdb->dbCountRows() > 0 )
			{
				while ($member = $this->mySQLdb->dbGetNextArray())
				{
					$this->t_body->set_var("DATE", $member['date']);	
					$this->t_body->set_var("COUNT", $member['hitcount']);	
					$this->outputName($member['firstname'], $member['lastname'],  $member['username'], $member['local_email'], $member['alt_email']);

					$this->t_body->parse("all_block", "all_listing", true);
				}
			}
			else
				$this->t_body->set_var("all_block", "<tr><td colspan=\"2\">No data available</td></tr>");
				
		}	
	 	$this->t_body->set_var("DISPLAY", "Total Hits - ".$student['total']);	
		$this->t_body->set_var("tt_recent_sect", "");
		$this->t_body->set_var("tt_freq_sect", "");
		$this->t_body->set_var("general_sect", "");
		$this->t_body->parse("all_sect", "all", true);
		$this->t_body->parse("main_sect", "stats_main", true);
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayGeneral()
	{
		//get hitcount for the portal
		$query = "
			SELECT SUM(hitcount) AS total 
			FROM homepage_log 
		";
		$queryend = " GROUP BY cid";
		$this->mySQLdb->dbDoQuery($query);
		$result = $this->mySQLdb->dbGetNextArray();
		$this->t_body->set_var("PORTALALL", $result['total']); 

		//get hitcount for this page
		$nextquery = $query."WHERE cid = '".$this->category."'".$queryend;
		$this->mySQLdb->dbDoQuery($nextquery);
		$result = $this->mySQLdb->dbGetNextArray();
	 	$this->t_body->set_var("ALL", $result['total']);	

		//get member count for the portal		
		if ($this->code == "") #if not a class page
		{
			$query = "
				SELECT SUM(hitcount) AS total
				FROM homepage_log, userAccess
				WHERE user = uid
				AND service = 1
				AND sid = '".$this->category."'
			";
			$queryend = " GROUP BY cid";
			$this->mySQLdb->dbDoQuery($query);
			$result = $this->mySQLdb->dbGetNextArray();
	 		$this->t_body->set_var("PORTALMEM", $result['total']);	

			//get member count for this category
			$nextquery = $query."AND cid = '".$this->category."'".$queryend;
			$this->mySQLdb->dbDoQuery($nextquery);
			$result = $this->mySQLdb->dbGetNextArray();
	 		$this->t_body->set_var("MEMBER", $result['total']);	
		}	
		else #if class page
		{
			//get all count for this page
 			$studentList = getStudents($this->code);
			$totalmem = 0;
			$totalport = 0;
			$ids = "(";
      			while($student = @mssql_fetch_array($studentList))
      			{
				$ids.= "'".$student['synergy_id']."',";
			}
			$ids = substr($ids, 0, -1);
			$ids.= ")";
        		$query = "
          			SELECT SUM(hitcount) as total
          			FROM  user
          			LEFT JOIN homepage_log ON user.id = homepage_log.user WHERE
        		";
          		$querymid = " homepage_log.cid = '".$this->category."' AND";
			$queryend = " user.synergy_id IN ". $ids;

			$nextquery = $query.$queryend;
        		$this->mySQLdb->dbDoQuery($nextquery) or die ("problem with finding all members query");
			$result = $this->mySQLdb->dbGetNextArray();
	 		$this->t_body->set_var("PORTALMEM", $result['total']);	
			
      			$nextquery = $query.$querymid.$queryend;
        		$this->mySQLdb->dbDoQuery($nextquery) or die ("problem with finding all members query");
			$result = $this->mySQLdb->dbGetNextArray();
	 		$this->t_body->set_var("MEMBER", $result['total']);	
		}
		//base query to find most popular page
		$query = "
			SELECT cid, name, code, SUM(hitcount) as totalhits 
			FROM homepage_log, category
			WHERE id = cid
		"; 
		$queryend = " GROUP BY cid ORDER BY totalhits desc"; 

		//for classes
		$queryc = $query." AND template in (1, 5)".$queryend;
        	$this->mySQLdb->dbDoQuery($queryc) or die ("problem with finding all members query");
		$resultc = $this->mySQLdb->dbGetNextArray();
	 	$this->t_body->set_var("CID", $resultc['cid']);	
	 	$this->t_body->set_var("HOMEPAGE", $resultc['name']." (".$result['code'].")" );	
	 	$this->t_body->set_var("HITS", $resultc['totalhits']);	
	 	$this->t_body->set_var("TEMPLATE", "Class");	
		$this->t_body->parse("spotstats_sect", "spotstats", true);
	
		//for groups	
		$queryg = $query." AND template in (2, 3)".$queryend;
        	$this->mySQLdb->dbDoQuery($queryg) or die ("problem with finding all members query");
		$resultg = $this->mySQLdb->dbGetNextArray();
	 	$this->t_body->set_var("CID", $resultg['cid']);	
	 	$this->t_body->set_var("HOMEPAGE", $resultg['name']);	
	 	$this->t_body->set_var("HITS", $resultg['totalhits']);	
	 	$this->t_body->set_var("TEMPLATE", "Group");	
		$this->t_body->parse("spotstats_sect", "spotstats", true);

		//for faculties	
		$queryf = $query." AND template = 4".$queryend;
        	$this->mySQLdb->dbDoQuery($queryf) or die ("problem with finding all members query");
		$resultf = $this->mySQLdb->dbGetNextArray();
	 	$this->t_body->set_var("CID", $resultf['cid']);	
	 	$this->t_body->set_var("HITS", $resultf['totalhits']);	
	 	$this->t_body->set_var("HOMEPAGE", $resultf['name']);	
	 	$this->t_body->set_var("TEMPLATE", "Faculty");	
		$this->t_body->parse("spotstats_sect", "spotstats", true);
	
		//for houses	
		$queryh = $query." AND template = 6".$queryend;
        	$this->mySQLdb->dbDoQuery($queryh) or die ("problem with finding all members query");
		$resulth = $this->mySQLdb->dbGetNextArray();
	 	$this->t_body->set_var("CID", $resulth['cid']);	
	 	$this->t_body->set_var("HITS", $resulth['totalhits']);	
	 	$this->t_body->set_var("HOMEPAGE", $resulth['name']);	
	 	$this->t_body->set_var("TEMPLATE", "House");	
		$this->t_body->parse("spotstats_sect", "spotstats", true);
		
	 	$this->t_body->set_var("DISPLAY", "");	
		$this->t_body->set_var("tt_freq_sect", "");
		$this->t_body->set_var("tt_recent_sect", "");
		$this->t_body->set_var("all_sect", "");
		$this->t_body->parse("general_sect", "general", true);
		$this->t_body->parse("main_sect", "stats_main", true); 
		$this->t_body->parse("menu_sect", "menu", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}	
}

if(isStaff($_SESSION['gid']))
{
	$page = new stats($_GET['cid'], $_GET['code']);

	$i = $_GET['flag'];
	
	switch ($i) {
		case 1:
			$page->displayTTRecent();
			break;
		case 2:
			$page->displayTTFreq();
			break;
		case 3:
			$page->displayMembers();
			break;
		case 4:
			$page->displayRecent();
			break;
		case 5:
			$page->displayFreq();
			break;
		default:
			$page->displayGeneral();
	}
}
else
	error(14);
?>
