<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use PhpXmlRpc\Request;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

class ClassMemberships implements IStrategy
{
    /**
     * A list of classEntries that we saw previously
     *
     * @var array
     */
    private array $classEntries = [];


    /**
     * A list of classInvolvements that we saw previously
     *
     * @var array
     */
    private array $classInvolvements = [];


    /**
     * Constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param Request                $message
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private Request $message
    ) {
    }


    /**
     * Import into suds timetable
     *
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $classMemberships = $this->message->getParam(1)->me['array'];

        foreach ($classMemberships as $classMembership) {
            $classCode = (string)$classMembership->me['struct']['ClassCode']->me['string'];
            $externalId = (string)$classMembership->me['struct']['StudentId']->me['string'];

            /* This might sometimes exist; sometimes it won't be there
             */
            $edvalClassCode = (string)($classMembership->me['struct']['EdvalClassCode']->me['string'] ?? '');

            $this->log->info(sprintf(
                'Enrolling student %s in class %s (Edval Class Code: %s)',
                $externalId,
                $classCode,
                $edvalClassCode
            ));

            $classEntry = $this->createClassEntry($classCode);

            $this->createEnrolment($classEntry, $externalId);
        }
    }


    /**
     *  Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param string $classCode
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(string $classCode): ClassEntry
    {
        /* Constraints in the schema are case insensitive, unlike
         * these array keys - class codes sometimes appear like
         * "Speech Therapy" and "Speech therapy" so inserting them
         * twice will cause it to pack a sad. The classEntries array
         * treats these as unique whereas the db didn't
         */
        $existingKey = strtolower($classCode);

        if (isset($this->classEntries[$existingKey])) {
            /* If $classEntry was created in this import, get it from the local cache
             */
            $classEntry = $this->classEntries[$existingKey];
        } else {
            /* Otherwise it might have been created from another part of the import
             */
            try {
                $classEntry = $this->classEntryMapper->findOneByColumn('code', $classCode);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $classEntry = $this->classEntryMapper->createModel()
                    ->setName($classCode)
                    ->setCode($classCode);

                if ($this->perform) {
                    $this->classEntryMapper->save($classEntry);
                }
            }

            $this->classEntries[$existingKey] = $classEntry;
        }

        return $classEntry;
    }


    /**
     * Save an enrolment for user $externalId for the class $classEntry
     *
     * @param ClassEntry $classEntry
     * @param string     $externalId
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createEnrolment(
        ClassEntry $classEntry,
        string $externalId
    ): void {
        if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
            /* If class enrolment was created in this import we don't need to do anything else
             * This person was already enrolled in it
             */
            $this->log->debug(sprintf(
                'Reusing previous class involvement for %s in class %s',
                $externalId,
                $classEntry->getCode()
            ));
        } else {
            /* Otherwise it might have been created from another part of the import
             */
            try {
                $classInvolvement = $this->classInvolvementMapper->findOneByColumnList([
                    'class_id' => $classEntry->getId(),
                    'external_id' => $externalId
                ]);

                $this->log->debug(sprintf(
                    'Reusing previous class involvement for %s in class %s',
                    $externalId,
                    $classEntry->getCode()
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log->debug(sprintf(
                    'Creating new class involvement for %s in class %s',
                    $externalId,
                    $classEntry->getCode()
                ));

                $classInvolvement = $this->classInvolvementMapper->createModel()
                    ->setClass($classEntry)
                    ->setExternalId($externalId);

                if ($this->perform) {
                    $this->classInvolvementMapper->save($classInvolvement);
                }
            }

            /* Remember we found this
             */
            $this->classInvolvements[$classEntry->getId()][$externalId] = true;
        }
    }
}
