<?php
/**
 * Outputs the value of configured skin variables in a CSS file.
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Twig\Environment;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// Do stuff
/** @var IConfigLoader $config */
$config = $kernel->get('config');

// Now output stuff
$colorConfigMapping = [
    // Nav bar colors.
    // Derived styles:
    // * $off-canvas-bg-hover
    // * $off-canvas-bg-active
    // * $icon-nav-highlight-color
    // * .top-menu style buried deep within www/css/foundation/ui/navigation/_navigation.scss
    // * .right-off-canvas-menu style buried deep within www/css/foundation/ui/navigation/_navigation.scss
    'skin_colour_navigation_foreground'        => '--navigation-foreground',
    'skin_colour_navigation_background'        => '--navigation-background',
    'skin_colour_navigation_hover'             => '--navigation-hover',

    // Off-canvas colors.
    // Derived styles:
    // * ul.off-canvas-list in www/css/foundation/ui/navigation/_navigation.scss:
    // * used to generate a constrasting color to off-canvas
    // * $off-canvas-bg
    'skin_colour_offcanvas_foreground'         => '--off-canvas-foreground',
    'skin_colour_offcanvas_background'         => '--off-canvas-background',
    'skin_colour_offcanvas_hover'              => '--off-canvas-hover',

    // Accounts menu colors
    // Derived styles:
    // * www/css/foundation/ui/navigation/_navigation.scss, mainly contrast-bg
    'accounts_menu_text_colour'                => '--accounts-menu-foreground',
    'accounts_menu_bg_colour'                  => '--accounts-menu-background',
    'accounts_menu_hover_colour'               => '--accounts-menu-hover',

    // Content UI colors.
    // Derived styles:
    // * too many
    'skin_colour_content_ui_foreground'        => '--content-ui-foreground',
    'skin_colour_content_ui_background'        => '--content-ui-background',
    'skin_colour_content_ui_hover'             => '--content-ui-hover',
    'skin_colour_content_ui_selected'          => '--content-ui-selected',

    // Content UI submit colors.
    // Derived styles:
    // * www/css/foundation/components/_buttons.scss
    // * www/css/foundation/ui/selectize.customisation.scss
    'skin_colour_content_ui_submit_foreground' => '--content-ui-submit-foreground',
    'skin_colour_content_ui_submit_background' => '--content-ui-submit-background',

     // Accent colors.
     // Derived styles:
     // * www/css/foundation/ui/_content.scss, including a select color mixin I don't know what to do with
     // * www/css/foundation/components/alaress-datetime-picker/alaress-datetime-picker.scss
    'skin_colour_accent_foreground'            => '--accent-foreground',
    'skin_colour_accent_background'            => '--accent-background',
    'skin_colour_accent_hover'                 => '--accent-hover',

     // Logo background.
     // Derived styles: none known
    'skin_colour_logo_background'              => '--logo-background',

    // Body background.
    // Derived styles:
    // * the contrast-bg rubric
    'skin_colour_body_bg'                      => '--body-background',

    // Login form background color.
    // Derived styles:
    // * www/css/foundation/layout/_login.scss calc-text-color
    'skin_colour_login_background'             => '--login-form-background',

    // Form colors.
    // Derived styles:
    // * www/css/foundation/ui/forms/_forms.scss
    // * www/css/foundation/components/submissions/rubric.scss
    'skin_colour_form_input_border_color'      => '--form-border-color',
    'skin_colour_form_background_hover_color'  => '--form-bg-hover-color',
];
$twigVars = [];
foreach ($colorConfigMapping as $configKey => $cssVar) {
    try {
        $pixel = new ImagickPixel($config->get($configKey));
        $hsl   = $pixel->getHSL();
    } catch (ImagickPixelException $e) {
        // Couldn't create it because it wasn't configured: skip
        continue;
    }
    $twigVars['colors'][$configKey] = [
        'cssVar' => $cssVar,
        'h'      => $hsl['hue'] * 360,
        's'      => $hsl['saturation'] * 100,
        'l'      => $hsl['luminosity'] * 100,
        'a'      => $pixel->getColorValueQuantum(Imagick::COLOR_ALPHA) / 0xFFFF * 100,
    ];
}

// Derived colors:
// --body-background has a contrasting color generated

/** @var Environment $twig */
$twig = $kernel->get('templateEngine');

// This is a CSS file, cached for a while
header('Content-Type: text/css');
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

echo $twig->render('css/skin.twig', $twigVars);
