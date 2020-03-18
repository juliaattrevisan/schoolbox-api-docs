<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\CommandLineTool;

use Schoolbox\Application\CliTool;

/**
 * Pre-parse all vCal events.
 */
class PreparseVcal extends CliTool\App
{
    public function run(): void
    {
        // Find every active vCal config
        /** @var iterable&\Calendar_Api_Config_Record[] $configs */
        $configs = \Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

        // Fetch it from file cache, then parse and store in Redis
        foreach ($configs as $config) {
            $id            = $config->getId();
            $decodedConfig = $config->decodeConfig();
            if ($decodedConfig instanceof \Calendar_Api_Config_Connector_Vcal) {
                $source = $this->retrieveSource($decodedConfig);
                if (isset($source)) {
                    // Build the calendar

                }
            }
        }
    }


    /**
     * Retrieve source data out of calendar.
     *
     * @param \Calendar_Api_Config_Connector_Vcal $config
     * @return string|null
     */
    private function retrieveSource(\Calendar_Api_Config_Connector_Vcal $config): ?string
    {
        /* Load calendar from locker cache
        * Cache step 1: cache load of VCAL file from URL
        */
        $cache   = new \Calendar_Api_Cache();
        $headers = [];

        if ($config->getAuthorization()) {
            $headers = ['Authorization' => 'Bearer ' . $config->getAuthorization()];
        }
        return $cache->init($config->getUrl(), $headers)->getStreamContents();
    }
}
