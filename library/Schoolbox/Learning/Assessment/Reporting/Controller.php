<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting;

use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model as CriteriaModel;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as DialogAgentScenarioMapper;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Application\Exception\Misconfigured;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\CompositeFactoryFactory;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Mapper as DialogAgentResponseMessageMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Translator\ModelToArray as DialogAgentResponseMessageTranslator;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Button;
use Schoolbox\Learning\Assessment\DialogAgent\Model;
use Schoolbox\Learning\Assessment\DialogAgent\Response;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Timeline\Full as FullTimeline;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\AbstractUnit;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Learning\DialogAgent\ValidDialogAgentEnabled;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Error\Error;

/**
 * This fulfils the standard screens enforced in all learning activities via the \Schoolbox\Assessment\Controller.
 * These include the summary, status, response creation screens.
 *
 * Anything outside of that set typically end up in their own controller \Schoolbox\Learning\*\Controller elsewhere
 * to break away from the inheritance tree here.
 *
 * Whether or not that's a great idea is yet to be determined; generally inheritance isn't a good move if you can
 * recompose your objects to avoid it. Sadly we made this mistake so long ago and consolidated upon it.
 *
 * An alternative to putting your activity-specific methods in \Schoolbox\Learning\*\Controller would be to simply
 * dump them into the relevant subclass of \Schoolbox\Assessment\Controller to inherit its superclass' properties,
 * methods, and modcons. Both seem acceptable: the latter marries you into the inheritance tree, but splitting away
 * means you end up duping a lot of perms-based code which is kind of disgusting. You lose either way :(
 */
class Controller extends Assessment\Controller
{
    /**
     * Constructor.
     *
     * @param UserMapper $userMapper
     * @param ErrorMapper $errorMapper
     * @param AssessmentMapperProxy $assessmentMapper
     * @param WorkTypeMapper $workTypeMapper
     * @param Access $access
     * @param Transactor $transactor
     * @param BreadcrumbFactory $bakery
     * @param Mapper $termMapper
     * @param DataSource $participantDataSource
     * @param CourseSpecification\Mapper $courseSpecificationMapper
     * @param UnitSpecification\Mapper $unitSpecificationMapper
     * @param RosettaStone $rosettaStone
     * @param Replicator $replicator
     * @param LatestViewMapper $latestViewMapper
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper $lessonPlanDataModelMapper
     * @param DialogAgentDataModelMapper $dialogAgentDataModelMapper
     * @param DialogAgentScenarioMapper $dialogAgentScenarioMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
     * @param DialogAgentResponseMessageMapper $dialogAgentResponseMessageMapper
     * @param IdFinder $filtersAssessmentIdFinder
     * @param CompositeFactoryFactory $filtersFactoryFactory
     * @param Filter\Mapper $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param CourseImplementation\Mapper $courseImplementationMapper
     * @param UnitImplementation\Mapper $unitImplementationMapper
     * @param EditorTemplateHelper $editorTemplateHelper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecification\Mapper $courseSpecificationMapper,
        UnitSpecification\Mapper $unitSpecificationMapper,
        RosettaStone $rosettaStone,
        Replicator $replicator,
        LatestViewMapper $latestViewMapper,
        Project\ResultCache\Mapper $projectResultCacheMapper,
        LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        protected DialogAgentDataModelMapper $dialogAgentDataModelMapper,
        protected DialogAgentScenarioMapper $dialogAgentScenarioMapper,
        private DialogAgentResponseMapper $dialogAgentResponseMapper,
        private DialogAgentResponseMessageMapper $dialogAgentResponseMessageMapper,
        protected Filter\Filters\Assessment\IdFinder $filtersAssessmentIdFinder,
        protected Filter\CompositeFactoryFactory $filtersFactoryFactory,
        protected Filter\Mapper $filterMapper,
        protected Filter\Selection\Mapper $filterSelectionMapper,
        private CourseImplementation\Mapper $courseImplementationMapper,
        private UnitImplementation\Mapper $unitImplementationMapper,
        private EditorTemplateHelper $editorTemplateHelper
    ) {
        parent::__construct(
            $userMapper,
            $errorMapper,
            $assessmentMapper,
            $workTypeMapper,
            $access,
            $transactor,
            $bakery,
            $termMapper,
            $participantDataSource,
            $courseSpecificationMapper,
            $unitSpecificationMapper,
            $rosettaStone,
            $replicator,
            $latestViewMapper,
            $projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper,
            $dialogAgentScenarioMapper,
            $filtersAssessmentIdFinder,
            $filtersFactoryFactory,
            $filterMapper,
            $filterSelectionMapper,
            $courseImplementationMapper,
            $unitImplementationMapper,
            editorTemplateHelper: $editorTemplateHelper
        );
    }


    /**
     * Assessment Summary Screen for a Reporting activity: not used by anyone.
     *
     * At most it'd contain the metadata at the top and a list of feedbacks received, but staff should be favouring the
     * status screen instead, afaict.
     *
     * @param Model|IAssessable $assessment
     * @param User              $student
     * @return void
     * @throws Exception
     */
    public function getSummary(Model|IAssessable $assessment, User $student): void
    {
        /* Summary screens aren't used by anyone for Reporting Activities.
         *
         * jez.gray [11:59 AM, 11 March 2026]
         * I do not think it is required at this stage for anyone because we said students and parents couldn't access it at all at the minute.
         * Staff don't need it as there won't be a description or a submission history. So all it would have is their own grading - which shouldn't be needed.
         */
        throw new NotFound();
    }


