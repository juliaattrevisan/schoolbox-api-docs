#!/usr/bin/php
<?php

/**
 * Install the OLC 24 tour to advertise new features to staff
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Set colours to pastoral severity
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $siteSchoolCode = strtolower($this->getConfig()->get('site_school_code'));
        if (strtolower($siteSchoolCode) === 'help') {
            $dbc = $this->getDatabaseInternal(); // Database connector.
            $logger = $this->getLogger();

            $tourTitle = 'OLC 24 Release'; // This must match the 'title' value in olc24-tour.yaml.
            $logger->info("Site '$siteSchoolCode' is a Schoolbox Help instance, so installing tour '$tourTitle'.");

            $tourAlreadyExists = $dbc->executeQuery(
                'SELECT EXISTS(
                    SELECT *
                    FROM tour
                    WHERE title = ?
                )',
                [$tourTitle]
            )->fetchColumn();

            if ($tourAlreadyExists) {
                $logger->info("Tour '$tourTitle' has already been installed.  Nothing to do.");
                die;
            }

            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/olc24-tour.yaml', $returnVar);

            // Deactivate the tour installed above.
            try {
                $stmt = $dbc->executeQuery(
                    'UPDATE tour
                    SET active=0
                    WHERE title = ?',
                    [$tourTitle]
                );
                $logger->info("Deactivated the '$tourTitle' tour so it can be manually activated later.");
            } catch (Exception $e) {
                $logger->error("Could not deactivate the '$tourTitle' tour.");
                throw $e;
            }
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::DEBUG, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
