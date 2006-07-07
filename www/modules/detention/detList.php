<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//allows for reporting using a search and filter on detentions contained in the database

require_once("../../include/html-time.php");
require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("detperm.php");
require_once("studSearch.php");
require_once("sql/modDet.php");
require_once("sql/modOffence.php");

// Multiple pages added 19 Jan 2005 by Mark Parncutt
// the value below defines how many dets should be shown on each page.
define('DETENTIONS_PER_PAGE',25);

class detList
{
	function detList()
	{
		if (isset($_GET['page'])) {
			$_POST = $_GET;
			unset($_POST['page']);
		}
		$this->filter = array( 	'status' => 	$_POST['filter_status'], 
					'type' 	=> 	$_POST['filter_type'], 
					'order' => 	$_POST['filter_order'], 
					'where' => 	'', 
					'limit' => 	'',
					'house' => 	$_POST['filter_house'],
					'student' => 	$_POST['filter_student'],
					'staff' => 	$_POST['filter_staff'],
					'year' => 	$_POST['filter_year'],
					'offence' => 	$_POST['filter_offence'],
					'timeframe' =>	$_POST['timeframe'],
					'sday' =>	$_POST['sday'],
					'smth' =>	$_POST['smth'],
					'eday' =>	$_POST['eday'],
					'emth' =>	$_POST['emth'],
					'sdate' =>	date('Y-m-d', mktime(0,0,0,$_POST['smth'], $_POST['sday'], date("Y"))),
					'edate' =>	date('Y-m-d', mktime(0,0,0,$_POST['emth'], $_POST['eday'], date("Y")))
				);
	}

