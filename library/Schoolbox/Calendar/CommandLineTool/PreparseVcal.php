<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\CommandLineTool;

use Calendar_Api_Config_Connector_Vcal;
use Calendar_Api_Config_Mapper;
use Calendar_Api_Config_Record;
use InvalidArgumentException;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Schoolbox\Application\CliTool;
use Schoolbox\Calendar\Cache\Vcal as VcalCache;

/**
 * Pre-parse all vCal events.
 */
class PreparseVcal extends CliTool\App
{
    /**
     * @var VcalCache
     */
    private $cache;


    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $this->cache = new VcalCache($this->getDi()->get('redis.cache'));

        // Find every active vCal config
        /** @var iterable&Calendar_Api_Config_Record[] $configs */
        $configs = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();
        $this->getLogger()->debug(sprintf('Found %d calendar configurations', count($configs)));

        // Fetch it from file cache, then parse and store in Redis
        foreach ($configs as $config) {
            $this->cacheCalendar($config);
        }
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

        if (!$decodedConfig->getUrl()) {
            $this->getLogger()->info(
                sprintf(
                    'Calendar configuration #%d has no URL: skipping',
                    $config->getId()
                )
            );
            return;
        }
        $this->getLogger()->info(
            sprintf(
                'Retrieving and caching raw vCal source for config #%d (%s)',
                $config->getId(),
                $decodedConfig->getUrl()
            )
        );
        try {
            $this->cache->write($decodedConfig);
        } catch (InvalidArgumentException | ParseException | InvalidDataException $e) {
            // There was a failure when writing the cache,
            // or reading from the URL in the config.
            // Whatever the case, log and continue.
            $this->getLogger()->warning(
                sprintf(
                    'Could not cache source: %s',
                    $e->getMessage()
                )
            );
        } catch (\Schoolbox\Application\Exception\Exception $e) {
            $this->getLogger()->warning(
                sprintf(
                    'Could not cache source: %s',
                    $e->getMessage()
                )
            );
            $config->addFailedCount();
            Calendar_Api_Config_Mapper::getInstance()->save($config);
        }
    }
}
