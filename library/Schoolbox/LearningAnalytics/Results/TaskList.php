<?php
/**
 * TaskList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\LearningAnalytics\Registry;
use Schoolbox\LearningAnalytics\Metadata;

class TaskList
{

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry;

    /**
     * Request metadata
     *
     * @var Metadata
     */
    private $metadata;

    /**
     * @var Collection|Collection[]|IAssessable[][]
     */
    private $activities = [];

    /**
     * Constructor
     *
     * @param Registry $registry
     * @param Metadata $metadata
     */
    public function __construct(Registry $registry, Metadata $metadata)
    {
        $this->setRegistry($registry);
        $this->setMetadata($metadata);

        /* A unique list of all tasks which get matched with data in TaskResults
         *
         * A task that has a mark is one that has either
         * + A submission box instance with a numeric id, or
         * + An assessment instance with a numeric id
         *
         * Tasks always come with objects for submission_box and assessment,
         * but they could be entirely empty. That denotes a task which does not
         * require a mark.
         *
         * Ignore all tasks which have no mark requirement. Ignoring them
         * here prevents matching in TaskResults(), effectively removing
         * them from the boxplots.
         */

        $this->setActivities(new Collection());

        foreach ($this->getRegistry()->getUnitImplementations() as $unitImplementation) {
            $this->extractActivities($unitImplementation);
        }
    }


    /**
     * @param AssessmentContext $context
     * @param callable          $filter
     */
    protected function extractActivities(AssessmentContext $context, callable $filter = null)
    {
        // by default, leave tasks out of it
        $filter = $filter ?: function ($input) { return is_object($input) && !$input instanceof Task\Model; };

        foreach ($context->getAssessables() as $activity) {
            if ($activity instanceof AssessmentContext) {
                // recurse into project
                $this->extractActivities($activity, $filter);

            } else {
                $specActivity = $activity->getSpecificationAssessment();

                if ($filter($specActivity)) {
                    $this->getActivities()->offsetSet($specActivity->getId(), $specActivity);
                }
            }
        }

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
     * Set all tasks
     *
     * @param Collection|Collection[]|IAssessable[][] $activities
     */
    private function setActivities(Collection $activities)
    {
        $this->activities = $activities;
    }

    /**
     * Get all tasks
     *
     * @return Collection|Collection[]|IAssessable[][]
     */
    public function getActivities()
    {
        return $this->activities;
    }

    /**
     * Set metadata
     *
     * @param Metadata $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

}
