<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays results of a search

require_once("include/session.php");
require_once("include/include.php");
require_once("include/fileTypes.php");
require_once("include/html-parse.php");
require_once("include/permission.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "search_result.htm");

		$this->t_body->set_var("TITLE", HEADING." - Search Result");
		
		$this->db = new dbManager;
		
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

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function listSearch($keyword, $type=6, $sort=2)
	{
		$tokens = $this->stringTokenise(stripslashes($keyword));
		if(count($tokens) > 0)
		{
			$this->catSearch($tokens);
			$this->fileSearch($tokens, $type, $sort);
			$this->linkSearch($tokens, $type, $sort);
		}
		else
			error(45); //not enough tokens to perform search
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
			//cut out string from search smaller than 3 chars
			if(strlen($match[1]) > 3)
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
		$query = "$column LIKE '%".array_shift($tokenArray)."%'";

		foreach($tokenArray as $token)
		{
			$query .= " $op $column LIKE '%$token%'";
		} 
		
		return $query;

	}

	function catSearch($token)
	{
		//this query will get all categories that the user can see
		$query = "SELECT name, id FROM category WHERE ".$this->tokenToQuery($token, "category.name", "OR"). 
		"ORDER BY name";

		$result = $this->db->dbDoQuery($query);

		while($queryArray = $this->db->dbGetNextArray($result) )
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
		$query = "SELECT file.id, file.realname, file.mime_type, DATE_FORMAT(file.submitted, '%W %M %e @ %l:%i%p') AS submitted, 
		user.username, user.firstname, user.lastname, user.local_email, user.alt_email, file.description,
		file.comment, category.name, category.id as cid 
		FROM file, user, category WHERE file.owner=user.id AND file.category = category.id AND file.deleted = '0'";
		//do the query based on search type
		switch ($type)
		{
		//description search
			case 1:
			$query .= " AND (".$this->tokenToQuery($token, "file.description", "OR").")";
			break;

			//filename search
			case 2:
			$query .= " AND (".$this->tokenToQuery($token, "file.realname", "OR").")";
			break;

			//comment search
			case 3:
			$query .= " AND (".$this->tokenToQuery($token, "file.comment", "OR").")";
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
			$query .= " AND (".$this->tokenToQuery($token, "file.description", "OR").
				" OR ".$this->tokenToQuery($token, "file.realname", "OR").
				" OR ".$this->tokenToQuery($token, "file.comment", "OR").
				" OR ".$this->tokenToQuery($token, "user.username", "OR").
				" OR ".$this->tokenToQuery($token, "CONCAT(user.firstname, ' ', user.lastname)", "OR").")";
			break;
		}

		//sort the data by
		switch($sort)
		{
		        //description
			case 1:
			$query .= " ORDER BY file.description ";
			break;

			//submission date
			case 2:
			$query .= " ORDER BY file.submitted DESC";
			break;

			//author name
			case 3:
			$query .= " ORDER BY file.owner ";
			break;
		}
		$this->db->dbDoQuery($query);

		$count = 0;

		while($queryArray = $this->db->dbGetNextArray() )
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
		//this query will get all files that the user can see
		$query = "SELECT link.id, link.url, DATE_FORMAT(link.submitted, '%W %M %e @ %l:%i%p') AS submitted, 
		user.username, user.firstname, user.lastname, user.local_email, user.alt_email, link.description, link.comment, category.name, category.id as cid 
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

		$this->db->dbDoQuery($query);

		$count = 0;
		while($queryArray = $this->db->dbGetNextArray() )
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

	function outputFile($queryArray)
	{
		global $allowedFileTypes;
		// correction for empty description
		if ($queryArray['comment'] == "") { $queryArray['comment'] = "No comment available"; }

		// set filename for filesize() call below
		$filename = FILE_directory . "/". $queryArray['id'] . ".dat";

                // set file size
                if($queryArray['realname'] != "")
                {
                        $filesize = @filesize($filename);
                        if($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
                        {
                                $this->t_body->set_var("SIZE", "");
                                $queryArray['realname'] = "";
                        }
                        else
                                $this->t_body->set_var("SIZE", bytesToString($filesize));
                }
                else
                        $this->t_body->set_var("SIZE", "");

                //set image
                if(isset($allowedFileTypes[$queryArray['mime_type']][0]))
                        $this->t_body->set_var("FILE_IMAGE", "/images/mime/".$allowedFileTypes[$queryArray['mime_type']][0]);
                else
                        $this->t_body->set_var("FILE_IMAGE", "/images/mime/application-x-none.png");

		// set description
                if($queryArray['realname'] != "")
                {
                        //if inline open in new window, if not give user a save dialog box
			if($allowedFileTypes[$queryArray['mime_type']][1] == 1)
				$fileurl = "<a href=\"/sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
                        else
				$fileurl = "<a href=\"/send.php?id=".$queryArray['id']."&save=1\">".scrubTEXT($queryArray['description'])."</a>";

                        $this->t_body->set_var("FILE_DESCRIPTION", $fileurl);
                }
                else
                        $this->t_body->set_var("FILE_DESCRIPTION", scrubTEXT($queryArray['description']));

		// set comment
		$this->t_body->set_var("COMMENT", scrubTEXT($queryArray['comment']));

		// set owner
               $fullname = $queryArray['firstname']." ".$queryArray['lastname'];

                if($queryArray['local_email'])
                        $email = "<a href=\"".$_SESSION['mailhandle'].$queryArray['username']."@".MAIL_address."\">$fullname</a>";
                else if($queryArray['alt_email'])
                        $email = "<a href=\"".$_SESSION['mailhandle'].$queryArray['alt_email']."\">$fullname</a>";
                else
                        $email = $fullname;

		$this->t_body->set_var("OWNER", $email); 

		// set date uploaded
		$this->t_body->set_var("DATETIME", $queryArray['submitted']);

		//set category
		$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($queryArray['name']));
		$this->t_body->set_var("CATEGORY_ID", $queryArray['cid']);

		//parse the output
		$this->t_body->parse("file_block", "file_desc");
		$this->t_body->parse("files_block", "file_listing", true);
	}

	function outputLink($queryArray)
	{
		// correction for empty description
		if ($queryArray['comment'] == "") { $queryArray['comment'] = "No comment available"; }

                if(isLocalURL($queryArray['url']))
                {
                        $this->t_body->set_var("LINK_HTML", "<a href=\"".$queryArray['url']."\">
                                ".scrubTEXT($queryArray['description'])."</a>");
                }
                else
                {
                        $this->t_body->set_var("LINK_HTML", "<a href=\"redir.php?url=".$queryArray['url']."\" 
                                target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>");
                }


		$this->t_body->set_var("COMMENT", scrubTEXT($queryArray['comment']));

		// set owner
                $fullname = $queryArray['firstname']." ".$queryArray['lastname'];

                if($queryArray['local_email'])
                        $email = "<a href=\"".$_SESSION['mailhandle'].$queryArray['username']."@".MAIL_address."\">$fullname</a>";
                else if($queryArray['alt_email'])
                        $email = "<a href=\"".$_SESSION['mailhandle'].$queryArray['alt_email']."\">$fullname</a>";
                else
                        $email = $fullname;

		$this->t_body->set_var("OWNER", $email); 
		// set date uploaded
		$this->t_body->set_var("DATETIME", $queryArray['submitted']);
		//set category category
		$this->t_body->set_var("CATEGORY_NAME", scrubTEXT($queryArray['name']));
		$this->t_body->set_var("CATEGORY_ID", $queryArray['cid']);
		
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
			$query = "SELECT id, name, parent FROM category WHERE id = '".$parent."'";
			$queryArray = $this->db->dbGetSingleRow($query);

			$parent = $queryArray['parent'];
			if($parent == 0)
                       		$path = "<a href=\"browse.php?category=".$queryArray['id']."\">".scrubTEXT($queryArray['name'])."</a>".$path;
			else			 
				$path = " > <a href=\"browse.php?category=".$queryArray['id']."\">".scrubTEXT($queryArray['name'])."</a>".$path;
		}

		return $path;
        }

	
	function outputCat($queryArray)
	{
		//set description
		$this->t_body->set_var("CAT_DESCRIPTION", $this->getCatPath($queryArray['id']));
		
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
	if( (isset($_GET['type'])) && (isset($_GET['sort'])) && (isset($_GET['keyword'])) && $_GET['type'] && 
		$_GET['sort'] && $_GET['keyword'])
	{    //advanced search
		$myPage->listSearch($_GET['keyword'], $_GET['type'], $_GET['sort']);
	}
	else if( (isset($_GET['keyword'])) && $_GET['keyword'])
	{  //simple keyword search
		$myPage->listSearch($_GET['keyword']);
	}
	else
		error(2);

	$myPage->showPage();
}
?>
