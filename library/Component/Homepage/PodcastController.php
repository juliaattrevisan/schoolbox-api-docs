<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_PodcastController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'podcast')) {
            return false;
        }

        $this->setBlock($templateHandle, 'podcast_list', 'podcast_list_block');
        $this->setBlock($templateHandle, 'default_podcast', 'default_podcast_block');
        $this->setVar('default_podcast_block', '');
        $this->setVar('podcast_list_block', '');

        if ($this->getConfig('url')) {
            $xml = new Xml($this->getConfig('url'));

            // Podcasts from xml feed
            if ($xml->channel) {
                $j = 0;
                $this->setComponentName(Scrub::text($xml->channel->title));

                // Iterate through each item in the channel
                if (strlen(trim($this->getConfig('url')))) {
                    for ($i = 0; $i < count($xml->channel->item); $i++) {
                        if ($i >= $this->getConfig('size') && $this->getConfig('size') != 0) {
                            break;
                        }

                        $item = $xml->channel->item[$i];

                        // check the podcast item has an enclosure element ()
                        if (!isset($item->enclosure)) {
                            continue;
                        }

                        // Is this the first podcast? output the player if so
                        if ($j === 0) {
                            $this->setVar(
                                array(
                                    'DEFAULT_PODCAST_URL'   => Scrub::text($item->enclosure['url']),
                                    'DEFAULT_PODCAST_TITLE' => Scrub::text($item->title),
                                )
                            );
                            $this->parse('default_podcast_block', 'default_podcast');
                        }

                        // Get file type information
                        $fileType = new FileType($item->enclosure->attributes()->url, $item->enclosure->attributes()->type);

                        if ($fileType->isAudio() || $fileType->isVideo()) {
                            $this->setVar(
                                array(
                                    'PODCAST_URL'         => Scrub::text($item->enclosure['url']),
                                    'PODCAST_TITLE'       => Scrub::text($item->title),
                                    'PODCAST_DESCRIPTION' => Scrub::html($item->description),
                                    'ON_OFF'              => ++$j % 2 ? '' : 'dark',
                                    'PODCAST_ID'          => md5($item->title),
                                )
                            );

                            $this->parse('podcast_list_block', 'podcast_list', true);
                        }
                    }
                }

                // If there are no podcasts in the list
                if ($j == 0) {
                    $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - URL does not contain any valid audio podcasts');
                    $this->setVar('podcast_list_block', '');
                }
            } // no channel found in xml
            else {
                $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - channel not found at URL');
                $this->setVar('podcast_list_block', '');
            }
        } else {
            $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - Please set URL');
            $this->setVar('podcast_list_block', '');
        }

        /* Need the podcast title injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }
}
