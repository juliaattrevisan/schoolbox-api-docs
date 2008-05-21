<?
		//news output
		$this->setBlock($componentHandle, "news", "news_block");

		include("subject/subjectNews.php");
		$news = new news($categoryID);
		
		$subnews = $news->getNews();
		if($subnews)
		{
			foreach($subnews as $article)
			{
				if($article['owner'] == $_SESSION['id'] || $_SESSION['su'] == 1 || CATEGORY_perms == 2)
					$this->setVar("MOD_NEWS", "<a href=\"/subject/modSubNews.php?modify=".$article['id']."\">Modify</a>");
				else
					$this->setVar("MOD_NEWS", "");
				
				$poster = formatName($article);
				
				$this->setVar("NEWS_HEADING", scrubTEXT($article['heading']));
				$this->setVar("NEWS_BODY", scrubHTML($article['body']));
				$this->setVar("EMAIL_CONTACT", "<a href=\"/search/userLookup.php?id={$article['uid']}\">$poster</a>");
				$this->setVar("POSTED_DATE", $article['datetime']);
				$this->parse("news_block", "news", true);
			}
		}
		else
		{
			$this->setVar("news_block", "");
		}

		//setup block for submission button
		$this->setBlock($componentHandle, "submit_news", "submit_news_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_news_block", "");
		else
			$this->parse("submit_news_block", "submit_news");	


?>