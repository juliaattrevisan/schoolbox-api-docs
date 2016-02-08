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
            'Class Title'  => 'The display name for the class (only used if Class Code is not an existing class)',
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
        if ($this->performUpdate) {
            $this->log->notice('Cleaning up all existing enrolments');
            $this->enrolmentMapper->truncate();
        }
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
            /** @var ClassEntry $class */
            try {
                $class = $this->classMapper->findOneByColumn('code', $classCode);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf('Class "%s" not found: creating a new class', $classCode));
                $class = $this->classMapper->createModel();
                $class
                    ->setCode($classCode)
                    ->setName($row['Class Title'] ?: '')
                ;
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
        $result         = $this->parse();
        $createdClasses = [];
        foreach ($result as $enrolment) {
            $class = $enrolment->getClass();
            $identifier = sprintf(
                '"%s" for class "%s"',
                $enrolment->getUser()->getExternalId(),
                $class->getCode()
            );
            if ($this->performUpdate) {
                if (!$class->getId()) {
                    // This same class might have been created in an earlier enrolment update.
                    // I still need the ID regardless.
                    try {
                        $class = $this->classMapper->findOneByColumn('code', $class->getCode());
                        $this->log->info(sprintf('Class "%s" has already been created', $class->getCode()));
                    } catch (Mapper\Exception $e) {
                        $createdClasses[$class->getCode()] = true;
                        $this->log->info(sprintf('Creating class "%s"', $class->getCode()));
                        $this->classMapper->save($class);
                    }
                    $enrolment->setClass($class);
                }
                $this->log->info('Saving enrolment of student ' . $identifier);
                if ($this->enrolmentMapper->save($enrolment)) {
                    $this->log->info(sprintf('Enrolment saved (ID: #%d)', $enrolment->getId()));
                } else {
                    $this->log->error('Could not save enrolment!');
                }
            } else {
                if (!$class->getId()) {
                    if (!isset($createdClasses[$class->getCode()])) {
                        $createdClasses[$class->getCode()] = true;
                        $this->log->info(sprintf('Pretending to create class "%s"', $class->getCode()));
                    } else {
                        $this->log->info(sprintf('Class "%s" has already been pretend created', $class->getCode()));
                    }
                }
                $this->log->info('Pretending to save enrolment of student ' . $identifier);
            }
        }
    }
}
