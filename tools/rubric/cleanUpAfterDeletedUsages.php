#!/usr/bin/php
<?php
/**
 * Repair script for cleaning up rubric data on spec due works after deletion of capability and indicator usages.
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
 * This script cleans up the existing broken data by:
 * - toggling off Use Rubric if a due work spec has no capability or indicator usages
 *    - Because we cannot enforce at a schema level that an indicator usage always has a capability usage attached,
 *      I have chosen to only repair assessments that have both 0 capability usages AND 0 indicator usages;
 *      otherwise we run the risk of trying to "fix" data that is already broken in a different way
 * - TODO ??? deleting a level if it no longer contains any descriptors
 *
 * The script ONLY affects due work specifications, not implementations.
 * It should never be necessary to repair this data on implementations,
 * because specs with this kind of invalid data cannot be imported to classes (this is the symptom our schools have
 * been observing).
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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


require_once __DIR__ . '/../../conf/core/require.php';

$opts   = getopt('vp', ['perform']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Rubric Usage Cleanup',
    [
        'verbose' => $opts['v'] ?? [],
        'perform' => isset($opts['p']) || isset($opts['perform']),
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
     * Is this actually performing an update?
     *
     * @var bool
     */
    private $perform = false;


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
        $this->now     = Alaress_Date_Format::now();
        $this->perform = $opts['perform'] ?? false;
        $config        = $di->get('config');
        $this->fqdn    = trim($config->get('site_hostname') . "." . $config->get('site_domain'), ';.');


        // log perform / dry run
        if ($this->perform) {
            $this->log->warning('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->warning('Running in dry-run mode: use -p to write changes to database');
        }

        // log verbosity
        $verbosity = count($opts['verbose'] ?? []);
        if ($verbosity <= 0) {
            $this->log->warning('Running in non-verbose mode: Use -v, -vv or -vvv for varying levels of verbosity');
        } elseif ($verbosity === 1) {
            $this->log->warning('Running in verbose mode (-v)');
        } elseif ($verbosity === 2) {
            $this->log->warning('Running in very verbose mode (-vv)');
        } elseif ($verbosity >= 3) {
            $this->log->warning('Running in very very verbose mode (-vvv)');
        }

        // Capability and indicator (not usage) queries
        $this->findAssessmentsWithoutRubricUsagesQuery
            = $this->db->prepareQuery(self::FIND_ASSESSMENTS_WITHOUT_RUBRIC_USAGES_QUERY);
        $this->disableUseRubricQuery
            = $this->db->prepareQuery(self::DISABLE_USE_RUBRIC_QUERY);

        // Wrap entire loop in a transaction
        $this->db->beginTransaction();
        try {
            $this->repair();
            if ($this->perform) {
                $this->log->notice('Repair complete: committing changes');
                $this->db->commit();
            } else {
                $this->log->notice('Repair dry run complete: rolling back changes');
                $this->db->rollBack();
            }
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
