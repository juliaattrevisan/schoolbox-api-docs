<?php
/**
 * Crude hack to display navigation element on admin pages
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Admin;

class Navigation {

    public static function getNavigation() {

        // Gets the nav items from the existing \AdminV2_Nav class

        $nav = new \AdminV2_Nav();
        $items = $nav->nav;

        // Work out page title

        if (isset($items[basename($_GET['page'])]))
            $currentTitle = " › " . $items[basename($_GET['page'])];
        else
            $currentTitle = "";

        $html =
        "
            <div class='small-12 columns'>
                <h1>Schoolbox Administration{$currentTitle}</h1>
                <ul class='sub-nav'>
        ";

        // Render each menu item

        foreach ($items as $link => $title) {

            // Highlight current page

            $active = $link == basename($_GET['page']) ? 'active' : '';
            $html .= "<li class='{$active}'><a href='/adminv2/{$link}'>{$title}</a></li>";
        }

        $html .= "
                </ul>
            </div>
        ";

        // Return HTML for navigation

        return $html;
    }
}