<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer;

use Schoolbox\Collection;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

/**
 * Class Timetable
 *
 * Importer for timetable into the Schoolbox connector.
 * Saves into the following tables:
 * * class
 * * class_period
 * Assumes that `Schoolbox\External\Importer\TimetableStructure` has been run.
 *
 * @package Schoolbox\External\Importer
 */
class Timetable extends AbstractImporter
{
    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var ClassEntryMapper
     */
    private $classMapper;

    /**
     *
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * @var TimetableCycleMapper
     */
    private $timetableCycleMapper;


    /**
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        return [
            'Term Identifier'   => 'The term identifier to import a timetable for (must already be imported)',
            'Term Title'        => 'The name of the term (must already be imported)',
            'Period Day'        => 'The day number that this period is on (must already be imported)',
            'Period Title'      => 'The name of this period (must already be imported)',
            'Class Code'        => 'The code for the class',
            'Class Title'       => 'The display name for the class',
            'Class Room'        => '(optional) The room that this class is in',
            'Class Description' => '(optional, currently unused) A description for this class',
            'Teacher Code'      => 'The code for the teacher of this class (must correspond to a teacher\'s external ID)',
        ];
    }


    /**
     * Custom setup function
     */
    protected function setup()
    {
        $this->userMapper            = new UserMapper();
        $this->classMapper           = new ClassEntryMapper();
        $this->classPeriodMapper     = new ClassPeriodMapper();
        $this->timetablePeriodMapper = new TimetablePeriodMapper();
        $this->timetableCycleMapper  = new TimetableCycleMapper();
    }


    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @return array
     */
    protected function parse()
    {
        $result               = [];
        $timetableCycleCache  = [];
        $timetablePeriodCache = [];

        foreach ($this->reader as $i => $row) {
            $key = trim($row['Class Code'] ?? '');

            /* The class code must exist, otherwise we can't import it
             * Purely whitespace class codes aren't valid class codes
             */
            if ('' === $key) {
                $this->log->warning('Mandatory field "Class Code" not defined: skipping!');
                continue;
            }

            /* Accumulate a list of unique ClassEntry instances
             *
             * Class Codes are expected to be unique across this data set
             * If it's not already in our ClassEntry cache, $result, add it
             */
            if (!isset($result[$key])) {
                $class = (new ClassEntry())
                    ->setCode($key)
                    ->setName(trim($row['Class Title'] ?? ''))
                    ->setDescription(trim($row['Class Description'] ?? ''));

                $result[$key] = [
                    'class'   => $class,
                    'periods' => new Collection(),
                ];
            }

            // Load timetable cycle from DB or cache
            $termTitle  = $row['Term Title'];
            $termIdentifier = $row['Term Identifier'];

            /* Reuse an instance of TimetableCycle that we previously fetched
             */
            if (isset($timetableCycleCache[$termTitle][$termIdentifier])) {
                $timetableCycle = $timetableCycleCache[$termTitle][$termIdentifier];
            } else {
                try {
                    $timetableCycle = $this->timetableCycleMapper->findOneByColumnList([
                        'title' => $termTitle,
                        'cycle_identifier' => $termIdentifier
                    ]);

                    $timetableCycleCache[$termTitle][$termIdentifier] = $timetableCycle;
                } catch (Mapper\Exception $e) {
                    /* Can't assign ClassEntry instances to Cycles that don't exist
                     * Cycles need to be created with Timetable Structure first
                     */
                    $this->log->warning(sprintf(
                        'Term "%s" for term identifier "%s" not found: skipping!',
                        $termTitle,
                        $termIdentifier
                    ));

                    continue;
                }
            }

            // Load timetable period from DB or cache
            $periodTitle = $row['Period Title'];
            $periodDay   = $row['Period Day'];

            /* Also maintain a cache of Timetable Period instances that we previously fetched
             */
            /** @var TimetablePeriod $timetablePeriod */
            if (isset($timetablePeriodCache[$timetableCycle->getId()][$periodTitle][$periodDay])) {
                $timetablePeriod = $timetablePeriodCache[$timetableCycle->getId()][$periodTitle][$periodDay];
            } else {
                try {
                    $timetablePeriod = $this->timetablePeriodMapper->findOneByColumnList([
                        'cycle_id' => $timetableCycle->getId(),
                        'title'    => $periodTitle,
                        'day'      => $periodDay,
                    ]);

                    $timetablePeriodCache[$timetableCycle->getId()][$periodTitle][$periodDay] = $timetablePeriod;
                } catch (Mapper\Exception $e) {
                    /* Can't assign ClassEntry instances to Periods that don't exist
                     * Periods need to be created with Timetable Structure first
                     */
                    $this->log->warning(sprintf(
                        'Period "%s", day "%s" for term "%s" / term identifier "%s" not found: skipping!',
                        $periodTitle,
                        $periodDay,
                        $termTitle,
                        $termIdentifier
                    ));

                    continue;
                }
            }

            /* Also maintain a cache of User instances that we previously fetched
             *
             * But this idea is obsolete now - anyway, warn the user that this external id didn't exist
             * and proceed normally regardless because they're not mandatory
             */
            $teacherExternalId = trim($row['Teacher Code']);

            if (!isset($teacherCache[$teacherExternalId])) {
                try {
                    $teacherCache[$teacherExternalId] = $this->userMapper->findByExternalId($teacherExternalId);
                } catch (Mapper\Exception $e) {
                    $this->log->warning(sprintf('Teacher code "%s" not found but assigning anyway', $teacherExternalId));
                }
            }

            // Add the class period for this item
            $classPeriod = (new ClassPeriod())
                ->setClass($result[$key]['class'])
                ->setTeacher($teacherExternalId)
                ->setRoom(trim($row['Class Room'] ?? ''))
                ->setPeriod($timetablePeriod);

            $result[$key]['periods']->add($classPeriod);
        }

        return $result;
    }


    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        if ($this->performUpdate) {
            $this->log->notice('Cleaning up all existing classes');
            $this->classMapper->truncate();
            $this->log->notice('Cleaning up all existing class teachers');
            $this->classPeriodMapper->truncate();
        }
        // Eventual override rules:
        // * Class: none, update if there
        // * Class Periods: delete all existing and re-insert
        foreach ($this->parse() as $row) {
            /** @var ClassEntry $class */
            $class = $row['class'];
            if ($this->performUpdate) {
                if ($this->classMapper->save($class)) {
                } else {
                    $this->log->error('Could not save class!');
                }
            }

            // Class mapper now has an ID, hence the periods may now be saved
            /** @var ClassPeriod $period */
            foreach ($row['periods'] as $period) {
                if ($this->performUpdate) {
                    if ($this->classPeriodMapper->save($period)) {
                    } else {
                        $this->log->error('Could not save class teacher!');
                    }
                }
            }
        }
    }
}
