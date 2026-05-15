<?php

/**
 * IB registration session student CSV export builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Export;

use Alaress_Date_Format;
use Schoolbox\IB\Registrations\Sessions\Student\Model;

class Csv
{
    /**
     * @return array<string, string>
     */
    public function headings(): array
    {
        return [
            'firstName' => 'First Name',
            'lastName' => 'Last Name',
            'dateOfBirth' => 'Date of Birth',
            'gender' => 'Gender',
            'nationality1' => '1st Nationality',
            'language1' => '1st Language',
        ];
    }

    /**
     * @param array<string, mixed> $externalStudent
     * @return array<string, string>
     */
    public function buildRow(Model $student, array $externalStudent): array
    {
        $user = $student->getUser();

        return [
            'firstName' => $this->normalise($user?->getGivenName()),
            'lastName' => $this->normalise($user?->getLastname()),
            'dateOfBirth' => $this->formatDateOfBirth($externalStudent['StudentBirthDate'] ?? null),
            'gender' => $this->normalise($externalStudent['StudentGender'] ?? null),
            'nationality1' => $this->normalise($user?->getNationalityCollection()->first()?->getName()),
            'language1' => $this->normalise($user?->getLanguageCollection()->first()?->getName()),
        ];
    }

    private function normalise(mixed $value): string
    {
        return trim((string) ($value ?? ''));
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

            return Alaress_Date_Format::newFromTimestamp($timestamp)->getDatabaseDate();
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return '';
        }

        return Alaress_Date_Format::newFromTimestamp($timestamp)->getDatabaseDate();
    }
}
