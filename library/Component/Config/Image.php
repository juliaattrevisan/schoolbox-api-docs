<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Image extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // has an internal block for the select element
        $page->setBlock($keyblock, 'config_images', 'config_images_block');
        $page->setVar('config_images_block', '');

        $allImages = \Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
            [$this->getCategory()->getId()],
            [
                'filter' => ['image'],
                'sort'   => 'date',
                'hidden' => true,
            ]
        );

        if ($allImages->count()) {
            foreach ($allImages as $currentImage) {
                $page->setVar(
                    array(
                        'COMPONENT_CONFIG_IMAGE'          => $currentImage->getTitle(),
                        'COMPONENT_CONFIG_IMAGE_SELECTED' => $currentImage->getTitle() == $configValue
                            ? ' selected="selected"'
                            : ''
                    )
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
