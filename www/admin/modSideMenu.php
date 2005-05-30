<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//Displays structure of menu and allows modification of menu
require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("sideMenu.php");

class modSideMenu
{
	function modSideMenu()
	{
		$this->db = new dbManager;
	}

	function displaySideMenuItem($id)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modMenuItem.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify Menu Item");
		include_once("../include/page.php");	

		$menu = new sideMenu();
		
		$item = $menu->getMenuItem($id);

		$this->createMenuTree(0, "&nbsp; &nbsp;|&nbsp;");
		$this->t_body->set_var("MENUITEMLIST", $this->createMenuItemSelect($this->itemList, $item['parent']));

		$this->t_body->set_var("TEXT", htmlspecialchars($item['txt']));
		$this->t_body->set_var("URL", $item['url']);		
		$this->t_body->set_var("TARGETLIST", $this->createTargetList($item['target']));

		$campusList = $menu->getMenuItemCampusList($id);
		$groupList = $menu->getMenuItemGroupList($id);

		$this->t_body->set_var("CAMPUSLIST", $this->createCampusList($campusList));
		$this->t_body->set_var("GROUPLIST", $this->createGroupList($groupList));

		$this->t_body->set_var("IDFIELD", "<input type=\"hidden\" name=\"id\" value=\"".$item['id']."\">");		

		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function displayNewSideMenuItem($parent = 0)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modMenuItem.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add Menu Item");
		include_once("../include/page.php");	

		$this->createMenuTree(0, "&nbsp; &nbsp;|&nbsp;");
		$this->t_body->set_var("MENUITEMLIST", $this->createMenuItemSelect($this->itemList, $parent));

		$this->t_body->set_var("TEXT", "");
		$this->t_body->set_var("URL", "");		
		$this->t_body->set_var("TARGETLIST", $this->createTargetList());

		$this->t_body->set_var("CAMPUSLIST", $this->createCampusList());
		$this->t_body->set_var("GROUPLIST", $this->createGroupList());

		$this->t_body->set_var("IDFIELD", "");		
		
		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function displayMenuItemList()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/manageMenuItems.htm");
		$this->t_body->set_var("TITLE", HEADING." - Manage Menu Items");
		include_once("../include/page.php");

		$this->createMenuTree(0, "");
		$this->t_body->set_var("MENU_ITEM", $this->createMenuItemList($this->itemList));

