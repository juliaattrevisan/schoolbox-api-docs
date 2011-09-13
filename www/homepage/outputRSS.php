<?php
$this->setBlock($componentHandle, 'rss_stories', 'rss_block');
$this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

/* Initialise feed
 */
$rss = new Alaress_Rss_Stream($config['url']);
$rss->setLimit($config['size']);
$rss->stream();

/* Set up feed info
 */
$this->setVar(array(
	'RSS_NAME' => $rss->getTitle(),
	'RSS_LINK' => $rss->getLink(),
));

$this->setVar('rss_block', '');

/* Process each item in feed
 */
$allRss = $rss->getOutput();

if (count($allRss)) {
	$j = 0;

	foreach($allRss as $currentRss) {
		$this->setVar(array(
			'ON_OFF' => ++$j % 2 ? '' : 'dark',
			'RSS_TITLE' => Scrub::text($currentRss['title']),
			'RSS_URL' => base64_encode($currentRss['link']),
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

		$j++;
	}
}
else {
    $this->setVar('rss_block', '');
}

