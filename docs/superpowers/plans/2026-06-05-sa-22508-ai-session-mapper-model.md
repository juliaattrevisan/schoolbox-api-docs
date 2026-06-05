# New AI Session mapper/model classes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add new mapper, model, and enum classes that map the new `ai_session` and `ai_session_log` tables, in the `Schoolbox\OpenAi\Session\` namespace, without touching any existing consumers.

**Architecture:** Mirror the existing `Schoolbox\Editor\OpenAI\Log\Mapper`/`Model` pattern. The single old `openai_log` table is now two tables, so we get two mapper/model pairs. `feature` (previously a per-log string) becomes a typed `Feature` enum on the session. `LogFormat` is referenced in place from `Schoolbox\Editor\OpenAI\Log`. Each mapper LEFT JOINs `user` via `created_by` and exposes `getUser()/setUser()`. The two models are independent (no eager join); a session's logs are fetched via `findByColumn('ai_session_id', …)`.

**Tech Stack:** PHP 8, the Schoolbox `Mapper` ORM (`library/Schoolbox/Mapper.php`), PHPUnit. DB round-trip tests use `Alaress\Database\Test\TestConnectorTrait`.

**Spec:** `docs/superpowers/specs/2026-06-05-sa-22508-ai-session-mapper-model-design.md`

---

## File Structure

- Create `library/Schoolbox/OpenAi/Session/Feature.php` — backed enum of the `ai_session.feature` DB enum values.
- Create `library/Schoolbox/OpenAi/Session/Model.php` — model for `ai_session`.
- Create `library/Schoolbox/OpenAi/Session/Mapper.php` — mapper for `ai_session`.
- Create `library/Schoolbox/OpenAi/Session/Log/Model.php` — model for `ai_session_log`.
- Create `library/Schoolbox/OpenAi/Session/Log/Mapper.php` — mapper for `ai_session_log`.
- Create `library/Schoolbox/OpenAi/Session/Test/FeatureTest.php` — unit test for the enum.
- Create `library/Schoolbox/OpenAi/Session/Test/ModelTest.php` — unit test for the session model.
- Create `library/Schoolbox/OpenAi/Session/Log/Test/ModelTest.php` — unit test for the log model (length trimming, logFormat normalisation).
- Create `library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php` — DB round-trip test for the session mapper.
- Create `library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php` — DB round-trip test for the log mapper.

How to run a single test file:
`vendor/bin/phpunit --filter <TestClassName> library/Schoolbox/<path>/<File>Test.php`

---

## Task 1: `Feature` enum

**Files:**
- Create: `library/Schoolbox/OpenAi/Session/Feature.php`
- Test: `library/Schoolbox/OpenAi/Session/Test/FeatureTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Feature;

class FeatureTest extends TestCase
{
    /**
     * Guards that the enum values stay in sync with the ai_session.feature DB enum.
     */
    public function testValuesMatchDatabaseEnum(): void
    {
        $values = array_column(Feature::cases(), 'value');

        $this->assertEqualsCanonicalizing([
            'chat',
            'lessonplan',
            'image',
            'differentiation',
            'dialogagent',
            'learningactivitycustomisation',
            'aimoderationagent',
        ], $values);
    }

    public function testCanBuildFromValue(): void
    {
        $this->assertSame(Feature::CHAT, Feature::from('chat'));
        $this->assertSame(Feature::AI_MODERATION_AGENT, Feature::from('aimoderationagent'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter FeatureTest library/Schoolbox/OpenAi/Session/Test/FeatureTest.php`
Expected: FAIL — class `Schoolbox\OpenAi\Session\Feature` not found.

- [ ] **Step 3: Write the enum**

```php
<?php

/**
 * The AI features that own a session, mirroring the `ai_session`.`feature` DB enum.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

enum Feature: string
{
    case CHAT = 'chat';
    case LESSON_PLAN = 'lessonplan';
    case IMAGE = 'image';
    case DIFFERENTIATION = 'differentiation';
    case DIALOG_AGENT = 'dialogagent';
    case LEARNING_ACTIVITY_CUSTOMISATION = 'learningactivitycustomisation';
    case AI_MODERATION_AGENT = 'aimoderationagent';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FeatureTest library/Schoolbox/OpenAi/Session/Test/FeatureTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Feature.php library/Schoolbox/OpenAi/Session/Test/FeatureTest.php
git commit -m "Add OpenAi\\Session\\Feature enum [SA-22508]"
```

---

## Task 2: `Session\Model`

