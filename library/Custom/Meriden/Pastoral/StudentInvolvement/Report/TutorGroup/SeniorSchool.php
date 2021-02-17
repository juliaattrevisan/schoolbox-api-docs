<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement\Report\TutorGroup;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;

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
     * A list of endeavor grades, collated for this report.
     */
    private array $endeavorGrades = [];

    /**
     * Collate the endeavor grades out of `$allGrades`, and inject them into
     * `$this->endeavorGrades`.
     *
     * @param array $allGrades
     * @param bool  $publishedOnly
     * @return $this
     */
    public function injectEndeavorGrades(array $allGrades, bool $publishedOnly): self
    {
        foreach ($allGrades as $grade) {
            // Which one is Endeavor Grades?
            /** @var IAssessable $assessment */
            $assessment      = $grade['assessment'];
            $folder          = $assessment->getContext()->getFolder();
            $assessmentTitle = $assessment->getTitle();
            foreach (self::ENDEAVOR_GRADES_ASSESSMENT_TITLES as $key => $title) {
                if (strcasecmp($title, $assessmentTitle) === 0) {
                    $participant = $assessment->getParticipantIfExtant($this->student);
                    $this->endeavorGrades[$folder->getId()] ??= [
                        'folder'      => $folder,
                        'assessments' => [],
                    ];
                    if ($participant) {
                        $this->endeavorGrades[$folder->getId()]['assessments'][$key]
                            = $participant->getAuthoritativeFeedback($publishedOnly);
                    }
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
     * @return array
     */
    public function getEndeavorGrades(): array
    {
        return $this->endeavorGrades;
    }
}
