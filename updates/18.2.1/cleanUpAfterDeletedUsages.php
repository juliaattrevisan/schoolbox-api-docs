#!/usr/bin/php
<?php

/**
 * Repair script for cleaning up rubric data on spec due works after deletion of capability and indicator usages.
 * See SA-8261.
 *
 * Business rules:
 * - It is LEGAL to delete indicators from a course or unit spec if they are used on due works in the spec
 * - It is ILLEGAL to have Use Rubric toggled on if no indicators are selected on a due work
 *
 * Prior to SA-8158, deleting the last in-use indicator from a course or unit spec did not toggle off the
 * Use Rubric setting on due works, leading to an invalid state where Use Rubric was on
 * but no indicators selected.
 * SA-8158 implemented post-delete hooks to ensure this could no longer happen,
 * but did not migrate existing broken data. This meant that while schools will no longer be able
 * to get their due work specs into an invalid state, they may still experience errors trying to import
 * courses where indicators were deleted before the fix from SA-8158 rolled out.
 *
 * This script cleans up the existing broken data by toggling off Use Rubric if a due work spec has
 * no capability or indicator usages.
 * Because we cannot enforce at a schema level that an indicator usage always has a capability usage attached,
 * I have chosen to only repair assessments that have both 0 capability usages AND 0 indicator usages;
 * otherwise we run the risk of trying to "fix" data that is already broken in a different way.
 *
 * The script ONLY affects due work specifications, not implementations.
 * It should never be necessary to repair this data on implementations,
 * because specs with this kind of invalid data cannot be imported to classes
 * (this is the symptom our schools have been observing).
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Learning\Course\DataModel\Specification;
use Zend\Uri\Uri;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Rubric Usage Cleanup',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(new class extends App
{
    private const INDENT = '  ';

    private const FIND_ASSESSMENTS_WITHOUT_RUBRIC_USAGES_QUERY = "
        SELECT
        sb.id,
        sb.assessment_id,
        sb.category,
        COUNT(DISTINCT rgu.rubric_group_id) AS capability_usage_count,
        COUNT(DISTINCT rcu.criterion_id)    AS indicator_usage_count,
        sb.use_rubric
        FROM submission_box sb
        LEFT JOIN rubric_group_usage     rgu ON sb.id = rgu.submission_box_id
        LEFT JOIN rubric_criterion_usage rcu ON sb.id = rcu.submission_box_id
        WHERE sb.use_rubric IS TRUE
        AND sb.category = :folderId
        GROUP BY sb.id
        HAVING capability_usage_count = 0 AND indicator_usage_count = 0;
    ";

    private const DISABLE_USE_RUBRIC_QUERY = "
        UPDATE submission_box sb
        SET   sb.use_rubric = FALSE
        WHERE sb.use_rubric IS TRUE
        AND sb.assessment_id = :assessmentId
    ";


    /**
     * Bit of a misnomer, but I didn't want this var name to get too long.
     * It finds due works:
     * - in a specified folder,
     * - that do not use any capabilities or indicators
     * - and have "Use Rubric" enabled.
     *
     * @var IQuery
     */
    private $findAssessmentsWithoutRubricUsagesQuery;


    /**
     * Toggles off "Use Rubric" for a particular due work if it has no capability or indicator usages
     *
     * @var IQuery
     */
    private $disableUseRubricQuery;

    /**
     * @var Logger
     */
    private $log;


    /**
     * @var IConnector
     */
    private $db;


    /**
     * @var Alaress_Date_Format
     */
    private $now;


    /**
     * The fully-qualified domain name for this instance.
     *
     * @var
     */
    private $fqdn;


    /**
     * Significant events: read these, if nothing else.
     *
     * Indexed by assessment ID.
     *
     * @var array|string[][]
     */
    private $significantEvents = [];


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit, or rollback (if an error is raised).
     *
     * @inheritdoc
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts, string $flags): void
    {
        // Install logger and DB
        $this->log     = $log;
        $this->db      = $di->get('databaseInternal');
        $this->now     = Alaress_Date_Format::now();
        $config        = $di->get('config');
        $this->fqdn    = trim($config->get('site_hostname') . "." . $config->get('site_domain'), ';.');

        // Prepare queries
        $this->findAssessmentsWithoutRubricUsagesQuery
            = $this->db->prepareQuery(self::FIND_ASSESSMENTS_WITHOUT_RUBRIC_USAGES_QUERY);
        $this->disableUseRubricQuery
            = $this->db->prepareQuery(self::DISABLE_USE_RUBRIC_QUERY);

        // Wrap entire loop in a transaction
        $this->db->beginTransaction();
        try {
            $this->repair();
            $this->log->notice('Repair complete: committing changes');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->log->error('Error occurred during repair: rolling back changes');
            $this->db->rollBack();
            throw $e;
        }


        // Talk about significant events
        if (!empty($this->significantEvents)) {
            $this->log->warning('Assessments affected:');
            $this->outputSignificantEvents();
        } else {
            $this->log->warning('No assessments affected');
        }
    }


    /**
     * Run the repair process.
     */
    private function repair(): void
    {
        // Find each course
        // Retrieve its capabilities+indicators
        /** @var Specification\Model[] $courses */
        $courses = Specification\Mapper::getInstance()->findAll();
        foreach ($courses as $course) {
            $this->log->info(
                sprintf(
                    'Finding units in course #%d (%s)',
                    $course->getFolder()->getId(),
                    $course->getTitle()
                )
            );

            /** @var Specification\Unit\Model[] $units */
            $units = Specification\Unit\Mapper::getInstance()->findAllByCourseFolderId($course->getFolder()->getId());
            foreach ($units as $unit) {
                $this->log->info(
                    sprintf(
                        self::indent(1) . 'finding assessments in unit #%d (%s)',
                        $unit->getFolder()->getId(),
                        $unit->getTitle()
                    )
                );

                $this->repairAssessmentsInUnit($unit->getFolder()->getId());
            }
        }
    }


    /**
     * Attempt to repair this set of assessments by toggling of Use Rubric if there's no rubric actually used
     *
     * @param int $unitId
     */
    private function repairAssessmentsInUnit(int $unitId): void
    {
        $this->findAssessmentsWithoutRubricUsagesQuery->execute(['folderId' => $unitId]);

        $hasBrokenData = false;
        foreach ($this->findAssessmentsWithoutRubricUsagesQuery->fetchColumnArray('assessment_id') as $assessmentId) {
            $hasBrokenData = true;

            $this->log->warning(
                sprintf(
                    'Assessment #%d has no capability or indicator usages, but has Use Rubric enabled: toggling off Use Rubric',
                    $assessmentId
                )
            );

            $this->logSignificantEvent(
                (int)$assessmentId,
                sprintf(
                    'Toggled off "Use Rubric" for assessment #%d',
                    $assessmentId
                )
            );

            $this->disableUseRubricQuery->execute(['assessmentId' => $assessmentId]);
        }

        if (! $hasBrokenData) {
            $this->log->info(
                sprintf(
                    self::indent(2) . 'No assessments with invalid rubric usages found in unit #%s',
                    $unitId
                )
            );
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


    /**
     * Log a significant event regarding an assessment.
     *
     * @param int    $assessmentId
     * @param string $message
     */
    private function logSignificantEvent(int $assessmentId, string $message): void
    {
        $this->significantEvents[$assessmentId][] = $message;
    }


    /**
     * Output significant events.
     */
    private function outputSignificantEvents(): void
    {
        foreach ($this->significantEvents as $assessmentId => $events) {
            $url = (new Uri())
                ->setScheme('https')
                ->setHost($this->fqdn)
                ->setPath('/learning/assessments/' . $assessmentId);
            $this->log->warning(self::indent(1) . $url->toString());
            foreach ($events as $event) {
                $this->log->warning(self::indent(2) . $event);
            }
        }
    }
});
