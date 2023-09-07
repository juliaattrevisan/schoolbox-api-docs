<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Aura\Router\Generator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\User;

/**
 * Translator for translating an assessment model into an array for feed.
 */
class UnitAssessments
{
    public function __construct(private null|Generator $routeGenerator = null, private null|User $student = null)
    {
    }

    /**
     * @param IAssessable $assessment
     * @return array
     */
    public function __invoke(IAssessable $assessment): array
    {
        $links = null;
        if ($this->routeGenerator) {
            $links = [
                'status' => $this->routeGenerator->generate('learningAssessment.getSummary', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'studentId' => $this->student->getId(),
                    'student' => $this->student,
                ])
            ];
        }
        $assessments = null;
        if ($assessment instanceof Project) {
            $assessments = $assessment->getAssessables();
        }
        return [
            'id'                => $assessment->getId(),
            'title'             => $assessment->getTitle(),
            'assessmentType'    => $assessment->getFamilyName(),
            'closeAt'           => $assessment->getCloseAt()?->getUsingFormat(DATE_ATOM),
            'closeAtFormatted'  => $assessment->getCloseAt()?->getRelativeDay(),
            '_links'            => $links,
            'assessments'    => $assessments?->reindex()->map(new self($this->routeGenerator, $this->student)),
        ];
    }
}
