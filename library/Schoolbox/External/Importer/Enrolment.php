<?php
/**
 * Importer for student enrolment into the Schoolbox connector.
 *
 * Saves into the following tables:
 * * class_user
 *
 * Assumes that `Schoolbox\External\Importer\TimetableStructure` and
 * `Schoolbox\External\Importer\Timetable` have been run.
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
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCampus;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

class Enrolment
{
    /**
     * @var Reader
     */
    protected $csvReader;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var ClassEntryMapper
     */
    protected $classMapper;

    protected $enrolmentMapper;

    /**
     * Constructor
     *
     * @param string $filename The filename to load from
     */
    public function __construct($filename)
    {
        $this->userMapper      = new UserMapper();
        $this->classMapper     = new ClassEntryMapper();
        $this->enrolmentMapper = new ClassInvolvementMapper();
        $this->csvReader       = new Reader($filename);
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
            // Load student and class from DB
            try {
                /** @var ClassEntry $class */
                $class = $this->classMapper->findOneByColumn('code', $row['Class Code']);
            } catch (Mapper\Exception $e) {
                continue;
            }
            try {
                $student = $this->userMapper->findOneByColumn('synergy_id', $row['Student Code']);
            } catch (Mapper\Exception $e) {
                continue;
            }
            $enrolment = new ClassInvolvement();
            $enrolment
                ->setUser($student)
                ->setClass($class)
            ;
            $result[] = $enrolment;
        }
        return $result;
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        // Eventual override rules:
        // * Enrolment: delete all existing and re-insert
        $this->enrolmentMapper->save($this->parse());
    }
}
