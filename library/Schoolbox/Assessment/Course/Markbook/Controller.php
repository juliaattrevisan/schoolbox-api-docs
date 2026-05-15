<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Alaress\Web\Controller as WebController;
use Alaress\HTMLPurifier\IPurifier;
use Schoolbox\Assessment\Course\Markbook\Export\CSV;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment\AssessmentType\Definition as AssessmentTypeDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\Assessment\Feedback\Visibility;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\DueWork\State;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Throwable;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

use function array_combine;
use function func_get_args;

class Controller extends WebController\Base
{
    /**
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Mapper                      $filterMapper
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Assessment\MapperProxy             $assessmentMapperProxy
     * @param Term\Mapper                        $termMapper
     * @param CourseImpl\Mapper                  $courseImplMapper
     * @param UnitImpl\Mapper                    $unitImplMapper
     * @param Access                             $access
     * @param StateMapper                        $stateMapper
     * @param IPurifier                          $purifier
     * @param EditorTemplateHelper               $editorTemplateHelper
     */
    public function __construct(
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        private Assessment\MapperProxy $assessmentMapperProxy,
        private Term\Mapper $termMapper,
        private CourseImpl\Mapper $courseImplMapper,
        private UnitImpl\Mapper $unitImplMapper,
        private Access $access,
        private StateMapper $stateMapper,
        private IPurifier $purifier,
        private EditorTemplateHelper $editorTemplateHelper
    ) {
    }


    /**
     * Redirects to the course markbook with the specified unit and folder pre-selected
     *
     * It is possible the specified unitSpec no longer belongs to the specified courseSpec.
     * It may have been deleted or moved to a different course. This is fine, ultimately
     * the assessments imported from the unitSpec belong to the courseSpec that was linked
     * at the time of import.
     *
     * @since 18.0
     * @param CourseSpec\Model $courseSpec With all units hydrated
     * @param int              $unitSpecId 0 => ignore
     * @param int              $folderId   0 => ignore
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getRedirect(CourseSpec\Model $courseSpec, int $unitSpecId, int $folderId): void
    {
        $getArgString = '';
        if ($unitSpecId || $folderId) {
            $classes = [];
            if ($unitSpecId) {
                $classes[] = Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class;
            }
            if ($folderId) {
                $classes[] = Filter\Filters\Assessment\Folder\CourseImports\Definition::class;
            }
            $filters = $this->filterMapper->findByClass($classes);
            $filters->indexBy('class');

            $selections = new GenericCollection(GenericCollection::class);

            // Unit Filter options
            if ($unitSpecId) {
                $unitFilter                                    = $filters[Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class];
                $selections[$unitFilter->getId()]              = $this->filterSelectionMapper->createCollection();
                $selections[$unitFilter->getId()][$unitSpecId] = $this->createSelectionModel($unitFilter, (string)$unitSpecId, true);
            }

            // Folder Filter options
            if ($folderId) {
                $folderFilter                                  = $filters[Filter\Filters\Assessment\Folder\CourseImports\Definition::class];
                $selections[$folderFilter->getId()]            = $this->filterSelectionMapper->createCollection();
                $selections[$folderFilter->getId()][$folderId] = $this->createSelectionModel($folderFilter, (string)$folderId, true);
            }
            $getArgString = '?' . (new Codec($this->filterMapper, $this->filterSelectionMapper))->convertSelectionsToQueryString($selections);
        }

        $route = $this->routeGenerator->generate('courseMarkbook.getMarkbook', ['courseSpecId' => $courseSpec->getId()]);

        $this->response = $this->redirectFound("{$route}{$getArgString}");
    }


    /**
     * Renders the outer part of the course markbook page with a filter panel
     *
     * @since 18.0
     * @param CourseSpec\Model $courseSpec
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getMarkbook(CourseSpec\Model $courseSpec): void
    {
        $queryArgs = $this->requestQuery->getArrayCopy();
        unset($queryArgs['page']);

        $page        = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $route       = $this->routeGenerator->generate('courseMarkbook.postMarkbook', ['courseSpecId' => $courseSpec->getId()]);
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), func_get_args(), $queryArgs);

        $this->response = $this->responseFromTemplate(
            '@courseMarkbook/courseMarkbookOuter.twig',
            [
                'courseSpec'  => $courseSpec,
                'viewee'      => $this->getUser(),
                'filterPanel' => $filterPanel,
                'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
            ],
            "Course Markbook: {$courseSpec->getTitle()}",
            'submission'
        );
    }

    /**
     * Stream a filtered markbook in CSV format
     *
     * @api   GET /learning/courseMarkbook/export/:courseSpecId?{filterQueryArgs}
     * @param CourseSpec\Model $courseSpec
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getMarkbookExport(CourseSpec\Model $courseSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $queryArgs = $this->requestQuery->getArrayCopy();
        [$filters,] = $this->convertRequestToFilters($courseSpec, $queryArgs, false);

        /**
         * if no assessment type was selected, then use the following defaults...
         */
        if (!isset($filters[AssessmentTypeDefinition::class])) {
            $filters[AssessmentTypeDefinition::class] = [
                AssessmentTypeDefinition::KEY_PROJECT           => AssessmentTypeDefinition::KEY_PROJECT,
                AssessmentTypeDefinition::KEY_QUIZ              => AssessmentTypeDefinition::KEY_QUIZ,
                AssessmentTypeDefinition::KEY_DIALOG_AGENT      => AssessmentTypeDefinition::KEY_DIALOG_AGENT,
                AssessmentTypeDefinition::KEY_DUE_WORK          => AssessmentTypeDefinition::KEY_DUE_WORK,
                AssessmentTypeDefinition::KEY_TASK              => AssessmentTypeDefinition::KEY_TASK,
                AssessmentTypeDefinition::KEY_LTI_ASSESSMENT    => AssessmentTypeDefinition::KEY_LTI_ASSESSMENT,
            ];
        }

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
        $courseMarkbook = $this->buildCourseMarkbook($courseSpec, $assessmentIds);

