<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays viewDet templates and inputs form information to calendar class

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("../../include/html-parse.php");
require_once("detperm.php");
require_once("studSearch.php");
require_once("detNav.php");
require_once("sql/modDet.php");

class display
{
	function display($access)
	{
		$this->access = $access;
		$this->filter = array( 'status' => '', 'type' => '', 'order' => '', 'where' => '', 'house' => '');
		if ($this->access == 3)
			$this->filter['house'] = permHouse(); 
	}

	function viewDets()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/detention/viewDet.htm");

		$this->t_body->set_block("tplBODY", "hoh_auth", "hoh_auth_sect");
		$this->t_body->set_block("tplBODY", "hoh_header", "hoh_head");
		$this->t_body->set_block("tplBODY", "ss_auth", "ss_auth_sect");
		$this->t_body->set_block("tplBODY", "ss_header", "ss_head");
		$this->t_body->set_block("ss_auth", "ss_action", "ss_action_sect");
		$this->t_body->set_block("tplBODY", "confirmed", "confirmed_sect");	
		$this->t_body->set_block("tplBODY", "conf_header", "conf_head");	

		$this->t_body->set_var("TITLE", "Detention System - Current Detentions");

		$myDet = new det;
		//get all dets to display
		if ($this->access == 3 || $this->access == 4){
			$detsHoH = $myDet->getDetUnconfirmedHoH($this->filter);
			$detsSS = $myDet->getDetUnconfirmedSS($this->filter);
			$detsConf = $myDet->getDetConfirmed($this->filter);
			$nav = new detNav($this->access);                  // M. Parncutt
			$this->t_body->set_var("DETNAV", $nav->getNav());  // M. Parncutt
			if ($this->access == 4) {
				$this->t_body->parse("ss_action_sect", "ss_action", 1);
			}
		 	else {
				$this->t_body->set_var("ss_action_sect", "<td width=\"15%\" align=\"right\">&nbsp;</td>");
			}
		}
		else
			error(19);

	if ($detsHoH){
	  $last = 0;
	  foreach ($detsHoH as $HoH){
		if ($last['id'] == $HoH['id']){ 
			continue;
		}
		$uniqid = md5(uniqid(rand(),true));
		$this->t_body->set_var("DETNAV", $nav->getNav());  // Tyson Day Jan. 2006
		$this->t_body->set_var("NAME", $HoH['studfname']." ".$HoH['studlname']);
		$this->t_body->set_var("YEAR", $HoH['year']);
		$this->t_body->set_var("HOUSE", $HoH['house']);
		$this->t_body->set_var("TYPE", $HoH['type']);
		$this->t_body->set_var("DATE", $HoH['detDate']);
		$this->t_body->set_var("OFFENCE", $HoH['offence']);
		$this->t_body->set_var("ID", $HoH['id']);
		$this->t_body->set_var("DETAILS", "<a border=\"0\" href=\"#\" onclick=\"hidetoggle('show".$uniqid."'); return false;\" onmouseover=\"window.status=''; return true;\" onmouseout=\"window.status=''; return true;\"><img id=\"".$HoH['id']."\" onclick=\"ImageRotate('".$HoH['id']."')\" border=\"0\" alt=\"Click for details\" src=\"/images/button_add.jpg\"></a>"); // Edited Jan.2006 Tyson Day
		$this->t_body->set_var("UNIQID", $uniqid);
		$this->t_body->set_var("WORKSET", $HoH['workset']);
		$this->t_body->set_var("STAFF", $HoH['stafffname']." ".$HoH['stafflname']);
		$this->t_body->set_var("HISTORY", $HoH['history']);
		$this->t_body->parse("hoh_auth_sect", "hoh_auth", true);

		$last = $HoH;
	  }
	  $this->t_body->parse("hoh_head", "hoh_header", true);
	}
	else {
		$this->t_body->set_var("hoh_head", "");
		$this->t_body->set_var("hoh_auth_sect", "<tr><td width='5%'></td><td> - No detentions in this category - </td></tr>");
	}

