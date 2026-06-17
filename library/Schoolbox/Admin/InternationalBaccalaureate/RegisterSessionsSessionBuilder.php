<?php

/**
 * IBIS register sessions session builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use TypeError;

class RegisterSessionsSessionBuilder
{
    private const array REQUIRED_FIELDS = [
        'candidate',
        'category',
        'main_school',
        'month',
        'programme',
        'year',
        'subjects',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(RegistrationSessionStudentModel $student, string $candidateId, string $schoolCode): array
    {
        $user = $student->getUser();
        $registrationSession = $student->getRegistrationSession();
        $systemPurpose = $registrationSession?->getSystemPurpose();

        $session = [
            'candidate' => $this->stringOrNull($candidateId),
            'category' => $this->stringOrNull($systemPurpose?->getCategory()?->getName()),
            'main_school' => $this->stringOrNull($schoolCode),
            'month' => $this->stringOrNull($registrationSession?->getExamSession()),
            'programme' => $this->stringOrNull($systemPurpose?->getCategory()?->getName()),
            'year' => $this->stringOrNull($registrationSession?->getAcademicYear()),
            'email' => $this->stringOrNull($user?->getEmail()),
            'subjects' => $this->buildSubjects($student->getIbGroupSubjects()),
        ];

        $missingFields = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if ($session[$field] === null || $session[$field] === '' || $session[$field] === []) {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new InvalidArgumentException(
                'Selected candidate is missing required IBIS session data: ' . implode(', ', $missingFields) . '.'
            );
        }

        return array_filter(
            $session,
            static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }

    /**
     * @return array<int, array{subject_name: string, subject_language: string, subject_level: string|null}>
     */
    private function buildSubjects(GenericCollection $groupSubjects): array
    {
        $subjects = [];

        foreach ($groupSubjects as $groupSubject) {
            if (!$groupSubject instanceof GroupSubjectModel) {
                continue;
            }

            $subjectName = $this->stringOrNull($groupSubject->getSubject()?->getTranscriptName()) ?? $this->stringOrNull($groupSubject->getSubject()?->getName());
            $subjectLevel = $this->stringOrNull($groupSubject->getLevel());
            $subjectLanguage = $this->stringOrNull($this->findSubjectLanguage($groupSubject));

            if ($subjectName === null || $subjectLanguage === null) {
                continue;
            }

            $subjects[] = [
                'subject_name' => str_replace('[LANGUAGE]', strtoupper($subjectLanguage), $subjectName),
                'subject_language' => $subjectLanguage,
                'subject_level' => $subjectLevel,
            ];
        }

        return $subjects;
    }

    private function findSubjectLanguage(GroupSubjectModel $groupSubject): ?string
    {
        try {
            $languages = $groupSubject->getLanguages();
        } catch (TypeError) {
            return null;
        }

        foreach ($languages as $languageSelection) {
            if (!$languageSelection instanceof GroupSubjectLanguageModel) {
                continue;
            }

            return $languageSelection->getLanguage()?->getName();
        }

        return null;
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
