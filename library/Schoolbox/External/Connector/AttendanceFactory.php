<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

use Schoolbox\IConfigLoader;
use Schoolbox\External\Importer\Endpoint\Factory;

/**
 * Produces a class that can manufacture an attendance link in some manner
 */
class AttendanceFactory
{
    /**
     * Constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(private IConfigLoader $configLoader)
    {
    }


    /**
     * Produces a specific DSN codec for the selected suds importer
     *
     * @return IAttendanceLink|null
     */
    public function produce(): ?IAttendanceLink
    {
        return match ($this->configLoader->get('schooldata_import_data_source')) {
            Factory::TASS_API => new \Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\AttendanceLink(
                (bool)$this->configLoader->get('external_attendance'),
                (int)$this->configLoader->get('external_attendance_days'),
                (bool)$this->configLoader->get('external_tass_attendance_show_prod_menu')
            ),
            Factory::CIVICA => new \Schoolbox\External\Connector\Civica\Base\Endpoint\Codec\AttendanceLink(
                (bool)$this->configLoader->get('external_attendance'),
                (int)$this->configLoader->get('external_attendance_days')
            ),
            Factory::SENTRAL => new \Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec\AttendanceLink(
                (bool)$this->configLoader->get('external_attendance'),
                (int)$this->configLoader->get('external_attendance_days')
            ),
            Factory::VERACROSS => new \Schoolbox\External\Connector\Veracross\Base\Endpoint\Codec\AttendanceLink(
                (bool)$this->configLoader->get('external_attendance'),
                (int)$this->configLoader->get('external_attendance_days')
            ),
            default => null,
        };
    }
}