	if ($detsSS) {
	  $last = 0;
	  foreach ($detsSS as $SS){
		if ($last['id'] == $SS['id']){ 
			continue;
		}
		if ($SS['dup'] && $SS['dupstat'] != 8 && $SS['dupstat'] != 5 && $SS['dupstat'] != 6) 
			$this->t_body->set_var("NAME", "<font color=\"#FF0000\">".$SS['studfname']." ".$SS['studlname']."</font>");
		else
			$this->t_body->set_var("NAME", $SS['studfname']." ".$SS['studlname']);
		$uniqid = md5(uniqid(rand(),true));
		$this->t_body->set_var("YEAR", $SS['year']);
		$this->t_body->set_var("HOUSE", $SS['house']);
		$this->t_body->set_var("TYPE", $SS['type']);
		$this->t_body->set_var("DATE", $SS['detDate']);
		$this->t_body->set_var("OFFENCE", $SS['offence']);
		$this->t_body->set_var("ID", $SS['id']);
		$this->t_body->set_var("DETAILS", "<a border=\"0\" href=\"#\" onclick=\"hidetoggle('show".$uniqid."'); return false;\" onmouseover=\"window.status=''; return true;\" onmouseout=\"window.status=''; return true;\"><img id=\"".$SS['id']."\" onclick=\"ImageRotate('".$SS['id']."')\" border=\"0\" alt=\"Click for details\" src=\"/images/button_add.jpg\"></a>"); // Edited Jan.2006 Tyson Day
		$this->t_body->set_var("UNIQID", $uniqid);
		$this->t_body->set_var("WORKSET", $SS['workset']);
		$this->t_body->set_var("STAFF", $SS['stafffname']." ".$SS['stafflname']);
		$this->t_body->set_var("HISTORY", $SS['history']);
		$this->t_body->parse("ss_auth_sect", "ss_auth", true);
		$last = $SS;
	  }
	  $this->t_body->parse("ss_head", "ss_header", true);
	}
	else {
		$this->t_body->set_var("ss_head", "");
		$this->t_body->set_var("ss_auth_sect", "<tr><td width='5%'></td><td> - No detentions in this category - </td></tr>");
	}

	if ($detsConf) {
	  $last = 0;
	  foreach ($detsConf as $Conf){
		if ($last['id'] == $Conf['id']){ 
			continue;
		}
		if ($Conf['dup'] && $Conf['dupstat'] != 8 && $Conf['dupstat'] != 5 && $Conf['dupstat'] != 6)
			$this->t_body->set_var("NAME", "<font color=\"#FF0000\">".$Conf['studfname']." ".$Conf['studlname']."</font>");
		else
			$this->t_body->set_var("NAME", $Conf['studfname']." ".$Conf['studlname']);
		$this->t_body->set_var("YEAR", $Conf['year']);
		$this->t_body->set_var("HOUSE", $Conf['house']);
		$uniqid = md5(uniqid(rand(),true));
		$this->t_body->set_var("TYPE", $Conf['type']);
		$this->t_body->set_var("DATE", $Conf['detDate']);
		$this->t_body->set_var("CREATED", $Conf['detCreated']);
		$this->t_body->set_var("OFFENCE", $Conf['offence']);
		$this->t_body->set_var("ID", $Conf['id']);
		$this->t_body->set_var("DETAILS", "<a border=\"0\" href=\"#\" onclick=\"hidetoggle('show".$uniqid."'); return false;\" onmouseover=\"window.status=''; return true;\" onmouseout=\"window.status=''; return true;\"><img id=\"".$Conf['id']."\" onclick=\"ImageRotate('".$Conf['id']."')\" border=\"0\" alt=\"Click for details\" src=\"/images/button_add.jpg\"></a>"); // Edited Jan.2006 Tyson Day
		$this->t_body->set_var("UNIQID", $uniqid);
		$this->t_body->set_var("WORKSET", $Conf['workset']);
		$this->t_body->set_var("STAFF", $Conf['stafffname']." ".$Conf['stafflname']);
		$this->t_body->set_var("HISTORY", $Conf['history']);
		$this->t_body->parse("confirmed_sect", "confirmed", true);
		$last = $Conf;
	  }
	  $this->t_body->parse("conf_head", "conf_header", true);
	}
	else {
		$this->t_body->set_var("conf_head", "");
		$this->t_body->set_var("confirmed_sect", "<tr><td width='5%'></td><td> - No detentions in this category - </td></tr>");
	}

		include_once("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function createHTMLselect($options, $name)
	{
		$str = "<select name=\"$name\" id=\"$name\" class=\"formfield\">";
		foreach ($options as $key => $value){
			$str .= "<option id=\"\" value=\"".$key."\">$value</option>";
		}	
		$str .= "</select>";
		return $str;
	}
}
//if staff member and permissions are set as Head of House or School Steward level   
$permType = permType();
if (isStaff($_SESSION['gid']) && $permType > 2){
  if (!empty($_GET['id']) && !empty($_GET['confirm'])) 
  {
	$myDet = new det($_GET['id']);
	if (!empty($_GET['type']))
		$myDet->confirmDet($_GET['type'],$permType);

	$myView = new display($permType);
	$myView->viewDets();
  }
  else if ($_GET['id'] && isset($_GET['id']) && $_GET['delete'] && isset($_GET['delete']))
  {
	$myDet = new det($_GET['id']);
	$myDet->deleteDet();
	$myView = new display($permType);
	$myView->viewDets();
  }
  //show view Det main page with appropriate permissions
  else 
  {
	$myView = new display($permType);
	$myView->viewDets();
  }
}
//if staff member or prefect with permissions show search/add page
else if (isStaff($_SESSION['gid']) || $permType == 1)
{
	$myAdd = new detSearch;
	$myAdd->displayFindStud();
}
//if student and no permissions give error
else {
	error(19);
}
?>
