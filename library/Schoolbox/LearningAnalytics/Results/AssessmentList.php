<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Assessment\Term\Model as Term;

class AssessmentList
{
    /**
     * @var Collection|Collection[]|IAssessable[][]
     */
    private $assessments;

    /** @var  Term */
    private $term;

    /**
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     * @param Term $term
     */
    public function __construct(GenericCollection $unitImplementations, Term $term)
    {
        $this->assessments = new GenericCollection(IAssessable::class);
        $this->term = $term;

        foreach ($unitImplementations as $unitImplementation) {
            $this->extractAssessments($unitImplementation);
        }
    }


    /**
     * Extracts AssessmentSpecs from relevant AssessmentImpls
     *
     * @param AssessmentContext $context
     * @param callable          $filter
     */
    protected function extractAssessments(AssessmentContext $context, callable $filter = null)
    {
        // by default, leave tasks out of it
        $filter = $filter ?: function ($input) { return is_object($input) && !$input instanceof Task\Model; };

        foreach ($context->getAssessables() as $activity) {
            if ($activity instanceof AssessmentContext) {
                // recurse into project
                $this->extractAssessments($activity, $filter);

            } else {
                // Is the activity due in the specified term ?
                $dueAfterTermStart = $activity->getDueAt()->gte($this->term->getStart());
                $dueBeforeTermEnd = $this->term->getActualFinish()->isNull() || $activity->getDueAt()->lt($this->term->getActualFinish());
                if(!$dueAfterTermStart || !$dueBeforeTermEnd){
                    continue;
                }

                $specActivity = $activity->getSpecificationAssessment();

                if ($filter($specActivity)) {
                    $this->assessments->offsetSet($specActivity->getId(), $specActivity);
                }
            }
        }

    }

    /* Getters/Setters
     */

    /**
     * Get all assessments
     *
     * @return Collection|Collection[]|IAssessable[][]
     */
    public function getAssessments()
    {
        return $this->assessments;
    }
}
