<?php

/**
 * Mapper to store and retrieve log records for openai requests
 *
 * @author      James Leckie <james.leckie@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Editor\OpenAI\Log;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Mapper as BaseMapper;
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
                $pd = $this->hydrate($row);

                if ($pd) {
                    $pd->setUser($userMapper->hydrate($row, 'u'));
                }

                $collection->add($pd);
            } catch (BaseMapper\Exception $e) {
                // noop, couldn't hydrate for some reason
            }
        }

        return $collection;
    }


    /**
     * Find by predicate and group by session id
     *
     * @param Builder $pb
     * @param int $limit
     * @param int $offset
     * @param array $options
     * @return (Model[]|GenericCollection)[] grouped by session id
     */
    public function findPredicateGrouped(Builder $pb, int $limit = 100, int $offset = 0, array $options = []): array
    {
        // TODO: this actually needs to GROUP BY session_id so that the limit/offset refers to sessions, not individual log entries
        // although at that point it feels odd for it to be a method directly on this mapper perhaps... hmm
        $results = $this->findByPredicates($pb, [
            'limit' => $limit,
            'offset' => $offset,
            'order' => 'created_at DESC',
        ]);

        // group by session id
        $grouped = [];
        foreach ($results as $result) {
            $sessionId = $result->getSessionId() ?? 'no-session-' . $result->getId();
            if (!isset($grouped[$sessionId])) {
                $grouped[$sessionId] = $this->createCollection();
            }
            $grouped[$sessionId]->add($result);
        }

        // sort by most recent created_at in each group
        uasort($grouped, function ($a, $b) {
            $aDate = $a[0]->getCreatedAt() ? $a[0]->getCreatedAt()->getUnixTimeStamp() : 0;
            $bDate = $b[0]->getCreatedAt() ? $b[0]->getCreatedAt()->getUnixTimeStamp() : 0;
            return $bDate <=> $aDate;
        });


        return $grouped;
    }


    /**
     * Get grouped logs with offset and limit
     */
    public function getGroupedLogs(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
        int $limit = 100,
        int $offset = 0
    ): array {
        // base query
//        $query = "Select session_id from {$this->table} where feature = :feature group by session_id order by max(created_at) desc limit :limit offset :offset";
        $query = "Select id, session_id, user_id, created_at, feature from {$this->table} WHERE 1 = 1 ";
        $where = "";
        $order = " group by session_id order by max(created_at) desc limit :limit offset :offset";
        $params = [
            ':limit' => $limit,
            ':offset' => $offset,
        ];

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

        $statement = $this->getDatabase()->prepareQuery($query . $where . $order, $params);
        $statement->execute();
        // hydrate user and create actual date
        $data = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $user = null;
            if ($row['user_id']) {
                $userMapper = $this->getJoinMapper('user');
                $user = $userMapper->findById($row['user_id']);
            }
            // if no sessionId then create a fake one
            if (!$row['session_id']) {
                $row['session_id'] = Model::MISSING_SESSIONID . $row['id'];
            }
            $data[$row['session_id']] = [
                'session_id' => $row['session_id'],
                'user' => $user,
                'created_at' => new \Alaress_Date_Format($row['created_at'], \Alaress_Date_Format::DATABASE_FORMAT),
                'feature' => $row['feature'],
            ];
        }
        return $data;
    }


    public function countGroupedLogs(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
    ): array {
        // base query
//        $query = "Select session_id from {$this->table} where feature = :feature group by session_id order by max(created_at) desc limit :limit offset :offset";
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
