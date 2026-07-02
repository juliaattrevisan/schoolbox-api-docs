<?php

/**
 * Outputs the value of configured skin variables in a CSS file.
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\SkinConfig\Helper;
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
$twigVars = (new Helper($configMapper, $config))->buildPersistedData();

/** @var Environment $twig */
$twig = $kernel->get('templateEngine');

// This is a CSS file, cached for a while
header('Content-Type: text/css');
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

echo $twig->render('css/skin.twig', $twigVars);
