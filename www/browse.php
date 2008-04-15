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

require_once('include/session.php');
require_once('include/include.php');
require_once('include/permission.php');

class OutPut
{
	function OutPut($category, $verbose)
	{
		$this->category = $category;
		$this->verbose = $verbose;
		
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file('tplBODY', 'browse.htm');

		$this->t_body->set_var("TITLE", HEADING." - Browse");
		$this->t_body->set_var("CID", $category);
		
		//category output
		$this->t_body->set_block("tplBODY", "category_listing", "cats_block");
		$this->t_body->set_block("category_listing", "mod_cat", "mod_cat_section");		
	
		//file output
		$this->t_body->set_block("tplBODY", "file_listing", "files_block");
		$this->t_body->set_block("file_listing", "file_desc", "file_block");
		$this->t_body->set_block("file_listing", "mod_file", "mod_file_section");		

		//link output
		$this->t_body->set_block("tplBODY", "link_listing", "links_block");
		$this->t_body->set_block("link_listing", "link_desc", "link_block");
		$this->t_body->set_block("link_listing", "mod_link", "mod_link_section");		

		//database handle
		$this->db = new dbManager;

		include_once("include/page.php");	

	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function outputAll()
	{			
		if($this->category != 0)
		{
			//check permissions
			$this->perm = perm($this->category, 1);
			if($this->perm < 1)
				error(14);
		}

		//output path to current category
		$this->outputCatPath();

		//output various page links
		$this->outputPageLink();		
		
		if($this->perm < 2) {
			$hiddenQuery = ' AND hidden = 0';
		}

		//get a list of all the categories.  later we will check if we have permission to access each
		$catQuery = "SELECT id, name, template, code FROM category WHERE parent = ".$this->category." ORDER BY name";

		$fileQuery = "SELECT file.id, file.realname, DATE_FORMAT(file.submitted, '".FILE_date_format."') AS submitted, 
		user.title, user.id as uid, user.firstname, user.lastname, user.local_email, user.alt_email, file.description, file.comment, file.mime_type,
		iconFile, allowedFileTypes.description as mime_description, inline
		FROM file LEFT JOIN user ON user.id = file.owner LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
		WHERE file.category = ".$this->category." AND deleted = '0'" . $hiddenQuery;

		$linkQuery = "SELECT link.id, link.url, DATE_FORMAT(link.submitted, '".FILE_date_format."') AS submitted, 
		user.id as uid, user.title, user.firstname, user.lastname, user.local_email, user.alt_email, link.description, link.comment 
		FROM link LEFT JOIN user ON user.id = link.owner WHERE link.category = ".$this->category."" . $hiddenQuery;

		$result = $this->db->query($catQuery);

		$flag = 0;
		while($queryArray = $this->db->getNextArray($result) )
		{
			//only ouput if user has right to see
			if(perm($queryArray['id'], 1) > 0)
			{
				$this->outputCategory($queryArray);
				$flag =1;
			}
		}
		if(!$flag){ $this->t_body->set_var("cats_block", "");}

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
		
		//output the links
		$this->db->query($linkQuery);
		if($this->db->countRows()<1){ $this->t_body->set_var("links_block", "");}
		else
		{
			while($queryArray = $this->db->getNextArray() )
			{
				$this->outputLink($queryArray);
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
			$query = "SELECT id, name, parent, code FROM category WHERE id = $this->category";	
			$currentCat = $this->db->getRow($query);
			$parentID = $currentCat['parent'];
			if($currentCat['code'])
				$code = ' ('.scrubTEXT($currentCat['code']).')';
			else
				$code = '';
				
			$path = ' > <b>'.scrubTEXT($currentCat['name']).$code.'</b>';
		}

		while($parentID != 0)
		{
			$query = "SELECT id, name, parent, code FROM category WHERE id = ".$parentID."";
			$parentArray = $this->db->getRow($query);
			$parentID = $parentArray['parent'];
			if($parentArray['code'])
				$code = ' ('.scrubTEXT($parrentArray['code']).')';
			else
				$code = '';
			$path = " > <a href=\"browse.php?category=".$parentArray['id']."&verbose=".$this->verbose."\">".scrubTEXT($parentArray['name']).$code."</a>".$path;

		}
		
		$path = "<a href=\"browse.php\"><b>Top </b></a>".$path;		
	
		$this->t_body->set_var("CATEGORY_PATH", $path);
	}	

	function outputPageLink()
	{	
		$this->t_body->set_block("tplBODY", "view_expand", "view_expand_block");
		$this->t_body->set_block("tplBODY", "view_collapse", "view_collapse_block");
		$this->t_body->set_block("tplBODY", "view_homepage", "view_homepage_block");
		$this->t_body->set_block("tplBODY", "view_image_gallery", "view_image_gallery_block");
		$this->t_body->set_block("tplBODY", "modify_access", "modify_access_block");
	
		//output link to control detail
		if($this->verbose)
		{
			$this->t_body->set_var("view_expand_block", "");
			$this->t_body->parse("view_collapse_block", "view_collapse");
		}
		else
		{
			$this->t_body->set_var("view_collapse_block", "");
			$this->t_body->parse("view_expand_block", "view_expand");
		}
		
		$query  = "SELECT id, code, template FROM category WHERE id = ".$this->category."";
		$result = $this->db->getRow($query);
		
		if($result['id'] != $this->category)  //this will ensure that the category exists
			error(13);
		
		if($result['template'])
			$this->t_body->parse("view_homepage_block", "view_homepage");
		else
			$this->t_body->set_var("view_homepage_block", "");

		$this->t_body->parse("view_image_gallery_block", "view_image_gallery");			

		if($this->perm >= 2)
			$this->t_body->parse("modify_access_block", "modify_access");
		else
			$this->t_body->set_var("modify_access_block", "");
	}

	function outputFile($fileDetails)
	{
		// correction for empty description
		if ($fileDetails['comment'] == "") { $fileDetails['comment'] = "No comment available"; }

		// set filename for filesize() call below
		$filename = FILE_directory . "/" . $fileDetails['id'] . ".dat";

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
				$this->t_body->set_var("FILE_URL", "<a href=\"/sendframe.php?id=".$fileDetails['id']."\" target=\"_blank\">");
			else
				$this->t_body->set_var("FILE_URL", "<a href=\"/send.php?id=".$fileDetails['id']."&save=1\">");
				
			$this->t_body->set_var('FILE_URL_END', '</a>');
		}
		else
		{
			$this->t_body->set_var('FILE_URL', '');
			$this->t_body->set_var('FILE_URL_END', '');
		}
		
		$this->t_body->set_var('FILE_DESCRIPTION', scrubTEXT($fileDetails['description']));
		$this->t_body->set_var('FILE_DESCRIPTION_ESCAPED', addslashes(htmlentities($fileDetails['description'])));
		
		//if user has write access give modify/delete buttons
		if($this->perm >= 2)
		{
			$this->t_body->set_var("FILEID", $fileDetails['id']);
			$this->t_body->parse("mod_file_section", "mod_file"); 
		}
		else
			$this->t_body->set_var("mod_file_section", "");
		
		//show verbose info
		if($this->verbose)
		{
			// set comment
			$this->t_body->set_var("COMMENT", scrubTEXT($fileDetails['comment']));
		
			// set owner
			$fullname = formatName($fileDetails);
			$owner = "<a href=\"/search/userLookup.php?id={$fileDetails['uid']}\">$fullname</a>";
			
			//output owner
			$this->t_body->set_var("OWNER", $owner);
		
			// set date uploaded
			$this->t_body->set_var("DATETIME", $fileDetails['submitted']);

			$this->t_body->parse("file_block", "file_desc");
		}
		else
		{
			$this->t_body->set_var("file_block", "");
		}
		
		$this->t_body->parse("files_block", "file_listing", true);
	}

	function outputLink($linkDetails)
	{
		if ($linkDetails['comment'] == "") { $linkDetails['comment'] = "No comment available"; }

		// set link
		if(isLocalURL($linkDetails['url']))
		{
			$this->t_body->set_var("LINK_URL", $linkDetails['url']);
			$this->t_body->set_var("LINK_TARGET", "_self");
		}
		else
		{
			$this->t_body->set_var("LINK_URL", "/redir.php?url=".$linkDetails['url']);
			$this->t_body->set_var("LINK_TARGET", "_blank");
		}
		
		$this->t_body->set_var("LINK_DESCRIPTION", scrubTEXT($linkDetails['description']).'</a>');
		
		$this->t_body->set_var("LINK_DESCRIPTION_ESCAPED", addslashes(htmlentities($linkDetails['description'])));

		//give the users modify buttons if they have rights
		if($this->perm >= 2)
		{
			$this->t_body->set_var("LINKID", $linkDetails['id']);
			$this->t_body->parse("mod_link_section", "mod_link"); 
		}
		else
			$this->t_body->set_var("mod_link_section", "");
	
		//show verbose info
		if($this->verbose)
		{
			// set comment
			$this->t_body->set_var("COMMENT", scrubTEXT($linkDetails['comment']));
			
			// set owner
			$fullname = formatName($linkDetails);
			$owner = "<a href=\"/search/userLookup.php?id={$linkDetails['uid']}\">$fullname</a>";

			$this->t_body->set_var("OWNER", $owner);		
		
			// set date uploaded
			$this->t_body->set_var("DATETIME", $linkDetails['submitted']);
		
			$this->t_body->parse("link_block", "link_desc");
		}
		else
		{
			$this->t_body->set_var("link_block", "");
		}
		
		$this->t_body->parse("links_block", "link_listing", true);
	}

	function outputCategory($catDetails)
	{
		// set name
		if($catDetails['code'] != "")
			$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($catDetails['name'])." (".$catDetails['code'].")");
		else
			$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($catDetails['name']));
		
