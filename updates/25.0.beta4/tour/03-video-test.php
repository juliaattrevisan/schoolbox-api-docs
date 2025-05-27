#!/usr/bin/php
<?php

/**
 * Install the 25.0 tour to advertise new features
 *
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Prepare video content
 */
$app = new class extends CliTool\App
{
    public function run(): void
    {
        /* Hopefully we pump out a new release within a year, right
         */
        $expiryAt = \Alaress_Date_Format::now()->addYears(1);

        /* This never did anything
         */
        $public = true;

        /* Apparently this is the secret recipe
         */
        $tmp = tempnam(sys_get_temp_dir(), '250_feature_tour');
        file_put_contents($tmp, file_get_contents('lms.mp4'));

        $storage = \Schoolbox\Resource\Storage\Mapper::getInstance()->accept('lms.mp4', $tmp, $public, $expiryAt);

        print_r($storage);
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
