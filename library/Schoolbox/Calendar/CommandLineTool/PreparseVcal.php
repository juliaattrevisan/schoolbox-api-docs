<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\CommandLineTool;

use Alaress\Redis;
use Calendar_Api_Cache;
use Calendar_Api_Config_Connector_Interface;
use Calendar_Api_Config_Connector_Vcal;
use Calendar_Api_Config_Mapper;
use Calendar_Api_Config_Record;
use DateTime;
use DateTimeZone;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Schoolbox\Application\CliTool;

/**
 * Pre-parse all vCal events.
 */
class PreparseVcal extends CliTool\App
{
    public function run(): void
    {
        // Find every active vCal config
        /** @var iterable&Calendar_Api_Config_Record[] $configs */
        $configs = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();
        $this->getLogger()->info(sprintf('Found %d calendar configurations', count($configs)));

        // Fetch it from file cache, then parse and store in Redis
        foreach ($configs as $config) {
            $this->cacheCalendar($config);
        }
    }


    /**
     * Retrieve source data out of calendar.
     *
     * @param Calendar_Api_Config_Connector_Vcal $config
     * @return string|null
     */
    private function retrieveSource(Calendar_Api_Config_Connector_Vcal $config): ?string
    {
        $cache   = new \Calendar_Api_Cache();
        $headers = [];
        if ($config->getAuthorization()) {
            $headers = ['Authorization' => 'Bearer ' . $config->getAuthorization()];
        }
        return $cache->init($config->getUrl(), $headers)->getStreamContents();
    }


    /**
     * Convert an event source to an actual list of events.
     *
     * @param string $source
     * @return VCalendar
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function convertSourceToEvents(string $source): VCalendar
    {
        if (preg_match('/^(\x{FF}\x{FE})|(\x{FE}\x{FF})/', $source)) {
            $source = mb_convert_encoding($source, 'UTF-8', 'UTF-16');
        } else {
            // Just remove UTF8 BOM if it exists
            $source = preg_replace('/^(\x{EF}\x{BB}\x{BF})/', '', $source);
        }

        // Expand to the start/end of the last/next year
        $timezone = new DateTimeZone('UTC');
        /** @noinspection PhpUnhandledExceptionInspection */
        return Reader::read($source, Reader::OPTION_FORGIVING)
                     ->expand(
                         new DateTime('Jan 1 -1 year', $timezone),
                         new DateTime('Dec 31 +1 year', $timezone)
                     );
    }


    /**
     * Caches the calendar into a set of cached events.
     *
     * @param Calendar_Api_Config_Record $config
     */
    private function cacheCalendar(Calendar_Api_Config_Record $config): void
    {
        $this->getLogger()->debug(
            sprintf(
                'Caching calendar configuration #%d (%s)',
                $config->getId(),
                $config->getConnectorClass()
            )
        );
        $decodedConfig = $config->decodeConfig();
        if (!$decodedConfig instanceof Calendar_Api_Config_Connector_Vcal) {
            $this->getLogger()->debug(
                sprintf(
                    'Calendar configuration #%d is not a vCal config: skipping',
                    $config->getId()
                )
            );
            return;
        }

        $this->getLogger()->info(
            sprintf(
                'Retrieving raw vCal source for config #%d (%s)',
                $config->getId(),
                $decodedConfig->getUrl()
            )
        );
        $source = $this->retrieveSource($decodedConfig);

        $this->getLogger()->info(
            sprintf(
                'Parsing and caching raw vCal into events for config #%d (%s)',
                $config->getId(),
                $decodedConfig->getUrl()
            )
        );
        if (isset($source)) {
            Redis::getClientInstance()->setEx(
                $decodedConfig->determineCacheKey($config->getId()),
                Calendar_Api_Config_Connector_Vcal::CACHE_EXPIRY,
                serialize($this->convertSourceToEvents($source))
            );
        }
    }
}
