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
     * All feedback within the unit implementation, indexed by *SPECIFICATION* assessment.ID, then student.ID
     *
     * @var IFeedback[][]
     */
    private $results;

    /**
     * @param UnitImplementation $unitImplementation
     */
    public function __construct(UnitImplementation $unitImplementation)
    {
        $results = [];

        foreach ($unitImplementation->getAssessables() as $assessment) {

            if($assessment->getSpecificationAssessment() === null) {
                //skip assessments with no specification
                continue;
            }

            $specAssessmentId = $assessment->getSpecificationAssessment()->getId();

            foreach ($assessment->getFeedbacks() as $feedback) {
                $studentId = $feedback->getReviewee()->getId();

                $results[$specAssessmentId][$studentId] = $feedback;
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
