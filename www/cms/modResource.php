<?php

/************************************************************
*							    *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*							    *
************************************************************/

require_once("../include/include.php");
require_once("../include/session.php");

class ModResourcePage {
	
	function ModResourcePage()
	{
		$this->db = new dbManager;
	}
		
	function setFileTypeList($type=0)
	{
		//get types of files that can be added
		$query = "SELECT * FROM file_type";
		$this->db->dbDoQuery($query);
		$typeHTML = "<OPTION VALUE=\"0\"></OPTION>";
		while($line = $this->db->dbGetNextArray())
		{
			if($line['id'] == $type)
			{
				$typeHTML .= "<OPTION VALUE=\"".$line['id']."\" selected>";
				$typeHTML .= $line['name'];
				$typeHTML .= "</OPTION>";
			}
			else
			{
				$typeHTML .= "<OPTION VALUE=\"".$line['id']."\">";
				$typeHTML .= $line['name'];
				$typeHTML .= "</OPTION>";
			}
		}
		$typeHTML = "<select name=\"ftype\" class=\"formfield\">".$typeHTML."</select>";
		$this->t_body->set_var("TYPELIST", $typeHTML);
	}	


	function setCategoryList($category=0)
	{

		//convert category list into html code
		$this->listTree(0, "");
		array_shift($this->catList);

		$catHTML = "";
		while ($thisCat = array_shift($this->catList) ) 
		{
				if($thisCat['id'] == $category)
				{
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\" selected>";
					$catHTML .= $thisCat['name'];
					if($thisCat['code'] != "")
						$catHTML .= " (".$thisCat['code'].")";
					$catHTML .= "</OPTION>";
				}
				else
				{
					$catHTML .= "<OPTION VALUE=\"" . $thisCat['id'] . "\">";
					$catHTML .= $thisCat['name'];
					if($thisCat['code'] != "")
						$catHTML .= " (".$thisCat['code'].")";
					$catHTML .= "</OPTION>";
				}
		}
		$catHTML = "<select name=\"category\" class=\"formfield\">".$catHTML."</select>";
		$this->t_body->set_var("CATLIST", $catHTML);
		
	}

	function listTree($parent, $ident)
	{
		$this->catList[-1] = "";

		//query database for all categories that user has permission to see (redundant doesnt work properly)

		if($_SESSION['su'])
			$catQuery = "SELECT name, id, code FROM category WHERE parent = '".$parent."' ORDER BY name";
		else
			$catQuery = "SELECT name, id, code FROM category, perms WHERE parent = '".$parent."' AND
                                cid=id AND gid ='".$_SESSION['gid']."' AND type >= '1' ORDER BY name";

 		$result = $this->db->dbDoQuery($catQuery);

		if( $this->db->dbCountRows() > 0 )
		{
			while($line = $this->db->dbGetNextArray($result) )
			{
				$thisRow['id'] = $line['id'];
				$thisRow['name'] = $ident.$line['name'];
				$thisRow['code'] = $line['code'];
				array_push($this->catList, $thisRow);

				$this->listTree($line['id'], $ident."&nbsp; &nbsp;|&nbsp;");
			}
		}
		$this->db->dbEndQuery($result);
	}	
	
	function setCategory($category)
	{
		if($category)
		{
			$query = "SELECT name, code FROM category WHERE id = '$category'";
			$result = $this->db->dbGetSingleRow($query);

			//if category has code output it
			if($result['code'])			
				$catOutput = "<b>".$result['name']." (".$result['code'].")</b>";
			else
				$catOutput = "<b>".$result['name']."</b>";

			$catOutput .= "<input type=\"hidden\" name=\"category\" value=\"$category\">";

			$this->t_body->set_var("CATLIST", $catOutput);
		}
		else
			$this->setCategoryList();
		
	}

	function setMultiCat($category)
	{

		//get list of categories involved in with write access
		require("../include/involved.php");
		$catList = involvedCategoryList($_SESSION['id'], $_SESSION['subjects'], 2);
	
		$catOutput = "";
		//turn list into html 
		foreach($catList as $cat)
		{
			$catOutput .= "<input type=\"checkbox\" name=\"mulc[]\" value=\"".$cat['id']."\">".$catList['name'];
			
			if($cat['code'] != "")
				$catOutput .= " (".$cat['code'].")";

			$catOutput .= "<br />";
		}

		

		//hide a variable in the page to indicate on submission how many cats to parse
		$catOutput .="<input type=\"hidden\" name=\"catCount\" value=\"$i\">";

		$this->t_body->set_var("CATLIST", $catOutput);

	}


