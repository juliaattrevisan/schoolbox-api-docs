<?php

/**
 * Factory
 *
 * Create a trail of crumbs based on available information regarding an existing
 * IAssessable $asseessment, or derived from a contextId/contextType tuple
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Breadcrumb;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Factory
{
    /**
     * @var Router
     */
    private $router;


    /**
     * @var Authorizer
     */
    private $authorizer;


    /**
     * @var Access
     */
    private $access;


    /**
     * Constructor
     *
     * @param Router $router Used to generate route URLs
     */
    /**
     * Constructor
     *
     * @param Router     $router     Used to generate route URLs
     * @param Authorizer $authorizer Used to verify that route URLs are
     *                               accessible
     * @param Access     $access     Used to verify homepage access permissions
     */
    public function __construct(Router $router, Authorizer $authorizer, Access $access)
    {
        $this->router     = $router;
        $this->authorizer = $authorizer;
        $this->access     = $access;
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
        $trail = new Collection();
        $trail->add(['Learning', $this->router->generate('learning.getClasses')]);

        $context = $assessment->getContext();
        $path = '';

        if ($context instanceof Project) {
            $routeName   = 'learningAssessment.getStatus';
            $routeParams = [
                'assessment'   => $context,
                'assessmentId' => $context->getId(),
                'studentId' => $user->getId()
            ];

            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $user)) {
                $path = $this->router->generate($routeName, $routeParams);
            } else {
                $path = $this->router->generate('learningAssessment.getSummary', $routeParams);
            }

            $trail = $this->produceFromAssessment($context, $user);
        } elseif ($context instanceof UnitSpecification) {
            $path = $this->router->generate('course.modifyCourse', ['id' => $context->getCourse()->getId()]) . '#' . $context->getId();
        } elseif ($context instanceof UnitImplementation) {
            if ($this->access->getFolderAccess($user, $context->getFolder()->getId())) {
                $path = "/homepage/{$context->getFolder()->getId()}";
            }

            $trail->add([$context->getFolder()->getTitle(), $path]);
        } elseif ($this->access->getFolderAccess($user, $context->getFolder()->getId())) {
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
     * @param IAssessable|null $assessment
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
