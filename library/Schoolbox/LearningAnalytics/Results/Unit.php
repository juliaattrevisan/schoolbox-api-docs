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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;


class Unit {

    /**
     * @var UnitImplementation
     */
    private $unitImplementation;

    /**
     * All feedback within the unit implementation, indexed by *SPECIFICATION* activity ID, then student ID
     *
     * @var IFeedback[][]
     */
    private $results;

    /**
     * @param UnitImplementation $unitImplementation
     */
    public function __construct(UnitImplementation $unitImplementation)
    {
        $this->unitImplementation = $unitImplementation;

        $results = [];

        foreach ($this->unitImplementation->getAssessables() as $activity) {

            if($activity->getSpecificationAssessment() === null) {
                //skip activities with no specification
                continue;
            }

            $specActivityId = $activity->getSpecificationAssessment()->getId();

            foreach ($activity->getFeedbacks() as $feedback) {
                $studentId = $feedback->getReviewee()->getId();

                $results[$specActivityId][$studentId] = $feedback;
            }
        }

        $this->results = $results;
    }

    /* Getters/Setters
     */


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
