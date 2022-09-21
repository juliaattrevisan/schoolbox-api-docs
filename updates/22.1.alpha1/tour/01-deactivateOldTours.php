#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Soft deletes old release tours
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $titles = [
            '22.0 Release',
            '22.1 Release',
        ];

        $now = (\Alaress_Date_Format::now())->getDatabase();
        foreach ($titles as $title) {
            $query = $this->getDatabaseInternal()->executeQuery(
                "SELECT `uuid` FROM `tour` WHERE `title` = :title",
                ['title' => $title]
            );
            $tourUuid = $query->fetchColumn();

            if (!$tourUuid) {
                continue;
            }

            $deleteStep = $this->getDatabaseInternal()->prepareQuery(
                "UPDATE `tour_step` SET `deleted_at` = :now WHERE `tour_uuid` = :uuid"
            );
            $deleteStep->execute(['uuid' => $tourUuid, 'now' => $now]);

            $deleteModal = $this->getDatabaseInternal()->prepareQuery(
                "UPDATE `tour_step_modal` SET `deleted_at` = :now WHERE `tour_step_uuid` IN
                 (SELECT `uuid` FROM `tour_step` WHERE `tour_uuid` = :uuid)"
            );
            $deleteModal->execute(['uuid' => $tourUuid, 'now' => $now]);

            $deleteTrigger = $this->getDatabaseInternal()->prepareQuery(
                "UPDATE `tour_trigger` SET `deleted_at` = :now WHERE `tour_uuid` = :uuid"
            );
            $deleteTrigger->execute(['uuid' => $tourUuid, 'now' => $now]);

            $deleteTour = $this->getDatabaseInternal()->prepareQuery(
                "UPDATE `tour` SET `deleted_at` = :now WHERE `uuid` = :uuid"
            );
            $deleteTour->execute(['uuid' => $tourUuid, 'now' => $now]);
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
