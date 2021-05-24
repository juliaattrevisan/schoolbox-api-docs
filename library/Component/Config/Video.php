<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Video extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        /* Sort order
         */
        if ($component instanceof Component_ControllerAbstract) {
            $sortConfig = array(
                'sort' => $component->getConfig('sort'),
                'sortdir' => $component->getConfig('sortdir')
            );
            $sourceId = $component->getSource() ? $component->getSource()->getId() : 0;
        } else {
            $sortConfig = array();
            $sourceId = $this->getContext()->getId();
        }

        if ($sourceId) {
            $videoMapper = new \Schoolbox\Video\ResourceVideoMapper();
            $vidList = $videoMapper->findByCategory($sourceId, $sortConfig);
        } else {
            $vidList = new \Schoolbox\Collection();
        }

        $page->setBlock($keyblock, 'video_select', 'video_select_block');
        $page->setVar('video_select_block', '');

        if (count($vidList)) {
            foreach ($vidList as $video) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_VIDSELECT_NAME'       => Scrub::text($video->getFile()->getTitle()),
                    'COMPONENT_CONFIG_VIDSELECT_VALUE'      => $video->getFile()->getId(),
                    'COMPONENT_CONFIG_VIDSELECT_SELECTED'   => $video->getFile()->getId() == $configValue ? 'selected="selected"' : '',
                ));

                $page->parse("video_select_block", "video_select", true);
            }
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_VIDSELECT_NAME'       => Scrub::text(' (none) '),
                'COMPONENT_CONFIG_VIDSELECT_VALUE'      => '0',
                'COMPONENT_CONFIG_VIDSELECT_SELECTED'   => 'disabled="disabled"',
            ));

            $page->parse("video_select_block", "video_select", true);
        }
    }
}
