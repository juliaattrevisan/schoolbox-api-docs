<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\File;

use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Resource\Storage;
use Schoolbox\File\Mimetype;

/**
 * Class Mapper
 *
 * Mapper for files associated with a news article
 *
 * @package Schoolbox\Comms\News\File
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_article_file');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->joins('article_id', News\Mapper::class, 'id', null, null, 'article', 'article');
        $this->joins('storage_id', Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('storage', 'f')},
                {$this->getJoinColumnString('storage.allowedFileExtension', 'm')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('storage', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'f', 'm', true)}
        ";
    }


    /**
     * Include storage object in selected result.
     *
     * @param \PDOStatement $statement
     * @return Collection
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(\PDOStatement $statement): Collection
    {
        /** @var Collection|Model[] $resultSet */
        $resultSet = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $result */
            $result = $this->hydrate($row);

            /** @var Storage\Model $storage */
            $storage = $this->hydrateJoin($row, 'storage', 'f');

            /** @var Mimetype $mime */
            $mime    = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'm');

            $resultSet[$result->getId()] = $result->setStorage($storage->setMimetype($mime));
        }

        return $resultSet;
    }


    /**
     * Find the count of attached files for each of $articleIds
     * Returns a hash lookup of (articleId, count) tuples
     *
     * @param array $articleIds
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \UnderflowException
     */
    public function findCounts(array $articleIds): array
    {
        $counts = [];

        /* Only count non-deleted attachments in the set of $articleIds
         */
        $pb = $this->getPredicateBuilder()
            ->isNull('deleted_at')
            ->in('article_id', $articleIds);

        $where = implode(' AND ', $pb->getWhere());

        $query = "
            SELECT article_id, COUNT(article_id) AS num
            FROM {$this->getTable()}
            WHERE {$where}
            GROUP BY article_id
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $counts[$row['article_id']] = $row['num'];
        }

        return $counts;
    }
}