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
}
