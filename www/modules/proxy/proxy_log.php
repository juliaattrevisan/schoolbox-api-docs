<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//outputs users history
require("../../include/session.php");
require("../../include/include.php");
require("common.php");
require("../../include/permission.php");

//start by checking perms
$perms = perm(0, 4);

//if does not have 2||3 and not requesting info on themselves they do not have permission
if(($perms != 2 && $perms !=3) && ($_SESSION['id'] != $_REQUEST['uid']))
	error(19); 

$currentYear = date("Y");


if($_REQUEST['uid'] && ($_REQUEST['bdate'] && $_REQUEST['bmonth'] && $_REQUEST['edate'] && $_REQUEST['emonth']) || $_REQUEST['week'])
{
	$proxyLog = new ProxyLog();
}
else
	error(0);

class ProxyLog {

function ProxyLog() {
	$db = new dbManager();
	$db->dbConnect();

	global $months;

	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "modules/proxy/history_list.htm");
	include("../../include/page.php");

	$this->t_body->set_var("TITLE", HEADING." - Proxy URL History");
	$this->t_body->set_block("tplBODY", "url", "url_block");
	

	//first get user information including fullname and username
	$query = "SELECT firstname, lastname, username FROM user WHERE id = '".$_REQUEST['uid']."'";
	$db->dbDoQuery($query);
	$user = $db->dbGetNextArray();


	$this->t_body->set_var("NAME", $user['firstname']." ".$user['lastname']);

	$db->dbDoQuery("use proxy");

	//make begin/end time in unix format
	if($_REQUEST['week'])
	{
		$begintime = beginDateOfWeek(date("Y"), $_REQUEST['week']);
		$endtime = $begintime + 604800;	
	}
	else
	{
		$begintime = mktime(0,0,0,$_REQUEST['bmonth'],$_REQUEST['bdate'], date("Y"));
		$endtime = mktime(23,59,59,$_REQUEST['emonth'],$_REQUEST['edate'], date("Y"));
	}

	$beginmonth = date("n", $begintime);
	$endmonth = date("n", $endtime);
	$begindate = date("j", $begintime);
	$enddate = date("j", $endtime);

	if($_REQUEST['cid'])
		$keyword = wordListQuery($_REQUEST['cid'], &$db);
	else if($_REQUEST['keyword'])
		$keyword = " url like '%".$_REQUEST['keyword']."%'";

	$totalrows = 0;
	$resultsperpage = 100;

	if($_REQUEST['page'])
		$page = $_REQUEST['page'];
	else
		$page = 1;

	$begin = (($page - 1) * $resultsperpage) + 1;

	$end = $page * $resultsperpage;

	//haack at something that didnt work.. left here cause its done now and it does no harm
	//tried to get the amount of results and the time location so we could do a subset query
	if($_REQUEST['results'])
		$results = $_REQUEST['results'];
	else
	{
		$results = 0;
		$firsttime = 1;
	}
	//if the current result 
	$cur_results = 0;
	
	//flag to tell if any urls were output
	$outputflag = 0;
	

	for($i = $beginmonth; $i <= $endmonth; $i++)	
	{
		$query = "SELECT DATE_FORMAT(time, '%e/%c %l:%i %p') as outtime, ip, url, UNIX_TIMESTAMP(time) as utime FROM ".$months[$i]." 
			WHERE user like '".$user['username']."' AND time > FROM_UNIXTIME('$begintime') AND time < FROM_UNIXTIME('$endtime')";	
		if($keyword)
			$query .= "AND ( $keyword )";
		
		$query .= " ORDER BY time ASC";
		$db->dbDoQuery($query);

		while( $url = $db->dbGetNextArray() )
		{
			if($cur_results >= $begin && $cur_results <= $end)
			{
				$this->t_body->set_var("TIME", $url['outtime']);
				$this->t_body->set_var("LOCATION", $url['ip']);
				$this->t_body->set_var("URL",$url['url'] );
				$this->t_body->parse("url_block", "url", true);
				$outputflag = 1;
			}

			$cur_results++;			

			//if this is the first time through lets get a tally of results
			if($firsttime)
				$results++;				
		}

	}
	
	//no urls output
	if($outputflag == 0)
		$this->t_body->set_var("url_block", "");

	//setup page links
	if($page == 1)
		$lastpage = "";
	else
	{
		$lastpage = "<a href=\"proxy_log.php?uid=".$_REQUEST['uid']."&
				bdate=$begindate&bmonth=$beginmonth&
				edate=$enddate&emonth=$endmonth&results=$results&";
		if($_REQUEST['cid'])
			$lastpage .= "cid=".$_REQUEST['cid']."&";
		else if($_REQUEST['keyword'])
			$lastpage .= "keyword=".$_REQUEST['keyword']."&";

		$lastpage .= "page=".($page-1)."\">last page</a>";
	}	 
	if($end >= $results)
		$nextpage = "";
	else
	{
		$nextpage = " <a href=\"proxy_log.php?uid=".$_REQUEST['uid']."&
				bdate=$begindate&bmonth=$beginmonth&
				edate=$enddate&emonth=$endmonth&results=$results&";
		if($_REQUEST['cid'])
			$nextpage .= "cid=".$_REQUEST['cid']."&";
		else if($_REQUEST['keyword'])
			$nextpage .= "keyword=".$_REQUEST['keyword']."&";

		$nextpage .= "page=".($page+1)."\">next page</a>";
 	}

	$totalpages = ceil($results/$resultsperpage);
	$pagelist = "";
	
	if($totalpages > 1)
	{
		for($x = 1; $x <= $totalpages; $x++)
		{
			if($x == $page)
				$pagelist .= " $x";
			else
			{
				$pagelist .= " <a href=\"proxy_log.php?uid=".$_REQUEST['uid']."&
					bdate=$begindate&bmonth=$beginmonth&
					edate=$enddate&emonth=$endmonth&results=$results&";
				if($_REQUEST['cid'])
					$pagelist .= "cid=".$_REQUEST['cid']."&";
				else if($_REQUEST['keyword'])
					$pagelist .= "keyword=".$_REQUEST['keyword']."&";

				$pagelist .= "page=".$x."\">$x</a>";
			}
		}
	}
	else
		$pagelist = "";

	$this->t_body->set_var("LASTPAGE", $lastpage );
	$this->t_body->set_var("PAGELIST", $pagelist);
	$this->t_body->set_var("NEXTPAGE", $nextpage);

	$this->t_body->set_var("TOTAL", $results);
	$this->t_body->set_var("BEGIN", $begin);
	if($results < $end)
		$this->t_body->set_var("END", $results);
	else	
		$this->t_body->set_var("END", $end);
	$this->t_body->set_var("PAGE", $page." of ".$totalpages);

	$this->t_body->pparse("finaloutput", "tplBODY");
}
}

?>
