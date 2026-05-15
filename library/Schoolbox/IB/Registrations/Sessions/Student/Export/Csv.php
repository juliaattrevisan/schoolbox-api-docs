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
    private const string CSV_FORMULA_PREFIX_PATTERN = '/^[=+\-@]/';

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

    /**
     * Trim exported values and neutralise spreadsheet formula prefixes so
     * user-controlled CSV cells are treated as plain text when opened in
     * spreadsheet applications.
     */
    private function normalise(mixed $value): string
    {
        $normalisedValue = trim((string) ($value ?? ''));

        if ($normalisedValue === '') {
            return '';
        }

        if (
            preg_match(self::CSV_FORMULA_PREFIX_PATTERN, $normalisedValue) === 1
            || in_array($normalisedValue[0], ["\t", "\n", "\r"], true)
        ) {
            // Prefix with an apostrophe so values such as =SUM(...) are
            // exported as literal text instead of executable formulas.
            return "'" . $normalisedValue;
        }

        return $normalisedValue;
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
