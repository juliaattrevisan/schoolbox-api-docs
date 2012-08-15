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
        $page->setBlock($keyblock, 'images', 'images_block');
        $page->setVar('images_block', '');

        $catHandler = new Category();
        $imageList = $catHandler->getImages($this->getCategory('id'));

        if (is_array($imageList)) {
            foreach ($imageList as $image) {
                $page->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
                $page->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $configValue ? ' selected="selected"' : '');
                $page->parse('images_block', 'images', true);
            }
        }
        else {
            $page->setVar('COMPONENT_CONFIG_IMAGE', '(none)');
            $page->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', '');
            $page->parse('images_block', 'images', false);
        }

    }

}
