<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");
require_once("folder.php");

class ModFolderPage
{

	function ModFolderPage()
	{
		$this->db = new dbManager;
	}

	function setCatList($name, $cid = 0)
	{
		//convert category list into html code

		$this->getCatTree(0,"");	
		
		array_shift($this->catList);

		$catHTML = "";

		//first setup the default root node
		$catHTML .= "<SELECT name=\"$name\"><OPTION VALUE=\"0\">Top Level</OPTION>";
		while ($thisCat = array_shift($this->catList) ) 
		{
				if($cid == $thisCat['id'])
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\" selected>";
				else
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\">";
				$catHTML .= $thisCat['name'];
				$catHTML .= "</OPTION>";
		}
		$catHTML .= "</SELECT>";
		$this->t_body->set_var("CATLIST", $catHTML);

	}

	function setTemplateList($tid = 0)
	{		
		$templateList = $this->getTemplateList();

		$templateHTML = "<SELECT name=\"template\"><OPTION VALUE=\"0\">No Homepage</OPTION>";
		while ($thisTemplate = array_shift($templateList))
		{
				if($tid == $thisTemplate['id'])
					$templateHTML .= "<OPTION VALUE=\"" . $thisTemplate['id'] . "\" selected>";
				else
					$templateHTML .= "<OPTION VALUE=\"" . $thisTemplate['id'] . "\">";
				$templateHTML .= $thisTemplate['name'];
				$templateHTML .= "</OPTION>";

		}
		$templateHTML .="</SELECT>";
		$this->t_body->set_var("TEMPLATELIST", $templateHTML); 
		
	}

	function setGroupList()
	{
		$groupList = $this->getGroupList();
		$groupHTML = "";
		
		$checkboxNames = '';
		while ($thisGroup = array_shift($groupList))
		{
				$groupHTML .= "<tr>";
				$groupHTML .= "<td>".$thisGroup['name']."</td>";
				$groupHTML .= "<td><input type=\"radio\" name=\"".$thisGroup['id']."\" value=\"0\" checked></td>";
				$groupHTML .= "<td><input type=\"radio\" name=\"".$thisGroup['id']."\" value=\"1\"></td>";
				$groupHTML .= "<td><input type=\"radio\" name=\"".$thisGroup['id']."\" value=\"2\"></td>";
				$groupHTML .= "</tr>";

				$checkboxNames .= '"' . $thisGroup[ 'id'] . '_r","' . $thisGroup[ 'id'] . '_w",';
		}
		$checkboxNames = substr( $checkboxNames, 0, strlen( $checkboxNames) - 1) . ',"groupcheck"';

		$this->t_body->set_var("PERMSTABLE", $groupHTML); 
		$this->t_body->set_var("CHECKBOXNAMES", $checkboxNames); 
	}

	function setGroupListPerms($cid, $folderDB)
	{
		$groupList = $this->getGroupList();
		$groupHTML = "";
		
		while ($thisGroup = array_shift($groupList))
		{
				$type = $folderDB->getPerms($cid, $thisGroup['id']);
				$groupHTML .= "<tr>";
				$groupHTML .= "<td>".$thisGroup['name']."</td>";
				$groupHTML .= "<td><input type=\"radio\" name=\"group[".$thisGroup['id']."]\" value=\"0\"";
				if($type == 0 || $type == "")
					$groupHTML .= " checked></td>";
				else
					$groupHTML .= "></td>";
				$groupHTML .= "<td><input type=\"radio\" name=\"group[".$thisGroup['id']."]\" value=\"1\"";
				if($type == 1)
					$groupHTML .= " checked></td>";
				else
					$groupHTML .= "></td>";
				$groupHTML .= "<td><input type=\"radio\" name=\"group[".$thisGroup['id']."]\" value=\"2\"";
				if($type == 2)
					$groupHTML .= " checked></td>"; 
				else
					$groupHTML .= "></td>"; 
				$groupHTML .= "</tr>";
		}
		$this->t_body->set_var("PERMSTABLE", $groupHTML); 
	}
	
	function getGroupList()
	{
		$query = "SELECT * FROM groups ORDER BY name";
		return $this->db->dbGetAllRows($query);
	}		
		
	function getTemplateList()
	{
		$query = "SELECT id, name FROM homepages";
		return $this->db->dbGetAllRows($query);
	}
		
	function getCatTree($parent, $ident)
	{
        	$this->catList[-1] = "";
		
		$query = "SELECT id, name FROM category WHERE parent = ".$parent." ORDER BY name";

 		$result = $this->db->dbDoQuery($query);

		if( $this->db->dbCountRows() > 0 )
		{
			while($line = $this->db->dbGetNextArray($result) )
			{
				$thisRow['id'] = $line['id'];
				$thisRow['name'] = $ident.htmlentities($line['name']);
				array_push($this->catList, $thisRow);

				$this->getCatTree($line['id'], $ident."&nbsp; &nbsp;|&nbsp;");
			}
		}
		//clean up 
		$this->db->dbEndQuery($result);
	}	

