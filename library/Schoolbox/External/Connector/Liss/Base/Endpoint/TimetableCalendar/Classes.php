<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use Alaress\Database\Predicate\InvalidColumnException;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\Connector\Liss\Base\Endpoint\PayloadCache;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper;

/**
 * Class Classes
 *
 * Adds a bunch of (classname, classcode) records to our sis_class table.
 */
class Classes implements IStrategy
{
    /**
     * A list of classEntries that we saw previously
     *
     * @var array
     */
    private array $classEntries = [];


    /**
     * Constructor.
     *
     * @param LoggerInterface $log Logs the things that happened, in a text file
     * @param bool $perform
     * @param FilesystemProxy $filesystemProxy
     * @param Mapper $storageMapper
     * @param ClassEntryMapper $classEntryMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private ClassEntryMapper $classEntryMapper
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
            ->load(PayloadCache::PAYLOAD_CLASSES);

        if (null === $message) {
            $this->log->debug(sprintf(
                'No payloads for %s; skipping',
                PayloadCache::PAYLOAD_CLASSES
            ));
        }

        $this->log->info('publishClasses: processing request');

        /* Truncate everything before re-adding
         * Note: This was supposed to already have been done via publishDailyData
         * which is supposed to go first
         *
         * $classEntryMapper->truncate();
         */
        $allData = $message->getParam(2)->me['array'];
        $classCount = 0;

        foreach ($allData as $currentData) {
            $struct = $currentData->me['struct'];
            $className = trim((string)($struct['CourseName']->me['string'] ?? null));
            $classCode = trim((string)($struct['ClassCode']->me['string'] ?? null));

            /* Constraints in the schema are case insensitive, unlike
             * these array keys - class codes sometimes appear like
             * "Speech Therapy" and "Speech therapy" so inserting them
             * twice will cause it to pack a sad. The classEntries array
             * treats these as unique whereas the db didn't
             */
            $existingKey = strtolower($classCode);

            if (isset($this->classEntries[$existingKey])) {
                /* Check if this class was added by this method
                 */
                $this->log->debug(sprintf(
                    '%s was already added in publishClasses; skipping',
                    $classCode
                ));
            } else {
                try {
                    /* Check if this class was added by a different method
                     */
                    $classEntry = $this->classEntryMapper->findOneByColumn('code', $classCode);

                    $this->log->debug(sprintf(
                        '%s was already added by some other means; skipping',
                        $classCode
                    ));
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $classEntry = $this->classEntryMapper->createModel()
                        ->setName($className)
                        ->setCode($classCode);

                    if ($this->perform) {
                        $this->classEntryMapper->save($classEntry);
                    }

                    $this->log->debug(sprintf(
                        'Adding %s',
                        $classCode
                    ));
                }

                /* Remember we saw it, in either outcome
                 */
                $this->classEntries[$existingKey] = $classEntry;
            }

            $classCount++;
        }

        $this->log->info(sprintf('publishClasses: completed adding %d classes', $classCount));
    }
}
