<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_ItemImage extends Component_Config_Abstract
{

    /**
     * Render the config option
     *
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param string                            $configValue
     * @param Component_ControllerAbstract|null $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'item_image', 'item_image_block');
        $page->setVar('item_image_block', '');

        $sortable = [
            'author-image' => 'Author Portrait',
            'school-logo' => 'School Logo',
            'none' => 'None'
        ];

        foreach ($sortable as $k => $opt) {
            $page->setVar('COMPONENT_CONFIG_ITEMIMAGE_NAME', $opt);
            $page->setVar('COMPONENT_CONFIG_ITEMIMAGE_VALUE', $k);
            $page->setVar('COMPONENT_CONFIG_ITEMIMAGE_SELECTED', $k == $configValue ? ' selected="selected"' : '');
            $page->parse('item_image_block', 'item_image', true);
        }
    }
}
