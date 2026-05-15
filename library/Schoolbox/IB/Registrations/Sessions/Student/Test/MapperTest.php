<?php

/**
 * IB registration session student mapper test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as SubjectLanguageMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Model as SubjectLanguageModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Resource\Folder\Code\Model as FolderCodeModel;

class MapperTest extends TestCase
{
    public function testSubjectLanguageMapperProvidesBatchedLookupMethod(): void
    {
        // @phpstan-ignore-next-line - This contract test intentionally asserts the public mapper API exists.
        $this->assertTrue(method_exists(
            SubjectLanguageMapper::class,
            'findByRegistrationSessionStudentIds'
        ));
    }

    public function testApplySubjectLanguageOverridesReplacesDefaultLanguagesForPersistedStudents(): void
    {
        $student = (new RegistrationSessionStudentModel())
            ->setId(101)
            ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [
                $this->createGroupSubject(201, [301, 302]),
            ]));
        $overrideLanguage = (new LanguageModel())->setId(401);
        $override = (new SubjectLanguageModel())
            ->setGroupSubject((new GroupSubjectModel())->setId(201))
            ->setLanguage($overrideLanguage);
        $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('findByRegistrationSessionStudentIds')
            ->with([101])
            ->willReturn([
                101 => new GenericCollection(SubjectLanguageModel::class, [$override]),
            ]);

        $this->applyOverridesWithSubjectLanguageMapper($subjectLanguageMapper, new GenericCollection(
            RegistrationSessionStudentModel::class,
            [$student]
        ));

        $languages = $this->findGroupSubjectById($student, 201)->getLanguages();
        $this->assertCount(1, $languages);
        $this->assertSame($overrideLanguage, $languages[0]->getLanguage());
    }

    public function testApplySubjectLanguageOverridesKeepsDefaultsWithoutMatchingOverrides(): void
    {
        $matchingSubject = $this->createGroupSubject(201, [301, 302]);
        $unmatchedSubject = $this->createGroupSubject(202, [303]);
        $student = (new RegistrationSessionStudentModel())
            ->setId(101)
            ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [
                $matchingSubject,
                $unmatchedSubject,
            ]));
        $override = (new SubjectLanguageModel())
            ->setGroupSubject((new GroupSubjectModel())->setId(203))
            ->setLanguage((new LanguageModel())->setId(401));
        $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('findByRegistrationSessionStudentIds')
            ->with([101])
            ->willReturn([
                101 => new GenericCollection(SubjectLanguageModel::class, [$override]),
            ]);

        $this->applyOverridesWithSubjectLanguageMapper($subjectLanguageMapper, new GenericCollection(
            RegistrationSessionStudentModel::class,
            [$student]
        ));

        $this->assertSame($matchingSubject->getLanguages(), $this->findGroupSubjectById($student, 201)->getLanguages());
        $this->assertSame($unmatchedSubject->getLanguages(), $this->findGroupSubjectById($student, 202)->getLanguages());
    }

    public function testApplySubjectLanguageOverridesOnlyLooksUpPersistedStudents(): void
    {
        $persistedStudent = (new RegistrationSessionStudentModel())
            ->setId(101)
            ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [
                $this->createGroupSubject(201, [301]),
            ]));
        $derivedStudent = (new RegistrationSessionStudentModel())
            ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [
                $this->createGroupSubject(202, [302]),
            ]));
        $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('findByRegistrationSessionStudentIds')
            ->with([101])
            ->willReturn([]);

        $this->applyOverridesWithSubjectLanguageMapper($subjectLanguageMapper, new GenericCollection(
            RegistrationSessionStudentModel::class,
            [
            $persistedStudent,
            $derivedStudent,
            ]
        ));

        $derivedLanguages = $this->findGroupSubjectById($derivedStudent, 202)->getLanguages();
        $this->assertCount(1, $derivedLanguages);
        $this->assertSame(302, $derivedLanguages[0]->getLanguage()->getId());
    }

    public function testApplySubjectLanguageOverridesMatchesReindexedSubjectsByModelId(): void
    {
        $subjectAtCollidingOffset = $this->createGroupSubject(200, [300]);
        $matchingSubject = $this->createGroupSubject(1, [301]);
        $student = (new RegistrationSessionStudentModel())
            ->setId(101)
            ->setIbGroupSubjects(new GenericCollection(GroupSubjectModel::class, [
                $this->createGroupSubject(100, [299]),
                $subjectAtCollidingOffset,
                $matchingSubject,
            ]));
        $overrideLanguage = (new LanguageModel())->setId(401);
        $override = (new SubjectLanguageModel())
            ->setGroupSubject((new GroupSubjectModel())->setId(1))
            ->setLanguage($overrideLanguage);
        $subjectLanguageMapper = $this->createMock(SubjectLanguageMapper::class);
        $subjectLanguageMapper
            ->expects($this->once())
            ->method('findByRegistrationSessionStudentIds')
            ->with([101])
            ->willReturn([
                101 => new GenericCollection(SubjectLanguageModel::class, [$override]),
            ]);

        $this->applyOverridesWithSubjectLanguageMapper($subjectLanguageMapper, new GenericCollection(
            RegistrationSessionStudentModel::class,
            [$student]
        ));

        $this->assertSame(
            $subjectAtCollidingOffset->getLanguages(),
            $this->findGroupSubjectById($student, 200)->getLanguages()
        );
        $languages = $this->findGroupSubjectById($student, 1)->getLanguages();
        $this->assertCount(1, $languages);
        $this->assertSame($overrideLanguage, $languages[0]->getLanguage());
    }

    /**
     * @param GenericCollection|RegistrationSessionStudentModel[] $students
     */
    private function applyOverridesWithSubjectLanguageMapper(
        SubjectLanguageMapper $subjectLanguageMapper,
        GenericCollection $students
    ): void {
        $mapper = new class ($subjectLanguageMapper) extends Mapper {
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

        $mapper->applyOverrides($students);
    }

    /**
     * @param int[] $languageIds
     */
    private function createGroupSubject(int $groupSubjectId, array $languageIds): GroupSubjectModel
    {
        $groupSubject = (new GroupSubjectModel())->setId($groupSubjectId);
        $languages = new GenericCollection(GroupSubjectLanguageModel::class);

        foreach ($languageIds as $languageId) {
            $languages->add((new GroupSubjectLanguageModel())
                ->setGroupSubject($groupSubject)
                ->setLanguage((new LanguageModel())->setId($languageId))
                ->setClassCode(new GenericCollection(FolderCodeModel::class)));
        }

        return $groupSubject->setLanguages($languages);
    }

    private function findGroupSubjectById(
        RegistrationSessionStudentModel $student,
        int $groupSubjectId
    ): GroupSubjectModel {
        foreach ($student->getIbGroupSubjects() as $groupSubject) {
            if ($groupSubject->getId() === $groupSubjectId) {
                return $groupSubject;
            }
        }

        $this->fail("Group subject {$groupSubjectId} was not found.");
    }
}
