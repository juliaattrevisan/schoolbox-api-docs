<?php

/**
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Simple;

use InvalidArgumentException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Segment;
use Schoolbox\IHomepageCleanable;

/**
 * @method static self getInstance()
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper implements IHomepageCleanable
{
    public const TABLE_NAME                  = 'comms_news_audience';

    public const FIELD_COMMS_NEWS_ARTICLE_ID = 'comms_news_article_id';
    public const FIELD_ENTITY_TYPE           = 'entity_type';
    public const FIELD_ENTITY_VALUE          = 'entity_value';
    public const FIELD_RELATIONSHIP          = 'relationship';


    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);

        $this->joins(self::FIELD_COMMS_NEWS_ARTICLE_ID, News\Mapper::class, 'id', null, null, 'commsNewsArticle', 'commsNewsArticle');

        $this->uses(self::FIELD_ENTITY_TYPE, self::TYPE_STRING, self::COLUMN_ENUM, null, Segment\StrategyFactory::getSimpleEntityTypes());
        $this->uses(self::FIELD_ENTITY_VALUE, self::TYPE_STRING);
        $this->uses(self::FIELD_RELATIONSHIP, self::TYPE_STRING, self::COLUMN_ENUM, null, Model::getRelationships());
    }


    /**
     * Deletes related news article audience by folder id
     *
     * @param int $cid
     */
    public function homepageClean($cid): void
    {
        $this->deleteByColumnList([
            self::FIELD_ENTITY_VALUE => $cid,
            self::FIELD_ENTITY_TYPE  => Segment\Strategy\Folder::getEntityType(),
        ]);
    }
}
