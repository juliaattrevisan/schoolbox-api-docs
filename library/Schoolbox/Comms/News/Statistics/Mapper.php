<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
    public const TYPE_OPEN = 'open';
    public const TYPE_IMPRESSION = 'impression';
    public const REFERRER_DASHBOARD = 'dashboard';
    public const REFERRER_HOMEPAGE = 'homepage';
    public const REFERRER_ARCHIVE = 'archive';
    public const REFERRER_EMAIL = 'email';

    /**
     * These consts are valid ones
     */
    private const VALID_REFERRER = [
        self::REFERRER_DASHBOARD,
        self::REFERRER_HOMEPAGE,
        self::REFERRER_ARCHIVE,
        self::REFERRER_EMAIL
    ];

    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_article_statistics');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('article_id', self::TYPE_INTEGER);
        $this->uses('user_id', self::TYPE_INTEGER);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('referrer', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Record an impression for $articleId
     * ie. $userId has witnessed $articleId
     *
     * $referrer indicates a place where they saw the impression and can't be null for this use case
     *
     * @param int    $articleId
     * @param int    $userId
     * @param string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveImpression(int $articleId, int $userId, string $referrer)
    {
        if (\in_array($referrer, self::VALID_REFERRER,true)) {
            return $this->save($this->createModel()
                ->setArticleId($articleId)
                ->setUserId($userId)
                ->setType(self::TYPE_IMPRESSION)
                ->setReferrer($referrer));
        }
    }


    /**
     * Record an impression for $articleId
     * ie. $userId has witnessed $articleId
     *
     * $referrer indicates a link they clicked to initiate the open
     *
     * It can be null if someone directly typed in the url, or opened it from some random place
     * that we don't know about
     *
     * @param int    $articleId
     * @param int    $userId
     * @param string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveOpen(int $articleId, int $userId, ?string $referrer)
    {
        if (\in_array($referrer, self::VALID_REFERRER,true)) {
            return $this->save($this->createModel()
                ->setArticleId($articleId)
                ->setUserId($userId)
                ->setType(self::TYPE_OPEN)
                ->setReferrer($referrer));
        }
    }
}
