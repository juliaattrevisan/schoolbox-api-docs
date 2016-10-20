<?php
/**
 * Factory
 *
 * Create a trail of crumbs based on available information regarding an existing
 * IAssessable $asseessment, or derived from a contextId/contextType tuple
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Breadcrumb;

use Aura\Router\Router;
use \Schoolbox\Collection;

use \Schoolbox\Learning\Assessment\Project;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use \Schoolbox\Resource\Folder\Model as Folder;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplementationDataSource;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationDataSource;
use \Schoolbox\Mapper\Exception\Deleted as DeletedException;

class Factory
{
    /**
     * @var Router
     */
    private $router;

    /**
     * Constructor
     *
     * @param Router $router Used to generate route URLs
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Produce breadcrumb trail from a previously-existing IAssessable $assessment
     *
     * These instances typically get hydrated with enough information to
     * determine a meaningful trail of crumbs
     *
     * @param IAssessable $assessment
     * @return Collection
     */
    public function produceFromAssessment(IAssessable $assessment)
    {
        $trail = new Collection;
        $trail->add(['Learning', '#']);

        $context = $assessment->getContext();

        if ($context instanceof Project) {
            $path = $this->router->generate('learningAssessment.getSummary', ['assessmentId' => $context->getId()]);
        } else {
            $path = "/homepage/{$context->getId()}";
        }
        $trail->add([$context->getTitle(), $path]);

        /* Additional project node, if you're creating within a project
         */
        if ($assessment instanceof Project) {
            $params = ['assessmentId' => $assessment->getId()];
            $route = $this->router->generate('learningAssessment.getUpdateAssessment', $params);
            $trail->add([$assessment->getTitle(), $route]);
        }

        return $trail;
    }

    /**
     * Produce breadcrumb trail for a new IAssessable $assessment
     *
     * These won't have enough information to determine a meaningful trail of crumbs
     * so we give it the contextId/contextType, and decide from there
     *
     * @param AssessmentContext $context
     * @param IAssessable $assessment
     * @return Collection
     */
    public function produceFromContext(AssessmentContext $context, IAssessable $assessment = null)
    {
        $trail = new Collection();
        $trail->add(['Learning', '#']);

        if ($context instanceof Folder) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getId()}"]);
        } elseif ($context instanceof UnitImplementation) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getId()}"]);
        } elseif ($context instanceof UnitSpecification) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getId()}"]);
        } elseif ($context instanceof Project) {
            $trail->add([$context->getTitle(), $this->router->generate('learningAssessment.getSummary', ['assessmentId' => $context->getId()])]);
        }

        $assessmentType = $assessment instanceof IAssessable ? $assessment->getTypeName() : 'Assessment';
        $trail->add(["Create $assessmentType", '#']);

        return $trail;
    }
}


