<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Assessment\Term;
use Schoolbox\CampusMapper;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
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
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * Host address is where the xml files are located
     *
     * @var string
     */
    private $host;


    /**
     * The place where the kamar folder will be created which contains xml files
     * This is specified separately to $host but ultimately leads to the same place
     * $host is determined by user configuration in a ui
     * $streamDir is determined at schoolbox instance installation time by us
     *
     * @var string
     */
    private $streamDir;


    /**
     * @var UserProfile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * @var TimetableCampusMapper
     */
    private $timetableCampusMapper;


    /**
     * @var TimetableCycleMapper
     */
    private $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * @var Term\Mapper
     */
    private $termMapper;


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
     * @param CampusMapper                    $campusMapper
     * @param TimetableCampusMapper           $timetableCampusMapper
     * @param TimetableCycleMapper            $timetableCycleMapper
     * @param TimetablePeriodMapper           $timetablePeriodMapper
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     * @param Term\Mapper                     $termMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $streamDir,

        /* Profile pieces
         */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,

        /* Timetable pieces
         */
        CampusMapper $campusMapper,
        TimetableCampusMapper $timetableCampusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        Term\Mapper $termMapper
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
        $this->campusMapper = $campusMapper;
        $this->timetableCampusMapper = $timetableCampusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->termMapper = $termMapper;
    }


    /**
     * @return GenericCollection
     * @throws RuntimeException
     * @throws UnexpectedValueException
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

        /* Profile and student timetable xmls come with a datetime suffix
         *
         * Timetable Composite requires access across multiple xml files cross-referencing nodes
         * in profile xml so you can identify who is in a class ($container)
         */
        $dirhandle = opendir($this->host);

        $xml = [
            Receiver::XML_FULL => [],
            Receiver::XML_STUDENTTIMETABLES => []
        ];

        /* Make a stack of profile xmls (full_ ... .xml)
         * Make a stack of student timetable xmls (studenttimetables_ ... .xml)
         */
        $numFiles = 0;

        while ($xmlFile = readdir($dirhandle)) {
            if ($xmlFile !== '.' && $xmlFile !== '..') {
                if (strpos($xmlFile, Receiver::XML_FULL) !== false) {
                    $xml[Receiver::XML_FULL][] = $xmlFile;
                    $numFiles++;
                } elseif (strpos($xmlFile, Receiver::XML_STUDENTTIMETABLES) !== false) {
                    $xml[Receiver::XML_STUDENTTIMETABLES][] = $xmlFile;
                    $numFiles++;
                }

                /* TODO: Potentially manage stafftimetables_xxxx.xml files
                 * TODO: Potentially manage part_xxxx.xml files
                 */
            }
        }

        /* If there aren't any files to process, do nothing
         */
        if (!$numFiles) {
            $this->log->info('No xml files here; noop');

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
            $this->timetableCampusMapper,
            $this->timetableCycleMapper,
            $this->timetablePeriodMapper,
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
         */
        $bus->add(new Timetable\Definition(
            $this->log,
            $this->perform,
            $this->campusMapper,
            $this->timetableCampusMapper,
            $this->timetableCycleMapper,
            $this->timetablePeriodMapper
        ));

        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         * Recycle this instance in every iteration
         */
        $lookup = new Timetable\Lookup(TimetablePeriodMapper::getInstance(), UserMapper::getInstance());

        /* Load all the Student Timetable xmls
         */
        foreach ($xml[Receiver::XML_STUDENTTIMETABLES] as $xmlFile) {
            $bus->add(new Timetable\Classes(
                $this->log,
                $this->perform,
                $registry,
                new Batchxml(new Api($this->host . '/' . $xmlFile)),
                $lookup,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper,
                $this->termMapper
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
