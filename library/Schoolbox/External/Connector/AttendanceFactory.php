<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

use Schoolbox\Config\Loader;

/**
 * Produces a class that can manufacture an attendance link in some manner
 */
class AttendanceFactory
{
    /**
     * Produces a specific DSN codec for the selected suds importer
     *
     * @param string $type
     * @return IAttendanceLink|null
     */
    public function produce(string $type): ?IAttendanceLink
    {
        $config = Loader::getInstance();

        switch ($type) {
            case \Schoolbox\External\Importer\Endpoint\Factory::TASS_API:
                return new \Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\AttendanceLink(
                    (bool)$config->get('external_attendance'),
                    (int)$config->get('external_attendance_days'),
                    (string)$config->get('external_tass_attendance_show_prod_menu')
                );
        }

        return null;
    }
}
