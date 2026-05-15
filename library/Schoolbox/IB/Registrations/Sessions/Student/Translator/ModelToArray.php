<?php

/**
 * IB registration session student translator
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\IB\Registrations\Sessions\Student\Helper;
use Schoolbox\IB\Registrations\Sessions\Student\Model;
use Schoolbox\User;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        private Helper $helper,
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
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
            '_routes' => [
                'updateLanguages' => $this->buildUpdateLanguagesRoute($student),
            ],
        ];
    }

    private function buildUpdateLanguagesRoute(Model $student): ?string
    {
        $sessionId = $student->getRegistrationSession()?->getId();
        $studentId = $student->getUser()?->getId();

        if ($sessionId === null || $studentId === null) {
            return null;
        }

        return $this->getRouteIfAuthorized(
            'internationalBaccalaureate.updateRegistrationSessionStudentSubjectLanguages',
            [
                'sessionId' => $sessionId,
                'studentId' => $studentId,
            ]
        );
    }
}
