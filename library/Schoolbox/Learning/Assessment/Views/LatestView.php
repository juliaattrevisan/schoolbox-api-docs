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
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\User;

class LatestView
{
    /**
     * LastView Mapper Instance
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * Constructor
     * @param Mapper|null $mapper
     */
    public function __construct(Mapper $mapper = null)
    {
        if (null === $mapper) {
            $mapper = Mapper::getInstance();
        }
        $this->mapper = $mapper;
    }

    /**
     * add record?
     * is new? or existing?
     * update value
     * @param IAssessable $assessment
     * @param User $participant (student)
     * @param User $user (student, parent's)
     */
    public function addNewView(IAssessable $assessment, User $participant, User $user): void
    {
        /**
         * find if record exists
         */
        try {
            $last_view = $this->mapper->findOneByColumnList([
                'assessment_id'     => $assessment->getId(),
                'participant_id'    => $participant->getId(),
                'user_id'           => $user->getId(),
                                                            ]);
        } catch (NotFound $e) {
            $last_view = $this->mapper->createModel()
                ->setAssessment($assessment)
                ->setParticipant($participant)
                ->setUser($user);
        }

        /**
         * update time to now
         */
        $last_view->setLastVisit(\Alaress_Date_Format::now());

        /**
         * check if use is either that student or the parents, then save
         */
        if (
            (null !== $user->getChildren() & $user->getChildren()->getBy('id', $participant->getId())) ||
             $participant->getId() === $user->getId()
        ) {
            /**
             * save record
             */
            $this->mapper->save($last_view);
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
     * @return array
     */
    public function getTwigViews(IAssessable $assessment, User $student): array
    {
        //if no participant, must be submitting the assessment, so exit
        $participant = $assessment->getParticipantIfExtant($student);
        if (null === $participant) {
            return [];
        }

        $views = [
            'student' => [
                'name'      => $student->getFullName(),
                'status'    => 'is yet to view',
                'object'    => 'assessment',
                'date'      => null,
            ],
            'parents'   => [],

        ];

        /** @var Model[]|GenericCollection $last_views */
        $last_views = $this->mapper->findByColumnList([
                                                     'assessment_id'  => $assessment->getId(),
                                                     'participant_id' => $student->getId(),
                                                 ]);

        /**
         * set the student values
         */
        /** @var Model|null $studentView */
        $studentView = $last_views->getBy('user', $student);

        /**
         * check if there was a view recorded? in our special table
         */
        if (null === $studentView) {
            /**
             * no student entry, error out
             */
            return $views;
        }

        /**
         * check if there has been a submission? as the student has viewed the assignment when they were submitting it
         * this will also be true if the teacher submitted on behalf of the student
         */
        $feedback = null;
        if ($participant->hasSubmittedResponse()) {
            $views['student']['status'] = 'has viewed';
            $feedback = $participant->getAuthoritativeFeedback(true);
        }

        $studentDate = $studentView->getLastVisit();
        $views['student']['date']   = $studentDate->getLocal();

        if (null !== $feedback && $feedback->getPublishedAt() < $studentDate) {
            /**
             * was the last student view after feedback?
             */
            $views['student']['object'] = 'feedback';
        }

        /**
         * if not been submitted, don't check parent views.
         * and return just the student
         */
        if (!$participant->hasSubmittedResponse()) {
            return $views;
        }
        /**
         * parent's now
         * check status of parent's lasts views, and set the status'
         */
        $parentList = $participant->getUser()->loadParents()->getParents();
        foreach ($parentList as $parent) {
            /**
             * set basic data
             */
            $view = [
                'name'      => $parent->getFullName(),
                'status'    => 'has not viewed',
                'object'    => 'assessment',
                'date'      => null,
            ];
            /**
             * check if viewed?
             */
            /** @var Model|null $parentView */
            $parentView = $last_views->getBy('user', $parent);
            if (null !== $parentView) {
                $parentDate = $parentView->getLastVisit();
                $view['date'] = $parentDate->getLocal();
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
