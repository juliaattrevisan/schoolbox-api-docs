<?php

$this->setJavascript('/javascript/swfobject.js');
$this->setBlock($componentHandle, 'podcast_list', 'podcast_list_block');
/*
$config = array('url'	=> '',
				'limit' => 0,
				
				);
print serialize($config);
*/
if (!empty($config['url']) && $xml = simplexml_load_file( $config['url'])) {

	$this->setVar('PODCAST_COMPONENT_TITLE', scrubText((string)$xml->channel->title));
	$k = 0;
	
	foreach ($xml->channel->item as $item) {
		if (!$config['size'] || $k < $config['size']) {
			$k++;
			$output = array('PODCAST_URL'			=> (string)$item->enclosure['url'],
							'PODCAST_TITLE'			=> scrubText((string)$item->title),
							'PODCAST_DESCRIPTION'	=> scrubText((string)$item->description),
							'ON_OFF'				=> ++$j%2 ? '' : 'dark',
							'PODCAST_ID'			=> md5((string)$item->title)
							);
							
			$this->setVar($output);
			$this->parse('podcast_list_block', 'podcast_list', true);
		}
	}
} else {
	$this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - Please set URL');
	$this->setVar('podcast_list_block', '');
}


?>