<?php

/**
 * Factory
 *
 * Create a trail of crumbs based on available information regarding an existing
 * IAssessable $assessment, or derived from a contextId/contextType tuple
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Breadcrumb;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Exception;
use Schoolbox\Collection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Factory
{
    /**
     * Constructor
     *
     * @param Router     $router     Used to generate route URLs
     * @param Authorizer $authorizer Used to verify that route URLs are accessible
     * @param Access     $access     Used to verify homepage access permissions
     */
    public function __construct(
        private Router $router,
        private Authorizer $authorizer,
        private Access $access
    ) {
    }

    /**
     * Produce breadcrumb trail from a previously-existing IAssessable $assessment
     *
     * These instances typically get hydrated with enough information to
     * determine a meaningful trail of crumbs
     *
     * @param IAssessable $assessment
     * @param User        $user
     * @return Collection
     * @throws RouteNotFound
     * @throws Exception
     * @see library/Schoolbox/Breadcrumbs::getAssessmentCrumbs() - similar to this function but for www-style pages.
     */
    public function produceFromAssessment(IAssessable $assessment, User $user): Collection
    {
        $assessmentContext = $assessment->getContext();
        $breadcrumbs       = new Collection();
        $breadcrumbs->add(['Learning', $this->router->generate('learning.getClasses')]);

        if (
            $assessmentContext instanceof UnitImplementation ||
            $assessmentContext instanceof UnitSpecification ||
            $assessmentContext instanceof Project
        ) {
            $crumbs = $assessmentContext->getBreadcrumbs();

            foreach ($crumbs as $crumb) {
                $breadcrumbs->add($crumb);
            }
        } else {
            // Assessment belongs directly to subject (eg. '9ENGA').
            $subject       = $assessmentContext;
            $subjectFolder = $subject->getFolder();

            if ($this->access->getFolderAccess($user, $subjectFolder->getId())) {
                $breadcrumbs->add([$subject->getTitle(), "/homepage/{$subjectFolder->getId()}"]);
            }
        }

        return $breadcrumbs;
    }

    /**
     * Produce breadcrumb trail for a new IAssessable $assessment
     *
     * These won't have enough information to determine a meaningful trail of crumbs,
     * so we give it the contextId/contextType, and decide from there
     *
     * @param AssessmentContext $context
     * @param IAssessable|null  $assessment
     * @return Collection
     * @throws RouteNotFound
     */
    public function produceFromContext(AssessmentContext $context, IAssessable $assessment = null): Collection
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
