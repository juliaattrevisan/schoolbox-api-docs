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
class Component_Config_EportfolioImage extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'config_images', 'config_images_block');
        $page->setVar('config_images_block', '');

        $allImages    = [];
        $pageInstance = $component->getContext()['page'];
        if ($pageInstance instanceof Eportfolio_Page_Instance) {
            $fileMapper = new \Schoolbox\MyFileMapper();
            $allImages  = $fileMapper->findAllImagesByUser($pageInstance->getUserId());
        }

        $page->setVar('IMAGE_TITLE', 'Image');

        if (count($allImages)) {
            foreach ($allImages as $currentImage) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_FILE_ID' => $currentImage->getId(),
                    'COMPONENT_CONFIG_FILE_DESCRIPTION' => $currentImage->getTitle()
                ));

                if ($currentImage->getId() == $configValue) {
                    $page->setVar(array(
                        'COMPONENT_CONFIG_IMAGE_SELECTED' => ' selected="selected"',
                        'IMAGE_TITLE' => $currentImage->getTitle()
                    ));
                } else {
                    $page->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', '');
                }

                $page->parse('config_images_block', 'config_images', true);
            }
        } else {
            $page->setVar('COMPONENT_CONFIG_FILE_DESCRIPTION', '(none)');
            $page->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', '');
            $page->parse('config_images_block', 'config_images', false);
        }
    }
}
