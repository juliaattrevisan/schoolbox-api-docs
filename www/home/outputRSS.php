<?php
$this->setBlock($component['name'], "rss_stories", "rss_block");

require_once('../library/xml.php');
$rss = new XML(RSS_url, RSS_cache_time);
	
$this->setVar('RSS_NAME', $rss->channel->title);
$this->setVar('RSS_LINK', $rss->channel->link);

if($rss->channel) {
    foreach($rss->channel->item as $i => $item) {
        $this->setVar(array(
            'ON_OFF'    => ++$j%2 ? '' : 'dark',
            'RSS_TITLE' => scrubTEXT($item->title),
            'RSS_URL'   => scrubHTML($item->link)
        ));

        $this->parse("rss_block", "rss_stories", true);

        if($j == RSS_limit)
            break;
    }

    $this->parse('POS'.$component['position'], $component['name']);
} else {
    $this->setVar('POS'.$component['position'], '');
}

?>