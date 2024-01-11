#!/usr/bin/php
<?php

/**
 * Install the 23.0 tour to advertise new features to staff
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Set colours to pastoral severity
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $siteTitle = strtolower($this->getConfig()->get('title'));
        if (
            $siteTitle === '[PRD] [PRD] Schoolbox Help' ||
            $siteTitle === '[STG] [STG] Schoolbox Help'
        ) {
            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/olc24-tour.yaml', $returnVar);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
