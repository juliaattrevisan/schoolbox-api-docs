<?php
$this->setJavascript('/javascript/swfobject.js');
$this->setBlock($componentHandle, 'podcast_list', 'podcast_list_block');
$this->setVar('podcast_list_block', '');

require_once SITE_root.'/library/Xml.php';
require_once SITE_root.'/include/FileType.php';

if(!empty($config['url'])) {
    $xml = new Xml($config['url']);

    if ($xml->channel) {
        $this->setVar('PODCAST_COMPONENT_TITLE', Scrub::text($xml->channel->title));
        $j = 0;

	    foreach ($xml->channel->item as $item) {
			$fileType = new FileType($item->enclosure->attributes()->url, $item->enclosure->attributes()->type);

			if ($fileType->isAudio()) {
				$this->setVar(array(
					'PODCAST_URL' => Scrub::text($item->enclosure['url']),
					'PODCAST_TITLE' => Scrub::text($item->title),
					'PODCAST_DESCRIPTION' => Scrub::text($item->description),
					'ON_OFF'  => ++$j%2 ? '' : 'dark',
					'PODCAST_ID' => md5($item->title),
				));

				$this->parse('podcast_list_block', 'podcast_list', true);
			}
            else {
                $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - URL is not a valid audio podcast');
                $this->setVar('podcast_list_block', '');
                break;
            }

            if ($j == $config['size'] || $config['url'] != 0) {
                break;
			}
        }
    }
    else {
        $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - URL is not a valid audio podcast');
        $this->setVar('podcast_list_block', '');
    }
}
else {
    $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - Please set URL');
    $this->setVar('podcast_list_block', '');
}
