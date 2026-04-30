<?php

/**
 * IB registration session student payload builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;

class Payload
{
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

        $payload = [
            'userId' => $user?->getId(),
            'registrationSessionStudentId' => $student->getId(),
            'status' => $student->getStatus(),
            'userName' => $this->buildUserName($firstName, $lastName),
            'firstName' => $firstName,
            'lastName' => $lastName,
            'dateOfBirth' => $this->formatDateOfBirth($externalStudent['StudentBirthDate'] ?? null),
            'email' => $this->normalise($user?->getEmail()),
            'gender' => $this->normalise($externalStudent['StudentGender'] ?? null),
            'language1' => $languageCodes[0] ?? '',
            'language2' => $languageCodes[1] ?? '',
            'nationality1' => $nationalityCodes[0] ?? '',
            'nationality2' => $nationalityCodes[1] ?? '',
            'schoolName' => $this->normalise($schoolName),
            'reference' => '',
        ];

        $missingFields = $this->findMissingFields($payload);
        $payload['isComplete'] = empty($missingFields);
        $payload['missingFields'] = $missingFields;

        return $payload;
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
     * @param array<string, mixed> $payload
     * @return string[]
     */
    private function findMissingFields(array $payload): array
    {
        return array_values(
            array_filter(
                self::REQUIRED_FIELDS,
                fn(string $field): bool => $this->normalise($payload[$field] ?? '') === ''
            )
        );
    }
}
