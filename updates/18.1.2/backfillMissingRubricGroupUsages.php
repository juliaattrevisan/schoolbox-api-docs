#!/usr/bin/php
<?php

/**
 * Backfill missing `rubric_group_usage` rows.
 *
 * The original importer (@see updates/18.1.alpha1/rubrics/12-updateRubricGroupUsage.sql)
 * only created a `rubric_group_usage` if at least one descriptor within the
 * capability had a non-null value.
 *
 * This was an error: the `rubric_group_usage` should have been created
 * regardless.
 */

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    "'Backfill missing rubric_group_usage records'",
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$backfiller = new class extends App
{
    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var IConnector
     */
    private $database;


    /**
     * @inheritdoc
     * @throws Di\Exception\ServiceNotFound
     * @throws PDOException
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags): void
    {
        $this->log      = $log;
        $this->database = $di->get('databaseInternal');
        $this->database->beginTransaction();
        try {
            $this->execute();
            $this->database->commit();
        } catch (\Alaress\Database\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            $this->database->rollBack();
        }
    }


    /**
     * @throws \Alaress\Database\Exception
     */
    private function execute()
    {
        $candidateGroupUsages = $this->database->executeQuery("
            SELECT DISTINCT
                rcu.submission_box_id AS boxId,
                rg.id                 AS groupId
            FROM       rubric_criterion_usage rcu
            INNER JOIN rubric_criterion   rc  ON rcu.criterion_id   = rc.id
            INNER JOIN rubric_group       rg  ON rc.rubric_group_id = rg.id
            ORDER BY rcu.submission_box_id, rg.id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $countGroupUsageQuery = $this->database->prepareQuery("
            SELECT * FROM rubric_group_usage
            WHERE submission_box_id = :boxId
            AND   rubric_group_id   = :groupId
            LIMIT 1
        ");

        $insertGroupUsageQuery = $this->database->prepareQuery("
            INSERT IGNORE INTO rubric_group_usage
                (rubric_group_id, submission_box_id, max_value, sequence)
            VALUES
                (:groupId, :boxId, 0, 0)
        ");

        $count = 0;
        foreach ($candidateGroupUsages as $groupUsage) {
            $this->log->debug(
                sprintf("Testing box #%d, group #%d...", $groupUsage['boxId'], $groupUsage['groupId'])
            );
            $countGroupUsageQuery->execute($groupUsage);
            if ($countGroupUsageQuery->rowCount() === 0) {
                $this->log->debug("No rubric_group_usage record found: creating one");
                $count++;
                $insertGroupUsageQuery->execute($groupUsage);
            }
        }

        $this->log->info($count . ' rubric_group_usage record(s) created');
    }
};

try {
    $kernel->run($backfiller);
} catch (Exception $e) {
    throw $e;
}
