<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class AdditionalSubjects
 *
 * Adds additional non-timetabled subjects from the sis.
 *
 * These don't have any associated sis_class_period records;
 * it only has sis_class and sis_class_user records.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class AdditionalSubjects implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Api
     */
    private $api;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var Lookup
     */
    private $lookup;


    /**
     * TimetableClass constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Api[]                  $api
     * @param Lookup                 $lookup
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        Lookup $lookup,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->lookup = $lookup;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
    }


    /**
     * Import data from api endpoints into local schema
     */
    public function import(): void
    {
        /* Cache a flat key-value array of (user.id, user.synergy_id)
         * The alternative would be to iterate findById() in a loop which probably costs more at scale
         *
         * Cache a flat key-value array of (day, period)
         */
        $this->lookup->init();

        $users = $this->lookup->getUsers();
        $involvements = $this->lookup->getInvolvements();

        foreach ($users as $externalId => $userData) {
            $data = $this->api[DSNCodec::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudentSubjects', [
                'code' => $externalId
            ]);

            /* No additional subjects
             */
            if (!isset($data[$externalId])) {
                continue;
            }

            /* Otherwise, add some more things that we didn't already have from
             * the student's timetable import
             */
            foreach ($data[$externalId] as $additionalSubject) {
                $code = "{$additionalSubject['year_grp']}-{$additionalSubject['sub_code']}-{$additionalSubject['class']}";

                /* If they're already involved, don't worry about it
                 *
                 * This hash lookup lets us skips a lot of mapper nonsense if we don't need to do anything
                 * since we don't need to ->find()
                 */
                if (isset($involvements[$externalId][$code])) {
                    continue;
                }

                /* Otherwise install them and move onwards
                 *
                 * First find out if the subject already exists; if so, use its existing record
                 * to create a new class involvement. If it doesn't, create the class, then
                 * create the involvement
                 */
                try {
                    $class = $this->classEntryMapper->findOneByColumn('code', $code);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $class = $this->classEntryMapper->createModel()
                        ->setName($additionalSubject['sub_long'])
                        ->setCode($code);

                    $this->classEntryMapper->save($class);

                    $this->log->info(sprintf(
                        "Additional subject %s (%s) added for %s (user %s)",
                        $additionalSubject['sub_long'],
                        $code,
                        "{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}",
                        $externalId
                    ));
                }

                /* Find out if there's already an involvement, adding it if necessary
                 */
                try {
                    $classInvolvement = $this->classInvolvementMapper->findOneByColumnList([
                        'external_id' => $externalId,
                        'class_id'    => $class->getId()
                    ]);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $classInvolvement = $this->classInvolvementMapper->createModel()
                        ->setExternalId((string)$externalId)
                        ->setClass($class);

                    $this->classInvolvementMapper->save($classInvolvement);

                    $this->log->info(sprintf(
                        "Additional subject involvement in %s (%s) added for %s (user %s)",
                        $additionalSubject['sub_long'],
                        $code,
                        "{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}",
                        $externalId
                    ));
                }
            }
        }
    }
}
