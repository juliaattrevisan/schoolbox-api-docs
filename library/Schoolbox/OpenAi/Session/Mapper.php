<?php

/**
 * Mapper for the `ai_session` table — one row per AI session.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\OpenAi\Feature;
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
     * The id is minted in memory by the frontend/controller before any row exists,
     * so possession of an id never implies the row exists. `INSERT IGNORE` makes
     * "create vs reuse" atomic; the first writer's feature/created_by/created_at win.
     *
     * @param string    $id      The session UUID (the ai_session primary key).
     * @param string    $feature The owning feature, as a {@see Feature} value.
     * @param User|null $user    The creating user (for created_by), if known.
     * @return Model The persisted (existing or newly created) session.
     * @throws \ValueError If $feature is not a valid Feature value.
     * @throws BaseMapper\Exception
     */
    public function findOrCreate(string $id, string $feature, ?User $user): Model
    {
        $feature = Feature::from($feature)->value;

        $statement = $this->getDatabase()->prepare(
            'INSERT IGNORE INTO `ai_session` (`id`, `feature`, `created_by`, `created_at`)
             VALUES (:id, :feature, :createdBy, NOW())'
        );
        $statement->bindValue('id', $id);
        $statement->bindValue('feature', $feature);
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
     * Filters: keyword (matched against the logs' prompt/response via EXISTS),
     * feature, creating user, and created_at date range. logFormat/returnCode are
     * derived from each session's first log (earliest created_at, then id).
     * This is a summary hint for the admin list, not a guarantee that every log in the session has this format.
     *
     * @param string|null               $keyword
     * @param string|null               $feature
     * @param User|null                 $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @param int|null                  $limit
     * @param int|null                  $offset
     * @return GenericCollection<Model>
     * @throws Exception
     */
    public function getFilteredSessions(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
        ?int $limit = 100,
        ?int $offset = 0
    ): GenericCollection {
        [$where, $params] = $this->buildSessionFilter($keyword, $feature, $user, $start, $end);

        $limitSql  = null !== $limit ? 'LIMIT :limit' : '';
        $offsetSql = null !== $limit && null !== $offset ? 'OFFSET :offset' : '';

        // The admin list emits a details route for each session, and that route
        // preloads the first ai_session_log row. Requiring firstLog here keeps
        // orphan sessions from appearing with a broken details link.
        $query = "
            SELECT
                `{$this->table}`.id AS id,
                `{$this->table}`.feature AS feature,
                `{$this->table}`.created_at AS created_at,
                firstLog.log_format AS derived_log_format,
                firstLog.return_code AS derived_return_code,
                {$this->getJoinColumnString('user', 'u')}
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('user', '', 'u', true)}
            JOIN `ai_session_log` firstLog ON firstLog.id = (
                SELECT id FROM `ai_session_log`
                WHERE ai_session_id = `{$this->table}`.id
                ORDER BY created_at ASC, id ASC
                LIMIT 1
            )
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

        $collection = $this->createCollection();
        $userMapper = $this->getJoinMapper('user');

        foreach ($statement as $row) {
            $session = $this->hydrate($row)
                ->setDerivedLogFormat($row['derived_log_format'] ?? null)
                ->setDerivedReturnCode(
                    isset($row['derived_return_code']) ? (int)$row['derived_return_code'] : null
                );

            /** @var \Schoolbox\User|null $sessionUser */
            $sessionUser = $userMapper->hydrate($row, 'u');
            if ($sessionUser) {
                $session->setUser($sessionUser);
            }

            $collection->add($session);
        }

        return $collection;
    }

    /**
     * Count all sessions with logs matching the same filters, ignoring limit/offset.
     *
     * @param string|null               $keyword
     * @param string|null               $feature
     * @param User|null                 $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @return int
     * @throws Exception
     */
    public function countFilteredSessions(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
    ): int {
        [$where, $params] = $this->buildSessionFilter($keyword, $feature, $user, $start, $end);
        $where = $this->requireSessionLog($where);

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
     * Require at least one child log for session counts.
     *
     * Session rows without logs cannot open the usage-log details route, because
     * the route preload resolves a first ai_session_log row from the session id.
     */
    private function requireSessionLog(string $where): string
    {
        $hasLog = 'EXISTS (
            SELECT 1 FROM `ai_session_log` l
            WHERE l.ai_session_id = `' . $this->table . '`.id
        )';

        if ('' === $where) {
            return 'WHERE ' . $hasLog;
        }

        return $where . "\nAND " . $hasLog;
    }

    /**
     * Build the shared WHERE clause and bound params for the session list/count.
     *
     * @param string|null               $keyword
     * @param string|null               $feature
     * @param User|null                 $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSessionFilter(
        ?string $keyword,
        ?string $feature,
        ?User $user,
        ?\Alaress_Date_Format $start,
        ?\Alaress_Date_Format $end,
    ): array {
        $clauses = [];
        $params  = [];

        if ('' !== trim((string)$keyword)) {
            $like = '%' . $this->getDatabase()->getQuoter()->escapeForLike($keyword) . '%';
            $clauses[] = 'EXISTS (
                SELECT 1 FROM `ai_session_log` l
                WHERE l.ai_session_id = `' . $this->table . '`.id
                  AND (l.prompt LIKE :keyword OR l.response LIKE :keyword)
            )';
            $params['keyword'] = $like;
        }
        if ('' !== trim((string)$feature)) {
            $clauses[] = '`' . $this->table . '`.feature = :feature';
            $params['feature'] = $feature;
        }
        if (null !== $user) {
            $clauses[] = '`' . $this->table . '`.created_by = :userId';
            $params['userId'] = $user->getId();
        }
        if (null !== $start) {
            $clauses[] = '`' . $this->table . '`.created_at >= :startAt';
            $params['startAt'] = $start->getDatabase();
        }
        if (null !== $end) {
            $clauses[] = '`' . $this->table . '`.created_at <= :endAt';
            $params['endAt'] = $end->getDatabase();
        }

        $where = $clauses ? 'WHERE ' . implode("\nAND ", $clauses) : '';

        return [$where, $params];
    }
}
