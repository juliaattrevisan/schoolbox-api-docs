<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Image extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'config_images', 'config_images_block');
        $page->setVar('config_images_block', '');

        $catHandler = new Category();
        $imageList = $catHandler->getImages($this->getCategory('id'), 0, true);

        if (is_array($imageList)) {
            foreach ($imageList as $image) {
                $page->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
                $page->setVar(
                    'COMPONENT_CONFIG_IMAGE_SELECTED',
                    $image['description'] == $configValue ? ' selected="selected"' : ''
                );
                $page->parse('config_images_block', 'config_images', true);
            }
        } else {
            $page->setVar('COMPONENT_CONFIG_IMAGE', '(none)');
            $page->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', '');
            $page->parse('config_images_block', 'config_images', false);
        }

    }

}
