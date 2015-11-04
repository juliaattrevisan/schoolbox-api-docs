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
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

class Enrolment extends AbstractImporter
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
     * Custom setup function
     */
    protected function setup()
    {
        $this->userMapper      = new UserMapper();
        $this->classMapper     = new ClassEntryMapper();
        $this->enrolmentMapper = new ClassInvolvementMapper();
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     *
     * @return array|ClassInvolvement[]
     */
    protected function parse(Reader $reader, LoggerInterface $log)
    {
        $result = [];
        foreach ($reader as $i => $row) {
            $log->info("Parsing row $i...");
            $classCode   = $row['Class Code'];
            $studentCode = $row['Student Code'];
            if (!isset($classCode) || !isset($studentCode)) {
                $log->warning('Mandatory fields "Class Code" and "Student Code" not defined: skipping!');
                continue;
            }

            // Load student and class from DB
            try {
                /** @var ClassEntry $class */
                $class = $this->classMapper->findOneByColumn('code', $classCode);
            } catch (Mapper\Exception $e) {
                $log->warning(sprintf('Class "%s" not found: skipping!', $classCode));
                continue;
            }
            try {
                $student = $this->userMapper->findOneByColumn('synergy_id', $studentCode);
            } catch (Mapper\Exception $e) {
                $log->warning(sprintf('Student "%s" not found: skipping!', $studentCode));
                continue;
            }
            $enrolment = new ClassInvolvement();
            $enrolment
                ->setUser($student)
                ->setClass($class)
            ;
            $log->info(sprintf('Class "%s", student "%s" has been parsed', $classCode, $studentCode));
            $result[] = $enrolment;
        }
        return $result;
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     */
    public function save(Reader $reader, LoggerInterface $log)
    {
        // Eventual override rules:
        // * Enrolment: delete all existing and re-insert
        $result = $this->parse($reader, $log);
        foreach ($result as $enrolment) {
            $log->info(
                sprintf(
                    'Saving enrolment of student "%s" for class "%s"',
                    $enrolment->getUser()->getExternalId(),
                    $enrolment->getClass()->getCode()
                )
            );
        }
        $this->enrolmentMapper->save($result);
    }
}
