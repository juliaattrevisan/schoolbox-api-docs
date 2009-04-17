<?php
$j = 0;
$this->setBlock($component['name'], "rss_stories", "rss_block");

require_once('include/lastRSS.php');
$rss = new lastRSS; 

$rss->cache_dir = RSS_cache_dir; 
$rss->cache_time = RSS_cache_time; // one hour
$rss->items_limit = RSS_limit;
$headlineList = $rss->get(RSS_url);
	
$this->setVar('RSS_NAME', $headlineList['title']);
$this->setVar('RSS_LINK', $headlineList['link']);

//output headlines
if(is_array($headlineList['items']) ) {
	for($i = 0; $headlineList['items'][$i]['title'] && $i < RSS_limit; $i++ ) {			
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark');		
		$this->setVar("RSS_TITLE", scrubTEXT($headlineList['items'][$i]['title']));		
		$this->setVar("RSS_URL", scrubHTML($headlineList['items'][$i]['link'])); 		
		$this->parse("rss_block", "rss_stories", true);
	}
	
	$this->parse('POS'.$component['position'], $component['name']);
}
else
	$this->setVar('POS'.$component['position'], '');

?>
