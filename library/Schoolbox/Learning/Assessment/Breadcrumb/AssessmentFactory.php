<?php
/**
 * AssessmentFactory
 *
 * Create a trail of crumbs for a particular assessment based
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Breadcrumb;

use \Schoolbox\Learning\Assessment\Project;
use \Schoolbox\Resource\Folder\Model as Folder;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

class AssessmentFactory
{
    /**
     * @var IAssessable
     */
    protected $assessment;

    /**
     * AssessmentFactory constructor.
     * @param IAssessable $assessment
     */
    public function __construct(IAssessable $assessment)
    {
        $this->setAssessment($assessment);
    }

    /**
     * Produce breadcrumb trail
     *
     * @return array
     */
    public function produce()
    {
        $trail   = [];
        $trail[] = ['Learning', '#'];

        $context = $this->getAssessment()->getContext();

        if ($context instanceof Folder) {
            $trail[] = [$context->getName(), "/homepage/{$context->getId()}"];
        } elseif ($context instanceof UnitImplementation) {
            $trail[] = [$context->getTitle(), "/homepage/{$context->getId()}"];
        } elseif ($context instanceof UnitSpecification) {
            $trail[] = [$context->getFolder()->getName(), "/homepage/{$context->getId()}"];
        } elseif ($context instanceof Project) {
            $trail[] = [$context->getTitle(), "/learning/assessment/{$context->getId()}"];
        }

        /* Additional project node, if you're creating within a project
         */
        if ($this->getAssessment() instanceof Project) {
            $trail[] = [$this->getAssessment()->getTitle(), "/learning/assessment/{$this->getAssessment()->getId()}"];
        }

        return $trail;
    }

    /* Getters/Setters
     */

    /**
     * @return IAssessable
     */
    protected function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param IAssessable $assessment
     * @return $this
     */
    protected function setAssessment($assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }
}