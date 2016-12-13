<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;


class Component_Homepage_PodcastController extends Component_Homepage_ControllerAbstract
{
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

        $this->setBlock($templateHandle, 'podcast', 'podcast_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $emptyState = false;
        $noConfig = false;

        if (strlen($this->getConfig('url')) == 0) {
            $emptyState = true;
            $noConfig = true;
        } else {
            $xml = new Xml($this->getConfig('url'));

            if (!count($xml->channel->item)) {
                $emptyState = true;
            }
        }

        if ($emptyState) {

            $this->setVar('podcast_block', '');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_results', 'empty_results_block');
            $this->setBlock('empty_state', 'empty_results_write', 'empty_results_write_block');

            /**
             * If the user has no write permission just go for a generic nothing found message
             */
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {

                $this->setVar('empty_config_block', '');
                $this->setVar('empty_results_write_block', '');
                $this->parse('empty_results_block', 'empty_results');

            } else {

                $this->setVar('empty_results_block', '');

                if ($noConfig) {
                    $this->setVar('empty_results_write_block', '');
                    $this->parse('empty_config_block', 'empty_config');
                } else {
                    $this->setVar('empty_config_block', '');
                    $this->parse('empty_results_write_block', 'empty_results_write');
                }

            }

            $this->parse('empty_state_block', 'empty_state');

        } else {
            $this->setBlock('podcast', 'podcast_list', 'podcast_list_block');
            $this->setBlock('podcast', 'default_podcast', 'default_podcast_block');
            $this->setVar('empty_state_block', '');
            $this->parse('podcast_block', 'podcast');

            // Podcasts from xml feed
            $j = 0;
            $this->setComponentName(Scrub::text($xml->channel->title));

            // Iterate through each item in the channel
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
            // If there are no podcasts in the list
            if ($j == 0) {
                $this->setVar('PODCAST_COMPONENT_TITLE', 'Podcast - URL does not contain any valid audio podcasts');
                $this->setVar('podcast_list_block', '');
            }
        }

        /* Need the podcast title injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }
}
