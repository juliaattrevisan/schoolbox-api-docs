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
        if (!is_scalar($value) || !is_numeric($value)) {
            return '';
        }

        if ((int) $value <= 0) {
            return '';
        }

        return Alaress_Date_Format::newFromTimestamp((int) $value)->getDatabaseDate();
    }
}
