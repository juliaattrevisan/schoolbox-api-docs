# SA-22508 `openai_log` → `ai_session` Conversion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert all runtime and admin code from the old `openai_log` table to the new `ai_session` / `ai_session_log` tables, repoint `dialog_agent_response` at the new session id, and deprecate the old table and column.

**Architecture:** The session row is created eagerly at the point a log is built (`Client::createLog` and `Editor\OpenAI\Helper::request`) via a new `Session\Mapper::findOrCreate()`, so the FK parent always exists before any log row. The admin read path queries the real `ai_session` table directly, deriving the per-session `logFormat`/`returnCode` from each session's first log. Existing translators are repointed in place to the new models. The old `Editor\OpenAI\Log\Mapper`/`Model` are deleted; `LogFormat` and the format strategies are retained.

**Tech Stack:** PHP 8.3, the in-house `Schoolbox\Mapper` ORM, PHPUnit with `Alaress\Database\Test\TestConnectorTrait` (transaction-per-test DB integration), Vue/TypeScript frontend (read-only contract), SQL update scripts under `updates/26.1.alpha1/ai/`.

**Reference spec:** `docs/superpowers/specs/2026-06-09-sa-22508-openai-log-conversion-design.md`

**Conventions for this codebase:**
- Every class/method/test gets a docblock (including getters/setters).
- DB-integration tests run in CI; run a single test file with `vendor/bin/phpunit <path>` (do not use `--filter`). Do not modify shared test infrastructure.
- Commit after each task. Never push (the human pushes).

---

## File Structure

**Modify:**
- `library/Schoolbox/OpenAi/Session/Mapper.php` — add `findOrCreate()`, `getFilteredSessions()`, `countFilteredSessions()`.
- `library/Schoolbox/OpenAi/Session/Model.php` — add transient `derivedLogFormat` / `derivedReturnCode`.
- `library/Schoolbox/OpenAi/Client.php` — inject `Session\Mapper`; eager find-or-create in `createLog`; `Log\*` alias → new namespace.
- `library/Schoolbox/OpenAi/ClientFactory.php` — inject + pass `Session\Mapper`; `Log\Mapper` → `Session\Log\Mapper`.
- `library/Schoolbox/OpenAi/IClient.php` — `Log\*` alias → new namespace.
- `library/Schoolbox/Editor/OpenAI/Helper.php` — inject `Session\Mapper`; eager find-or-create; new log mapper/model.
- `library/Alaress/Application/Kernel.php` — DI wiring swaps.
- `library/Schoolbox/Learning/DialogAgent/Module.php`, `Controller.php` — `AILogMapper` → `Session\Log\Mapper`.
- `library/Schoolbox/Learning/Assessment/Module.php` — `AILogMapper` → `Session\Log\Mapper`.
- `library/Schoolbox/Admin/OpenAI/Usage/Controller.php` — inject `Session\Mapper` + `Session\Log\Mapper`; rewrite `postSearch` and `getLog`.
- `library/Schoolbox/Editor/OpenAI/Log/Translator/Session/ModelToArray.php`, `Session/Links.php` — repoint to `Session\Model`.
- `library/Schoolbox/Editor/OpenAI/Log/Translator/ModelToArray.php` — repoint to `Session\Log\Model`, drop `feature`.
- `library/Schoolbox/Editor/OpenAI/Log/Translator/Format/IStrategy.php`, `Format/AgentChat.php`, `Format/Guardrail.php`, `Format/StrategyFactory.php` — repoint `Model` type to `Session\Log\Model`.
- `library/Schoolbox/Learning/Assessment/DataModel/DialogAgent/Response/Mapper.php` — `session_id` → `ai_session_id` column.
- `library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts` — drop `feature` from `AiSessionLog`.

**Create:**
- `library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php`
- `library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php`
- `updates/26.1.alpha1/ai/11-deprecate-openai-log-table.sql`
- `updates/26.1.alpha1/ai/12-deprecate-dialog-agent-response-session-id.sql`

**Delete (after consumers converted):**
- `library/Schoolbox/Editor/OpenAI/Log/Mapper.php`
- `library/Schoolbox/Editor/OpenAI/Log/Model.php`

---

## Task 1: `Session\Mapper::findOrCreate()`

The write-path primitive: ensure an `ai_session` row exists for a caller-supplied UUID, carrying its `feature` and `created_by`. First writer wins (reused session ids must not be overwritten).

**Files:**
- Modify: `library/Schoolbox/OpenAi/Session/Mapper.php`
- Test: `library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php`

- [ ] **Step 1: Write the failing test**

Create `library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session;

use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Mapper;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\UserMapper;

/**
 * Database tests for {@see Mapper::findOrCreate()}.
 */
class FindOrCreateTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * Open a transaction and point the mappers at it, so writes roll back afterwards.
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        Mapper::getInstance()->setDatabase($this->schoolbox);
        UserMapper::getInstance()->setDatabase($this->schoolbox);
    }

    /**
     * Roll back everything written during the test.
     */
    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    /**
     * First call with an unseen id inserts the session row with the given feature.
     */
    public function testCreatesWhenAbsent(): void
    {
        $id = Uuid::uuid4()->toString();

        $session = Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, null);

        $this->assertInstanceOf(Model::class, $session);
        $this->assertSame($id, $session->getId());

        $reloaded = Mapper::getInstance()->findById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame('chat', $reloaded->getFeature());
    }

    /**
     * A second call with the same id does not insert a duplicate and preserves the
     * original feature (first writer wins).
     */
    public function testReusesWhenPresentAndKeepsOriginalFeature(): void
    {
        $id = Uuid::uuid4()->toString();

        Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, null);
        Mapper::getInstance()->findOrCreate($id, Feature::DIALOG_AGENT->value, null);

        $matches = Mapper::getInstance()->findByColumn('id', $id);
        $this->assertCount(1, $matches, 'No duplicate session row should be created');
        $this->assertSame('chat', $matches->first()->getFeature(), 'Original feature is preserved');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php`
