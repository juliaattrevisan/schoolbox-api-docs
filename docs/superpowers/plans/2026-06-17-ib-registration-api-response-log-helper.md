# IB Registration API Response Log Helper Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add backend support for saving IBIS registration response logs and returning the latest candidate registration error to the existing IB candidate-list API.

**Architecture:** Add a focused `Schoolbox\IB\Registrations\Api\ResponseLog` model, mapper, and helper around `ib_registration_api_response_log`. `RegisterCandidatesTool` writes response rows through the helper, while the admin controller reads latest error messages once per session and passes each candidate's message into the existing student translator as `registrationError`.

**Tech Stack:** PHP 8.4, Schoolbox Mapper/Model patterns, PHPUnit, MariaDB.

---

## File Structure

- Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php`: row model and constants for request type/status values.
- Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php`: mapper definition and latest-error query.
- Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php`: intent-level save/read methods for CLI and controller use.
- Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php`: unit tests for helper conversion and read mapping.
- Modify `library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php`: accept optional registration error and include it in payloads.
- Modify `library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php`: assert default and populated `registrationError`.
- Modify `library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php`: inject response log helper and enrich candidate payloads.
- Modify `library/Schoolbox/Admin/InternationalBaccalaureate/Module.php`: construct/inject response log helper.
- Modify `library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php`: save log rows for IBIS candidate and session responses.

---

### Task 1: Response Log Model And Mapper

**Files:**
- Create: `library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php`
- Create: `library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php`

- [ ] **Step 1: Create the response log model**

Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php`:

```php
<?php

/**
 * IB registration API response log model
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog;

use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\Mapper\Traits\Model as ModelTraits;

class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;

    public const string REQUEST_TYPE_REGISTER_CANDIDATES = 'register_candidates';
    public const string REQUEST_TYPE_REGISTER_SESSIONS = 'register_sessions';
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_ERROR = 'error';

    private ?RegistrationSessionModel $registrationSession = null;
    private ?RegistrationSessionStudentModel $registrationSessionStudent = null;
    private string $msgId = '';
    private string $requestType = self::REQUEST_TYPE_REGISTER_CANDIDATES;
    private string $status = self::STATUS_ERROR;
    private ?string $message = null;

    public function getRegistrationSession(): ?RegistrationSessionModel
    {
        return $this->registrationSession;
    }

    public function setRegistrationSession(?RegistrationSessionModel $registrationSession): self
    {
        $this->registrationSession = $registrationSession;
        return $this;
    }

    public function getRegistrationSessionStudent(): ?RegistrationSessionStudentModel
    {
        return $this->registrationSessionStudent;
    }

    public function setRegistrationSessionStudent(?RegistrationSessionStudentModel $registrationSessionStudent): self
    {
        $this->registrationSessionStudent = $registrationSessionStudent;
        return $this;
    }

    public function getMsgId(): string
    {
        return $this->msgId;
    }

    public function setMsgId(string $msgId): self
    {
        $this->msgId = $msgId;
        return $this;
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    public function setRequestType(string $requestType): self
    {
        $this->requestType = $requestType;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

}
```

- [ ] **Step 2: Create the response log mapper**

Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php`:

```php
<?php

/**
 * IB registration API response log mapper
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog;

use PDO;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * @method Model createModel($idValue = null)
 * @method ?Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    protected function mapping(): void
    {
        $this->hasTable('ib_registration_api_response_log');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('registration_session_id', RegistrationSessionMapper::class, 'id', null, null, 'registrationSession', 'registrationSession');
        $this->joins('registration_session_student_id', RegistrationSessionStudentMapper::class, 'id', null, null, 'registrationSessionStudent', 'registrationSessionStudent');
        $this->uses('msg_id', self::TYPE_STRING, null, 'msgId');
        $this->uses('request_type', self::TYPE_STRING, null, 'requestType');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('message', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }

    /**
     * @return GenericCollection|Model[]
     */
    public function findLatestErrorsForSession(int $registrationSessionId): GenericCollection
    {
        $query = "
            SELECT ranked.*
            FROM (
                SELECT l.*,
                       ROW_NUMBER() OVER (
                           PARTITION BY l.registration_session_student_id
                           ORDER BY l.created_at DESC, l.id DESC
                       ) AS row_num
                FROM {$this->getTable()} l
                WHERE l.registration_session_id = :registrationSessionId
                  AND l.registration_session_student_id IS NOT NULL
                  AND l.status = :status
            ) ranked
            WHERE ranked.row_num = 1
            ORDER BY ranked.created_at DESC, ranked.id DESC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'registrationSessionId' => $registrationSessionId,
            'status' => Model::STATUS_ERROR,
        ]);

        $logs = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $log = $this->hydrate($row);

            if ($log instanceof Model) {
                $logs->add($log);
            }
        }

        return $logs;
    }
}
```

- [ ] **Step 3: Run syntax checks**

Run:

```bash
php -l library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php
php -l library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php
```

Expected: both commands print `No syntax errors detected`.

- [ ] **Step 4: Commit**

Run:

```bash
git add library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php
git commit -m "Add IB API response log mapper [SA-21594]"
```

---

### Task 2: Response Log Helper

**Files:**
- Create: `library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php`
- Create: `library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php`

- [ ] **Step 1: Write helper unit tests**

Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php`:

