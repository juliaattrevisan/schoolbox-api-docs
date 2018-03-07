<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Calendar\Api;

use Calendar_Api_Config_Connector_Booking;
use Calendar_Api_Config_Connector_EOTC;
use Calendar_Api_Config_Connector_Interface;
use Calendar_Api_Config_Connector_Moderation;
use Schoolbox\IConfigLoader;


/**
 * Responsible for determining which calender connections are enabled for this SB instance
 */
class ApiHierarchyGenerator
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
     * Generate a tree of available EventSource/Connector pairs
     *
     * @return Calendar_Api_Config_Connector_Interface[][]
     */
    public function generateApiHierarchy(): array
    {
        /* Figure out which Event Sources are around, and which Connectors
         * are available for each Event Sources.
         */
        $path = __DIR__ . '/EventType';

        $allApi = [];

        /* Just get the dir names (these correspond to event types)
         */
        /** @var string[] $dirNames */
        $dirNames = array_filter(glob("{$path}/*"), 'is_dir');
        foreach ($dirNames as $dirName) {
            $eventTypeFilename = basename($dirName);

            /* Path to connectors
             */
            $subPath = "{$path}/{$eventTypeFilename}";

            /** @var string[] $dirNames */
            $subDirNames = array_filter(glob("{$subPath}/*"), 'is_dir');
            foreach ($subDirNames as $subDirName) {
                $connectorClassFilename = basename($subDirName);

                $className = "Calendar_Api_Config_Connector_{$connectorClassFilename}";

                if ($this->isEnabled($className)) {
                    $allApi[$eventTypeFilename][$connectorClassFilename] = new $className();
                }
            }
        }

        return $allApi;
    }


    /**
     * Returns whether the specified config's API is enabled.
     *
     * @param string $className
     * @return bool
     */
    private function isEnabled(string $className): bool
    {
        switch ($className) {
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
