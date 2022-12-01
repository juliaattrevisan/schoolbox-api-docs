<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Project\ResultCache;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * Takes a project IAssessable/AbstractAssessment,
 * performs the specified summation that aggregates all the feedbacks inside
 * then for a given user, remember the human-readable and normalised marks
 *
 * Can't think of a nice name for the kind of i/o this performs.
 */
class ResultCache
{
    /**
     * @param Mapper $mapper
     */
    public function __construct(
        private Mapper $mapper
    ) {
    }


    /**
     * Takes a $project instance and for the given users,
     * finds the project result that we calculated and stashed in the db,
     * saving you the explicit derivation steps. You'll get the answer(s)
     * straight away without having to calculate anything.
     *
     * @param IAssessable $project
     * @param array       $userIds
     * @return GenericCollection
     */
    public function retrieveMultiple(IAssessable $project, array $userIds): \Schoolbox\GenericCollection
    {
        /* Find a record for the (project id, user) tuple
         * and returns a model instance for it
         */
        return $this->mapper->findByColumnList([
            'project_id' => $project->getId(),
            'user_id' => $userIds
        ]);
    }


    /**
     * Takes a $project instance and for the given user,
     * finds the project result that we calculated and stashed in the db,
     * saving you the explicit derivation steps. You'll get the answer(s)
     * straight away without having to calculate anything.
     *
     * @param IAssessable $project
     * @param User        $user
     * @return Model|null
     */
    public function retrieve(IAssessable $project, \Schoolbox\User $user): ?Model
    {
        /* Find a record for the (project id, user) tuple
         * and returns a model instance for it
         */
        try {
            return $this->mapper->findOneByColumnList([
                'project_id' => $project->getId(),
                'user_id' => $user->getId()
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Returns null if it doesn't exist
             * (no marks were ever calculated for this tuple)
             */
            return null;
        }
    }


    /**
     * Takes a fully hydrated $project instance and for the given $user,
     * finds the participant equivalent and obtains the human-readable
     * and normalised marks.
     *
     * Shank it straight into a cache db
     *
     * $marker is the teacher who is currently causing the project's marks
     * to be aggregated and persisted to the db, overwriting (potentially)
     * anyone else who might have done the same, at some point in the past
     *
     * @param IAssessable $project
     * @param User        $user
     * @param User        $marker
     * @return Model|null
     * @throws Exception
     * @throws \DomainException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function persist(IAssessable $project, \Schoolbox\User $user, \Schoolbox\User $marker): ?Model
    {
        $participant = $project->getParticipantIfExtant($user);

        /* No participant? No trabajo
         */
        if (null === $participant) {
            return null;
        }

        /* See if an existing record exists for this (project, user) tuple
         *
         * If there is, update it with the new values
         * Otherwise, spawn a new record for them
         */
        $model = $this->retrieve($project, $participant->getUser());

        if (null === $model) {
            $model = $this->mapper->createModel()
                ->setProjectId($project->getId())
                ->setUser($participant->getUser());
        }

        /* We only care for published feedbacks' marks, not draft ones
         */
        $normalisedMark = $project->getNormalisedMark($participant, true);
        $humanReadableMark = $project->getHumanReadableMark($participant, true);

        /* Shove in all the parts
         */
        $model
            ->setMarker($marker)
            ->setHumanReadableMark($humanReadableMark)
            ->setNormalisedMark($normalisedMark);

        $this->mapper->save($model);

        return $model;
    }
}
