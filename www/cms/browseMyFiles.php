<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
*							    *
*   Displays CMS filesystem.  Filesystem is made up off     *
*   folders, files, links.  Its a recursive system and has  *
*   both group and individual permissions                   *
*							    *
*   $Id: browse.php,v 1.17 2004/04/27 06:00:49 jleckie Exp $						    
*                                                           *
************************************************************/

require_once('../include/session.php');
require_once('../include/include.php');

class OutPut
{
	function OutPut($category)
	{
		$this->category = $category;
		
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file('tplBODY', 'cms/browseMyFiles.htm');

		$this->t_body->set_var("TITLE", HEADING." - My Files");
		$this->t_body->set_var("PARENTID", $category);
		
		//category output
		$this->t_body->set_block("tplBODY", "category_listing", "cats_block");
		$this->t_body->set_block("category_listing", "mod_cat", "mod_cat_section");	
					
		//file output
		$this->t_body->set_block("tplBODY", "file_listing", "files_block");
		$this->t_body->set_block("file_listing", "file_desc", "file_block");
		$this->t_body->set_block("file_listing", "mod_file", "mod_file_section");		

		//database handle
		$this->db = new dbManager;

		include_once("../include/page.php");	

	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function outputAll()
	{	
		//output path to current category
		$this->outputCatPath();	
		
		//get a list of all the categories.  later we will check if we have permission to access each
		$catQuery = "SELECT * FROM myFolder WHERE parent = ".$this->category." AND owner = ".$_SESSION['id']." ORDER BY name";
		$result = $this->db->query($catQuery);
		$count = $this->db->countRows($result);
		
		if($count > 0)
		{
			while($queryArray = $this->db->getNextArray($result))
			{
				$this->outputCategory($queryArray);
			}
		}else{
			$this->t_body->set_var("cats_block", "");
		}
				
		$fileQuery = "SELECT myFile.id, myFile.realname, DATE_FORMAT(myFile.submitted, '".FILE_date_format."') AS submitted, 
		user.username, user.firstname, user.lastname, user.local_email, user.alt_email, myFile.description, myFile.mime_type,
		iconFile, allowedFileTypes.description as mime_description, inline
		FROM myFile LEFT JOIN user ON user.id = myFile.owner LEFT JOIN allowedFileTypes ON myFile.mime_type = allowedFileTypes.mime
		WHERE myFile.owner = ".$_SESSION['id']." AND myFile.deleted = 0 AND myFile.parent = ".$this->category;

		$this->db->query($fileQuery);

		//if nothing there just display this
		if($this->db->countRows()<1){ $this->t_body->set_var("files_block", "");}
		else
		{
			while($queryArray = $this->db->getNextArray() )
			{
				$this->outputFile($queryArray);
			}
		}
	}
	
	function outputCatPath()
	{
		$parentID = 0;
		$path = "";			
	
		//first we dont want to be able to click on current node so just display its name
		if($this->category != 0) //unless of course its the root node (no parents here)
		{
			$query = "SELECT * FROM myFolder WHERE id = $this->category";	
			$currentCat = $this->db->getRow($query);
			$parentID = $currentCat['parent'];
			$path = ' > <b>'.scrubTEXT($currentCat['name']).'</b>';
		}

		while($parentID != 0)
		{
			$query = "SELECT * FROM myFolder WHERE id = ".$parentID."";
			$parentArray = $this->db->getRow($query);
			$parentID = $parentArray['parent'];
			$path = " > <a href=\"/cms/browseMyFiles.php?catid=".$parentArray['id']."\">".scrubTEXT($parentArray['name'])."</a>".$path;

		}
		
		$path = "<a href=\"/cms/browseMyFiles.php\"><b>Top </b></a>".$path;		
	
		$this->t_body->set_var("CATEGORY_PATH", $path);
	}	
	
	function outputFile($fileDetails)
	{
		// set filename for filesize() call below
		$filename = MYFILE_directory . "/" . $fileDetails['id'] . ".dat";

		// set file size
		if($fileDetails['realname'] != "")
		{
			if($fileDetails['file_size'])
				$filesize = $fileDetails['file_size'];
			else
				$filesize = @filesize($filename);
			
			if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
			{
				$this->t_body->set_var("SIZE", "");
				$fileDetails['realname'] = "";	
			}
			else
				$this->t_body->set_var("SIZE", '('.bytesToString($filesize).')');
		}
		else
			$this->t_body->set_var("SIZE", "");
		

		//set image
		if($fileDetails['iconFile'])
			$this->t_body->set_var("FILE_IMAGE", "/images/mime/".$fileDetails['iconFile']);
		else				
			$this->t_body->set_var("FILE_IMAGE", "/images/mime/application-x-none.png");
	
		if($fileDetails['mime_description'])
			$this->t_body->set_var("FILE_MIME", $fileDetails['mime_description']);
		else
			$this->t_body->set_var("FILE_MIME", "Unknown");
			
		// set url depending on wether it should have one and how it should be opened
		if($fileDetails['realname'] != "")
		{
			//if inline open in new window, if not give user a save dialog box
			if($fileDetails['inline'] == 'true')
				$this->t_body->set_var("FILE_URL", "<a href=\"/sendframe.php?id=".$fileDetails['id']."&myFile=1\" target=\"_blank\">");
			else
				$this->t_body->set_var("FILE_URL", "<a href=\"/send.php?id=".$fileDetails['id']."&myFile=1&save=1\">");
				
			$this->t_body->set_var('FILE_URL_END', '</a>');
		}
		else
		{
			$this->t_body->set_var('FILE_URL', '');
			$this->t_body->set_var('FILE_URL_END', '');
		}
		
		$this->t_body->set_var('FILE_DESCRIPTION', scrubTEXT($fileDetails['description']));
		$this->t_body->set_var('FILE_DESCRIPTION_ESCAPED', addslashes(htmlentities($fileDetails['description'])));
		
		//give modify/delete buttons
		$this->t_body->set_var("FILEID", $fileDetails['id']);
		$this->t_body->parse("mod_file_section", "mod_file"); 
		
		$this->t_body->set_var("file_block", "");		
		$this->t_body->parse("files_block", "file_listing", true);
	}
	
	function outputCategory($catDetails)
	{
		// set name
		$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($catDetails['name']));
		
		$this->t_body->set_var("CATEGORY_NAME_ESCAPED", addslashes(htmlentities($catDetails['name'])));		

		// set url
		$this->t_body->set_var("CATEGORY_LINK", "/cms/browseMyFiles.php?catid=".$catDetails['id']);
				
		//give the users modify buttons
		$this->t_body->set_var("CATID", $catDetails['id']);
		$this->t_body->parse("mod_cat_section", "mod_cat"); 

		$this->t_body->parse("cats_block", "category_listing", true);
	}
}

if(is_numeric($_GET['catid']))
	$myPage = new OutPut($_GET['catid']);
else
	$myPage = new OutPut(0);
	
$myPage->outputAll();
$myPage->showPage();


?>
