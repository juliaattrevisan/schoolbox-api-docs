#!/usr/bin/php
<?php

/**
 * Video encoder and processor
 * This script should be run regularly by cron, it picks up any videos in the queue automatically converts them
 * into playable videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Video\CommandLineTool\Processor;

require_once __DIR__ . '/../../../conf/core/require.php';

/* Initiate video transcodes immediately...this might capture more stuffs than just
 * the assets we uploaded (such as when schools upload their own content) but that
 * will be inevitably processed synonymously. This is a direct clone of the cron
 * processor (aside from this docblock) solely to bypass maintenance mode.
 *
 * This means that your potential transcode time is not just a few seconds to process
 * the assets for the feature tour, but ALSO whatever time it takes to process any
 * pending video transcodes for content made by schools (and not us)
 */
$app = new class extends Processor
{
    /**
     * Forcibly be root to get past the upgrade process
     *
     * @inheritDoc
     */
    public function getRequiredProcessUser(): ?string
    {
        /* This is probably not the right way to do it, but it gets past the upgrade process.
         * This wasn't my first choice
         */
        return 'root';
    }


    /**
     * Get around this update script shenanigans
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return true;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
