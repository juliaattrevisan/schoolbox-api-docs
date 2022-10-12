#!/usr/bin/php
<?php

/**
 * Install the 21.1 tour to advertise new features to staff
 * @copyright Copyright 2021, Schoolbox Pty Ltd
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

        if(strtolower($this->getConfig()->get('site_plan')) === 'elite') {
            passthru('php ' . __DIR__ .  '/../../../tools/tour/install.php --perform ' . __DIR__ . '/221tour-elite.yaml', $returnVar);
        }
        if(strtolower($this->getConfig()->get('site_plan')) === 'pro') {
            passthru('php ' . __DIR__ .  '/../../../tools/tour/install.php --perform ' . __DIR__ . '/221tour-pro.yaml', $returnVar);
        }
        else {
            passthru('php ' . __DIR__ .  '/../../../tools/tour/install.php --perform ' . __DIR__ . '/221tour.yaml', $returnVar);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app))->runApp();


