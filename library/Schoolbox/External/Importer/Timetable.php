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
     * @var TimetablePeriodMapper
     */
    protected $timetablePeriodMapper;

    /**
     * Custom setup function
     */
    protected function setup()
    {
        $this->userMapper            = new UserMapper();
        $this->classMapper           = new ClassEntryMapper();
        $this->classPeriodMapper     = new ClassPeriodMapper();
        $this->timetablePeriodMapper = new TimetablePeriodMapper();
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     *
     * @return array
     */
    protected function parse(Reader $reader, LoggerInterface $log)
    {
        $result = [];
        foreach ($reader as $i => $row) {
            $log->info("Parsing row $i...");
            $key = $row['Class Code'];

            if (!isset($key)) {
                $log->warning('Mandatory field "Class Code" not defined: skipping!');
                continue;
            }

            if (!isset($result[$key])) {
                // Class
                $log->info(sprintf('Class "%s" has not been parsed: parsing now', $key));
                $class = new ClassEntry();
                $class
                    ->setCode($key)
                    ->setDescription($row['Class Description'])
                    ->setName($row['Class Name'])
                ;

                $result[$key] = [
                    'class'   => $class,
                    'periods' => new Collection(),
                ];
            } else {
                $log->info(sprintf('Class "%s" has been parsed', $key));
            }

            // Load period and teacher from DB
            try {
                /** @var TimetableCampus $timetableCampus */
                $timetableCampus = $this->timetableCampusMapper->findOneByNameAndCampusCode(
                    $row['Term Name'],
                    $row['Term Campus']
                );
            } catch (Mapper\Exception $e) {
                $log->warning(
                    sprintf(
                        'Term "%s" for campus "%s" not found: skipping!',
                        $row['Term Name'],
                        $row['Term Campus']
                    )
                );
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
                $log->warning(
                    sprintf(
                        'Period "%s", day "%s" for term "%s" / campus "%s" not found: skipping!',
                        $row['Period Name'],
                        $row['Period Day'],
                        $row['Term Name'],
                        $row['Term Campus']
                    )
                );
                continue;
            }

            try {
                $teacher = $this->userMapper->findOneByColumn('synergy_id', $row['Teacher Code']);
            } catch (Mapper\Exception $e) {
                $log->warning(sprintf('Teacher code "%s" not found: skipping!', $row['Teacher Code']));
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
            $log->info(
                sprintf(
                    'Period "%s", day "%s" has been parsed',
                    $row['Period Name'],
                    $row['Period Day']
                )
            );
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
        // * Class: none, update if there
        // * Class Periods: delete all existing and re-insert
        foreach ($this->parse($reader, $log) as $row) {
            /** @var ClassEntry $class */
            $class = $row['class'];
            $log->info(sprintf('Saving class "%s"', $class->getCode()));
            $this->classMapper->save($class);

            // Class mapper now has an ID, hence the periods may now be saved
            foreach ($row['periods'] as $period) {
                /** @var ClassPeriod $period */
                $log->info(
                    sprintf(
                        'Saving teacher "%s" for day %d, period "%s"',
                        $period->getTeacher()->getExternalId(),
                        $period->getPeriod()->getDay(),
                        $period->getPeriod()->getTitle()
                    )
                );
            }
            $this->classPeriodMapper->save($row['periods']);
        }
    }
}
