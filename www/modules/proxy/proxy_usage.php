<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//outputs weekly reports
require("../../include/session.php");
require("../../include/include.php");
require("../../include/permission.php");
require("common.php");


class ProxyUsage {

function ProxyUsage() {
	$currentYear = date("Y");

	if($_GET['week'] == 0)
		$week = getCurrentWeek( mktime()-604800 );
	else
		$week = $_GET['week'];

	$proxydb = new dbManager("proxy");
	$proxydb->dbConnect();

	$this->t_body = new Template(TPL_directory);

	//check perms
	$perms = perm(0, 4);

	if($_GET['usage'])
	{
		//only people with rights can access this page
		if($perms != 3 && $perms != 2)
			error(19);
	
		//display full usage report for week
	
		$this->t_body->set_file("tplBODY", "modules/proxy/full_list.htm");
		include("../../include/page.php");
		$proxydb->dbDoQuery("use proxy");

		$this->t_body->set_var("TITLE", HEADING." - Full Listing of Proxy Usage");
		$this->t_body->set_block("tplBODY", "user", "user_block");
	
		$this->t_body->set_var("TOPIC", "Usage");

		//if campus submitted use that otherwise default to user campus
		if($_GET['campus'])
			$campusid = $_GET['campus'];
		else
			$campusid = $_SESSION['campus'][0];

		if($campusid == ALLCAMPUS)
			$campusquery = "";
		else
			$campusquery = "AND userCampus.campus = '$campusid'";

		if($perms == 3)
		{
			$this->t_body->set_var("WHO", "Everyone");
			$studquery = "";
		}
		else 
		{
			$this->t_body->set_var("WHO", "All Students");
			$studquery = "AND user.group_id NOT IN(1,2)";
		}


		$query = "SELECT DISTINCT user.firstname, user.lastname, user.id, proxy.usage_report_week.user, usage_report_week.datausage
		FROM proxy.usage_report_week
		RIGHT JOIN intranet.user ON usage_report_week.user = user.id
                LEFT JOIN intranet.userCampus ON user.id = userCampus.uid
		WHERE usage_report_week.year = '$currentYear' AND usage_report_week.week = '$week' $studquery $campusquery
		ORDER BY usage_report_week.datausage DESC";

		$proxydb->dbDoQuery($query);
	
		for($x = 1; $row = $proxydb->dbGetNextArray(); $x++ )
		{
			$this->t_body->set_var("POS", $x.".");
			$this->t_body->set_var("NAME", $row['firstname']." ".$row['lastname']);
	                $this->t_body->set_var("UID", $row['id']);
			$this->t_body->set_var("HITS", bytesToString($row['datausage']));
			$this->t_body->set_var("VIEW", "<a href=\"proxy_log.php?uid=".$row['id']."&week=$week\">view urls</a>");

			$this->t_body->parse("user_block", "user", true);
		}
		

	}
	else if($_GET['cid'])
	{
		if($perms != 3 && $perms != 2)
			error(19);

	//display full category word matches

	$this->t_body->set_file("tplBODY", "modules/proxy/full_list.htm");
	include("../../include/page.php");
	$proxydb->dbDoQuery("use proxy");
	$this->t_body->set_var("TITLE", HEADING." - Full Listing of Filter Matches");
	$this->t_body->set_block("tplBODY", "user", "user_block");

	$query = "SELECT name FROM filter_category WHERE id = '".$_GET['cid']."'";
	$proxydb->dbDoQuery($query);
	$category = $proxydb->dbGetNextArray();

	$this->t_body->set_var("TOPIC", $category['name']." Filter");

	//if campus submitted use that otherwise default to user campus
	if($_GET['campus'])
		$campusid = $_GET['campus'];
	else
		$campusid = $_SESSION['campus'][0];

	if($campusid == ALLCAMPUS)
		$campusquery = "";
	else
		$campusquery = "AND userCampus.campus = '$campusid'";

	if($perms == 3)
	{
		$this->t_body->set_var("WHO", "Everyone");
		$studquery = "";
	}
	else 
	{
		$this->t_body->set_var("WHO", "All Students");
		$studquery = "AND user.group_id NOT IN(1,2)";
	}

	$query = "SELECT DISTINCT user.firstname, user.lastname, user.id, filter_report_week.user, filter_report_week.count, filter_report_week.datausage
		FROM proxy.filter_report_week 
		RIGHT JOIN intranet.user ON filter_report_week.user = user.id 
		LEFT JOIN intranet.userCampus ON user.id = userCampus.uid 
		WHERE filter_report_week.year = '$currentYear' AND filter_report_week.week = '$week'
		AND filter_report_week.category = '".$_GET['cid']."' $studquery $campusquery
		ORDER BY filter_report_week.count DESC";

	$proxydb->dbDoQuery($query);

	for($x = 1; $row = $proxydb->dbGetNextArray(); $x++ )
	{
		$this->t_body->set_var("POS", $x.".");
		$this->t_body->set_var("NAME", $row['firstname']." ".$row['lastname']);
                $this->t_body->set_var("UID", $row['id']);
		$this->t_body->set_var("HITS", $row['count']." (".bytesToString($row['datausage']).")");
		$this->t_body->set_var("VIEW", "<a href=\"proxy_log.php?uid=".$row['id']."&week=$week&cid=".$_GET['cid']."\">view urls</a>");

		$this->t_body->parse("user_block", "user", true);
	}

}
else if($_GET['report'])
{
	if($perms != 3 && $perms != 2)
		error(19);

	//display summary report
	
	$this->t_body->set_file("tplBODY", "modules/proxy/week_report.htm");
	include("../../include/page.php");
	$this->t_body->set_var("TITLE", HEADING." - Weekly Internet Usage Report");
	$this->t_body->set_block("tplBODY", "usage", "usage_block");
	$this->t_body->set_block("tplBODY", "category", "category_block");
	$this->t_body->set_block("category", "category_hits", "category_hits_block");

	//if campus submitted use that otherwise default to user campus
	if($_GET['campus'])
		$campusid = $_GET['campus'];
	else
		$campusid = $_SESSION['campus'][0];

	$this->t_body->set_var("CAMPUSSELECT", createCampusList(getCampusList(&$proxydb), $campusid, "campus", "onChange='this.form.submit();'")); 
	$this->t_body->set_var("WEEKSELECT", createWeekList($week, "week", "onChange='this.form.submit();'"));
	$this->t_body->set_var("CAMPUS", $campusid);

	if($campusid == ALLCAMPUS)
		$campusquery = "";
	else
		$campusquery = "AND userCampus.campus = '$campusid'";

	if($perms == 3)
	{
		$this->t_body->set_var("WHO", "Everyone");
		$studquery = "";
	}
	else 
	{
		$this->t_body->set_var("WHO", "All Students");
		$studquery = "AND user.group_id NOT IN(1,2)";
	}

	$proxydb->dbDoQuery("use proxy");

	$query = "SELECT DISTINCT user.firstname, user.lastname, user.id, usage_report_week.user, usage_report_week.datausage
		FROM proxy.usage_report_week
		RIGHT JOIN intranet.user ON usage_report_week.user = user.id 
		LEFT JOIN intranet.userCampus ON user.id = userCampus.uid 
 		WHERE usage_report_week.year = '$currentYear' AND usage_report_week.week = '$week' 
		$studquery $campusquery
		ORDER BY usage_report_week.datausage DESC LIMIT 0, 10";

	
	$proxydb->dbDoQuery($query);

        for($x = 1; $row = $proxydb->dbGetNextArray(); $x++ )
        {
                $this->t_body->set_var("POS", $x.".");
                $this->t_body->set_var("NAME", $row['firstname']." ".$row['lastname']);
                $this->t_body->set_var("UID", $row['id']);
                $this->t_body->set_var("USAGE", bytesToString($row['datausage']));
		$this->t_body->set_var("VIEW", "<a href=\"proxy_log.php?uid=".$row['id']."&week=$week\">view urls</a>");

                $this->t_body->parse("usage_block", "usage", true);

        }
	
	if($x == 1)
		$this->t_body->set_var("usage_block", "<tr><td>No report available</td></tr>");

	$query = "SELECT * FROM filter_category";
	$handle = $proxydb->dbDoQuery($query);
	while( $cat = $proxydb->dbGetNextArray($handle) )
	{
		$this->t_body->set_var("CATEGORY", $cat['name']);
		$this->t_body->set_var("CID", $cat['id']);

		$query = "SELECT DISTINCT user.firstname, user.lastname, user.id, filter_report_week.user, filter_report_week.count, filter_report_week.datausage
                        FROM filter_report_week
			RIGHT JOIN intranet.user ON filter_report_week.user = user.id 
			LEFT JOIN intranet.userCampus ON user.id = userCampus.uid 
			WHERE filter_report_week.year = '$currentYear' AND filter_report_week.week = '$week'
                        AND filter_report_week.category = '".$cat['id']."' $studquery $campusquery
                        ORDER BY filter_report_week.count DESC LIMIT 0,10";

		$proxydb->dbDoQuery($query);

		for($x = 1; $row = $proxydb->dbGetNextArray(); $x++ )
		{
			$this->t_body->set_var("POS", $x.".");
			$this->t_body->set_var("NAME", $row['firstname']." ".$row['lastname']);
	                $this->t_body->set_var("UID", $row['id']);
			$this->t_body->set_var("HITS", $row['count']." (".bytesToString($row['datausage']).")");
			$this->t_body->set_var("VIEW", "<a href=\"proxy_log.php?uid=".$row['id']."&week=$week&cid=".$cat['id']."\">view urls</a>");
                
			if($x==1)		
				$this->t_body->parse("category_hits_block", "category_hits", false);
			else
				$this->t_body->parse("category_hits_block", "category_hits", true);
		}
		if($x == 1)
			$this->t_body->set_var("category_hits_block", "<tr><td>No report available</td></tr>");
		
		//$this->t_body->parse("category_hits_block", "category_hits", false);
		$this->t_body->parse("category_block", "category", true);
		

	}
}
else
{
	//display user report
	$this->t_body->set_file("tplBODY", "modules/proxy/user_report.htm");
	include("../../include/page.php");
	$this->t_body->set_var("TITLE", HEADING." - Proxy User Report");
	$this->t_body->set_block("tplBODY", "category", "category_block");
		
	if($_GET['uid'])
	{
		$uid = $_GET['uid'];

		if($perms < 2)
			error(19);
	}
	else
		$uid = $_SESSION['id'];


	$query = "SELECT user.id, username, firstname, lastname, group_id, groups.name as group_name, luCampus.name as campus_name, synergy_id, year 
			FROM user
			LEFT JOIN groups ON group_id = groups.id 
			LEFT JOIN userCampus ON user.id = userCampus.uid
			LEFT JOIN luCampus ON userCampus.campus = luCampus.id
			WHERE user.id = '".$uid."'";

	$proxydb->dbDoQuery($query); 
	$user = $proxydb->dbGetNextArray();

	$this->t_body->set_var("FULLNAME", $user['firstname']." ".$user['lastname']);
	$this->t_body->set_var("SYNID", $user['synergy_id']);
	$this->t_body->set_var("UID", $user['id']);
	$this->t_body->set_var("CAMPUS", $user['campus_name']);
	$this->t_body->set_var("GROUP", $user['group_name']);
	if($_SESSION['mail'] == 1)
           	$email = "<a href=\"mailto:".$user['username']."@".MAIL_address."\">".$user['username']."@".MAIL_address."</a>";
	else
		$email = "<a href=\"/mail/send.php?replyto=".$user['username']."@".MAIL_address."\">".$user['username']."@".MAIL_address."</a>";
	$this->t_body->set_var("EMAIL", $email);
	$this->t_body->set_var("YEAR", $user['year']);
	

	$proxydb->dbDoQuery("use proxy");

	//get usage history?
	

	//get last weeks history
	$query = "SELECT datausage FROM usage_report_week WHERE year = '$currentYear' 
			AND week = '$week' AND user = '".$uid."'";
	
	$proxydb->dbDoQuery($query);
	$usage = $proxydb->dbGetNextArray();

	$this->t_body->set_var("WEEK_USAGE", bytesToString($usage['datausage']));

	//get total history
	$query = "SELECT SUM(datausage) as totalusage FROM usage_report_week WHERE year = '$currentYear' AND user = '".$uid."'";
	$proxydb->dbDoQuery($query);
	$usagetotal = $proxydb->dbGetNextArray();

	$this->t_body->set_var("TOTAL_USAGE", bytesToString($usagetotal['totalusage']));
	

	//last weeks filter hits
 	$this->t_body->set_block("tplBODY", "filter_matches", "filter_matches_block");
	if(isStaff($_SESSION['gid']) && $perms < 2)
	{
		$this->t_body->set_var("filter_matches_block", "");	
	}
	else
	{
		$query = "SELECT * FROM filter_category";
		$handle = $proxydb->dbDoQuery($query);
		while( $cat = $proxydb->dbGetNextArray($handle) )
		{
			$this->t_body->set_var("CATEGORY", $cat['name']);
			$this->t_body->set_var("CID", $cat['id']);
			//start with last week
			$query = "SELECT count, datausage FROM filter_report_week WHERE category = '".$cat['id']."' AND user = '".$uid."' AND week = '$week'"; 	
			$proxydb->dbDoQuery($query);
			$filter = $proxydb->dbGetNextArray();
			if($filter['count'] == "")
			{
				$this->t_body->set_var("WEEK_HITS", "0"); 
				$this->t_body->set_var("WEEK_DATASIZE", ""); 
				$this->t_body->set_var("VIEW", ""); 
			}
			else
			{
				$this->t_body->set_var("WEEK_HITS", $filter['count']); 
				$this->t_body->set_var("WEEK_DATASIZE", bytesToString($filter['datausage'])); 
				$this->t_body->set_var("VIEW", "<a href=\"proxy_log.php?uid=".$uid."&week=$week&cid=".$cat['id']."\">view</a>"); 
			}
			//now get for the entire year
			$query = "SELECT SUM(count) as totalcount, SUM(datausage) as totalusage FROM filter_report_week 
					WHERE category = '".$cat['id']."' AND user = '".$uid."'"; 	
			$proxydb->dbDoQuery($query);
			$filter = $proxydb->dbGetNextArray();
			if($filter['totalcount'] == "")
			{
				$this->t_body->set_var("TOTAL_HITS", "0"); 
				$this->t_body->set_var("TOTAL_DATASIZE", ""); 
			}
			else
			{
				$this->t_body->set_var("TOTAL_HITS", $filter['totalcount']); 
				$this->t_body->set_var("TOTAL_DATASIZE", bytesToString($filter['totalusage'])); 
			}

			$this->t_body->parse("category_block", "category", true); 
		}
		$this->t_body->parse("filter_matches_block", "filter_matches");
	}

	//search box
	$this->t_body->set_block("tplBODY", "search_row", "search_row_block");
	if($perms < 2)
	{
		$this->t_body->set_var("search_row_block","");
	}
	else	
	{

		include("../../include/html-time.php");

		$this->t_body->set_var("BEGIN_DATE", createDateList(date("j"), "bdate")); 
		$this->t_body->set_var("BEGIN_MONTH", createMonthList(date("n")-1, "bmonth")); 
		$this->t_body->set_var("END_DATE", createDateList(date("j"), "edate")); 
		$this->t_body->set_var("END_MONTH", createMonthList(date("n"), "emonth")); 
		
		$this->t_body->parse("search_row_block", "search_row");
	}
}

$this->t_body->set_var("WEEK", $week);

$this->t_body->pparse("finaloutput", "tplBODY");
}
}
$page = new ProxyUsage;
?>
