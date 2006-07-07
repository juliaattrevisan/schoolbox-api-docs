<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays modDet allows staff to add or modify a detention for an MGS student to the detention database 

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("../../include/html-time.php");
require_once("../../include/html-parse.php");
require_once("detNav.php");
require_once("detperm.php");
require_once("studSearch.php");
require_once("sql/modDet.php");
require_once("sql/modDate.php");
require_once("sql/modOffence.php");

class modDet
{
	function modDet()
	{
	}

	function displayModDet($ID, $studName = "")
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/detention/modDet.htm");
		
		$nav = new detNav();
		
//		$this->t_body->set_block("tplBODY", "upgrade_button", "upgrade_sect");
		$this->t_body->set_var("TITLE", "Detention System - Issuing"); 
		$this->t_body->set_var("DETNAV", $nav->getNav());
		
		$hidden = "";
		if ($studName){
			//if adding detention
			//set upgrade button to be hidden
			$this->t_body->set_var("upgrade_sect", "");
			$this->t_body->set_var("HEADING", "Add Detention");
			$this->t_body->set_var("TEXT", 1);
			$this->t_body->set_var("STUDENT", $studName);
			$this->t_body->set_var("WORKSET", "");
			$this->t_body->set_var("HISTORY", "");
			$hidden.= "<input type=\"hidden\" name=\"staffID\" value=\"".$_SESSION['id']."\">";
			$hidden.= "<input type=\"hidden\" name=\"studID\" value=\"$ID\">";
		}
		else {
			//if modifying detention
			//set upgrade button to be viewed
//			$this->t_body->parse("upgrade_sect", "upgrade_button", 1);
			$this->t_body->set_var("HEADING", "Modify Detention");
			$this->t_body->set_var("TEXT", 0);
			$myDet = new det($ID);
			$details = $myDet->getDet();
			$this->t_body->set_var("WORKSET", $details[0]['workset']);
			$this->t_body->set_var("HISTORY", $details[0]['history']);
			$this->t_body->set_var("STUDENT", $details[0]['studfname']." ".$details[0]['studlname']);
			$hidden.= "<input type=\"hidden\" name=\"staffID\" value=\"".$details[0]['staffID']."\">";
			$hidden.= "<input type=\"hidden\" name=\"studID\" value=\"".$details[0]['studID']."\">";
			$hidden.= "<input id=\"detID\" type=\"hidden\" name=\"detID\" value=\"".$details[0]['id']."\">";
		}	
		//allow for any 'type' of date clientside by providing all
		//create html select with dates
		$date1str = "<option value=\"\">&nbsp;</option>";
		$date2str = "<option value=\"\">&nbsp;</option>";
		$date3str = "<option value=\"\">&nbsp;</option>";
		$selected = "";
		$myDate = new date;

		// Edited by Mark Parncutt on 18 Jan 2005
		// Prefects should only be able to give green cards.  This <select> was previously
		// hardcoded in the template.
		
		// Edited by Robert Skillington	Jan 2006
		// Marks code kinda broke the add detention query.  Fixed
		global $permType;
		$this->t_body->set_var("PERMTYPE", $permType);
		if ($permType == 1) {
		/*	$detselect = '<input type="hidden" name="type" id="type" value="G">Green'."\r\n";
			$detselect .= '<script type="text/javascript">'."\r\n";
			$detselect .= 'showRow()'."\r\n";
			$detselect .= '</script>'."\r\n";

			$detselect = "\r\n";*/
			foreach (array('G' => 'Green') as $key => $val) {
				if ($key == $details[0]['type']) {
		        	$detselect .= '<option selected value="'.$key.'">'.$val.'</option>'."\r\n";
				} else {
		        	$detselect .= '<option value="'.$key.'">'.$val.'</option>'."\r\n";
				}					
			}
	        $detselect .= "\r\n";
		} else {
			$detselect = "\r\n";
			foreach (array('G' => 'Green','Y' => 'Yellow','R' => 'Red') as $key => $val) {
				if ($key == $details[0]['type']) {
		        	$detselect .= '<option selected value="'.$key.'">'.$val.'</option>'."\r\n";
				} else {
		        	$detselect .= '<option value="'.$key.'">'.$val.'</option>'."\r\n";
				}					
			}
	        $detselect .= "\r\n";
		}

		$this->t_body->set_var("SELECT_DET_TYPE", $detselect);

