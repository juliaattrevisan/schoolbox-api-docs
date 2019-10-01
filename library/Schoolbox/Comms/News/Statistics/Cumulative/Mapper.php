<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Cumulative;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Statistics;
use Schoolbox\Comms\News\Statistics\Mapper as StatisticsMapper;

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
        $this->uses('type', self::TYPE_STRING);
        $this->uses('referrer', self::TYPE_STRING);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('role_id', self::TYPE_INTEGER);
        $this->uses('num', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Increment a counter
     *
     * @param int         $articleId
     * @param string      $type
     * @param string      $roleType
     * @param int         $roleId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function increment(int $articleId, string $type, string $roleType, int $roleId, ?string $referrer)
    {
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
            'article_id' => $articleId,
            'type' => $type,
            'referrer' => $referrer,
            'role_id' => $roleId,
        ], ['cache' => false])->first();

        if ($model) {
            $model->setNum((int)$model->getNum() + 1);
        } else {
            $model = $this->createModel()
                ->setDate($now)
                ->setArticleId($articleId)
                ->setType($type)
                ->setReferrer($referrer)
                ->setRoleType($roleType)
                ->setRoleId($roleId)
                ->setNum(1);
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
     * @param string      $roleType
     * @param int         $roleId
     * @param string|null $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function incrementOpen(int $articleId, string $roleType, int $roleId, ?string $referrer)
    {
        if (!(new Statistics\ValidOpenReferrer())->isSatisfiedBy($referrer)) {
            return false;
        }

        return $this->increment($articleId, StatisticsMapper::TYPE_OPEN, $roleType, $roleId, $referrer);
    }
}
