<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;

class LatestView
{
    /**
     * Constructor
     * @param Access $access
     * @param Mapper $mapper
     * @param \Schoolbox\External\User $externalUser
     */
    public function __construct(private Access $access, private Mapper $mapper, private \Schoolbox\External\User $externalUser)
    {
    }


    /**
     * add record?
     * is new? or existing?
     * update value
     * @param IAssessable $assessment
     * @param User $participant (student)
     * @param User $user (student, parent's)
     * @throws \PDOException
     * @throws Exception
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function addNewView(IAssessable $assessment, User $participant, User $user): void
    {
        /**
         * find if record exists
         */
        try {
            $lastView = $this->mapper->findOneByColumnList([
                'assessment_id' => $assessment->getId(),
                'participant_id' => $participant->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (NotFound $e) {
            $lastView = $this->mapper->createModel()
                ->setAssessment($assessment)
                ->setParticipant($participant)
                ->setUser($user);
        }

        $feedbackIsVisibleToUser = $this->isFeedbackVisibleToUser($assessment, $user, $participant);
        /**
         * Update last visit time to now, and set whether feedback was visible on this visit.
         */
        $lastView->setLastVisit(\Alaress_Date_Format::now())->setFeedbackWasVisible($feedbackIsVisibleToUser);

        /**
         * check if use is either that student or the parents, then save
         */
        $this->externalUser->loadParents([$participant]);
        if (
            ($user->isParentOf($participant)) ||
             $participant->getId() === $user->getId()
        ) {
            /**
             * save record
             */
            $this->mapper->save($lastView);
        }
    }


    /**
     * get twig view data
     * returns a helper array for the student and parent containing the status and time of last view
     *
     * return array [
     * student: [status: (is yet to view / has viewed), object: 'assessment / feedback', 'date': date last view or (null) ]
     * parents: [
     * [status: (has viewed), object: 'assessment / feedback', 'date': date last view or (null) ]
     * [status: (has viewed), object: 'assessment / feedback', 'date': date last view or (null) ]
     * ]
     * @param IAssessable $assessment
     * @param User $student (student)
     * @param bool $isInstructor (only instructors should view this data)
     * @return array
     */
    public function getTwigViews(IAssessable $assessment, User $student, bool $isInstructor): array
    {
        //if no participant, must be submitting the assessment, so exit
        $participant = $assessment->getParticipantIfExtant($student);
        if (null === $participant || false === $isInstructor) {
            return [];
        }

        $views = [
            'student' => [
                'name' => $student->getFullName(),
                'status' => 'is yet to view',
                'object' => 'assessment',
                'date' => null,
            ],
            'parents' => [],
        ];

        /** @var Model[]|GenericCollection $lastViews */
        $lastViews = $this->mapper->findByColumnList([
            'assessment_id'  => $assessment->getId(),
            'participant_id' => $student->getId(),
        ])->indexByCallback(static function (Model $lastView) {
            /* Index by user id so we know who has been here
             */
            return $lastView->getUser()->getId();
        });

        /**
         * set the student values
         */
        /** @var Model|null $studentView */
        $studentView = $lastViews->getOffset($student->getId());

        /**
         * check if there was a view recorded? in our special table
         */
        $feedback = null;
        if (null !== $studentView) {
            $views['student']['status'] = 'has viewed';
            $views['student']['date'] = $studentView->getLastVisit()->getLong();

            if ($studentView->getFeedbackWasVisible()) {
                $views['student']['object'] = 'feedback';
            }
        }

        /**
         * parent's now
         * check status of parent's lasts views, and set the status'
         */
        $this->externalUser->loadParents([$participant->getUser()]);
        foreach ($participant->getUser()->getParents() as $parent) {
            /**
             * set basic data
             */
            $view = [
                'name' => $parent->getFullName(),
                'id' => $parent->getId(),
                'status' => 'has not viewed',
                'object' => 'assessment',
                'date' => null,
            ];
            /**
             * check if viewed?
             */
            /** @var Model|null $parentView */
            $parentView = $lastViews->getOffset($parent->getId());
            if (null !== $parentView) {
                $view['date'] = $parentView->getLastVisit()->getLong();
                $view['status'] = 'has viewed';

                if ($parentView->getFeedbackWasVisible()) {
                    $view['object'] = 'feedback';
                }
            }
            $views['parents'][] = $view;
        }
        return $views;
    }

    /**
     * Check if the mark is visible to the user visiting the page
     *
     * @param IAssessable $assessment
     * @param User        $viewer
     * @param User        $participant
     * @return bool
     * @throws Exception
     */
    private function isFeedbackVisibleToUser(IAssessable $assessment, User $viewer, User $participant): bool
    {
        /* Is the feedback already published and viewable by the student/parent
         */
        $isFeedbackVisible = $assessment->getParticipantIfExtant($participant)?->getAuthoritativeFeedback(true);
        if (null === $isFeedbackVisible) {
            return false;
        }

        /* Check if feedback/mark is visible to the visitor
         */
        $markVisibility = $assessment->getMarkVisibility();

        // Note class instructors can see feedback on that class' assessments.
        if ($viewer->getRole()->isStaff() || $this->access->isInstructor($viewer, $assessment->getContext()->getFolder())) {
            if ($markVisibility >= IAssessable::MARK_VISIBILITY_PRIVATE) {
                return true;
            }
        } else if ($viewer->getRole()->isStudent() && $participant->getId() === $viewer->getId()) {
            if ($markVisibility >= IAssessable::MARK_VISIBILITY_STUDENT) {
                return true;
            }
        } else if ($viewer->getRole()->isParent() && $viewer->isParentOf($participant)) {
            if ($markVisibility >= IAssessable::MARK_VISIBILITY_PARENT_STUDENT) {
                return true;
            }
        }

        return false;
    }
}
