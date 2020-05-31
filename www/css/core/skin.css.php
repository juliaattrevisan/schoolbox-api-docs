<?php
/**
 * Outputs the value of configured skin variables in a CSS file.
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Color\ColorFactory;
use Alaress\Color\Transformer;
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
$colorFactory   = new ColorFactory();
$twigVars       = [];
$createTwigVars = static function ($color, $cssVar) {
    $hsl = $color->getAsHslArray();
    return [
        'cssVar'      => $cssVar,
        'colorObject' => $color,
        'h'           => $hsl['hue'],
        's'           => $hsl['saturation'],
        'l'           => $hsl['lightness'],
        'a'           => $color->getAlpha() * 100,
    ];
};
foreach ($colorConfigMapping as $configKey => $cssVar) {
    $twigVars['colors'][$configKey] = $createTwigVars(
        $colorFactory->build($config->get($configKey)),
        $cssVar
    );
}

// Add a derived color for --body-background-contrast
// Probably should just be --body-foreground
$transformer = new Transformer();
$twigVars['colors']['skin_colour_body_fg'] = $createTwigVars(
    $transformer->contrast($twigVars['colors']['skin_colour_body_bg']['colorObject']),
    '--body-foreground'
);

// Derived colors:
// www/css/foundation/base/_typography.scss:
// * $subheader-font-color is --body-background passed thru contrast-bg
// * .meta is --body-background passed thru contrast-bg
// * h1 is --body-background passed thru contrast-bg (with different params)
// www/css/foundation/components/_buttons.scss:
// * $submit-button-bg-hover is --content-ui-submit-background with a 20% tint
// * $secondary-button-bg-hover is --content-ui-submit-foreground with a 20% tint
// --accent-foreground is lightened by 60%
// www/css/foundation/components/alaress-datetime-picker/alaress-datetime-picker.scss:
// * $pickr-hover-background is --accent-foreground lightened by 60%
// www/css/foundation/components/submissions/rubric.scss:
// * .rb.markable checkboxes (Selected descriptor on marked grid) has a color contrasting to --content-ui-background
// * .rb-group-summary (Mark-per-cap on marked grid) is --form-border-color lightened 10%, and text contrasting to that
// * .rb-no-cell (Header cells on grid) is --body-background passed thru contrast-bg
// * .rb-selected (Selected cells on marked read-only) is --content-ui-selected passed thru calc-text-color
// * .rb-final-summary (Final summary on marked grid) is --form-bg-hover-color passed thru calc-text-color
// www/css/foundation/css-only/components/_buttons.scss:
// * button-style mixin has colors contrasting to --content-ui-background
// www/css/foundation/css-only/components/_offcanvas.scss:
// * $off-canvas-bg-hover, $off-canvas-bg-active have darker versions of --navigation-foreground (which appear ignored)
// www/css/foundation/layout/_footer.scss:
// * #footer a is --body-background passed thru contrast-bg
// www/css/foundation/layout/_login.scss:
// * body.login { a, label } has --login-form-background passed thru calc-text-color
// www/css/foundation/ui/_alert-boxes.scss:
// * $alert-default-background is --body-background darkened by 10%
// * .alert-box has --body-background passed through calc-text-color, then tinted 90%
// www/css/foundation/ui/_content.scss:
// * @include selection is commented out: this is text selection styles
// * .progress .pipe-meter is --accent-foreground darkened 5%
// * .train-line a span is --content-ui-foreground passed thru calc-text-color
// www/css/foundation/ui/cards.scss:
// * .card.removed h3 is --body-background passed thru contrast-bg
// www/css/foundation/ui/selectize.customisation.scss:
// * something deep in here has --content-ui-submit-foreground passed thru calc-text-color
// www/css/foundation/ui/button/_dropdown.scss:
// * .f-dropdown .separator has contrast-bg based on --content-ui-background
// * #tab-control .separator has --content-ui-background darkened 5%
// www/css/foundation/ui/button/_header-actions.scss:
// * .header-actions button:hover has --content-ui-hover tinted 20%
// www/css/foundation/ui/button/_list-actions.scss:
// * some label somewhere has --content-ui-foreground passed thru calc-text-color
// www/css/foundation/ui/forms/_datetime.scss:
// * .ui-datepicker-calendar td has --content-ui-background passed thru contrast-bg
// * .ui-timepicker-list li:hover has --content-ui-background passed thru contrast-bg
// www/css/foundation/ui/forms/_forms.scss:
// * $input-focus-color has --form-border-color passed thru calc-text-color
// * $input-focus-border-color hos --form-border-color darkened 15%
// * vaporwave mixin has --content-ui-foreground passed thru calc-text-color
// www/css/foundation/ui/navigation/_breadcrumb.scss:
// * $breadcrumb-noclick-bg-color is --content-ui-background, scaled lightness 40%
// * .breadcrumb li a is --content-ui-background-h, scaled lightness -40%
// www/css/foundation/ui/navigation/_navigation.scss:
// * $icon-nav-highlight-color is --navigation-background passed thru calc-text-color, with a contrast used as the dark
// * .top-menu li a.navthis is --navigation-background passed thru calc-text-color, with minor tint/darkening
// * ul.off-canvas-list { h3, h4 } is contrast-bg, with a dark color --off-canvas-foreground
// * { .account-menu-mobile, .account-drop} .separator is --accounts-menu-background passed thru contrast-bg
// * { .account-menu-mobile, .account-drop} .user .card .meta is --accounts-menu-foreground passed thru contrast-bg
// www/css/foundation/ui/navigation/_notification.scss:
// * Unread notifs (.right-off-canvas-menu ul.off-canvas-list label) is --navigation-background passed thru contrast-bg
// www/css/foundation/ui/navigation/_pagination.scss:
// * $pagination-link-unavailable-bg-active is --content-ui-background, scaled lightness -10%
// www/css/foundation/utils/_fonts.scss:
// * [class^='icon-'], [class*=' icon-'] has --body-background passed thru contrast-bg
// www/css/foundation/utils/_mixins.scss:
// * selection mixin has --accent-foreground passed thru calcTextColor
// www/css/foundation/utils/_variables.scss:
// * $row-hover-background is --content-ui-hover tinted 87%
// * $$row-action-hover-background is --content-ui-hover darkened 7%


/** @var Environment $twig */
$twig = $kernel->get('templateEngine');

// This is a CSS file, cached for a while
header('Content-Type: text/css');
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

echo $twig->render('css/skin.twig', $twigVars);
