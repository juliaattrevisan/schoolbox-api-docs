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
use InvalidArgumentException;
use Predis\ClientInterface as RedisClient;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;

/**
 * Cache helper for vCal events.
 */
class Vcal
{
    private const CACHE_KEY_BASE = 'vcal.%s';


    // Cache expiry: 2 hours?
    private const CACHE_EXPIRY = 7200;


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
     * @throws InvalidArgumentException if the config has no URL
     * @throws InvalidDataException     if the URL contains bad data
     * @throws ParseException           if the URL contains bad data
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function write(CalendarConfig $config): void
    {
        $source = $this->retrieveSource($config);

        if (isset($source)) {
            $this->redis->setex(
                $this->determineCacheKey($config),
                self::CACHE_EXPIRY,
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
        $cacheKey = $this->determineCacheKey($config);
        if ($this->redis->exists($cacheKey)) {
            /** issues with extracting the cached calendar, we should delete and re fetch */
            set_error_handler(function () use ($cacheKey) {
                // If we can't unserialize the data, delete it.
                $this->redis->del($cacheKey);
                return null;
            }, E_ALL);
            $cal =  unserialize($this->redis->get($cacheKey), [VCalendar::class]);

            // go back to the usual error handler
            restore_error_handler();

            return $cal;
        }
        return null;
    }


    /**
     * Retrieve source data out of calendar.
     *
     * @param CalendarConfig $config
     * @return string|null
     * @throws InvalidArgumentException if the config has no URL
     * @throws \Schoolbox\Application\Exception\Exception
     */
    private function retrieveSource(CalendarConfig $config): ?string
    {
        $cache   = new Calendar_Api_Cache();
        $headers = [];
        if ($config->getAuthorization()) {
            $headers = ['Authorization' => 'Bearer ' . $config->getAuthorization()];
        }

        // Don't actually store the file in cache.
        // (Use the cache class because it contains logic for handling proxy
        //  servers, failures, relative URLs and probably other stuff.)
        try {
            $cache->init($config->getUrl(), $headers);
        } catch (InvalidArgumentException $e) {
            // The cached calendar wasn't able to be loaded.
            // Maybe the config's URL was invalid or empty?
            return null;
        }

        $source = $cache->getStreamContents();
        $cache->invalidateCache();
        return $source;
    }


    /**
     * Convert an event source to an actual list of events.
     *
     * @param string $source
     * @return VCalendar
     * @throws InvalidDataException apparently. What it actually throws is a ParseException: see below
     * @throws ParseException       if the document can't be parsed
     */
    private function convertSourceToEvents(string $source): VCalendar
    {
        if (preg_match('/^(\x{FF}\x{FE})|(\x{FE}\x{FF})/', $source)) {
            $source = mb_convert_encoding($source, 'UTF-8', 'UTF-16');
        } else {
            // Just remove UTF8 BOM if it exists
            $source = preg_replace('/^(\x{EF}\x{BB}\x{BF})/', '', $source);
        }

        //fix broken LastModified date field \t character
        $source = str_replace('LAST-MODIFIED:	', 'LAST-MODIFIED:', $source);

        // Expand 1 year in both directions
        $timezone = new DateTimeZone('UTC');
        $document = Reader::read($source, Reader::OPTION_FORGIVING);
        if ($document instanceof VCalendar) {
            $document = $document->expand(
                new DateTimeImmutable('-1 year', $timezone),
                new DateTimeImmutable('+2 year', $timezone)
            );
        }
        return $document;
    }

    /**
     * Checks whether cache exists for this calendar config
     * @param CalendarConfig $config
     * @return bool
     */
    public function keyExists(CalendarConfig $config): bool
    {
        $cacheKey = $this->determineCacheKey($config);
        return (bool)$this->redis->exists($cacheKey);
    }

    /**
     * Determine the cache key to read/write.
     *
     * @param CalendarConfig $config
     * @return string
     */
    private function determineCacheKey(CalendarConfig $config): string
    {
        // Can't use config's PK, as it doesn't exist in the encoded object.
        // We can only use its URL.
        return sprintf(self::CACHE_KEY_BASE, hash('sha256', $config->getUrl()));
    }
}
