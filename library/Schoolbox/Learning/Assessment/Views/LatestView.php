<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Schoolbox\Learning\Assessment\DataModel as LearningAssessment;
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
     * @param LearningAssessment\Model $assessment
     * @param User $participant
     * @param User $user
     */
    public function addNewView(LearningAssessment\Model $assessment, User $participant, User $user): void
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
                ->setAssessment($assessment->getId())
                ->setParticipant($participant->getId())
                ->setUser($user->getId());
        }

        /**
         * update time to now
         */
        $last_view->setLastView(\Alaress_Date_Format::now());

        /**
         * save record
         */
        $this->mapper->save($last_view);
    }

    /**
     * get views all
     * @param LearningAssessment\Model $assessment
     * @param User $participant
     */
    public function getAllViews(LearningAssessment\Model $assessment, User $participant): array
    {
        /**
         * find latest views for assessment and participant
         */
        $last_views = [
            'student' => [],
            'parent' => [],
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
            if ($participant === $view->getParticipant) {
                $last_views['student'] = $view;
                continue;
            }
            $last_views['parent'][] = $view;
        }
        return $last_views;
    }

    /**
     * get single user view
     * @param LearningAssessment\Model $assessment
     * @param User                     $participant
     * @param User                     $user
     * @return \Alaress_Date_Format|null
     */
    public function getView(LearningAssessment\Model $assessment, User $participant, User $user): ?\Alaress_Date_Format
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
            return $last_view->getLastView();
        } catch (NotFound $e) {
            return null;
        }
    }
}
