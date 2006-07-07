<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays modDet allows staff to add or modify a detention for an MGS student to the detention database 

require_once("../../include/html-time.php");
require_once("../../include/session.php");
require_once("../../include/permission.php");
require_once("../../include/include.php");
require_once("sql/modDate.php");
require_once("sql/modOffence.php");
require_once("detNav.php");
require_once("detperm.php");
require_once("studSearch.php");

class admin
{
	function admin()
	{
	}

	function displayDate($find = "", $add = "", $loc = "") 
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/detention/date.htm");
		$this->t_body->set_block("tplBODY", "moddate", "moddate_sect");		
		$this->t_body->set_block("tplBODY", "staff_select", "staff_select_sect");		

		$nav = new detNav(4);
		$this->t_body->set_var("DETNAV", $nav->getNav());	
		$adminnav = new detAdminNav();
		$this->t_body->set_var("DETADMINNAV", $adminnav->getAdminNav());	

		//set date to coming friday
 		$day = date("j", mktime(0,0,0,date("m"),(date("d")+(5-date("w"))),date("Y")));
                $mth = date("n", mktime(0,0,0,date("m"),(date("d")+(5-date("w"))),date("Y")));
		$this->t_body->set_var("NDAY", createDateList($day, "nday"));
		$this->t_body->set_var("NMTH", createMonthList($mth, "nmth"));
		$this->t_body->set_var("NHR", "<select name='nhr'><option value='01'>01</option><option value='02'>02</option><option value='03'>03</option><option value='04'>04</option><option value='05'>05</option><option value='06'>06</option><option value='07'>07</option><option value='08'>08</option><option value='09'>09</option><option value='10'>10</option><option value='11'>11</option><option value='12'>12</option></select>");
		$this->t_body->set_var("NMIN", "<select name='nmin'><option value='00'>00</option><option value='05'>05</option><option value='10'>10</option><option value='15'>15</option><option value='20'>20</option><option value='25'>25</option><option value='30'>30</option><option value='35'>35</option><option value='40'>40</option><option value='45'>45</option><option value='50'>50</option><option value='55'>55</option></select>");
		$this->perarr = array("AM" => "AM" , "PM" => "PM");
		$this->typearr = array("G" => "Green", "Y" => "Yellow", "R" => "Red");
		$this->t_body->set_var("NPER", $this->createHTMLselect($this->perarr, "PM", "nper"));
		$this->t_body->set_var("NTYPE", $this->createHTMLselect($this->typearr, "G", "ntype"));
	
