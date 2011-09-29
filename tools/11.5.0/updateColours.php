<?php
/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 9/21/11
 * Time: 6:38 PM
 * To change this template use File | Settings | File Templates.
 */

require_once(dirname(__FILE__) . '/../include/Bootstrap.php');

$configHandler = new Config;

$colours = unserialize(HOMEPAGE_colors);

Cfg::set('homepage_colour_head_font',$colours['left_head_font']);
Cfg::set('homepage_colour_head_bg',$colours['left_head_bg']);
Cfg::set('homepage_colour_font',$colours['left_font']);
Cfg::set('homepage_colour_bg_primary',$colours['left_bg_primary']);
Cfg::set('homepage_colour_bg_secondary',$colours['left_bg_secondary']);
Cfg::set('homepage_colour_link',$colours['homepage_colour_link']);
Cfg::set('homepage_colour_hover',$colours['homepage_colour_hover']);