Expected: FAIL — `Error: Call to undefined method ...Mapper::findOrCreate()`.

- [ ] **Step 3: Implement `findOrCreate()`**

In `library/Schoolbox/OpenAi/Session/Mapper.php`, add `use Schoolbox\User;` to the imports, and add this method after `mapping()`:

```php
    /**
     * Find the session for a caller-supplied UUID, or create it if absent.
     *
     * The id is minted in memory by the frontend/controller before any row exists,
     * so possession of an id never implies the row exists. `INSERT IGNORE` makes
     * "create vs reuse" atomic; the first writer's feature/created_by/created_at win.
     *
     * @param string      $id      The session UUID (the ai_session primary key).
     * @param string|null $feature The owning feature, as a {@see Feature} value.
     * @param User|null   $user    The creating user (for created_by), if known.
     * @return Model The persisted (existing or newly created) session.
     * @throws BaseMapper\Exception
     */
    public function findOrCreate(string $id, ?string $feature, ?User $user): Model
    {
        $statement = $this->getDatabase()->prepare(
            'INSERT IGNORE INTO `ai_session` (`id`, `feature`, `created_by`, `created_at`)
             VALUES (:id, :feature, :createdBy, NOW())'
        );
        $statement->bindValue('id', $id);
        $statement->bindValue('feature', $feature);
        $statement->bindValue('createdBy', $user?->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return $this->findById($id);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php`
Expected: PASS (2 tests). (DB tests are also validated in CI.)

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Mapper.php library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php
git commit -m "Add Session\\Mapper::findOrCreate for ai_session [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Read-path query — `getFilteredSessions` / `countFilteredSessions`

Replace the old `getGroupedLogs`/`countGroupedLogs` (which grouped `openai_log`) with direct queries over the real `ai_session` table, deriving `logFormat`/`returnCode` from each session's first log (decision D1).

**Files:**
- Modify: `library/Schoolbox/OpenAi/Session/Model.php` (transient derived fields)
- Modify: `library/Schoolbox/OpenAi/Session/Mapper.php` (query methods)
- Test: `library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php`

- [ ] **Step 1: Add transient derived fields to `Session\Model`**

In `library/Schoolbox/OpenAi/Session/Model.php`, add these properties after `$feature` and accessors after `setFeature()`. They are populated only by the list query's hydration and never persisted.

```php
    /**
     * Log format derived from the session's first log, for the admin list only.
     * Not a column; populated by {@see Mapper::getFilteredSessions()}.
     *
     * @var string|null
     */
    private ?string $derivedLogFormat = null;

    /**
     * Return code derived from the session's first log, for the admin list only.
     * Not a column; populated by {@see Mapper::getFilteredSessions()}.
     *
     * @var int|null
     */
    private ?int $derivedReturnCode = null;
```

```php
    /**
     * @return string|null
     */
    public function getDerivedLogFormat(): ?string
    {
        return $this->derivedLogFormat;
    }

    /**
     * @param string|null $derivedLogFormat
     * @return $this
     */
    public function setDerivedLogFormat(?string $derivedLogFormat): self
    {
        $this->derivedLogFormat = $derivedLogFormat;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getDerivedReturnCode(): ?int
    {
        return $this->derivedReturnCode;
    }

    /**
     * @param int|null $derivedReturnCode
     * @return $this
     */
    public function setDerivedReturnCode(?int $derivedReturnCode): self
    {
        $this->derivedReturnCode = $derivedReturnCode;
        return $this;
    }
```

- [ ] **Step 2: Write the failing test**