	function displayInitial($permType,$house = false)
	{
		if ($house)
			$this->filter['house'] = $house;   // M. Parncutt 27 Jan 2005
											   // HoH access to the history page is now allowed,
											   // but they can only see dets from their own house
											   // (request of I. Riddell and T. Scott)

		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/detention/detList.htm");
		$this->t_body->set_block("tplBODY", "det_list", "detlist_sect");		
		$this->t_body->set_block("det_list", "list", "list_sect");		
		$this->t_body->set_block("list", "history_row", "history_sect");
		$this->t_body->set_block("list", "workset_row", "workset_sect");
		$this->t_body->set_var("TITLE", "Detention System - Reporting");
		$nav = new detNav($permType);			// Mark Parncutt 20 Jan 2005
		$this->t_body->set_var("DETNAV", $nav->getNav());	// Mark Parncutt 20 Jan 2005
		$myDets = new det;

		// Multiple pages added 19 Jan 2005 by Mark Parncutt
		if (isset($_GET['page'])) {
			$start = ($_GET['page'] - 1) * DETENTIONS_PER_PAGE;
		} else {
			$start = 0;
		}
		$this->filter['limit'] = "$start,".DETENTIONS_PER_PAGE;
		// End M. Parncutt

		if ($this->filter['year'] > 0)
			$this->filter['year'] = $this->filter['year'] + 6;
		if ($this->filter['status'] == 1)
			$detList = $myDets->getDetAttended($this->filter);
		else if ($this->filter['status'] == 2)
			$detList = $myDets->getDetCancelled($this->filter);
		else if ($this->filter['status'] == 3)
			$detList = $myDets->getDetConfirmed($this->filter);
		else if ($this->filter['status'] == 4)
			$detList = $myDets->getDetNoShow($this->filter);
		else if ($this->filter['status'] == 5)
			$detList = $myDets->getDetUnconfirmed($this->filter);
		else if ($this->filter['status'] == 6)
			$detList = $myDets->getDetUpgraded($this->filter);
		else  
			$detList = $myDets->getDet($this->filter);
		$i = 0;
		if ($detList){
		foreach ($detList as $det){
			$i++;
			$this->t_body->set_var("NAME", $det['studfname']." ".$det['studlname']);
			$this->t_body->set_var("HOUSE", $det['house']);
			$this->t_body->set_var("STAFF", $det['stafffname']." ".$det['stafflname']);
			$this->t_body->set_var("YEAR", $det['year']);
			if ($det['type'] == 'G')
				$type = "Green";
			else if ($det['type'] == 'Y')
				$type = "Yellow";
			else if ($det['type'] == 'R')
				$type = "Red";
			$this->t_body->set_var("TYPE", $type);
			$this->t_body->set_var("OFFENCE", $det['offence']);
			$this->t_body->set_var("DATE", $det['detDate']);
			$this->t_body->set_var("STATUS", $det['status']);
			$this->t_body->set_var("UNIQID", $det['id']);
					$this->t_body->set_var("DETAILS", "<a border=\"0\" href=\"#\" onclick=\"hidetoggle('show".$det['id']."'); return false;\" onmouseover=\"window.status=''; return true;\" onmouseout=\"window.status=''; return true;\"><img id=\"".$det['id']."\" onclick=\"ImageRotate('".$det['id']."')\" border=\"0\" alt=\"Click for details\" src=\"/images/button_add.jpg\"></a>"); // Edited Jan.2006 Tyson Day
			if ($det['statid'] == 1){ 
				$action = "<a href=\"/modules/detention/viewDet.php?id=".$det['id']."&confirm=1&type=".$det['type']."\">Confirm</a> / <a href=\"/modules/detention/modifyDet.php?id=".$det['id']."\">Modify</a>";
			}
			else if ($det['statid'] == 2 || $det['statid'] == 3 || $det['statid'] == 4 || $det['statid'] == 7) {
				$action = "<a href=\"/modules/detention/modifyDet.php?id=".$det['id']."\">Modify</a> / <a href=\"/modules/detention/viewDet.php?id=".$det['id']."&delete=1\">Delete</a>";
			}
			else {
				$action = "";
			}
			$this->t_body->set_var("ACTION", $action);
			if ($det['history']){
				$this->t_body->set_var("HISTORY", $det['history']);
				$this->t_body->parse("history_sect", "history_row", false);
			}
			else {
				$this->t_body->set_var("history_sect", "");
			}
			if ($det['workset']){
				$this->t_body->set_var("WORKSET", $det['workset']);
				$this->t_body->parse("workset_sect", "workset_row", false);
			}
			else {
				$this->t_body->set_var("workset_sect", "");
			}
			$this->t_body->parse("list_sect", "list", true);
		}
		
		// Multiple pages added 19 Jan 2005 by Mark Parncutt
		$total_dets = $myDets->countDets();
		if (!is_int($pages = ($total_dets / DETENTIONS_PER_PAGE)))
			$pages = floor($pages) + 1;
		
		if (!isset($_GET['page'])) {
			$_GET['page'] = 1;
		}
		
		$pagelist = '';
		
		$querystring = '';
		foreach ($_POST as $key => $val) {
			$querystring .= "&amp;$key=$val";
		}
		
		for ($x=1;$x<$pages+1;$x++) {
			if ($x == $_GET['page']) {
				$pagelist .= "<strong>$x</strong> ";
			} else {
				$pagelist .= "<a href=\"detList.php?page=$x$querystring\">$x</a> ";
			}				
		}
		$this->t_body->set_var("PAGELIST", "Page: ".$pagelist);

		$showend = $start + DETENTIONS_PER_PAGE;
		if ($showend > $total_dets) {
			$showend = $total_dets;
		}

		$showstart = $start + 1;
		$this->t_body->set_var("RECORDS_SHOWN", "Showing $showstart - $showend of $total_dets total records");
		// End M. Parncutt

		}
		else {
			$this->t_body->set_var("PAGELIST", "&nbsp;");
			$this->t_body->set_var("RECORDS_SHOWN", "No detentions were found which match your filter request.");
			$this->t_body->set_var("list_sect", "");
		}

		if ($house) {
			$houses = $myDets->getHouseNames($house);
		} else {
			$houses = $myDets->getHouseNames();
			$houses[0] = "All";
		}
		$this->t_body->set_var("HOUSE_SEL", $this->createHTMLselect($houses, $this->filter['house'], "filter_house"));

		//set offence filter selection box
		$myOff = new offence;
		$offs = $myOff->getOffence();
		foreach ($offs as $offence){
                        $key = $offence['id'];
                        $offenceList[$key] = $offence['description'];
                }
		$offenceList[0] = "All";
		$this->t_body->set_var("OFF_SEL", $this->createHTMLselect($offenceList, $this->filter['offence'], "filter_offence"));	

		//set time frame selection box
		for ($i = 0; $i < 5; $i++){
			if ($this->filter['timeframe'] == $i)
				$this->t_body->set_var("TF".$i, "selected");
			else
				$this->t_body->set_var("TF".$i, "");
		}
		//set date selection boxes
		$this->t_body->set_var("SDAY", createDateList($this->filter['sday'], "sday"));
		$this->t_body->set_var("EDAY", createDateList($this->filter['eday'], "eday"));
                $this->t_body->set_var("SMTH", createMonthList($this->filter['smth'], "smth"));
                $this->t_body->set_var("EMTH", createMonthList($this->filter['emth'], "emth"));

		//set year filter selection box
		if ($this->filter['year'] > 6)
			$this->filter['year'] = $this->filter['year'] - 6;
		for ($i = 0; $i < 7; $i++){
			if ($this->filter['year'] == $i)
				$this->t_body->set_var("Y".$i, "selected");
			else 
				$this->t_body->set_var("Y".$i, "");
		}
	 	//set status filter selection box
		for ($i = 0; $i < 8; $i++){
			if ($this->filter['status'] == $i)
				$this->t_body->set_var("S".$i, "selected");
			else
				$this->t_body->set_var("S".$i, "");
		}	
		//set type filter selection box
		for ($i = 0; $i < 5; $i++){
			if ($this->filter['type'] == $i)
				$this->t_body->set_var("T".$i, "selected");
			else
				$this->t_body->set_var("T".$i, "");
		}
		//set order selection box
		for ($i = 0; $i < 6; $i++){
			if ($this->filter['order'] == $i)
				$this->t_body->set_var("OB".$i, "selected");
			else
				$this->t_body->set_var("OB".$i, "");
		} 
		//set student value
		$this->t_body->set_var("STUDENT", $this->filter['student']);
		//set staff value
		$this->t_body->set_var("STAFF", $this->filter['staff']);

		$this->t_body->parse("detlist_sect", "det_list", true);
		include_once("../../include/page.php");
	 	$this->t_body->pparse("finaloutput", "tplBODY");	
	}

	function createHTMLselect($optarr, $id, $valname){
		$str = "<select name=\"$valname\" id=\"$valname\" class=\"formfield\">";
		foreach ($optarr as $key => $value){
			if ($key == $id){
				$str .= "<option value=\"".$key."\" selected>$value</option>";
			}
			else
				$str .= "<option value=\"".$key."\">$value</option>";
		}	
		$str .= "</select>";
		return $str;
	}
}
//if school steward or similar permissions
$permType = permType();
if ($permType == 4) {
  $page = new detList();
  $page->displayInitial($permType);
}

// Mark Parncutt 27 Jan 2005
// HoH can now access det history, but can only see dets for their own house.
else if ($permType == 3)
{
  $page = new detList();
  $page->displayInitial($permType,permHouse());
}
//if staff member or prefect show search/add page
else if (isStaff($_SESSION['gid']) || $permType == 1)
{
 	$myAdd = new detSearch;
        $myAdd->displayFindStud();
}
//if student and no permissions then error
else
	error(14); 
?>
