<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
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
     * @param Generator|null  $routeGenerator
     * @param Authorizer|null $authorizer
     * @param User|null       $student         The student whose assessment is being viewed.
     * @param User|null       $loggedInUser    The logged-in user.
     * @param bool            $hideLessonPlan  Should we be removing lesson plans from the output?
     */
    public function __construct(
        private null|Generator $routeGenerator = null,
        private null|Authorizer $authorizer = null,
        private null|User $student = null,
        private null|User $loggedInUser = null,
        private bool $hideLessonPlan = false
    ) {
    }

    /**
     * @param IAssessable $assessment
     * @return array
     * @throws RouteNotFound
     */
    public function __invoke(IAssessable $assessment): array
    {
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


        // Progress ticks should only be shown if the logged-in user is the user whose activities are being viewed.
        $progressTick = null;
        if ($this->student->getId() === $this->loggedInUser->getId()) {
            $participant = $assessment->getParticipantIfExtant($this->student);
            $progressTick = $this->getProgressTick($assessment, $participant);
        }

        $dueAt = $this->getDueAt($assessment);

        return [
            'id'                   => $assessment->getId(),
            'title'                => $assessment->getTitle(),
            'assessmentType'       => $assessment::getFamilyName(),
            'course'               => $this->getCourseName($assessment),
            'unit'                 => $assessment->getBaseContext()->getTitle(),
            'projectName'          => $projectName,
            'dueAt'                => $dueAt ? [
                'atom' => $dueAt->getUsingFormat(DATE_ATOM),
                'long' => $dueAt->getLong(),
                'relative' => $dueAt->getRelativeDayWithoutMidnight(),
            ] : null,
            '_links'               => $this->getLinks($assessment, true),
            'assessments'          => $assessments?->reindex()->map($this),
            'completionCount'      => $completionCount,
            'studentCount'         => $studentCount,
            'progressTick'         => $progressTick,
            'isSelfAssessableTask' => (
                $assessment instanceof \Schoolbox\Learning\Assessment\Task\Model &&
                $assessment->isSelfAssessable()
            )
        ];
    }

    /**
     * Get all the relevant links for an assessment
     *
     * @param IAssessable $assessment
     * @param bool $authorize - Should we check for authorization to the links?
     * @return array|null
     */
    public function getLinks(IAssessable $assessment, bool $authorize): ?array
    {
        $links = [];
        if ($this->routeGenerator) {
            /** The status page is the same for all assessments */
            $statusLink = [
                "route" => "learningAssessment.getStatus",
                "params" => [
                    'assessmentId' => $assessment->getId(),
                    'assessment'   => $assessment,
                ],
            ];

            /**
             * Create a link for the student participation page if the assessment is
             * within an implementation and is not a project or lesson plan
             */
            if ($assessment->getBaseContext() instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model &&
                !$assessment instanceof Project &&
                !$assessment instanceof \Schoolbox\Learning\Assessment\LessonPlan\Model) {
                $studentParticipationLink = [
                    "route" => "learningAssessment.getAssessmentParticipationForm",
                    "params" => [
                        'assessmentId' => $assessment->getId(),
                        'assessment'   => $assessment,
                    ],
                ];
            } else {
                $studentParticipationLink = null;
            }

            $availableLinks = [
                'summary' => ["route" => "learningAssessment.getSummary", "params" => [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'studentId' => $this->student->getId(),
                    'student' => $this->student,
                ]],
                'status' => $statusLink,
                'studentParticipation' => $studentParticipationLink,
                'modify' => ["route" => "learningAssessment.getUpdateAssessmentForm", "params" => [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]],
                'delete' => ["route" => "learningAssessment.postDeleteAssessment", "params" => [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]],
                'modifyContent' => null,
                'export' => null,
            ];
            // if quiz we have special links
            if ($assessment instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
                $availableLinks['modifyContent'] = ["route" => "learnosity.getContent", "params" => [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]];
            }
            // spec assessments can be exported
            if ($assessment->isSpecification()) {
                $availableLinks['export'] = ["route" => "learningAssessmentExporter.getExportFormForAssessment", "params" => [
                    'assessmentId' => $assessment->getId(),
                    'assessment'   => $assessment,
                ]];
            }

            // Filter out links that the user is not authorized to access (null if not authorized or not applicable)
            foreach ($availableLinks as $key => $link) {
                if ($link === null || ($authorize && !$this->authorizer->isPathAuthorizedForUser($link['route'], $link['params'], $this->loggedInUser))) {
                    $links[$key] = null;
                } else {
                    $links[$key] = $this->routeGenerator->generate($link['route'], $link['params']);
                }
            }
        }

        // If the user does not have access to any links, set $links to null
        if (empty(array_filter($links))) {
            $links = null;
        }

        return $links;
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
             * so yes, PHPStan, `getFolder()` can be null...
             */
            return $base->getSpecification()->getCourse()->getFolder()?->getTitle();
        } elseif ($base instanceof \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model) {
            // Specification
            /**
             * @phpstan-ignore-next-line
             * we aren't always hydrating the course folder when the assessment is inside a project,
             * so yes, PHPStan, `getFolder()` can be null...
             */
            return $base->getCourse()->getFolder()?->getTitle();
        }
        //no course, return null
        return null;
    }

    /**
     * The logic used in this function is intended to match the logic used in progressTick.twig.
     *
     * @param IAssessable $assessable
     * @param Participant|null $participant
     * @return ?array
     * @throws RouteNotFound
     * @see library/Schoolbox/Learning/Assessment/templates/fragments/progressTick.twig
     */
    public function getProgressTick(IAssessable $assessable, ?Participant $participant): ?array
    {
        if (null === $participant || $participant->isExcluded()) {
            // The student ought to be a participant.  The only way this situation could arise is if the student was
            // excluded from the assessment, or marked as deleted, by another process, since permissions were checked
            // at the start of this page-load (in Assessment/Module.php).
            return null;
        }

        // Only set this var if the assessment is a task so participants can create a
        // response asynchronously without reloading the page.  This only applies to Tasks.
        // Due Work and Quiz require more in-depth workflows to submit a response.
        $postRouteUrl = null;
        if ($assessable instanceof \Schoolbox\Learning\Assessment\Task\Model && $assessable->isImplementation()) {
            $postRouteName = 'learningAssessment.postCreateResponse';
            $postRouteParams = [
                'assessment' => $assessable,
                'assessmentId' => $assessable->getId(),
                'student' => $this->student,
                'studentId' => $this->student->getId()
            ];

            if ($this->authorizer->isPathAuthorizedForUser($postRouteName, $postRouteParams, $this->loggedInUser)) {
                $postRouteUrl = $this->routeGenerator->generate($postRouteName, $postRouteParams);
            }
        }

        if ($postRouteUrl === null) {
            $postRouteUrl = '';
        }

        $progress = '';

        $loggedInUserShouldSeeIncompleteStatus = (
            $participant->isResponseRequired() && (
                $this->loggedInUser->getRole()->isStaff() || (
                    $assessable instanceof \Schoolbox\Learning\Assessment\Task\Model &&
                    $assessable->isImplementation() &&
                    $assessable->isOpen($this->student) &&
                    $assessable->isSelfAssessable()
                )
            )
        );

        if ($postRouteUrl !== '') {
            if ($loggedInUserShouldSeeIncompleteStatus) {
                $progress = 'incomplete';
            } elseif ($participant->isFeedbackRequired()) {
                $progress = 'pending';
            } elseif ($participant->isReviewComplete()) {
                $progress = 'reviewed';
            }
        } else {
            if ($loggedInUserShouldSeeIncompleteStatus) {
                $progress = 'incomplete';
            } elseif ($participant->isFeedbackRequired()) {
                $progress = 'pending';
            } elseif ($participant->isResubmissionRequired()) {
                $progress = 'failed';
            } elseif ($participant->isReviewComplete()) {
                $progress = 'reviewed';
            }
        }

        return [
            'postRouteUrl'  => $postRouteUrl,
            'progressClass' => $progress,
        ];
    }

    /**
     * We should be showing the students personalised plan duedate (if it exists), or the assessment due date.
     *
     * @param IAssessable $assessable
     * @return ?Alaress_Date_Format
     */
    private function getDueAt(IAssessable $assessable): ?Alaress_Date_Format
    {
        $participant = $assessable->getParticipantIfExtant($this->student);

        if (null === $participant || $participant->isExcluded()) {
            // The student ought to be a participant.  The only way this situation could arise is if the student was
            // excluded from the assessment, or marked as deleted, by another process, since permissions were checked
            // at the start of this page-load (in Assessment/Module.php).
            return $assessable->getDueAt();
        }

        return $participant->getDueDate();
    }
}
