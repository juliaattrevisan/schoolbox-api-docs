<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use function count;

use PDO;
use PDOStatement;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Mapper for comments in discussion threads.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Used as column alias when fetching a comment count by thread id
     */
    public const THREAD_ID = 'threadId';

    /**
     * Used as column alias when fetching a comment count by article id
     */
    public const ARTICLE_ID = 'parentId';

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('discussion_thread_comment');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('discussion_thread_id', Thread\Mapper::class, 'id', null, null, 'thread', 'thread');
        $this->joins('author_id', UserMapper::class, 'id', null, null, null, 'author');
        $this->uses('content', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Overridden to join on thread and author.
     *
     * @return string
     * @throws BaseMapper\DefinitionException if somehow a join doesn't exist?
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('thread', 't')},
                {$this->getJoinColumnString('author', 'a')}
        ";
    }


    /**
     * Overridden to join on thread and author.
     *
     * @return string
     * @throws BaseMapper\DefinitionException if somehow a join doesn't exist?
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('thread', '', 't', true)}
            INNER JOIN {$this->getJoinSql('author', '', 'a', true)}
        ";
    }


    /**
     * Overridden to join on thread and author.
     *
     * @param PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     * @throws BaseMapper\Exception if a hydration fails
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var Thread\Model|null $thread */
            $thread = $this->hydrateJoin($row, 'thread', 't');
            if ($thread) {
                $model->setThread($thread);
            }

            /** @var User|null $author */
            $author = $this->hydrateJoin($row, 'author', 'a');
            if ($author) {
                $model->setAuthor($author);
            }

            $collection[$model->getId()] = $model;
        }
        return $collection;
    }

    /**
     * @param array  $threadIds
     * @param string $indexBy
     *
     * Available options for indexBy:
     * - threadId = Refers to threadId. If this is the option that was selected, the index of the array will be
     *              based on the threadId.
     * - parentId = Currently it is referring to news id, and if this option is selected, the index of the array
     *              will be based on the newsId.
     *
     * This needs to be done for now as news in the archive and other listing pages have a different structure.
     * For more info, please see: News/Controller::getListForUser and News/Controller::generatePartial
     *
     * @return array
     */
    public function getCommentCountByThreadIds(array $threadIds, string $indexBy): array
    {
        if (count($threadIds) === 0) {
            return [];
        }

        if (!in_array($indexBy, [self::ARTICLE_ID , self::THREAD_ID], true)) {
            throw new \InvalidArgumentException('Invalid index: ' . $indexBy);
        }

        $newsTable = \Schoolbox\Comms\News\Mapper::getInstance()->getTable();

        // Prepare column and join string
        $join = '';
        $indexByColumn = $this->table . '.discussion_thread_id AS ' . self::THREAD_ID;

        if ($indexBy === self::ARTICLE_ID) {
            $indexByColumn = $newsTable . '.id AS ' . self::ARTICLE_ID;
            $join = 'INNER JOIN ' . $newsTable . ' ON ' . $newsTable . '.discussion_thread_id = ' . $this->table . '.discussion_thread_id';
        }

        $columns = [
            $indexByColumn,
            'COUNT(' . $this->table . '.id) AS totalCount'
        ];

        $selectColumns = implode(',', $columns);

        $pb = $this->getPredicateBuilder()
            ->in($this->table . '.discussion_thread_id', $threadIds)
            ->isNull($this->table . '.deleted_at');

        $where = implode("\nAND ", $pb->getWhere());

        $query = "
            SELECT
            {$selectColumns}
            FROM {$this->getTable()} 
            {$join}
            WHERE {$where} 
            GROUP BY {$this->table}.discussion_thread_id
        ";

        $statement = $this->database->prepare($query);

        $statement->execute($pb->getParams());

        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
