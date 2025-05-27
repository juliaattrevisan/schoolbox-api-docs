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

        /* A mapper
         */
        $storageMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

        /* Apparently this is the secret recipe
         */
        $assetPrefix = '250_feature_tour';

        /* Video assets to cram into storage
         */
        $assets = [
            'lms.mp4'
        ];

        foreach ($assets as $asset) {
            $tmp = tempnam(sys_get_temp_dir(), $assetPrefix);
            file_put_contents($tmp, file_get_contents($asset));

            $this->getLogger()->debug(sprintf(
                'Accepting asset %s at location %s',
                $asset,
                $tmp
            ));

            /* This is deprecated in favour of something else, but not sure what
             */
            $storage = $storageMapper->accept($asset, $tmp, true, $expiryAt);

            $this->getLogger()->debug(sprintf(
                '    Received with hash %s',
                $storage->getHash()
            ));
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
