<?php

use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Tile\Layout\Mapper as TileLayoutMapper;

/**
 * Layout for tiles: contains a link to edit the component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        $page->setBlock($keyblock, 'layout_select', 'layout_select_block');
        $page->setBlock('layout_select', 'layout_option', 'layout_option_block');
        $page->setBlock($keyblock, 'buttons_for_layout', 'buttons_for_layout_block');
        $page->setBlock($keyblock, 'buttons_for_component', 'buttons_for_component_block');
        $page->setBlock($keyblock, 'no_buttons_deleted_layout', 'no_buttons_deleted_layout_block');
        $page->setBlock($keyblock, 'no_buttons_permission_denied', 'no_buttons_permission_denied_block');


        // Show the list of layouts here
        // First, whereabouts am I?
        // Alt-source: show its layouts
        // No alt-source: show this context's layouts
        $tileLayoutMapper = TileLayoutMapper::getInstance();
        if ($component) {
            $layouts = $tileLayoutMapper->findForComponentContext($component->getInstance());
        } else {
            $layouts = $tileLayoutMapper->createCollection();
        }


        // Build a PHPLib list
        $page->setVar('layout_option_block', '');
        $layoutId = (int)$configValue;
        foreach ($layouts as $layout) {
            $page->setVar([
                'COMPONENT_CONFIG_LAYOUTID_NAME'     => Scrub::text($layout->getHeading()),
                'COMPONENT_CONFIG_LAYOUTID_VALUE'    => Scrub::text($layout->getId()),
                'COMPONENT_CONFIG_LAYOUTID_SELECTED' => (int)$layout->getId() === $layoutId ? 'selected' : '',
            ]);
            $page->parse('layout_option_block', 'layout_option', true);
        }
        $page->parse('layout_select_block', 'layout_select');


        // Render the buttons
        $page->setVar([
            'COMPONENT_CONFIG_LAYOUTID' => $layoutId,
            'RETURN_URL_NAME'           => Redirect::RETURN_URL,
            'RETURN_URL_CURRENT'        => Scrub::text(Redirect::encode($_GET["page"])),
        ]);
        $page->setVar('buttons_for_layout_block', '');
        $page->setVar('buttons_for_component_block', '');
        $page->setVar('no_buttons_deleted_layout_block', '');
        $page->setVar('no_buttons_permission_denied_block', '');

        if (!$component->hasWriteAccess()) {
            // (or not, if the current user can't view them)
            $page->parse('no_buttons_permission_denied_block', 'no_buttons_permission_denied');
        } elseif ($layoutId) {
            // Does the layout exist? If not, don't render buttons
            $layoutInUse = $layouts->getBy('id', $layoutId);
            if ($layoutInUse) {
                $page->parse('buttons_for_layout_block', 'buttons_for_layout');
            } else {
                $page->parse('no_buttons_deleted_layout_block', 'no_buttons_deleted_layout');
            }
        } else {
            $page->setVar('TILE_COMPONENT_INSTANCE_ID', $component->getInstance()->getId());
            $page->parse('buttons_for_component_block', 'buttons_for_component');
        }
    }
}
