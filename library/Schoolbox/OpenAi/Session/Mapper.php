<?php

/**
 * Mapper for the `ai_session` table — one row per AI session.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

use Alaress\Database\Predicate\Builder;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection<Model> findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection<Model> createCollection($contents = [])
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
        $this->hasTable('ai_session');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('feature', self::TYPE_STRING, self::COLUMN_ENUM, null, array_column(Feature::cases(), 'value'));
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }

    /**
     * Find the session for a caller-supplied UUID, or create it if absent.
     *
     * Note the ai_session table is unusual in that its ID is a UUID.  For that reason, session IDs are typically
     * created before the ai_session rows exist, and so the existence of a session ID does not imply the existence of
     * an ai_session row.
     *
     * Wherever a session ID is to be used for database actions therefore, the question of whether the ai_session row
     * exists will arise.  This function allows both possibilities (the row does or does not exist) to be treated the
     * same way.
     *
     * @param string    $id      The session UUID (the ai_session primary key).
     * @param Feature   $feature The owning feature.
     * @param User|null $user    The creating user (for created_by), if known.
     * @return Model The persisted (existing or newly created) session.
     * @throws BaseMapper\Exception
     */
    public function findOrCreate(string $id, Feature $feature, ?User $user): Model
    {
        $statement = $this->getDatabase()->prepare(
            'INSERT IGNORE INTO `ai_session` (`id`, `feature`, `created_by`, `created_at`)
             VALUES (:id, :feature, :createdBy, NOW())'
        );
        $statement->bindValue('id', $id);
        $statement->bindValue('feature', $feature->value);
        $createdBy = $user?->getId();
        $statement->bindValue(
            'createdBy',
            $createdBy,
            null === $createdBy ? \PDO::PARAM_NULL : \PDO::PARAM_INT
        );
        $statement->execute();

        return $this->findById($id);
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
     * Note derived properties are hydrated by $this->getFilteredSessions() and by the SessionSummariser.
     *
     * @param \PDOStatement $statement
     * @return GenericCollection<Model>
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $userMapper = $this->getJoinMapper('user');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $session = $this->hydrate($row);

            /** @var \Schoolbox\User $user */
            $user = $userMapper->hydrate($row, 'u');

            if ($user) {
                $session->setUser($user);
            }

            $collection->add($session);
        }

        return $collection;
    }

    /**
     * Fetch a page of sessions with logs for the admin usage log, newest first.
     *
     * @param string|null               $keyword This filters sessions to those with at least one log whose prompt or
     *                                           response contains this text.
     * @param string|null               $feature This filters sessions to those created for this AI feature.
     * @param User|null                 $user    This filters sessions to those created by this user.
     * @param \Alaress_Date_Format|null $start   This filters sessions to those with at least one log created on or
     *                                           after this date.
     * @param \Alaress_Date_Format|null $end     The end date filters sessions to those with at least one log created
     *                                           on or before this date.
     * @param GenericCollection<ModerationEventStatus> $moderationStatuses This filters sessions to those with at least one
     *                                                                     log whose moderation status is in this collection.
     * @param int|null                  $limit   The limit controls the maximum number of sessions returned.
     * @param int|null                  $offset  The offset controls how many matching sessions are skipped before
     *                                           results are returned.
     * @return GenericCollection<Model>
     * @throws Exception
     */
    public function getFilteredSessions(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
        GenericCollection $moderationStatuses,
        ?int $limit = 100,
        ?int $offset = 0
    ): GenericCollection {
        [$where, $params] = $this->buildSessionFilter($keyword, $feature, $user, $start, $end, $moderationStatuses);

        $limitSql  = null !== $limit ? 'LIMIT :limit' : '';
        $offsetSql = null !== $limit && null !== $offset ? 'OFFSET :offset' : '';

        $query = "
            SELECT
                `{$this->table}`.id AS id,
                `{$this->table}`.feature AS feature,
                `{$this->table}`.created_at AS created_at,
                {$this->getJoinColumnString('user', 'u')}
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('user', '', 'u', true)}
            {$where}
            ORDER BY `{$this->table}`.created_at DESC
            {$limitSql}
            {$offsetSql}
        ";

        $statement = $this->getDatabase()->prepare($query);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        if (null !== $limit) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }
        if (null !== $limit && null !== $offset) {
            $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        }
        $statement->execute();

        $sessions = $this->hydrateSelect($statement);
        new SessionSummariser($this->getDatabase())->hydrateSessionsWithSummaryInfo($sessions);

        return $sessions;
    }

    /**
     * Count all sessions with logs matching the same filters, ignoring limit/offset.
     * See docblock for $this->getFilteredSessions() for descriptions of filter params.
     *
     * @param string|null               $keyword
     * @param string|null               $feature
     * @param User|null                 $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @param GenericCollection<ModerationEventStatus> $moderationStatuses
     * @return int
     * @throws Exception
     */
    public function countFilteredSessions(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
        GenericCollection $moderationStatuses,
    ): int {
        [$where, $params] = $this->buildSessionFilter($keyword, $feature, $user, $start, $end, $moderationStatuses);

        $statement = $this->getDatabase()->prepare(
            "SELECT COUNT(*) FROM `{$this->table}` {$where}"
        );
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int)$statement->fetchColumn();
    }

    /**
     * Build the shared WHERE clause and bound params for the session list/count.
     * See docblock for $this->getFilteredSessions() for descriptions of filter params.
     *
     * @param string|null               $keyword
     * @param string|null               $feature
     * @param User|null                 $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @param GenericCollection<ModerationEventStatus> $moderationStatuses
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSessionFilter(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
        GenericCollection $moderationStatuses,
    ): array {
        $pb = $this->getPredicateBuilder();
        $keywordPredicate = null;

        if ('' !== trim((string)$keyword)) {
            $like = '%' . $this->getDatabase()->getQuoter()->escapeForLike($keyword) . '%';
            $keywordPredicate = $this->getPredicateBuilder()
                ->like('l.prompt', $like)
                ->like('l.response', $like);
        }

        $logClauses = [];
        $logParams = [];

        if (null !== $keywordPredicate) {
            $logClauses[] = '(' . implode(' OR ', $keywordPredicate->getWhere()) . ')';
            $logParams = array_merge($logParams, $keywordPredicate->getParams());
        }
        if ('' !== trim((string)$feature)) {
            $pb->equals('feature', $feature);
        }
        if (null !== $user) {
            $pb->equals('created_by', $user->getId());
        }
        if (null !== $start || null !== $end) {
            $datePredicate = $this->getPredicateBuilder();
            if (null !== $start) {
                $datePredicate->greaterThanOrEqualTo('l.created_at', $start->getDatabase());
            }
            if (null !== $end) {
                $datePredicate->lessThanOrEqualTo('l.created_at', $end->getDatabase());
            }
            $logClauses = array_merge($logClauses, $datePredicate->getWhere());
            $logParams = array_merge($logParams, $datePredicate->getParams());
        }

        if ($moderationStatuses->count() > 0) {
            $statusValues = $moderationStatuses
                ->map(static fn(ModerationEventStatus $status) => $status->value)
                ->jsonSerialize();

            $pb->rawWhere(
                'EXISTS (
                    SELECT 1 FROM `ai_moderation_event` me
                    WHERE me.target_ai_session_id = `' . $this->table . '`.id
                    AND me.status IN ' . Builder::getArrayPlaceholders($statusValues, 'moderation_status') . '
                )',
                Builder::getArrayParameters($statusValues, 'moderation_status')
            );
        }

        if ($logClauses) {
            $pb->rawWhere(
                'EXISTS (
                    SELECT 1 FROM `ai_session_log` l
                    WHERE l.ai_session_id = `' . $this->table . '`.id
                    AND ' . implode("\nAND ", $logClauses) . '
                )',
                $logParams
            );
        } else {
            $pb->rawWhere(
                'EXISTS (
                    SELECT 1 FROM `ai_session_log` l
                    WHERE l.ai_session_id = `' . $this->table . '`.id
                )',
                []
            );
        }

        $where = $pb->getWhere() ? 'WHERE ' . implode("\nAND ", $pb->getWhere()) : '';

        return [$where, $pb->getParams()];
    }

    /**
     * @return string[]
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['created_at' => 'ASC'];
    }
}
