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
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;

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
     * @param Collection $allUnitInstances
     */
    public function __construct(Collection $allUnitInstances) {
        $this->findRegistries($allUnitInstances);
    }

    /**
     * A list of all the students who are associated with each unit
     *
     * This basically serves as a preload for the external db, because
     * we reuse this list of students and we don't want to hit the
     * external db more than we have to
     *
     * @param Collection $allUnitInstances
     */
    private function findRegistries(Collection $allUnitInstances) {
        $unitInstanceMapper = new UnitInstanceMapper();
        $folderMapper = new FolderMapper();
        $submissionBoxMapper = new SubmissionBoxMapper();
        $quizMapper = new OnlineAssessmentMapper();

        /* A registry of unitId-studentList relations
         */
        $allStudentRegistry = array();

        /* Also maintain a growing collection of units
         * These contain task completion details, which $allUnitInstances do not
         */
        $allUnitRegistryTemp = array();

        /* Extract all the students first because we're going to
         * reuse this list again
         */
        foreach ($allUnitInstances as $currentUnitInstance) {
            $folder = $currentUnitInstance->getCategory();
            $folderMapper->loadJoinedModel('codes', $folder, array('set_method' => 'setCodes'));

            $allUnits = $unitInstanceMapper->findForCategoryId($folder->getId());

            foreach ($allUnits as $currentUnit) {

                /* Rehydrate submission box in each taskInstance of the unit
                 * This feels viciously expensive. May be able to solve this
                 * by writing yet another custom query and mapping them all
                 * together manually, because I need the workType hydration
                 */
                foreach ($currentUnit->getTasks() as $currentTask) {
                    if (is_numeric($currentTask->getSubmissionBox()->getId())) {
                        try {
                            $submissionBox = $submissionBoxMapper->findById($currentTask->getSubmissionBox()->getId());
                            $currentTask->setSubmissionBox($submissionBox);
                        }
                        catch (\Exception $e) {
                        }
                    }
                    else if (is_numeric($currentTask->getAssessment()->getId())) {
                        try {
                            $quiz = $quizMapper->findById($currentTask->getAssessment()->getId());
                            $currentTask->setAssessment($quiz);
                        }
                        catch (\Exception $e) {
                        }
                    }
                }

                $allUnitRegistryTemp[$currentUnit->getId()] = $currentUnit;

                $studentListProcessor = new StudentList($folder, $currentUnit);
                $allStudentRegistry[$currentUnit->getId()] = $studentListProcessor->getStudentList();
            }
        }

        /* Inject $allRegistryTemp as stack
         */
        $allUnitRegistry = new Collection($allUnitRegistryTemp);

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