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
            print_r($data);
            die();
        }
    }
}
