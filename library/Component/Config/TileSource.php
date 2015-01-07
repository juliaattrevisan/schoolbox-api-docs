<?php
/**
 * Source selector for tiles components.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Component_Config_TileSource extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // a dropdown of tiles in the current alt source.
        // If no alt source is selected, this doesn't get shown.
        // Alt source is always a homepage.
        $page->setBlock($keyblock, 'tile_select', 'tile_select_block');
        $page->setBlock('tile_select', 'tile_option', 'tile_option_block');
        $page->setVar('tile_select_block', '');
        if ($component instanceof Component_ControllerAbstract) {
            $source = $component->getConfig('src') ?: $component->getInstance()->getId();
            /** @var Component_Instance[] $components */
            $components = Component_InstanceMapper::getInstance()->findByContextAndClass(
                'homepage',
                $source,
                'Schoolbox\\Tile\\Component\\HomepageTileController'
            );
            $page->setVar('tile_option_block', '');
            foreach ($components as $component) {
                $configs = $component->getConfigArray();
                $page->setVar(
                    array(
                        'COMPONENT_CONFIG_TILESELECT_NAME'     => $configs['componentname']['value'],
                        'COMPONENT_CONFIG_TILESELECT_VALUE'    => $component->getId(),
                        'COMPONENT_CONFIG_TILESELECT_SELECTED' => $component->getId() == $configValue
                            ? 'selected' : '',
                    )
                );
                $page->parse('tile_option_block', 'tile_option', true);
            }
            $page->parse('tile_select_block', 'tile_select');
        }
    }
}
