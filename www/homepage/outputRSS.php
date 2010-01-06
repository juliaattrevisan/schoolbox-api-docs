<?php
$this->setBlock($componentHandle, 'rss_stories', 'rss_block');
$this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

require_once('../library/xml.php');
$rss = new XML($config['url'], RSS_cache_time);

$this->setVar('RSS_NAME', !empty($rss->channel->title) ? scrubTEXT($rss->channel->title) : 'Please enter a feed URL');
$this->setVar('RSS_LINK', $rss->channel->link);

if($rss->channel) {
    foreach($rss->channel->item as $i => $item) {
        $this->setVar(array(
            'ON_OFF'    => ++$j%2 ? '' : 'dark',
            'RSS_TITLE' => scrubTEXT($item->title),
            'RSS_URL'   => scrubHTML($item->link),
            'RSS_BRIEF' => scrubHTML($item->description)
        ));

        if($item->pubDate) {
            $this->setVar('RSS_POSTED_DATE', getRelativeTime($item->pubDate));
            $this->parse('rss_date_block', 'rss_date');
        } else {
            $this->setVar('rss_date_block', '');
        }
            
        $this->parse("rss_block", "rss_stories", true);

        if($j == $config['size'] && $config['size'] != 0)
            break;
    }

    $this->parse("rss_block", "rss_stories", true);
} else {
    $this->setVar("rss_block", '');
}
		
?>