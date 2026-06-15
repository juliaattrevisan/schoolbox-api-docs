<?php

/**
 * IBIS register candidates candidate builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Alaress_Date_Format;
use Exception;
use InvalidArgumentException;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;

class RegisterCandidatesCandidateBuilder
{
    private const array REQUIRED_FIELDS = [
        'date_of_birth',
        'firstname',
        'lastname',
        'email',
        'gender',
        'language1',
        'nationality1',
        'reference_code',
    ];

    /**
     * @param array<string, mixed> $externalStudent
     * @return array<string, string|null>
     */
    public function build(RegistrationSessionStudentModel $student, array $externalStudent): array
    {
        $user = $student->getUser();

        if ($user === null) {
            throw new InvalidArgumentException('Selected candidate is missing required IBIS data.');
        }

        $gender = $this->stringOrNull($externalStudent['StudentGender'] ?? null);
        $languageCodes = $this->extractCollectionCodes($user->getLanguageCollection());
        $nationalityCodes = $this->extractCollectionCodes($user->getNationalityCollection());

        $candidate = [
            'date_of_birth' => $this->formatDateOfBirth($externalStudent['StudentBirthDate'] ?? null),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'gender' => $gender === null ? null : strtoupper($gender),
            'language1' => $languageCodes[0] ?? null,
            'language2' => $languageCodes[1] ?? null,
            'nationality1' => $nationalityCodes[0] ?? null,
            'nationality2' => $nationalityCodes[1] ?? null,
            'reference_code' => $user->getId() === null ? null : (string) $user->getId(),
        ];

        $missingFields = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if ($candidate[$field] === null || $candidate[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new InvalidArgumentException(
                'Selected candidate is missing required IBIS data: ' . implode(', ', $missingFields) . '.'
            );
        }

        return $candidate;
    }

    private function formatDateOfBirth(mixed $dateOfBirth): ?string
    {
        $dateOfBirth = $this->stringOrNull($dateOfBirth);

        if ($dateOfBirth === null) {
            return null;
        }

        try {
            return strtoupper(Alaress_Date_Format::newFromTimestamp((int)$dateOfBirth)->getUsingFormat('d-M-Y'));
        } catch (Exception) {
            throw new InvalidArgumentException('Selected candidate is missing required IBIS data.');
        }
    }

    /**
     * @param mixed $collection
     * @return string[]
     */
    private function extractCollectionCodes(mixed $collection): array
    {
        if (!is_iterable($collection)) {
            return [];
        }

        $codes = [];

        foreach ($collection as $item) {
            if (!is_object($item) || !method_exists($item, 'getCode')) {
                continue;
            }

            $code = $this->stringOrNull($item->getCode());

            if ($code !== null) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
