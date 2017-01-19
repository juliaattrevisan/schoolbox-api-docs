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

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\LearningAnalytics\Registry;

class TaskResults
{

    /**
     * Feedback instances, indexed by assessment, then student
     *
     * @var IFeedback[][]
     */
    private $results = [];

    /**
     * Constructor
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $results = [];

        foreach ($registry->getUnitImplementations() as $unitImplementation) {

            $result = new Unit($unitImplementation);

            foreach ($result->getResults() as $specActivityId => $activityFeedbacks) {
                // flatten
                $results[$specActivityId] = $activityFeedbacks;
            }
        }

        $this->setResults($results);
    }

    /* Getters/Setters
     */

    /**
     * Set all results
     *
     * @param IFeedback[][] $results
     */
    private function setResults($results)
    {
        $this->results = $results;
    }

    /**
     * Feedback instances, indexed by assessment, then student
     *
     * @return IFeedback[][]
     */
    public function getResults()
    {
        return $this->results;
    }
}
