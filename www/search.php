<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays results of a search

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->db = new dbManager;
	}

	function searchResult()
	{
		$this->t_body->set_file("tplBODY", "search/search_result.htm");
		$this->t_body->set_var("TITLE", HEADING." - Search Result");
		
		//category output
		$this->t_body->set_block("tplBODY", "cat_listing", "cat_block");
		
		//fileoutput
		$this->t_body->set_block("tplBODY", "file_listing", "files_block");
		$this->t_body->set_block("file_listing", "file_desc", "file_block");
		
		//linkoutput
		$this->t_body->set_block("tplBODY", "link_listing", "links_block");
		$this->t_body->set_block("link_listing", "link_desc", "link_block");
		
		include("include/page.php");
	}
	
	function advancedSearch()
	{
		$this->t_body->set_file("tplBODY", "search/adSearch.htm");
		$this->t_body->set_var("TITLE", HEADING." - Advanced Search");
		include("include/page.php");
	}
	

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function listSearch($keyword, $type=6, $sort=2)
	{
		if(strlen($keyword) > 3) {
			$tokens = $this->stringTokenise(stripslashes(trim($keyword)));
			if(count($tokens) > 0)
			{
				$this->catSearch($tokens);
				$this->fileSearch($tokens, $type, $sort);
				$this->linkSearch($tokens, $type, $sort);
			}
			else
				error(45); //not enough tokens to perform search
		}
		else {
			error(45);
		}
	}

	function stringTokenise($str)
	{
		$str = preg_replace("/[\{\}\[\]\(\)]/", "", $str);

		//toke till we cant toke no more or till we hit 30
		for($x = 0;$str != "" && $x < 30; $x++ )
		{
			$str = trim($str); 
			$c = substr($str, 0, 1); //get the next char
			if($c == "\"" || $c == "'")
            		{	
				preg_match("/^$c([^$c]*)$c/", $str, $match);
				$val = preg_quote($match[1]);
				$str = preg_replace("/^$c$val$c/", "", $str);
			}
			else
			{
				preg_match("/^([^\s\(\)\"\']+)/", $str, $match);
				$val = preg_quote($match[1]);
				$str = preg_replace("/^$val/", "", $str);
			}
			//cut out string from search smaller than 2 chars
			if(strlen($match[1]) > 1)
			{
				$token[$x] = $match[1];
			}
 		}
		return $token;
	}

	function tokenToQuery($tokenArray, $column, $op)
	{
		//tokenArray is array of search tokens
		//column is the column the tokens should be compared against
		//op is the operator AND/OR/+		
		
		//first item has no op to begin with
		$query = "$column LIKE '%".mysql_escape_string(array_shift($tokenArray))."%'";

		foreach($tokenArray as $token)
		{
			$query .= " $op $column LIKE '%".mysql_escape_string($token)."%'";
		} 
		
		return $query;

	}

	function catSearch($token)
	{
		//this query will get all categories that the user can see
		$query = "SELECT name, id, template FROM category WHERE ".$this->tokenToQuery($token, 'name', 'OR'). 
		" OR ".$this->tokenToQuery($token, 'code', 'OR')." ORDER BY name";

		$result = $this->db->query($query);

		while($queryArray = $this->db->getNextArray($result) )
		{
			//only output if they have access
			if(perm($queryArray['id'], 1) >= 1)
			{
				$this->outputCat($queryArray);
				$count++;
			}	
		}

		//if not categories output just blank out the var
		if($count == 0){$this->t_body->set_var("cat_block", "");}


		$this->db->dbEndQuery;
	}

	function fileSearch($token, $type, $sort)
	{
		//this query will get all files that the user can see
		$query = "SELECT f.id, f.realname, inline, iconFile, DATE_FORMAT(f.submitted, '".FILE_date_format."') AS submitted, 
		user.title, user.id as uid, user.firstname, user.lastname, f.description,
		f.comment, category.name, category.id as cid, category.code, file_size, f.mime_type
		FROM (file as f, user, category) LEFT JOIN allowedFileTypes ON mime = f.mime_type WHERE f.owner=user.id AND f.category = 
category.id AND f.deleted = '0'";
		//do the query based on search type
		switch ($type)
		{
		//description search
			case 1:
			$query .= " AND (".$this->tokenToQuery($token, "f.description", "OR").")";
			break;

			//filename search
			case 2:
			$query .= " AND (".$this->tokenToQuery($token, "f.realname", "OR").")";
			break;

			//comment search
			case 3:
			$query .= " AND (".$this->tokenToQuery($token, "f.comment", "OR").")";
			break;

			//category search
			case 4:
			$query .= " AND (".$this->tokenToQuery($token, "category.name", "OR").")";
			break;

			//author search
			case 5:
			$query .= " AND (".$this->tokenToQuery($token, "user.username", "OR").
				  " OR ".$this->tokenToQuery($token, "fullname", "OR").")";
			break;
			//search all
			default:
			$query .= " AND (".$this->tokenToQuery($token, "f.description", "OR").
				" OR ".$this->tokenToQuery($token, "f.realname", "OR").
				" OR ".$this->tokenToQuery($token, "f.comment", "OR").
				" OR ".$this->tokenToQuery($token, "user.username", "OR").
				" OR ".$this->tokenToQuery($token, "CONCAT(user.firstname, ' ', user.lastname)", "OR").")";
			break;
		}

		//sort the data by
		switch($sort)
		{
		        //description
			case 1:
			$query .= " ORDER BY f.description ";
			break;

			//submission date
			case 2:
			$query .= " ORDER BY f.submitted DESC";
			break;

			//author name
			case 3:
			$query .= " ORDER BY f.owner ";
			break;
		}
		$this->db->query($query);

		$count = 0;

		while($queryArray = $this->db->getNextArray() )
		{
			//only output if they have access
			if(perm($queryArray['cid'], 1) >= 1)
			{				
				$this->outputFile($queryArray);
				$count++;
			}
		}
		if($count < 1){ $this->t_body->set_var("files_block", "");}

		$this->db->dbEndQuery();
	}

	function linkSearch($token, $type, $sort)
	{
		//this query will get all links that the user can see
		$query = "SELECT link.id, link.url, DATE_FORMAT(link.submitted, '".FILE_date_format."') AS submitted, 
		user.title, user.id as uid, user.firstname, user.firstname, user.lastname, link.description, link.comment, category.name, category.id as cid, category.code 
		FROM link, user, category WHERE link.owner = user.id AND link.category = category.id";
		//do the query based on search type
		switch ($type)
		{
			//description search
			case 1:
			$query .= " AND (".$this->tokenToQuery($token, "link.description", "OR").")";
			break;

			//url search
			case 2:
			$query .= " AND (".$this->tokenToQuery($token, "link.url", "OR").")";
			break;

 			//comment search
			case 3:
			$query .= " AND (".$this->tokenToQuery($token, "link.comment", "OR").")";
			break;

			//category search
			case 4:
			$query .= " AND (".$this->tokenToQuery($token, "category.name", "OR").")";
			break;

			//author search
			case 5:
			$query .= " AND (".$this->tokenToQuery($token, "user.username", "OR").
				" OR ".$this->tokenToQuery($token, "user.firstname", "OR").
				" OR ".$this->tokenToQuery($token, "user.lastname", "OR").")";
			break;

			//search all
			default:
			$query .= " AND (".$this->tokenToQuery($token, "link.description", "OR").
				" OR ".$this->tokenToQuery($token, "link.url", "OR").
				" OR ".$this->tokenToQuery($token, "link.comment", "OR").
				" OR ".$this->tokenToQuery($token, "user.username", "OR").
				" OR ".$this->tokenToQuery($token, "user.firstname", "OR").
				" OR ".$this->tokenToQuery($token, "user.lastname", "OR").")";
			break;
		}

		//sort the data by
		switch($sort)
		{
		        //description
			case 1:
			$query .= " ORDER BY link.description ";
			break;

			//submission date
			case 2:
			$query .= " ORDER BY link.submitted DESC";
			break;

			//author name
			case 3:
			$query .= " ORDER BY link.owner ";
			break;
		}

		$this->db->query($query);

		$count = 0;
		while($queryArray = $this->db->getNextArray() )
		{
			//only output if they have access
			if(perm($queryArray['cid'], 1) >= 1)
			{				
				$this->outputLink($queryArray);
				$count++;
			}
		}
		if($count < 1){ $this->t_body->set_var("links_block", "");}

		$this->db->dbEndQuery;
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

		// set url depending on wether it should have one and how it should be opened
		if($fileDetails['realname'] != "")
		{
			//if inline open in new window, if not give user a save dialog box
			if($fileDetails['inline']== 'true' && FILE_use_frame)
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

		// set comment
		$this->t_body->set_var("COMMENT", scrubTEXT($fileDetails['comment']));
	
		// set owner
		$fullname = formatName($fileDetails);
		$owner = "<a href=\"/search/user/{$fileDetails['uid']}\">$fullname</a>";
		
		//output owner
		$this->t_body->set_var("OWNER", $owner);
	
		// set date uploaded
		$this->t_body->set_var("DATETIME", $fileDetails['submitted']);

		//set category
		if($queryArray['code'])
			$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($fileDetails['name']).' ('.$fileDetails['code'].')');
		else
			$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($fileDetails['name']));

		$this->t_body->set_var("CATEGORY_ID", $fileDetails['cid']);

		//parse the output
		$this->t_body->parse("file_block", "file_desc");
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
			$this->t_body->set_var("LINK_URL", "/redir.php?url=".urlencode(urlencode($linkDetails['url'])));
			$this->t_body->set_var("LINK_TARGET", "_blank");
		}
		
		$this->t_body->set_var("LINK_DESCRIPTION", scrubTEXT($linkDetails['description']).'</a>');
		$this->t_body->set_var("COMMENT", scrubTEXT($linkDetails['comment']));
		
		// set owner
		$fullname = formatName($linkDetails);
		$owner = "<a href=\"/search/user/{$linkDetails['uid']}\">$fullname</a>";

		$this->t_body->set_var("OWNER", $owner);		
	
		// set date uploaded
		$this->t_body->set_var("DATETIME", $linkDetails['submitted']);
		
		//set category category
		$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($linkDetails['name']));
		$this->t_body->set_var("CATEGORY_ID", $linkDetails['cid']);
		
		//parse the output
		$this->t_body->parse("link_block", "link_desc");
		//and again?
		$this->t_body->parse("links_block", "link_listing", true);
	}

	function getCatPath($parent)
	{
		$path = "";

		while($parent != 0)
		{
			$query = "SELECT id, name, parent, code FROM category WHERE id = '".$parent."'";
			$queryArray = $this->db->getRow($query);
			
			if($queryArray['code'])
				$code = " ({$queryArray['code']})";
			else
				$code = '';
		
			$parent = $queryArray['parent'];
			if($parent == 0)
                       		$path = "<a href=\"/resources/".$queryArray['id']."\">".scrubTEXT($queryArray['name']).$code."</a>".$path;
			else			 
				$path = " > <a href=\"/resources/".$queryArray['id']."\">".scrubTEXT($queryArray['name']).$code."</a>".$path;
		}

		return $path;
        }

	
	function outputCat($catDetails)
	{
		//set description
		$this->t_body->set_var("CAT_DESCRIPTION", $this->getCatPath($catDetails['id']));
		
		//output homepage link
		if($catDetails['template'])
			$this->t_body->set_var("HOMEPAGE", "<a href=\"/homepage/".$catDetails['id']."\">homepage</a>");
		else
			$this->t_body->set_var("HOMEPAGE", "&nbsp;");
		
		//parse
		$this->t_body->parse("cat_block", "cat_listing", true);
	}

}

//first check whether a redirect was requested
if($_GET['where'] == 1 && isset($_GET['keyword']))
{
	//redirect to google
	header("Location: newWindow.php?url=redir.php?url=http://www.google.com/search?q=".$_GET['keyword']);
	exit;
}
else if($_GET['where'] == 2 && isset($_GET['keyword']))
{
	//redirect to yahoo
	header("Location: newWindow.php?url=redir.php?url=http://search.yahoo.com/bin/search?p=".$_GET['keyword']);
	exit;
}
else
{
	$myPage = new OutPut();
	if($_REQUEST['keyword'])
	{ 	
		//setup search result page
		$myPage->searchResult();
		
		//advanced search
		if($_GET['type'] && $_GET['sort'])
		{   
			$myPage->listSearch($_REQUEST['keyword'], $_GET['type'], $_GET['sort']);
		}
		else //simple keyword search
		{  
			$myPage->listSearch($_REQUEST['keyword']);
		}
	}
	else
	{
		$myPage->advancedSearch();
	}
	
	$myPage->showPage();
}
?>
