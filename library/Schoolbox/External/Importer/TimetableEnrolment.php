<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

/**
 * Class TimetableEnrolment
 *
 * Importer for student enrolment into the Schoolbox connector.
 * Saves into the following tables:
 * * class_user
 *
 * Assumes that `Schoolbox\External\Importer\TimetableStructure` and
 * `Schoolbox\External\Importer\Timetable` have been run.
 *
 * @package Schoolbox\External\Importer
 */
class TimetableEnrolment extends AbstractImporter
{
    /**
     * @var \Alaress\Database\Connector\IConnector
     */
    private $externalDb;

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
        $this->externalDb      = \Schoolbox\Database\Factory::getInstanceExternal();
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
            $classCode   = trim($row['Class Code']);
            $studentCode = trim($row['Student Code']);
            if (!isset($classCode) || !isset($studentCode)) {
                $this->log->warning('Mandatory fields "Class Code" and "Student Code" not defined: skipping!');
                continue;
            }

            // Load student and class from DB
            /** @var ClassEntry $class */
            try {
                $class = $this->classMapper->findOneByColumn('code', $classCode, ['cache'=>false]);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf('Class "%s" not found: creating a new class', $classCode));
                $class = $this->classMapper->createModel();
                $class
                    ->setCode($classCode)
                    ->setName(trim($row['Class Title']))
                ;
            }
            try {
                $student = $this->userMapper->findByExternalId($studentCode);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf('Student "%s" not found: skipping!', $studentCode));
                continue;
            }
            $enrolment = new ClassInvolvement();
            $enrolment
                ->setUser($student)
                ->setClass($class);
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

            if ($this->performUpdate) {
                if (!$class->getId()) {
                    // This same class might have been created in an earlier enrolment update.
                    // I still need the ID regardless.
                    try {
                        $class = $this->classMapper->findOneByColumn('code', $class->getCode(), ['cache'=>false]);
                    } catch (Mapper\Exception $e) {
                        $createdClasses[$class->getCode()] = true;
                        $this->log->info(sprintf('Creating class "%s"', $class->getCode()));
                        $this->classMapper->save($class);
                    }
                    $enrolment->setClass($class);
                }
                $user = $enrolment->getUser();
                $enrolmentItems[] = "(NULL, '".$class->getId()."', '".$user->getId()."')";
            } else {
                if (!$class->getId()) {
                    if (!isset($createdClasses[$class->getCode()])) {
                        $createdClasses[$class->getCode()] = true;
                    }
                }
            }
        }
        if ($this->performUpdate && count($enrolmentItems) > 0) {
            $fullQuery = 'INSERT INTO class_user (id, class_id, user_id) VALUES ' . implode($enrolmentItems, ',') . ';';
            try {
                // Manual bulk insert into the DB of "$fullquery"
                $this->externalDb->executeQuery($fullQuery);
            } catch (Exception $e) {
                $this->log->error(sprintf("Could not save enrolment! %s", $e->getMessage()));
            }
        }
    }
}
