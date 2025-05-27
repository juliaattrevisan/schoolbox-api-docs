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

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Prepare video content
 */
$app = new class extends CliTool\App
{
    /**
     * A string prefix used to segregate imported content from other stuffs
     * in the temp folder
     */
    private const ASSET_IDENTIFIER = '250_feature_tour';


    /**
     * Video assets to cram into storage
     */
    private const ASSETS = [
        'lms.mp4',
        'pastoral.mp4',
        'core.mp4',
    ];


    /**
     * Number of months for assets to stay alive
     *
     * Hopefully we push out another major release + feature tour within
     * this many months
     */
    private const EXPIRY = 12;


    private \Schoolbox\Resource\Storage\Mapper $storageMapper;


    /**
     * @inheritDoc
     *
     * @return void
     */
    public function run(): void
    {
        $this->storageMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

        /* Hopefully we pump out a new release within a year, right
         */
        $expiryAt = \Alaress_Date_Format::now()->addMonths(self::EXPIRY);

        /* Apparently this is the secret recipe
         */
        foreach (self::ASSETS as $asset) {
            $this->receive($asset, $expiryAt);
        }

        /* Initiate video transcodes immediately...this might capture more stuffs than just
         * the assets we uploaded (such as when schools upload their own content) but that
         * will be inevitably processed synonymously
         */
        $cmd = sprintf(
            'php %s/../../../tools/video/processor.php',
            __DIR__,
        );

        /* Render EVERYTHING
         */
        $this->getLogger()->debug('Executing video processor...');

        passthru($cmd, $returnVar);

        $this->getLogger()->debug('Done.');
    }


    /**
     * Simulate a POST request by harvesting the temp folder with a prefix,
     * returning a storage model
     *
     * @param string $asset
     * @param Alaress_Date_Format $expiryAt
     * @return \Schoolbox\Resource\Storage\Model
     */
    private function receive(string $asset, \Alaress_Date_Format $expiryAt)
    {
        $tmp = tempnam(sys_get_temp_dir(), self::ASSET_IDENTIFIER);
        file_put_contents($tmp, file_get_contents($asset));

        $this->getLogger()->debug(sprintf(
            'Accepting asset %s at location %s',
            $asset,
            $tmp
        ));

        /* TODO This is deprecated in favour of something else, but not sure what
         */
        $storage = $this->storageMapper->accept($asset, $tmp, true, $expiryAt);

        $this->getLogger()->debug(sprintf(
            '    Received with hash %s',
            $storage->getHash()
        ));

        return $storage;
    }


    /**
     * @inheritDoc
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
