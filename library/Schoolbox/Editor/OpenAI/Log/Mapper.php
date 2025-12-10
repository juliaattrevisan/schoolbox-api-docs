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
        $this->uses('input_text', self::TYPE_STRING);
        $this->uses('output_text', self::TYPE_STRING);
        $this->uses('model', self::TYPE_STRING);
        $this->uses('endpoint', self::TYPE_STRING);
        $this->uses('feature', self::TYPE_STRING);
        $this->uses('log_format', self::TYPE_STRING, self::COLUMN_ENUM, null, [...array_column(LogFormat::cases(), 'value'), null]);
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
        ?int $limit = 100,
        ?int $offset = 0
    ): GenericCollection {
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

        /* WHERE clause is actually optional, if you never filled in any of the Filter fields
         * all you'd get is limit and offset.
         */
        $where = '';

        if (\count($pb->getWhere())) {
            $where = sprintf(
                'WHERE %s',
                implode("\nAND ", $pb->getWhere())
            );
        }

        /* Optional pieces
         */
        $limitSql = '';

        if (null !== $limit) {
            $limitSql = 'LIMIT :limit';
        }

        $offsetSql = '';

        if (null !== $offset) {
            $offsetSql = 'OFFSET :offset';
        }

        /* Don't need to get the openai_log.response column for what we're doing here
         * That column could contain A LOT of text esp. for image streaming
         */
        $query = "
            SELECT
                {$this->getTable()}.id AS id,
                session_id,
                feature,
                log_format,
                '' AS prompt, -- Prompt is required in the Model, but we specifically want it excluded to reduce the potentially large amount of unnecessary memory use
                return_code,
                {$this->getTable()}.created_at,
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->table}
            LEFT JOIN {$this->getJoinSql('user', '', 'u', true)}
            {$where}
            GROUP BY session_id
            ORDER BY created_at DESC
            {$limitSql}
            {$offsetSql}
        ";

        $statement = $this->getDatabase()->prepare($query);

        /* Cram in all the params...
         */
        foreach ($pb->getParams() as $name => $value) {
            $statement->bindValue($name, $value);
        }

        /* ...along with these optional special ints with special treatment
         */
        if (null !== $limit) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }

        if (null !== $offset) {
            $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        }

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
     * Find the total quantity of grouped logs that fulfil the filter criteria but without any limits/offsets
     *
     * @param string|null $keyword
     * @param string|null $feature
     * @param User|null $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @return int
     * @throws Exception
     */
    public function countGroupedLogs(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
    ): int {
        return $this->getGroupedLogs($keyword, $feature, $user, $start, $end, null, null)->count();
    }
}