**Files:**
- Create: `library/Schoolbox/OpenAi/Session/Model.php`
- Test: `library/Schoolbox/OpenAi/Session/Test/ModelTest.php`

The model has a string UUID `id` (defined directly, not via the int `Id` trait), a
`feature` normalised through the `Feature` enum but stored/returned as a string (same
pattern as `Editor\OpenAI\Log\Model::setLogFormat`), `createdAt` via the `CreatedAtDate`
trait, and a `user` join accessor.

- [ ] **Step 1: Write the failing test**

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\User;

class ModelTest extends TestCase
{
    public function testSetFeatureAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature(Feature::DIALOG_AGENT);

        $this->assertSame('dialogagent', $model->getFeature());
    }

    public function testSetFeatureAcceptsStringAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature('chat');

        $this->assertSame('chat', $model->getFeature());
    }

    public function testSetFeatureAcceptsNull(): void
    {
        $model = new Model();
        $model->setFeature(null);

        $this->assertNull($model->getFeature());
    }

    public function testIdIsString(): void
    {
        $model = new Model();
        $model->setId('1f2e3d4c-5b6a-7890-abcd-ef0123456789');

        $this->assertSame('1f2e3d4c-5b6a-7890-abcd-ef0123456789', $model->getId());
    }

    public function testUserAccessor(): void
    {
        $user = new User();
        $model = new Model();
        $model->setUser($user);

        $this->assertSame($user, $model->getUser());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "Schoolbox\\OpenAi\\Session\\Test\\ModelTest" library/Schoolbox/OpenAi/Session/Test/ModelTest.php`
Expected: FAIL — class `Schoolbox\OpenAi\Session\Model` not found.

- [ ] **Step 3: Write the model**

```php
<?php

/**
 * Model for a single AI session (one row of the `ai_session` table).
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\User;

class Model
{
    use CreatedAtDate;

    /**
     * UUID primary key. Null until persisted (the mapper auto-generates it on save).
     */
    private ?string $id = null;

    private ?User $user = null;

    private ?string $feature = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    /**
     * @param string|Feature|null $feature
     */
    public function setFeature(string|Feature|null $feature): self
    {
        $this->feature = $feature instanceof Feature
            ? $feature->value
            : $feature;

        return $this;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "Schoolbox\\OpenAi\\Session\\Test\\ModelTest" library/Schoolbox/OpenAi/Session/Test/ModelTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Model.php library/Schoolbox/OpenAi/Session/Test/ModelTest.php
git commit -m "Add OpenAi\\Session\\Model [SA-22508]"
```

---

## Task 3: `Session\Mapper` (with DB round-trip test)

**Files:**
- Create: `library/Schoolbox/OpenAi/Session/Mapper.php`
- Test: `library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php`

The mapper maps the `ai_session` table, auto-generating the UUID `id` on save
(`COLUMN_IDENTIFIER_UUID`, as in `Tour\Mapper`), validates `feature` against the
`Feature` enum values (`COLUMN_ENUM`), LEFT JOINs `user` via `created_by`, and sets
`created_at` as the created timestamp.

- [ ] **Step 1: Write the failing DB test**

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
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Mapper;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\UserMapper;

class MapperTest extends TestCase
{
    use TestConnectorTrait;

    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    public function testSaveGeneratesUuidAndRoundTrips(): void
    {
        $model = (new Model())->setFeature(Feature::CHAT);

        Mapper::getInstance()->save($model);

        $id = $model->getId();
        $this->assertNotNull($id);
        $this->assertSame(36, strlen($id), 'A char(36) UUID should be generated on save');

        $reloaded = Mapper::getInstance()->findById($id);

        $this->assertInstanceOf(Model::class, $reloaded);
        $this->assertSame($id, $reloaded->getId());
        $this->assertSame('chat', $reloaded->getFeature());
        $this->assertNull($reloaded->getUser());
    }

    public function testUserJoinRoundTrips(): void
    {
        // Use any existing user in the (transactional) test DB for the created_by FK.
        $user = UserMapper::getInstance()->findOneByColumnList([]);
        $this->assertNotNull($user, 'Test DB is expected to contain at least one user');

        $model = (new Model())
            ->setFeature(Feature::DIALOG_AGENT)
            ->setUser($user);

        Mapper::getInstance()->save($model);

        $reloaded = Mapper::getInstance()->findById($model->getId());

        $this->assertNotNull($reloaded->getUser());
        $this->assertSame($user->getId(), $reloaded->getUser()->getId());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "Schoolbox\\Database\\Test\\OpenAi\\Session\\MapperTest" library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php`
Expected: FAIL — class `Schoolbox\OpenAi\Session\Mapper` not found.

- [ ] **Step 3: Write the mapper**

```php
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
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\UserMapper;

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
        $this->hasTable('ai_session');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('feature', self::TYPE_STRING, self::COLUMN_ENUM, null, array_column(Feature::cases(), 'value'));
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'user', 'user');
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
                $session = $this->hydrate($row);

                /** @var \Schoolbox\User $user */
                $user = $userMapper->hydrate($row, 'u');

                if ($user) {
                    $session->setUser($user);
                }

                $collection->add($session);
            } catch (BaseMapper\Exception $e) {
                // noop, couldn't hydrate for some reason
            }
        }

        return $collection;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "Schoolbox\\Database\\Test\\OpenAi\\Session\\MapperTest" library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php`
Expected: PASS (2 tests).

If `findOneByColumnList([])` does not return a user, fall back to
`UserMapper::getInstance()->findById(1)` (system user) in the test — adjust only the
test, not the mapper.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Mapper.php library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php
git commit -m "Add OpenAi\\Session\\Mapper [SA-22508]"
```

---

## Task 4: `Session\Log\Model`

**Files:**
- Create: `library/Schoolbox/OpenAi/Session/Log/Model.php`
- Test: `library/Schoolbox/OpenAi/Session/Log/Test/ModelTest.php`

This is the old `Editor\OpenAI\Log\Model` minus `feature` (now on the session) and with
`sessionId` renamed to `aiSessionId`. It keeps the int `Id` trait (auto-increment),
the `CreatedAtDate` trait, the `user` accessor, the `logFormat` normalisation importing
`LogFormat` from `Editor\OpenAI\Log`, and the length-trimming constants/logic for
`prompt`, `inputText`, `outputText`, and `response`.

- [ ] **Step 1: Write the failing test**

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Log\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Session\Log\Model;

class ModelTest extends TestCase
{
    public function testSetLogFormatAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setLogFormat(LogFormat::AGENT_CHAT);

        $this->assertSame('agent_chat', $model->getLogFormat());
    }

    public function testSetLogFormatAcceptsString(): void
    {
        $model = new Model();
        $model->setLogFormat('guardrail');

        $this->assertSame('guardrail', $model->getLogFormat());
    }

    public function testAiSessionIdAccessor(): void
    {
        $model = new Model();
        $model->setAiSessionId('1f2e3d4c-5b6a-7890-abcd-ef0123456789');

        $this->assertSame('1f2e3d4c-5b6a-7890-abcd-ef0123456789', $model->getAiSessionId());
    }

    public function testPromptIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setPrompt(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getPrompt()));
    }

    public function testResponseIsTrimmedToMediumTextMaxLength(): void
    {
        $model = new Model();
        $model->setResponse(str_repeat('a', 16777215 + 10));

        $this->assertSame(16777215, mb_strlen($model->getResponse()));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "Schoolbox\\OpenAi\\Session\\Log\\Test\\ModelTest" library/Schoolbox/OpenAi/Session/Log/Test/ModelTest.php`
Expected: FAIL — class `Schoolbox\OpenAi\Session\Log\Model` not found.

- [ ] **Step 3: Write the model**

```php
<?php

/**
 * Model for a single AI session log (one row of the `ai_session_log` table).
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Log;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;

    private ?string $aiSessionId = null;
    private ?User $user = null;
    private string $prompt;
    private ?int $inputTokens = null;
    private ?int $outputTokens = null;
    private ?string $inputText = null;
    private ?string $outputText = null;
    private ?string $model = null;
    private ?string $endpoint = null;
    private ?string $logFormat = null;
    private ?string $response = null;
    private ?int $returnCode = null;
    private ?string $errorMessage = null;

    // TEXT max length, to trim the prompt, rather than die.
    private const PROMPT_MAX_LENGTH = 65535;

    // TEXT max length for input_text and output_text columns
    private const TEXT_MAX_LENGTH = 65535;

    // MEDIUM TEXT max length, to trim the response, rather than die.
    private const RESPONSE_MAX_LENGTH = 16777215;

    public function getAiSessionId(): ?string
    {
        return $this->aiSessionId;
    }

    public function setAiSessionId(?string $aiSessionId): self
    {
        $this->aiSessionId = $aiSessionId;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): self
    {
        // Limit the prompt length to prevent database errors
        if ($prompt && mb_strlen($prompt) > self::PROMPT_MAX_LENGTH) {
            $prompt = mb_substr($prompt, 0, self::PROMPT_MAX_LENGTH);
        }
        $this->prompt = $prompt;
        return $this;
    }

    public function getReturnCode(): ?int
    {
        return $this->returnCode;
    }

    public function setReturnCode(?int $returnCode): self
    {
        $this->returnCode = $returnCode;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getInputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function setInputTokens(?int $inputTokens): self
    {
        $this->inputTokens = $inputTokens;
        return $this;
    }

    public function getOutputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function setOutputTokens(?int $outputTokens): self
    {
        $this->outputTokens = $outputTokens;
        return $this;
    }

    public function getInputText(): ?string
    {
        return $this->inputText;
    }

    public function setInputText(?string $inputText): self
    {
        // Limit the input text length to prevent database errors
        if ($inputText && mb_strlen($inputText) > self::TEXT_MAX_LENGTH) {
            $inputText = mb_substr($inputText, 0, self::TEXT_MAX_LENGTH);
        }
        $this->inputText = $inputText;
        return $this;
    }

    public function getOutputText(): ?string
    {
        return $this->outputText;
    }

    public function setOutputText(?string $outputText): self
    {
        // Limit the output text length to prevent database errors
        if ($outputText && mb_strlen($outputText) > self::TEXT_MAX_LENGTH) {
            $outputText = mb_substr($outputText, 0, self::TEXT_MAX_LENGTH);
        }
        $this->outputText = $outputText;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getLogFormat(): ?string
    {
        return $this->logFormat;
    }

    /**
     * @param string|LogFormat|null $logFormat
     */
    public function setLogFormat(string|LogFormat|null $logFormat): self
    {
        $this->logFormat = $logFormat instanceof LogFormat
            ? $logFormat->value
            : $logFormat;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        // Limit the response length to prevent database errors
        if ($response && mb_strlen($response) > self::RESPONSE_MAX_LENGTH) {
            $response = mb_substr($response, 0, self::RESPONSE_MAX_LENGTH);
        }
        $this->response = $response;
        return $this;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "Schoolbox\\OpenAi\\Session\\Log\\Test\\ModelTest" library/Schoolbox/OpenAi/Session/Log/Test/ModelTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Log/Model.php library/Schoolbox/OpenAi/Session/Log/Test/ModelTest.php
git commit -m "Add OpenAi\\Session\\Log\\Model [SA-22508]"
```

---

## Task 5: `Session\Log\Mapper` (with DB round-trip test)

**Files:**
- Create: `library/Schoolbox/OpenAi/Session/Log/Mapper.php`
- Test: `library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php`

The mapper maps the `ai_session_log` table, LEFT JOINs `user` via `created_by`, and
validates `log_format` against the `LogFormat` enum values (allowing null). The test
first creates a parent `ai_session` row (FK target) inside the transaction, then saves a
log referencing it.

- [ ] **Step 1: Write the failing DB test**

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session\Log;

use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Log\Mapper;
use Schoolbox\OpenAi\Session\Log\Model;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
use Schoolbox\OpenAi\Session\Model as SessionModel;

class MapperTest extends TestCase
{
    use TestConnectorTrait;

    private string $sessionId;

    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        // ai_session_log.ai_session_id is a FK to ai_session.id, so create a parent first.
        $session = (new SessionModel())->setFeature(Feature::CHAT);
        SessionMapper::getInstance()->save($session);
        $this->sessionId = $session->getId();
    }

    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    public function testSaveAndRoundTrip(): void
    {
        $model = (new Model())
            ->setAiSessionId($this->sessionId)
            ->setPrompt('Hello')
            ->setInputTokens(11)
            ->setOutputTokens(22)
            ->setInputText('in')
            ->setOutputText('out')
            ->setModel('gpt-4o')
            ->setEndpoint('/v1/responses')
            ->setLogFormat(LogFormat::AGENT_CHAT)
            ->setResponse('the response')
            ->setReturnCode(200);

        Mapper::getInstance()->save($model);

        $id = $model->getId();
        $this->assertNotNull($id);

        $reloaded = Mapper::getInstance()->findById($id);

        $this->assertInstanceOf(Model::class, $reloaded);
        $this->assertSame($this->sessionId, $reloaded->getAiSessionId());
        $this->assertSame('Hello', $reloaded->getPrompt());
        $this->assertSame(11, $reloaded->getInputTokens());
        $this->assertSame(22, $reloaded->getOutputTokens());
        $this->assertSame('in', $reloaded->getInputText());
        $this->assertSame('out', $reloaded->getOutputText());
        $this->assertSame('gpt-4o', $reloaded->getModel());
        $this->assertSame('/v1/responses', $reloaded->getEndpoint());
        $this->assertSame('agent_chat', $reloaded->getLogFormat());
        $this->assertSame('the response', $reloaded->getResponse());
        $this->assertSame(200, $reloaded->getReturnCode());
        $this->assertNull($reloaded->getUser());
    }

    public function testNullLogFormatRoundTrips(): void
    {
        $model = (new Model())
            ->setAiSessionId($this->sessionId)
            ->setPrompt('No format')
            ->setLogFormat(null);

        Mapper::getInstance()->save($model);

        $reloaded = Mapper::getInstance()->findById($model->getId());

        $this->assertNull($reloaded->getLogFormat());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter "Schoolbox\\Database\\Test\\OpenAi\\Session\\Log\\MapperTest" library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php`
Expected: FAIL — class `Schoolbox\OpenAi\Session\Log\Mapper` not found.

- [ ] **Step 3: Write the mapper**

```php
<?php

/**
 * Mapper for the `ai_session_log` table — one row per logged AI request.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Log;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

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
        $this->hasTable('ai_session_log');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('ai_session_id', self::TYPE_STRING);
        $this->uses('prompt', self::TYPE_STRING);
        $this->uses('input_tokens', self::TYPE_INTEGER);
        $this->uses('output_tokens', self::TYPE_INTEGER);
        $this->uses('input_text', self::TYPE_STRING);
        $this->uses('output_text', self::TYPE_STRING);
        $this->uses('model', self::TYPE_STRING);
        $this->uses('endpoint', self::TYPE_STRING);
        $this->uses('log_format', self::TYPE_STRING, self::COLUMN_ENUM, null, [...array_column(LogFormat::cases(), 'value'), null]);
        $this->uses('response', self::TYPE_STRING);
        $this->uses('return_code', self::TYPE_INTEGER);
        $this->uses('error_message', self::TYPE_STRING);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'user', 'user');
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
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter "Schoolbox\\Database\\Test\\OpenAi\\Session\\Log\\MapperTest" library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/OpenAi/Session/Log/Mapper.php library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php
git commit -m "Add OpenAi\\Session\\Log\\Mapper [SA-22508]"
```

---

## Task 6: Whole-suite sanity check

**Files:** none (verification only)

- [ ] **Step 1: Run all new tests together**

Run:
```bash
vendor/bin/phpunit \
  library/Schoolbox/OpenAi/Session/Test/FeatureTest.php \
  library/Schoolbox/OpenAi/Session/Test/ModelTest.php \
  library/Schoolbox/OpenAi/Session/Log/Test/ModelTest.php \
  library/Schoolbox/Database/Test/OpenAi/Session/MapperTest.php \
  library/Schoolbox/Database/Test/OpenAi/Session/Log/MapperTest.php
```
Expected: all green (16 tests total).

- [ ] **Step 2: Run static analysis on the new files**

Run: `vendor/bin/phpstan analyse library/Schoolbox/OpenAi/Session`
Expected: no new errors. (phpstan.neon is already modified in the working tree; if the
new paths need listing there, add them following the existing entries — but do not
otherwise touch unrelated config.)

- [ ] **Step 3: Final confirmation**

Confirm `git log --oneline` shows the five feature commits and the working tree is clean.
No further commit needed.

---

## Notes / things to watch during execution

- **`feature` getter returns a string, not a `Feature`.** This is intentional, mirroring
  the existing `LogFormat` handling so the mapper's `COLUMN_ENUM` dehydration receives a
  scalar. The enum is the single source of truth for the allowed values
  (`array_column(Feature::cases(), 'value')`) and for type-safe construction at call sites.
- **`UserMapper::findOneByColumnList([])`** is used in the session mapper test just to grab
  any existing user for the FK. If that signature/behaviour differs, fall back to
  `findById(1)`. Adjust the test only — never weaken the mapper to make a test pass.
- **FK ordering:** `ai_session_log.ai_session_id` references `ai_session.id`, so the log
  mapper test creates a parent session first (handled in its `setUp`).
- **Deferred (NOT in this plan):** `getGroupedLogs`/`countGroupedLogs`, the
  `MISSING_SESSIONID` constant, reconciling `Helper::FEATURE_*`, the frontend
  `ModelToArray` translators, and converting the ~35 existing consumers. Those belong to
  the follow-up conversion phase.
```