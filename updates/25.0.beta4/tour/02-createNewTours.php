#!/usr/bin/php
<?php

/**
 * Install the 25.0 tour to advertise new features
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Install feature tours based on an instance's plan since one plan
 * supports different feature sets than other plans.
 */
$app = new class extends CliTool\App
{
    public function run(): void
    {
        /* Passthru for running a tool during an upgrade
         */
        $cmd = sprintf(
            'php %s /../../../tools/tour/install.php --perform %s',
            __DIR__,
            __DIR__,
        );

        /* Feature Tours depend on the current plan that you're on
         */
        $currentPlan = $this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN);

        /* Every plan gets this tour
         */
        passthru("{$cmd}/250-core.yaml", $returnVar);

        /* Pastoral Enhancements are only available for some plans
         */
        $pastoralCarePlans = [
            'Community Elite',
            'Learning Enhanced',
            'Canvas Elite'
        ];

        if (in_array($currentPlan, $pastoralCarePlans)) {
            passthru("{$cmd}/250-pastoral-care.yaml", $returnVar);
        }

        /* LMS Enhancements are only available for some plans
         */
        $lmsPlans = [
            'Learning Essentials',
            'Learning Enhanced',
            'Canvas Enhanced',
            'Canvas Elite'
        ];

        if (in_array($currentPlan, $lmsPlans)) {
            passthru("{$cmd}/250-lms.yaml", $returnVar);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
