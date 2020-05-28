<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Twig\Environment;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// Do stuff
/** @var IConfigLoader $config */
$config = $kernel->get('config');

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
    try {
        $pixel = new ImagickPixel($config->get($colorVar));
        $hsl   = $pixel->getHSL();
    } catch (ImagickPixelException $e) {
        // Couldn't create it because it wasn't configured: skip
        continue;
    }
    $twigVars['colors'][$colorVar] = [
        'h' => $hsl['hue'] * 360,
        's' => $hsl['saturation'] * 100,
        'l' => $hsl['luminosity'] * 100,
        'a' => $pixel->getColorValueQuantum(Imagick::COLOR_ALPHA) / 0xFFFF * 100,
    ];
}

/** @var Environment $twig */
$twig = $kernel->get('templateEngine');

// This is a CSS file, cached for a while
header('Content-Type: text/css');
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

echo $twig->render('css/skin.twig', $twigVars);