```php
<?php

/**
 * IB registration API response log helper test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Api\ResponseLog\Helper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Mapper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Model;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;

class HelperTest extends TestCase
{
    public function testSavesCandidateResponseAsErrorLog(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);
        $student = $this->student();

        (new Helper($mapper))->saveCandidateResponse(
            $student,
            str_repeat('A', 96),
            [
                'candidateId' => null,
                'referenceId' => '456',
                'text' => 'Candidate rejected',
                'successful' => false,
            ]
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(12, $saved->getRegistrationSession()?->getId());
        $this->assertSame(456, $saved->getRegistrationSessionStudent()?->getId());
        $this->assertSame(str_repeat('A', 96), $saved->getMsgId());
        $this->assertSame(Model::REQUEST_TYPE_REGISTER_CANDIDATES, $saved->getRequestType());
        $this->assertSame(Model::STATUS_ERROR, $saved->getStatus());
        $this->assertSame('Candidate rejected', $saved->getMessage());
    }

    public function testSavesSessionResponseAsSuccessLog(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);

        (new Helper($mapper))->saveSessionResponse(
            $this->student(),
            str_repeat('B', 96),
            [
                'candidateId' => 'abc123',
                'month' => 'May',
                'year' => '2026',
                'text' => 'Success',
                'successful' => true,
            ]
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(Model::REQUEST_TYPE_REGISTER_SESSIONS, $saved->getRequestType());
        $this->assertSame(Model::STATUS_SUCCESS, $saved->getStatus());
        $this->assertSame('Success', $saved->getMessage());
    }

    public function testSavesRequestError(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);

        (new Helper($mapper))->saveRequestError(
            12,
            $this->student(),
            str_repeat('C', 96),
            Model::REQUEST_TYPE_REGISTER_CANDIDATES,
            new \RuntimeException('IBIS unavailable')
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(12, $saved->getRegistrationSession()?->getId());
        $this->assertSame(456, $saved->getRegistrationSessionStudent()?->getId());
        $this->assertSame(Model::STATUS_ERROR, $saved->getStatus());
        $this->assertSame('IBIS unavailable', $saved->getMessage());
    }

    public function testReturnsLatestErrorMessagesByStudentId(): void
    {
        $log = (new Model())
            ->setRegistrationSessionStudent((new RegistrationSessionStudentModel())->setId(456))
            ->setMessage('Latest error');

        $mapper = $this->createMock(Mapper::class);
        $mapper
            ->expects($this->once())
            ->method('findLatestErrorsForSession')
            ->with(12)
            ->willReturn(new GenericCollection(Model::class, [$log]));

        $this->assertSame(
            [456 => 'Latest error'],
            (new Helper($mapper))->getLatestErrorMessagesByStudentId(12)
        );
    }

    private function mapperSavingTo(?Model &$saved): Mapper
    {
        $mapper = $this->createMock(Mapper::class);
        $mapper
            ->method('createModel')
            ->willReturnCallback(static fn(): Model => new Model());
        $mapper
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Model $model) use (&$saved): Model {
                $saved = $model;
                return $model;
            });

        return $mapper;
    }

    private function student(): RegistrationSessionStudentModel
    {
        return (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setRegistrationSession((new RegistrationSessionModel())->setId(12));
    }
}
```

