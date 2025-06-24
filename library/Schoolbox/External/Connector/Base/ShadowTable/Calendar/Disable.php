<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\ShadowTable\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Base\ShadowTable\BaseDisable;
use Schoolbox\External\Event;
use Schoolbox\External\Event\Mapper;

/**
 * Disable writing to shadow tables (instead of to the base tables) for SUDS
 * calendar event mappers.
 *
 * This includes mappers for:
 * * `sis_event`
 * * `sis_event_campus`
 * * `sis_event_roletype`
 * * `sis_event_user`
 */
class Disable extends BaseDisable
{
    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $version
     * @param Mapper $eventMapper
     * @param Event\Roletype\Mapper $eventRoleTypeMapper
     * @param Event\Campus\Mapper $eventCampusMapper
     * @param Event\User\Mapper $eventUserMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $version,
        Event\Mapper $eventMapper,
        Event\Roletype\Mapper $eventRoleTypeMapper,
        Event\Campus\Mapper $eventCampusMapper,
        Event\User\Mapper $eventUserMapper,
    ) {
        parent::__construct(
            $log,
            $perform,
            $version,
            $eventMapper,
            $eventRoleTypeMapper,
            $eventCampusMapper,
            $eventUserMapper,
        );
    }
}
