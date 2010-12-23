<?php

$this->setJavascript('/javascript/swfobject.js');
$this->setBlock($componentHandle, 'podcast_list', 'podcast_list_block');
$this->setVar('podcast_list_block', '');

require_once('../library/xml.php');

if(!empty($config['url'])) {
    $xml = new XML($config['url']);
    if( $xml->channel) {
        $this->setVar('PODCAST_COMPONENT_TITLE', scrubText($xml->channel->title));

        foreach ($xml->channel->item as $item) {
            $this->setVar(array(
                'PODCAST_URL'           => scrubText($item->enclosure['url']),
                'PODCAST_TITLE'         => scrubText($item->title),
                'PODCAST_DESCRIPTION'   => scrubText($item->description),
                'ON_OFF'                => ++$j%2 ? '' : 'dark',
                'PODCAST_ID'            => md5($item->title)
            ));

            $this->parse('podcast_list_block', 'podcast_list', true);

            if($j == $config['size'] || $config['url'] != 0)
                break;
        }
    }
    else {
        $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - URL is not a podcast');
        $this->setVar('podcast_list_block', '');
    }
} else {
    $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - Please set URL');
    $this->setVar('podcast_list_block', '');
}

?>