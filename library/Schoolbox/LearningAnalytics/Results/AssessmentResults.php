<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

class AssessmentResults
{

    /**
     * Feedback instances, indexed by assessment.id, then student.id
     *
     * @var IFeedback[][]
     */
    private $results;

    /**
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     */
    public function __construct(GenericCollection $unitImplementations)
    {
        $results = [];

        foreach ($unitImplementations as $unitImplementation) {

            $result = new Unit($unitImplementation);

            foreach ($result->getResults() as $specActivityId => $activityFeedbacks) {
                // flatten
                $results[$specActivityId] = $activityFeedbacks;
            }
        }

        $this->results = $results;
    }

    /* Getters/Setters
     */

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
