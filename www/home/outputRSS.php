<?php

$this->setBlock($component['name'], "rss_stories", "rss_block");

require_once('include/lastRSS.php');
$rss = new lastRSS; 

$rss->cache_dir = RSS_cache_dir; 
$rss->cache_time = RSS_cache_time; // one hour
$rss->items_limit = RSS_limit;
$headlineList = $rss->get(RSS_url);
	
$this->setVar('RSS_NAME', $headlineList['title']);

//output headlines
if(is_array($headlineList['items']) ) {
	foreach($headlineList['items'] as $item) {
		$this->setVar("RSS_TITLE", scrubTEXT($item['title']));
		$this->setVar("RSS_URL", scrubHTML($item['link'])); 
		$this->setVar("RSS_BRIEF", scrubTEXT($item['description']));
		$this->parse("rss_block", "rss_stories", true);
	}
	
	$this->parse('POS'.$component['position'], $component['name']);
}
else
	$this->setVar('POS'.$component['position'], '');

?>
