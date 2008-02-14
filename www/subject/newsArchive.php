<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//Allows the user to see all the news for a subject, including expired news

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");

require_once("subjectNews.php");


class NewsArchive {

	function NewsArchive($category) {
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "subjects/newsArchive.htm");
		$this->t_body->set_var("TITLE", HEADING." - Subject News Archive");
		include_once("../include/page.php");
	
		$this->t_body->set_block("tplBODY", "submit_news", "submit_news_block");
		$this->t_body->set_block("tplBODY", "news", "news_block");
	
		$this->service = HOMEPAGESERVICE;
		$this->category = $category;
		$this->checkPerms();
		
		//hide add button
		if($this->perm > 2)
			$this->t_body->parse("submit_news_block", "submit_news");
		else
			$this->t_body->set_var("submit_news_block","");
		
		//output category name
		$query = "SELECT name FROM category WHERE id = $category";
		$this->dbConnection = new dbManager(SQL_db, 1);
		$category = $this->dbConnection->dbGetSingleRow($query);
		
		$this->t_body->set_var("CATEGORY_NAME", $category['name']);
	}
	
	function outputNews() 
	{
		$news = new news($this->category);
		
		$subnews = $news->getNewsArchive();
		if($subnews)
		{
			foreach($subnews as $article)
			{
				if($article['owner'] == $_SESSION['id'] || $_SESSION['su'] == 1 || $this->perm == 2)
					$this->t_body->set_var("MOD_NEWS", "<a href=\"/subject/modSubNews.php?modify=".$article['id']."\">Modify</a>");
				else
					$this->t_body->set_var("MOD_NEWS", "");
				
				$poster = formatName($article);
				
				$this->t_body->set_var("NEWS_HEADING", scrubTEXT($article['heading']));
				$this->t_body->set_var("NEWS_BODY", scrubHTML($article['body']));
				$this->t_body->set_var("EMAIL_CONTACT", "<a href=\"/search/userLookup.php?id={$article['uid']}\">$poster</a>");
				$this->t_body->set_var("POSTED_DATE", $article['datetime']);
				$this->t_body->parse("news_block", "news", true);
			}
		}
		else
		{
			$this->t_body->set_var("news_block", "");
		}
	}
	
	function checkPerms()
	{
		$this->perm = perm($this->category, $this->service);
		if($this->perm < 1)
			error(14);
	}
	
	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}


if(is_numeric($_GET['category'])) {
	$page = new NewsArchive($_GET['category']);
	$page->outputNews();
	$page->showPage();
}
else
	error(23);


?>