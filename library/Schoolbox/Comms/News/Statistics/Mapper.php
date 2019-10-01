<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

/**
 * Class Mapper
 * @package Schoolbox\Comms\News\Statistics
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * Consts to match schema enums
     */
    public const TYPE_CONVERSION = 'conversion';
    public const TYPE_OPEN = 'open';
    public const REFERRER_DASHBOARD = 'dashboard';
    public const REFERRER_HOMEPAGE = 'homepage';
    public const REFERRER_ARCHIVE = 'archive';
    public const REFERRER_EMAIL = 'email';
    public const REFERRER_INDEX = 'index';
    public const REFERRER_SAVED = 'saved';
    public const REFERRER_SEARCH = 'search';
    public const REFERRER_UNKNOWN = 'unknown';


    /**
     * This const represents a string value for an empty referrer so it can be meaningful to humans
     */
    public const REFERRER_EMPTY = 'unknown';


    /**
     * These are the referrers that we understand in the domain logic
     */
    public const REFERRERS = [
        self::REFERRER_DASHBOARD,
        self::REFERRER_HOMEPAGE,
        self::REFERRER_INDEX,
        self::REFERRER_ARCHIVE,
        self::REFERRER_EMAIL,
        self::REFERRER_SAVED,
        self::REFERRER_SEARCH,
        self::REFERRER_UNKNOWN
    ];


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    protected function mapping(): void
    {
        $this->hasTable('comms_news_article_statistics');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('article_id', self::TYPE_INTEGER);
        $this->uses('user_id', self::TYPE_INTEGER);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('referrer', self::TYPE_STRING);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('role_id', self::TYPE_INTEGER);
        $this->uses('occurs_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
