<?php

/************************************************************
*							    *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*							    *
************************************************************/

require_once("../include/include.php");
require_once("../include/session.php");
require_once("myFile.php");

class ModMyFilePage {
	
	function ModMyFilePage()
	{
		$this->db = new dbManager;
	}
	
	function displayModifyFile($id)
	{
		$this->t_body->set_var("MAX_FILE_SIZE", "");
		if(!empty($id)){			
			$this->t_body->set_var("ACTION", "Modify");
	
			//first find details about file to fill form with
			$myFileObj = new myFile;
			$myFile = $myFileObj->getFile($id); 
			
			$this->setCatList("parent", $myFile['parent']);
	
			//remove the browse block and replace with a hidden value
			$this->t_body->set_block("tplBODY", "browse_row", "browse_block");
			$this->t_body->set_var("browse_block", "<input type=\"hidden\" name=\"modid\" value=\"$id\">");
			
		}else{
			$this->t_body->set_var("ACTION", "Create");
			$this->setCatList("parent", $_GET['catid']);
		}
		
		$this->t_body->set_var("DESCRIPTION", $myFile['description']);
	}
	
	function showPage($id = 0)
	{
		// Permission & Existence check
		if($id != 0){
			$myFileObj = new myFile;
			$myFile = $myFileObj->getFile($id);
			if(empty($myFile)){
				error(101);
			}
			if($myFile['owner'] != $_SESSION['id']){
				error(47);
			}
		}
					
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "cms/addMyFile.htm");
		$this->t_body->set_var("TITLE", HEADING." - File Submission page");
		$this->displayModifyFile($id);

		require_once("../include/page.php");
	
		$this->t_body->pparse("finaloutput", "tplBODY");
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
}

//create the object

$myPage = new ModMyFilePage();

if(isset($_POST['description']) && $_POST['description'])
{
	//description entered
	if( isset($_FILES['file']['name']) && $_FILES['file']['name'] )
	{
		$fileDB = new myFile; 
		//call file security checks
		include("../include/fileSecurity.php");
		fileSecurityCheck($_FILES['file']['error'], $_FILES['file']['name'], $_FILES['file']['tmp_name'], $_FILES['file']['size']);
		
		
		$fileId = $fileDB->addFile($_POST['parent'], $_FILES['file']['name'], $_FILES['file']['type'], $_POST['description']);

	 	//Added, now redirect to browse
		localRedir("/cms/browseMyFiles.php?catid=".$_POST['parent']);
		exit;
	}
	else if(!empty($_POST['modid']) && $_POST['modid'])
	{
		$fileDB = new myFile; 

		$fileDB->modifyFile($_POST['parent'], $_POST['description'], $_POST['modid']);		
		
		localRedir("/cms/browseMyFiles.php?catid=".$_POST['parent']);
		exit;
	}
	else
	{ 
		//error either both file and url submitted or neither
		error(2);
	}
}
else if(isset($_FILES['file']['name']) && $_FILES['file']['name'])
{
	error(2);
}
else
{
	//just display the page
	$myPage->showPage($_GET['modid']);
}

?>
