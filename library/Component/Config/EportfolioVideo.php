<?php

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_EportfolioVideo extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        /* Sort order
         */
        $sortConfig = array();
        if ($component instanceof Component_ControllerAbstract) {
            $sortConfig = array(
                'sort' => $component->getConfig('sort'),
                'sortdir' => $component->getConfig('sortdir')
            );
        }

        $videoMapper = new \Schoolbox\Video\ResourceVideoMapper();
        $allVideos = ($page->arg[1] ?? false) ? $videoMapper->findByEportfolioPage($page->arg[1], $sortConfig) : $videoMapper->createCollection();

        $page->setBlock($keyblock, 'video_select', 'video_select_block');
        $page->setVar('video_select_block', '');

        if ($allVideos->count()) {
            foreach ($allVideos as $currentVideo) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_VIDSELECT_NAME' => Scrub::text($currentVideo->getFile()->getTitle()),
                    'COMPONENT_CONFIG_VIDSELECT_VALUE' => $currentVideo->getFile()->getId(),
                    'COMPONENT_CONFIG_VIDSELECT_SELECTED' => $currentVideo->getFile()->getId() == $configValue ? 'selected="selected"' : '',
                ));

                $page->parse("video_select_block", "video_select", true);
            }
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_VIDSELECT_NAME' => Scrub::text(' (none) '),
                'COMPONENT_CONFIG_VIDSELECT_VALUE' => '0',
                'COMPONENT_CONFIG_VIDSELECT_SELECTED' => 'disabled="disabled"',
            ));

            $page->parse("video_select_block", "video_select", true);
        }
    }
}