Create `library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session;

use Alaress\Database\Test\TestConnectorTrait;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Log\Mapper as LogMapper;
use Schoolbox\OpenAi\Session\Log\Model as LogModel;
use Schoolbox\OpenAi\Session\Mapper;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\UserMapper;

/**
 * Database tests for the admin session-list query {@see Mapper::getFilteredSessions()}.
 */
class FilteredSessionsTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * Open a transaction and point the mappers at it, so writes roll back afterwards.
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        Mapper::getInstance()->setDatabase($this->schoolbox);
        LogMapper::getInstance()->setDatabase($this->schoolbox);
        UserMapper::getInstance()->setDatabase($this->schoolbox);
    }

    /**
     * Roll back everything written during the test.
     */
    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    /**
     * A session is returned with logFormat/returnCode derived from its first log,
     * and the feature filter matches it.
     */
    public function testReturnsSessionWithDerivedFirstLogValues(): void
    {
        $id = Uuid::uuid4()->toString();
        Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, null);

        // First log (earliest) is the one whose log_format/return_code should surface.
        LogMapper::getInstance()->save(
            (new LogModel())->setAiSessionId($id)->setPrompt('first')
                ->setLogFormat(LogFormat::AGENT_CHAT)->setReturnCode(200)
        );
        LogMapper::getInstance()->save(
            (new LogModel())->setAiSessionId($id)->setPrompt('second')
                ->setLogFormat(LogFormat::GUARDRAIL)->setReturnCode(500)
        );

        $sessions = Mapper::getInstance()->getFilteredSessions(
            null, Feature::CHAT->value, null, null, null, 100, 0
        );

        $match = null;
        foreach ($sessions as $session) {
            if ($session->getId() === $id) {
                $match = $session;
            }
        }

        $this->assertInstanceOf(Model::class, $match);
        $this->assertSame('chat', $match->getFeature());
        $this->assertSame('agent_chat', $match->getDerivedLogFormat());
        $this->assertSame(200, $match->getDerivedReturnCode());
    }

    /**
     * The keyword filter matches sessions by their logs' prompt/response text.
     */
    public function testKeywordFilterMatchesLogText(): void
    {
        $id = Uuid::uuid4()->toString();
        Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, null);
        LogMapper::getInstance()->save(
            (new LogModel())->setAiSessionId($id)->setPrompt('a unique needle phrase')
        );

        $count = Mapper::getInstance()->countFilteredSessions(
            'unique needle', null, null, null, null
        );

        $this->assertGreaterThanOrEqual(1, $count);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php`
Expected: FAIL — `Call to undefined method ...Mapper::getFilteredSessions()`.

- [ ] **Step 4: Implement the query methods**

In `library/Schoolbox/OpenAi/Session/Mapper.php` add `use Schoolbox\GenericCollection;` (already present) and `use Schoolbox\User;`, `use Schoolbox\Mapper\Exception;` then add:

```php
    /**
     * Fetch a page of sessions for the admin usage log, newest first.
     *
     * Filters: keyword (matched against the logs' prompt/response via EXISTS),
     * feature, creating user, and created_at date range. logFormat/returnCode are
     * derived from each session's first log (earliest created_at, then id).
     *
     * @param string|null               $keyword
     * @param string|null               $feature
     * @param User|null                 $user
     * @param \Alaress_Date_Format|null $start
     * @param \Alaress_Date_Format|null $end
     * @param int|null                  $limit
     * @param int|null                  $offset
     * @return GenericCollection|Model[]
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
        $offsetSql = null !== $offset ? 'OFFSET :offset' : '';

        $query = "
            SELECT
                s.id AS id,
                s.feature AS feature,
                s.created_at AS created_at,
                firstLog.log_format AS derived_log_format,
                firstLog.return_code AS derived_return_code,
                {$this->getJoinColumnString('user', 'u')}
            FROM `ai_session` s
            LEFT JOIN {$this->getJoinSql('user', 's', 'u', true)}
            LEFT JOIN `ai_session_log` firstLog ON firstLog.id = (
                SELECT id FROM `ai_session_log`
                WHERE ai_session_id = s.id
                ORDER BY created_at ASC, id ASC
                LIMIT 1
            )
            {$where}
            ORDER BY s.created_at DESC
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
        if (null !== $offset) {
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
     * Count all sessions matching the same filters, ignoring limit/offset.
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

        $statement = $this->getDatabase()->prepare("SELECT COUNT(*) FROM `ai_session` s {$where}");
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int)$statement->fetchColumn();
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
                WHERE l.ai_session_id = s.id
                  AND (l.prompt LIKE :keyword OR l.response LIKE :keyword)
            )';
            $params['keyword'] = $like;
        }
        if ('' !== trim((string)$feature)) {
            $clauses[] = 's.feature = :feature';
            $params['feature'] = $feature;
        }
        if (null !== $user) {
            $clauses[] = 's.created_by = :userId';
            $params['userId'] = $user->getId();
        }
        if (null !== $start) {
            $clauses[] = 's.created_at >= :startAt';
            $params['startAt'] = (string)$start;
        }
        if (null !== $end) {
            $clauses[] = 's.created_at <= :endAt';
            $params['endAt'] = (string)$end;
        }

        $where = $clauses ? 'WHERE ' . implode("\nAND ", $clauses) : '';

        return [$where, $params];
    }
```

> Note: `getJoinColumnString`/`getJoinSql` are the same base-mapper helpers used by the existing `getSelectString`/`getFromString`; confirm the `'s'` table alias is accepted by `getJoinSql` (it is the local-table alias). If the helper does not accept an alias argument, fall back to selecting `u.*` columns explicitly mirroring `getColumnString('u')` as the existing `Editor\OpenAI\Log\Mapper::getGroupedLogs` did.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Model.php library/Schoolbox/OpenAi/Session/Mapper.php library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php
git commit -m "Add ai_session list/count queries with derived first-log values [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Write path — `Client` + `ClientFactory` (F2a)

Inject `Session\Mapper` into `Client` (via `ClientFactory`), repoint the `Log\*` alias to the new namespace, and make `createLog` eagerly ensure the session. `feature` is consumed to stamp the session and never set on the log.

**Files:**
- Modify: `library/Schoolbox/OpenAi/Client.php`
- Modify: `library/Schoolbox/OpenAi/IClient.php`
- Modify: `library/Schoolbox/OpenAi/ClientFactory.php`

