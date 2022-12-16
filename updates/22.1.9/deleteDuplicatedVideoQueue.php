#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Deletes duplicated hash (keeping the latest one) in video_queue and back them up in _video_queue table
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $this->getDatabaseInternal()->executeQuery('CREATE TABLE IF NOT EXISTS `_video_queue` LIKE `video_queue`');

        $query = $this->getDatabaseInternal()->executeQuery(
            "SELECT MAX(`video_queue_id`) AS max_id, `hash`, count(*) AS cnt FROM `video_queue` WHERE `status` != :failed GROUP BY `hash` HAVING cnt > 1",
            ['failed' => \Schoolbox\Video\QueueMapper::STATUS_FAILED]
        );
        $duplicatedQueues = $query->fetchAll();

        $backupQuery = "INSERT INTO `_video_queue` (SELECT * FROM `video_queue` WHERE `hash` = :hash AND `video_queue_id` != :maxId)";
        $deleteQuery = "DELETE FROM `video_queue` WHERE `hash` = :hash AND `video_queue_id` != :maxId";
        foreach ($duplicatedQueues as $duplicatedQueue) {
            $param = ['hash' => $duplicatedQueue['hash'], 'maxId' => $duplicatedQueue['max_id'],];
            $this->getDatabaseInternal()->executeQuery($backupQuery, $param);
            $this->getDatabaseInternal()->executeQuery($deleteQuery, $param);
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
