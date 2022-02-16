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
     * get views all
     * @param IAssessable $assessment
     * @param User $participant (student)
     * @param User $user (student, parent's)
     */
    public function getAllViews(IAssessable $assessment, User $participant, User $user): array
    {
        /**
         * find latest views for assessment and participant
         */
        $last_views = [
            'student' => [],
            'parents' => [],
        ];
        try {
            $views = $this->mapper->findByColumnList([
                                                'assessment_id'  => $assessment->getId(),
                                                'participant_id' => $participant->getId(),
                                            ]);
        } catch (NotFound $e) {
            return $last_views;
        }
        /**
         * return array of [
         * 'student'
         * 'parents' [
         *    'parent1'
         *    'parent2'
         *      ]
         * ]
         */
        foreach ($views as $view) {
            if ($participant === $view->getUser()) {
                $last_views['student'] = $view;
                continue;
            }
            //parent full name
            $participant->loadParents();
            $last_views['parents'][$participant->getParents()->getBy('id', $view->getUser())] = $view;
        }
        return $last_views;
    }

    /**
     * get single user view
     * @param IAssessable $assessment
     * @param User        $participant (student)
     * @param User        $user (student, parent's)
     * @return \Alaress_Date_Format|null
     */
    public function getView(IAssessable $assessment, User $participant, User $user): ?\Alaress_Date_Format
    {
        /**
         * find latest view of assessmen/participation for the user specified.
         */
        try {
            $last_view = $this->mapper->findOneByColumnList([
                                                   'assessment_id'  => $assessment->getId(),
                                                   'participant_id' => $participant->getId(),
                                                   'user_id'        => $user->getId(),
                                               ]);
            return $last_view->getLastVisit();
        } catch (NotFound $e) {
            return null;
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
     * @param Participant|null $participant (student)
     * @return array
     */
    public function getTwigViews(IAssessable $assessment, ?Participant $participant): array
    {
        //if no participant, must be submitting the assessment, so exit
        if (null === $participant) {
            return [];
        }

        $views = [
            'student' => [
                'name'      => $participant->getUser()->getFullName(),
                'status'    => 'is yet to view',
                'object'    => 'assessment',
                'date'      => null,
            ],
            'parents'   => [],

        ];

        /** @var Model[]|GenericCollection $last_views */
        $last_views = $this->mapper->findByColumnList([
                                                     'assessment_id'  => $assessment->getId(),
                                                     'participant_id' => $participant->getUser()->getId(),
                                                 ]);

        /**
         * set the student values
         */
        /** @var Model|null $studentView */
        $studentView = $last_views->getBy('user', $participant->getUser());
        if (null === $studentView) {
            /**
             * no student entry, error out
             * has the student submitted the assessment, if so show a basic message
             */
            if ($participant->hasSubmittedResponse()) {
                $views['student']['status'] = 'has viewed';
            }
            return $views;
        }

        $views['student']['status'] = 'has viewed';
        $views['student']['date']   = $studentView->getLastVisit()->getLocal();
        $views['student']['object'] = 'assessment';
        $feedback = $participant->getAuthoritativeFeedback(true);
        if (null !== $feedback) {
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
            ];
            /**
             * check if viewed?
             */
            /** @var Model|null $parentView */
            $parentView = $last_views->getBy('user', $parent);
            if (null !== $parentView) {
                $date = $parentView->getLastVisit();
                $view['date'] = $date->getLocal();
                $view['status'] = 'has viewed';
                /**
                 * check if view after feedback?
                 */
                if (null !== $feedback & $feedback->getPublishedAt() < $date) {
                    //parent viewed after feedback was published
                    $view['object'] = 'feedback';
                }
            }
            $views['parents'][] = $view;
        }
        return $views;
    }
}
