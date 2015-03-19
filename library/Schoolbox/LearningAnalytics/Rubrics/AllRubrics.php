<?php
/**
 * AllRubrics
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Rubrics;

use Schoolbox\LearningAnalytics\Metadata\Base as Metadata;
use Schoolbox\LearningAnalytics\Registry;

class AllRubrics {

    /**
     * An instance of Metadata from Module
     *
     * @var Metadata
     */
    private $metadata = null;

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * An array of rubric data to plot
     *
     * @var array
     */
    private $allRubrics = null;

    /**
     * A rubric handler instance
     *
     * @var \DueWork_Rubric_Handler
     */
    private $rubricHandler = null;

    /**
     * Constructor
     *
     * @param Metadata $metadata
     * @param Registry $registry
     */
    public function __construct(Metadata $metadata, Registry $registry) {
        $this->setMetadata($metadata);
        $this->setRegistry($registry);
        $this->setRubricHandler(new \DueWork_Rubric_Handler());

        $this->process();
    }

    /**
     * Process
     */
    private function process() {
        $allCriteria = array();

        /* A list of all the criteria for this folder
         */
        foreach($this->getMetadata()->getAllAggregateFolders() as $currentFolder) {
            $allCriteria = $allCriteria + $this->getRubricHandler()->getAvailableCriteria($currentFolder->getId());

            /* All the students' values
             */
            $groupedMarks = array();

            foreach($this->getRegistry()->getAllStudentRegistry() as $unitId => $allStudents) {
                foreach ($allStudents as $currentStudent) {

                    /* Put folders in a collection for iteration
                     *
                     * Oddly enough this method gets the students' marks for a
                     * criterion spanning across the entire course, which is
                     * nice and convenient
                     */
                    foreach ($this->getMetadata()->getAllAggregateFolders() as $currentFolder) {
                        $allMarks = $this->getRubricHandler()->getStudentMarksByCriteriaInCategory($currentStudent->getId(), $currentFolder->getId());

                        foreach($allMarks as $criterionId => $current) {
                            $groupedMarks[$current['name']][] = array(
                                'user' => $currentStudent->getId(),
                                'value' => $current['value'],
                            );
                        }
                    }
                }
            }
        }

        /* Recombine and preserve criterion grouping
         *
         * You cannot aggregate criteria by their id, because when you
         * spawn rubrics for multiple homepages, criteria get their db rows
         * copied and pasted as entirely new records. They don't have any
         * persistent relations to the parent rubric from which they
         * were copied. That means you have to aggregate based on their
         * name, rather than their id.
         *
         * There is no record of a decision being made about whether a
         * rubric instance is the same as another:
         *
         * + based on their originating id, or
         * + based on their name
         *
         * I suppose one could say that once you change a rubric criterion's
         * name it becomes an entirely new criterion. But then again, that
         * also captures changes that aren't substantial but purely cosmetic
         * like punctuation and other slight changes like that. The names need
         * to be exactly the same in order to aggregate.
         */
        $allRubrics = array();

        foreach ($allCriteria as $criteriaGroup => $currentCriterion) {
            foreach ($currentCriterion as $criterionId => $details) {
                $reforged = $details;
                $reforged['allValues'] = $groupedMarks[$reforged['name']];

                /* Group by rubric name, not id
                 */
                if (isset($allRubrics[$criteriaGroup][$details['name']])) {
                    $allRubrics[$criteriaGroup][$details['name']]['allValues'] = array_merge($allRubrics[$criteriaGroup][$details['name']], $reforged['allValues']);
                }
                else {
                    $allRubrics[$criteriaGroup][$details['name']] = $reforged;
                }
            }

            /* Sort in order of spawn
             */
            asort($allRubrics[$criteriaGroup]);
        }

        $this->setAllRubrics($allRubrics);
    }

    /* Getters/Setters
     */

    /**
     * Set metadata
     *
     * @param \Schoolbox\LearningAnalytics\Metadata\Base $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return \Schoolbox\LearningAnalytics\Metadata\Base
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set registry
     *
     * @param \Schoolbox\LearningAnalytics\Registry $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return \Schoolbox\LearningAnalytics\Registry
     */
    private function getRegistry()
    {
        return $this->registry;
    }

    /**
     * Set all rubrics
     *
     * @param array $allRubrics
     */
    private function setAllRubrics($allRubrics)
    {
        $this->allRubrics = $allRubrics;
    }

    /**
     * Get all rubrics
     *
     * @return array
     */
    public function getAllRubrics()
    {
        return $this->allRubrics;
    }

    /**
     * Set rubric handler
     *
     * @param \DueWork_Rubric_Handler $rubricHandler
     */
    private function setRubricHandler($rubricHandler)
    {
        $this->rubricHandler = $rubricHandler;
    }

    /**
     * Get rubric handler
     *
     * @return \DueWork_Rubric_Handler
     */
    private function getRubricHandler()
    {
        return $this->rubricHandler;
    }
}