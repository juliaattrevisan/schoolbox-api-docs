<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\External\Importer;

use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
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
     * @var Collection|Campus[]
     */
    protected $campusList;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var ClassEntryMapper
     */
    protected $classMapper;

    /**
     * @var ClassPeriodMapper
     */
    protected $classPeriodMapper;

    /**
     * @var timetableCampusMapper
     */
    protected $timetableCampusMapper;

    /**
     * @var TimetablePeriodMapper
     */
    protected $timetablePeriodMapper;

    /**
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        return [
            'Term Campus'       => 'The campus to import a timetable for (must already be imported)',
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
        $this->timetableCampusMapper = new TimetableCampusMapper();
        $this->timetablePeriodMapper = new TimetablePeriodMapper();
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
        $timetableCampusCache = [];
        $timetablePeriodCache = [];
        $teacherCache         = [];
        foreach ($this->reader as $i => $row) {
            $key = $row['Class Code'];

            if (!isset($key)) {
                $this->log->warning('Mandatory field "Class Code" not defined: skipping!');
                continue;
            }

            $key = trim($key);

            if (!isset($result[$key])) {
                // Class
                $class = new ClassEntry();
                $class
                    ->setCode($key)
                    ->setName($row['Class Title']);

                if (isset($row['Class Description'])) {
                    $class->setDescription($row['Class Description']);
                }

                $result[$key] = [
                    'class'   => $class,
                    'periods' => new Collection(),
                ];
            }

            // Load timetable campus from DB or cache
            $termTitle  = $row['Term Title'];
            $termCampus = $row['Term Campus'];
            /** @var TimetableCampus $timetableCampus */
            if (isset($timetableCampusCache[$termTitle][$termCampus])) {
                $timetableCampus = $timetableCampusCache[$termTitle][$termCampus];
            } else {
                try {
                    $timetableCampus = $this->timetableCampusMapper->findOneByNameAndCampusCode(
                        $termTitle,
                        $termCampus
                    );
                    $timetableCampusCache[$termTitle][$termCampus] = $timetableCampus;

                } catch (Mapper\Exception $e) {
                    $this->log->warning(
                        sprintf(
                            'Term "%s" for campus "%s" not found: skipping!',
                            $termTitle,
                            $termCampus
                        )
                    );
                    continue;
                }
            }

            // Load timetable period from DB or cache
            $periodTitle = $row['Period Title'];
            $periodDay   = $row['Period Day'];
            /** @var TimetablePeriod $timetablePeriod */
            if (isset($timetablePeriodCache[$timetableCampus->getCycle()->getId()][$periodTitle][$periodDay])) {
                $timetablePeriod = $timetablePeriodCache[$timetableCampus->getCycle()->getId()][$periodTitle][$periodDay];
            } else {
                try {
                    $timetablePeriod = $this->timetablePeriodMapper->findOneByColumnList(
                        [
                            'cycle_id' => $timetableCampus->getCycle()->getId(),
                            'title'    => $periodTitle,
                            'day'      => $periodDay,
                        ]
                    );

                    $timetablePeriodCache[$timetableCampus->getCycle()->getId()][$periodTitle][$periodDay] = $timetablePeriod;
                } catch (Mapper\Exception $e) {
                    $this->log->warning(
                        sprintf(
                            'Period "%s", day "%s" for term "%s" / campus "%s" not found: skipping!',
                            $periodTitle,
                            $periodDay,
                            $termTitle,
                            $termCampus
                        )
                    );
                    continue;
                }
            }

            // Load teacher from DB or cache
            $teacherCode = $row['Teacher Code'];
            /** @var \Schoolbox\User $teacher */
            if (!isset($teacherCache[$teacherCode])) {
                try {
                    $teacher = $this->userMapper->findByExternalId($teacherCode);
                    $teacherCache[$teacherCode] = $teacher;
                } catch (Mapper\Exception $e) {
                    $this->log->warning(sprintf('Teacher code "%s" not found', $teacherCode));
                }
            }

            // Add the class period for this item
            $classPeriod = new ClassPeriod();
            $classPeriod
                ->setClass($result[$key]['class'])
                ->setTeacher($teacherCode)
                ->setPeriod($timetablePeriod);

            if (isset($row['Class Room'])) {
                $classPeriod->setRoom($row['Class Room']);
            }

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
            foreach ($row['periods'] as $period) {
                /** @var ClassPeriod $period */
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
