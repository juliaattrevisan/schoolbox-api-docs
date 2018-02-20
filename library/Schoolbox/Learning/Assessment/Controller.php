<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress_Date_Format;
use DomainException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeSchemeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeScheme;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Mapper\DefinitionException;
use UnexpectedValueException;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Application\Exception as ApplicationException;
use Aura\Web\Exception\InvalidStatusCode;

abstract class Controller extends ListController implements IAssessmentController
{

    /** @var BreadcrumbFactory */
    protected $bakery;

    /** @var WorkTypeMapper */
    private $workTypeMapper;

    /** @var Term\Mapper */
    private $termMapper;

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
        Participant\DataSource $participantDataSource
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $participantDataSource);
        $this->bakery          = $bakery;
        $this->workTypeMapper  = $workTypeMapper;
        $this->termMapper      = $termMapper;
    }


    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @level 165
     *
     * @param AssessmentContext $context
     * @throws DomainException If user has interaction but not participant record
     * @throws ServiceNotFound If unable to locate HTML web Output renderer in DI
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws UnexpectedValueException If unable to derive a project's participant collection
     */
    public function getCreateAssessmentForm(AssessmentContext $context)
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
     * @api /learning/assessments/:assessmentId/modify
     * @level 165
     * @param IAssessable $assessment
     */
    public function getUpdateAssessmentForm(IAssessable $assessment)
    {
        $data = $this->getAssessmentAsTwigArray($assessment->getContext(), $assessment);
        $data['breadcrumbs'] = $this->getBakery()->produceFromAssessment($assessment,$this->getUser())->add([$assessment->getTitle()]);
        $data['returnUrlName'] = Redirect::RETURN_URL;
        $data['returnUrl'] = $this->getRequest()->query->get(Redirect::RETURN_URL);

        /* Curriculum Codes prefilled from those associated with the assessment itself
         */
        $data['curriculumNodes'] = $assessment->getCurriculumNodes();

        $data['custom_assessment_participation'] = $this->getConfigLoader()->get('custom_assessment_participation');
        $data['selfAssessmentEnabled'] = $this->getConfigLoader()->get('self_assessment_enabled');

        $template = "@learningAssessment/update.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @api   POST /learning/assessments/:assessmentId
     * @level 165
     *
     * @param IAssessable $assessment
     */
    public function postUpdateAssessment(IAssessable $assessment)
    {
        /* Modify particular fields
         */
        if ($this->getRequest()->server['HTTP_X_HTTP_METHOD_OVERRIDE']) {
            $this->patchUpdateAssessment($assessment);
            return;
        }

        /* Regular update
         */
        try {
            $assessmentInfo = $this->persistAssessment(
                $this->getUser(),
                $this->getRequest()->post,
                $assessment
            );
            $internalReturnUrl = $assessmentInfo[Redirect::RETURN_URL];
        }
        catch (DomainException $e) {
            $this->sendError('Could not save assessment', [ $e->getMessage() ]);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        $postReturnUrl = $this->getRequest()->post->get(Redirect::RETURN_URL);

        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
        } else {
            $returnUrl = $this->determineRedirect($assessment, $postReturnUrl, $internalReturnUrl);
        }

        // we're done here
        $this->redirect($returnUrl);
    }

    /**
     * Get an overview of rubric marks
     *
     * @param IAssessable $assessment
     */
    public function getOverview(IAssessable $assessment)
    {
        // For now, only DueWork uses this route.
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * Download overview of rubric marks
     *
     * @param IAssessable $assessment
     */
    public function downloadOverview(IAssessable $assessment)
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
     * @level 165
     * @param IAssessable $assessment
     * @return void
     */
    public function postDeleteAssessment(IAssessable $assessment)
    {
        // If not already deleted?
        if (!$assessment->isDeleted()) {
            $this->getAssessmentMapper()->delete($assessment);
        }

        $folder = $assessment->getContext()->getFolder();
        $postReturnUrl = $this->getRequest()->query[Redirect::RETURN_URL];

        $returnUrl = $this->determineRedirect($assessment, $postReturnUrl, "/homepage/{$folder->getId()}");

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([
                Redirect::RETURN_URL => $returnUrl
            ]));
        }
        else {
            $this->redirect($returnUrl);
        }
    }


    /**
     * Redirect method
     *
     * Sends you to a status, summary, or preview screen depending on who is
     * currently logged in and what they are viewing
     *
     * @api GET /learning/assessment/:assessmentId
     * @param IAssessable $assessment
     * @throws RouteNotFound If unable to generate a requested route
     * @throws InvalidStatusCode If we attempt to set an invalid HTTP response code
     * @deprecated
     * @todo This route is very fragile. Its auth check (loggedIn) is potentially misleading.
     *       Callers are the best informed on whether they wish to view a single user's intersection with
     *       the assessment (summary), or a group of users (create/modify/markbook/status etc)
     */
    public function getAssessment(IAssessable $assessment)
    {
        try {
            $redirect      = $this->determineGetAssessmentRedirect($assessment);
            $redirOverride = $this->getRequest()->query->get(Redirect::RETURN_URL);
            if ($redirOverride) {
                $redirect .= '?' . Redirect::RETURN_URL . "={$redirOverride}";
            }
            $this->getResponse()->redirect->to($redirect);
        } catch (ApplicationException\Exception $e) {
            // As far as this user is concerned, this resource does not exist.
            $this->getResponse()->status->setCode(404);
        }
    }


    /**
     * @param AssessmentContext $context
     * @param IAssessable       $assessment
     * @return array
     */
    private function getAssessmentAsTwigArray(AssessmentContext $context, IAssessable $assessment)
    {
        if (!$assessment->getId()) {
            // new assessment: set default values
            $assessment
                ->setDueAt(new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
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
            'context'               => $context,
            'contextType'           => $contextType,
            'baseContextType'       => AssessmentContextType::objectToString($context->getBaseContext()),
            'breadcrumbs'           => $this->getBakery()->produceFromContext($context, $assessment),
            'assessment'            => $assessment,
            'folder'                => $context->getFolder(),
            'assessmentType'        => AssessmentType::objectToString($assessment),
            'markVisibility'        => $assessment->getMarkVisibility(),
            'availableVisibilities' => $this->getAvailableVisibilities(),
            'allWorkTypes'          => $this->workTypeMapper->findAll(),
        ];

        $data = array_merge($data, $this->getAssessmentTwigData($assessment));

        return $data;
    }


    /**
     * All the possible Mark Types to choose from for this particular $assessmentType
     *
     * @param IAssessable $assessable
     * @return array
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
     * @return BreadcrumbFactory
     */
    protected function getBakery()
    {
        return $this->bakery;
    }

    /**
     * Available visibilities for assessments
     *
     * TODO move this to a helper I guess? It's already in obsolete \Schoolbox\Assessment\Widgets::getAvailableOptions
     *
     * @return array
     */
    protected function getAvailableVisibilities()
    {
        $availableVisibilities = [
            IAssessable::MARK_VISIBILITY_PRIVATE,
        ];
        switch ($this->getConfigLoader()->get('submission_visibility_max')) {
            case IAssessable::MARK_VISIBILITY_PRIVATE:
                break;

            case IAssessable::MARK_VISIBILITY_STUDENT:
                $availableVisibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
                break;

            case IAssessable::MARK_VISIBILITY_PARENT_STUDENT:
            default:
                $availableVisibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
                $availableVisibilities[] = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
                break;
        }
        return $availableVisibilities;
    }

    /**
     * @param IAssessable $assessment
     * @return array
     */
    protected abstract function getAssessmentTwigData(IAssessable $assessment);

    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl an encoded URL
     * @param string      $defaultReturnUrl
     * @return string
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '')
    {
        /**
         * First priority is a the post return URL
         * Then if this is in a project, and if so then go to project status
         * Otherwise go to default return url
         */
        if ($postReturnUrl) {
            return Redirect::decode($postReturnUrl);
        } elseif ($assessment->getContext() instanceof Project) {
            return $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getContext()->getId() ]);
        }

        return $defaultReturnUrl;
    }

    /**
     * @param IMarkScheme $scheme
     * @return string
     */
    protected function deriveSchemeName(IMarkScheme $scheme)
    {
        $c = get_class($scheme);
        return substr($c, strrpos($c, '\\')+1);
    }

    /**
     * Update particular fields of an existing IAssessable
     *
     * Default Abstract implementation does nothing, Concrete class to override.
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function patchUpdateAssessment(IAssessable $assessment)
    {
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }

    /**
     * @return Term\Mapper
     */
    protected function getTermMapper()
    {
        return $this->termMapper;
    }


    /**
     * Returns a redirect route, or throw an exception.
     *
     * Order of preference:
     * * A spec?          => Summary (must be able to simulate participation, but not create interactions)
     * * Status viewable? => Status
     * * A participant?   => Summary
     *
     * @param IAssessable $assessment
     * @return string
     * @throws RouteNotFound If unable to generate a requested route
     * @throws ApplicationException\Exception If current user should not have permission to view this page
     */
    private function determineGetAssessmentRedirect(IAssessable $assessment): string
    {
        // Is status page viewable?
        $statusRoute = $this->getRouteIfAuthorized('learningAssessment.getStatus', [
            'assessment'   => $assessment,
            'assessmentId' => $assessment->getId(),
        ], $this->getUser());
        if ($statusRoute) {
            return $statusRoute;
        }


        // Participants should go to their summary page
        if ($assessment->isParticipant($this->getUser())) {
            $summaryRoute = $this->getRouteIfAuthorized('learningAssessment.getSummary', [
                'assessmentId' => $assessment->getId(),
                'assessment'   => $assessment,
                'studentId'    => $this->getUser()->getId(),
                'student'      => $this->getUser(),
            ]);
            if ($summaryRoute) {
                return $summaryRoute;
            }
        }

        // If a parent of a participant, go to the summary page of the first non-excluded participant, if parents can READ+ folder context
        // TODO - this is not ideal. Consider a teacher has N children participating in the assessment
        /** @var mixed[][] $children */
        foreach ($this->getUser()->getChildren() as $child) {
            $childSummaryRoute = $this->getRouteIfAuthorized('learningAssessment.getSummary', [
                'assessmentId' => $assessment->getId(),
                'assessment'   => $assessment,
                'studentId'    => $child->getId(),
                'student'      => $child,
            ]);
            if ($childSummaryRoute) {
                return $childSummaryRoute;
            }
        }

        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
    }
}
