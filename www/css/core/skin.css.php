<?php

use Schoolbox\IConfigLoader;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// Do stuff
/** @var IConfigLoader $config */
$config = $kernel->get('config');

//// This is a CSS file
//header('Content-Type: text/css');

// Now output stuff
$colorVars = [
    'skin_colour_navigation_foreground',
    'skin_colour_navigation_background',
    'skin_colour_navigation_hover',
    'skin_colour_offcanvas_foreground',
    'skin_colour_offcanvas_background',
    'skin_colour_offcanvas_hover',
    'accounts_menu_bg_colour',
    'accounts_menu_text_colour',
    'accounts_menu_hover_colour',
    'skin_colour_content_ui_foreground',
    'skin_colour_content_ui_background',
    'skin_colour_content_ui_hover',
    'skin_colour_content_ui_selected',
    'skin_colour_content_ui_submit_foreground',
    'skin_colour_content_ui_submit_background',
    'skin_colour_accent_foreground',
    'skin_colour_accent_background',
    'skin_colour_accent_hover',
    'skin_colour_logo_background',
    'skin_colour_body_bg',
    'skin_colour_login_background',
    'skin_colour_form_input_border_color',
    'skin_colour_form_background_hover_color',
];
$twigVars = [];
foreach ($colorVars as $colorVar) {
    $pixel = new ImagickPixel($config->get($colorVar));
    $twigVars['colors'][$colorVar]          = $pixel->getHSL();
    $twigVars['colors'][$colorVar]['alpha'] = $pixel->getColorValueQuantum(Imagick::COLOR_ALPHA) / 0xFFFF;
}

new Alaress_Probe($twigVars);