	function setSynergyCodeField($code = 0)
	{
		//if user a superuser allow them to add subjects
		if($_SESSION['su'])
		{
			$this->t_body->set_var("SYNERGYDESC", "<b>Synergy Subject Code</b><br><i>If you want this 
						category to be a subject enter its code here</i>");
			if($code)
				$this->t_body->set_var("SYNERGYCODE", "<input type=\"text\" name=\"code\" maxlength=\"8\" size=\"8\" value=\"$code\">");
			else
				$this->t_body->set_var("SYNERGYCODE", "<input type=\"text\" name=\"code\" maxlength=\"8\" size=\"8\">");
		}
		else
		{
			$this->t_body->set_var("SYNERGYDESC", "");
			$this->t_body->set_var("SYNERGYCODE", "");
		}
	}

        function showPage($action, $cid = 0)
        {	
	
			$this->t_body = new Template(TPL_directory);
			$folderDB = new folder;
			
			//ensure they have access to do this
			$folderDB->checkWriteAccess($cid);
			
			if($action == "del")
			{
				$this->t_body->set_file("tplBODY", "cms/delCat.htm");
				$this->t_body->set_var("TITLE", "Delete Category page");
				$this->setCatList("delete");
			}
			else if($action == "mod")
			{
	             $this->t_body->set_file("tplBODY", "cms/addCat.htm");
        		$this->t_body->set_var("TITLE", "Modify Category page");
				$this->t_body->set_var("ACTION", "Modify");
				$this->t_body->set_var("CID", "<input type=\"hidden\" name=\"cid\" value=\"$cid\">");
			
			//get category details
			$cat = $folderDB->getFolder($cid);
			
			//set name
			$this->t_body->set_var("CATNAME", $cat['name']);

			//set template box
			if($cat['template'])
				$this->setTemplateList($cat['template']);
			else
				$this->setTemplateList();
	
			$this->setCatList("parent", $cat['parent']);
			$this->setGroupListPerms($cid, $folderDB);
			$this->setSynergyCodeField($cat['code']);
			
		}	
		else
		{
	                $this->t_body->set_file("tplBODY", "cms/addCat.htm");
        		$this->t_body->set_var("TITLE", HEADING." - Create Category page");
			$this->t_body->set_var("ACTION", "Create");
			$this->t_body->set_var("CATNAME", "");
			$this->t_body->set_var("CID", "");
			//if user hasnt specified a category
			if($cid != 0)
			{
				$cat = $folderDB->getFolder($cid);
				$this->t_body->set_var("CATLIST", "<b>".$cat['name']."</b><input type=\"hidden\" name=\"parent\" value=\"$cid\"");
			}
			else
				$this->setCatList("parent");

			$this->setTemplateList();
			$this->setSynergyCodeField();

			$this->setGroupList();
		}

		require_once("../include/page.php");

                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}



if(isset($_POST['name']) && isset($_POST['parent']) && $_POST['name'])
{
	//stuff has been submitted
	$folderDB = new folder;

	//check if its a modify or add
	if(isset($_POST['cid']) && $_POST['cid'])
	{
		$cid = $_POST['cid'];
		//modify
		$folderDB->modifyCat($cid, $_POST['name'], $_POST['parent'], $_POST['template'], $_POST['code']);
		//delete perms and so we can readd new ones later
		$folderDB->deletePerms($cid);
	}
	else
	{
		//add
		$cid = $folderDB->insertCat($_POST['name'], $_POST['parent'], $_POST['template'], $_POST['code']);
	}

	//for each group insert the permissions selected
	$folderDB->insertPermArray($cid, $_POST['parent'], $_POST['group']);
	
	//if user would like to create group forward them onto the group admin page and make them owner
	if($_POST['groupcheck'])
		localRedir("/group/modUserGroup.php?owner=".$_SESSION['id']."&service=1&sid=".$cid);
	else
	{
		$back = -2;
		include("../include/back.php");
		exit;
	}
}	
else if(isset($_GET['delete']) && $_GET['delete'])
{
	//delete submition has been made
	//check user has permission to delete
	$folderDB = new folder;

	$folderInfo = $folderDB->getFolder($_GET['delete']);
	$folderDB->deleteCat($_GET['delete']);

	localRedir("/browse.php?category=".$folderInfo['parent']);
}
else
{
	//nothing submitted or wrong submission
	$myPage = new ModFolderPage();
	$myPage->showPage($_GET['action'], $_GET['cid']);
}

?>