- [ ] **Step 2: Run helper tests to verify they fail**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php
```

Expected: FAIL because `Schoolbox\IB\Registrations\Api\ResponseLog\Helper` does not exist.

- [ ] **Step 3: Implement the helper**

Create `library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php`:

```php
<?php

/**
 * IB registration API response log helper
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog;

use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Throwable;

class Helper
{
    public function __construct(private Mapper $mapper)
    {
    }

    /**
     * @param array{candidateId?: string|null, referenceId?: string|null, text?: string|null, successful: bool} $candidateResponse
     */
    public function saveCandidateResponse(
        RegistrationSessionStudentModel $student,
        string $msgId,
        array $candidateResponse
    ): void {
        $this->saveStudentResponse(
            $student,
            $msgId,
            Model::REQUEST_TYPE_REGISTER_CANDIDATES,
            (bool)$candidateResponse['successful'],
            $candidateResponse['text'] ?? null
        );
    }

    /**
     * @param array{candidateId?: string|null, month?: string|null, year?: string|null, text?: string|null, successful: bool} $sessionResponse
     */
    public function saveSessionResponse(
        RegistrationSessionStudentModel $student,
        string $msgId,
        array $sessionResponse
    ): void {
        $this->saveStudentResponse(
            $student,
            $msgId,
            Model::REQUEST_TYPE_REGISTER_SESSIONS,
            (bool)$sessionResponse['successful'],
            $sessionResponse['text'] ?? null
        );
    }

    public function saveRequestError(
        int $registrationSessionId,
        ?RegistrationSessionStudentModel $student,
        string $msgId,
        string $requestType,
        Throwable|string $error
    ): void {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $registrationSession = $student?->getRegistrationSession()
            ?? (new RegistrationSessionModel())->setId($registrationSessionId);

        $log = $this->mapper->createModel()
            ->setRegistrationSession($registrationSession)
            ->setRegistrationSessionStudent($student)
            ->setMsgId($msgId)
            ->setRequestType($requestType)
            ->setStatus(Model::STATUS_ERROR)
            ->setMessage($message !== '' ? $message : null);

        $this->mapper->save($log);
    }

    /**
     * @return array<int, string>
     */
    public function getLatestErrorMessagesByStudentId(int $registrationSessionId): array
    {
        $messages = [];

        foreach ($this->mapper->findLatestErrorsForSession($registrationSessionId) as $log) {
            if (!$log instanceof Model) {
                continue;
            }

            $studentId = $log->getRegistrationSessionStudent()?->getId();
            $message = trim((string)($log->getMessage() ?? ''));

            if ($studentId !== null && $message !== '') {
                $messages[(int)$studentId] = $message;
            }
        }

        return $messages;
    }

    private function saveStudentResponse(
        RegistrationSessionStudentModel $student,
        string $msgId,
        string $requestType,
        bool $successful,
        ?string $message
    ): void {
        $registrationSession = $student->getRegistrationSession();

        if ($registrationSession === null || $registrationSession->getId() === null || $student->getId() === null) {
            throw new \InvalidArgumentException('Registration session and student IDs are required to log IBIS responses.');
        }

        $log = $this->mapper->createModel()
            ->setRegistrationSession($registrationSession)
            ->setRegistrationSessionStudent($student)
            ->setMsgId($msgId)
            ->setRequestType($requestType)
            ->setStatus($successful ? Model::STATUS_SUCCESS : Model::STATUS_ERROR)
            ->setMessage($message !== null && trim($message) !== '' ? trim($message) : null);

        $this->mapper->save($log);
    }
}
```

- [ ] **Step 4: Run helper tests to verify they pass**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php
git commit -m "Add IB API response log helper [SA-21594]"
```