		$this->t_body->set_var("CATEGORY_NAME_ESCAPED", addslashes(htmlentities($catDetails['name'])));		

		// set url
		$this->t_body->set_var("CATEGORY_LINK", "/browse.php?category=".$catDetails['id']."&verbose=".$this->verbose);
	
		//output link to homepage
		if($catDetails['template'])
			$this->t_body->set_var("HOMEPAGE", "<a id=\"home\" href=\"/homepage.php?category=".$catDetails['id']."\" title=\"homepage\">homepage</a>");
		else
			$this->t_body->set_var("HOMEPAGE", "");
			
		//give the users modify buttons if they have rights
		if($this->perm >= 2)
		{
			$this->t_body->set_var("CATID", $catDetails['id']);
			$this->t_body->parse("mod_cat_section", "mod_cat"); 
		}
		else
			$this->t_body->set_var("mod_cat_section", "");

		$this->t_body->parse("cats_block", "category_listing", true);
	}
}

//setup verbose flag, default is not verbose
if($_GET['verbose'] == 1)
	$verbose = 1;
else	
	$verbose = 0;

if(is_numeric($_GET['category']))
	$myPage = new OutPut($_GET['category'], $verbose);
else
	$myPage = new OutPut(0, $verbose);
	
$myPage->outputAll();
$myPage->showPage();


?>