- [ ] **Step 1: Repoint the `Log` alias in `IClient.php`**

At the top of `library/Schoolbox/OpenAi/IClient.php`, change the import:

```php
// from:
use Schoolbox\Editor\OpenAI\Log;
// to:
use Schoolbox\OpenAi\Session\Log;
```

All `Log\Model` references in the interface now resolve to `Session\Log\Model`. No signature text changes.

- [ ] **Step 2: Repoint the `Log` alias and inject the session mapper in `Client.php`**

At the top of `library/Schoolbox/OpenAi/Client.php`, change `use Schoolbox\Editor\OpenAI\Log;` to `use Schoolbox\OpenAi\Session\Log;` and add `use Schoolbox\OpenAi\Session\Mapper as SessionMapper;`.

Update the constructor (Client.php:74-80) to add the session mapper:

```php
    public function __construct(
        private HttpClient $httpClient,
        private UriInterface $baseUrl,
        private array $authHeaders,
        private Log\Mapper $logMapper,
        private SessionMapper $sessionMapper,
    ) {
    }
```

- [ ] **Step 3: Make `createLog` ensure the session**

Replace the body of `createLog` (Client.php:193-218). The `setFeature` line is removed; `setSessionId` becomes the eager find-or-create plus `setAiSessionId`:

```php
    public function createLog(
        array $payload,
        ?string $inputText = null,
        ?User $user = null,
        ?string $feature = null,
        ?string $aiSessionId = null,
        ?LogFormat $logFormat = null,
    ): Log\Model {
        /** @noinspection PhpUnhandledExceptionInspection - this will never throw */
        $log = $this->logMapper->createModel()
            ->setPrompt(json_encode($payload))
            ->setLogFormat($logFormat);
        if (isset($inputText)) {
            $log = $log->setInputText($inputText);
        }
        if (isset($user)) {
            $log = $log->setUser($user);
        }
        if (isset($aiSessionId)) {
            // Ensure the parent ai_session row exists (carrying the feature) before
            // any ai_session_log row references it.
            $this->sessionMapper->findOrCreate($aiSessionId, $feature, $user);
            $log = $log->setAiSessionId($aiSessionId);
        }
        return $log;
    }
```

`saveLog` (Client.php:223-226) is unchanged — it stays `$this->logMapper->save($log)`.

- [ ] **Step 4: Inject + pass the session mapper in `ClientFactory.php`**

In `library/Schoolbox/OpenAi/ClientFactory.php`: change `use Schoolbox\Editor\OpenAI\Log\Mapper;` to `use Schoolbox\OpenAi\Session\Log\Mapper;` and add `use Schoolbox\OpenAi\Session\Mapper as SessionMapper;`.

Add the param to the constructor (ClientFactory.php:62-67):

```php
    public function __construct(
        private IConfigLoader $config,
        private Mapper $logMapper,
        private HttpClient $httpClient,
        private SessionMapper $sessionMapper,
    ) {
    }
```

Pass it into both `new Client(...)` calls (ClientFactory.php:143-148 in `buildOpenAi`, and 189-194 in `buildAzure`):

```php
        return new Client(
            $this->httpClient,
            new Uri(self::OPENAI_BASE_URL),
            $authHeader,
            $this->logMapper,
            $this->sessionMapper,
        );
```

```php
        return new Client(
            $this->httpClient,
            new Uri($endpoint)->withQuery(http_build_query($queryParts)),
            $authHeader,
            $this->logMapper,
            $this->sessionMapper,
        );
```

- [ ] **Step 5: Verify syntax**

Run: `php -l library/Schoolbox/OpenAi/Client.php && php -l library/Schoolbox/OpenAi/ClientFactory.php && php -l library/Schoolbox/OpenAi/IClient.php`
Expected: `No syntax errors detected` for each. (Runtime behaviour is covered by the existing AI integration tests + Task 5/Kernel wiring; full suite runs in CI.)

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/OpenAi/Client.php library/Schoolbox/OpenAi/IClient.php library/Schoolbox/OpenAi/ClientFactory.php
git commit -m "Ensure ai_session on log creation in OpenAi client [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: Write path — legacy `Editor\OpenAI\Helper::request`

The second write path builds a log inline and saves it. Inject `Session\Mapper`, ensure the session before building the log, and use the new log mapper/model accessor (`setAiSessionId`, no `setFeature`).

**Files:**
- Modify: `library/Schoolbox/Editor/OpenAI/Helper.php`

- [ ] **Step 1: Inspect the current log mapper dependency and constructor**

Run: `grep -n 'logMapper\|Log\\Mapper\|use Schoolbox\\Editor\\OpenAI\\Log\|function __construct\|setSessionId\|setFeature' library/Schoolbox/Editor/OpenAI/Helper.php`
Expected: shows the `$logMapper` property/constructor param (type `Log\Mapper` / `Editor\OpenAI\Log\Mapper`) and the `request()` build at lines ~333-338.

- [ ] **Step 2: Repoint the log mapper type and add the session mapper**

Change the `Helper` log mapper import/type from `Schoolbox\Editor\OpenAI\Log\Mapper` to `Schoolbox\OpenAi\Session\Log\Mapper`, and add a constructor-injected `Schoolbox\OpenAi\Session\Mapper $sessionMapper` property (mirroring the existing `$logMapper` injection — match whatever constructor/DI style the class already uses).

