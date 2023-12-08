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
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\User;

/**
 * Translator for translating an assessment model into an array for feed.
 */
class UnitAssessments
{
    /**
     * @param Generator|null $routeGenerator
     * @param User|null      $student         The student whose lesson plan is being viewed.
     * @param User|null      $loggedInUser    The logged-in user.
     * @param bool           $hideLessonPlan  Should we be removing lesson plans from the output.
     */
    public function __construct(
        private null|Generator $routeGenerator = null,
        private null|User $student = null,
        private null|User $loggedInUser = null,
        private bool $hideLessonPlan = false
    ) {
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
                'summary' => $this->routeGenerator->generate('learningAssessment.getSummary', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'studentId' => $this->student->getId(),
                    'student' => $this->student,
                ]),
                'status' => $this->routeGenerator->generate('learningAssessment.getStatus', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]),
                'modify' => $this->routeGenerator->generate('learningAssessment.getUpdateAssessmentForm', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ])
            ];
            // if quiz we have special links
            if ($assessment instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
                $links['modifyContent'] = $this->routeGenerator->generate('learnosity.getContent', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]);
            }
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
        /**
         * record / track participation
         * logic clone from Course Outline component view:
         * @see library/Schoolbox/Learning/Assessment/templates/outline/unitAssessments.twig
         */
        // init values
        $completionCount = $studentCount = 0;
        // only look at students who are not excluded
        foreach (
            $assessment->getAllParticipants()->filter(
                static function (Participant $participant) {
                    return !$participant->isExcluded();
                }
            ) as $participant
        ) {
            // count students which have completed this
            if (!$participant->isResponseRequired()) {
                $completionCount++;
            }
            // count al students who are part of this
            $studentCount++;
        }

        return [
            'id'                   => $assessment->getId(),
            'title'                => $assessment->getTitle(),
            'assessmentType'       => $assessment->getFamilyName(),
            'course'               => $this->getCourseName($assessment),
            'unit'                 => $assessment->getBaseContext()->getTitle(),
            'projectName'          => $projectName,
            'dueAt'                => $assessment->getDueAt()?->getUsingFormat(DATE_ATOM),
            'dueAtFormatted'       => $assessment->getDueAt()?->getRelativeDayWithoutMidnight(),
            '_links'               => $links,
            'assessments'          => $assessments?->reindex()->map($this),
            'completionCount'      => $completionCount,
            'studentCount'         => $studentCount,
            'progressTick'         => $this->getProgressTick($assessment),
            'isSelfAssessableTask' => (
                $assessment instanceof \Schoolbox\Learning\Assessment\Task\Model &&
                $assessment->isSelfAssessable()
            )
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

    /**
     * logic from @see library/Schoolbox/Learning/Assessment/templates/fragments/progressTick.twig
     * @param IAssessable $assessable
     * @return ?array
     */
    private function getProgressTick(IAssessable $assessable): ?array
    {
        // Progress ticks should only be shown if the logged-in user is the user whose activities are being viewed.
        if ($this->student->getId() !== $this->loggedInUser->getId()) {
            return null;
        }

        $participant = $assessable->getParticipantIfExtant($this->student);

        if (null === $participant) {
            throw new \LogicException('If the student is not a participant in the assessment, we should not be here.');
        }

        // task url allows students to mark their tasks as complete
        $taskUrl = $progress = null;
        // only create a url for tasks, and if they are implementations
        if ($assessable instanceof \Schoolbox\Learning\Assessment\Task\Model && $assessable->isImplementation()) {
            $taskUrl = $this->routeGenerator->generate('learningAssessment.postCreateResponse', [
                'assessment' => $assessable,
                'assessmentId' => $assessable->getId(),
                'student' => $this->student,
                'studentId' => $this->student->getId()
            ]);
            if ($participant->isResponseRequired() && ($assessable->isOpen($this->student) and $assessable->isSelfAssessable())) {
                $progress = 'incomplete';
            } else if ($participant->isFeedbackRequired()) {
                $progress = 'pending';
            } else if ($participant->isResubmissionRequired()) {
                $progress = 'failed';
            } else if ($participant->isReviewComplete()) {
                $progress = 'reviewed';
            }
        } else {
            if ($participant->isResponseRequired() && $assessable->isOpen($this->student)) {
                $progress = 'incomplete';
            } else if ($participant->isFeedbackRequired()) {
                $progress = 'pending';
            } else if ($participant->isResubmissionRequired()) {
                $progress = 'failed';
            } else if ($participant->isReviewComplete()) {
                $progress = 'reviewed';
            }
        }

        return [
            'url'       => $taskUrl,
            'progress'  => $progress,
        ];
    }
}
