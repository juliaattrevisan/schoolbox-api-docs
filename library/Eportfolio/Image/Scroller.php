<?php
/**
 * Eportfolio_Image_Scroller
 *
 * A port of Homepage's image scroller, with interface defined in the Abstract.
 * Timings are all in seconds, and denote how long images last before cycling
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Image_Scroller extends Eportfolio_Image_Abstract
{
    private $timings = array(2, 5, 10, 15, 20, 30);
    private $defaultTiming = 2;

    /**
     * Setup blocks and stuff
     *
     * @return void
     */
    public function setup()
    {
        $this->getPage()->setBlock($this->getComponentHandle(), 'image', 'image_block');
        $this->getPage()->setBlock('config', 'caption_state', 'caption_state_block');
        $this->getPage()->setBlock('image', 'show_caption', 'show_caption_block');
        $this->getPage()->setBlock('config', 'timing', 'timing_block');

        $this->setDefaultConfig();
        $config = $this->getConfig();

        $this->getPage()->setVar(array(
            'COMPONENT_PIXEL' => $config['pixel']
        ));

    }

    /**
     * Set default config options
     *
     * @return void
     */
    private function setDefaultConfig()
    {
        $config = $this->getConfig();

        if (empty($config['pixel'])) {
            $config['pixel'] = 222;
        }

        $this->setConfig($config);
    }

    /**
     * Render all the files in a scroller
     *
     * @return void
     */
    private function renderFiles()
    {
        $config = $this->getConfig();

        $allFiles = $this->getEportfolioContainer()->getImages($this->getUserId());

        /* Render each
           */
        if (is_array($allFiles) && count($allFiles)) {
            foreach ($allFiles as $currentFile) {
                $this->getPage()->setVar(array(
                    'IMAGE_ID' => $currentFile['id'],
                    'IMAGE_NAME' => $currentFile['description'],
                    'IMAGE_DIMENSION' => $config['pixel']
                ));

                if ($config['captionState'] == 'Active') {
                    $this->getPage()->parse('show_caption_block', 'show_caption');
                } else {
                    $this->getPage()->setVar('show_caption_block', '');
                }

                $this->getPage()->parse('image_block', 'image', true);
            }
        } else {
            $this->getPage()->setVar('image_block', '');
        }
    }

    /**
     * Render captions
     *
     * @return void
     */
    private function renderCaptions()
    {
        $config = $this->getConfig();

        foreach (array('Active', 'Inactive') as $state) {
            $this->getPage()->setVar(array(
                'COMPONENT_CONFIG_CAPTION_STATE_NAME' => $state,
                'COMPONENT_CONFIG_CAPTION_STATE_VALUE' => $state,
                'COMPONENT_CONFIG_CAPTION_STATE_SELECTED' => $state == $config['captionState'] ? ' selected="selected"' : ''
            ));

            $this->getPage()->parse("caption_state_block", "caption_state", true);
        }

    }

    /**
     * Render timings dropdown
     *
     * @return void
     */
    private function renderTimings()
    {
        $config = $this->getConfig();

        $this->getPage()->setVar(array(
            'COMPONENT_CONFIG_TIMING_VALUE_MS' => $config['timing'] * 1000
        ));

        foreach ($this->getTimings() as $currentTime) {
            $this->getPage()->setVar(array(
                'COMPONENT_CONFIG_TIMING_VALUE' => $currentTime,
                'COMPONENT_CONFIG_TIMING_SELECTED' => $currentTime == $config['timing'] ? ' selected="selected"' : '',
            ));

            $this->getPage()->parse('timing_block', 'timing', true);
        }
    }

    /**
     * Render control blocks
     *
     * @return void
     */
    private function renderControls()
    {
    }

    /**
     * Facade method to do everything in one command
     *
     * @return void
     */
    public function render()
    {
        $this->setup();
        $this->renderFiles();
        $this->renderCaptions();
        $this->renderTimings();
        $this->renderControls();
    }

    /**
     * Get list of timing values
     *
     * @return
     */
    private function getTimings()
    {
        return $this->timings;
    }

    /**
     * Return default timing when config is not set
     *
     * @return int
     */
    private function getDefaultTiming()
    {
        return $this->defaultTiming;
    }
}