- [ ] **Step 3: Ensure the session and use the new accessor in `request()`**

Replace the log build (Helper.php:333-338) so the session is ensured first and the new accessor is used:

```php
        /* Ensure the ai_session row exists (carrying the feature) before logging
         * against it, then build the log for this request.
         */
        $this->sessionMapper->findOrCreate($aiSessionId, $feature, $user);

        $log = $this->logMapper->createModel()
            ->setUser($user)
            ->setPrompt($payload)
            ->setAiSessionId($aiSessionId)
            ->setEndpoint($this->getEndpoint());
```

(Any later `$this->logMapper->save($log)` in the method stays as-is.)

- [ ] **Step 4: Verify syntax**

Run: `php -l library/Schoolbox/Editor/OpenAI/Helper.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/Editor/OpenAI/Helper.php
git commit -m "Ensure ai_session on legacy Helper log path [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Kernel DI wiring

Wire the new `Session\Log\Mapper` and `Session\Mapper` into everything that previously received `Editor\OpenAI\Log\Mapper`.

**Files:**
- Modify: `library/Alaress/Application/Kernel.php`

- [ ] **Step 1: Find every wiring site**

Run: `grep -n 'Editor\\OpenAI\\Log\\Mapper\|ClientFactory\|OpenAI\\Helper\|OpenAi\\Helper' library/Alaress/Application/Kernel.php`
Expected: the `logMapper` lazy at line ~2470, the `ClientFactory` construction, and the `Editor\OpenAI\Helper` construction.

- [ ] **Step 2: Swap the log mapper and add the session mapper**

For each site that builds `ClientFactory` or `Editor\OpenAI\Helper`, change the `logMapper` argument from `$mapperFactory->getLazy(\Schoolbox\Editor\OpenAI\Log\Mapper::class)` to `$mapperFactory->getLazy(\Schoolbox\OpenAi\Session\Log\Mapper::class)`, and add a `sessionMapper` argument:

```php
'sessionMapper' => $mapperFactory->getLazy(\Schoolbox\OpenAi\Session\Mapper::class),
```

Match the exact argument-passing style each construction uses (named params array vs positional `lazyNew`).

- [ ] **Step 3: Verify syntax**

Run: `php -l library/Alaress/Application/Kernel.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add library/Alaress/Application/Kernel.php
git commit -m "Wire new ai_session mappers through the kernel [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: DialogAgent + Assessment consumer swaps

These modules inject the old log mapper purely to pass it on (the client/agent does the logging). Swap the alias to the new log mapper.

**Files:**
- Modify: `library/Schoolbox/Learning/DialogAgent/Module.php`
- Modify: `library/Schoolbox/Learning/DialogAgent/Controller.php`
- Modify: `library/Schoolbox/Learning/Assessment/Module.php`

- [ ] **Step 1: Check how each uses the mapper**

Run: `grep -n 'AILogMapper\|aiLogMapper\|Editor\\OpenAI\\Log\\Mapper' library/Schoolbox/Learning/DialogAgent/Module.php library/Schoolbox/Learning/DialogAgent/Controller.php library/Schoolbox/Learning/Assessment/Module.php`
Expected: each has `use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;` and `getMapperFactory()->get(AILogMapper::class)` / a typed `$aiLogMapper` property.

- [ ] **Step 2: Repoint the alias**

In each of the three files change:

```php
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
// to:
use Schoolbox\OpenAi\Session\Log\Mapper as AILogMapper;
```

