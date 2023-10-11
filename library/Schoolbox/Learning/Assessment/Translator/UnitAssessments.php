<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Aura\Router\Generator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\User;

/**
 * Translator for translating an assessment model into an array for feed.
 */
class UnitAssessments
{
    /**
     * @param Generator|null $routeGenerator
     * @param User|null      $student
     * @param bool $hideLessonPlan Should we be removing lesson plans from the output
     */
    public function __construct(private null|Generator $routeGenerator = null, private null|User $student = null, private bool $hideLessonPlan = false)
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
            if ($this->hideLessonPlan) {
                // Remove Lesson plans, no linking to other lessons
                $assessments = $assessments->filter(function ($assessment) {
                    /** @var IAssessable $assessment */
                    return !($assessment instanceof Model);
                });
            }
        }
        $projectName = null;
        // is parent project
        if ($assessment->getContext() instanceof Project) {
            $projectName = $assessment->getContext()->getTitle();
        }
        return [
            'id'                => $assessment->getId(),
            'title'             => $assessment->getTitle(),
            'assessmentType'    => $assessment->getFamilyName(),
            'course'            => $this->getCourseName($assessment),
            'unit'              => $assessment->getBaseContext()->getTitle(),
            'projectName'       => $projectName,
            'closeAt'           => $assessment->getCloseAt()?->getUsingFormat(DATE_ATOM),
            'closeAtFormatted'  => $assessment->getCloseAt()?->getRelativeDay(),
            '_links'            => $links,
            'assessments'       => $assessments?->reindex()->map($this),
        ];
    }

    /**
     * Fetch the name of the assessment's course; if it's in:
     * * a unit spec, fetch its course's folder's name;
     * * a unit implementation, fetch its specification's course's folder name;
     * * otherwise, return null
     *
     * @param IAssessable $assessable
     * @return string|null
     */
    private function getCourseName(IAssessable $assessable): ?string
    {
        $base = $assessable->getBaseContext();
        if ($base instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
            // Implementation
            /**
             * @phpstan-ignore-next-line
             * we aren't always hydrating the course folder when the assessment is inside a project,
             * so yes, phpstan, `getFolder()` can be null...
             */
            return $base->getSpecification()->getCourse()->getFolder()?->getTitle();
        } elseif ($base instanceof \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model) {
            // Specification
            /**
             * @phpstan-ignore-next-line
             * we aren't always hydrating the course folder when the assessment is inside a project,
             * so yes, phpstan, `getFolder()` can be null...
             */
            return $base->getCourse()->getFolder()?->getTitle();
        }
        //no course, return null
        return null;
    }
}