        $csvExporter = new CSV((bool)$this->getConfigLoader()->get('view_rank'));
        $showDrafts  = (bool)($queryArgs['showDrafts'] ?? false);
        $showDisplayMarks = (bool)($queryArgs['showDisplayMarks'] ?? false);
        $csvExporter->export(
            $courseMarkbook,
            ($showDrafts ? 'Draft - ' : '') . $courseSpec->getTitle() . ' - Markbook.csv',
            $showDrafts,
            $showDisplayMarks,
        );
        exit;
    }

    /**
     * Loads a filtered report into the Course Markbook page
     *
     * @param CourseSpec\Model $courseSpec
     * @since 18.0
     * @todo  Condense this and the GET route into a single route and distinguish via SYNC vs AJAX
     */
    public function postMarkbook(CourseSpec\Model $courseSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        try {
            $request = $this->requestPost['filter_selection'] ?: [];

            /**
             * @var null[]|Collection[]        $filters
             * @var Filter\Codec               $codec
             * @var Filter\Selection\Model[][] $selectionsPerFilter
             */
            // TODO add some intelligence around which filter settings to save (eg AssessmentType but not units)
            [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($courseSpec, $request, true);

            $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
            $courseMarkbook = $this->buildCourseMarkbook($courseSpec, $assessmentIds);

            $unitSpecs = $courseMarkbook->getCollapsedAssessments()->map(function (IAssessable $assessment) {
                $base = $assessment->getBaseContext();
                if ($base instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
                    // Implementation
                    return $base->getSpecification();
                } elseif ($base instanceof \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model) {
                    // Specification
                    return $base;
                }

                // no unit spec, return null
                return null;
            });

            $terms = $this->termMapper->findAll(['order' => ['start' => 'ASC']]);

            $data = [
                'rank' => (bool)$this->getConfigLoader()->get('view_rank') ? $courseMarkbook->getRanking() : null,
                'assessments' => $courseMarkbook->getCollapsedAssessments()->reindex()->map(function (IAssessable $assessment, $sequence) use ($courseMarkbook, $unitSpecs) {
                    $unitAssessmentsTranslator = new UnitAssessments(
                        $this->routeGenerator,
                        $this->getAuthorizer(),
                        $this->getUser(),
                        $this->getUser(),
                    );

                    return [
                        'id'                => $assessment->getId(),
                        'title'             => $assessment->getTitle(),
                        'typeName'          => $assessment->getTypeName(),
                        'assessmentType'    => $assessment->getFamilyName(),
                        'sequence'          => $sequence,
                        'isSpecification'   => $assessment->isSpecification(),
                        'unitSpec'          => $unitSpecs[$assessment->getId()] !== null ? [
                            /* for rendering the unit bars at the top of the page */
                            'id'    => $unitSpecs[$assessment->getId()]->getId(),
                            'title' => $unitSpecs[$assessment->getId()]->getTitle(),
                        ] : null,
                        /* stats here are only relevant for specs */
                        'stats' => $assessment->isSpecification() ? array_map(
                            /* Get the stats in the same format as returned by the class markbook */
                            fn($mark): string|null => $mark === null ? null : sprintf("%.2f %%", $mark),
                            [
                                'mean'      => $courseMarkbook->getAssessmentSpecAverages()[$assessment->getId()]['published'] ?? null,
                                'draftMean' => $courseMarkbook->getAssessmentSpecAverages()[$assessment->getId()]['any'] ?? null,
                            ]
                        ) : null,
                        /* only show the links for specs, for historical reasons i guess...
                         * if the spec is deleted, don't show any links as otherwise this will throw errors
                         */
                        '_links'          => $assessment->isDeleted() ? null : $unitAssessmentsTranslator->getLinks($assessment, true),
                        '_routes'         => [
                            /* this will change depending on if it's due work or reporting activity */
                            'postFeedbacks' => $assessment instanceof DueWork\Model ? $this->getRouteIfAuthorized(
                                'learningDueWorkFeedback.postParticipantFeedbacks',
                                [
                                    'assessment'   => $assessment,
                                    'assessmentId' => $assessment->getId(),
                                ]
                            ) : ($assessment instanceof Reporting\Model ? $this->getRouteIfAuthorized(
                                'learningReportingFeedback.postParticipantFeedbacks',
                                [
                                    'assessment'   => $assessment,
                                    'assessmentId' => $assessment->getId(),
                                ]
                            ) : null),
                        ],
                    ];
                })->indexByCallback(fn(array $assessment) => $assessment['id']),
                /**
                 * A "class implementation" is a course/class markbook-specific term.
                 * Since the class markbook can contain non-unit assessments, this is a generalised term to allow
                 * each of the course markbook's course implementation data and the class markbook's class data to
                 * be returned as a similar payload structure
                 *
                 * For the course markbook, there is one of these for each course implementation
                 */
                'classImpls' => $courseMarkbook->getCourseImpls()->map(
                    fn(CourseImpl\Model $courseImpl) => [
                        'folder' => [
                            'id'    => $courseImpl->getFolder()->getId(),
                            'title' => $courseImpl->getFolder()->getTitle(),
                        ],
                        'assessments' => $courseMarkbook->getCourseImportAssessments()->getOffset($courseImpl->getId())->map(
                            function (IAssessable $aImpl, int $assessmentId) use ($courseImpl, $courseMarkbook, $terms) {
                                /** Only due work has the concept of "instructorMarkScheme", others just use markScheme for now */
                                $instructorMarkScheme = $aImpl instanceof DueWork\Model
                                    ? $aImpl->getInstructorMarkScheme()
                                    : $aImpl->getMarkScheme();

                                if ($aImpl->getOriginalAssessmentId() === null) {
                                    $aImpl = $this->assessmentMapperProxy->loadCustomisationsIntoAssessment($aImpl);
                                }

                                $originalUrl = null;
                                if (null !== $aImpl->getOriginalAssessmentId() || $aImpl->getCustomisedAssessments()->count() > 0) {
                                    $originalAssessmentId = (null !== $aImpl->getOriginalAssessmentId()) ?
                                        $aImpl->getOriginalAssessmentId() :
                                        $aImpl->getId();
                                    $originalAssessment = $this->assessmentMapperProxy->findById($originalAssessmentId);
                                    if ($originalAssessment instanceof IAssessable) {
                                        $originalUrl = $this->getRouteIfAuthorized(
                                            'learningAssessment.getAssessmentParticipationForm',
                                            ['assessment' => $originalAssessment, 'assessmentId' => $originalAssessment->getId()]
                                        );
                                    }
                                }

                                /**
                                 * When an assessment is part of a project, we need a way to know which other
                                 * activities need to be updated if the marks for this one change.
                                 */
                                $parentProject = null;
                                if ($aImpl->getContext() instanceof Project) {
                                    $parentProject = $aImpl->getContext();
                                }

                                return [
                                    'id' => $aImpl->getId(),
                                    'markVisibility' => $aImpl->getMarkVisibility(),
                                    'courseManaged'  => $aImpl->getCourseManaged(),
                                    /* unused on the course markbook: */
                                    // 'weight'         => ...,
                                    'projectId'      => $parentProject?->getId(),
                                    'writable'       => $this->isAssessmentMarkable($this->getUser(), $aImpl, $terms),
                                    'instructorMarkScheme' => $instructorMarkScheme->generateContext(),
                                    'participants' => $courseMarkbook->getCourseImportStudents()[$courseImpl->getId()]->map(
                                        function (User $student) use ($aImpl, $courseImpl, $assessmentId, $courseMarkbook) {
                                            $participant = $aImpl->getParticipantIfExtant($student, false) ?? null;
                                            if ($participant === null) {
                                                return null;
                                            }

                                            /* Find the progress tick status, only relevant for tasks/dialogAgent */
                                            $progressTick = null;
                                            if ($aImpl instanceof Task\Model || $aImpl instanceof DialogAgent\Model) {
                                                $unitAssessmentsTranslator = new UnitAssessments(
                                                    $this->routeGenerator,
                                                    $this->getAuthorizer(),
                                                    $participant->getUser(),
                                                    $this->getUser(),
                                                );
                                                $progressTick = $unitAssessmentsTranslator->getProgressTick($aImpl, $participant);
                                            }

                                            $marks = $courseMarkbook->getStudentMarks()[$courseImpl->getId()][$assessmentId][$student->getId()] ?? null;

                                            return [
                                                'isExcluded' => $participant->isExcluded(),
                                                'hasSubmittedResponse' => $participant->hasSubmittedResponse(),
                                                'hasPublishedFeedback' => $participant->hasPublishedFeedback(),
                                                'progressTick' => $progressTick,
                                                'marks' => [
                                                    'published' => [
                                                        'norm'         => $marks['published']['norm'] ?? null,
                                                        'hr'           => $marks['published']['hr'] ?? null,
                                                        'hrInstructor' => $marks['published']['hrInstructor'] ?? null,
                                                        'comment'      => $this->purifier->purify($marks['published']['comment'] ?? null),
                                                        'resubmissionRequired' => $marks['published']['resubmissionRequired'] ?? false,
                                                        'responseId'   => $marks['published']['responseId'] ?? null,
                                                    ],
                                                    'draft' => [
                                                        'norm'         => $marks['draft']['norm'] ?? null,
                                                        'hr'           => $marks['draft']['hr'] ?? null,
                                                        'hrInstructor' => $marks['draft']['hrInstructor'] ?? null,
                                                        'comment'      => $this->purifier->purify($marks['draft']['comment'] ?? null),
                                                        'resubmissionRequired' => $marks['draft']['resubmissionRequired'] ?? false,
                                                        'responseId'   => $marks['draft']['responseId'] ?? null,
                                                    ],
                                                ],
                                                'plan' => [
                                                    'isDynamic' => $participant->getPlan()->getIsDynamic(),
                                                    'name' => $participant->getPlan()->getName(),
                                                    'dueDate' => $participant->getPlan()->getDueDate()?->getShort() ?? null,
                                                ],
                                            ];
                                        }
                                    ),
                                    'stats' => array_merge(
                                        array_map(
                                            /* Get the stats in the same format as returned by the class markbook */
                                            fn($mark): string|null => $mark === null ? null : sprintf("%.2f %%", $mark),
                                            [
                                                'mean'      => $courseMarkbook->getAssessmentImplAverages()[$aImpl->getId()]['published'] ?? null,
                                                'draftMean' => $courseMarkbook->getAssessmentImplAverages()[$aImpl->getId()]['any'] ?? null,
                                            ]
                                        ),
                                        [ 'markVisibility' => $aImpl->getMarkVisibility() ]
                                    ),
                                    '_routes' => [
                                        'learningAssessment.getStatus' => $this->routeGenerator->generate(
                                            'learningAssessment.getStatus',
                                            ['assessmentId' => $aImpl->getId()]
                                        ),
                                        'student' => $courseMarkbook->getCourseImportStudents()[$courseImpl->getId()]->map(fn(User $student) => [
                                            'learningAssessmentTimeline.getFullTimeline' => $this->getRouteIfAuthorized(
                                                'learningAssessmentTimeline.getFullTimeline',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'userId' => $student->getId(),
                                                ]
                                            ),
                                            'learningDueWorkResponse.getParticularResponse' => $this->getRouteIfAuthorized(
                                                'learningDueWorkResponse.getParticularResponse',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'studentId' => $student->getId(),
                                                    'student' => $student,
                                                ]
                                            ),
                                            'learningAssessment.postExclude' => $this->getRouteIfAuthorized(
                                                'learningAssessment.postExclude',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'studentId' => $student->getId(),
                                                    'student' => $student,
                                                ]
                                            ),
                                            'learningAssessment.postInclude' => $this->getRouteIfAuthorized(
                                                'learningAssessment.postInclude',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'studentId' => $student->getId(),
                                                    'student' => $student,
                                                ]
                                            ),
                                            'learningAssessment.getSummary' => $this->getRouteIfAuthorized(
                                                'learningAssessment.getSummary',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'studentId' => $student->getId(),
                                                    'student' => $student,
                                                ]
                                            ),
                                            'learningAssessment.getInfo' => $this->getRouteIfAuthorized(
                                                'learningAssessment.getInfo',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'studentId' => $student->getId(),
                                                    'student' => $student,
                                                ]
                                            ),
                                            /* this will change depending on if it's a due work, quiz, or reporting activity */
                                            'postFeedback' =>
                                                $aImpl instanceof DueWork\Model ? $this->getRouteIfAuthorized(
                                                    'learningDueWorkFeedback.postParticipantFeedback',
                                                    [
                                                        'assessment' => $aImpl,
                                                        'assessmentId' => $aImpl->getId(),
                                                        'studentId' => $student->getId(),
                                                        'student' => $student,
                                                    ]
                                                ) : ($aImpl instanceof Quiz\Model ? $this->getRouteIfAuthorized(
                                                    'learningQuizAttempt.postModify',
                                                    [
                                                        'assessment' => $aImpl,
                                                        'assessmentId' => $aImpl->getId(),
                                                        'studentId' => $student->getId(),
                                                        'student' => $student,
                                                    ],
                                                ) : ($aImpl instanceof Reporting\Model ? $this->getRouteIfAuthorized(
                                                    'learningReportingFeedback.postParticularFeedback',
                                                    [
                                                        'assessment' => $aImpl,
                                                        'assessmentId' => $aImpl->getId(),
                                                        'learnerId' => $student->getId(),
                                                        'learner' => $student,
                                                    ]
                                                ) : null)
                                            ),
                                            /* this will change depending on if it's due work or reporting activity */
                                            'postPublishFeedback' =>
                                                $aImpl instanceof DueWork\Model ? $this->getRouteIfAuthorized(
                                                    'learningDueWorkFeedback.postPublishFeedback',
                                                    [
                                                        'assessment' => $aImpl,
                                                        'assessmentId' => $aImpl->getId(),
                                                        'studentId' => $student->getId(),
                                                        'student' => $student,
                                                    ]
                                                ) : ($aImpl instanceof Reporting\Model ? $this->getRouteIfAuthorized(
                                                    'learningReportingFeedback.postPublishFeedback',
                                                    [
                                                        'assessment' => $aImpl,
                                                        'assessmentId' => $aImpl->getId(),
                                                        'learnerId' => $student->getId(),
                                                        'learner' => $student,
                                                    ],
                                                ) : null
                                            ),
                                            /* The original assessment customisation route, if it's a customised copy otherwise null
                                             */
                                            'getAssessmentParticipationForm' => $originalUrl,
                                        ]),
                                    ]
                                ];
                            }
                        ),
                        'students' => $courseMarkbook->getCourseImportStudents()[$courseImpl->getId()]->map(
                            function (User $student) use ($courseMarkbook, $courseImpl) {
                                $stats = $courseMarkbook->getStudentAverageMarks()[$courseImpl->getId()][$student->getId()] ?? null;

                                return [
                                    'id'         => $student->getId(),
                                    'externalId' => $student->getExternalId(), // ...for portrait.twig
                                    'givenName'  => $student->getGivenName(),
                                    'lastName'   => $student->getLastname(),
                                    'stats' => [
                                        /* Get the stats in the same format as returned by the class markbook */
                                        'weightedMean'        => $stats['published']['hr'] ?? null,
                                        'meanNormalised'      => $stats['published']['norm'] ?? null,
                                        'draftWeightedMean'   => $stats['any']['hr'] ?? null,
                                        'meanDraftNormalised' => $stats['any']['norm'] ?? null,
                                    ],
                                    'averageMarks' => $courseMarkbook->getStudentAverageMarks()[$courseImpl->getId()][$student->getId()] ?? null,
                                    '_routes'    => [
                                        'grades' => $this->routeGenerator->generate(
                                            'learningGrades.getGrades',
                                            [
                                                'viewee'   => $student,
                                                'userId'   => $student->getId(),
                                                'termCode' => '',
                                            ]
                                        ),
                                    ]
                                ];
                            }
                        ),
                        /* no overall stats for the entire class on the course markbook */
                        'stats' => null,
                    ]
                )->reIndex(),
                '_routes' => [
                    'exportReport' => $this->routeGenerator->generate(
                        'courseMarkbook.getMarkbookExport',
                        [
                            'courseSpecId' => $courseSpec->getId(),
                        ]
                    ),
                    'courseImport.getImport' => $this->routeGenerator->generate(
                        'courseImport.getImport',
                        [
                            'courseSpecId' => $courseSpec->getId(),
                            'folderId'     => 0,
                            'componentId'  => 0,
                        ]
                    ),
                ],
                'availableVisibilities' => Visibility::getAvailableVisibilities(
                    (bool) $this->getConfigLoader()->get(ConfigMapper::NAME_MARK_VISIBILITY_ALLOW_STUDENTS),
                    (bool) $this->getConfigLoader()->get(ConfigMapper::NAME_MARK_VISIBILITY_ALLOW_PARENTS),
                ),
                'submissionErrorStates' => $this->stateMapper->findAll()->map(fn(State $state) => [
                    'id' => $state->getId(),
                    'name' => $state->getDisplayAs(),
                    'normaliseAs' => $state->getNormaliseAs(),
                ])->reindex(),
                'filtersQueryArgs' => $codec->convertSelectionsToQueryString($selectionsPerFilter),
            ];

            $this->response = $this->responseAsJson([
                'status'  => 200,
                'content' => $data,
            ]);
        } catch (Filter\BrokenFilterException $e) {
            // For some reason: this never actually set status 500
            $data = [
                'status'  => 500,
                'message' => "Sorry, the {$e->getFilterName()} filter appears to be broken. Please deselect all options and try again",
            ];
            if ($this->getConfigLoader()->get('debug_mode')) {
                $data = array_merge($data, [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'line'    => $e->getLine(),
                    'file'    => $e->getFile(),
                ]);
            }
            $this->response = $this->responseAsJson($data);
        } catch (Throwable $e) {
            $this->captureException($e);
            $this->returnJsonError($e);
        }
    }


    /**
     * @param CourseSpec\Model $courseSpec
     * @param array            $request  Indexed by filter.id, option.key
     * @param bool             $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function convertRequestToFilters(CourseSpec\Model $courseSpec, array $request, bool $saveToDB): array
    {
        $page            = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $codec           = new Codec($this->filterMapper, $this->filterSelectionMapper);

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Ensure that assessments are:
        // * not Assessment Specs
        // * Are inside unitImpls that belong to courseImpls that were imported from the courseSpec
        $courseImpls = $this->courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId());
        if ($courseImpls->count()) {
            $pb          = $this->unitImplMapper->getPredicateBuilder()->in('course_implementation_id', $courseImpls->getPropertyValues('id'));
            $unitImplIds = $this->unitImplMapper->findIdsByPredicates($pb);
        } else {
            $unitImplIds = [];
        }
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
            Filter\Filters\Assessment\NullableContext\UnitImpl\Definition::class => array_combine($unitImplIds, $unitImplIds),
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }


    /**
     * @param CourseSpec\Model $courseSpec
     * @param array            $assessmentIds
     * @return CourseMarkbook
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function buildCourseMarkbook(CourseSpec\Model $courseSpec, array $assessmentIds): CourseMarkbook
    {
        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        // Build a course markbook
        return new CourseMarkbook(
            $courseSpec,
            $assessments,
            $this->getUser(),
            $this->getConfigLoader()
        );
    }


    /**
     * Helper method to create a Filter Selection model
     *
     * @param Filter\Model $filter
     * @param string       $key
     * @param bool         $selected
     * @return Filter\Selection\Model
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createSelectionModel(Filter\Model $filter, string $key, bool $selected): Filter\Selection\Model
    {
        return $this->filterSelectionMapper
            ->createModel()
            ->setFilter($filter)
            ->setUser($this->getUser())
            ->setPage(Filter\Page\Model::PAGE_COURSE_MARK_BOOK)
            ->setKey($key)
            ->setSelected($selected);
    }

    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param Throwable $e
     */
    private function returnJsonError(Throwable $e): void
    {
        $data = [
            'status'  => 500,
            'message' => 'Sorry, an error occurred during your request and has been logged. Please try deselecting filter options',
        ];
        if ($this->getConfigLoader()->get('debug_mode')) {
            $data = array_merge($data, [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
        }
        $this->response = $this->responseAsJson($data);
    }


    /**
     * Can the specified user mark the specified assessment?
     *
     * Will be false if assessment's folder is archived, or if user lacks WRITE+ perm to folder
     *
     * @param User                           $user
     * @param IAssessable                    $assessment
     * @param GenericCollection|Term\Model[] $terms
     * @return bool
     */
    private function isAssessmentMarkable(User $user, IAssessable $assessment, GenericCollection $terms): bool
    {
        /* Note that this will contain Extraordinary Terms whose dates could
         * be encompassed by another Extraordinary Term (ie. those extraordinary
         * terms have overlapping dates)
         *
         * There used to be an exit-early here for an archived term, under the
         * presumption that Academic Periods could never overlap. That's true,
         * for ordinary terms, but not for Extraordinary Terms.
         *
         * This means we have to inspect the full set of $terms to potentially find
         * one that matches, considering that overlapping dates are possible.
         */
        foreach ($terms as $term) {
            /* Only care about terms that contain the assessment's due date
             * and that the term is not archived
             */
            if ($term->contains($assessment->getDueAt()) && !$term->getArchived()) {
                // Rely on access cache to catch repeated calls to same folder.
                return $this->access->isInstructor($user, $assessment->getContext()->getFolder());
            }
        }
        // Logically should never get to here. It means assessment is due before first term in system starts.
        return false;
    }
}
