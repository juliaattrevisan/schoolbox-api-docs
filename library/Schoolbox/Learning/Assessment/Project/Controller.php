<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project;

use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Filter;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

/**
 * Class Controller
 *
 * @package Schoolbox\Learning\Assessment\Project
 */
class Controller extends Assessment\Controller
{
    private const PARTICIPANT_CHUNK_SIZE = 100;

    /**
     * Controller constructor.
     *
     * @param EditorTemplateHelper|null $editorTemplateHelper
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
        RosettaStone $rosettaStone,
        Replicator $replicator,
        LatestViewMapper $latestViewMapper,
        protected Project\ResultCache\Mapper $projectResultCacheMapper,
        protected LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        protected LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        protected \Schoolbox\Admin\DialogAgent\Scenario\Mapper $dialogAgentScenarioMapper,
        protected ?Filter\Filters\Assessment\IdFinder $filtersAssessmentIdFinder = null,
        protected ?Filter\CompositeFactoryFactory $filtersFactoryFactory = null,
        protected ?Filter\Mapper $filterMapper = null,
        protected ?Filter\Selection\Mapper $filterSelectionMapper = null,
        protected ?CourseImplementationMapper $courseImplementationMapper = null,
        protected ?UnitImplementationMapper $unitImplementationMapper = null,
        private ?EditorTemplateHelper $editorTemplateHelper = null,
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
            $this->projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper,
            $dialogAgentScenarioMapper,
            $filtersAssessmentIdFinder,
            $filtersFactoryFactory,
            $filterMapper,
            $filterSelectionMapper,
            $courseImplementationMapper,
            $unitImplementationMapper,
            null,
            null,
            null,
            null,
            null,
            null,
            $editorTemplateHelper
        );
    }

    /**
     * @param Project|IAssessable $assessment
     * @return array
     * @throws UnexpectedValueException
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $summatorMapping = new Assessment\Summator\Mapping();

        try {
            $selectedSummator = $summatorMapping->decompose($assessment->getSummator());
        } catch (InvalidArgumentException $e) {
            //no selected summator if none exists
            $selectedSummator = null;
        }

        return [
            'types'                   => new Assessment\AssessmentType(),
            'selectedSummator'        => $selectedSummator,
            'allSummators'            => $summatorMapping,
            'allMarkTypes'            => $this->getAllMarkTypes($assessment),
            'allGradeSchemes'         => $this->getAllGradeSchemes(),
        ];
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws NotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // route not implemented
        throw new NotFound();
    }


    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws NotFound
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // route not implemented
        throw new NotFound();
    }


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param Project|IAssessable $assessment
     */
    public function getStatus(IAssessable $assessment): void
    {
        // Calculate summary totals for display purposes
        $participants = $assessment->getAllParticipants();

        $assessmentStats = new GenericCollection(Assessment\Statistics::class);
        $assessmentStats[$assessment->getId()] = new Assessment\Statistics($assessment);
        foreach ($assessment->getAssessables() as $subAssessable) {
            $assessmentStats[$subAssessable->getId()] = new Assessment\Statistics($subAssessable);
        }

        $participantStats = new GenericCollection(Assessment\ParticipantStatistics::class);
        foreach ($participants as $participant) {
            $participantStats[$participant->getUser()->getId()] = new Assessment\ParticipantStatistics($participant);
        }

        $twigData = [
            'breadcrumbs'      => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle()]),
            'baseContext'      => $assessment->getBaseContext(),
            'assessment'       => $assessment,
            'assessmentStats'  => $assessmentStats,
            'aImpls'           => [],
            'returnUrl'        =>
                $this->requestQuery->get(Redirect::RETURN_URL)
                    ? Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL))
                    : $this->getSession()->getHistory(),
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];

        // impl assessments we can just grab the details, spec we need to get the unit assessments first
        if (!$assessment->isSpecification()) {
            $twigData['aImpls'][$assessment->getId()] = [
                'participantCount' => null,
                'participantCursor' => null,
                'assessment' => $assessment,
                'participantStats' => $participantStats,
            ];
        } else {
            $assessments = $this->getUnitAssessments($assessment, false);
            /** @var Project $assessment */
            foreach ($assessments as $assessment) {
                // Calculate summary totals for display purposes
                $participants = $assessment->getAllParticipants();

                $participantStats = new GenericCollection(Assessment\ParticipantStatistics::class);
                foreach ($participants as $participant) {
                    $participantStats[$participant->getUser()->getId()] = new Assessment\ParticipantStatistics($participant);
                }

                $twigData['participantStats'][$assessment->getId()] = $participantStats;
                $twigData['aImpls'][$assessment->getId()] = [
                    'participantCount' => $participants->count(),
                    'participantCursor' => null,
                    'assessment' => $assessment,
                    'participantStats' => $participantStats,
                    'participants' => $participants,
                ];
            }
        }

        /**
         * somehow there is a hydration inside
         * getUnitAssessments
         * $assessments = $this->getAssessmentMapper()->findAllByIds($assessmentIds);
         *
         * which is causing deleted sub-assessments to appear in $assessment->getAssessables()
         * lets just filter them out here
         */
        $twigData['assessment']->setAssessables($twigData['assessment']->getAssessables()->filterBy('deletedAt', null));

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/project/status/staff.twig',
            $twigData
        );
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 165
     * @param IAssessable|Project $assessment
     * @param User                $student
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Exception
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $subActivities = $assessment->getAssessables();

        // filter out any sub-activities that have not yet been published
        $visibility    = new Assessment\Visibility($this->getAccess());
        $now           = Alaress_Date_Format::now();
        $mutator       = new Participant\AssessmentListMutator(
            $this->getUser(),
            $visibility,
            new ExternalUser(),
            $now,
            true
        );
        $classFolder          = $assessment->getBaseContext()->getFolder();
        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $classFolder);

        // If $student is an instructor, all assessments appear on project summary page
        // *except for* assessments which the instructor has participated to and then has been excluded from.

        $subActivities = $subActivities
            ->map([$mutator, 'map'])
            ->filter([$mutator, 'filter'])
            ->filter(static function (IAssessable $subAssessable) use ($student, $isViewerAnInstructor) {
                try {
                    $participant = $subAssessable->getParticipant($student);
                    return !$participant->isExcluded();
                } catch (UnexpectedValueException $e) {
                    // not participant
                    return $isViewerAnInstructor;
                }
            });

        // hack the project object for good measure
        $assessment->setAssessables($subActivities);

        // How should the jewelry transition?
        $tickTransitions      = [
            'incomplete'      => $isViewerAnInstructor ? 'teacherAssessed' : 'selfAssessed',
            'selfAssessed'    => $isViewerAnInstructor ? 'teacherAssessed' : 'incomplete',
            'teacherAssessed' => 'incomplete',
        ];

        /**
         * record view
         */
        $lastView = new Assessment\Views\LatestView(
            $this->getAccess(),
            $this->latestViewMapper,
            new \Schoolbox\External\User(
                $this->getConfigLoader()->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getUserMapper()
            )
        );
        $lastView->addNewView($assessment, $student, $this->getUser());

        // Ideally make this an instance var instead of instantiating all the time
        // Except you can't, $this->getRouter() returns null in __construct()?
        $twigData = [
            'lastViews'       => $lastView->getTwigViews($assessment, $student, $isViewerAnInstructor),
            'breadcrumbs'     => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'student'         => $student,
            'assessment'      => $assessment,
            'transitions'     => $tickTransitions,
            'returnUrlSuffix' => '?' . Redirect::RETURN_URL . '=' . Redirect::encode($this->request->getUri()->getPath()),
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/project/summary/student.twig',
            $twigData
        );
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
        /**
         * Always go to the status screen when a project has been created,
         * but send the return url with it
         *
         * If the project was only updated, use the parent logic to determine the redirect instead
         */
        if ($isCreate) {
            $redirect = $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]);

            if ($postReturnUrl) {
                $redirect .= '?' . Redirect::RETURN_URL . "=$postReturnUrl";
            }

            return $redirect;
        }

        return parent::determineRedirect($assessment, $postReturnUrl, $defaultReturnUrl, $relocated, $isCreate);
    }


    /**
     * fetch and display the participants for a specific class assessment, that is part of the course assessment
     * this is not chromed, so we can async it into the dom and it _should_ just work...
     *
     * @api GET /learning/assessments/{assessmentId}/status/{classAssId}
     * @param IAssessable $courseAss
     * @param IAssessable $classAss
     * @return void
     * @throws Exception\NotFound
     * @throws \Twig\Error\Error
     */
    public function getClassStatus(IAssessable $courseAss, IAssessable $classAss): void
    {
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningAssessment/project/status/class.twig',
            $this->getClassStudentInfo($courseAss, $classAss)
        );
    }

    /**
     * fetch and display the participants for a specific class assessment, that is part of the course assessment
     * this is not chromed, so we can async it into the dom and it _should_ just work... (chunked version)
     *
     * @api GET /learning/assessments/{assessmentId}/status/{classAssId}/chunk
     * @param IAssessable $courseAss
     * @param IAssessable $classAss
     * @return void
     * @throws Exception\NotFound
     * @throws \Twig\Error\Error
     */
    public function getClassStatusChunk(IAssessable $courseAss, IAssessable $classAss): void
    {
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningAssessment/project/status/classChunk.twig',
            $this->getClassStudentInfo($courseAss, $classAss)
        );
    }

    /**
     * fetch participants for a specific class assessment, that is part of the course assessment
     * this collates and may chunk the data as needed
     *
     * @param IAssessable $courseAss
     * @param IAssessable $classAss
     * @return array
     * @throws Exception\NotFound
     */
    private function getClassStudentInfo(IAssessable $courseAss, IAssessable $classAss): array
    {
        $assessments = new GenericCollection(IAssessable::class, [$classAss]);
        $this->getAssessmentMapper()->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        $twigData = [
            'baseContext'      => $courseAss->getBaseContext(),
            'assessment'       => $courseAss,
            'aImpls'           => [],
            'showGroups'       => true,
            'returnUrl'        =>
                $this->requestQuery->get(Redirect::RETURN_URL)
                    ? Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL))
                    : $this->getSession()->getHistory(),
            'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
        ];
        /** @var Project $assessment */
        foreach ($assessments as $assessment) {
            // Calculate summary totals for display purposes
            $participants = $assessment->getAllParticipants();

            // slice/cut the participants to a chunked count
            $participantCount = $participants->count();
            $participantCursor = (int)$this->requestQuery->get('participantCursor', 0);
            if ($participantCursor < 0) {
                $participantCursor = 0;
            }
            $participants = $participants->slice($participantCursor, self::PARTICIPANT_CHUNK_SIZE);

            $participantStats = new GenericCollection(Assessment\ParticipantStatistics::class);
            foreach ($participants as $participant) {
                $participantStats[$participant->getUser()->getId()] = new Assessment\ParticipantStatistics($participant);
            }

            $twigData['participantStats'][$assessment->getId()] = $participantStats;
            $twigData['aImpl'] = [
                'participantCount' => $participantCount,
                'participantCursor' => $participantCursor + self::PARTICIPANT_CHUNK_SIZE,
                'assessment' => $assessment,
                'participantStats' => $participantStats,
                'participants' => $participants,
            ];
        }

        return $twigData;
    }
}
