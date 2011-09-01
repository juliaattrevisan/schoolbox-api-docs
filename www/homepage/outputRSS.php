<?php
$this->setBlock($componentHandle, 'rss_stories', 'rss_block');
$this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

require_once SITE_root.'/library/xml.php';
$rss = new Xml($config['url'], RSS_cache_time);

$this->setVar('RSS_NAME', !empty($rss->channel->title) ? scrubTEXT($rss->channel->title) : 'Please enter a feed URL');
$this->setVar('RSS_LINK', $rss->channel->link);
$this->setVar("rss_block", '');

if ($rss->channel) {
    $j = 0;

    foreach($rss->channel->item as $i => $item) {
        $this->setVar(array(
            'ON_OFF'    => ++$j%2 ? '' : 'dark',
            'RSS_TITLE' => scrubTEXT($item->title),
            'RSS_URL'   => base64_encode($item->link),
            'RSS_BRIEF' => scrubHTML($item->description)
        ));

		$postedDate = new Alaress_Date_Relative($item->pubDate);

        if ($item->pubDate) {
            $this->setVar(array(
				'RSS_POSTED_DATE' => $postedDate->getRelativeTime()
			));

            $this->parse('rss_date_block', 'rss_date');
        }
		else {
            $this->setVar('rss_date_block', '');
        }

        $this->parse("rss_block", "rss_stories", true);

        if ($j == $config['size'] && $config['size'] != 0) {
            break;
		}
    }
}
else {
    $this->setVar("rss_block", '');
}
