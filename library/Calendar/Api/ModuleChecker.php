<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Calendar\Api;

use Calendar_Api_Config_Connector_Interface;
use Calendar_Api_Config_Connector_Booking;
use Calendar_Api_Config_Connector_EOTC;
use Calendar_Api_Config_Connector_Moderation;
use Schoolbox\IConfigLoader;

/**
 * Responsible for ensuring a calender config does not belong to a disabled module
 */
class ModuleChecker
{

    /** @var IConfigLoader */
    private $config;


    /**
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }


    /**
     * Returns whether the specified config's API is enabled.
     *
     * @param Calendar_Api_Config_Connector_Interface $config
     * @return bool
     */
    public function isEnabled(Calendar_Api_Config_Connector_Interface $config): bool
    {
        switch (\get_class($config)) {
            case Calendar_Api_Config_Connector_Booking::class:
            case Calendar_Api_Config_Connector_Moderation::class:
                return 1 === (int)$this->config->get('resource_booking_enabled');
            case Calendar_Api_Config_Connector_EOTC::class:
                return 1 === (int)$this->config->get('eotc_enabled');
            default:
                return true;
        }
    }
}
