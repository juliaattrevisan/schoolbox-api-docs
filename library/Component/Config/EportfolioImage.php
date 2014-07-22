<?php
/**
 *
 * An image selector for eportfolio components
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_EportfolioImage extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue, $allConfigs = array()) {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'config_images', 'config_images_block');
        $page->setVar('config_images_block', '');

		$imageMapper = new \Component\Eportfolio\FileListMapper();
		$allImages = $imageMapper->findAllImagesByUser($this->cat['uid']);

        if ($allImages->count()) {
            foreach ($allImages as $currentImage) {
                $page->setVar('COMPONENT_CONFIG_IMAGE', $currentImage->getFile()->getDescription());
                $page->setVar(
                    'COMPONENT_CONFIG_IMAGE_SELECTED',
					$currentImage->getFile()->getDescription() == $configValue ? ' selected="selected"' : ''
                );

                $page->parse('config_images_block', 'config_images', true);
            }
        }
		else {
            $page->setVar('COMPONENT_CONFIG_IMAGE', '(none)');
            $page->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', '');
            $page->parse('config_images_block', 'config_images', false);
        }
    }
}
