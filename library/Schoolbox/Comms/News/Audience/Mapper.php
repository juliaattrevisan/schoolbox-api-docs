<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Comms\News;
use Alaress\Database\Predicate\Builder;
use Schoolbox\GenericCollection;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findByPredicates(Builder $pb, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    // Table definition
    public const TABLE_NAME                  = 'comms_news_audience';

    public const FIELD_ID                    = 'id';
    public const FIELD_COMMS_NEWS_ARTICLE_ID = 'comms_news_article_id';
    public const FIELD_PARENT_ID             = 'parent_id';
    public const FIELD_ENTITY_TYPE           = 'entity_type';
    public const FIELD_ENTITY_VALUE          = 'entity_value';
    public const FIELD_RELATIONSHIP          = 'relationship';


    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);

        $this->uses(self::FIELD_ID, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(self::FIELD_COMMS_NEWS_ARTICLE_ID, News\Mapper::class, 'id', null, null, 'commsNewsArticle', 'commsNewsArticle');
        $this->joins(self::FIELD_PARENT_ID,  self::class, 'id', null, null, 'parent', 'parent');
        $this->uses(self::FIELD_ENTITY_TYPE, self::TYPE_STRING);
        $this->uses(self::FIELD_ENTITY_VALUE, self::TYPE_STRING);
        $this->uses(self::FIELD_RELATIONSHIP, self::TYPE_STRING);
    }
}
