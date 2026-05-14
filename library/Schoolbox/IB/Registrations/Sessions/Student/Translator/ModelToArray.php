<?php

/**
 * IB registration session student translator
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Translator;

use Schoolbox\IB\Registrations\Sessions\Student\Helper;
use Schoolbox\IB\Registrations\Sessions\Student\Model;

class ModelToArray
{
    public function __construct(private Helper $helper)
    {
    }

    /**
     * @param array<string, mixed> $externalStudent
     * @return array<string, mixed>
     */
    public function __invoke(Model $student, array $externalStudent, string $schoolName): array
    {
        $user = $student->getUser();

        $firstName = $this->helper->normalise($user?->getFirstname());
        $lastName = $this->helper->normalise($user?->getLastname());
        $languageCodes = $this->helper->extractCodes($user?->getLanguageCollection());
        $nationalityCodes = $this->helper->extractCodes($user?->getNationalityCollection());
        $dateOfBirth = $this->helper->formatDateOfBirth($externalStudent['StudentBirthDate'] ?? null);
        $userName = $this->helper->buildUserName($firstName, $lastName);

        $requiredDetails = [
            'userName' => $userName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'dateOfBirth' => $dateOfBirth,
            'email' => $this->helper->normalise($user?->getEmail()),
            'gender' => $this->helper->normalise($externalStudent['StudentGender'] ?? null),
            'language1' => $languageCodes[0] ?? '',
            'nationality1' => $nationalityCodes[0] ?? '',
            'schoolName' => $this->helper->normalise($schoolName),
        ];
        $missingFields = $this->helper->findMissingFields($requiredDetails);

        return [
            'userId' => $user?->getId(),
            'registrationSessionStudentId' => $student->getId(),
            'status' => $student->getStatus(),
            'userName' => $userName,
            'hasMissingDetails' => $missingFields !== [],
            'missingFields' => $missingFields,
            'ibGroupSubjects' => $this->helper->buildIbGroupSubjects($student->getIbGroupSubjects()),
        ];
    }
}