		$this->t_body->pparse("finaloutput", "tplBODY");
	}


	function createMenuTree($parent, $indent)
	{
                $this->itemList[-1] = "";

                //query database for all categories that user has permission to see (redundant doesnt work properly)
		$catQuery = "SELECT txt, id FROM sideMenu WHERE parent = '".$parent."' ORDER BY sequence, txt";

                $result = $this->db->dbDoQuery($catQuery);

                if( $this->db->dbCountRows() > 0 )
                {
                        while($line = $this->db->dbGetNextArray($result) )
                        {
                                $thisRow['id'] = $line['id'];
                                $thisRow['indent'] = $indent;
				$thisRow['txt'] = $line['txt'];
                                array_push($this->itemList, $thisRow);

                                $this->createMenuTree($line['id'], $indent."&nbsp; &nbsp;|&nbsp;");
                        }
                }
                $this->db->dbEndQuery($result);
	}

	function createMenuItemSelect($list, $selected=0)
	{
		if(is_array($list))
		{
			$html = "<select name=\"parent\">";
			$html .= "<option value=\"0\">Top Level</option>";

			foreach($list as $item)
			{
				if($item['id'] == $selected)
					$html .= "<option value=\"".$item['id']."\" selected>".$item['indent'].$item['txt']."</option>";
				else
					$html .= "<option value=\"".$item['id']."\">".$item['indent'].$item['txt']."</option>";
			}
			$html .= "</select>";
		}

		return $html;
	}

	function createMenuItemList($list)
	{
		$html = "<table width=\"100%\" border=\"0\">";
		if( is_array($list) )
		{
			foreach($list as $item)
			{
				if($item['id'])
					$html .= "<tr>
					      <td width=\"70%\">".$item['indent']."<a href=\"modSideMenu.php?id=".$item['id']."\">".$item['txt']."</a></td>
					      <td width=\"30%\"><a href=\"modSideMenu.php?delete=".$item['id']."\">delete</a></td>
					</tr>";
			}
		}
		$html .= "</table>";
		return $html;
	}	

	function createTargetList($selected = 0)
	{
		//creates the drop down for displaying the menu item target

		$html = "<select name=\"target\">";
		$html .= "<option value=\"\">No Target</option>";
		if($selected == "_blank")
			$html .= "<option value=\"_blank\" selected>New Window</option>";
        	else
			$html .= "<option value=\"_blank\">New Window</option>";
		$html .= "</select>";
		return $html;
	}


	function createCampusList($selected = 0)
	{	
		$campusListHTML = "";
		$query = "SELECT * FROM luCampus WHERE id != '".ALLCAMPUS."' ORDER BY name";
		$this->db->dbDoQuery($query);
		while($line = $this->db->dbGetNextArray() )
		{
			if($this->isInHashArray($selected, $line['id'], "campus") )
				$campusListHTML .= "<input type=\"checkbox\" name=\"campus[]\" value=\"".$line['id']."\" checked> ".$line['name']."<br>";	
			else
				$campusListHTML .= "<input type=\"checkbox\" name=\"campus[]\" value=\"".$line['id']."\"> ".$line['name']."<br>";	
		}
	
		return $campusListHTML;

	}


	function createGroupList($selected = 0)
	{
		$groupListHTML = "";
		$query = "SELECT * FROM groups ORDER BY name";
		$this->db->dbDoQuery($query);
		while( $line = $this->db->dbGetNextArray() )
		{
			if($this->isInHashArray($selected, $line['id'], "gid") )	
				$groupListHTML .= "<input type=\"checkbox\" name=\"group[]\" value=\"".$line['id']."\" checked> ".$line['name']."<br>";	
			else
				$groupListHTML .= "<input type=\"checkbox\" name=\"group[]\" value=\"".$line['id']."\"> ".$line['name']."<br>";	
		}
	
		return $groupListHTML;

	}


	function isInHashArray($array, $value, $hash)
	{
		//quick n dirty to check value in hashed array
		if(is_array($array))
		{
			foreach($array as $hasharray)
			{
				if($hasharray[$hash] == $value)
					return 1;
			}
		}
		return 0;
	}

		
		
}

$sideMenu = new sideMenu;
$myPage = new modSideMenu;


if(isset($_GET['delete']) && $_GET['delete'])
{
	//delete menu item
	$sideMenu->deleteMenuItem($_GET['delete']);
	$myPage->displayMenuItemList();
}
else if($_POST['text'] && $_POST['url'] && isset($_POST['parent']) && $_POST['id'])
{
	$sideMenu->updateMenuItem($_POST['id'], $_POST['parent'], $_POST['text'], $_POST['url'], $_POST['target'], $_POST['campus'], $_POST['group']);
	$myPage->displaySideMenuItem($_POST['id']);
}
else if($_POST['text'] && $_POST['url'] && isset($_POST['parent']))
{
	$sideMenu->addMenuItem($_POST['parent'], $_POST['text'], $_POST['url'], $_POST['target'], $_POST['campus'], $_POST['group']);
	$myPage->displayMenuItemList();
}

else if(isset($_GET['id']) && $_GET['id'])		
{
	$myPage->displaySideMenuItem($_GET['id']);
}
else if(isset($_GET['add']) && $_GET['add'])
{
	$myPage->displayNewSideMenuItem();
}
else
{
	$myPage->displayMenuItemList();
}


