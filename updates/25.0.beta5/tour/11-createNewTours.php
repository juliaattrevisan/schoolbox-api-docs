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
            'php %s/../../../tools/tour/install.php --perform %s',
            __DIR__,
            __DIR__,
        );

        /* Feature Tours depend on the current plan that you're on
         */
        $currentPlan = $this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN);

        /* Every plan gets this tour
         */
        passthru("{$cmd}/250-core.yaml", $returnVar);

        /* Some plans have both Pastoral and LMS Enhancements
         * We didn't really add support for multiple tours commencing from the same initiation point
         * so we've added a special tour that services both, in addition to the individual tours for
         * pastoral care and lms enhancements
         */
        $lmsPastoraLPlans = [
            'Learning Enhanced',
            'Learning-Enhanced',
            'Canvas Elite',
            'Canvas-Elite'
        ];

        if (in_array($currentPlan, $lmsPastoraLPlans)) {
            $this->getLogger()->debug("Creating joint tour for {$currentPlan} via {$cmd}/250-lms-and-pastoral-care.yaml");
            passthru("{$cmd}/250-lms-and-pastoral-care.yaml", $returnVar);
        }

        /* Pastoral Enhancements are only available for some plans
         */
        $pastoralCarePlans = [
            'Community Elite',
            'Community-Elite',
        ];

        if (in_array($currentPlan, $pastoralCarePlans)) {
            $this->getLogger()->debug("Creating pastoral care tour for {$currentPlan} via {$cmd}/250-pastoral-care.yaml");
            passthru("{$cmd}/250-pastoral-care.yaml", $returnVar);
        }

        /* LMS Enhancements are only available for some plans
         */
        $lmsPlans = [
            'Learning Essentials',
            'Learning-Essentials',
            'Canvas Enhanced',
            'Canvas-Enhanced',
        ];

        if (in_array($currentPlan, $lmsPlans)) {
            $this->getLogger()->debug("Creating lms tour for {$currentPlan} via {$cmd}/250-pastoral-care.yaml");
            passthru("{$cmd}/250-lms.yaml", $returnVar);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
