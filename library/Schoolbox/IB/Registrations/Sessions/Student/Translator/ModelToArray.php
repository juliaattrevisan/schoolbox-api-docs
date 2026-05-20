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

        $givenName = $this->helper->normalise($user?->getGivenName());
        $lastName = $this->helper->normalise($user?->getLastname());
        $languageCodes = $this->helper->extractCodes($user?->getLanguageCollection());
        $nationalityCodes = $this->helper->extractCodes($user?->getNationalityCollection());
        $dateOfBirth = $this->helper->formatDateOfBirth($externalStudent['StudentBirthDate'] ?? null);
        $userName = $this->helper->buildUserName($givenName, $lastName);

        $requiredDetails = [
            'userName' => $userName,
            'firstName' => $givenName,
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
                'updateConfirmation' => $this->buildUpdateConfirmationRoute($student),
                'renotify' => $this->buildRenotifyRoute($student),
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

    private function buildUpdateConfirmationRoute(Model $student): ?string
    {

        $sessionId = $student->getRegistrationSession()?->getId();
        $studentId = $student->getUser()?->getId();

        if ($sessionId === null || $studentId === null) {
            return null;
        }

        return $this->getRouteIfAuthorized(
            'internationalBaccalaureate.updateRegistrationSessionStudentConfirmation',
            [
                'sessionId' => $sessionId,
                'studentId' => $studentId,
            ]
        );
    }

    private function buildRenotifyRoute(Model $student): ?string
    {
        if ($student->getStatus() !== Model::STATUS_UNCONFIRMED) {
            return null;
        }

        $sessionId = $student->getRegistrationSession()?->getId();
        $studentId = $student->getUser()?->getId();

        if ($sessionId === null || $studentId === null) {
            return null;
        }

        return $this->getRouteIfAuthorized(
            'internationalBaccalaureate.renotifyUnconfirmed',
            [
                'sessionId' => $sessionId,
                'studentId' => $studentId,
            ]
        );
    }
}