    /**
     * Students cannot create responses for a Reporting activity: it's strictly (floating-) feedback-only
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws NotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        throw new Exception\NotFound();
    }


    /**
     * Students cannot create responses for a Reporting activity: it's strictly (floating-) feedback-only
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws Exception\NotFound
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        throw new Exception\NotFound();
    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @param bool        $isCreate
     * @param bool        $toParticipation
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false, bool $isCreate = false, bool $toParticipation = false): string
    {
        return parent::determineRedirect($assessment, $postReturnUrl, $defaultReturnUrl, $relocated, $isCreate);
    }


    /**
     * @inheritDoc
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        return [
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
            'allMarkTypes' => $this->getAllMarkTypes($assessment),
            'allGradeSchemes' => $this->getAllGradeSchemes(),
        ];
    }


    /**
     * @inheritDoc
     *
     * @param IAssessable|Model $assessment
     * @return void
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     */
    public function getStatus(IAssessable|Model $assessment): void
    {
        /** All implementations if $assessment is a spec, or $assessment itself if it's already one */
        $implementations = new \Schoolbox\GenericCollection(Model::class);

        // impl assessments we can just grab the details, spec we need to get the unit assessments first
        if ($assessment->isImplementation()) {
            $implementations->add($assessment);
        } else {
            foreach ($this->getUnitAssessments($assessment) as $unitAssessment) {
                /** @var Model $unitAssessment */
                $implementations->add($unitAssessment);
            }
        }

        $userTranslator = new \Schoolbox\User\Translator\ModelToArray();

        $assessmentsPayload = [];

        foreach ($implementations as $implementation) {
            $participantPayload = [];

            foreach ($implementation->getAllParticipants() as $participant) {
                /** @var Participant $participant */

                /* Are they excluded? ignore them */
                if ($participant->isExcluded()) {
                    continue;
                }

                /* Students cannot create responses for a Reporting activity: it's strictly (floating-) feedback-only
                 */
                $authoritativeResponse = null;

                /* This would have been populated via the AI
                 */
                $authoritativeFeedback = null;

                if ($participant->getAllFeedbacks()->count()) {
                    $authoritativeFeedback = $implementation->getAuthoritativeFeedback($participant, true);
                }

                $routeParams = [
                    'assessment'   => $implementation,
                    'assessmentId' => $implementation->getId(),
                    'student'      => $participant->getUser(),
                    'studentId'    => $participant->getUser()->getId(),
                ];

                $participantPayload[] = [
                    'id' => $participant->getUser()->getId(),
                    'user' => $userTranslator->convertLinkedUser($participant->getUser()),
                    'response' => $authoritativeResponse,
                    'feedback' => $authoritativeFeedback,
                    '_routes' => [
                        'summary' => $this->getRouteIfAuthorized('learningAssessment.getSummary', $routeParams),
                    ],
                ];
            }

            $assessmentsPayload[] = [
                'id' => $implementation->getId(),
                'folder' => [
                    'id' => $implementation->getBaseContext()->getFolder()->getId(),
                    'title' => $implementation->getBaseContext()->getFolder()->getTitle(),
                ],
                'participants' => $participantPayload,
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/reporting/status.twig',
            [
                'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                    [
                        sprintf('Status for %s', $assessment->getTitle()),
                    ]
                ),
                'assessment' => $assessment,
                'assessments' => $assessmentsPayload,
            ]
        );
    }
}
