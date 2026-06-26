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

/**
 * Builds one IBIS register-sessions row from a Schoolbox registration student.
 */
class RegisterSessionsSessionBuilder
{
    private const string DATE_FORMAT_DAY_MONTH_YEAR = 'd/F/Y';
    private const string DATE_FORMAT_YEAR_MONTH_DAY = 'Y-m-d';
    private const string SESSION_DATE_TEMPLATE = '01/%s/%s';

    // IBIS transcript names change for future sessions and once IBIS accepts the shorter names.
    private const string SUBJECT_TRANSCRIPT_OVERRIDE_CHANGEOVER_DATE = '2026-08-09';
    private const string SUBJECT_TRANSCRIPT_OVERRIDE_CUTOVER_SESSION_DATE = '2027-05-01';

    private const array SUBJECT_TRANSCRIPT_OVERRIDE = [
        'ENGLISH A: Lang and Literature' => 'ENGLISH A: Lang and Lit',
        'SPANISH A: Lang and Literature' => 'SPANISH A: Lang and Lit',
    ];

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
     * Map registration session, school, and subject data into an IBIS session payload.
     *
     * @return array<string, mixed>
     */
    public function build(RegistrationSessionStudentModel $student, string $candidateId, string $schoolCode): array
    {
        $user = $student->getUser();
        $registrationSession = $student->getRegistrationSession();
        $systemPurpose = $registrationSession?->getSystemPurpose();

        $month = $this->stringOrNull($registrationSession?->getExamSession());
        $year = $this->stringOrNull($registrationSession?->getAcademicYear());

        if ($month === null || $year === null) {
            throw new InvalidArgumentException('Selected candidate is missing required IBIS session time data.');
        }

        $session = [
            'candidate' => $this->stringOrNull($candidateId),
            'category' => $this->stringOrNull($systemPurpose?->getCategory()?->getName()),
            'main_school' => $this->stringOrNull($schoolCode),
            'month' => $month,
            'programme' => $this->stringOrNull($systemPurpose?->getCategory()?->getName()),
            'year' => $year,
            'email' => $this->stringOrNull($user?->getEmail()),
            'subjects' => $this->buildSubjects($student->getIbGroupSubjects(), $month, $year),
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
     * Build subject rows using each group subject's configured transcript name
     * and selected language.
     *
     * @return array<int, array{subject_name: string, subject_language: string, subject_level: string|null}>
     */
    private function buildSubjects(GenericCollection $groupSubjects, string $month, string $year): array
    {
        $subjects = [];

        $cutOverSession = new \Alaress_Date_Format(
            self::SUBJECT_TRANSCRIPT_OVERRIDE_CUTOVER_SESSION_DATE,
            self::DATE_FORMAT_YEAR_MONTH_DAY
        );
        $sessionDate = new \Alaress_Date_Format(
            sprintf(self::SESSION_DATE_TEMPLATE, $month, $year),
            self::DATE_FORMAT_DAY_MONTH_YEAR
        );
        $changeOverDate = new \Alaress_Date_Format(
            self::SUBJECT_TRANSCRIPT_OVERRIDE_CHANGEOVER_DATE,
            self::DATE_FORMAT_YEAR_MONTH_DAY
        );
        $today = \Alaress_Date_Format::now();

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

            $subjectName = str_replace('[LANGUAGE]', strtoupper($subjectLanguage), $subjectName);

            // Use the shortened transcript names after either the session or live IBIS changeover date.
            // TODO this should be temporary, if these kind of thing is starting to happen often might consider with a more permenant solution
            if (
                ($sessionDate->gt($cutOverSession) || $today->gte($changeOverDate)) &&
                isset(self::SUBJECT_TRANSCRIPT_OVERRIDE[$subjectName])
            ) {
                $subjectName = self::SUBJECT_TRANSCRIPT_OVERRIDE[$subjectName];
            }

            $subjects[] = [
                'subject_name' => $subjectName,
                'subject_language' => $subjectLanguage,
                'subject_level' => $subjectLevel,
            ];
        }

        return $subjects;
    }

    /**
     * Return the first configured subject language for the group subject.
     */
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

    /**
     * Normalise optional model values before required-field checks.
     */
    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
