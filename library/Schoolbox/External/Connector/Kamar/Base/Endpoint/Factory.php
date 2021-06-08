<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Alaress_Date_Format;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable\WeekOffset;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Factory
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * Host address is where the xml files are located
     *
     * @var string
     */
    private string $host;


    /**
     * The place where the kamar folder will be created which contains xml files
     * This is specified separately to $host but ultimately leads to the same place
     * $host is determined by user configuration in a ui
     * $streamDir is determined at schoolbox instance installation time by us
     *
     * @var string
     */
    private string $streamDir;


    /**
     * @var UserProfile\Mapper
     */
    private UserProfile\Mapper $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private UserProfile\Student\Mapper $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private UserProfile\Parent\Mapper $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private UserProfile\Staff\Mapper $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private UserProfile\Relationship\Mapper $relationshipMapper;


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * @var TimetableCycleMapper
     */
    private TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private ClassPeriodMapper $classPeriodMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param string                          $host
     * @param string                          $streamDir
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param UserMapper                      $userMapper
     * @param TimetableCycleMapper            $timetableCycleMapper
     * @param TimetablePeriodMapper           $timetablePeriodMapper
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $streamDir,
        /* Profile pieces */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        /* Timetable pieces */
        UserMapper $userMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->streamDir = $streamDir;

        /* Profile pieces
         */
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

        /* Timetable pieces
         */
        $this->userMapper = $userMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * @return GenericCollection
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function produce(): GenericCollection
    {
        $this->log->info('Accessing local xml endpoints');

        /* Set up the kamar directory which is where all their xml files go
         */
        (new Setup(
            $this->log,
            $this->streamDir
        ))->import();

        /* Set up composite bus - everybody's xml gets on board and shuttled off
         * into schoolbox town
         */
        $bus = new GenericCollection(IStrategy::class);

        /* Check that the xml payloads meet a variety of preconditions before importing
         */
        $xml = (new Preconditions($this->host))->getXmlFiles();

        /* If there aren't any files to process, do nothing
         */
        if (empty($xml)) {
            $this->log->info('XML Preconditions not met. A transmission may be taking place and/or required quantity of xml files has yet to be met.');

            return $bus;
        }

        /* Purge previously-imported profile relationships and classes
         */
        $bus->add(new Prepare(
            $this->log,
            $this->perform,
            $this->baseProfileMapper,
            $this->studentProfileMapper,
            $this->parentProfileMapper,
            $this->staffProfileMapper,
            $this->relationshipMapper,
            $this->classEntryMapper,
            $this->classInvolvementMapper,
            $this->classPeriodMapper
        ));

        /* Load all the Profile xmls
         */
        $registry = new Profile\Registry();

        foreach ($xml[Receiver::XML_FULL] as $xmlFile) {
            $bus->add(new Profile\Profile(
                $this->log,
                $this->perform,
                new Batchxml(new Api($this->host . '/' . $xmlFile)),
                $registry,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ));
        }

        /* Set up the Timetable definition separately from the masses of timetable-related xml files
         * Schools must do this via a timetableStructure import csv
         */

        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         * Recycle this instance in every iteration
         */
        $lookup = new Timetable\Lookup($this->timetablePeriodMapper, $this->userMapper);

        /* Week Offset, Term selection
         *
         * TODO: Potentially configurable
         * You could potentially make this configurable, giving the school an opportunity
         * to view an arbitrary weekOffset's data (maybe repurpose a config field?)
         */
        $weekOffsetCalculator = new WeekOffset(
            $this->timetableCycleMapper,
            Alaress_Date_Format::now(),
            $this->log
        );

        $currentWeekOffset = $weekOffsetCalculator->findWeekOffset();
        $termSettings = $weekOffsetCalculator->findCurrentTerm();

        /* You need both of these to work stuff out
         *
         * The cycles are set up via a specific CSV import into Timetable Structure
         * They aren't defined in the kamar xmls; we import that info because we need
         * to name their Periods specifically and kamar has no period data
         */
        if (null === $currentWeekOffset) {
            $this->log->error('Could not determine week offset; check your Timetable Structure CSV import.');
            exit;
        }

        if (null === $termSettings) {
            $this->log->error('Could not determine term settings; check your Timetable Structure CSV import.');
            exit;
        }

        /* Figure out which term number we're in, to navigate xml files' nodes
         * Figure out which cycle we're in, when persisting data
         */
        $currentTerm = $termSettings['termIndex'];
        $currentCycleId = $termSettings['cycleId'];

        /* You need both of these to work stuff out
         */
        if (null === $currentTerm) {
            $this->log->error('Unknown term; did you set the campus timetable code and/or import that timetable structure csv?');
            exit;
        }

        if (null === $currentCycleId) {
            $this->log->error('Unknown cycle; did you set the campus timetable code and/or import that timetable structure csv');
            exit;
        }

        /* Otherwise continue onwards
         */
        $this->log->debug(sprintf(
            'Loading timetables using Week Offset %s, Term %s',
            $currentWeekOffset,
            $currentTerm
        ));

        /* Load all the Student Timetable xmls
         */
        foreach ($xml[Receiver::XML_STUDENTTIMETABLES] as $xmlFile) {
            $bus->add(new Timetable\Classes(
                $this->log,
                $this->perform,
                $registry,
                new Batchxml(new Api($this->host . '/' . $xmlFile)),
                $lookup,
                $currentWeekOffset,
                $currentTerm,
                $currentCycleId,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ));
        }

        /* Archive the xmls into a folder when we're done processing
         * We could delete them at any time, but if we just deleted it at the end of every
         * processing we'd have no idea how the schoolbox instance was populated - we'd
         * have destroyed the source. Instead, chuck them into a folder which we can remove
         * whenever we need
         */
        $bus->add(new Cleanup(
            $this->log,
            $this->perform,
            $this->streamDir,
            $this->baseProfileMapper,
            $this->studentProfileMapper,
            $this->parentProfileMapper,
            $this->staffProfileMapper,
            $this->relationshipMapper
        ));

        return $bus;
    }
}