		$datelist = $myDate->getDateCurr();
		if ($datelist){
		  foreach ($datelist as $date){
			if (!$studName){
				if ($date['id'] == $details[0]['dateID'])
					$selected = "selected";
			}
			if ($date['type'] == 'G')
				$date1str.= "<option value=\"".$date['id']."\" $selected>".$date['detdate']."</option>";
			if ($date['type'] == 'Y')
				$date2str.= "<option value=\"".$date['id']."\" $selected>".$date['detdate']."</option>";
			if ($date['type'] == 'R')
				$date3str.= "<option value=\"".$date['id']."\" $selected>".$date['detdate']."</option>";
			$selected = "";
		  }
		}
		else
			error(47);

		$this->t_body->set_var("DATE1", $date1str);
		if ($permType == 1) {
			$this->t_body->set_var("DATE2", "");
			$this->t_body->set_var("DATE3", "");
		} else {
			$this->t_body->set_var("DATE2", $date2str);
			$this->t_body->set_var("DATE3", $date3str);
		}
		$this->t_body->set_var("HIDDEN", $hidden);

		//create html select with offences
		$myOff = new offence;
		$offs = $myOff->getOffence();
		$offenceList[""] = "";
		foreach ($offs as $offence){
			$key = $offence['id'];
			$offenceList[$key] = $offence['description'];
		}
		$offenceHTMLlist = $this->createHTMLselect($offenceList, $details[0]['offid'], "offence");
		$this->t_body->set_var("OFFENCE", $offenceHTMLlist);

		$this->t_body->set_var("ACTION", "<a href=\"admin.php?off=1\"><b>Offence Admin</b></a>");
		include_once("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function createHTMLselect($optarr, $id, $valname){
		$str = "<select name=\"$valname\" id=\"$valname\" class=\"formfield\">";
		foreach ($optarr as $key => $value){
			if ($key == $id){
				$str .= "<option value=\"".$key."\" selected>$value</option>";
			}
			else {
				$str .= "<option value=\"".$key."\">$value</option>";
			}
		}	
		$str .= "</select>";
		return $str;
	}
}
//access to staff and prefects
$permType = permType();
if (isStaff($_SESSION['gid']) || $permType == 1)
{
  //if det form complete, add or update detention
  if (	isset($_POST['studID']) && $_POST['studID'] && 
	isset($_POST['type']) && $_POST['type'] && 
	isset($_POST['staffID']) && $_POST['staffID'] &&
	isset($_POST['offence']) && $_POST['offence'])
  { 

	switch($_POST['type']){
		case 'G':
			$detdate = $_POST['detdate1'];
			break;
		case 'Y':
			$detdate = $_POST['detdate2'];
			break;
		case 'R':
			$detdate = $_POST['detdate3'];
			break;
		default:
			print "Error: bad data";
			break;
	}

	if(!$detdate)
		print "Error: No detention set";
		

	$page = new det($_POST['detID']);
	if (isset($_POST['workset']) && $_POST['workset']){
		if ($_POST['detID'] && isset($_POST['detID'])) 
			$page->updateDet($_POST['detID'], $detdate, $_POST['studID'], $_POST['staffID'], $_POST['offence'], $_POST['type'], $_POST['history'], $_POST['upgradeval'], $_POST['workset']);
		else
			$page->addDet($detdate, $_POST['studID'], $_POST['staffID'], $_POST['offence'], $_POST['type'], $_POST['history'], $_POST['workset']);
	}
	else { 
		if ($_POST['detID'] && isset($_POST['detID']))
			$page->updateDet($_POST['detID'], $detdate, $_POST['studID'], $_POST['staffID'], $_POST['offence'], $_POST['type'], $_POST['history'], $_POST['upgradeval']); 
		else
			$page->addDet($detdate, $_POST['studID'], $_POST['staffID'], $_POST['offence'], $_POST['type'], $_POST['history']);
	}
	//if modifying det
	if ($_POST['detID']){
		header("Location: /modules/detention/");
	}
	//if adding det take back to search page
	else {
		$search = new detSearch;
		$search->displayFindStud();
	}
  }
  //if modifying already existing detention
  else if (isset($_GET['id']) && $_GET['id'])
  {
	$page = new modDet();
	$page->displayModDet($_GET['id']);
  }
  //if searched for user
  else if(isset($_GET['studID']) && $_GET['studID'] &&
	isset($_GET['studName']) && $_GET['studName']){ 
	$page = new modDet();
	$page->displayModDet($_GET['studID'], $_GET['studName']);
  }
  //display list of students from search
  else if((isset($_GET['name']) && $_GET['name']) || (isset($_GET['yrlvl']) && $_GET['yrlvl']))
  {
	$search = new detSearch;
        $students = $search->searchStud($_GET['name'], $_GET['yrlvl']);
        //display list of found students
        $search->displayStudList($students);
  }
  //display search student form
  else {
	$search = new detSearch;
	$search->displayFindStud();
  }
}
//if not staff or prefect with permission, give error
else
	error(14); 
?>
