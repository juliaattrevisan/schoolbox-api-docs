<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//allows users to edit there links

require("session.php");
include("include.php");

class EditMyLinks
{
	
	function setMyLinks()
	{
		$db = new dbManager;
		$db->dbConnect();
				
		$this->t_body->set_block("tplBODY", "link_block", "editlinks");

		$query = "SELECT id, url, description FROM bookmarks WHERE owner = '".$_SESSION['id']."'";
		$db->dbDoQuery($query);
		
		if($db->dbCountRows() > 0)
		{
			while($link = $db->dbGetNextArray())
			{
				$this->t_body->set_var("URL", $link["url"]);
				$this->t_body->set_var("DESC", $link["description"]);
				$this->t_body->set_var("ID", $link["id"]);
				$this->t_body->parse("editlinks", "link_block", true);
			}
		}
		else 
		{
			$this->t_body->set_var("editlinks", "");
		}
	}

	function submitLink($url, $description)
	{
		
		$db = new dbManager;
		$db->dbConnect();
	
		//escape intput
		$url = mysql_escape_string($url);
		$description = mysql_escape_string($description);
	
		//append http:// if it wasnt included
                if(strncasecmp($url,"http://", 7) && strncasecmp($url, "https://", 8))           
                        $url = "http://".$url;		

		$query = "INSERT INTO bookmarks (owner, url, description) 
			VALUES ('".$_SESSION['id']."','$url', '$description')";

		$db->dbDoQuery($query);
		
	}
	
	function deleteLink($id)
	{
		//delete only links that belong to the deletor
		$db = new dbManager;
		$db->dbConnect();
	
		$query = "DELETE FROM bookmarks WHERE id = '$id' AND owner = '".$_SESSION['id']."'";
		
		$db->dbDoQuery($query);
	}
	

	function showPage()
        {
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "mylinks.htm");
		$this->t_body->set_var("TITLE", HEADING." - Edit My Links");
		include("page.php");

		$this->setMyLinks();

                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}

$page = new EditMyLinks();

if(isset($_POST['url']) && $_POST['url'] && isset($_POST['description']) && $_POST['description'])
{
	$page->submitLink($_POST['url'], $_POST['description']);
}
else if(isset($_GET['delete']) && $_GET['delete'])
{
	$page->deleteLink($_GET['delete']);
}

$page->showPage();

?>
