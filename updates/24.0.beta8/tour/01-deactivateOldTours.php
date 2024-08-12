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
            '23.0 Release',
            '23.1 Release',
            '24.0 Release',
        ];

        $now = (\Alaress_Date_Format::now())->getDatabase();
        foreach ($titles as $title) {
            $tourUuid = $this->getDatabaseInternal()->executeQuery(
                "SELECT uuid FROM tour WHERE title = :title",
                ['title' => $title]
            )->fetchColumn();

            if (!$tourUuid) {
                continue;
            }

            // 'UPDATE SET deleted_at' commands for all tour tables containing deleted_at columns.
            $sqlCommands = [
                "UPDATE tour_step
                 SET deleted_at = :now
                 WHERE tour_uuid = :uuid",
                "UPDATE tour_step_event
                 SET deleted_at = :now
                 WHERE tour_step_uuid IN (
                     SELECT uuid FROM tour_step WHERE tour_uuid = :uuid
                 )",
                "UPDATE tour_step_modal
                 SET deleted_at = :now
                 WHERE tour_step_uuid IN (
                     SELECT uuid FROM tour_step WHERE tour_uuid = :uuid
                 )",
                "UPDATE tour_step_tooltip
                 SET deleted_at = :now
                 WHERE tour_step_uuid IN (
                     SELECT uuid FROM tour_step WHERE tour_uuid = :uuid
                 )",
                "UPDATE tour_trigger
                 SET deleted_at = :now
                 WHERE tour_uuid = :uuid",
                "UPDATE tour
                 SET deleted_at = :now
                 WHERE uuid = :uuid",
            ];

            foreach ($sqlCommands as $sqlCommand) {
                $this->getDatabaseInternal()->prepareQuery($sqlCommand)->execute(['uuid' => $tourUuid, 'now' => $now]);
            }
        }
    }
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
