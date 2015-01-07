<?php
/**
 * Layout for tiles: contains a link to edit the component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Component_Config_LayoutId extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // Which link gets displayed?
        // If there's an alt source: show that, otherwise show the link for this item.
        $page->setVar(
            'TILE_COMPONENT_INSTANCE_ID',
            $component->getConfig('tileSource') ?: $component->getInstance()->getId()
        );
    }
}
