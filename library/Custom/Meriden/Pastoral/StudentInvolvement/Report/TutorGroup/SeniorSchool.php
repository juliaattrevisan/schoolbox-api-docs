<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement\Report\TutorGroup;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Representation of report data for a senior school student's tutor group.
 *
 * The senior school variant contains endeavor grades for all of the student's
 * subjects for that year.
 */
class SeniorSchool extends Base
{
    private const ENDEAVOR_GRADES_ASSESSMENT_TITLES = [
        'endeavourGrades' => 'Endeavour Grades',
        'endeavourGrade1' => 'Endeavour Grade Term 1',
        'endeavourGrade2' => 'Endeavour Grade Term 2',
        'endeavourGrade3' => 'Endeavour Grade Term 3',
        'endeavourGrade4' => 'Endeavour Grade Term 4',
    ];

    /**
     * Regular expressions for the names of subjects to exclude from the senior
     * report.
     */
    private const EXCLUDED_SENIOR_SUBJECT_NAME_REGEXS = [
        '/^Year\\s+10\\s+Tutor\\s+Group$/i',
        '/^Year\\s+11\\s+Tutor\\s+Group$/i',
        '/^Year\\s+12\\s+Tutor\\s+Group$/i',
        '/^English\\s+Language\\s+Skills$/i',
        '/^Senior\\s+Study\\s+1[012]$/i',
        '/^Senior\\s+Study\\s+[a-z0-9]$/i',
    ];

    /**
     * A list of endeavor grades, collated for this report.
     */
    private array $endeavorGrades = [];

    /**
     * Senior tutor group reports don't have subject reports.
     *
     * @return false
     */
    public function hasSubjectReports(): bool
    {
        return false;
    }

    /**
     * Collate the endeavor grades out of `$allGrades`, and inject them into
     * `$this->endeavorGrades`.
     *
     * @param array|Folder[] $allFolders
     * @param array          $allGrades
     * @param bool           $publishedOnly
     * @return $this
     */
    public function injectEndeavorGrades(array $allFolders, array $allGrades, bool $publishedOnly): self
    {
        // Collate all folders this student is in, presuming they have an endeavor grade
        foreach ($allFolders as $folder) {
            $this->endeavorGrades[$folder->getId()] ??= [
                'folder'      => $folder,
                'assessments' => [],
            ];
        }

        foreach ($allGrades as $grade) {
            // Which one is Endeavor Grades?
            /** @var IAssessable $assessment */
            $assessment      = $grade['assessment'];
            $folder          = $assessment->getContext()->getFolder();
            $assessmentTitle = $assessment->getTitle();
            foreach (self::ENDEAVOR_GRADES_ASSESSMENT_TITLES as $key => $title) {
                if (strcasecmp($title, $assessmentTitle) === 0) {
                    $participant = $assessment->getParticipantIfExtant($this->student);
                    $this->endeavorGrades[$folder->getId()]['assessments'][$key] = $participant
                        ? $participant->getAuthoritativeFeedback($publishedOnly)
                        : null
                    ;
                }
            }
        }

        // Sort by folder title
        usort($this->endeavorGrades, static function ($a, $b) {
            return strnatcasecmp($a['folder']->getTitle(), $b['folder']->getTitle());
        });

        return $this;
    }

    /**
     * Should this subject be displayed on the senior school tutor group report?
     *
     * @param string $subjectName
     * @return bool
     */
    public function isSubjectDisplayed(string $subjectName): bool
    {
        foreach (self::EXCLUDED_SENIOR_SUBJECT_NAME_REGEXS as $regex) {
            if (preg_match($regex, $subjectName)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array
     */
    public function getEndeavorGrades(): array
    {
        return $this->endeavorGrades;
    }
}
