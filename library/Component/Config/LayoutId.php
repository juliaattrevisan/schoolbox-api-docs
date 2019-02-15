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
    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null): void
    {
        $page->setBlock($keyblock, 'link', 'link_block');
        $page->setBlock($keyblock, 'no_link', 'no_link_block');


        // does this component have an alternate tile source?
        // If so, point the "add tile" and "modify layout" buttons to that
        // component instead.
        $tileComponentInstanceId = null;
        if ($component) {
            // Is there an alt tile source?
            // Use it if it exists: otherwise don't show the buttons at all
            $altTileSource = $component->getConfig('tileSource');
            if ($altTileSource) {
                $count = Component_InstanceMapper::getInstance()->countByColumn('id', $altTileSource);
                if ($count >= 1) {
                    $tileComponentInstanceId = $altTileSource;
                }
            } else {
                $tileComponentInstanceId = $component->getInstance()->getId();
            }
        }


        // Render something that isn't the buttons if there's nothing to point to
        if ($tileComponentInstanceId) {
            $page->setVar('TILE_COMPONENT_INSTANCE_ID', $tileComponentInstanceId);
            $page->parse('link_block', 'link');
            $page->setVar('no_link_block', '');
        } else {
            $page->setVar('link_block', '');
            $page->parse('no_link_block', 'no_link');
        }
    }
}
