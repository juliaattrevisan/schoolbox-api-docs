<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Web\Controller\Exception as ControllerException;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeSchemeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeScheme;
use Schoolbox\Learning\Assessment\Feedback\Visibility as FeedbackVisibility;
use Schoolbox\Learning\Assessment\Seb\Payload;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Mapper\DefinitionException;
use UnexpectedValueException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;

/**
 * Class Controller
 *
 * This controller contains the Create/Modify screen that's shared among all the IAssessables:
 * DueWork, Task, Project. They were all originally one screen, though their abilities have
 * since expanded and diverged.
 *
 * @package Schoolbox\Learning\Assessment
 */
abstract class Controller extends ListController implements IAssessmentController
{

    /**
     * @var WorkTypeMapper
     */
    private $workTypeMapper;


    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /** @var CourseSpecificationMapper */
    private $courseSpecificationMapper;

    /** @var Replicator */
    private $replicator;

    /**
     * @var LatestViewMapper
     */
    protected LatestViewMapper $latestViewMapper;

    /**
     * Controller constructor.
     *
     * @param UserMapper             $userMapper
     * @param ErrorMapper            $errorMapper
     * @param DataModel\MapperProxy  $assessmentMapper
     * @param WorkTypeMapper         $workTypeMapper
     * @param Access                 $access
     * @param Transactor             $transactor
     * @param BreadcrumbFactory      $bakery
     * @param Term\Mapper            $termMapper
     * @param Participant\DataSource $participantDataSource
     * @param CourseSpecificationMapper $courseSpecificationMapper
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @param Replicator    $replicator
     * @param LatestViewMapper       $latestViewMapper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator,
        LatestViewMapper $latestViewMapper
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $participantDataSource, $unitSpecificationMapper);

        $this->bakery                    = $bakery;
        $this->workTypeMapper            = $workTypeMapper;
        $this->termMapper                = $termMapper;
        $this->replicator                = $replicator;
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        $this->latestViewMapper          = $latestViewMapper;
    }


    /**
     * Redirect the standard assessment URL to a user specific version
     * @api   GET /learning/assessment/{assessmentId}
     * @since 165
     * @param IAssessable $assessment
     * @throws RouteNotFound
     */
    public function getAssessment(IAssessable $assessment): void
    {

        /* Presume a default url.
         *
         * If you're a parent you'll end up at a dead end if you don't have
         * any children which can unlock the summary screen but that would
         * be an unusual scenario (what are you even doing here then?)
         *
         *  - you could be given read access to a class page and view its course outline
         *    but you don't have any children associated with your session
         *
         *  - or you don't have any children participating in them
         *
         *  - the Hide Due Work Details config is activated
         *
         * If you're a student, you'll end up at a dead end if you've been excluded
         * from participating in the assessment but try to get there anyway
         */
        $defaultUrl = $this->getRouter()->generate('learningAssessment.getSummary', [
            'assessmentId' => $assessment->getId(),
            'studentId' => $this->getUser()->getId()
        ]);

        /* If you're a parent,
         * find the first child who can visit this url
         * and pass that id into the router instead so you can witness details
         *
         * That Hide Due Work Details config is defined in buildAuthRuleCanViewSummary()
         * which uses CanSeeParticipation()
         */
        $parentUrl = null;

        if ($this->getUser()->getRole()->isParent()) {
            foreach ($this->getUser()->getChildren() as $child) {
                $parentUrl = $this->getRouteIfAuthorized('learningAssessment.getSummary', [
                    'assessment' => $assessment,
                    'assessmentId' => $assessment->getId(),
                    'student' => $child,
                    'studentId' => $child->getId()
                ]);

                if ($parentUrl) {
                    break;
                }
            }
        }

        $this->redirect($parentUrl ?: $defaultUrl);
    }


