<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\User;

class Registry
{

    /**
     * Sets of students indexed by student.id, grouped by unitImplementation.id
     *
     * @var Collection[]|User[][]
     */
    private $students;


    /**
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     */
    public function __construct(GenericCollection $unitImplementations)
    {
        $users = [];

        foreach ($unitImplementations as $unitImplementation) {
            $unitImplId = $unitImplementation->getId();

            foreach ($unitImplementation->getAssessables() as $assessment) {
                foreach ($assessment->getAllParticipants() as $participant) {
                    $user = $participant->getUser();

                    if (empty($users[$unitImplId][$user->getId()])) {
                        $users[$unitImplId][$user->getId()] = $user;
                    }
                }
            }

            $users[$unitImplId] = new Collection($users[$unitImplId]);
        }

        $this->students = new Collection($users);
    }

    /* Getters/Setters
     */

    /**
     * Get all student registry
     *
     * @return Collection[]|User[][]
     */
    public function getStudents()
    {
        return $this->students;
    }
}
