<?php

/**
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Complex;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Segment;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\IHomepageCleanable;

/**
 * @method static self getInstance()
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends \Schoolbox\Mapper implements IHomepageCleanable
{
    public const TABLE_NAME                  = 'comms_news_audience_complex';

    public const FIELD_COMMS_NEWS_ARTICLE_ID = 'comms_news_article_id';
    public const FIELD_ENTITY_TYPE_1         = 'entity_type_1';
    public const FIELD_ENTITY_VALUE_1        = 'entity_value_1';
    public const FIELD_ENTITY_TYPE_2         = 'entity_type_2';
    public const FIELD_ENTITY_VALUE_2        = 'entity_value_2';


    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);

        $this->joins(
            self::FIELD_COMMS_NEWS_ARTICLE_ID,
            News\Mapper::class,
            'id',
            null,
            null,
            'commsNewsArticle',
            'commsNewsArticle'
        );
        $entityTypes = Segment\StrategyFactory::getSimpleEntityTypes();
        $this->uses(self::FIELD_ENTITY_TYPE_1, self::TYPE_STRING, self::COLUMN_ENUM, null, $entityTypes);
        $this->uses(self::FIELD_ENTITY_VALUE_1, self::TYPE_STRING);
        $this->uses(self::FIELD_ENTITY_TYPE_2, self::TYPE_STRING, self::COLUMN_ENUM, null, $entityTypes);
        $this->uses(self::FIELD_ENTITY_VALUE_2, self::TYPE_STRING);
    }


    /**
     * Deletes complex new audiences segments if either component is for the specified folder
     *
     * @param int $cid
     * @return void
     * @throws InvalidColumnException
     */
    public function homepageClean($cid): void
    {
        $entityType = Segment\Strategy\Folder::getEntityType();

        $pb = $this->getPredicateBuilder()->any(
            $this->getPredicateBuilder()
                 ->all($this->getPredicateBuilder()
                            ->equals(self::FIELD_ENTITY_TYPE_1, $entityType)
                            ->equals(self::FIELD_ENTITY_VALUE_1, $cid))
                 ->all($this->getPredicateBuilder()
                            ->equals(self::FIELD_ENTITY_TYPE_2, $entityType)
                            ->equals(self::FIELD_ENTITY_VALUE_2, $cid))
        );

        $this->deleteByPredicates($pb);
    }


    /**
     * Return a predicate that will match the combination of type/value pairs, regardless of ordering.
     * eg [R:1,C:2] ~= [C:2,R:1]
     *
     * @param string      $entityType1
     * @param string|null $entityValue1
     * @param string      $entityType2
     * @param string|null $entityValue2
     * @return Builder
     * @noinspection DuplicatedCode
     * @throws InvalidColumnException
     */
    public function getCombinationMatchPredicate(
        string $entityType1,
        ?string $entityValue1,
        string $entityType2,
        ?string $entityValue2
    ): Builder {
        // Match in first permutation
        $match1 = $this->getPredicateBuilder()
                       ->equals(self::FIELD_ENTITY_TYPE_1, $entityType1)
                       ->equals(self::FIELD_ENTITY_TYPE_2, $entityType2);
        if ($entityValue1 === null) {
            $match1->isNull(self::FIELD_ENTITY_VALUE_1);
        } else {
            $match1->equals(self::FIELD_ENTITY_VALUE_1, $entityValue1);
        }
        if ($entityValue2 === null) {
            $match1->isNull(self::FIELD_ENTITY_VALUE_2);
        } else {
            $match1->equals(self::FIELD_ENTITY_VALUE_2, $entityValue2);
        }

        // Match second permutation
        $match2 = $this->getPredicateBuilder()
                       ->equals(self::FIELD_ENTITY_TYPE_2, $entityType1)
                       ->equals(self::FIELD_ENTITY_TYPE_1, $entityType2);
        if ($entityValue1 === null) {
            $match2->isNull(self::FIELD_ENTITY_VALUE_2);
        } else {
            $match2->equals(self::FIELD_ENTITY_VALUE_2, $entityValue1);
        }
        if ($entityValue2 === null) {
            $match2->isNull(self::FIELD_ENTITY_VALUE_1);
        } else {
            $match2->equals(self::FIELD_ENTITY_VALUE_1, $entityValue2);
        }

        return $this->getPredicateBuilder()->any($this->getPredicateBuilder()->all($match1)->all($match2));
    }
}
