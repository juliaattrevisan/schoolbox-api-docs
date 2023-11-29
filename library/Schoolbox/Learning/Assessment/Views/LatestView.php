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
use Schoolbox\User;

class LatestView
{
    /**
     * Constructor
     * @param Mapper $mapper
     * @param \Schoolbox\External\User $externalUser
     */
    public function __construct(private Mapper $mapper, private \Schoolbox\External\User $externalUser)
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

        /**
         * update time to now
         */
        $lastView->setLastVisit(\Alaress_Date_Format::now());

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
        ])->indexByCallback(static function(Model $lastView) {
            /* Index by participant and user id
             * so we know who has been here
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
            /**
             * student has viewed the assessment
             */
            $views['student']['status'] = 'has viewed';

            $studentDate = $studentView->getLastVisit();
            $views['student']['date'] = $studentDate->getLong();

            $feedback = $participant->getAuthoritativeFeedback(true);
            if (null !== $feedback && $feedback->getPublishedAt() < $studentDate) {
                /**
                 * was the last student view after feedback?
                 */
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
                $parentDate = $parentView->getLastVisit();
                $view['date'] = $parentDate->getLong();
                $view['status'] = 'has viewed';
                /**
                 * check if view after feedback?
                 */
                if (null !== $feedback && $feedback->getPublishedAt() < $parentDate) {
                    //parent viewed after feedback was published
                    $view['object'] = 'feedback';
                }
            }
            $views['parents'][] = $view;
        }
        return $views;
    }
}
