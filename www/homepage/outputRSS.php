<?php
//reset ON_OFF
$j=0;

		$this->setBlock($componentHandle, "rss_stories", "rss_block");
		
		require_once('include/lastRSS.php');
		$rss = new lastRSS; 
		
		$rss->cache_dir = RSS_cache_dir; 
  		$rss->cache_time = RSS_cache_time; // one hour
  		$rss->stripHTML = true;
		$rss->items_limit = $config['size'];
		$headlineList = $rss->get($config['url']);
			
		$this->setVar('RSS_NAME', !empty($headlineList['title']) ? $headlineList['title'] : 'RSS Feed');
		
		//output headlines
		if(is_array($headlineList['items']) ) {
			foreach($headlineList['items'] as $item) {
				$this->setVar("RSS_TITLE", $item['title']);
				$this->setVar("RSS_URL", $item['link']); 
				$this->setVar("RSS_BRIEF", $item['description']);
				$this->parse("rss_block", "rss_stories", true);
				$this->setVar('ON_OFF', $j++%2? '' : 'dark');
	    	}
		}
		else
			$this->setVar("rss_block", '');
		
?>