The `AILogMapper::class` references and the `$aiLogMapper` constructor param type then resolve to the new mapper with no other edits. (The DialogAgent controller's `$aiLogMapper` is forwarded into the client/agent flow, which already does the find-or-create via Task 3.)

- [ ] **Step 3: Verify syntax**

Run: `php -l library/Schoolbox/Learning/DialogAgent/Module.php && php -l library/Schoolbox/Learning/DialogAgent/Controller.php && php -l library/Schoolbox/Learning/Assessment/Module.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/Learning/DialogAgent/Module.php library/Schoolbox/Learning/DialogAgent/Controller.php library/Schoolbox/Learning/Assessment/Module.php
git commit -m "Point DialogAgent/Assessment at new ai_session_log mapper [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: Repoint the translators to the new models

The format strategies and the two `ModelToArray` translators (plus `Session\Links`) currently type-hint the old `Editor\OpenAI\Log\Model`. Repoint them to the new models. The new models expose identical getters, so strategy bodies are unchanged. Drop `feature` from the per-log translator; use `getId()` / `getDerivedLogFormat()` / `getDerivedReturnCode()` for the session translator.

**Files:**
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/Format/IStrategy.php`
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/Format/AgentChat.php`
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/Format/Guardrail.php`
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/Format/StrategyFactory.php`
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/ModelToArray.php`
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/Session/ModelToArray.php`
- Modify: `library/Schoolbox/Editor/OpenAI/Log/Translator/Session/Links.php`

- [ ] **Step 1: Repoint the format strategy model type**

In `Format/IStrategy.php`, `Format/AgentChat.php`, `Format/Guardrail.php`, and `Format/StrategyFactory.php`, change every `use Schoolbox\Editor\OpenAI\Log\Model;` to `use Schoolbox\OpenAi\Session\Log\Model;`. Leave `use Schoolbox\Editor\OpenAI\Log\LogFormat;` as-is (the enum stays). Method bodies are unchanged — the new `Session\Log\Model` has the same `getInputText()`, `getOutputText()`, `getResponse()`, etc.

- [ ] **Step 2: Repoint + trim the per-log detail translator**

In `Translator/ModelToArray.php`, change `use Schoolbox\Editor\OpenAI\Log\Model;` to `use Schoolbox\OpenAi\Session\Log\Model;`, change `$log->getSessionId()` to `$log->getAiSessionId()`, and **remove the `feature` key** (Zac: unused on the frontend). The result array becomes:

```php
        $translation = [
            'id' => $log->getId(),
            'sessionId' => $log->getAiSessionId(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $log->getUser() ? $this->userTranslator->__invoke($log->getUser()) : null,
            'logFormat' => $logFormat?->value,
            'data' => $logDataStrategy ? [
                'input' => $logDataStrategy->translateInput($log),
                'output' => $logDataStrategy->translateOutput($log),
            ] : null,
        ];
```

- [ ] **Step 3: Repoint the session-list translator to `Session\Model`**

In `Translator/Session/ModelToArray.php`, change `use Schoolbox\Editor\OpenAI\Log\Model;` to `use Schoolbox\OpenAi\Session\Model;` and update the body to read from the session model (id, feature, and the derived first-log values):

```php
    public function __invoke(Model $session): array
    {
        $routes = $this->linkTranslator->__invoke($session);

        return [
            'id' => $session->getId(),
            'feature' => $session->getFeature(),
            'logFormat' => $session->getDerivedLogFormat(),
            'createdAt' => $session->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $session->getUser() ? $this->userTranslator->__invoke($session->getUser()) : null,
            'returnCode' => $session->getDerivedReturnCode(),
            '_routes' => $routes,
        ];
    }
```

Update the `@param`/`__invoke` type hint to `Model $session` (now `Session\Model`).

- [ ] **Step 4: Repoint the session links translator**

In `Translator/Session/Links.php`, change `use Schoolbox\Editor\OpenAI\Log\Model;` to `use Schoolbox\OpenAi\Session\Model;` and change `$log->getSessionId()` to `$session->getId()`:

```php
    public function __invoke(Model $session): array
    {
        $translation = [];

        $translation['getLog'] = $this->getRouteIfAuthorized('openAIUsage.getLog', [
            'sessionId' => $session->getId(),
        ]);

        return $translation;
    }
```

- [ ] **Step 5: Verify syntax**

Run: `php -l library/Schoolbox/Editor/OpenAI/Log/Translator/ModelToArray.php && php -l library/Schoolbox/Editor/OpenAI/Log/Translator/Session/ModelToArray.php && php -l library/Schoolbox/Editor/OpenAI/Log/Translator/Session/Links.php && php -l library/Schoolbox/Editor/OpenAI/Log/Translator/Format/IStrategy.php && php -l library/Schoolbox/Editor/OpenAI/Log/Translator/Format/AgentChat.php && php -l library/Schoolbox/Editor/OpenAI/Log/Translator/Format/Guardrail.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/Editor/OpenAI/Log/Translator/
git commit -m "Repoint AI usage translators at new session models [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: Admin Usage controller — `postSearch` + `getLog`

Switch the controller to the new mappers: the session list uses `Session\Mapper`, the detail uses `Session\Log\Mapper`.

**Files:**
- Modify: `library/Schoolbox/Admin/OpenAI/Usage/Controller.php`

- [ ] **Step 1: Update imports and constructor**

In `library/Schoolbox/Admin/OpenAI/Usage/Controller.php`, **keep** `use Schoolbox\Editor\OpenAI\Log;` (Task 7 leaves the translators under `Editor\OpenAI\Log\Translator\...`, so `Log\Translator\Session\ModelToArray` etc. must still resolve), and **add** the two new mapper imports:

```php
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
use Schoolbox\OpenAi\Session\Log\Mapper as SessionLogMapper;
```

Replace the constructor (Controller.php:40-45). The old single `Log\Mapper $aiLogMapper` becomes two mappers:

```php
    public function __construct(
        private SessionMapper $sessionMapper,
        private SessionLogMapper $sessionLogMapper,
        private ConfigMapper $configMapper,
        private UserMapper $userMapper,
    ) {
    }
```

> Because Task 7 kept the translators in `Editor\OpenAI\Log\Translator`, keep `use Schoolbox\Editor\OpenAI\Log;` too so `Log\Translator\Session\ModelToArray` etc. still resolve. Only the mapper import changed.

- [ ] **Step 2: Update Kernel wiring for this controller**

The Usage controller is constructed somewhere in `Kernel.php` (or a module) with `aiLogMapper`. Run: `grep -rn 'Admin\\OpenAI\\Usage\\Controller\|openAIUsage' library/Alaress/Application/Kernel.php library/Schoolbox/Admin/OpenAI/Usage/Module.php`
Update that construction to pass `sessionMapper` (`getLazy(Session\Mapper::class)`) and `sessionLogMapper` (`getLazy(Session\Log\Mapper::class)`) in place of the single `aiLogMapper`.

- [ ] **Step 3: Rewrite `postSearch` to use the session mapper**

In `postSearch` (Controller.php:198-236), replace the `getGroupedLogs`/`countGroupedLogs` calls and the translator wiring:

```php
        $logGroups = $this->sessionMapper->getFilteredSessions(
            $keyword,
            $feature,
            $user,
            $startAt,
            $endAt,
            $limit,
            ($page - 1) * $limit,
        );

        $count = $this->sessionMapper->countFilteredSessions(
            $keyword,
            $feature,
            $user,
            $startAt,
            $endAt,
        );
```

The translator block below it is unchanged in shape (it already builds `Log\Translator\Session\ModelToArray`), since Task 7 repointed those translators internally:

```php
        $userTranslator = new User\Translator\Author\ModelToArray();
        $sessionLinksTranslator = new Log\Translator\Session\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $sessionTranslator = new Log\Translator\Session\ModelToArray(
            $userTranslator,
            $sessionLinksTranslator
        );

        $sessions = $logGroups->map($sessionTranslator);
```

- [ ] **Step 4: Rewrite `getLog` to fetch logs by `ai_session_id`**

`getLog` (Controller.php:257-272) currently receives a `Log\Model` resolved from the route `sessionId`. With the route still passing a `sessionId` string, change the lookup to the new log mapper. Replace the method body:

```php
    /**
     * @param string $sessionId - the ai_session id from the route
     * @return void
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiSessionLogApiResponse
     */
    public function getLog(string $sessionId): void
    {
        $logEntries = $this->sessionLogMapper->findByColumn('ai_session_id', $sessionId, [
            'order' => [
                'created_at' => 'ASC',
            ],
        ]);

        $userTranslator = new User\Translator\Author\ModelToArray();
        $strategyFactory = new Log\Translator\Format\StrategyFactory();
        $logTranslator = new Log\Translator\ModelToArray($userTranslator, $strategyFactory);

        $logEntriesArray = $logEntries->map($logTranslator);

        $this->response = $this->responseAsJson($logEntriesArray);
    }
```

> Check the route binding for `openAIUsage.getLog`: if the router currently resolves a `Log\Model` from `sessionId` via a mapper param converter, switch it to pass the raw `sessionId` string (as above). Run `grep -rn "getLog" library/Schoolbox/Admin/OpenAI/Usage/` and the route registration to confirm how the param is injected, and adjust the signature to match the existing convention.

- [ ] **Step 5: Verify syntax**

Run: `php -l library/Schoolbox/Admin/OpenAI/Usage/Controller.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/Admin/OpenAI/Usage/Controller.php library/Alaress/Application/Kernel.php library/Schoolbox/Admin/OpenAI/Usage/Module.php
git commit -m "Switch AI usage admin to ai_session/ai_session_log [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: Frontend type — drop `feature` from `AiSessionLog`

**Files:**
- Modify: `library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts`

- [ ] **Step 1: Remove the `feature` field**

In `payload.ts`, in the `AiSessionLog` type, delete the `feature: AiFeature;` line. Also simplify `AiSessionLogApiResponse` to drop the `& { ... feature: TFeature }` intersection so it no longer requires `feature`:

```ts
export type AiSessionLogApiResponse<
    TLogFormat extends AiLogFormat = AiLogFormat,
> = (AiSessionLog & { logFormat: TLogFormat })[];
```

- [ ] **Step 2: Typecheck the frontend**

Run the project's TypeScript check for this area (e.g. `npx eslint library/Schoolbox/Admin/OpenAI/Usage/templates/` and the repo's `tsc`/type task). Confirm no usage of `AiSessionLog.feature` remains (grep: `grep -rn "\.feature" library/Schoolbox/Admin/OpenAI/Usage/templates/` — the remaining `session.feature` usages are on `AiSession`, which keeps `feature`).
Expected: no type errors referencing the removed field.

- [ ] **Step 3: Commit**

```bash
git add library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts
git commit -m "Drop unused feature from AiSessionLog frontend type [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 10: Step 5 — `dialog_agent_response` column repoint

Point the DataModel mapper at the new `ai_session_id` column while keeping the `sessionId` property and `getSessionId()`/`setSessionId()` accessors (decision 5a), so downstream code is untouched.

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/DataModel/DialogAgent/Response/Mapper.php`

- [ ] **Step 1: Repoint the column mapping**

In `library/Schoolbox/Learning/Assessment/DataModel/DialogAgent/Response/Mapper.php`, change line 43 from:

```php
        $this->uses('session_id', self::TYPE_STRING, null, 'sessionId');
// to:
        $this->uses('ai_session_id', self::TYPE_STRING, null, 'sessionId');
```

The property alias stays `sessionId`, so `Model::getSessionId()/setSessionId()`, the `Transformer`, `NextRoutes` and the DialogAgent `Controller` need no change.

- [ ] **Step 2: Verify syntax**

Run: `php -l library/Schoolbox/Learning/Assessment/DataModel/DialogAgent/Response/Mapper.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Run the existing dialog-agent response tests (if any)**

Run: `grep -rln "DialogAgent\\\\Response" library/Schoolbox/Learning/Assessment/DataModel/DialogAgent/Response/Test/ 2>/dev/null` then run any found test file with `vendor/bin/phpunit <path>`.
Expected: PASS — the round-trip now reads/writes `ai_session_id` transparently.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/Learning/Assessment/DataModel/DialogAgent/Response/Mapper.php
git commit -m "Repoint dialog_agent_response to ai_session_id column [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 11: Delete the dead old log classes

After every consumer is converted, the old mapper/model are unused. Remove them. Keep `LogFormat.php` (still imported by the new model and the strategies) and the repointed translators.

**Files:**
- Delete: `library/Schoolbox/Editor/OpenAI/Log/Mapper.php`
- Delete: `library/Schoolbox/Editor/OpenAI/Log/Model.php`

- [ ] **Step 1: Prove there are no remaining references**

Run: `grep -rn 'Editor\\OpenAI\\Log\\Mapper\|Editor\\OpenAI\\Log\\Model' --include=*.php library/ application/ public/ | grep -v '/Editor/OpenAI/Log/Mapper.php' | grep -v '/Editor/OpenAI/Log/Model.php'`
Expected: **no output** (every consumer now uses the `Session\` classes). If anything prints, convert it before deleting.

- [ ] **Step 2: Delete the files**

```bash
git rm library/Schoolbox/Editor/OpenAI/Log/Mapper.php library/Schoolbox/Editor/OpenAI/Log/Model.php
```

- [ ] **Step 3: Confirm autoload/static analysis is clean**

Run: `grep -rn 'getGroupedLogs\|countGroupedLogs' --include=*.php library/ | grep -v '/OpenAi/Session/'`
Expected: **no output** (the only grouped-session queries now live on `Session\Mapper` as `getFilteredSessions`).

- [ ] **Step 4: Commit**

```bash
git commit -m "Remove unused Editor OpenAI log mapper/model [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 12: Step 7 — deprecation migrations

Rename the now-unused table and column with an underscore prefix (the project's deprecation convention, per the ticket).

**Files:**
- Create: `updates/26.1.alpha1/ai/11-deprecate-openai-log-table.sql`
- Create: `updates/26.1.alpha1/ai/12-deprecate-dialog-agent-response-session-id.sql`

- [ ] **Step 1: Create the table-rename script**

`updates/26.1.alpha1/ai/11-deprecate-openai-log-table.sql`:

```sql
-- [SA-22508] Deprecate the openai_log table: it has been replaced by ai_session
-- and ai_session_log. Renamed (not dropped) so data is retained for one release.
RENAME TABLE `openai_log` TO `_openai_log`;
```

- [ ] **Step 2: Create the column-rename script**

`updates/26.1.alpha1/ai/12-deprecate-dialog-agent-response-session-id.sql`:

```sql
-- [SA-22508] Deprecate dialog_agent_response.session_id: superseded by
-- ai_session_id (FK to ai_session.id). Renamed (not dropped) for one release.
ALTER TABLE `dialog_agent_response`
    CHANGE COLUMN `session_id` `_session_id` char(36) DEFAULT NULL;
```

> Before finalising script 12, confirm the exact current definition of `dialog_agent_response.session_id` (`grep -rn "session_id" sql/ updates/25.1.beta6/openai/01-sessionId.sql updates/26.0.alpha1/dialogagent/01-schema.sql`) and reproduce its type/nullability in the `CHANGE COLUMN` so the rename does not alter the type. `char(36) DEFAULT NULL` matches the `ai_session_id` column added in script 09; adjust if the live column differs.

- [ ] **Step 3: Apply and verify against the Docker DB (or rely on CI provisioning)**

Apply the AI update scripts in order against the test database, then check:

```sql
SHOW TABLES LIKE '\_openai\_log';                       -- expect one row
SHOW COLUMNS FROM dialog_agent_response LIKE '\_session\_id'; -- expect one row
SHOW TABLES LIKE 'openai\_log';                         -- expect empty
```

Expected: the underscore-prefixed names exist and the originals are gone.

- [ ] **Step 4: Commit**

```bash
git add updates/26.1.alpha1/ai/11-deprecate-openai-log-table.sql updates/26.1.alpha1/ai/12-deprecate-dialog-agent-response-session-id.sql
git commit -m "Deprecate openai_log table and dialog_agent_response.session_id [SA-22508]

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 13: Whole-PR verification

- [ ] **Step 1: Static analysis**

Run the project's PHPStan over the changed namespaces (the repo uses `phpstan.neon`):
`vendor/bin/phpstan analyse library/Schoolbox/OpenAi library/Schoolbox/Editor/OpenAI library/Schoolbox/Admin/OpenAI library/Schoolbox/Learning`
Expected: no new errors introduced by this change (baseline pre-existing errors aside).

- [ ] **Step 2: Run the new and affected DB tests**

```bash
vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/FindOrCreateTest.php
vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/FilteredSessionsTest.php
vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php
vendor/bin/phpunit library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php
```

Expected: all PASS (DB tests also run in CI).

- [ ] **Step 3: No stale references remain**

Run: `grep -rn "openai_log\b" --include=*.php library/ | grep -v '_openai_log'`
Expected: only comments/strings remain (e.g. doc comments) — no live mapper/table code references the old table name.

- [ ] **Step 4: Manual smoke (per the spec's Verification section)**

Exercise an AI feature end to end (e.g. dialog agent chat) and confirm a new `ai_session` row plus its `ai_session_log` rows are written and nothing new lands in `_openai_log`. Load the admin AI usage log: the session list, the "Error" column, and the per-session "view log" detail all render.

- [ ] **Step 5: Update the PR description progress checklist**

Tick steps 5, 6 and 7 in PR #35132's "Progress" section.
