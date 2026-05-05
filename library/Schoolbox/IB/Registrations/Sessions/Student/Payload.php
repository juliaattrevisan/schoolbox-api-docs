<?php

/**
 * IB registration session student payload builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;

class Payload
{
    private const LEVEL_HL = 'HL';
    private const LEVEL_SL = 'SL';
    private const LEVEL_CORE = 'Core';

    private const REQUIRED_FIELDS = [
        'userName',
        'firstName',
        'lastName',
        'dateOfBirth',
        'email',
        'gender',
        'language1',
        'nationality1',
        'schoolName',
    ];

    /**
     * @param array<string, mixed> $externalStudent
     * @return array<string, mixed>
     */
    public function build(Model $student, array $externalStudent, string $schoolName): array
    {
        $user = $student->getUser();

        $firstName = $this->normalise($user?->getFirstname());
        $lastName = $this->normalise($user?->getLastname());
        $languageCodes = $this->extractCodes($user?->getLanguageCollection());
        $nationalityCodes = $this->extractCodes($user?->getNationalityCollection());
        $dateOfBirth = $this->formatDateOfBirth($externalStudent['StudentBirthDate'] ?? null);
        $userName = $this->buildUserName($firstName, $lastName);

        $requiredDetails = [
            'userName' => $userName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'dateOfBirth' => $dateOfBirth,
            'email' => $this->normalise($user?->getEmail()),
            'gender' => $this->normalise($externalStudent['StudentGender'] ?? null),
            'language1' => $languageCodes[0] ?? '',
            'nationality1' => $nationalityCodes[0] ?? '',
            'schoolName' => $this->normalise($schoolName),
        ];
        $missingFields = $this->findMissingFields($requiredDetails);

        return [
            'userId' => $user?->getId(),
            'registrationSessionStudentId' => $student->getId(),
            'status' => $student->getStatus(),
            'userName' => $userName,
            'hasMissingDetails' => $missingFields !== [],
            'missingFields' => $missingFields,
            'ibGroupSubjects' => $this->buildIbGroupSubjects($student->getIbGroupSubjects()),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @return object
     */
    public function groupByStatus(array $students): object
    {
        $groups = [];

        foreach ($students as $student) {
            $status = (string) (int) ($student['status'] ?? Model::STATUS_PREPARATION);
            $groups[$status][] = $student;
        }

        return (object) $groups;
    }

    /**
     * @return array<int, array{value: int, key: string, label: string}>
     */
    public function getStatuses(): array
    {
        return Model::STATUSES;
    }

    private function buildUserName(string $firstName, string $lastName): string
    {
        if ($firstName === '' || $lastName === '') {
            return '';
        }

        return "{$firstName}, {$lastName}";
    }

    /**
     * @param iterable<mixed>|null $models
     * @return string[]
     */
    private function extractCodes(?iterable $models): array
    {
        $codes = [];

        foreach ($models ?? [] as $model) {
            if (!$model instanceof LanguageModel && !$model instanceof NationalityModel) {
                continue;
            }

            $code = $this->normalise($model->getCode());

            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function formatDateOfBirth(mixed $value): string
    {
        if ($value === null || $value === false || trim((string) $value) === '') {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if ($timestamp <= 0) {
                return '';
            }

            return date('Y-m-d', $timestamp);
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalise(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /**
     * @param GenericCollection|GroupSubjectModel[] $groupSubjects
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildIbGroupSubjects(GenericCollection $groupSubjects): array
    {
        $subjectsByLevel = $groupSubjects->groupByCallback(
            fn(GroupSubjectModel $groupSubject): string => $this->getGroupSubjectLevel($groupSubject)
        );
        $payload = [];

        foreach ([self::LEVEL_HL, self::LEVEL_SL, self::LEVEL_CORE] as $level) {
            $payload[$level] = ($subjectsByLevel[$level] ?? new GenericCollection(GroupSubjectModel::class))
                ->map(fn(GroupSubjectModel $groupSubject): array => $this->buildIbGroupSubject($groupSubject))
                ->reindex()
                ->getAll();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIbGroupSubject(GroupSubjectModel $groupSubject): array
    {
        return [
            'id' => $groupSubject->getId(),
            'groupId' => $groupSubject->getGroupId(),
            'subject' => $groupSubject->getSubject()?->getName() ?? '',
            'level' => $this->getGroupSubjectLevel($groupSubject),
            'languages' => $groupSubject->getLanguages()
                ->map(
                    fn(GroupSubjectLanguageModel $language): array => [
                        'languageId' => $language->getLanguage()?->getId(),
                        'languageName' => $language->getLanguage()?->getName() ?? '',
                        'classCode' => $language->getClassCode() ?? '',
                    ]
                )
                ->reindex()
                ->getAll(),
        ];
    }

    private function getGroupSubjectLevel(GroupSubjectModel $groupSubject): string
    {
        return $groupSubject->getLevel() ?: self::LEVEL_CORE;
    }

    /**
     * @param array<string, mixed> $details
     * @return string[]
     */
    private function findMissingFields(array $details): array
    {
        $missingFields = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if ($this->normalise($details[$field] ?? '') === '') {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }
}
