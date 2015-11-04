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
use Psr\Log\LoggerInterface;
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
            'Term Campus'  => 'The campus to import a timetable for (must already be imported)',
            'Term Title'   => 'The name of the term (must already be imported)',
            'Period Day'   => 'The day number that this period is on (must already be imported)',
            'Period Title' => 'The name of this period (must already be imported)',
            'Class Code'   => 'The code for the class',
            'Class Title'  => 'The display name for the class',
            'Class Room'   => 'The room that this class is in',
            'Teacher Code' => 'The code for the teacher of this class (must correspond to a teacher\'s external ID)',
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
        $result = [];
        foreach ($this->reader as $i => $row) {
            $this->log->info("Parsing row $i...");
            $key = $row['Class Code'];

            if (!isset($key)) {
                $this->log->warning('Mandatory field "Class Code" not defined: skipping!');
                continue;
            }

            if (!isset($result[$key])) {
                // Class
                $this->log->info(sprintf('Class "%s" has not been parsed: parsing now', $key));
                $class = new ClassEntry();
                $class
                    ->setCode($key)
                    ->setDescription($row['Class Description'])
                    ->setName($row['Class Title'])
                ;

                $result[$key] = [
                    'class'   => $class,
                    'periods' => new Collection(),
                ];
            } else {
                $this->log->info(sprintf('Class "%s" has been parsed', $key));
            }

            // Load period and teacher from DB
            try {
                /** @var TimetableCampus $timetableCampus */
                $timetableCampus = $this->timetableCampusMapper->findOneByNameAndCampusCode(
                    $row['Term Title'],
                    $row['Term Campus']
                );
            } catch (Mapper\Exception $e) {
                $this->log->warning(
                    sprintf(
                        'Term "%s" for campus "%s" not found: skipping!',
                        $row['Term Title'],
                        $row['Term Campus']
                    )
                );
                continue;
            }

            try {
                /** @var TimetablePeriod $timetablePeriod */
                $timetablePeriod = $this->timetablePeriodMapper->findOneByColumnList([
                    'cycle_id' => $timetableCampus->getCycle()->getId(),
                    'title'    => $row['Period Title'],
                    'day'      => $row['Period Day'],
                ]);
            } catch (Mapper\Exception $e) {
                $this->log->warning(
                    sprintf(
                        'Period "%s", day "%s" for term "%s" / campus "%s" not found: skipping!',
                        $row['Period Title'],
                        $row['Period Day'],
                        $row['Term Title'],
                        $row['Term Campus']
                    )
                );
                continue;
            }

            try {
                $teacher = $this->userMapper->findOneByColumn('synergy_id', $row['Teacher Code']);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf('Teacher code "%s" not found: skipping!', $row['Teacher Code']));
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
            $this->log->info(
                sprintf(
                    'Period "%s", day "%s" has been parsed',
                    $row['Period Title'],
                    $row['Period Day']
                )
            );
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
            $code  = $class->getCode();
            if ($this->performUpdate) {
                $this->log->info(sprintf('Saving class "%s"', $code));
                if ( $this->classMapper->save($class)) {
                    $this->log->info('Class saved (ID: #%d)', $class->getId());
                } else {
                    $this->log->error('Could not save class!');
                }
            } else {
                $this->log->info(sprintf('Pretending to save class "%s"', $code));
            }

            // Class mapper now has an ID, hence the periods may now be saved
            foreach ($row['periods'] as $period) {
                /** @var ClassPeriod $period */
                $identifier = sprintf(
                    '"%s" for day %d, period "%s"',
                    $period->getTeacher()->getExternalId(),
                    $period->getPeriod()->getDay(),
                    $period->getPeriod()->getTitle()
                );
                if ($this->performUpdate) {
                    $this->log->info('Saving class teacher ' . $identifier);
                    if ($this->classPeriodMapper->save($period)) {
                        $this->log->info('Class teacher entry saved (ID: #%d)', $period->getId());
                    } else {
                        $this->log->error('Could not save class teacher!');
                    }
                } else {
                    $this->log->info('Pretending to save class teacher ' . $identifier);
                }
            }
        }
    }
}
