<?php

/**
 * Outputs the value of configured skin variables in a CSS file.
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress\Color\Transformer;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Twig\Environment;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->setupSentryTrace(__FILE__);

// Do stuff
/** @var IConfigLoader $config */
$config = $kernel->get('config');

// Now output stuff
$colorConfigMapping = [
    // Nav bar colors
    'skin_colour_navigation_foreground'        => '--navigation-foreground',
    'skin_colour_navigation_background'        => '--navigation-background',
    'skin_colour_navigation_hover'             => '--navigation-hover',

    // Off-canvas colors
    'skin_colour_offcanvas_foreground'         => '--off-canvas-foreground',
    'skin_colour_offcanvas_background'         => '--off-canvas-background',
    'skin_colour_offcanvas_hover'              => '--off-canvas-hover',

    // Accounts menu colors
    'accounts_menu_text_colour'                => '--accounts-menu-foreground',
    'accounts_menu_bg_colour'                  => '--accounts-menu-background',
    'accounts_menu_hover_colour'               => '--accounts-menu-hover',

    // Content UI colors
    'skin_colour_content_ui_foreground'        => '--content-ui-foreground',
    'skin_colour_content_ui_background'        => '--content-ui-background',
    'skin_colour_content_ui_hover'             => '--content-ui-hover',
    'skin_colour_content_ui_selected'          => '--content-ui-selected',

    // Content UI submit colors
    'skin_colour_content_ui_submit_foreground' => '--content-ui-submit-foreground',
    'skin_colour_content_ui_submit_background' => '--content-ui-submit-background',

     // Accent colors
    'skin_colour_accent_foreground'            => '--accent-foreground',
    'skin_colour_accent_background'            => '--accent-background',
    'skin_colour_accent_hover'                 => '--accent-hover',

     // Logo background color
    'skin_colour_logo_background'              => '--logo-background',

    // Body background color
    'skin_colour_body_bg'                      => '--body-background',

    // Login form background color
    'skin_colour_login_background'             => '--login-form-background',

    // Form colors
    'skin_colour_form_input_border_color'      => '--form-border-color',
    'skin_colour_form_background_hover_color'  => '--form-bg-hover-color',
];
$colorFactory   = new ColorFactory();
$transformer    = new Transformer();
$twigVars       = [];
$createTwigVars = static function (Color $color, $cssVar) {
    $hsl = $color->getAsHslArray();
    return [
        'cssVar'      => $cssVar,
        'colorObject' => $color,
        'r'           => $color->getRed(),
        'g'           => $color->getGreen(),
        'b'           => $color->getBlue(),
        'h'           => $hsl['hue'],
        's'           => $hsl['saturation'],
        'l'           => $hsl['lightness'],
        'a'           => $color->getAlpha(),
    ];
};
foreach ($colorConfigMapping as $configKey => $cssVar) {
    $baseColor = $colorFactory->build($config->get($configKey));
    $twigVars['colors'][$configKey] = $createTwigVars($baseColor, $cssVar);

    // Build contrasts for every color too
    $twigVars['colors'][$configKey . '_contrast'] = $createTwigVars(
        $transformer->calculateTextColor($baseColor),
        $cssVar . '-contrast'
    );
}

// Add a derived color for --body-background-contrast
// Probably should just be --body-foreground
$twigVars['colors']['skin_colour_body_fg'] = $createTwigVars(
    $transformer->calculateForegroundColor($twigVars['colors']['skin_colour_body_bg']['colorObject']),
    '--body-foreground'
);
$twigVars['colors']['skin_colour_body_h1'] = $createTwigVars(
    $transformer->calculateForegroundColor($twigVars['colors']['skin_colour_body_bg']['colorObject'], 0.855),
    '--body-foreground-h1'
);

// Add a derived color for navigation foreground highlight
$twigVars['colors']['skin_colour_navigation_foreground_highlight'] = $createTwigVars(
    $transformer->calculateTextColor(
        $twigVars['colors']['skin_colour_navigation_background']['colorObject'],
        null,
        $transformer->calculateForegroundColor(
            $twigVars['colors']['skin_colour_navigation_background']['colorObject'],
            0.65
        )
    ),
    '--navigation-foreground-highlight'
);

/** @var Environment $twig */
$twig = $kernel->get('templateEngine');

// This is a CSS file, cached for a while
header('Content-Type: text/css');
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

echo $twig->render('css/skin.twig', $twigVars);
