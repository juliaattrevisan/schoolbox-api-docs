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
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use UnexpectedValueException;

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

        if ($assessmentContext instanceof UnitImplementation) {
            // Assessment belongs to subject->unit.
            // Generate trail like 'Learning->$subject->$unit' (eg. 'Learning'->'9ENGA'->'Romeo and Juliet').
            $unitImpl      = $assessmentContext;
            $subjectFolder = $unitImpl->getFolder();
            $trail->add([$subjectFolder->getTitle(), "/homepage/{$subjectFolder->getId()}"]); // Add breadcrumb for subject.

            $unitSpecFolder = $unitImpl->getSpecification()->getFolder();

            $lastCrumbTitle = $unitSpecFolder->getTitle();
            $lastCrumbPath  = "/homepage/{$unitSpecFolder->getId()}";
        } elseif ($assessmentContext instanceof UnitSpecification) {
            // Assessment belongs to unit specification.
            // Generate trail like 'Learning->$unit' (eg. 'Learning'->'Romeo and Juliet').
            // NOTE: UnitSpecifications are not attached to subjects.
            //       Think of them as templates of units that become implementations only when they are attached to subjects.
            $unitSpec       = $assessmentContext;
            $unitSpecFolder = $unitSpec->getFolder();

            $lastCrumbTitle = $unitSpecFolder->getTitle();
            $lastCrumbPath  = "/homepage/{$unitSpecFolder->getId()}";
        }
        elseif ($assessmentContext instanceof Project) {
            // Assessment belongs to $subject->$unit->project or $unit->$project.
            // Generate trail like one of:
            //  * 'Learning->$subject->$unit->$project' (eg. 'Learning'->'9ENGA'->'Romeo and Juliet'->'Project title').
            //  * 'Learning->$unit->$project'           (eg. 'Learning'->'Romeo and Juliet'->'Project title').
            $project        = $assessmentContext;
            $projectContext = $project->getContext();

            if ($projectContext instanceof UnitImplementation || $projectContext instanceof UnitSpecification) {
                // We have already handled the case of UnitImplementation and UnitSpecification above, so forget about
                // the trail we have started so far, and instead create a new $trail recursively.  Then we can add the
                // project as the last crumb.
                $trail = $this->produceFromAssessment($project, $user);

                $routeParams = [
                    'assessment'   => $project,
                    'assessmentId' => $project->getId(),
                    'studentId'    => $user->getId()
                ];

                $lastCrumbTitle = $project->getTitle();
                $lastCrumbPath  = (
                    // In some circumstances the user will not be authorised to view the 'status' page.
                    // In that case, show the 'summary' page instead.
                    ($this->authorizer->isPathAuthorizedForUser('learningAssessment.getStatus', $routeParams, $user)) ?
                    $this->router->generate('learningAssessment.getStatus', $routeParams) :
                    $this->router->generate('learningAssessment.getSummary', $routeParams)
                );
            } else {
                throw new UnexpectedValueException(
                    'The context of $project is expected to be an instance of either UnitImplementation or UnitSpecification.'
                );
            }
        } else {
            // Assessment belongs directly to subject (eg. '9ENGA').
            $subject       = $assessmentContext;
            $subjectFolder = $subject->getFolder();

            if ($this->access->getFolderAccess($user, $subjectFolder->getId())) {
                $lastCrumbTitle = $subject->getTitle();
                $lastCrumbPath  = "/homepage/{$subjectFolder->getId()}";
            } else {
                // The user does not have access to the subject folder, so generate an empty trail.
                $lastCrumbTitle = null;
                $lastCrumbPath  = null;
            }
        }

        if ($lastCrumbPath !== null) {
            $trail->add([$lastCrumbTitle, $lastCrumbPath]); // Add the last crumb to the trail.
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
