<?php
/**
 * @author      Janwelle Chua <janwelle@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;


use Schoolbox\Comms\News\Audience\Segment\Strategy\Folder;

class Archive extends \Schoolbox\Mapper
{
    protected function mapping() :void
    {
        $this->hasTable('comms_news_article');
        $this->hasModel(Model::class);
    }

    /**
     * Performs a check whether the homepage has at least one archived news.
     *
     * @param int $folderId
     *
     * @return bool
     */
    public function findForHomepage(int $folderId) :bool
    {
        $now = \Alaress_Date_Format::now()->getDatabase();

        $pb = $this->getPredicateBuilder()
            ->lessThan('expires_at', $now)
            ->lessThan('publish_at', $now)
            ->notNull('accepted_at')
            ->isNull('deleted_at');

        $where = implode("\nAND ", $pb->getWhere());

        $query = "
            SELECT 1
            FROM {$this->getTable()}
            INNER JOIN comms_news_audience cnau ON {$this->table}.id = cnau.comms_news_article_id
            AND cnau.entity_type = '". Folder::getEntityType() ."' AND cnau.entity_value = " . $folderId . "
            WHERE {$where} LIMIT 1
        ";

        $statement = $this->database->prepare($query);

        $statement->execute($pb->getParams());

        return (boolean) $statement->fetch(\PDO::FETCH_COLUMN);
    }
}
