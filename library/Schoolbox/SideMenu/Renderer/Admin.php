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
use Scrub;

class Admin
{
    /**
     * Text scrubber class
     *
     * @var Scrub
     */
    protected $scrubber;

    public function __construct(Scrub $scrubber)
    {
        $this->scrubber = $scrubber;
    }

    /**
     * Renders a menu tree as HTML.
     *
     * @param Collection|Model[] $tree
     * @param bool               $selectable Can the item be selected?
     * @param int                $level      Internal break parameter -
     *                                       renderer won't go > 50 levels deep
     *
     * @return string
     */
    public function renderTree(Collection $tree, $selectable = false, $level = 0)
    {
        if ($level > 50) {
            return '';
        }
        // Top level? Render a top level item.
        $text = "<ul>\n";
        foreach ($tree as $menuItem) {
            $id       = $menuItem->getId();
            $menuText = $this->scrubber->text($menuItem->getText());
            $children = $menuItem->getChildren();

            $text .= '<li id="node_' . $id . '">';
            if ($selectable || $id == 0) {
                $text .= '<a id="' . $id . '" href="#">' . $menuText . "</a>";
            } else {
                $text .= '<a href="/adminv2/modify-side-menu/' . $id . '">' . $menuText . '</a>';
            }

            if (count($children)) {
                $text .= $this->renderTree($children, $selectable, ($level + 1));
            }
            $text .= "</li>\n";
        }
        $text .= "</ul>\n";
        return $text;
    }
}
