<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//allows users to edit there links

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");

require_once("subjectNews.php");

class AddSubNews
{
	
	function setFields($body, $heading, $modify, $code)
	{
		$this->t_body->set_var("BODY", $body);	
		$this->t_body->set_var("HEADING", $heading);
		if($modify)
		{
			$this->t_body->set_var("MODIFY", "<input type=\"hidden\" name=\"id\" value=\"$modify\">");
			$this->t_body->set_var("DELETE_NEWS", "<input type=\"submit\" name=\"delete\" value=\"Delete News\">");
			$this->set_var("LEAVE_OPTION", "<option value=0 selected>Keep existing</option>");
		}
		else
		{
			$this->t_body->set_var("MODIFY", ""); 	
			$this->t_body->set_var("DELETE_NEWS", "");
			$this->set_var("LEAVE_OPTION", ""); 	
		}
		$this->t_body->set_var("CODE", "<input type=\"hidden\" name=\"code\" value=\"$code\">");
	}
	
	function setupPage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "subjects/modSubNews.htm");
		$this->t_body->set_var("TITLE", HEADING." - Submit Subject News");
		include_once("../include/page.php");			
	}

	function showPage()
        {
                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}

$page = new AddSubNews();

if(isset($_GET['category']) && $_GET['category'])
{
	if(perm($_GET['category'], 1) > 1)
	{
		//show page for new news
		$page->setupPage();
		$page->setFields("", "", 0, $_GET['category']);
		$page->showPage();
	}
}
else if(isset($_POST['delete']) && $_POST['delete'] && isset($_POST['id']) && $_POST['id'])
{
	//delete news item
	$news = new news;
	$orginal = $news->getSingleNews($_POST['id']);
	//allow owner, su or group owner to delete
	if($_SESSION['id'] == $orginal['owner'] || $_SESSION['su'] || perm($_POST['code'], 1) == 3 )
	{

		$news->deleteNews($_POST['id']);
		localRedir("/homepage.php?category=".$_POST['code']);
	}
	else
		error(19);
}		
else if(isset($_POST['code']) && $_POST['code'] && isset($_POST['heading']) && $_POST['heading'])
{
	//modify news
	$permlevel = perm($_POST['code'], 1);

	//check they have at least write access
	if($permlevel > 1)
	{
		$news = new news;
		if(isset($_POST['id']) && $_POST['id'])
		{
			$orginal = $news->getSingleNews($_POST['id']);
			//allow owner, su or group owner to modify
			if($_SESSION['id'] == $orginal['owner'] || $_SESSION['su'] || $permlevel == 3)
			{
				//modify news
				$news->modNews($_POST['id'], $_POST['code'], $_POST['heading'], $_POST['expires'], $_POST['body']);
			}	
			else
				error(23);
		}
		else
			$news->addNews($_POST['code'], $_SESSION['id'], $_POST['heading'], $_POST['expires'], $_POST['body']);		
	
		//redirect to subject page
		localRedir("/homepage.php?category=".$_POST['code']);
	}
	else
		error(19);
}
else if(isset($_GET['modify']) && $_GET['modify'])
{
	//get a single news
	$news = new news;
	$page->setupPage();
	$article = $news->getSingleNews($_GET['modify']);

	//security check if owner or is superuser or is group owner
	if($_SESSION['id'] == $article['owner'] || $_SESSION['su'] || perm($article['code'], 1) == 3)
	{
		$page->setFields($article['body'], $article['heading'], $_GET['modify'], $article['code']);
		$page->showPage();
	}
	else
		error(23);
}
else
{
	error(23);	
}
?>
