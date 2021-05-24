<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_ItemLayout extends Component_Config_Abstract
{

    /**
     * Layout consts corresponding to itemLayout.php component config options
     */
    public const LAYOUT_LIST = 'list';
    public const LAYOUT_LIST_REVERSED = 'list-reversed';
    public const LAYOUT_CARD = 'card';


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
        $page->setBlock($keyblock, 'item_layout', 'item_layout_block');
        $page->setVar('item_layout_block', '');

        $options = [
            self::LAYOUT_LIST => 'List - left image',
            self::LAYOUT_LIST_REVERSED => 'List - right image',
            self::LAYOUT_CARD => 'Card',
        ];

        foreach ($options as $k => $opt) {
            $page->setVar('COMPONENT_CONFIG_ITEMLAYOUT_NAME', $opt);
            $page->setVar('COMPONENT_CONFIG_ITEMLAYOUT_VALUE', $k);
            $page->setVar('COMPONENT_CONFIG_ITEMLAYOUT_SELECTED', $k == $configValue ? ' selected="selected"' : '');
            $page->parse('item_layout_block', 'item_layout', true);
        }
    }
}
