<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Permission\Access\UserAccess;


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
            try {
                $xml = new Xml($this->getConfig('url'));
                if (!$xml->channel || !count($xml->channel->item)) {
                    $emptyState = true;
                }
            } catch (\Exception $e) {
                $emptyState = true;
            }
        }

        $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
        $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
        $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

        /* Derive heading from xml field
         */
        if (!$emptyState) {
            $this->setComponentName(Scrub::text($xml->channel->title));
        }

        $this->parseConfig();

        if ($emptyState) {
            if (!$this->hasContextWriteAccess()) {
                return false;
            }

            $this->setVar([
                'podcast_block' => '',
                'empty_read_block' => '',
                'empty_config_block' => '',
                'empty_invalid_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } elseif ($noConfig) {
                $this->parse('empty_config_block', 'empty_config');
            } else {
                $this->parse('empty_invalid_block', 'empty_invalid');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {
            $this->setBlock('podcast', 'podcast_list', 'podcast_list_block');
            $this->setBlock('podcast', 'default_podcast', 'default_podcast_block');
            $this->setVar('empty_state_block', '');

            // Podcasts from xml feed
            $j = 0;

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
                    $this->setVar([
                        'DEFAULT_PODCAST_URL'   => Scrub::text($item->enclosure['url']),
                        'DEFAULT_PODCAST_TITLE' => Scrub::text($item->title),
                    ]);
                    $this->parse('default_podcast_block', 'default_podcast');
                }

                // Get file type information
                $fileType = new FileType($item->enclosure->attributes()->url, $item->enclosure->attributes()->type);

                if ($fileType->isAudio() || $fileType->isVideo()) {
                    $this->setVar([
                        'PODCAST_URL'         => Scrub::text($item->enclosure['url']),
                        'PODCAST_TITLE'       => Scrub::text($item->title),
                        'PODCAST_DESCRIPTION' => Scrub::html($item->description),
                        'PODCAST_ID'          => md5($item->title),
                    ]);

                    $j++;

                    $this->parse('podcast_list_block', 'podcast_list', true);
                }
            }
            // If there are no podcasts in the list
            if ($j === 0) {
                $this->setVar([
                    'podcast_list_block'          => '',
                    'default_podcast_block'       => '',
                    'empty_read_block'            => '',
                    'empty_config_block'          => ''
                ]);
                $this->parse('empty_invalid_block', 'empty_invalid');
                $this->parse('empty_state_block', 'empty_state');
            }

            $this->parse('podcast_block', 'podcast');
        }

        return true; // everything went according to plan
    }
}
