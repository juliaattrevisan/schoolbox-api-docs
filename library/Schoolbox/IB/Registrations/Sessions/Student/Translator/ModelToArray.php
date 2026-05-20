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
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;
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
            'session' => $this->buildSession($student),
            'qualification' => $this->helper->normalise($student->getRegistrationSession()?->getSystemPurpose()?->getQualification()?->getName()),
            'candidateDetails' => $this->buildCandidateDetails(
                [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'dateOfBirth' => $dateOfBirth,
                    'email' => $this->helper->normalise($user?->getEmail()),
                    'gender' => $this->helper->normalise($externalStudent['StudentGender'] ?? null),
                    'language1' => $this->formatCodeModel($user?->getLanguageCollection()->reindex()->getOffset(0)),
                    'language2' => $this->formatCodeModel($user?->getLanguageCollection()->reindex()->getOffset(1)),
                    'nationality1' => $this->formatCodeModel($user?->getNationalityCollection()?->reindex()->getOffset(0)),
                    'nationality2' => $this->formatCodeModel($user?->getNationalityCollection()?->reindex()->getOffset(1)),
                    'referenceCode' => '-',
                ],
                $missingFields
            ),
            'ibGroupSubjects' => $this->helper->buildIbGroupSubjects($student->getIbGroupSubjects()),
            '_routes' => [
                'updateLanguages' => $this->buildUpdateLanguagesRoute($student),
                'updateConfirmation' => $this->buildUpdateConfirmationRoute($student),
                'renotify' => $this->buildRenotifyRoute($student),
            ],
        ];
    }

    private function buildSession(Model $student): string
    {
        $registrationSession = $student->getRegistrationSession();
        $examSession = $this->helper->normalise($registrationSession?->getExamSession());
        $academicYear = $this->helper->normalise($registrationSession?->getAcademicYear());

        if ($examSession === '' && $academicYear === '') {
            return '';
        }

        return trim("{$examSession} {$academicYear}");
    }

    /**
     * @param array<string, string> $details
     * @param string[] $missingFields
     * @return array<int, array{key: string, label: string, value: string, isMissing: bool}>
     */
    private function buildCandidateDetails(array $details, array $missingFields): array
    {
        $labels = [
            'dateOfBirth' => 'Date of Birth',
            'email' => 'Email',
            'firstName' => 'First Name',
            'lastName' => 'Last Name',
            'gender' => 'Gender',
            'language1' => 'Language 1',
            'language2' => 'Language 2 (not required)',
            'nationality1' => 'Nationality 1',
            'nationality2' => 'Nationality 2 (not required)',
            'referenceCode' => 'Reference Code',
        ];
        $requiredFieldsByDetail = [
            'referenceCode' => 'userName',
        ];
        $optionalFields = [
            'language2' => true,
            'nationality2' => true,
        ];
        $missingLookup = array_fill_keys($missingFields, true);
        $candidateDetails = [];

        foreach ($labels as $key => $label) {
            $value = $details[$key] ?? '';
            $requiredField = $requiredFieldsByDetail[$key] ?? $key;
            $isMissing = isset($missingLookup[$requiredField]);

            $candidateDetails[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value !== '' ? $value : (isset($optionalFields[$key]) ? 'Not provided' : 'Missing'),
                'isMissing' => $isMissing,
            ];
        }

        return $candidateDetails;
    }

    private function formatCodeModel(mixed $model): string
    {
        if (!$model instanceof LanguageModel && !$model instanceof NationalityModel) {
            return '';
        }

        $name = $this->helper->normalise($model->getName());
        $code = $this->helper->normalise($model->getCode());

        if ($name === '' && $code === '') {
            return '';
        }

        return $name !== '' && $code !== ''
            ? "{$name} ({$code})"
            : $name . $code;
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
            ]
        );
    }
}
