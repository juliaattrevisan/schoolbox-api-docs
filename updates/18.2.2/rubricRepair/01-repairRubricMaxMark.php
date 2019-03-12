#!/usr/bin/php
<?php
/**
 * Repair script for repairing articles that were truncated in the migration
 * from homepage and school news to comms articles.
 *
 * Until now, comms articles have been stored as TEXT (2^16 bytes, ~64KB) when
 * the old tables were MEDIUMTEXT (2^24 bytes, ~16MB): this means that the new
 * comms articles will have been truncated.
 *
 * Fortunately the old tables and their data still exist, so their article
 * content can be migrated.
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;


require_once __DIR__ . '/../../../conf/core/require.php';

$opts   = getopt('p', ['perform']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Repair Rubric Marks',
    [
        'verbose' => [true, true, true],
        'perform' => true,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(new class extends App
{
    private const INDENT = '  ';

    private const FIND_INCONSISTENT_MAX_MARKS_QUERY = "
        SELECT
            sb.assessment_id,
            sb.max_mark            AS actual_max_mark,
            SUM(ind.indicator_max) AS expected_max_mark
        FROM submission_box sb
        INNER JOIN (
            SELECT
                rd.criterion_id,
                rl.box_id,
                MAX(rd.max_value) AS indicator_max
            FROM rubric_descriptor rd
            INNER JOIN rubric_level           rl  ON rd.level_id     = rl.id AND rl.deleted_at IS NULL
            INNER JOIN rubric_criterion       rc  ON rd.criterion_id = rc.id AND rc.deleted_at IS NULL
            INNER JOIN rubric_criterion_usage rcu ON rc.id           = rcu.criterion_id
            WHERE rd.deleted_at IS NULL
            GROUP BY rd.criterion_id, rl.box_id
        ) ind ON sb.id = ind.box_id

        WHERE sb.use_rubric         IS TRUE
        AND   sb.rubric_group_marks IS FALSE
        AND   sb.mark_type          != 6 # Mark type: none
        GROUP BY sb.assessment_id
        HAVING actual_max_mark != expected_max_mark

        UNION
        SELECT
            sb.assessment_id,
            sb.max_mark        AS actual_max_mark,
            SUM(rgu.max_value) AS expected_max_mark
        FROM       rubric_group       rg
        INNER JOIN rubric_group_usage rgu ON rg.id                 = rgu.rubric_group_id
        INNER JOIN submission_box     sb  ON rgu.submission_box_id = sb.id
        WHERE sb.use_rubric         IS TRUE
        AND   sb.rubric_group_marks IS TRUE
        AND   rg.deleted_at         IS NULL
        AND   sb.mark_type          != 6 # Mark type: none
        GROUP BY sb.assessment_id
        HAVING actual_max_mark != expected_max_mark

        ORDER BY assessment_id ASC
    ";

    private const UPDATE_RUBRIC_MAX_MARK_QUERY = "
        UPDATE submission_box SET max_mark = :maxMark WHERE assessment_id = :assessmentId
    ";


    /**
     * @var Logger
     */
    private $log;


    /**
     * @var IConnector
     */
    private $db;


    /**
     * Is this actually performing an update?
     *
     * @var bool
     */
    private $perform = false;


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts): void
    {
        // Install logger and DB
        $this->log     = $log;
        $this->db      = $di->get('databaseInternal');
        $this->perform = $opts['perform'] ?? false;


        // log perform / dry run
        if ($this->perform) {
            $this->log->warning('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->warning('Running in dry-run mode: use -p to write changes to database');
        }


        // Update queries
        $updateBodyQuery = $this->db->prepareQuery(self::UPDATE_RUBRIC_MAX_MARK_QUERY);


        // Find the list of migrated articles
        $this->db->beginTransaction();
        $inconsistentDueWorks = $this->db->executeQuery(self::FIND_INCONSISTENT_MAX_MARKS_QUERY);
        $this->log->notice(sprintf('%d assessments with inconsistent max marks found', $inconsistentDueWorks->rowCount()));
        foreach ($inconsistentDueWorks->getIterator() as $row) {
            $expectedMaxMark = $row['expected_max_mark'];
            $actualMaxMark   = $row['actual_max_mark'];
            $assessmentId    = $row['assessment_id'];
            $this->log->notice(sprintf('Updating assessment #%d',$assessmentId));
            $this->log->notice(self::indent(1).sprintf('Expected: %12.2f', $expectedMaxMark));
            $this->log->notice(self::indent(1).sprintf('Actual:   %12.2f', $actualMaxMark));
            $updateBodyQuery->execute([
                'assessmentId' => $assessmentId,
                'maxMark'      => $expectedMaxMark,
            ]);
        }


        if ($this->perform) {
            $this->db->commit();
        } else {
            $this->db->rollBack();
        }
    }


    /**
     * Helper method for indenting log output for legibility.
     *
     * @param int $depth The depth to indent by
     * @return string
     */
    private static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }
});
