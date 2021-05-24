<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Calendar\Api;

use Calendar_Api_Config_Connector_Interface;

/**
 * Responsible for determining which calender connections are enabled for this SB instance
 */
class ApiHierarchyGenerator
{

    /** @var ModuleChecker */
    private $moduleChecker;


    /**
     * @param ModuleChecker $moduleChecker
     */
    public function __construct(ModuleChecker $moduleChecker)
    {
        $this->moduleChecker = $moduleChecker;
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
                $config    = new $className();

                if ($this->moduleChecker->isEnabled($config)) {
                    $allApi[$eventTypeFilename][$connectorClassFilename] = $config;
                }
            }
        }

        return $allApi;
    }
}
