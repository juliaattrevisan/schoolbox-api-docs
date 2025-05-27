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
 */

$app = new class extends Processor
{
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
