<?php
$this->setBlock($componentHandle, 'rss_stories', 'rss_block');
$this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

$rss = new Alaress_Rss_Stream($config['url']);
$rss->setLimit($config['size']);

$allRss = $rss->stream();

//$rss = new Xml($config['url'], RSS_cache_time);

//$this->setVar('RSS_NAME', !empty($rss->channel->title) ? Scrub::text($rss->channel->title) : 'Please enter a feed URL');
//$this->setVar('RSS_LINK', $rss->channel->link);
$this->setVar("rss_block", '');

if (count($allRss)) {
	$j = 0;

	foreach($allRss as $currentRss) {
		$j++;
		$this->setVar(array(
            'ON_OFF'    => ++$j%2 ? '' : 'dark',
            'RSS_TITLE' => Scrub::text($currentRss['title']),
            'RSS_URL'   => base64_encode($currentRss['link']),
            'RSS_BRIEF' => Scrub::html($currentRss['description']),
		));

		/* If the item has a pubDate
		 */
		if (strlen($currentRss['pubDate'])) {
			$postedDate = new Alaress_Date_Relative(strtotime($currentRss['pubDate']), 'U');

            $this->setVar(array(
				'RSS_POSTED_DATE' => $postedDate->getRelativeTime(),
			));

			$this->parse('rss_date_block', 'rss_date');
		}
		else {
            $this->setVar('rss_date_block', '');
        }

		/* Parse the rss item row
		 */
		$this->parse('rss_block', 'rss_stories', true);
	}
}
else {
    $this->setVar('rss_block', '');
}

