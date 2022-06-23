<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar\EDiary;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Downloads the eDiary contents for each student
 */
class EDiary implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    protected bool $perform;


    /**
     * @var array
     */
    protected array $api;


    /**
     * Obtain events that started after this datetime
     *
     * @var \Alaress_Date_Format
     */
    protected \Alaress_Date_Format $from;


    /**
     * Obtain events that start before this datetime
     *
     * @var \Alaress_Date_Format
     */
    protected \Alaress_Date_Format $to;


    /**
     * Constructor
     *
     * @param LoggerInterface      $log
     * @param bool                 $perform
     * @param array                $api
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        \Alaress_Date_Format $from,
        \Alaress_Date_Format $to
    )
    {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;

        /* Obtain events between these two datetimes
         */
        $this->from = $from;
        $this->to = $to;
    }


    /**
     * @return void
     */
    public function import(): void
    {
        /* Get a list of Student external ids
         * Use the profile mappers to get a 1D array of those
         */

        /* For each person in that set,
         * Hit the endpoint and grab their calendar
         */

        /* Process each event
         */

        /* Get a list of Staff external ids
         * Use the profile mappers to get a 1D array of those
         */

        /* For each person in that set,
         * Hit the endpoint and grab their calendar
         */

        /* Process each event
         */
    }



    public function importEdiaryEvent(array $ediary) {}
}