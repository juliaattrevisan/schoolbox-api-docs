<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Config_Video extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        $videoMapper = new \Schoolbox\Video\ResourceVideoMapper();
	    $vidList = $videoMapper->findByCategory($this->getCategory('id'));

        $page->setBlock($keyblock, 'video_select', 'video_select_block');
        $page->setVar('video_select_block', '');

        if (count($vidList)) {
            foreach ($vidList as $video) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_VIDSELECT_NAME'       => Scrub::text($video->getFile()->getDescription()),
                    'COMPONENT_CONFIG_VIDSELECT_VALUE' 	    => $video->getFile()->getId(),
                    'COMPONENT_CONFIG_VIDSELECT_SELECTED'   => $video->getFile()->getId() == $configValue ? 'selected="selected"' : '',
                ));

                $page->parse("video_select_block", "video_select", true);
            }
        }
        else {
            $page->setVar(array(
                'COMPONENT_CONFIG_VIDSELECT_NAME'       => Scrub::text(' (none) '),
                'COMPONENT_CONFIG_VIDSELECT_VALUE'      => '0',
                'COMPONENT_CONFIG_VIDSELECT_SELECTED'   => 'disabled="disabled"',
            ));

            $page->parse("video_select_block", "video_select", true);
        }
    }
}
