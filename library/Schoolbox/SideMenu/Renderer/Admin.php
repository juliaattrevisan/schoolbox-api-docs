<?php
/**
 * Renders a side menu for the admin section.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SideMenu\Renderer;

use Schoolbox\SideMenu\Model;
use Schoolbox\Collection;

class Admin
{
    /**
     * Renders a menu tree as HTML.
     *
     * @param Collection|Model[] $tree
     * @param int                $level
     * @param bool               $selectable
     *
     * @return string
     */
    public function renderTree(Collection $tree, $level = 0, $selectable = false)
    {
        // Top level? Render a top level item.
        $text = "<ul>\n";
        foreach ($tree as $menuItem) {
            $id       = $menuItem->getId();
            $menuText = \Scrub::text($menuItem->getText());
            $children = $menuItem->getChildren();

            $text .= '<li id="node_' . $id . '">';
            if ($selectable) {
                $text .= '<a id="' . $id . '" href="#">' . $menuText . "</a>";
            } else {
                $text .= '<a href="/adminv2/modify-side-menu/' . $id . '">' . $menuText . '</a>';
            }

            if (count($children)) {
                $text .= $this->renderTree($children, ($level + 1), $selectable);
            }
            $text .= "</li>\n";
        }
        $text .= "</ul>\n";
        return $text;
    }
}
