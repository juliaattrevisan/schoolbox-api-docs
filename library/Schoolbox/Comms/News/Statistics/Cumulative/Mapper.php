<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Cumulative;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Statistics;

/**
 * Class Mapper
 * @package Schoolbox\Comms\News\Statistics
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Collection|Model[] findByColumn($identifierValue)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * Map a referrer string to its corresponding incrementer column
     * so I don't risk an sql injection
     *
     * @var array
     */
    private const IMPRESSION_COLUMN_MAP = [
        Statistics\Mapper::REFERRER_DASHBOARD => 'num_impressions_dashboard',
        Statistics\Mapper::REFERRER_HOMEPAGE => 'num_impressions_homepage',
        Statistics\Mapper::REFERRER_INDEX => 'num_impressions_index',
        Statistics\Mapper::REFERRER_ARCHIVE => 'num_impressions_archive',
        Statistics\Mapper::REFERRER_EMAIL => 'num_impressions_email',
    ];

    /**
     * Map a referrer string to its corresponding incrementer column
     * so I don't risk an sql injection
     *
     * @var array
     */
    private const CONVERSION_COLUMN_MAP = [
        Statistics\Mapper::REFERRER_DASHBOARD => 'num_conversions_dashboard',
        Statistics\Mapper::REFERRER_HOMEPAGE => 'num_conversions_homepage',
        Statistics\Mapper::REFERRER_INDEX => 'num_conversions_index',
        Statistics\Mapper::REFERRER_ARCHIVE => 'num_conversions_archive',
        Statistics\Mapper::REFERRER_EMAIL => 'num_conversions_email',
    ];

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_article_statistics_cumulative');
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
     * Record an Impression for $articleId
     * Only increment known referrers
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
        if (!(new Statistics\ValidImpressionReferrer())->isSatisfiedBy($referrer)) {
            return false;
        }

        /* Detect which referrer to increment using a mapper method
         */
        $get = $this->getGetMethodName($this->getPropertyFromColumn(self::IMPRESSION_COLUMN_MAP[$referrer]));
        $set = $this->getSetMethodName($this->getPropertyFromColumn(self::IMPRESSION_COLUMN_MAP[$referrer]));
        $now = \Alaress_Date_Format::now();

        /* TODO: Random bug in findOneByColumnList()?
         *
         * When crafting test data:
         * This was originally findOneByColumnList() but it was always remembering something and never
         * found an instance that already existed - it always threw Instance Not Found. I tried using
         * ['cache'=>false] but it didn't help. Resorting back to this for now
         */
        $model = $this->findByColumnList([
            'date' => $now->getDatabaseDate(),
            'article_id' => $articleId
        ], ['cache' => false])->first();

        if ($model) {
            $model->$set((int)$model->$get() + 1);
        } else {
            $model = $this->createModel()
                ->setDate($now)
                ->setArticleId($articleId)
                ->$set(1);
        }

        return $this->save($model);
    }


    /**
     * Record an Conversion for $articleId
     * ie. $userId has opened $articleId via an Impression
     *
     * $referrer indicates a link they clicked to initiate the Conversion
     *
     * Conversions cannot have a null referrer (despite that being considered valid
     * for general usage, it's invalid for this particular usage)
     *
     * @param int         $articleId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function incrementConversion(int $articleId, string $referrer)
    {
        if (!(new Statistics\ValidOpenReferrer())->isSatisfiedBy($referrer)) {
            return false;
        }

        /* Detect which referrer to increment using a mapper method
         */
        $get = $this->getGetMethodName($this->getPropertyFromColumn(self::CONVERSION_COLUMN_MAP[$referrer]));
        $set = $this->getSetMethodName($this->getPropertyFromColumn(self::CONVERSION_COLUMN_MAP[$referrer]));
        $now = \Alaress_Date_Format::now();

        /* TODO: Random bug in findOneByColumnList()?
         *
         * When crafting test data:
         * This was originally findOneByColumnList() but it was always remembering something and never
         * found an instance that already existed - it always threw Instance Not Found. I tried using
         * ['cache'=>false] but it didn't help. Resorting back to this for now
         */
        $model = $this->findByColumnList([
            'date' => $now->getDatabaseDate(),
            'article_id' => $articleId
        ], ['cache' => false])->first();

        if ($model) {
            $model->$set((int)$model->$get() + 1);

            /* Record the datetime when this was first opened
             */
            if (null === $model->getFirstOpen()) {
                $model->setFirstOpen($now);
            }
        } else {
            $model = $this->createModel()
                ->setDate($now)
                ->setArticleId($articleId)
                ->setFirstOpen($now)
                ->$set(1);
        }

        return $this->save($model);
    }


    /**
     * Record an Conversion for $articleId
     * ie. $userId has opened $articleId via an Impression
     *
     * $referrer indicates a link they clicked to initiate the Conversion
     *
     * It can be null if someone directly typed in the url so in this case,
     * $referrer has no utility.
     *
     * @param int $articleId
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function incrementOpen(int $articleId)
    {
        $now = \Alaress_Date_Format::now();

        try {
            $model = $this->findOneByColumnList([
                'date' => \Alaress_Date_Format::now(),
                'article_id' => $articleId
            ]);

            $model->setNumOpens((int)$model->getNumOpens() + 1);

            /* Record the datetime when this was first opened
             */
            if (null === $model->getFirstOpen()) {
                $model->setFirstOpen($now);
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            $model = $this->createModel()
                ->setDate($now)
                ->setArticleId($articleId)
                ->setFirstOpen($now)
                ->setNumOpens(1);
        }

        return $this->save($model);
    }
}
