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
		$this->itemList = array();
	}

	function displaySideMenuItem($id)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modMenuItem.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify Menu Item");

        /* Header variables
         */
        $this->t_body->set_var('SITE_GREETING', greeting());
        $this->t_body->set_var('SITE_LOGINNAME', formatName($_SESSION));

        /* Footer variables
         */
        $this->t_body->set_var('TEMPLATE_YEAR', date('Y'));
        $this->t_body->set_var('SITE_NAME', SITE_name);

		include_once("../include/page.php");

		$this->t_body->set_var("ACTION", "Update");

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

        /* Header variables
         */
        $this->t_body->set_var('SITE_GREETING', greeting());
        $this->t_body->set_var('SITE_LOGINNAME', formatName($_SESSION));

        /* Footer variables
         */
        $this->t_body->set_var('TEMPLATE_YEAR', date('Y'));
        $this->t_body->set_var('SITE_NAME', SITE_name);

		include_once("../include/page.php");
		$this->t_body->set_var("ACTION", "Create");
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

		$this->t_body->set_block("tplBODY", "up_button", "up_button_block");
		$this->t_body->set_block("tplBODY", "down_button", "down_button_block");
		$this->t_body->set_block("tplBODY", "menu_item", "menu_item_block");
		$this->t_body->set_block("tplBODY", "menu_item_list", "menu_item_list_block");

        /* Header variables
         */
        $this->t_body->set_var('SITE_GREETING', greeting());
        $this->t_body->set_var('SITE_LOGINNAME', formatName($_SESSION));

        /* Footer variables
         */
        $this->t_body->set_var('TEMPLATE_YEAR', date('Y'));
        $this->t_body->set_var('SITE_NAME', SITE_name);

		include_once("../include/page.php");

		$this->createMenuTree(0, "");
		$this->createMenuItemList($this->itemList);

		$this->t_body->pparse("finaloutput", "tplBODY");
	}


	function createMenuTree($parent, $indent)
	{
		//query database for all categories that user has permission to see (redundant doesnt work properly)
		$catQuery = "SELECT txt, id, sequence FROM sideMenu WHERE parent = '".$parent."' ORDER BY sequence, txt";

		$result = $this->db->query($catQuery);

		if( $this->db->countRows() > 0 )
		{
				while($line = $this->db->getNextArray($result) )
				{
						$thisRow['id'] = $line['id'];
						$thisRow['indent'] = $indent;
						$thisRow['txt'] = $line['txt'];
						//mark the first item
						if($line['sequence'] == 0)
							$thisRow['first'] = 1;

						$this->itemList[] = $thisRow;
						unset($thisRow);
						$this->createMenuTree($line['id'], $indent."&nbsp; &nbsp;|&nbsp;");
				}

			//mark the last item
			$lastRow = array_pop($this->itemList);
			$lastRow['last'] = 1;
			array_push($this->itemList, $lastRow);

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
		if( is_array($list) )
		{
			foreach($list as $item)
			{
				$this->t_body->set_var("ITEM_IDENT", $item['indent']);
				$this->t_body->set_var("ITEM_TXT", $item['txt']);
				$this->t_body->set_var("ITEM_ID", $item['id']);
				$this->t_body->set_var("ON_OFF", ++$i%2 ? 'dark' : '');

				if($item['first'])
					$this->t_body->set_var("up_button_block", "");
				else
					$this->t_body->parse("up_button_block", "up_button");

				if($item['last'])
					$this->t_body->set_var("down_button_block", "");
				else
					$this->t_body->parse("down_button_block", "down_button");

				$this->t_body->parse("menu_item_block", "menu_item", true);
			}
		}
		else
			$this->t_body->set_var("menu_item_list", "");
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
		$this->db->query($query);
		while($line = $this->db->getNextArray() )
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
		$this->db->query($query);
		while( $line = $this->db->getNextArray() )
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


if(is_numeric($_GET['delete']))
{
	//delete menu item
	$sideMenu->deleteMenuItem($_GET['delete']);
	$myPage->displayMenuItemList();
}
else if(is_numeric($_GET['up']))
{
	$sideMenu->moveMenuItemUp($_GET['up']);
	$myPage->displayMenuItemList();
}
else if(is_numeric($_GET['down']))
{
	$sideMenu->moveMenuItemDown($_GET['down']);
	$myPage->displayMenuItemList();
}
else if($_POST['text'] && $_POST['url'] && is_numeric($_POST['parent']) && is_numeric($_POST['id']))
{
	$sideMenu->updateMenuItem($_POST['id'], $_POST['parent'], $_POST['text'], $_POST['url'], $_POST['target'], $_POST['campus'], $_POST['group']);
	$myPage->displayMenuItemList($_POST['id']);
}
else if($_POST['text'] && $_POST['url'] && is_numeric($_POST['parent']))
{
	$sideMenu->addMenuItem($_POST['parent'], $_POST['text'], $_POST['url'], $_POST['target'], $_POST['campus'], $_POST['group']);
	$myPage->displayMenuItemList();
}

else if(is_numeric($_GET['id']))
{
	$myPage->displaySideMenuItem($_GET['id']);
}
else if(isset($_GET['add']))
{
	$myPage->displayNewSideMenuItem();
}
else
{
	$myPage->displayMenuItemList();
}