	function setLists($category, $fileType, $type)
	{
		//if page is opened with a cat already defined and a filetype make them static
	
		$this->t_body->set_var("ACTION", "Add");

		//set CATLIST to either just category or multiple cats if requested
		if($type=="multfile" || $type=="multurl")
			$this->setMultiCat($category);
		else
			$this->setCategory($category);

		if($type=="file" || $type=="multfile")
		{

			//set max file size to advice browser
			$this->t_body->set_var("MAX_FILE_SIZE", MAX_FILE_SIZE); 

			//dont allow submission box to be created for multiple files
			if($type=="file")
			{
				$this->t_body->set_var("SUBBOX", "<input type=\"checkbox\" name=\"submission\" value=\"1\">");
				$this->t_body->parse("submission_block","submission", true); 
			}
			else
				$this->t_body->set_var("submission_block", "");
				
			//set the browse block up
			if($fileType)
			{
				$query = "SELECT name FROM file_type WHERE id = '$fileType'";
				$result = $this->db->dbGetSingleRow($query);
			
				$typeOutput = "<b>".$result['name']."</b><input type=\"hidden\" name=\"ftype\" value=\"$fileType\">";
				$this->t_body->set_var("TYPELIST", $typeOutput);
				
			}
			else
			{
				$this->setFileTypeList();
			}
		}
		else
		{
			$this->t_body->set_var("URL", "");
			$this->t_body->set_var("IDFIELD", "");
		}

		//set defaults for content of boxes
		$this->t_body->set_var("DESCRIPTION", "");
		$this->t_body->set_var("COMMENT", "");

	}
	
