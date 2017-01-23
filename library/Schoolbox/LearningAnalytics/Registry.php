<?php
/**
 * Registry
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Schoolbox\Collection;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\User;

class Registry {

    /**
     * An array of unitId-user data
     *
     * @var Collection[]|User[][]
     */
    private $students;

    /**
     * A Collection of UnitInstance instances
     *
     * @var Collection
     */
    private $unitImplementations;

    /**
     * Constructor
     *
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     */
    public function __construct(Collection $unitImplementations)
    {
        $this->setUnitImplementations($unitImplementations);

        $users = [];

        foreach ($unitImplementations as $unit) {
            $unitId = $unit->getId();

            foreach ($unit->getAssessables() as $activity) {
                foreach ($activity->getAllParticipants() as $participant) {
                    $u = $participant->getUser();

                    if (empty($users[$unitId][$u->getId()])) {
                        $users[$unitId][$u->getId()] = $u;
                    }
                }
            }

            // je ne parle pas...?
            $users[$unitId] = new Collection($users[$unitId]);
        }

        $this->setStudents(new Collection($users));
    }

    /* Getters/Setters
     */

    /**
     * Set all student registry
     *
     * @param Collection|User[] $students
     */
    private function setStudents($students)
    {
        $this->students = $students;
    }

    /**
     * Get all student registry
     *
     * @return Collection[]|User[][]
     */
    public function getStudents()
    {
        return $this->students;
    }


    /**
     * @param Implementation\Unit\Model $unitImplementation
     * @return Collection|User[]
     */
    public function getStudentsByUnitImplementation(Implementation\Unit\Model $unitImplementation)
    {
        return $this->students[$unitImplementation->getId()];
    }

    /**
     * Set all unit registry
     *
     * @param Collection|Implementation\Unit\Model[] $allUnitRegistry
     */
    private function setUnitImplementations($allUnitRegistry)
    {
        $this->unitImplementations = $allUnitRegistry;
    }

    /**
     * Get all unit registry
     *
     * @return Collection|Implementation\Unit\Model[]
     */
    public function getUnitImplementations()
    {
        return $this->unitImplementations;
    }
} 
