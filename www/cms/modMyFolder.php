<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
require_once("../include/session.php");
require_once("../include/include.php");
require_once("myFolder.php");

class ModMyFolderPage
{

	function ModMyFolderPage()
	{
		$this->db = new dbManager;
	}

	function setCatList($name, $catid = 0)
	{
		//convert category list into html code

		$this->getCatTree(0,"");	
		
		array_shift($this->catList);

		$catHTML = "";

		//first setup the default root node
		$catHTML .= "<SELECT name=\"$name\"><OPTION VALUE=\"0\">Top Level</OPTION>";
		while ($thisCat = array_shift($this->catList) ) 
		{
				if($catid == $thisCat['id'])
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\" selected>";
				else
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\">";
					
				$catHTML .= $thisCat['name'];
				$catHTML .= "</OPTION>";
		}
		$catHTML .= "</SELECT>";
		$this->t_body->set_var("CATLIST", $catHTML);

	}
		
	function getCatTree($parent, $ident)
	{
        	$this->catList[-1] = "";
		//TODO: make this one query iterative rather than being recursive. also needs to check perms
		$query = "SELECT * FROM myFolder WHERE parent = ".$parent." ORDER BY name";

 		$result = $this->db->dbDoQuery($query);

		if( $this->db->dbCountRows() > 0 )
		{
			while($line = $this->db->dbGetNextArray($result) )
			{
				$thisRow['id'] = $line['id'];
				$thisRow['name'] = $ident.scrubTEXT($line['name']);
				array_push($this->catList, $thisRow);

				$this->getCatTree($line['id'], $ident."&nbsp; &nbsp;|&nbsp;");
			}
		}
		//clean up 
		$this->db->dbEndQuery($result);
	}	

        function showPage()
        {	
			$this->t_body = new Template(TPL_directory);
			$folderDB = new myFolder;
						
			if(isset($_GET['modid']) && $_GET['modid'])
			{
				$catid = $_GET['modid'];
				$this->t_body->set_file("tplBODY", "cms/modMyFolder.htm");
				$this->t_body->set_var("TITLE", "Modify Folder page");
				$this->t_body->set_var("ACTION", "Modify");
				$this->t_body->set_var("CATID", "<input type=\"hidden\" name=\"modid\" value=\"$catid\">");
				
				//get category details
				$cat = $folderDB->getFolder($catid);
				
				//set name
				$this->t_body->set_var("CATNAME", $cat['name']);
				
				$this->setCatList("parent", $cat['parent']);
			}	
			else
			{
			$this->t_body->set_file("tplBODY", "cms/modMyFolder.htm");
			$this->t_body->set_var("TITLE", HEADING." - Create Folder page");
			$this->t_body->set_var("ACTION", "Create");
			$this->t_body->set_var("CATNAME", "");
			$this->t_body->set_var("CATID", "");
			//if user hasnt specified a category
			if($_GET['catid'] != 0)
			{
				$this->setCatList("parent", $_GET['catid']);
			}
			else
				$this->setCatList("parent");
			}
			
			require_once("../include/page.php");
			
				$this->t_body->pparse("finaloutput", "tplBODY");
			}
}

//check data is being submitted
if(is_numeric($_POST['parent']) && $_POST['name'])
{
	//stuff has been submitted
	$folderDB = new myFolder;
	
	//check if its a modify or add
	if(isset($_POST['modid']) && $_POST['modid'])
	{
		$catid = $_POST['modid'];
		//modify
		$folderDB->modifyCat($catid, $_POST['name'], $_POST['parent']);
	}
	else
	{
		//add
		$catid = $folderDB->insertCat($_POST['name'], $_POST['parent']);
	}

	localRedir("/cms/browseMyFiles.php?catid=".$_POST['parent']);
	exit;
}	
elseif(isset($_GET['delid']) && $_GET['delid'])
{
	//delete submition has been made
	//check user has permission to delete
	$folderDB = new myFolder;

	$folderInfo = $folderDB->getFolder($_GET['delid']);
	$folderDB->deleteCat($_GET['delid']);

	localRedir("/cms/browseMyFiles.php?catid=".$folderInfo['parent']);
	exit;
}
else
{
	//nothing submitted or wrong submission
	$myPage = new ModMyFolderPage();
	$myPage->showPage();
}

?>
