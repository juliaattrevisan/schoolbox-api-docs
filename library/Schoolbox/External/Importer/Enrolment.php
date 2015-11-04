<?php
/**
 * Importer for student enrolment into the Schoolbox connector.
 * Saves into the following tables:
 * * class_user
 * Assumes that `Schoolbox\External\Importer\TimetableStructure` and
 * `Schoolbox\External\Importer\Timetable` have been run.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
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

    /**
     * @var ClassInvolvementMapper
     */
    protected $enrolmentMapper;

    /**
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        return [
            'Class Code'   => 'The code for the class',
            'Student Code' => 'The code for the student (must correspond to a student\'s external ID)',
        ];
    }

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
     * @return array|ClassInvolvement[]
     */
    protected function parse()
    {
        $result = [];
        foreach ($this->reader as $i => $row) {
            $this->log->info("Parsing row $i...");
            $classCode   = $row['Class Code'];
            $studentCode = $row['Student Code'];
            if (!isset($classCode) || !isset($studentCode)) {
                $this->log->warning('Mandatory fields "Class Code" and "Student Code" not defined: skipping!');
                continue;
            }

            // Load student and class from DB
            try {
                /** @var ClassEntry $class */
                $class = $this->classMapper->findOneByColumn('code', $classCode);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf('Class "%s" not found: skipping!', $classCode));
                continue;
            }
            try {
                $student = $this->userMapper->findOneByColumn('synergy_id', $studentCode);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf('Student "%s" not found: skipping!', $studentCode));
                continue;
            }
            $enrolment = new ClassInvolvement();
            $enrolment
                ->setUser($student)
                ->setClass($class);
            $this->log->info(sprintf('Class "%s", student "%s" has been parsed', $classCode, $studentCode));
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
        $result = $this->parse();
        foreach ($result as $enrolment) {
            $identifier = sprintf(
                '"%s" for class "%s"',
                $enrolment->getUser()->getExternalId(),
                $enrolment->getClass()->getCode()
            );
            if ($this->performUpdate) {
                $this->log->info('Saving enrolment of student ' . $identifier);
                if ($this->enrolmentMapper->save($enrolment)) {
                    $this->log->info('Enrolment saved (ID: #%d)', $enrolment->getId());
                } else {
                    $this->log->error('Could not save enrolment!');
                }
            } else {
                $this->log->info('Pretending to save enrolment of student ' . $identifier);
            }
        }
    }
}
