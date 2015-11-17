<?php
/**
 * Renders a side menu.
 *
 * This is a direct port of SitePage's menu renderer.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SideMenu;

use Schoolbox\SideMenu\Model;
use Schoolbox\Collection;
use Scrub;
use Url;

class Renderer
{
    /**
     * Text scrubber class
     *
     * @var Scrub
     */
    protected $scrubber;

    /**
     * @param Scrub $scrubber
     */
    public function __construct(Scrub $scrubber)
    {
        $this->scrubber = $scrubber;
    }

    /**
     * Renders a menu tree as HTML.
     *
     * @param Collection|Model[] $tree
     * @param int                $level      Internal break parameter -
     *                                       renderer won't go > 50 levels deep
     *
     * @return string
     */
    public function renderTree(Collection $tree, $level = 0)
    {
        if ($level >= 1) {
            $text = "<ul class='left-submenu'>\n<li class=\"back\"><a href=\"#\">Back</a></li>\n";
        } else {
            $text = "<ul class='off-canvas-list'>\n";
        }

        foreach ($tree as $menuItem) {
            $aTag = $this->generateMenuAnchor($menuItem);
            $children = $menuItem->getChildren();

            if (count($children)) {
                // Does this menu item have both children and a link?
                // Add a copy of the link to the foot of the tree.
                $url = $menuItem->getUrl();
                if ($url && $url != '#') {
                    $copy = clone $menuItem;
                    $copy->setChildren(new Collection());
                    $children->prepend($copy);
                }
                $text .= '<li class="has-submenu">' . $aTag;
                $text .= $this->renderTree($children, ($level + 1));
            } else {
                $text .= "<li>$aTag";
            }
            $text .= "</li>\n";
        }
        $text .= "</ul>\n";
        return $text;
    }

    /**
     * Creates a menu anchor for this menu item.
     *
     * @param Model $menuItem
     *
     * @return string
     */
    protected function generateMenuAnchor(Model $menuItem)
    {
        // Generate the target
        if ($menuItem->getTarget()) {
            $target = " target={$menuItem->getTarget()}";
        } else {
            $target = '';
        }

        // Generate the href
        if ($menuItem->getUrl()) {
            // derive the URL based on where it leads
            $url = new Url($menuItem->getUrl());

            if ($url->isLocalUrl()) {
                $linkUrl = $url->getRelativeURL();
            } elseif ($url->isFTPURL()) {
                $linkUrl = $url->getUrl();
            } else {
                $linkUrl = $url->getBase64EncodedRedirect();
            }

        } elseif (count($menuItem->getChildren())) {
            // if this is a parent, it has to have SOME kind of href
            $linkUrl = '#';
        } else {
            // otherwise, no href
            $linkUrl = '';
        }

        // Generate a caption
        $caption = trim($menuItem->getText());
        if (preg_match('`^(<[hb]r ?/>)|<li>(&nbsp;|\\s+)?</li>$`', $caption, $matches)) {
            if ($matches[1]) {
                // matched <hr/> or <br/>
                $caption = $matches[1];
            } else {
                // matched <li></li>
                $caption = '<li>'. $matches[2] . '</li>';
            }
        } else {
            $caption = $this->scrubber->text($caption);
        }

        return $linkUrl
            ? sprintf('<a href="%s"%s><span>%s</span></a>', $linkUrl, $target, $caption)
            : "<a><span>$caption</span></a>";
    }
}
