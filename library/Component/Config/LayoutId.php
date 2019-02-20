<?php

use Schoolbox\Tile\Layout\Mapper as TileLayoutMapper;

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
    public function parse(
        SitePage $page,
        $key,
        $keyblock,
        $configValue,
        Component_ControllerAbstract $component = null
    ): void {
        $page->setBlock($keyblock, 'tile_select', 'tile_select_block');
        $page->setBlock('tile_select', 'tile_option', 'tile_option_block');
        $page->setBlock($keyblock, 'link', 'link_block');
        $page->setBlock($keyblock, 'component_link', 'component_link_block');
        $page->setBlock($keyblock, 'no_link', 'no_link_block');


        // Show the list of layouts here
        // First, whereabouts am I?
        // Alt-source: show its layouts
        // No alt-source: show this context's layouts
        $layouts          = [];
        $tileLayoutMapper = TileLayoutMapper::getInstance();
        if ($component) {
            $layouts = $tileLayoutMapper->findForComponentContext($component->getInstance()->getId());
        }


        // Build a PHPLib list
        $page->setVar('tile_option_block', '');
        $layoutId = (int)$configValue;
        foreach ($layouts as $layout) {
            $page->setVar([
                'COMPONENT_CONFIG_LAYOUTID_NAME'     => Scrub::text($layout->getHeading()),
                'COMPONENT_CONFIG_LAYOUTID_VALUE'    => Scrub::text($layout->getId()),
                'COMPONENT_CONFIG_LAYOUTID_SELECTED' => (int)$layout->getId() === $layoutId ? 'selected' : '',
            ]);
            $page->parse('tile_option_block', 'tile_option', true);
        }
        $page->parse('tile_select_block', 'tile_select');


        // Render the buttons
        $page->setVar('COMPONENT_CONFIG_LAYOUTID', $layoutId);
        $page->setVar('link_block', '');
        $page->setVar('component_link_block', '');
        $page->setVar('no_link_block', '');
        if ($layoutId) {
            // Does the layout exist? If not, don't render buttons
            $layoutExists = $tileLayoutMapper->countByColumn('id', $layoutId);
            if ($layoutExists) {
                $page->parse('link_block', 'link');
            } else {
                $page->parse('no_link_block', 'no_link');
            }
        } else {
            $page->setVar('TILE_COMPONENT_INSTANCE_ID', $component->getInstance()->getId());
            $page->parse('component_link_block', 'component_link');
        }
    }
}
