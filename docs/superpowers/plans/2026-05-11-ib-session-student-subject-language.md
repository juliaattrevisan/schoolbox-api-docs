# IB Session Student Subject Language Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Return per-student IB subject language overrides in the registration session candidates payload, falling back to default subject languages when no override exists.

**Architecture:** Keep subjects in the initial candidates response because the page needs them on load. Build default matched subjects as today, then perform one batched override lookup for persisted session-student IDs and replace each matching subject's language collection in memory.

**Tech Stack:** PHP 8.4, Schoolbox Mapper models, PHPUnit.

---

## File Structure

- Modify `library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php`: add a batched lookup method returning override models grouped by registration session student ID.
- Modify `library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php`: after default subject matching, apply subject language overrides to the effective `GroupSubjectModel` collections.
- Modify `library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php`: cover override replacement, fallback defaults, derived students without IDs, and stale overrides for unmatched subjects.

## Task 1: Add Batched Override Lookup

**Files:**
- Modify: `library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php`

- [ ] **Step 1: Add a failing method contract test**

In `library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php`, ensure this import exists:

```php
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as SubjectLanguageMapper;
```

Add this test to the class:

```php
public function testSubjectLanguageMapperProvidesBatchedLookupMethod(): void
{
    $this->assertTrue(method_exists(
        SubjectLanguageMapper::class,
        'findByRegistrationSessionStudentIds'
    ));
}
```

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
```

Expected: FAIL because `findByRegistrationSessionStudentIds()` does not exist on `SubjectLanguageMapper`.

- [ ] **Step 2: Implement the lookup method**

In `library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php`, add the `Predicate` import and replace unused imports if present:

```php
use Alaress\Database\Predicate;
use PDO;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Mapper as GroupSubjectMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Mapper as BaseMapper;
```

Then add this method to the class:

```php
/**
 * @param int[] $registrationSessionStudentIds
 * @return array<int, GenericCollection|Model[]>
 * @throws BaseMapper\DefinitionException
 * @throws BaseMapper\Exception
 */
public function findByRegistrationSessionStudentIds(array $registrationSessionStudentIds): array
{
    $registrationSessionStudentIds = array_values(array_unique(array_filter(
        array_map('intval', $registrationSessionStudentIds),
        static fn(int $id): bool => $id > 0
    )));

    if ($registrationSessionStudentIds === []) {
        return [];
    }

    $bindings = Predicate\Builder::getBindings($registrationSessionStudentIds, 'registrationSessionStudentId');
    $statement = $this->getDatabase()->query(
        "
            SELECT
                {$this->getColumnString('ssl')},
                {$this->getJoinColumnString('registrationSessionStudent', 'rss')},
                {$this->getJoinColumnString('groupSubject', 'gs')},
                {$this->getJoinColumnString('language', 'l')}
            FROM {$this->getTable()} ssl
            INNER JOIN {$this->getJoinSql('registrationSessionStudent', 'ssl', 'rss')}
            INNER JOIN {$this->getJoinSql('groupSubject', 'ssl', 'gs')}
            INNER JOIN {$this->getJoinSql('language', 'ssl', 'l')}
            WHERE ssl.registration_session_student_id IN {$bindings->getPlaceholder()}
            ORDER BY ssl.registration_session_student_id ASC, ssl.group_subject_id ASC
        ",
        $bindings->getParams()
    );

    /** @var RegistrationSessionStudentMapper $registrationSessionStudentMapper */
    $registrationSessionStudentMapper = $this->getJoinMapper('registrationSessionStudent');
    /** @var GroupSubjectMapper $groupSubjectMapper */
    $groupSubjectMapper = $this->getJoinMapper('groupSubject');
    /** @var LanguageMapper $languageMapper */
    $languageMapper = $this->getJoinMapper('language');

    $selectionsByStudentId = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $selection = $this->hydrate($row, 'ssl');

        if (!$selection) {
            continue;
        }

        $registrationSessionStudent = $registrationSessionStudentMapper->hydrate($row, 'rss');
        $groupSubject = $groupSubjectMapper->hydrate($row, 'gs');
        $language = $languageMapper->hydrate($row, 'l');

        if (!$registrationSessionStudent?->getId() || !$groupSubject?->getId() || !$language?->getId()) {
            continue;
        }

        $selection
            ->setRegistrationSessionStudent($registrationSessionStudent)
            ->setGroupSubject($groupSubject)
            ->setLanguage($language);

        $studentId = $registrationSessionStudent->getId();

        if (!isset($selectionsByStudentId[$studentId])) {
            $selectionsByStudentId[$studentId] = new GenericCollection(Model::class);
        }

        $selectionsByStudentId[$studentId]->add($selection);
    }

    return $selectionsByStudentId;
}
```

- [ ] **Step 3: Run the focused test**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
```

