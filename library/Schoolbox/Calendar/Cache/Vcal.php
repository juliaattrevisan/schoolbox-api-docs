<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Cache;

use Calendar_Api_Cache;
use Calendar_Api_Config_Connector_Vcal as CalendarConfig;
use DateTimeImmutable;
use DateTimeZone;
use Predis\Client as RedisClient;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * Class Vcal
 */
class Vcal
{
    /**
     * @var RedisClient
     */
    private $redis;


    /**
     * Constructor
     *
     * @param RedisClient $redis
     */
    public function __construct(RedisClient $redis)
    {
        $this->redis = $redis;
    }


    /**
     * Stores this calendar's events in cache.
     *
     * @param CalendarConfig $config
     */
    public function write(CalendarConfig $config): void
    {
        $source = $this->retrieveSource($config);

        if (isset($source)) {
            $this->redis->setEx(
                $config->determineCacheKey($config->getId()),
                CalendarConfig::CACHE_EXPIRY,
                serialize($this->convertSourceToEvents($source))
            );
        }
    }


    /**
     * Retrieves this calendar's events from cache.
     *
     * @param CalendarConfig $config
     * @return VCalendar|null
     */
    public function read(CalendarConfig $config): ?VCalendar
    {
        return unserialize(
            $this->redis->get($config->determineCacheKey($config->getId())),
            [VCalendar::class]
        );
    }


    /**
     * Retrieve source data out of calendar.
     *
     * @param CalendarConfig $config
     * @return string|null
     */
    private function retrieveSource(CalendarConfig $config): ?string
    {
        // TODO ignore cache, use cURL or what have you to get file
        $cache   = new Calendar_Api_Cache();
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
                         new DateTimeImmutable('Jan 1 -1 year', $timezone),
                         new DateTimeImmutable('Dec 31 +1 year', $timezone)
                     );
    }
}