    /**
     * Redirect the standard assessment URL to a user specific version with SEB key
     * @param IAssessable $assessment
     * @param User        $student
     * @throws RouteNotFound
     * @since 211
     * @api   GET /learning/assessments/:assessmentId/:userId/seb
     */
    public function getSummaryViaSeb(IAssessable $assessment, User $student): void
    {
        $defaultUrl = $this->getRouter()->generate(
            'learningAssessment.getSummary',
            [
                'assessmentId' => $assessment->getId(),
                'studentId'    => $student->getId(),
            ]
        );

        if ($this->request->server->get('HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH')) {
            $defaultUrl .= '?' . http_build_query([Payload::SEB_KEY => $this->request->server->get('HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH')]);
        }
        $this->redirect($defaultUrl);
    }


    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @since 165
     *
     * @param AssessmentContext $context
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException If unable to derive a project's participant collection
     */
    public function getCreateAssessmentForm(AssessmentContext $context): void
    {
        $type = $this->getRequest()->query->get('type');

        // build a stub
        $assessment = AssessmentType::stringToObject($type, $context);

        // create a dynamic plan on the assessment to get started
        $assessment->getPlans()->add((new Plan($assessment))->setIsDynamic(true));

        $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment);

        $data = $this->getAssessmentAsTwigArray($context, $assessment);

        /* Curriculum Codes prefilled from those associated with context folder
         */
        $data['curriculumNodes'] = $context->getFolder()->getCurriculumNodes();

        /* Default Visibility comes from an LMS Policy setting in config
         */
        $data['markVisibility'] = $this->getConfigLoader()->get('submission_access');

