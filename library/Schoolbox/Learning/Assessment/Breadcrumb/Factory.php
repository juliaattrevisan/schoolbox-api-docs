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
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

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
     * @param User $user
     * @return Collection
     */
    public function produceFromAssessment(IAssessable $assessment, User $user)
    {
        $trail = new Collection;
        $trail->add(['Learning', $this->router->generate('learning.getClasses')]);

        $context = $assessment->getContext();

        if ($context instanceof Project) {
            $path = $this->router->generate('learningAssessment.getAssessment', ['assessmentId' => $context->getId()]);

            $trail = $this->produceFromAssessment($context, $user);

        } elseif ($context instanceof UnitSpecification) {

            $path = $this->router->generate('course.modifyCourse', ['id' => $context->getCourse()->getId()]).'#'.$context->getId();

        } elseif ($context instanceof UnitImplementation) {
            if ($user->getRole()->isStaff()) {
                $path = $this->router->generate('learningMarkbook.getMarkbookForClass', ['folder' => $context->getFolder(), 'folderId' => $context->getFolder()->getId()]);

            } elseif ($user->getRole()->isStudent()) {
                $path = "/homepage/{$context->getFolder()->getId()}";

            } else {
                $path = '#';
            }

            $trail->add([$context->getFolder()->getTitle(), $path]);
            $path = "/homepage/{$context->getFolder()->getId()}";

        } else {
            $path = "/homepage/{$context->getFolder()->getId()}";
        }

        $trail->add([$context->getTitle(), $path]);

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
        $trail->add(['Learning', $this->router->generate('learning.getClasses')]);

        if ($context instanceof Folder) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getId()}"]);
        } elseif ($context instanceof UnitImplementation) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getFolder()->getId()}"]);
        } elseif ($context instanceof UnitSpecification) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getFolder()->getId()}"]);
        } elseif ($context instanceof Project) {
            $trail->add([$context->getTitle(), $this->router->generate('learningAssessment.getSummary', ['assessmentId' => $context->getId()])]);
        }

        $assessmentType = $assessment instanceof IAssessable ? $assessment->getTypeName() : 'Activity';
        $trail->add(["Create $assessmentType"]);

        return $trail;
    }
}


