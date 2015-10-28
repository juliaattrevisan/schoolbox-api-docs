<?php
/**
 * Importer for timetable into the Schoolbox connector.
 *
 * Saves into the following tables:
 * * class
 * * class_period
 *
 * Assumes that `Schoolbox\External\Importer\TimetableStructure` has been run.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
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

class Timetable
{
    /**
     * @var Reader
     */
    protected $csvReader;

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
     * @var TimetablePeriodMapper
     */
    protected $timetablePeriodMapper;

    /**
     * Constructor
     *
     * @param string $filename The filename to load from
     */
    public function __construct($filename)
    {
        $this->userMapper            = new UserMapper();
        $this->classMapper           = new ClassEntryMapper();
        $this->classPeriodMapper     = new ClassPeriodMapper();
        $this->timetableCampusMapper = new TimetableCampusMapper();
        $this->timetablePeriodMapper = new TimetablePeriodMapper();
        $this->csvReader             = new Reader($filename);
        $this->csvReader
            ->setLoadFromFirst(true)
            ->setFillDown(true)
        ;
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @return array
     */
    protected function parse()
    {
        $result = [];
        foreach ($this->csvReader as $row) {
            $key = $row['Class Code'];
            if (!isset($result[$key])) {
                // Class
                $class = new ClassEntry();
                $class
                    ->setCode($row['Class Code'])
                    ->setDescription($row['Class Description'])
                    ->setName($row['Class Name'])
                ;

                $result[$key] = [
                    'class'   => $class,
                    'periods' => new Collection(),
                ];
            }

            // Load period and teacher from DB
            try {
                /** @var TimetableCampus $timetableCampus */
                $timetableCampus = $this->timetableCampusMapper->findOneByNameAndCampusCode(
                    $row['Term Name'],
                    $row['Term Campus']
                );
            } catch (Mapper\Exception $e) {
                continue;
            }

            try {
                /** @var TimetablePeriod $timetablePeriod */
                $timetablePeriod = $this->timetablePeriodMapper->findOneByColumnList([
                    'cycle_id' => $timetableCampus->getCycle()->getId(),
                    'title'    => $row['Period Name'],
                    'day'      => $row['Period Day'],
                ]);
            } catch (Mapper\Exception $e) {
                continue;
            }

            try {
                $teacher = $this->userMapper->findOneByColumn('synergy_id', $row['Teacher Code']);
            } catch (Mapper\Exception $e) {
                continue;
            }
            $classPeriod = new ClassPeriod();
            $classPeriod
                ->setClass($result[$key]['class'])
                ->setRoom($row['Class Room'])
                ->setTeacher($teacher)
                ->setPeriod($timetablePeriod)
            ;
            $result[$key]['periods']->add($classPeriod);
        }
        return $result;
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        // Eventual override rules:
        // * Class: none, update if there
        // * Class Periods: delete all existing and re-insert
        foreach ($this->parse() as $row) {
            /** @var ClassEntry $class */
            $class = $row['class'];
            $this->classMapper->save($class);

            // Class mapper now has an ID, hence the periods may now be saved
            $this->classPeriodMapper->save($row['periods']);
        }
    }
}
