<?php
/**
 * Querystring
 *
 * Rebuild a querystring or find out the particular pieces
 *  - topic slug
 *  - page number
 *  - limit
 *  - show paginator
 * Encapsulate in a stringable class
 * Encapsulate helpers
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\TopicMapper;

class Querystring
{

    /**
     * Querystring param for the Compact Mode option
     */
    public const COMPACT_MODE_PARAM = 'c';

    /**
     * Querystring param for the current Page Number chosen
     */
    public const PAGE_NUMBER_PARAM = 'p';

    /**
     * Querystring param for the current Limit chosen
     */
    public const LIMIT_PARAM = 'l';

    /**
     * Querystring param for the current topic chosen
     */
    public const TOPIC_SLUG_PARAM = 'topic';

    /**
     * Querystring param for the Hide Pagination option
     */
    public const HIDE_PAGINATOR_PARAM = 'hp';


    /**
     * An array of key/value pairs corresponding to GET params
     *
     * @var array
     */
    private $querystring;


    /**
     * Querystring constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->querystring = [
            self::COMPACT_MODE_PARAM => $data[self::COMPACT_MODE_PARAM],
            self::TOPIC_SLUG_PARAM => $data[self::TOPIC_SLUG_PARAM],
            self::PAGE_NUMBER_PARAM => $data[self::PAGE_NUMBER_PARAM],
            self::LIMIT_PARAM => $data[self::LIMIT_PARAM],
            self::HIDE_PAGINATOR_PARAM => $data[self::HIDE_PAGINATOR_PARAM]
        ];
    }


    /**
     * Current compact mode setting
     *
     * @return mixed
     */
    public function getCompactMode()
    {
        return $this->querystring[self::COMPACT_MODE_PARAM];
    }

    /**
     * Currently-chosen topic slug
     *
     * @return mixed
     */
    public function getSlug()
    {
        return $this->querystring[self::TOPIC_SLUG_PARAM];
    }


    /**
     * Current page index
     *
     * @return mixed
     */
    public function getPageNum()
    {
        return $this->determinePageNum($this->querystring[self::PAGE_NUMBER_PARAM]);
    }


    /**
     * Number of articles to obtain and dispaly
     *
     * @return mixed
     */
    public function getLimit()
    {
        return $this->querystring[self::LIMIT_PARAM];
    }


    /**
     * The option to determine whether paginator shows in the ui
     *
     * @return bool
     */
    public function getHidePaginator(): bool
    {
        return (bool)$this->querystring[self::HIDE_PAGINATOR_PARAM];
    }


    /**
     * Array representation of the params
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->querystring;
    }


    /**
     * String representation of the original values provided
     * Remove keys that have empty values
     *
     * @return string
     */
    public function __toString()
    {
        return http_build_query(array_filter($this->querystring, function($value, $key) {
            return \strlen($value);
        }, ARRAY_FILTER_USE_BOTH));
    }


    /**
     * Determine a slug from a topic id
     * TODO: Probably should be assertSlug()
     *
     * @param int $topicId
     * @return string
     */
    private function determineSlug(int $topicId): string
    {
        try {
            return TopicMapper::getInstance()->findById($topicId)->getSlug();
        } catch (MapperException $e) {
            // unknown topic id; just return the default All Topics slug
        }

        return Mapper::TOPIC_ALL_SLUG;
    }


    /**
     * The page number to obtain; default to 1
     * Otherwise use the page number provided
     *
     * @param null|string $param
     * @return int
     */
    private function determinePageNum(?string $param): int
    {
        return (int)$param ?: 1;
    }
}