Expected: the missing-method failure is gone. Remaining failures should point to student mapper override application not being implemented yet.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
git commit -m "Add IB student subject language lookup [SA-22262]"
```

## Task 2: Apply Overrides To Effective Subject Languages

**Files:**
- Modify: `library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php`
- Modify: `library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php`

- [ ] **Step 1: Write failing override tests**

In `library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php`, replace any selection-storage test with tests for effective language replacement. Add these imports if they are missing:

```php
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as SubjectLanguageMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Model as SubjectLanguageModel;
```

Add this test:

```php
public function testApplySubjectLanguageOverridesReplacesDefaultLanguagesForPersistedStudents(): void
{
    $defaultLanguage = (new GroupSubjectLanguageModel())
        ->setId(654)
        ->setLanguage((new LanguageModel())->setId(9)->setCode('FRA')->setName('French'))
        ->setClassCode('FRE-HL');
    $groupSubject = (new GroupSubjectModel())
        ->setId(321)
        ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class, [$defaultLanguage]));
    $selection = (new SubjectLanguageModel())
        ->setRegistrationSessionStudent((new RegistrationSessionStudentModel())->setId(456))
        ->setGroupSubject((new GroupSubjectModel())->setId(321))
        ->setLanguage((new LanguageModel())->setId(10)->setCode('SPA')->setName('Spanish'));

    $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
    $subjectLanguageMapper
        ->expects($this->once())
        ->method('findByRegistrationSessionStudentIds')
        ->with([456])
        ->willReturn([
            456 => new GenericCollection(SubjectLanguageModel::class, [$selection]),
        ]);

    $mapper = $this->buildMapperWithSubjectLanguageMapper($subjectLanguageMapper);
    $student = (new RegistrationSessionStudentModel())
        ->setId(456)
        ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [$groupSubject]));
    $students = new GenericCollection(RegistrationSessionStudentModel::class, [$student]);

    $mapper->applyOverrides($students);

    $languages = $groupSubject->getLanguages();
    $this->assertCount(1, $languages);

    /** @var GroupSubjectLanguageModel $effectiveLanguage */
    $effectiveLanguage = $languages->first();
    $this->assertSame(10, $effectiveLanguage->getLanguage()?->getId());
    $this->assertSame('Spanish', $effectiveLanguage->getLanguage()?->getName());
    $this->assertCount(0, $effectiveLanguage->getClassCode());
}
```

Add this test:

```php
public function testApplySubjectLanguageOverridesKeepsDefaultsWithoutMatchingOverrides(): void
{
    $defaultLanguage = (new GroupSubjectLanguageModel())
        ->setId(654)
        ->setLanguage((new LanguageModel())->setId(9)->setCode('FRA')->setName('French'))
        ->setClassCode('FRE-HL');
    $groupSubject = (new GroupSubjectModel())
        ->setId(321)
        ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class, [$defaultLanguage]));
    $selection = (new SubjectLanguageModel())
        ->setRegistrationSessionStudent((new RegistrationSessionStudentModel())->setId(456))
        ->setGroupSubject((new GroupSubjectModel())->setId(999))
        ->setLanguage((new LanguageModel())->setId(10)->setCode('SPA')->setName('Spanish'));

    $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
    $subjectLanguageMapper
        ->expects($this->once())
        ->method('findByRegistrationSessionStudentIds')
        ->with([456])
        ->willReturn([
            456 => new GenericCollection(SubjectLanguageModel::class, [$selection]),
        ]);

    $mapper = $this->buildMapperWithSubjectLanguageMapper($subjectLanguageMapper);
    $student = (new RegistrationSessionStudentModel())
        ->setId(456)
        ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [$groupSubject]));
    $students = new GenericCollection(RegistrationSessionStudentModel::class, [$student]);

    $mapper->applyOverrides($students);

    $languages = $groupSubject->getLanguages();
    $this->assertCount(1, $languages);
    $this->assertSame($defaultLanguage, $languages->first());
}
```

Add this test:

```php
public function testApplySubjectLanguageOverridesOnlyLooksUpPersistedStudents(): void
{
    $persistedSubject = (new GroupSubjectModel())
        ->setId(321)
        ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class));
    $derivedSubject = (new GroupSubjectModel())
        ->setId(321)
        ->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class));
    $selection = (new SubjectLanguageModel())
        ->setRegistrationSessionStudent((new RegistrationSessionStudentModel())->setId(456))
        ->setGroupSubject((new GroupSubjectModel())->setId(321))
        ->setLanguage((new LanguageModel())->setId(10)->setCode('SPA')->setName('Spanish'));

    $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
    $subjectLanguageMapper
        ->expects($this->once())
        ->method('findByRegistrationSessionStudentIds')
        ->with([456])
        ->willReturn([
            456 => new GenericCollection(SubjectLanguageModel::class, [$selection]),
        ]);

    $mapper = $this->buildMapperWithSubjectLanguageMapper($subjectLanguageMapper);
    $persistedStudent = (new RegistrationSessionStudentModel())
        ->setId(456)
        ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [$persistedSubject]));
    $derivedStudent = (new RegistrationSessionStudentModel())
        ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [$derivedSubject]));
    $students = new GenericCollection(RegistrationSessionStudentModel::class, [$persistedStudent, $derivedStudent]);

    $mapper->applyOverrides($students);

    $this->assertCount(1, $persistedSubject->getLanguages());
    $this->assertCount(0, $derivedSubject->getLanguages());
}
```

Add this helper to the test class:

```php
private function buildMapperWithSubjectLanguageMapper(SubjectLanguageMapper $subjectLanguageMapper): Mapper
{
    return new class ($subjectLanguageMapper) extends Mapper {
        public function __construct(private SubjectLanguageMapper $subjectLanguageMapper)
        {
            parent::__construct();
        }

        public function applyOverrides(GenericCollection $students): void
        {
            $this->applySubjectLanguageOverrides($students);
        }

        protected function getSubjectLanguageMapper(): SubjectLanguageMapper
        {
            return $this->subjectLanguageMapper;
        }
    };
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
```

Expected: FAIL because `applySubjectLanguageOverrides()` and `getSubjectLanguageMapper()` do not exist.

- [ ] **Step 3: Implement override application**

In `library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php`, add imports:

```php
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as SubjectLanguageMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Model as SubjectLanguageModel;
```

Call the override application after the `$students` collection is assembled in `findStudentsForSession()`:

```php
$this->applySubjectLanguageOverrides($students);

return $students;
```

Add these methods to the class:

```php
protected function getSubjectLanguageMapper(): SubjectLanguageMapper
{
    return SubjectLanguageMapper::getInstance();
}

/**
 * @param GenericCollection|Model[] $students
 */
protected function applySubjectLanguageOverrides(GenericCollection $students): void
{
    $studentIds = [];

    foreach ($students as $student) {
        if (!$student instanceof Model || !$student->getId()) {
            continue;
        }

        $studentIds[] = $student->getId();
    }

    if ($studentIds === []) {
        return;
    }

    $selectionsByStudentId = $this->getSubjectLanguageMapper()
        ->findByRegistrationSessionStudentIds($studentIds);

    foreach ($students as $student) {
        if (!$student instanceof Model || !$student->getId()) {
            continue;
        }

        foreach ($selectionsByStudentId[$student->getId()] ?? [] as $selection) {
            if ($selection instanceof SubjectLanguageModel) {
                $this->applySubjectLanguageOverride($student, $selection);
            }
        }
    }
}

private function applySubjectLanguageOverride(Model $student, SubjectLanguageModel $selection): void
{
    $groupSubjectId = $selection->getGroupSubject()?->getId();
    $language = $selection->getLanguage();

    if (!$groupSubjectId || !$language?->getId()) {
        return;
    }

    $groupSubjects = $student->getIbGroupSubjects();

    if (!$groupSubjects->offsetExists($groupSubjectId)) {
        return;
    }

    /** @var GroupSubjectModel $groupSubject */
    $groupSubject = $groupSubjects[$groupSubjectId];
    $groupSubject->setLanguages(new GenericCollection(GroupSubjectLanguageModel::class, [
        (new GroupSubjectLanguageModel())
            ->setGroupSubject($groupSubject)
            ->setLanguage($language)
            ->setClassCode(new GenericCollection(FolderCodeModel::class)),
    ]));
}
```

- [ ] **Step 4: Run focused PHP tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/PayloadTest.php
```

Expected: PASS.

- [ ] **Step 5: Run static checks for changed PHP files**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
./vendor/bin/phpstan analyze library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
```

Expected: PASS with no new errors. If PHPCS reports formatting errors, run:

```bash
./vendor/bin/phpcbf library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
```

then repeat PHPCS.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
git commit -m "Apply IB subject language overrides [SA-22262]"
```

## Task 3: Final Verification

**Files:**
- Verify only; no planned file modifications.

- [ ] **Step 1: Run backend verification inside Docker if available**

Run:

```bash
docker/ssh
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
./vendor/bin/phpunit library/Schoolbox/IB/Registrations/Sessions/Student/Test/PayloadTest.php
./vendor/bin/phpcs library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
./vendor/bin/phpstan analyze library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
```

Expected: all commands pass. If Docker is unavailable, record that final authoritative verification could not be completed and include the host-shell verification output in the handoff.

- [ ] **Step 2: Inspect final diff**

Run:

```bash
git status --short
git diff --stat HEAD~2..HEAD
```

Expected: changes are limited to the subject-language lookup, student mapper override application, and focused tests.

- [ ] **Step 3: Commit any verification-only fixes**

If Task 3 required formatting or static-analysis fixes, commit them:

```bash
git add library/Schoolbox/IB/Registrations/Sessions/Student/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Subject/Language/Mapper.php library/Schoolbox/IB/Registrations/Sessions/Student/Test/MapperTest.php
git commit -m "Fix IB subject language override checks [SA-22262]"
```

If no fixes were needed, do not create a commit.
