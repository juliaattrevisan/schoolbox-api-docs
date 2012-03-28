<?php
/*
 * outputPodcast
 *
 * Output a list of podcasts from xml feeds, or a url directly
 *
 * Useful test links:
 * http://www.abc.net.au/triplej/live/rss/podcast.xml
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$this->setJavascript('/javascript/swfobject.js');
$this->setBlock($componentHandle, 'podcast_list', 'podcast_list_block');
$this->setVar('podcast_list_block', '');

/* Tick for checkbox which controls whether to show the Posted By details
 */
if ($config['compactMode']) {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => 'compact-mode',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
	));
}
else {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => '',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
	));
}

require_once SITE_root.'/library/Xml.php';
require_once SITE_root.'/include/FileType.php';

if (!empty($config['url'])) {
    $xml = new Xml($config['url']);

	/* Podcasts from xml feed
	 */
    if ($xml->channel) {
        $this->setVar('PODCAST_COMPONENT_TITLE', Scrub::text($xml->channel->title));

		/* Iterate through each item in the channel
		 */
		if (strlen(trim($config['url']))) {
			for ($i = 0, $j = 0; $i < count($xml->channel->item); $i++) {

				/* Exit loop once maximum displayable is reached
				 * Otherwise continue iterating
				 */
				if (($j == $config['size']) && ($config['size'] > 0)) {
					break;
				}
				else {
					$item = $xml->channel->item[$i];

					/* Weird Alex fix
					 */
					if (!isset($item->enclosure)) {
						continue;
					}

					/* Get file type information
					 */
					$fileType = new FileType($item->enclosure->attributes()->url, $item->enclosure->attributes()->type);

					if ($fileType->isAudio() || $fileType->isVideo()) {
						$this->setVar(array(
							'PODCAST_URL' => Scrub::text($item->enclosure['url']),
							'PODCAST_TITLE' => Scrub::text($item->title),
							'PODCAST_DESCRIPTION' => Scrub::text($item->description),
							'ON_OFF'  => $j%2 ? '' : 'dark',
							'PODCAST_ID' => md5($item->title),
						));

						$this->parse('podcast_list_block', 'podcast_list', true);

						/* Unravelled hidden pre-increment from a conditional setVar()
						 * and turned it into a conventional post-increment
						 */
						$j++;
					}

				}
			}
		}

		/* If there are no podcasts in the list
		 */
		if (!$j) {
            $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - URL does not contain any valid audio podcasts');
            $this->setVar('podcast_list_block', '');
        }
    }
}
else {
    $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - Please set URL');
    $this->setVar('podcast_list_block', '');
}
