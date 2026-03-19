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
use OzdemirBurak\Iris\Color\Hexa;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Twig\Environment;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->setupSentryTrace(__FILE__);

// Do stuff
/** @var IConfigLoader $config */
$config = $kernel->get('config');
/** @var \Schoolbox\MapperFactory $mapperFactory */
$mapperFactory = $kernel->get('mapperFactory');
/** @var ConfigMapper $configMapper */
$configMapper = $mapperFactory->get(ConfigMapper::class);

$skinColourConfigs = $configMapper->findByColumnList(
    [
        'type_id' => ConfigMapper::TYPE_SKIN_CONFIG,
        'field_id' => ConfigMapper::FIELD_COLOUR,
    ],
    [
        'order' => [
            'sequence' => 'ASC',
            ConfigMapper::COL_ID => 'ASC',
        ],
    ]
);

$colorConfigMapping = $skinColourConfigs->reduce(
    /** @param array<string, string> $carry */
    static function (array $carry, Config $skinConfig): array {
        $options = $skinConfig->getOptions();

        if ($options === null || trim($options) === '') {
            return $carry;
        }

        $decoded = json_decode($options, true);

        if (!is_array($decoded)) {
            return $carry;
        }

        $customProperty = $decoded['customProperty'] ?? null;

        if (!is_string($customProperty) || $customProperty === '') {
            return $carry;
        }

        $carry[$skinConfig->getName()] = $customProperty;

        return $carry;
    },
    []
);
$colorFactory   = new ColorFactory();
$transformer    = new Transformer();
$twigVars       = [];
$createTwigVars = static function (Color $color, $cssVar) {
    $hsl = new Hexa($color->getAsHexAlpha())->toHsla();
    return [
        'cssVar'      => $cssVar,
        'colorObject' => $color,
        'r'           => $color->getRed(),
        'g'           => $color->getGreen(),
        'b'           => $color->getBlue(),
        'h'           => $hsl->hue(),
        's'           => $hsl->saturation(),
        'l'           => $hsl->lightness(),
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
