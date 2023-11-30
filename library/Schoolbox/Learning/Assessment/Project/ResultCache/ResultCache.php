<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Project\ResultCache;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
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
     * In normal usage, $perform mode is intended to be true.
     *
     * Only in particular circumstances would you set this to false,
     * such as in the cli context where the --perform flag is optional
     * and you only wanted to preview what would be found.
     *
     * @var bool
     */
    private bool $perform = true;


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
     * Note: The only public usage of persist() is the Cli Reloader tool
     * which dumps some diagnostic info at you when you use it. You should
     * prefer using refresh() first.
     *
     * @param IAssessable $project
     * @param User        $user
     * @param User|null   $marker
     * @return Model|null
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function persist(IAssessable $project, \Schoolbox\User $user, ?\Schoolbox\User $marker): ?Model
    {
        $participant = $project->getParticipantIfExtant($user);

        /* See if an existing record exists for this (project, user) tuple
         *
         * If there is, update it with the new values
         * Otherwise, spawn a new record for them
         */
        $model = $this->retrieve($project, $user);

        /* No participant? No trabajo
         */
        if (null === $participant && null === $model) {
            return null;
        }

        /* Delete cache, if exists for no participant user
         */
        if (null === $participant) {
            $this->mapper->delete($model);
            return null;
        }

        if (null === $model) {
            $model = $this->mapper->createModel()
                ->setProjectId($project->getId())
                ->setUser($participant->getUser());
        }

        /* We only care for published feedbacks' marks, not draft ones
         */
        $normalisedMark = $participant->generateNormalisedMark(true);
        $humanReadableMark = $participant->generateHumanReadableMark(true);

        if (null === $normalisedMark) {
            // ignore null results these have no feedback, so shouldn't persist to the db..
            return null;
        }

        // Normalised mark must be within the bounds [0, 100].
        // This restriction should (I think?) be in
        // `\Schoolbox\Learning\Assessment\Summator\AbstractSummator::summate`
        // implementations, but I am not sure what the change impact of adding
        // that restriction at that level is.
        if (isset($normalisedMark)) {
            if ($normalisedMark < IMarkScheme::NORMALISED_MARK_MIN) {
                $normalisedMark = IMarkScheme::NORMALISED_MARK_MIN;
            }
            if ($normalisedMark > IMarkScheme::NORMALISED_MARK_MAX) {
                $normalisedMark = IMarkScheme::NORMALISED_MARK_MAX;
            }
        }

        /* Shove in all the parts
         */
        $model
            ->setMarker($marker)
            ->setHumanReadableMark($humanReadableMark)
            ->setNormalisedMark($normalisedMark);

        if ($this->perform) {
            $this->mapper->save($model);
        }

        return $model;
    }


    /**
     * Decide whether we have to invalidate project_result cache
     * Have to compare $postData and $assessment -before- it's transacted/updated
     *
     * Updates the Project Result Cache whenever you do things like
     * Export To Classes, Import a Course, etc. through the app's gui
     *
     * Typically these are creational steps which should always invalidate
     * a Project Result Cache
     *
     * Optionally provide a user id to only update their project result.
     *
     * @param MapperProxy $assessmentMapperProxy
     * @param IAssessable $assessment
     * @param User|null   $student
     * @return void
     * @throws DefinitionException
     * @throws Deleted
     * @throws MapperException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function refresh(
        \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy,
        IAssessable $assessment,
        ?\Schoolbox\User $student = null,
    ): void {
        if ($assessment instanceof Project) {
            /* Dodge the mapper cache maybe. ffs
             * Take the performance hit right between the eyes
             *
             * If you aren't careful you'll operate on a cached version of $assessment,
             * participations and all, leading to calculating wrong values
             */
            $project = $assessmentMapperProxy->findOneByColumn('id', $assessment->getId(), true, $student, ['cache' => false]);
        } elseif ($assessment->getContext() instanceof Project) {
            /* Dodge the mapper cache maybe. ffs
             * Take the performance hit right between the eyes
             *
             * If you aren't careful you'll operate on a cached version of $assessment's context,
             * participations and all, leading to calculating wrong values
             */
            $project = $assessmentMapperProxy->findOneByColumn('id', $assessment->getContext()->getId(), true, $student, ['cache' => false]);
        } else {
            /* noop
             *
             * $assessment is neither a Project, nor a Project's Activity
             * It's some random assessment somewhere
             */
            return;
        }

        /* Decide how much data to update
         */
        if (null !== $student) {
            /* Only update one particular dude's result
             *
             * If $student is not a participant, or deleted, etc.
             * just exit without doing anything
             */
            $this->persist($project, $student, null);
        } else {
            /* Update everyone associated with this assessment
             */
            foreach ($project->getAllParticipants() as $userId => $participant) {
                $user = $participant->getUser();
                $this->persist($project, $user, null);
            }
        }
        // Attempt some cache cleaning
        // We spawned this, we should remove it
        unset($project);
    }


    /**
     * @param bool $perform
     * @return ResultCache
     */
    public function setPerform(bool $perform): self
    {
        $this->perform = $perform;
        return $this;
    }
}
