<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Cumulative;

use Schoolbox\Comms\News\Statistics;

/**
 * Class Mapper
 * @package Schoolbox\Comms\News\Statistics
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * Map a referrer string to its corresponding incrementer column
     * so I don't risk an sql injection
     *
     * @var array
     */
    private $impressionColumnMap = [
        Statistics\Mapper::REFERRER_DASHBOARD => 'num_impressions_dashboard',
        Statistics\Mapper::REFERRER_HOMEPAGE => 'num_impressions_homepage',
        Statistics\Mapper::REFERRER_INDEX => 'num_impressions_index',
        Statistics\Mapper::REFERRER_ARCHIVE => 'num_impressions_archive',
        Statistics\Mapper::REFERRER_EMAIL => 'num_impressions_email',
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
        $this->uses('date', self::TYPE_DATE);
        $this->uses('article_id', self::TYPE_INTEGER);
        $this->uses('first_open', self::TYPE_DATETIME);
        $this->uses('num_impressions_dashboard', self::TYPE_INTEGER);
        $this->uses('num_impressions_homepage', self::TYPE_INTEGER);
        $this->uses('num_impressions_index', self::TYPE_INTEGER);
        $this->uses('num_impressions_archive', self::TYPE_INTEGER);
        $this->uses('num_impressions_email', self::TYPE_INTEGER);
        $this->uses('num_conversions_dashboard', self::TYPE_INTEGER);
        $this->uses('num_conversions_homepage', self::TYPE_INTEGER);
        $this->uses('num_conversions_index', self::TYPE_INTEGER);
        $this->uses('num_conversions_archive', self::TYPE_INTEGER);
        $this->uses('num_conversions_email', self::TYPE_INTEGER);
        $this->uses('num_opens', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Record an impression for $articleId
     *
     * @param int         $articleId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function incrementImpression(int $articleId, ?string $referrer)
    {
        $existing = $this->findByColumnList([
            'date' => \Alaress_Date_Format::now()->getDateOnly(),
            'article_id' => $articleId
        ]);

        /* Detect which referrer to increment using a mapper method
         */
        if ($existing) {
            $method = $this->getPropertyFromColumn($this->impressionColumnMap[$referrer]);

            new \Alaress_Probe($method);die();

//
//            return $this->save($this->createModel()
//                ->
//                ->setArticleId($articleId)
//                ->setUserId($userId)
//                ->setType(self::TYPE_IMPRESSION)
//                ->setReferrer($referrer));
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
     * @param int         $articleId
     * @param int         $userId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function incrementOpen(int $articleId, int $userId, ?string $referrer)
    {
        if ((new ValidOpenReferrer())->isSatisfiedBy($referrer)) {
            return $this->save($this->createModel()
                ->setArticleId($articleId)
                ->setUserId($userId)
                ->setType(self::TYPE_OPEN)
                ->setReferrer($referrer));
        }
    }
}
