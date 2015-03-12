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
use Schoolbox\Assessment\Course\UnitInstanceMapper;

class Registry {

    /**
     * An array of unitId-user data
     *
     * @var Collection
     */
    private $allStudentRegistry = null;

    /**
     * A Collection of UnitInstance instances
     *
     * @var Collection
     */
    private $allUnitRegistry = null;

    /**
     * Constructor
     *
     * @param Collection $allFolders
     */
    public function __construct(Collection $allFolders) {
        $this->findRegistries($allFolders);
    }

    /**
     * A list of all the students who are associated with each unit
     *
     * This basically serves as a preload for the external db, because
     * we reuse this list of students and we don't want to hit the
     * external db more than we have to
     *
     * @param Collection $allFolders
     */
    private function findRegistries(Collection $allFolders) {
        $unitInstanceMapper = new UnitInstanceMapper();

        /* A registry of unitId-studentList relations
         */
        $allStudentRegistry = array();

        /* Also maintain a growing collection of units
         */
        $allUnitRegistry = new Collection();

        /* Extract all the students first because we're going to
         * reuse this list again
         */
        foreach ($allFolders as $currentFolder) {
            $allUnits = $unitInstanceMapper->findForCategoryId($currentFolder->getId());

            $allUnitRegistry->merge($allUnits);

            foreach ($allUnits as $currentUnit) {
                $studentListProcessor = new StudentList($currentFolder, $currentUnit);
                $allStudentRegistry[$currentUnit->getId()] = $studentListProcessor->getStudentList();
            }
        }

        $this->setAllStudentRegistry($allStudentRegistry);
        $this->setAllUnitRegistry($allUnitRegistry);
    }

    /* Getters/Setters
     */

    /**
     * Set all student registry
     *
     * @param array $allStudentRegistry
     */
    private function setAllStudentRegistry($allStudentRegistry)
    {
        $this->allStudentRegistry = $allStudentRegistry;
    }

    /**
     * Get all student registry
     *
     * @return array
     */
    public function getAllStudentRegistry()
    {
        return $this->allStudentRegistry;
    }

    /**
     * Set all unit registry
     *
     * @param \Schoolbox\Collection $allUnitRegistry
     */
    private function setAllUnitRegistry($allUnitRegistry)
    {
        $this->allUnitRegistry = $allUnitRegistry;
    }

    /**
     * Get all unit registry
     *
     * @return \Schoolbox\Collection
     */
    public function getAllUnitRegistry()
    {
        return $this->allUnitRegistry;
    }
} 