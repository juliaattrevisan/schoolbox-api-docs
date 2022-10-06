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
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception;
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
     * @throws \Exception
     * @see library/Schoolbox/Breadcrumbs::getAssessmentCrumbs() - similar to this function but for www-style pages.
     */
    public function produceFromAssessment(IAssessable $assessment, User $user): Collection
    {
        $trail = new Collection();
        $trail->add(['Learning', $this->router->generate('learning.getClasses')]);

        $assessmentContext = $assessment->getContext();

        if ($assessmentContext instanceof Project) {
            // Assessment belongs to project.
            $project        = $assessmentContext;
            $projectContext = $project->getContext();

            if ($projectContext instanceof UnitImplementation) {
                // Assessment belongs to subject->unit->project (eg. '9ENGA'->'Romeo and Juliet'->'Project'), so add crumb for subject.
                $unitSpec       = $projectContext->getSpecification();
                $unitSpecFolder = $unitSpec->getFolder();
                $subjectFolder  = $projectContext->getFolder();
                $trail->add([$subjectFolder->getTitle(), "/homepage/{$subjectFolder->getId()}"]);
            } elseif ($projectContext instanceof UnitSpecification) {
                // Assessment belongs to unit->project specification, so cannot add crumb for subject.
                // NOTE: UnitSpecifications are not attached to subjects.
                //       Think of them as templates of units that become implementations only when they are attached to subjects.
                $unitSpec       = $projectContext;
                $unitSpecFolder = $unitSpec->getFolder();
            } else {
                throw new \UnexpectedValueException("Unknown type '" . get_class($projectContext) . "' for project context.");
            }

            $trail->add([$unitSpecFolder->getTitle(), "/homepage/{$unitSpecFolder->getId()}"]); // Add crumb for unit.

            $routeParams = [
                'assessment'   => $project,
                'assessmentId' => $project->getId(),
                'studentId'    => $user->getId()
            ];

            $trail->add( // Add crumb for project.
                [
                    $assessmentContext->getTitle(),
                    (
                        // Link to the project status page if the user has access, otherwise to the summary page.
                        ($this->authorizer->isPathAuthorizedForUser('learningAssessment.getStatus', $routeParams, $user)) ?
                        $this->router->generate('learningAssessment.getStatus', $routeParams) :
                        $this->router->generate('learningAssessment.getSummary', $routeParams)
                    )
                ]
            );
        } elseif ($assessmentContext instanceof UnitImplementation) {
            // Assessment belongs to subject->unit (eg. '9ENGA'->'Romeo and Juliet'), so add crumbs for subject and unit.
            $unit           = $assessmentContext;
            $subjectFolder  = $unit->getFolder(); // TESTED - Works with assess_id 111.
            $unitSpecFolder = $unit->getSpecification()->getFolder();
            $trail->add([$subjectFolder->getTitle(), "/homepage/{$subjectFolder->getId()}"]); // Add breadcrumb for subject.
            $trail->add([$unitSpecFolder->getTitle(), "/homepage/{$unitSpecFolder->getId()}"]); // Add breadcrumb for unit.
        } elseif ($assessmentContext instanceof UnitSpecification) {
            // Assessment belongs to unit specification, so add breadcrumb for unit only (not subject).
            // NOTE: UnitSpecifications are not attached to subjects.
            //       Think of them as templates of units that become implementations only when they are attached to subjects.
            $unitSpec       = $assessmentContext;
            $unitSpecFolder = $unitSpec->getFolder();
            $trail->add([$unitSpecFolder->getTitle(), "/homepage/{$unitSpecFolder->getId()}"]); // Add crumb for unit.
        } else {
            $subject       = $assessmentContext;
            $subjectFolder = $subject->getFolder();

            if ($this->access->getFolderAccess($user, $subjectFolder->getId())) {
                // Assessment belongs directly to subject (eg. '9ENGA').
                $trail->add([$subject->getTitle(), "/homepage/{$subjectFolder->getId()}"]); // Add crumb for subject.
            }
        }

        return $trail;
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
