<?php
/**
 * Results
 *
 * Get results for a specific unit
 * 
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Mapper\Exception as MapperException;


class Unit {

    /**
     * Unit instance
     *
     * @var Implementation\Unit\Model
     */
    private $unitImplementation;

    /**
     * All feedback within the unit implementation, indexed by *SPECIFICATION* activity ID, then student ID
     *
     * @var IFeedback[][]
     */
    private $results = [];

    /**
     * Constructor
     *
     * @param Implementation\Unit\Model $unitImplementation
     */
    public function __construct(Implementation\Unit\Model $unitImplementation)
    {
        $this->setUnitImplementation($unitImplementation);

        $results = [];

        foreach ($this->getUnitImplementation()->getAssessables() as $activity) {

            if($activity->getSpecificationAssessment() == null) {
                //skip activities with no specification
                continue;
            }

            $specActivityId = $activity->getSpecificationAssessment()->getId();

            foreach ($activity->getFeedbacks() as $feedback) {
                $studentId = $feedback->getReviewee()->getId();

                $results[$specActivityId][$studentId] = $feedback;
            }
        }

        $this->setResults($results);
    }

    /* Getters/Setters
     */

    /**
     * Set unit
     *
     * @param Implementation\Unit\Model $unitImplementation
     */
    private function setUnitImplementation(Implementation\Unit\Model$unitImplementation)
    {
        $this->unitImplementation = $unitImplementation;
    }

    /**
     * Get unit
     *
     * @return Implementation\Unit\Model
     */
    private function getUnitImplementation()
    {
        return $this->unitImplementation;
    }

    /**
     * Set feedback results
     *
     * @param IFeedback[][] $results
     */
    private function setResults(array $results)
    {
        $this->results = $results;
    }

    /**
     * Get all values
     *
     * @return IFeedback[][]
     */
    public function getResults()
    {
        return $this->results;
    }

}