---

### Task 3: Candidate Payload Registration Error

**Files:**
- Modify: `library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php`
- Modify: `library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php`

- [ ] **Step 1: Add failing translator assertions**

Modify `testBuildsCompletePayload()` in `ModelToArrayTest.php` by adding this assertion after the `status` assertion:

```php
$this->assertNull($payload['registrationError']);
```

Add this test method before `createTranslator()`:

```php
public function testBuildsPayloadWithRegistrationError(): void
{
    $payload = $this->createTranslator()(
        $this->buildStudent()->setStatus(RegistrationSessionStudentModel::STATUS_FAILED),
        [
            'StudentBirthDate' => '2009-04-12',
            'StudentGender' => 'F',
        ],
        'Example School',
        'Candidate rejected by IBIS'
    );

    $this->assertSame('Candidate rejected by IBIS', $payload['registrationError']);
}
```

- [ ] **Step 2: Run translator tests to verify they fail**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php
```

Expected: FAIL because `registrationError` is missing and the translator currently accepts only three invoke arguments.

- [ ] **Step 3: Update translator signature and payload**

In `ModelToArray.php`, change:

```php
public function __invoke(Model $student, array $externalStudent, string $schoolName): array
```

to:

```php
public function __invoke(
    Model $student,
    array $externalStudent,
    string $schoolName,
    ?string $registrationError = null
): array {
```

Add this payload field immediately after `'status' => $student->getStatus(),`:

```php
'registrationError' => $registrationError,
```

- [ ] **Step 4: Run translator tests to verify they pass**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php
git commit -m "Return IB registration error in candidate payload [SA-21594]"
```

---

### Task 4: Admin Candidate List Read Path

**Files:**
- Modify: `library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php`
- Modify: `library/Schoolbox/Admin/InternationalBaccalaureate/Module.php`

- [ ] **Step 1: Inject the response log helper into the controller**

In `Controller.php`, add this use statement:

```php
use Schoolbox\IB\Registrations\Api\ResponseLog\Helper as RegistrationApiResponseLogHelper;
```

Add this constructor parameter after `RegistrationSessionStudentHelper $registrationSessionStudentHelper`:

```php
private RegistrationApiResponseLogHelper $registrationApiResponseLogHelper,
```

- [ ] **Step 2: Enrich candidate payloads with latest errors**

In `getRegistrationSessionStudents()`, after loading `$students`, add:

```php
$registrationErrorsByStudentId = $this->registrationApiResponseLogHelper
    ->getLatestErrorMessagesByStudentId($sessionId);
```

Change the translator call from:

```php
($this->registrationSessionStudentModelToArray)(
    $student,
    $this->findExternalStudent($student),
    $schoolName
)
```

to:

```php
($this->registrationSessionStudentModelToArray)(
    $student,
    $this->findExternalStudent($student),
    $schoolName,
    $student->getId() !== null ? ($registrationErrorsByStudentId[(int)$student->getId()] ?? null) : null
)
```

- [ ] **Step 3: Wire the helper in the module**

In `Module.php`, add these use statements:

```php
use Schoolbox\IB\Registrations\Api\ResponseLog\Helper as RegistrationApiResponseLogHelper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Mapper as RegistrationApiResponseLogMapper;
```

In `buildController()`, pass this new argument immediately after `$registrationSessionStudentHelper`:

```php
new RegistrationApiResponseLogHelper(
    $this->getMapperFactory()->get(RegistrationApiResponseLogMapper::class)
),
```

- [ ] **Step 4: Run syntax checks**

Run:

```bash
php -l library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php
php -l library/Schoolbox/Admin/InternationalBaccalaureate/Module.php
```

Expected: both commands print `No syntax errors detected`.

- [ ] **Step 5: Run focused translator/helper tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php library/Schoolbox/Admin/InternationalBaccalaureate/Module.php
git commit -m "Show IB registration errors in candidate list [SA-21594]"
```

---

### Task 5: CLI Write Path

**Files:**
- Modify: `library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php`

- [ ] **Step 1: Add response log dependencies**

In `RegisterCandidatesTool.php`, add these use statements:

```php
use Schoolbox\IB\Registrations\Api\ResponseLog\Helper as RegistrationApiResponseLogHelper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Mapper as RegistrationApiResponseLogMapper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Model as RegistrationApiResponseLogModel;
```

In `processPendingCandidates()`, after `$studentMapper`, add:

```php
/** @var RegistrationApiResponseLogMapper $responseLogMapper */
$responseLogMapper = $mapperFactory->get(RegistrationApiResponseLogMapper::class);
$responseLogHelper = new RegistrationApiResponseLogHelper($responseLogMapper);
```

- [ ] **Step 2: Thread helper into registration methods**

Add `RegistrationApiResponseLogHelper $responseLogHelper` to the calls and signatures for:

```php
registerCandidateNumber(...)
registerCandidateSession(...)
```

For `registerCandidateNumber`, place it before `$studentMapper`. For `registerCandidateSession`, place it before `$studentMapper`.

- [ ] **Step 3: Log candidate-number responses**

In `registerCandidateNumber()`, after:

```php
$request  = $clientFactory->buildRegisterCandidatesRequest([$candidate]);
$response = $client->registerCandidates($request);
```

the request message id is available via:

```php
$msgId = $request->getMsgId();
```

Inside the `foreach ($response->getCandidateResponses() as $candidateResponse)` loop, before mutating candidate status, add:

```php
try {
    $responseLogHelper->saveCandidateResponse($student, $msgId, $candidateResponse);
} catch (Throwable $e) {
    $this->getLogger()->error(
        sprintf(
            'Saving IBIS candidate registration response log failed for candidate #%s.',
            $student->getId() ?? 'unknown'
        ),
        ['exception' => $e]
    );
}
```

- [ ] **Step 4: Log candidate-number request exceptions after msgID exists**

Replace the current candidate request try/catch with this structure:

```php
$request = null;

try {
    $request  = $clientFactory->buildRegisterCandidatesRequest([$candidate]);
    $response = $client->registerCandidates($request);
} catch (Throwable $e) {
    if ($request !== null) {
        try {
            $responseLogHelper->saveRequestError(
                (int)$student->getRegistrationSession()->getId(),
                $student,
                $request->getMsgId(),
                RegistrationApiResponseLogModel::REQUEST_TYPE_REGISTER_CANDIDATES,
                $e
            );
        } catch (Throwable $logException) {
            $this->getLogger()->error(
                sprintf(
                    'Saving IBIS candidate registration request error log failed for candidate #%s.',
                    $student->getId() ?? 'unknown'
                ),
                ['exception' => $logException]
            );
        }
    }

    $this->getLogger()->error(
        sprintf(
            'IBIS candidate registration submission failed for candidate #%s.',
            $student->getId() ?? 'unknown'
        ),
        ['exception' => $e]
    );
    $this->markCandidateRegistrationFailed($student, $studentMapper);

    return false;
}
```

- [ ] **Step 5: Log session-registration responses**

In `registerCandidateSession()`, after:

```php
$sessionRequest = $clientFactory->buildRegisterSessionsRequest([$session]);
$sessionResponse = $client->registerSessions($sessionRequest);
```

the request message id is available via:

```php
$msgId = $sessionRequest->getMsgId();
```

Inside the `foreach ($sessionResponse->getSessionResponses() as $sessionResponseItem)` loop, before mutating candidate status, add:

```php
try {
    $responseLogHelper->saveSessionResponse($student, $msgId, $sessionResponseItem);
} catch (Throwable $e) {
    $this->getLogger()->error(
        sprintf(
            'Saving IBIS session registration response log failed for candidate #%s.',
            $student->getId() ?? 'unknown'
        ),
        ['exception' => $e]
    );
}
```

- [ ] **Step 6: Log session request exceptions after msgID exists**

Replace the current session request try/catch with this structure:

```php
$sessionRequest = null;

try {
    $session = $sessionBuilder->build($student, $candidateId, $schoolCode);
    $sessionRequest = $clientFactory->buildRegisterSessionsRequest([$session]);
    $sessionResponse = $client->registerSessions($sessionRequest);
} catch (Throwable $e) {
    if ($sessionRequest !== null) {
        try {
            $responseLogHelper->saveRequestError(
                (int)$student->getRegistrationSession()->getId(),
                $student,
                $sessionRequest->getMsgId(),
                RegistrationApiResponseLogModel::REQUEST_TYPE_REGISTER_SESSIONS,
                $e
            );
        } catch (Throwable $logException) {
            $this->getLogger()->error(
                sprintf(
                    'Saving IBIS session registration request error log failed for candidate #%s.',
                    $student->getId() ?? 'unknown'
                ),
                ['exception' => $logException]
            );
        }
    }

    $this->getLogger()->error(
        sprintf(
            'IBIS session registration submission failed for candidate #%s %s.',
            $student->getId() ?? 'unknown',
            $user?->getFullName() ?? 'unknown',
        ),
        ['exception' => $e]
    );
    $this->markCandidateRegistrationFailed($student, $studentMapper);

    return false;
}
```

- [ ] **Step 7: Run syntax check**

Run:

```bash
php -l library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 8: Run focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

Run:

```bash
git add library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php
git commit -m "Log IBIS registration API responses [SA-21594]"
```

---

### Task 6: Final Verification

**Files:**
- Verify changed PHP files and migration.

- [ ] **Step 1: Run PHPCS on changed PHP files**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php library/Schoolbox/Admin/InternationalBaccalaureate/Module.php library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php
```

Expected: PASS with no errors.

- [ ] **Step 2: Run PHPStan on changed production PHP files**

Run:

```bash
./vendor/bin/phpstan analyze library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php library/Schoolbox/Admin/InternationalBaccalaureate/Module.php library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php
```

Expected: PASS or only pre-existing baseline issues unrelated to the changed lines.

- [ ] **Step 3: Run focused PHPUnit tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php
```

Expected: PASS.

- [ ] **Step 4: Run full PHPUnit suite in Docker if available**

Run:

```bash
docker/ssh ./vendor/bin/phpunit
```

Expected: PASS. If Docker is unavailable, record the exact failure and run host-shell focused tests from Step 3.

- [ ] **Step 5: Review final diff**

Run:

```bash
git diff --stat HEAD
git diff HEAD -- updates/26.1.beta4/IB/04-addAPIErrorResponseLog.sql library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php library/Schoolbox/Admin/InternationalBaccalaureate/Module.php library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php
```

Expected: diff contains only the response log schema/helper, CLI logging, and candidate payload error changes.

- [ ] **Step 6: Commit final verification fixes if any**

If verification required formatting or small fixes, run:

```bash
git add library/Schoolbox/IB/Registrations/Api/ResponseLog/Model.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Mapper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Helper.php library/Schoolbox/IB/Registrations/Api/ResponseLog/Test/HelperTest.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/ModelToArray.php library/Schoolbox/IB/Registrations/Sessions/Student/Translator/Test/ModelToArrayTest.php library/Schoolbox/Admin/InternationalBaccalaureate/Controller.php library/Schoolbox/Admin/InternationalBaccalaureate/Module.php library/Schoolbox/Admin/InternationalBaccalaureate/Cli/RegisterCandidatesTool.php
git commit -m "Verify IB API response log helper [SA-21594]"
```

Expected: only run this commit when the previous verification steps caused additional code changes.

