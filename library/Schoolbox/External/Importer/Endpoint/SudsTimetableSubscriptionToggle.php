<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

/**
 * A really particular use case where this specific calendar subscription
 * needs to be enabled or disabled contextually
 */
class SudsTimetableSubscriptionToggle
{
    /**
     * Uniquely identify the SUDS Timetable subscription
     */
    private const SUDS_TIMETABLE_IDENTIFIER = 'SudsTimetable';


    /**
     * @param \Calendar_Api_Config_Mapper $mapper
     */
    public function __construct(private \Calendar_Api_Config_Mapper $mapper)
    {
    }


    /**
     * Forcibly put the system into a state in which SUDS Timetable "just works"
     * such as setting up the SUDS Timetable calendar subscription
     *
     * I recognise it's a sideways evolution of this class' purpose, lowering
     * cohesion, but there's no contextually meaningful and convenient place
     * to put this unorthodox mechanism. The ideas are conceptually related,
     * and somewhat coupled due to bidness logic requirements.
     *
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function enableSudsTimetable(): void
    {
        /* Arbitrary summoning. If you can't find SUDS Timetable subscription,
         * just tell sentry - this subscription instance should already be here
         */
        $calendarConfig = $this->mapper->findOneByColumn('event_type', self::SUDS_TIMETABLE_IDENTIFIER);
        $calendarConfig->activate();

        $this->mapper->save($calendarConfig);
    }


    /**
     * Forcibly unsubscribe you from SUDS Timetable
     *
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function disableSudsTimetable(): void
    {
        /* Arbitrary summoning. If you can't find SUDS Timetable subscription,
         * just tell sentry - this subscription instance should already be here
         */
        $calendarConfig = $this->mapper->findOneByColumn('event_type', self::SUDS_TIMETABLE_IDENTIFIER);
        $calendarConfig->deactivate();

        $this->mapper->save($calendarConfig);
    }
}
