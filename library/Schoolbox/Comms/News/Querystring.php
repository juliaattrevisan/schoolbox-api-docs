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
    public const PAGE_NUMBER_PARAM = 'page';

    /**
     * Querystring param for the current Limit chosen
     */
    public const LIMIT_PARAM = 'l';

    /**
     * Querystring param for the current topic chosen
     */
    public const TOPIC_SLUG_PARAM = 'topic';

    /**
     * Querystring param for the Show Pagination option
     */
    public const SHOW_PAGINATOR_PARAM = 'sp';

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
//        $compactMode = $data[self::COMPACT_MODE_PARAM];
//        $slug = $data[self::TOPIC_SLUG_PARAM];
//        $pageNum = $this->determinePageNum($data[self::PAGE_NUMBER_PARAM]);
//        $limit = $this->determineLimit($data[self::LIMIT_PARAM]) ?: $articlesPerPage;
//        $showPaginator = $data[self::SHOW_PAGINATOR_PARAM];

        $this->querystring = [
            self::COMPACT_MODE_PARAM => $data[self::COMPACT_MODE_PARAM],
            self::TOPIC_SLUG_PARAM => $data[self::TOPIC_SLUG_PARAM],
            self::PAGE_NUMBER_PARAM => $data[self::PAGE_NUMBER_PARAM],
            self::LIMIT_PARAM => $data[self::LIMIT_PARAM],
            self::SHOW_PAGINATOR_PARAM => $data[self::SHOW_PAGINATOR_PARAM]
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
        return $this->querystring[self::PAGE_NUMBER_PARAM];
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
    public function getShowPaginator(): bool
    {
        return (bool)$this->querystring[self::SHOW_PAGINATOR_PARAM];
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
     * String representatin
     *
     * @return string
     */
    public function __toString()
    {
        return http_build_query($this->querystring);
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

    /**
     * $limit can be 'all' or unspecified; this equates to "don't impose a limit"
     * ("all" could come from a component config option)
     *
     * @param null|string $limit
     * @return int
     */
    private function determineLimit(?string $limit): int
    {
        /* limit not set at all; don't impose limits
         */
        if (('all' === $limit) || (null === $limit) || !(int)$limit) {
            return 0;
        }

        /* use whatever was provided
         */
        return (int)$limit;
    }
}
