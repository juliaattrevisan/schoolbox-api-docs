<?php
$this->setBlock($componentHandle, 'rss_stories', 'rss_block');
$this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

require_once('include/lastRSS.php');
$rss = new lastRSS; 

$rss->cache_dir = RSS_cache_dir; 
$rss->cache_time = RSS_cache_time; // one hour
$rss->stripHTML = true;
$rss->items_count = 10;
$headlineList = $rss->get($config['url']);
	
$this->setVar('RSS_NAME', !empty($headlineList['title']) ? scrubTEXT($headlineList['title']) : 'Please enter a feed URL');
$this->setVar('RSS_LINK', $headlineList['link']);

//output headlines
if(is_array($headlineList['items']) ) {
	for($i = 0; $headlineList['items'][$i]['title'] && $i < $config['size']; $i++ ) {	
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		$this->setVar("RSS_TITLE", scrubTEXT($headlineList['items'][$i]['title']));
		$this->setVar("RSS_URL", scrubHTML($headlineList['items'][$i]['link'])); 
		$this->setVar("RSS_BRIEF", scrubTEXT($headlineList['items'][$i]['description']));
		
		if($headlineList['items'][$i]['pubDate']) {
			$this->setVar('RSS_POSTED_DATE', getRelativeTime($headlineList['items'][$i]['pubDate']));
			$this->parse('rss_date_block', 'rss_date');
		}
		else
			$this->setVar('rss_date_block', '');
		
		
		
		$this->parse("rss_block", "rss_stories", true);
	}
}
else
	$this->setVar("rss_block", '');
		
?>