	function displayModifyFile($id)
        {
		$this->t_body->set_var("ACTION", "Modify");

                //first find details about file to fill form with
		include("file.php");
		$fileObj = new file;
		$file = $fileObj->getFile($id); 

		//output submission block		
		$this->t_body->parse("submission_block","submission", true); 

		//remove the browse block and replace with a hidden value
		$this->t_body->set_block("tplBODY", "browse_row", "browse_block");
  		$this->t_body->set_var("browse_block", "<input type=\"hidden\" name=\"modid\" value=\"$id\">");
		
		$this->t_body->set_var("DESCRIPTION", $file['description']);
		$this->t_body->set_var("COMMENT", $file['comment']);
                $this->setCategory($file['category']);
		$this->setFileTypeList($file['type']);

		//check box for submission box associated with file
		$query = "SELECT id, deleted FROM submission_box WHERE fid = '$id'";
		$sub = $this->db->dbGetSingleRow($query);

		//if submission box exists
		if($sub['id'])
		{	
			//if its been deleted
			if($sub['deleted'])
			{
				 $this->t_body->set_var("SUBBOX", "<a href=\"/submission/listSubmission.php?undelete=".$sub['id']."\">undelete box</a>");
			}
			else
			{
				$this->t_body->set_var("SUBBOX", "<a href=\"/submission/listSubmission.php?delete=".$sub['id']."\">delete 
				</a> // <a href=\"/submission/createSubmission.php?sid=".$sub['id']."\">modify</a>");
			}
		}
		else
			$this->t_body->set_var("SUBBOX", "<a href=\"/submission/createSubmission.php?cid=".$file['category']."&fileid=$id\">create</a>");
			
		

	}

	function displayModifyURL($id)
        {
                //first find details about file to fill form with
		include("url.php");
		$urlObj = new url;
		$url = $urlObj->getUrl($id); 

		$this->t_body->set_var("ACTION", "Modify");
		$this->t_body->set_var("IDFIELD", "<input type=\"hidden\" name=\"modid\" value=\"$id\">");
		$this->t_body->set_var("URL", $url['url']); 
		$this->t_body->set_var("DESCRIPTION", $url['description']);
		$this->t_body->set_var("COMMENT", $url['comment']);
                $this->setCategory($url['category']);
		
	}
	
        function showPage($type, $category=0, $file=0, $id=0)
        {
			//check perms
			require_once("../include/permission.php");
			if($category != 0) //if user selecting category fine, but if they already selected dont give them false hope
			{	
				if(perm($category, HOMEPAGESERVICE) < 2)
					error(6);
			}
				
			$this->t_body = new Template(TPL_directory);
		
			if($type=="url"||$type=="modurl"||$type=="multurl")
			{
               	$this->t_body->set_file("tplBODY", "cms/addURL.htm");
				$this->t_body->set_var("TITLE", HEADING." - Link Submission page");
			}
			else
			{
               	$this->t_body->set_file("tplBODY", "cms/addFile.htm");
				$this->t_body->set_var("TITLE", HEADING." - File Submission page");
				$this->t_body->set_block("tplBODY", "submission", "submission_block");
			}			
			if($type=="modfile")
				$this->displayModifyFile($id);
			else if($type=="modurl")
				$this->displayModifyURL($id);
			else
				$this->setLists($category, $file, $type);	
	
			require_once("../include/page.php");
		
			$this->t_body->pparse("finaloutput", "tplBODY");
        }
}

//create the object

$myPage = new ModResourcePage();

if(isset($_POST['description']) && $_POST['description'])
{
	//description entered

	if(isset($_POST['url']) && $_POST['url'] )
	{
		//a url has been submitted 
		include("url.php");
		$urlDB = new url; 

		if(isset($_POST['modid']) && $_POST['modid'])
			$urlDB->modifyURL($_POST['url'], $_POST['description'], $_POST['comment'], $_POST['category'], $_POST['modid']);
		else
		{
			if(!isset($_POST['category']))
			{
				for($x=0; $_POST['catCount'] > $x; $x++)
				{
					if($_POST['mulc'][$x])
						$urlDB->addURL($_POST['url'], $_POST['description'], $_POST['comment'], $_POST['mulc'][$x]);
				}
			}
			else				
				$urlDB->addURL($_POST['url'], $_POST['description'], $_POST['comment'], $_POST['category']);
		}
		$back = -2;
		include("../include/back.php");
		exit;
	}
	else if( isset($_FILES['file']['name']) && $_FILES['file']['name'] )
	{
		//call file security checks
		include("../include/fileSecurity.php");

		include("file.php");
		$fileDB = new file; 
		
		//all checks passed add it
		if($_POST['catCount'])
		{
			for($x=0; $_POST['catCount'] > $x; $x++)
			{
				if($_POST['mulc'][$x])
	 			       	$fileId = $fileDB->addFile($_FILES['file']['name'], $_FILES['file']['type'], 
					$_POST['description'], $_POST['comment'], $_POST['mulc'][$x], $_POST['ftype']);
			}
		}
		else			
        		$fileId = $fileDB->addFile($_FILES['file']['name'], $_FILES['file']['type'], $_POST['description'], 
			$_POST['comment'], $_POST['category'], $_POST['ftype']);

	 	//ok finished redirect create submission page if requested or just go to where file was added
        	if($_POST['submission'])
			localRedir("/submission/createSubmission.php?cid=".$_POST['category']."&fileid=".$fileId);
		else
		{
			$back = -2;
			include("../include/back.php");
		}
		exit;
		

	}
	else if(isset($_POST['modid']) && $_POST['modid'])
	{
		include("file.php");
		$fileDB = new file; 

		$fileDB->modifyFile($_POST['description'], $_POST['comment'], $_POST['category'], $_POST['ftype'], $_POST['modid']);
		
		$back = -2;
		include("../include/back.php");
		exit;
	}
	else if(isset($_POST['ftype']))
	{	
		include("file.php");
		$fileDB = new file; 

		//submitting a file without a file 
		if($_POST['catCount'])
		{

			for($x=0; $_POST['catCount'] > $x; $x++)
			{
				if($_POST['mulc'][$x])
	 			       	$fileId = $fileDB->addFile("", "", $_POST['description'], $_POST['comment'], 
					$_POST['mulc'][$x], $_POST['ftype']);
			}
		}
		else			
			$fileId = $fileDB->addFile("", "", $_POST['description'], $_POST['comment'], $_POST['category'], 
			$_POST['ftype']);

	 	//ok finished redirect create submission page if requested or just go to where file was added
        	if($_POST['submission'])
			localRedir("/submission/createSubmission.php?cid=".$_POST['category']."&fileid=".$fileId);
		else
		{
			$back = -2;
			include("../include/back.php");
		}
		exit;
	}
	else
	{ 
        	//error either both file and url submitted or neither
		error(2);
	}
}
else if((isset($_FILES['file']['name']) && $_FILES['file']['name']) || (isset($_POST['url']) && $_POST['url']) || 
	(isset($_POST['comment']) && $_POST['comment']))
{
	error(2);
}
else
{
	if ($_GET['type'] != "url" && $_GET['type'] != "multurl" && $_GET['type'] != "multfile" && $_GET['type'] != "modurl" 
		&& $_GET['type'] != "modfile")
		$myPage->showPage("file", $_GET['cid'], $_GET['file'], $_GET['id']);
	else
		//just display the page
		$myPage->showPage($_GET['type'], $_GET['cid'], $_GET['file'], $_GET['id']);
}

?>