		$myDate = new date();
		//set staff list based on user input
		if ($add){
			$myDate->addStaff($add);
		}
		if ($find){
			$stafflisting = $myDate->findStaff($find);
			if ($stafflisting){
				$this->t_body->set_var("STAFFSELECT", $this->createHTMLselect($stafflisting, "", "staffselect"));			
				$this->t_body->parse("staff_select_sect", "staff_select", true);
			}
			else {
				$this->t_body->set_var("STAFFSELECT", " - No Results Search Again - ");
				$this->t_body->parse("staff_select_sect", "staff_select", true);
			}
				
		}
		//set add staff list to null until search done 
		else {
			$this->t_body->set_var("staff_select_sect", "");
		}
		if ($loc){
			$myDate->addLocation($loc);
		}
		//get staff id's with perm = 2 or higher
		$listOfStaff = $myDate->getStaff();
		$listOfLocs = $myDate->getLocations();
		$this->t_body->set_var("NSTAFF", $this->createHTMLselect($listOfStaff, "", "nstaff"));
		$this->t_body->set_var("NLOCATION", $this->createHTMLselect($listOfLocs, "", "nlocation"));
		$this->t_body->set_var("TITLE", "Detention System Date Administration");
		$datelist = $myDate->getDateCurr();
		//create the list of future dates already in database
		if ($datelist){
			foreach ($datelist as $date){
				//make day list
				$this->t_body->set_var("DAY", $date['day'] );	
				//make month list
				$this->t_body->set_var("MTH", MakeTextMonth($date['mth']));	
				//make hr list
				$this->t_body->set_var("HR", $date['hr']);
				//make min list 
				$this->t_body->set_var("MIN", $date['min']);
				//make period list select
				$this->t_body->set_var("PER", $date['per']); 
				//set staff select
				$this->t_body->set_var("STAFF", $this->ReturnArrayValue($listOfStaff, $date['staff'], "staff[]")); 
				//set location select
				$this->t_body->set_var("LOCATION", $this->ReturnArrayValue($listOfLocs, $date['location'], "location[]"));
				$this->t_body->set_var("ID", $date['id']);

				//don't allow delete if detentions are associated with the detention date
				if ($myDate->dateUsed($date['id'])){
					$this->t_body->set_var("ALERT", " onClick=\"alert('This date has already been assigned to a detention. Modifications can be made however it cannot be deleted unless it is not being used.  School Steward must modify all detentions with this Detention Class assigned, select another Detention Date for each of them, then this Class may be deleted.'); return false;\"");
					$this->t_body->set_var("DELETE", "");
					//set type select - only give one option as detdate is being used therefore type shouldn't be able to be changed.
					if ($date['type'] == "G")
						$str = "<select name=\"type[]\" id=\"type[]\" class=\"formfield\"><option value=\"G\">Green</option></select>";
					else if ($date['type'] == "Y")
						$str = "<select name=\"type[]\" id=\"type[]\" class=\"formfield\"><option value=\"Y\">Yellow</option></select>";
					else if ($date['type'] == "R") 
						$str = "<select name=\"type[]\" id=\"type[]\" class=\"formfield\"><option value=\"R\">Red</option></select>";
					$this->t_body->set_var("TYPE", $str); 
				}
				else {
					$this->t_body->set_var("ALERT", "");
					$this->t_body->set_var("DELETE", "admin.php?id=".$date['id']."&datedelete=1");
					//set type select
					$this->t_body->set_var("TYPE", $this->ReturnArrayValue($this->typearr, $date['type'], "type[]"));
				}				 	
				$this->t_body->parse("moddate_sect", "moddate", true);
			}
		} 
		else
			$this->t_body->set_var("moddate_sect", "");

		$this->t_body->set_var("DATELISTTITLE", "Future Detention Dates");	
		$this->t_body->set_var("ACTION", "<a href=\"viewDet.php\"><b>View Detentions</b></a>&nbsp;/&nbsp;<a href=\"admin.php?off=1\"><b>Offence Administration</b></a>&nbsp;/&nbsp;<a href=\"detList.php\"><b>Reporting</b></a>");	

		include_once("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayOffence()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/detention/offence.htm");
		$this->t_body->set_block("tplBODY", "offence_list", "offence_sect");		

		$nav = new detNav(4);
		$this->t_body->set_var("DETNAV", $nav->getNav());	
		$adminnav = new detAdminNav();
		$this->t_body->set_var("DETADMINNAV", $adminnav->getAdminNav(true));	

		$this->t_body->set_var("TITLE", "Detention System Offence Administration");

		$myOff = new offence;
		$offenceList = $myOff->getOffence();
		
		foreach ($offenceList as $offence) {
			//skip no-show penalty and upgrade
			if ($offence['id'] < 3)
				continue;
			$this->t_body->set_var("OFFENCE", $offence['description']); 
			$this->t_body->set_var("DELETE", "href=\"admin.php?id=".$offence['id']."&offdelete=1\"");
			$this->t_body->set_var("OFFID", $offence['id']);
			$this->t_body->parse("offence_sect", "offence_list", true); 	
		}
		include_once("../../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function createHTMLselect($optarr, $id, $valname){
		if (empty($optarr)) return "";
		$str = "<select name=\"$valname\" id=\"$valname\" class=\"formfield\">";
		foreach ($optarr as $key => $value){
			if ($key == $id){
				$str .= "<option id=\"\" value=\"".$key."\" selected>$value</option>";
			}
			else
				$str .= "<option id=\"\" value=\"".$key."\">$value</option>";
		}	
		$str .= "</select>";
		return $str;
	}

	function ReturnArrayValue($optarr, $id, $valname){
		if (empty($optarr)) return "";
		foreach ($optarr as $key => $value){
			if ($key == $id){
				$str .= $value;
			}
			else
				$str .= "";
		}	
		return $str;
	}

}

	function MakeTextMonth($month)
	{
		$month = str_replace("01", "January", $month);
		$month = str_replace("02", "February", $month);
		$month = str_replace("03", "March", $month);
		$month = str_replace("04", "April", $month);
		$month = str_replace("05", "May", $month);
		$month = str_replace("06", "June", $month);
		$month = str_replace("07", "July", $month);
		$month = str_replace("08", "August", $month);
		$month = str_replace("09", "September", $month);
		$month = str_replace("10", "October", $month);
		$month = str_replace("11", "November", $month);
		$month = str_replace("12", "December", $month);
		return $month;
	}

