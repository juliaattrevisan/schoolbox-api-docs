#!/usr/bin/php
<?php
/**
 * Repair script for max marks for due works that use rubrics.
 *
 * This script tests that the actual max mark for the due work matches up with:
 * * if it's mark-per-capability: the sum of its capabilities' max marks
 * * otherwise: the sum of its indicators' highest value descriptors
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
    'Repair Due Work Max Marks',
    [
        'verbose' => [true, true, true],
        'perform' => true,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(new class extends App
{
    private const INDENT = '  ';

    private const COUNT_POSSIBLY_INCONSISTENT_DUE_WORKS_QUERY = "
        SELECT COUNT(*)
        FROM submission_box sb
        WHERE sb.use_rubric IS TRUE
    ";

    private const FIND_INCONSISTENT_DUE_WORKS_QUERY = "
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


        // Update queries
        $updateBodyQuery = $this->db->prepareQuery(self::UPDATE_RUBRIC_MAX_MARK_QUERY);


        // Find the list of inconsistent due works
        $this->db->beginTransaction();
        $inconsistentDueWorks = $this->db->executeQuery(self::FIND_INCONSISTENT_DUE_WORKS_QUERY);
        $allDueWorksCount     = $this->db->executeQuery(self::COUNT_POSSIBLY_INCONSISTENT_DUE_WORKS_QUERY)->fetchColumn();
        $this->log->notice(
            sprintf(
                '%d due works with inconsistent max marks found, out of %d',
                $inconsistentDueWorks->rowCount(),
                $allDueWorksCount
            )
        );
        foreach ($inconsistentDueWorks->getIterator() as $row) {
            $expectedMaxMark = $row['expected_max_mark'];
            $actualMaxMark   = $row['actual_max_mark'];
            $assessmentId    = $row['assessment_id'];
            $this->log->notice(sprintf('Updating due work #%d', $assessmentId));
            $this->log->notice(self::indent(1).sprintf('Expected: %12.2f', $expectedMaxMark));
            $this->log->notice(self::indent(1).sprintf('Actual:   %12.2f', $actualMaxMark));
            $updateBodyQuery->execute([
                'assessmentId' => $assessmentId,
                'maxMark'      => $expectedMaxMark,
            ]);
        }


        $this->db->rollBack();
//        if ($this->perform) {
//            $this->db->commit();
//        } else {
//            $this->db->rollBack();
//        }
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
