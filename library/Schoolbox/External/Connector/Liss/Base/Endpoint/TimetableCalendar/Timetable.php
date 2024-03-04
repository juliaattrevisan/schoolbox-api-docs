<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use Alaress\Database\Predicate\InvalidColumnException;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Liss\Base\Endpoint\PayloadCache;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper;

/**
 * Class Timetable
 *
 * Adds a bunch of (classname, classcode) records to our sis_class table.
 */
class Timetable implements IStrategy
{
    /**
     * This const corresponds to the enum for the config var external_liss_default_event_type
     * This is used to decide if we use numeric teacher ids, or alphanumeric teacher codes
     * If you're not using teacherId, you're presumed to be using teacherCode instead
     */
    private const TEACHER_IDENTIFIER_ID = 'teacherId';


    /**
     * Constructor.
     *
     * @param LoggerInterface $log Logs the things that happened, in a text file
     * @param bool $perform
     * @param string $teacherIdentifier
     * @param FilesystemProxy $filesystemProxy
     * @param Mapper $storageMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param ClassPeriodMapper $classPeriodMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private string $teacherIdentifier,
        private \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $timetablePeriodMapper
    ) {
    }


    /**
     * Import into suds timetable
     *
     * @return void
     * @throws Exception
     * @throws FilesystemException
     * @throws InvalidColumnException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Make all the data easier to manipulate by rearranging their guts
         */
        $message = (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))
            ->load(PayloadCache::PAYLOAD_TIMETABLE);

        if (null === $message) {
            $this->log->debug(sprintf(
                'No payloads for %s; skipping',
                PayloadCache::PAYLOAD_TIMETABLE
            ));
        }

        $this->log->info('publishTimetable: processing request');

        /* Truncate everything before re-adding
         * Note: This was supposed to already have been done via publishDailyData
         * which is supposed to go first
         *
         * $classPeriodMapper->truncate();
         */

        /* Decide which externalId format to use via config var
         */
        $isTeacherIds = $this->isTeacherIds();

        $allData = $message->getParam(1)->me['array'];

        foreach ($allData as $currentData) {
            /* An example $currentData that you can paste into a test.json
             * so you have a meaningful example of what you're working with
             * This came straight outta their 2020 devkit
             *
             * {"me":{"struct":{"DayNumber":{"me":{"i4":1},"mytype":1,"_php_class":null},"Period":{"me":{"string":"3"},"mytype":1,"_php_class":null},"ClassCode":{"me":{"string":"7PDH 2"},"mytype":1,"_php_class":null},"EdvalClassCode":{"me":{"string":"7PDH2"},"mytype":1,"_php_class":null},"TeacherId":{"me":{"string":"79455"},"mytype":1,"_php_class":null},"TeacherCode":{"me":{"string":"ANDN"},"mytype":1,"_php_class":null},"RoomCode":{"me":{"string":"B3"},"mytype":1,"_php_class":null}}},"mytype":3,"_php_class":null}
             */
            $struct = $currentData->me['struct'];

            /* Decide which externalId format to use via config var
             */
            if ($isTeacherIds) {
                $externalId = trim((string)($struct['TeacherId']->me['string'] ?? null));
            } else {
                $externalId = trim((string)($struct['TeacherCode']->me['string'] ?? null));
            }

            $dayNumber = trim((string)($struct['DayNumber']->me['i4'] ?? null));
            $periodName = trim((string)($struct['Period']->me['string'] ?? null));
            $classCode = trim((string)($struct['ClassCode']->me['string'] ?? null));
            $room = trim((string)($struct['RoomCode']->me['string'] ?? null));

            /* Find the Period data
             */
            $allPeriods = $this->timetablePeriodMapper->findByColumnList([
                'day' => $dayNumber,
                'title' => $periodName
            ]);

            /* Find the Class
             */
            $allClasses = $this->classEntryMapper->findByColumn('code', $classCode);

            /* Check that all three are provided
             * Save it!
             */
            if ($allPeriods->count() && $allClasses->count()) {
                try {
                    $classPeriod = $this->classPeriodMapper->findOneByColumnList([
                        'class_id' => $allClasses->first()->getId(),
                        'period_id' => $allPeriods->first()->getId(),
                        'teacher_external_id' => $externalId,
                        'room' => $room
                    ]);

                    $this->log->debug(sprintf(
                        'Reusing existing new class period %s on period %s, with teacher %s',
                        $allClasses->first()->getName(),
                        $allPeriods->first()->getTitle(),
                        $externalId
                    ));
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $classPeriod = $this->classPeriodMapper->createModel()
                        ->setClass($allClasses->first()->getId())
                        ->setPeriod($allPeriods->first()->getId())
                        ->setTeacher($externalId)
                        ->setRoom($room);

                    $this->log->debug(sprintf(
                        'Created new class period %s on period %s, with teacher %s',
                        $allClasses->first()->getName(),
                        $allPeriods->first()->getTitle(),
                        $externalId
                    ));

                    if ($this->perform) {
                        $this->classPeriodMapper->save($classPeriod);
                    }
                }
            } else {
                /* These ones are broken */
                if ($allPeriods->count() === 0) {
                    $this->log->warning(sprintf("publishTimetable: Failed to find period %s on day %s", trim((string)$struct['DayNumber']->me['i4']), trim((string)$struct['Period']->me['string'])));
                }

                if ($allClasses->count() === 0) {
                    $this->log->warning(sprintf("publishTimetable: Failed to find class %s", trim($struct['ClassCode']->me['string'])));
                }
            }
        }

        $this->log->info("publishTimetable: completed request");
    }


    /**
     * Decide if we're going to use teacherIds as the externalId for staff
     * or whether we use teacherCodes instead
     *
     * @return bool
     */
    private function isTeacherIds(): bool
    {
        return self::TEACHER_IDENTIFIER_ID === $this->teacherIdentifier;
    }
}
