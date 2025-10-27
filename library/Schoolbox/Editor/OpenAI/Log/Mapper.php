<?php

/**
 * Mapper to store and retrieve log records for openai requests
 *
 * @author      James Leckie <james.leckie@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Editor\OpenAI\Log;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\GenericCollection;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritDoc
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('openai_log');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('session_id', self::TYPE_STRING);
        $this->uses('prompt', self::TYPE_STRING);
        $this->uses('input_tokens', self::TYPE_INTEGER);
        $this->uses('output_tokens', self::TYPE_INTEGER);
        $this->uses('model', self::TYPE_STRING);
        $this->uses('endpoint', self::TYPE_STRING);
        $this->uses('feature', self::TYPE_STRING);
        $this->uses('response', self::TYPE_STRING);
        $this->uses('return_code', self::TYPE_INTEGER);
        $this->uses('error_message', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }


    /**
     * @inheritDoc
     *
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        $userMapper = $this->getJoinMapper('user');

        return "
            SELECT
                {$this->getColumnString()},
                {$userMapper->getColumnString('u')}
        ";
    }


    /**
     * @inheritDoc
     *
     * @throws BaseMapper\DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('user', '', 'u', true)}
        ";
    }


    /**
     * @inheritDoc
     *
     * @param \PDOStatement $statement
     * @return Model[]|GenericCollection
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $userMapper = $this->getJoinMapper('user');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $log = $this->hydrate($row);

                /** @var \Schoolbox\User $user */
                $user = $userMapper->hydrate($row, 'u');

                if ($user) {
                    $log->setUser($user);
                }

                $collection->add($log);
            } catch (BaseMapper\Exception $e) {
                // noop, couldn't hydrate for some reason
            }
        }

        return $collection;
    }


    /**
     * Get grouped logs with offset and limit
     *
     * @param string|null $keyword
     * @param string|null $feature
     * @param User|null $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @param int $limit
     * @param int $offset
     * @return GenericCollection
     * @throws Exception
     */
    public function getGroupedLogs(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
        int $limit = 100,
        int $offset = 0
    ): GenericCollection
    {
        $pb = $this->getPredicateBuilder();

        /* Only search for meaningful keywords
         */
        if ('' !== trim((string)$keyword)) {
            $keywordPb = $this->getPredicateBuilder()
                ->like('prompt', "%{$this->getDatabase()->getQuoter()->escapeForLike($keyword)}%")
                ->like('response', "%{$this->getDatabase()->getQuoter()->escapeForLike($keyword)}%");

            $pb->any($keywordPb);
        }

        /* Only search for a meaningful feature
         */
        if ('' !== trim((string)$feature)) {
            $pb->equals('feature', $feature);
        }

        /* Only search for a meaningful user
         */
        if (null !== $user) {
            $pb->equals('user_id', $user->getId());
        }

        /* Only enforce a date range if specified
         */
        if (null !== $start) {
            $pb->greaterThanOrEqualTo('created_at', $start);
        }

        if (null !== $end) {
            $pb->lessThanOrEqualTo('created_at', $end);
        }

        $where = implode("\nAND ", $pb->getWhere());

        /* Don't need to get the openai_log.response column for what we're doing here
         * That column could contain A LOT of text esp. for image streaming
         */
        $query = "
            SELECT 
                {$this->getTable()}.id AS id, 
                session_id, 
                feature,
                prompt, 
                return_code, 
                {$this->getTable()}.created_at,
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->table}
            INNER JOIN {$this->getJoinSql('user', '', 'u', true)}
            WHERE {$where}
            GROUP BY session_id
            LIMIT :limit
            OFFSET :offset
        ";

        $statement = $this->getDatabase()->prepare($query);

        /* Cram in all the params...
         */
        foreach ($pb->getParams() as $name => $value) {
            $statement->bindValue($name, $value);
        }

        /* ...along with these special ints with special treatment
         */
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);

        $statement->execute();

        $stack = $this->createCollection();

        foreach ($statement as $row) {
            $log = $this->hydrate($row);

            /** @var \Schoolbox\User $user */
            $user = $this->getJoinMapper('user')->hydrate($row, 'u');

            if ($user) {
                $log->setUser($user);
            }

            $stack->add($log);
        }

        return $stack;
    }


    /**
     * Count grouped logs
     *
     * @param string|null $keyword
     * @param string|null $feature
     * @param User|null $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @return array
     */
    public function countGroupedLogs(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
    ): array {
        // base query
        $query = "Select count(*) from {$this->table} WHERE 1 = 1 ";
        $where = " group by session_id";
        $params = [];

        // add filters
        if ($feature !== null) {
            $where .= " and feature = :feature ";
            $params[':feature'] = $feature;
        }
        // dates
        if ($start !== null) {
            $where .= " and created_at >= :start ";
            $params[':start'] = $start->getDatabase();
        }
        if ($end !== null) {
            $where .= " and created_at <= :end ";
            $params[':end'] = $end->getDatabase();
        }
        if ($user !== null) {
            $where .= " and user_id = :user_id ";
            $params[':user_id'] = $user->getId();
        }
        // keyword search
        if ($keyword !== null) {
            $where .= " and (prompt like :keyword or response like :keyword) ";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $statement = $this->getDatabase()->prepareQuery($query . $where, $params);
        $statement->execute();
        return $statement->fetchAll();
    }
}
