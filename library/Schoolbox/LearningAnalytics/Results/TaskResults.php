<?php
/**
 * TaskResults
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\LearningAnalytics\Registry;

class TaskResults {

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * A set of unitId-results relations
     *
     * @var array
     */
    private $allResults = array();

    /**
     * Constructor
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry) {
        $this->setRegistry($registry);

        $this->process();
    }

    /**
     * Process
     */
    private function process() {
        $allResults = array();
        $everyone = $this->getRegistry()->getAllStudentRegistry();

        foreach ($this->getRegistry()->getAllUnitRegistry() as $currentUnit) {
            $allStudents = $everyone[$currentUnit->getId()];

            /* Merge and stack results for each unit
             */
            $results = new Unit($allStudents, $currentUnit);

            foreach ($results->getAllValues() as $taskId => $currentResultValue) {

                if (count($allResults[$taskId])) {
                    $allResults[$taskId] = array_merge($allResults[$taskId], $currentResultValue->getAll());
                }
                else {
                    $allResults[$taskId] = $currentResultValue->getAll();
                }
            }
        }

        $this->setAllResults($allResults);
    }

    /* Getters/Setters
     */

    /**
     * Set registry
     *
     * @param Registry $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return Registry
     */
    private function getRegistry()
    {
        return $this->registry;
    }

    /**
     * Set all results
     *
     * @param array $allResults
     */
    private function setAllResults($allResults)
    {
        $this->allResults = $allResults;
    }

    /**
     * Get all results
     *
     * @return array
     */
    public function getAllResults()
    {
        return $this->allResults;
    }
}