        // Did the caller request a returnUrl?
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);

        if ($returnUrl) {
            $data[Redirect::RETURN_URL] = $returnUrl;
        }

        $data['custom_assessment_participation'] = $this->getConfigLoader()->get('custom_assessment_participation');
        $data['selfAssessmentEnabled'] = $this->getConfigLoader()->get('self_assessment_enabled');

        $data['defaultGradeGroup'] = $this->getConfigLoader()->get('default_grade_group');

        // Relocation is available only for update
        $data['canRelocate'] = false;

        /* Course Managed Field and associated checkbox in the ui
         */
        $courseManagedFields = new CourseManagedFields($this->getUser(), $assessment);

        $data['courseManaged'] = [
            'isVisible' => $courseManagedFields->isVisible(),
            'isMutable' => $courseManagedFields->isMutable(),
            'isReadonly' => $courseManagedFields->isReadonly(),
        ];

        // either output the fragment to the mage, or use the synchronous form
        if ($this->getRequest()->isXhr()) {
            $content = $this->buildUnthemedHtmlOutput("@learningAssessment/$type/create.twig", $data);
        } else {
            $content = $this->buildHtmlOutput('@learningAssessment/create.twig', $data);
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * Get the form to update an existing Assessment's details
     *
     * @api   /learning/assessments/:assessmentId/modify
     * @since 165
     * @param IAssessable $assessment
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function getUpdateAssessmentForm(IAssessable $assessment): void
    {
        $data = $this->getAssessmentAsTwigArray($assessment->getContext(), $assessment);

        $data['breadcrumbs'] = $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle()]);
        $data['returnUrlName'] = Redirect::RETURN_URL;
        $data['returnUrl'] = $this->getRequest()->query->get(Redirect::RETURN_URL);

        /* Curriculum Codes prefilled from those associated with the assessment itself
         */
        $data['curriculumNodes'] = $assessment->getCurriculumNodes();

        $data['custom_assessment_participation'] = $this->getConfigLoader()->get('custom_assessment_participation');
        $data['selfAssessmentEnabled'] = $this->getConfigLoader()->get('self_assessment_enabled');

        $data['defaultGradeGroup'] = $this->getConfigLoader()->get('default_grade_group');

        $data['canRelocate'] = $assessment->isSpecification();

        /* Course Managed Field and associated checkbox in the ui
         */
        $courseManagedFields = new CourseManagedFields($this->getUser(), $assessment);

        $data['courseManaged'] = [
            'isVisible' => $courseManagedFields->isVisible(),
            'isMutable' => $courseManagedFields->isMutable(),
            'isReadonly' => $courseManagedFields->isReadonly(),
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput("@learningAssessment/update.twig", $data));
    }


    /**
     * Gets a list of units and projects, to which the assessment can be moved
     * @api   /learning/assessments/:assessmentId/locations
     * @since 201
     * @param IAssessable $assessment
     */
    public function getLocationForm(IAssessable $assessment): void
    {
        if ($assessment->isImplementation()) {
            // not interested
            return;
        }

        $currentUnitSpec = $assessment->getBaseContext();
        try {
            $currentCourseSpec = $this->courseSpecificationMapper->findByFolderId($currentUnitSpec->getFolder()->getParent()->getId());
        } catch (MapperException $e) {
            return;
        }
        $allUnitSpecs = $currentCourseSpec->getUnits();

        $unitProjects = new Collection();

        // if this assessment is not a project, add projects tp relocatable locations
        if (!$assessment instanceof Project) {
            foreach ($allUnitSpecs as $unitSpec) {
                $projects    = $this->getAssessmentMapper()->createCollection();
                $assessables = $this->getAssessmentMapper()->findAllByUnitSpecificationId($unitSpec->getId());
                foreach ($assessables as $assessable) {
                    if (!$assessable instanceof Project) {
                        continue;
                    }
                    $projects->add($assessable);
                }
                $unitProjects->offsetSet($unitSpec->getId(), $projects);
            }
        }
        $this->getResponse()->content->set($this->buildContent(
            '@learningAssessment/fragments/assessmentLocationList.twig',
            [
                'units'        => $allUnitSpecs,
                'unitProjects' => $unitProjects,
                'current'      => $assessment->getContext()->getLocationId()
            ]
        ));
    }

    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @api   POST /learning/assessments/:assessmentId
     * @since 165
     *
     * @param IAssessable $assessment
     * @throws ControllerException
     * @throws RouteNotFound
     * @throws \Exception
     */
    public function postUpdateAssessment(IAssessable $assessment): void
    {
        /* Modify particular fields
         * This is accessed when you tick Skip Unsubmitted in the Inline Grader
         */
        if ($this->getRequest()->server->get('HTTP_X_HTTP_METHOD_OVERRIDE')) {
            $this->patchUpdateAssessment($assessment);

            return;
        }

        /* Regular update while you're creating the assessment, otherwise
         */
        try {
            $assessmentInfo = $this->persistAssessment(
                $this->getUser(),
                $this->getRequest()->post,
                $assessment
            );
        } catch (DomainException $e) {
            $this->sendError('Could not save assessment. ', [ $e->getMessage() ]);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        $postReturnUrl = $this->getRequest()->post->get(Redirect::RETURN_URL);
        $returnUrl     = $this->determineRedirect(
            $assessmentInfo['assessment'],
            $postReturnUrl,
            $assessmentInfo[Redirect::RETURN_URL],
            $assessmentInfo['relocated']
        );

        // we're done here
        $this->redirect($returnUrl);
    }


    /**
     * Get an overview of rubric marks
     *
     * @param IAssessable $assessment
     * @throws \Exception
     */
    public function getOverview(IAssessable $assessment): void
    {
        // For now, only DueWork uses this route.
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * Download overview of rubric marks
     *
     * @param IAssessable $assessment
     * @throws \Exception
     */
    public function downloadOverview(IAssessable $assessment): void
    {
        // For now, only DueWork uses this route.
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * Delete the given assessment.
     *
     * Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     * On failure, respond with '4XX' or '5XX' as appropriate
     *
     * @api   POST /learning/assessments/{assessmentId}/delete
     * @since 165
     * @param IAssessable $assessment
     * @return void
     * @throws LogicException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws MapperException
     */
    public function postDeleteAssessment(IAssessable $assessment): void
    {
        // If not already deleted?
        if (!$assessment->isDeleted()) {
            $notifier = $this->getAssessmentMapper()->getNotifier();
            $before   = $notifier->dehydrate($assessment);
            $this->getAssessmentMapper()->delete($assessment);
            $notifier->notify($assessment, $this->getUser(), $before);
        }

        $folder = $assessment->getContext()->getFolder();
        $postReturnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);

        $returnUrl = $this->determineRedirect($assessment, $postReturnUrl, "/homepage/{$folder->getId()}");

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([
                Redirect::RETURN_URL => $returnUrl
            ]));
        } else {
            $this->redirect($returnUrl);
        }
    }


    /**
     * @param AssessmentContext $context
     * @param IAssessable       $assessment
     * @return array
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    private function getAssessmentAsTwigArray(AssessmentContext $context, IAssessable $assessment): array
    {
        if (!$assessment->getId()) {
            // new assessment: set default values
            $due = new Alaress_Date_Format(new \DateTime("+1 day"));
            $due->endOfDay();
            $due->setTimeFromString($this->getConfigLoader()->get('lms_duetime_default'));
            $assessment
                ->setDueAt($due)
                ->setCloseAt(new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
                ->setWeight(0);

            if ($assessment instanceof Quiz\Model) {
                $assessment
                    ->setPublishAt(new Alaress_Date_Format(new \DateTime('+1 hour')))
                    ->setOpenAt(new Alaress_Date_Format(new \DateTime('+1 hour')));
            } else {
                $assessment
                    ->setPublishAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                    ->setOpenAt(new Alaress_Date_Format(new \DateTime('00:00:00')));
            }
        }

        $contextType = AssessmentContextType::objectToString($context) . 's';

        /* The usual info everything requires
         */
        $data = [
            'context'                  => $context,
            'contextType'              => $contextType,
            'baseContextType'          => AssessmentContextType::objectToString($context->getBaseContext()),
            'breadcrumbs'              => $this->getBakery()->produceFromContext($context, $assessment),
            'assessment'               => $assessment,
            'folder'                   => $context->getFolder(),
            'assessmentType'           => AssessmentType::objectToString($assessment),
            'markVisibility'           => $assessment->getMarkVisibility(),
            'availableVisibilities'    => FeedbackVisibility::getAvailableVisibilities($this->getConfigLoader()->get('submission_visibility_max')),
            'allWorkTypes'             => $this->workTypeMapper->findAll(),

            /* Capabilities happen to have the same mark types as its DueWork IAssessable container,
             * so we don't need a different method than the existing getAllMarkTypes
             */
            'capabilityMarkTypes'      => $this->getAllMarkTypes($assessment),
            'capabilityGradingSchemes' => $this->getAllGradeSchemes()
        ];

        $data = array_merge($data, $this->getAssessmentTwigData($assessment));

        return $data;
    }


    /**
     * All the possible Mark Types to choose from for this particular $assessmentType
     *
     * @param IAssessable $assessable
     * @return array
     * @throws UnexpectedValueException
     */
    protected function getAllMarkTypes(IAssessable $assessable)
    {
        return MarkSchemeFactory::getAvailableMarkTypes($assessable);
    }


    /**
     * Gets all grading schemes.
     *
     * TODO DI this if you feel like it
     *
     * @return GradeScheme[]|Collection
     */
    protected function getAllGradeSchemes()
    {
        return GradeSchemeMapper::getInstance()->findAllWithGradeLevels();
    }


    /**
     * @param IAssessable $assessment
     * @return array
     */
    abstract protected function getAssessmentTwigData(IAssessable $assessment): array;


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl an encoded URL
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false): string
    {
        /**
         * First priority is a the post return URL
         * Then if this is in a project, and if so then go to project status
         * Otherwise go to default return url
         */
        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
            if ($relocated && strpos($returnUrl, '/homepage/') === 0) {
                return '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
            }
            return $returnUrl;
        } elseif ($assessment->getContext() instanceof Project) {
            return $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getContext()->getId()]);
        }

        return $defaultReturnUrl;
    }


    /**
     * @param IMarkScheme $scheme
     * @return string
     */
    protected function deriveSchemeName(IMarkScheme $scheme): string
    {
        $c = get_class($scheme);
        return substr($c, strrpos($c, '\\') + 1);
    }


    /**
     * Update particular fields of an existing IAssessable
     *
     * Default Abstract implementation does nothing, Concrete class to override.
     *
     * @param IAssessable $assessment
     * @return void
     * @throws \Exception
     */
    public function patchUpdateAssessment(IAssessable $assessment): void
    {
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * @return Term\Mapper
     */
    protected function getTermMapper(): \Schoolbox\Assessment\Term\Mapper
    {
        return $this->termMapper;
    }
}