if (permType() >= 4){
  $page = new admin();
	//if offence administration link was clicked
  if ($_GET['off'] == 1)
  {
	$page->displayOffence();
  }
	//if adding a date
  else if ($_POST['nday'] && isset($_POST['nday']) &&
	 $_POST['nmth'] && isset($_POST['nmth']) &&
	 $_POST['nhr'] && isset($_POST['nhr']) &&
	 $_POST['nmin'] && isset($_POST['nmth']) &&
	 $_POST['nper'] && isset($_POST['nper']) &&
	 $_POST['ntype'] && isset($_POST['ntype'])
	)
  {
	//add date
	$hour = $_POST['nhr'];
 	if ($_POST['nper'] == "PM" && $hour < 12) 
        	$hour += 12;
	$datetime = date("Y-m-d H:i:s" , mktime($hour, $_POST['nmin'], "00" , $_POST['nmth'], $_POST['nday'], date("Y") ));
	$myDate = new date;
	if ($myDate->addDate($datetime, $_POST['ntype'], $_POST['nstaff'], $_POST['nlocation']))
		$page->displayDate();		
	else
		print "could not add date";
  }
	//if modifying existing offences
  else if ($_POST['offence_edit'] && isset($_POST['offence_edit']))
  {
	//modify offences
	$i = 0;
	foreach($_POST['offence_edit'] as $offid => $val){
//		$off = $_POST['offence'][$id];
		$myOff = new offence($offid);
		$myOff->modOffence($val);
		$i++;
	}	
	$page->displayOffence();
  }
	//if adding a new offence
  else if ($_POST['offence'] && isset($_POST['offence']))
  {
	//add offence
	$myOff = new offence;
	$myOff->addOffence($_POST['offence']);
	$page->displayOffence();
  }
  	//if saving modifications to dates
  else if ($_POST['dateid'] && isset($_POST['dateid']))
  {
	//modify dates
	$i = 0;
	foreach ($_POST['dateid'] as $dateid){
		$day = $_POST['day'][$i];
	 	$mth = $_POST['mth'][$i];
	 	$hr = $_POST['hr'][$i];
	 	$min = $_POST['min'][$i];
	 	$per = $_POST['period'][$i];
	 	$type = $_POST['type'][$i];
	 	$staff = $_POST['staff'][$i];
	 	$location = $_POST['location'][$i];
 		if ($per == "PM" && $hr < 12) 
        		$hr += 12;
		$datetime = date("Y-m-d H:i:s" , mktime($hr, $min, "00" , $mth, $day, date("Y") ));
		$myDate = new date($dateid);
		$myDate->modDate($datetime, $type, $staff, $location);
		$i++;
	}
	$page->displayDate();		
  }
	//if deleting a detention class
  else if (!empty($_GET['datedelete']) && !empty($_GET['id']))
  {
	//delete date
	$myDate = new date($_GET['id']);
	if ($myDate->deleteDate())
		$page->displayDate(); 
  }
	//if deleting an offence
  else if ($_GET['offdelete'] && isset($_GET['offdelete']) &&
	 $_GET['id'] && isset($_GET['id']))
  {
	//delete offence
	$myOff = new offence($_GET['id']);
	if ($myOff->deleteOffence())
		$page->displayOffence(); 
  }
	//if adding staff for detention classes
  else if ($_POST['addstaff'] && isset($_POST['addstaff']))
  {
	$page->displayDate("", $_POST['staffselect'], $_POST['newloc']); 
  }
	//do add staff
  else if (!empty($_POST['staffselect']))
  {
	$page->displayDate("", $_POST['staffselect'], $_POST['newloc']); 
  }
  else 
  {
	$page->displayDate($_POST['findstaff'], "", $_POST['newloc']);
  }
}
//if staff member or prefect show search/add page
else if (isStaff($_SESSION['gid']) || permType() == 1)
{
 	$myAdd = new detSearch;
        $myAdd->displayFindStud();
}
//if student and no permissions then error
else
	error(14); 
?>
