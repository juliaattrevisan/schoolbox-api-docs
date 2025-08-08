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
use Aura\Router\Generator;
use Exception;
use Schoolbox\Collection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\LessonPlan;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use UnexpectedValueException;

class Factory
{
    /**
     * Constructor
     *
     * @param Generator  $routeGenerator Used to generate route URLs
     * @param Authorizer $authorizer     Used to verify that route URLs are accessible
     * @param Access     $access         Used to verify homepage access permissions
     */
    public function __construct(
        private Generator $routeGenerator,
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
     */
    public function produceFromAssessment(IAssessable $assessment, User $user): Collection
    {
        $assessmentContext = $assessment->getContext();
        $breadcrumbs       = new Collection();
        $breadcrumbs->add(['Learning', $this->routeGenerator->generate('learning.getClasses')]);

        if ($assessmentContext instanceof UnitImplementation) {
            // Generate trail like 'Learning'->$unitImp->$unitSpec (eg. 'Learning'->'9ENGA'->'Romeo and Juliet').
            $unitImp        = $assessmentContext;
            $unitImpFolder  = $unitImp->getFolder();
            $unitSpecFolder = $unitImp->getSpecification()->getFolder();
            $breadcrumbs->add($this->generateBreadcrumbForFolder($unitImpFolder, $user));
            // Point back to the unit impl folder, rather than the spec
            $breadcrumbs->add($this->generateBreadcrumbForFolder($unitImpFolder, $user, $unitSpecFolder->getTitle()));
        } elseif ($assessmentContext instanceof UnitSpecification) {
            // Generate trail like 'Learning'->$unitSpec (eg. 'Learning'->'Romeo and Juliet').
            $unitSpec       = $assessmentContext;
            $unitSpecFolder = $unitSpec->getFolder();
            $breadcrumbs->add($this->generateBreadcrumbForFolder($unitSpecFolder, $user));
        } elseif ($assessmentContext instanceof Project) {
            // Generate trail like one of:
            //  * 'Learning->$unitImp->$unitSpec->$project (eg. 'Learning'->'9ENGA'->'Romeo and Juliet'->'Project title').
            //  * 'Learning->$unitSpec->$project           (eg. 'Learning'->'Romeo and Juliet'->'Project title').
            $project        = $assessmentContext;
            $projectContext = $project->getContext();

            if (!($projectContext instanceof UnitImplementation || $projectContext instanceof UnitSpecification)) {
                throw new UnexpectedValueException(
                    'Variable $projectContext is expected to be an instance of either UnitImplementation or UnitSpecification.' .
                    "Found variable of class '" . get_class($projectContext) . "'."
                );
            }

            // We have already dealt with cases UnitSpecification and UnitImplementation above, so get breadcrumbs up to unitSpec level recursively.
            $breadcrumbs = $this->produceFromAssessment($project, $user);

            $routeParams = [
                'assessment'   => $project,
                'assessmentId' => $project->getId(),
                'studentId'    => $user->getId()
            ];

            $breadcrumbs->add(
                [
                    $project->getTitle(),
                    (
                        // In some circumstances the user will not be authorised to view the 'status' page.
                        // In that case, show the 'summary' page instead.
                        ($this->authorizer->isPathAuthorizedForUser('learningAssessment.getStatus', $routeParams, $user)) ?
                        $this->routeGenerator->generate('learningAssessment.getStatus', $routeParams) :
                        $this->routeGenerator->generate('learningAssessment.getSummary', $routeParams)
                    )
                ]
            );
        } else {
            // Assessment belongs directly to subject (eg. '9ENGA').
            $subject       = $assessmentContext;
            $subjectFolder = $subject->getFolder();

            $breadcrumbs->add($this->generateBreadcrumbForFolder($subjectFolder, $user));
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
     * @param User              $user
     * @return Collection
     * @throws RouteNotFound
     */
    public function produceFromContext(AssessmentContext $context, ?IAssessable $assessment = null, \Schoolbox\User $user): Collection
    {
        $trail = new Collection();
        $trail->add(['Learning', $this->routeGenerator->generate('learning.getClasses')]);

        if ($context instanceof Folder) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getId()}"]);
        } elseif ($context instanceof UnitImplementation) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getFolder()->getId()}"]);
        } elseif ($context instanceof UnitSpecification) {
            $trail->add([$context->getTitle(), "/homepage/{$context->getFolder()->getId()}"]);
        } elseif ($context instanceof Project) {
            /* Lesson Plans introduced a preceding Selection step so the project breadcumb
             * becomes clickable. None of the other assessment types do this, so the studentId
             * has never actually been needed; this route was never visible in that usage
             */
            if ($assessment instanceof LessonPlan\Model) {
                $trail->add([$context->getTitle(), $this->routeGenerator->generate('learningAssessment.getSummary', ['assessmentId' => $context->getId(), 'studentId' => $user->getId()])]);
            } else {
                $trail->add([$context->getTitle(), $this->routeGenerator->generate('learningAssessment.getSummary', ['assessmentId' => $context->getId()])]);
            }
        }

        $assessmentType = $assessment instanceof IAssessable ? $assessment->getTypeName() : 'Activity';
        $trail->add(["Create $assessmentType"]);

        return $trail;
    }

    /**
     * Helper function for generating a breadcrumb for a folder.
     * * breadcrumb has the folder's title
     * * if user has permission to view the folder, it links to that folder:
     *   otherwise there is no link
     *
     * @param Folder      $folder
     * @param User        $user
     * @param string|null $titleOverride
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function generateBreadcrumbForFolder(Folder $folder, User $user, ?string $titleOverride = null): array
    {
        return [
            $titleOverride ?? $folder->getTitle(),
            $this->access->getFolderAccess($user, $folder->getId())
                ? "/homepage/{$folder->getId()}"
                : null,
        ];
    }
}
