<?php
/**
 * Querystring
 *
 * Rebuild a querystring or find out the particular pieces
 *  - compact mode
 *  - topic slug
 *  - page number
 *  - limit
 *  - hide paginator
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
    public const COMPACT_MODE = 'c';

    /**
     * Querystring param for the current Page Number chosen
     */
    public const PAGE_NUMBER = 'p';

    /**
     * Querystring param for the current Limit chosen
     */
    public const LIMIT = 'l';

    /**
     * Querystring param for the current topic chosen
     */
    public const TOPIC_SLUG = 'topic';

    /**
     * Querystring param for the Hide Pagination option
     */
    public const HIDE_PAGINATOR = 'hp';

    /**
     * Querystring param for the Folder option
     */
    public const FOLDER = 'f';

    /**
     * Querystring param for the status filter (on the /me page)
     */
    public const STATUS = 'status';

    /**
     * Querystring param for the Referrer (used in stats)
     */
    public const REFERRER = 'ref';

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
            self::COMPACT_MODE => $data[self::COMPACT_MODE],
            self::TOPIC_SLUG => $data[self::TOPIC_SLUG],
            self::PAGE_NUMBER => $data[self::PAGE_NUMBER],
            self::LIMIT => $data[self::LIMIT],
            self::HIDE_PAGINATOR => $data[self::HIDE_PAGINATOR],
            self::FOLDER => $data[self::FOLDER],
            self::REFERRER => $data[self::REFERRER],
            self::STATUS => $data[self::STATUS],
        ];
    }


    /**
     * Current compact mode setting
     *
     * @return mixed
     */
    public function getCompactMode()
    {
        return $this->querystring[self::COMPACT_MODE];
    }

    /**
     * Currently-chosen topic slug
     *
     * @return mixed
     */
    public function getSlug()
    {
        return $this->querystring[self::TOPIC_SLUG];
    }


    /**
     * Current page index
     *
     * @return mixed
     */
    public function getPageNum()
    {
        return $this->determinePageNum($this->querystring[self::PAGE_NUMBER]);
    }


    /**
     * Number of articles to obtain and display
     *
     * @return mixed
     */
    public function getLimit()
    {
        return $this->querystring[self::LIMIT];
    }


    /**
     * The option to determine whether paginator shows in the ui
     *
     * @return bool
     */
    public function getHidePaginator(): bool
    {
        return (bool)$this->querystring[self::HIDE_PAGINATOR];
    }


    /**
     * Get the folder id if one was specified
     *
     * @return mixed
     */
    public function getFolderId()
    {
        return $this->querystring[self::FOLDER];
    }

    /**
     * Get the status
     *
     * @return mixed
     */
    public function getStatus()
    {
        return $this->querystring[self::STATUS];
    }


    /**
     * Get the referrer string if one was specified
     *
     * @return mixed
     */
    public function getReferrer()
    {
        return $this->querystring[self::REFERRER];
    }


    /**
     * @param null|string $referrer
     * @return $this
     */
    public function setReferrer(?string $referrer)
    {
        $this->querystring[self::REFERRER] = $referrer;
        return $this;
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
        return http_build_query(array_filter($this->querystring, function ($value) {
            return \strlen($value);
        }));
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
