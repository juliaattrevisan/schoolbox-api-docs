<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Image extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // has an internal block for the select element
        $page->setBlock($keyblock, 'config_images', 'config_images_block');
        $page->setVar('config_images_block', '');

        $allImages = $this->getContext()
            ? \Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
                [$this->getContext()->getId()],
                [
                    'filter'    => ['image'],
                    'sort'      => 'date',
                    'getHidden' => true,
                ]
            )
            : new \Schoolbox\Collection();

        if ($allImages->count()) {
            foreach ($allImages as $currentImage) {
                $currentImageTitle = Scrub::text($currentImage->getTitle());
                $page->setVar(
                    array(
                        'COMPONENT_CONFIG_IMAGE'          => $currentImageTitle,
                        'COMPONENT_CONFIG_IMAGE_SELECTED' => $currentImageTitle === $configValue
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
