<?php
/**
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Complex;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Segment;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\IHomepageCleanable;

/**
 * @method static self getInstance()
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper implements IHomepageCleanable
{
    public const TABLE_NAME                  = 'comms_news_audience_complex';

    public const FIELD_COMMS_NEWS_ARTICLE_ID = 'comms_news_article_id';
    public const FIELD_ENTITY_TYPE_1         = 'entity_type_1';
    public const FIELD_ENTITY_VALUE_1        = 'entity_value_1';
    public const FIELD_ENTITY_TYPE_2         = 'entity_type_2';
    public const FIELD_ENTITY_VALUE_2        = 'entity_value_2';
    public const FIELD_RELATIONSHIP          = 'relationship';


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
        $this->uses(self::FIELD_RELATIONSHIP, self::TYPE_STRING, self::COLUMN_ENUM, null, Model::getRelationships());
    }


    /**
     * Deletes complex new audiences segments if either component is for the specified folder
     *
     * @param int $cid
     * @return void
     * @throws InvalidColumnException
     */
    public function homepageClean($cid) :void
    {
        $entityType = Segment\Strategy\Folder::getEntityType();

        $pb = $this->getPredicateBuilder()
                   ->all($this->getPredicateBuilder()
                              ->equals(self::FIELD_ENTITY_TYPE_1, $entityType)
                              ->equals(self::FIELD_ENTITY_VALUE_1, $cid))
                   ->all($this->getPredicateBuilder()
                              ->equals(self::FIELD_ENTITY_TYPE_2, $entityType)
                              ->equals(self::FIELD_ENTITY_VALUE_2, $cid));

        $this->deleteByPredicates($this->getPredicateBuilder()->all($pb));
    }
}
