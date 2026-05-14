<?php

/**
 * IB registration session student CSV export builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Export;

use Schoolbox\IB\Registrations\Sessions\Student\Model;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;

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
            'nationality1' => $this->firstNationalityName($user?->getNationalityCollection()),
            'language1' => $this->firstLanguageName($user?->getLanguageCollection()),
        ];
    }

    private function normalise(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function formatDateOfBirth(mixed $value): string
    {
        if ($value === null || $value === false || !is_scalar($value)) {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if ($timestamp <= 0) {
                return '';
            }

            return date('Y-m-d', $timestamp);
        }

        $dateOfBirth = trim((string) $value);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateOfBirth, $matches)) {
            return '';
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @param iterable<mixed>|null $nationalities
     */
    private function firstNationalityName(?iterable $nationalities): string
    {
        foreach ($nationalities ?? [] as $nationality) {
            if ($nationality instanceof NationalityModel) {
                return $this->normalise($nationality->getName());
            }
        }

        return '';
    }

    /**
     * @param iterable<mixed>|null $languages
     */
    private function firstLanguageName(?iterable $languages): string
    {
        foreach ($languages ?? [] as $language) {
            if ($language instanceof LanguageModel) {
                return $this->normalise($language->getName());
            }
        }

        return '';
    }
}
