#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Sanitizer\CliSanitizer;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This tool is to strip location data from exif to protect peoples privacy
 * Use the -p option to commit the changes to disk
 */
$app = (new CliSanitizer())
    ->setSanitizeType(CliSanitizer::TYPE_EXIF);

(new CliTool\Kernel(
    $app,
    [
        CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG,
    ]
))->runApp();
