<?php

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/staffonly.php");

require_once("news.php");

class pageTpl
{

function pageTpl()
{
    $this->t_body = new Template(TPL_directory);
    $this->t_body->set_file("tplBODY", "news/modnews.htm");

    $this->set_var("TITLE", HEADING." - Submit News");
    
	
    include_once("../include/page.php");
    
    $this->set_var("FORMSUB_URL", "/news/donews.php");
    $this->t_body->set_block("tplBODY", "group_selection", "group_selection_block");
    $this->t_body->set_block("tplBODY", "campus_selection", "campus_selection_block");
	$this->t_body->set_block("tplBODY", "campus_row", "campus_row_block");
	$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
}

function setList($name, $array, $selected){
	$this->set_var($name."_TOTAL", count($array)); 
	foreach($array as $item) {
		if ($item['name'] != "") {
			$this->set_var($name."_NAME", $item['name']);
			$this->set_var($name."_ID", $item['id']);
			if(is_array($selected))
			{
				if (in_array($item['id'], $selected))
					$this->set_var("SELECTED_".$name, "selected");
				else
					$this->set_var("SELECTED_".$name, "");
			}
			else
			{
				if ($item['id'] == $selected)
					$this->set_var("SELECTED_".$name, "selected");
				else
					$this->set_var("SELECTED_".$name, "");
			}
			
			$this->t_body->parse(strtolower($name)."_selection_block", strtolower($name)."_selection", true);
		}
	}
}

function putEmpty($groupArray, $userInfo, $campusArray) {
	$this->set_var("ACTION", "Submit");
	$this->setList("GROUP", $groupArray, array());
	
	if(count($campusArray) < 2){  //dont show campus row when only 1
		$this->t_body->set_var("campus_row_block", "");
	}
	else{
		$this->setList("CAMPUS", $campusArray, array());
		$this->t_body->parse("campus_row_block", "campus_row");
	}
	
	$this->set_var("ID", "0");
	$this->set_var("HEADING", "");
	$this->set_var("LEAVE_OPTION", "");
	$this->set_var("DEFAULT_TIME_SELECTED", "selected");
	$this->set_var("disp_main", "");
	$this->set_var("BODY", "");
	$this->set_var("delete_button_block", "");
}

function putExisting($newsItem, $groupArray, $campusArray) {

	$this->set_var("ACTION", "Update");
	$this->setList("GROUP", $groupArray, $newsItem['item_group']);
	
	if(count($campusArray) < 2){  //dont show campus row when only 1
		$this->t_body->set_var("campus_row_block", "");
	}
	else{
		$this->setList("CAMPUS", $campusArray, $newsItem['item_campus']);
		$this->t_body->parse("campus_row_block", "campus_row");
	}

	$this->set_var("ID", $newsItem['item_id']);
	$this->set_var("HEADING", $newsItem['item_heading']);
	$this->set_var("LEAVE_OPTION", "<option value=\"0\" selected>Keep existing</option>");
	$this->set_var("DEFAULT_TIME_SELECTED", "");
	$this->set_var("BODY", $newsItem['item_body']);
	$this->t_body->parse("delete_button_block", "delete_button");
}


function set_var($varName, $varContents)
{
    $this->t_body->set_var($varName, $varContents);
}

function showPage()
{
    $this->t_body->pparse("finaloutput", "tplBODY");
}
}

function grabGroups() {
	$dbConnection = new dbManager;
	$SQL_QUERY = "SELECT id, name FROM groups";
	$dbConnection->dbDoQuery($SQL_QUERY);
	//$currentCategory = 0;
	$i = 0;
	while ($thisGroup = $dbConnection->dbGetNextArray()) {
		$allGroups[$i]['name'] = $thisGroup['name'];
		$allGroups[$i++]['id'] = $thisGroup['id'];
	}
	return $allGroups;
}
function grabCampus() {
	$dbConnection = new dbManager;
	$SQL_QUERY = "SELECT id, name FROM luCampus WHERE id != ".ALLCAMPUS;
	$dbConnection->dbDoQuery($SQL_QUERY);
	$i = 0;
	while ($thisCampus = $dbConnection->dbGetNextArray()) {
		$allCampus[$i]['name'] = $thisCampus['name'];
		$allCampus[$i++]['id'] = $thisCampus['id'];
	}
	return $allCampus;
}



$myPage = new pageTpl();
if (isset($_GET['id']))
{
	$myNews = new news();

	//check security perms to edit news
	if($myNews->getOwner($_GET['id']) == $_SESSION['id'] || $_SESSION['su'] == 1)
	{
		$myPage->putExisting($myNews->getOneNews($_GET['id']), grabGroups(),  grabCampus());
	}
	else
		error(23);
} 
else 
{
	$myPage->putEmpty(grabGroups(), $myInfo, grabCampus());
}
$myPage->showPage();

?>
