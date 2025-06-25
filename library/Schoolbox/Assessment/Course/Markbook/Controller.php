<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Mapper as RubricIndicatorMarkMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Permission\Access\Access;
use Alaress\Web\Controller as WebController;
use Schoolbox\Assessment\Course\Markbook\Export\CSV;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment\AssessmentType\Definition as AssessmentTypeDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\ListController;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\Assessment\Feedback\Visibility;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Throwable;

use function array_combine;
use function func_get_args;

class Controller extends WebController\Base
{
    /**
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Mapper                      $filterMapper
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param AssessmentMapperProxy             $assessmentMapperProxy
     * @param CourseImpl\Mapper                  $courseImplMapper
     * @param UnitImpl\Mapper                    $unitImplMapper
     * @param Access                             $access
     * @param Breadcrumb\Factory                 $bakery
     */
    public function __construct(
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private CourseImpl\Mapper $courseImplMapper,
        private UnitImpl\Mapper $unitImplMapper,
        private Access $access,
        private Breadcrumb\Factory $bakery
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
                'canAccessPopOut' => $this->getCanAccessPopOut($courseSpec),
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

            $data = [
                'rank' => (bool)$this->getConfigLoader()->get('view_rank') ? $courseMarkbook->getRanking() : null,
                'assessments' => $courseMarkbook->getCollapsedAssessments()->reindex()->map(fn(IAssessable $assessment, $sequence) => [
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
                    '_links'          => [
                        /* only show the summary link for specs */
                        'summary' => $assessment->isSpecification() ? $this->routeGenerator->generate(
                            'learningAssessment.getSummary',
                            [
                                'assessmentId' => $assessment->getId(),
                                'studentId'    => $this->getUser()->getId(),
                            ]
                        ) : null,
                    ],
                ])->indexByCallback(fn(array $assessment) => $assessment['id']),
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
                            fn(IAssessable $aImpl, int $assessmentId) => [
                                'id' => $aImpl->getId(),
                                /* unused on the course markbook: */
                                // 'markVisibility' => ...,
                                // 'courseManaged'  => ...,
                                // 'weight'         => ...,
                                // 'hasDraft'       => ...,
                                // 'writable'       => ...,
                                'participants' => $courseMarkbook->getCourseImportStudents()[$courseImpl->getId()]->map(
                                    function (User $student) use ($aImpl, $courseImpl, $assessmentId, $courseMarkbook) {
                                        $participant = $aImpl->getParticipantIfExtant($student, false) ?? null;
                                        if ($participant === null) {
                                            return null;
                                        }

                                        /* Find the progress tick status, only relevant for tasks */
                                        $progressTick = null;
                                        if ($aImpl instanceof Task\Model) {
                                            $unitAssessmentsTranslator = new UnitAssessments(
                                                $this->routeGenerator,
                                                $this->getAuthorizer(),
                                                $participant->getUser(),
                                                $this->getUser(),
                                            );
                                            $progressTick = $unitAssessmentsTranslator->getProgressTick($aImpl, $participant);
                                        }

                                        $marks = $courseMarkbook->getStudentMarks()[$courseImpl->getId()][$assessmentId][$student->getId()] ?? null;
                                        $comments = $courseMarkbook->getStudentComments()[$courseImpl->getId()];

                                        return [
                                            'isExcluded' => $participant->isExcluded(),
                                            'hasSubmittedResponse' => $participant->hasSubmittedResponse(),
                                            'hasPublishedFeedback' => $participant->hasPublishedFeedback(),
                                            'resubmissionRequired' => $participant->isResubmissionRequired(),
                                            'progressTick' => $progressTick,
                                            /* when all the values are null, the published/draft value itself will be null
                                             * to allow checking for a draft mark / published mark */
                                            'marks' => array_map(
                                                fn($markSet) => array_filter($markSet, fn($v) => $v !== null) === [] ? null : $markSet,
                                                [
                                                    'published' => [
                                                        'norm'         => $marks['published']['norm'],
                                                        'hr'           => $marks['published']['hr'],
                                                        'hrInstructor' => $marks['published']['hrInstructor'],
                                                        'comment'      => \Scrub::html2text($comments[$student->getId()][$assessmentId] ?? ''),
                                                    ],
                                                    'draft' => [
                                                        'norm'         => $marks['draft']['norm'],
                                                        'hr'           => $marks['draft']['hr'],
                                                        'hrInstructor' => $marks['draft']['hrInstructor'],
                                                        'comment'      => null, /* draft comments are unused on the course markbook */
                                                    ],
                                                ],
                                            ),
                                            /* unused on the course markbook: */
                                            // 'isResponseRequired' => ...,
                                            // 'isFeedbackRequired' => ...,
                                            // 'isReviewComplete'   => ...,
                                            // 'plan' => ...,
                                        ];
                                    }
                                ),
                                'stats' => array_map(
                                    /* Get the stats in the same format as returned by the class markbook */
                                    fn($mark): string|null => $mark === null ? null : sprintf("%.2f %%", $mark),
                                    [
                                        'mean'      => $courseMarkbook->getAssessmentImplAverages()[$aImpl->getId()]['published'] ?? null,
                                        'draftMean' => $courseMarkbook->getAssessmentImplAverages()[$aImpl->getId()]['any'] ?? null,
                                    ]
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
                                        'learningDueWorkFeedback.postPublishFeedback' => $this->getRouteIfAuthorized(
                                            'learningDueWorkFeedback.postPublishFeedback',
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
                                    ]),
                                ]
                            ]
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
                    'exportReport' => sprintf(
                        "%s?%s",
                        $this->routeGenerator->generate(
                            'courseMarkbook.getMarkbookExport',
                            [
                                'courseSpecId' => $courseSpec->getId(),
                            ]
                        ),
                        $codec->convertSelectionsToQueryString($selectionsPerFilter),
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
                'canAccessPopOut' => $this->getCanAccessPopOut($courseSpec),
                'availableVisibilities' => Visibility::getAvailableVisibilities((int)$this->getConfigLoader()->get(ConfigMapper::NAME_SUBMISSION_VISIBILITY_MAX)),
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
     * View status page for a course level assessment filtered by markbook filters
     * this will take a courseSpec and an assessmentId, then filter from url query args mostly the following filters:
     * * folders
     * * due in academic period
     * * activity status
     * (the rest of the filters are not relevant for this page)
     *
     * @param Model $courseSpec
     * @param IAssessable $assessmentSpec
     * @return void
     * @throws Exception
     * @api GET /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}
     */
    public function getAssessment(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $request = $this->requestPost['filter_selection'] ?: [];

        /**
         * @var null[]|Collection[]        $filters
         * @var Filter\Codec               $codec
         * @var Filter\Selection\Model[][] $selectionsPerFilter
         */
        // TODO add some intelligence around which filter settings to save (eg AssessmentType but not units)
        [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($courseSpec, $request, true);

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        // filter to only the assessment we are looking for
        $assessments = $assessments->filter(static function (IAssessable $assessment) use ($assessmentSpec) {
            return $assessment->getSpecificationAssessment()?->getId() === $assessmentSpec->getId();
        });
        // hydrate participants
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        $unitAssessmentsMap = new UnitAssessments(null, $this->authorizer, null, $this->getUser());

        // Injectables...
        $rubricIndicatorMarkMapper = RubricIndicatorMarkMapper::getInstance();
        $termMapper = Term\Mapper::getInstance();

        $classes = [];
        // init the twig data
        $twigData = [
            'type' => AssessmentType::objectToString($assessmentSpec),
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessmentSpec, $this->getUser())->add([ $assessmentSpec->getTitle(), '' ]),
            'assessments' => $assessments,
            'assessmentSpec'  => $assessmentSpec,
            'classes' => $classes,
        ];
        // switch based on which assessment type
        if ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\DueWork\Model) {
            // duework specific data
            $twigData['errors'] = StateMapper::getInstance()->listDisplayValues();

            /* Draft Responses made by each guy
             */
            $twigData['draftResponses'] = [];

            // class assessment specific data
            foreach ($assessments as $assessment) {
                // Only display download link if there are actually files to download in any of the classes
                $twigData['download'] = $twigData['download'] ?: $this->hasDownloadableSubmissionFiles($assessment);

                $twigData['rubricMarks'][$assessment->getId()] = $rubricIndicatorMarkMapper->findMarkedByAssessment($assessment->getId());
                $twigData['interactionLocked'][$assessment->getId()] = (new Assessment\LockedChecker($termMapper))->isLockedForInteractions($assessment);
                /* Find the first valid Blind Review url
                */
                $twigData['blindReviewUrl'][$assessment->getId()] = (new StudentCarousel\Blind(
                    $this->routeGenerator,
                    null,
                    $assessment
                ))->getFirstUrl();

                /* Find out which users have a draft submission
                * MAYBE you can do this purely in twig...but I'm not enamourd by frontend making
                * those sort of data collecting maneuvers mainly because they don't know when their
                * calls access a potentially expensive method.
                */
                foreach ($assessment->getAllParticipants() as $participant) {
                    $draftResponse = $participant->findMostRecentDraftResponse();

                    if ($draftResponse) {
                        $twigData['draftResponses'][$assessment->getId()][$participant->getUser()->getId()] = $draftResponse->getId();
                    }
                }
            }

            $twigData['selfReflect'] = false;
            $twigData['selfAssess'] = false;

            $template = '@courseMarkbook/status/duework.twig';
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Lti\Model) {
            $template = '@learningAssessment/lti/status.twig';
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Task\Model) {
            $template = '@courseMarkbook/status/task.twig';
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
            $template = '@learningAssessment/quiz/status.twig';
//            project doesn't have a status page yet
//        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Project) {
//            $template = '@learningAssessment/quiz/status.twig';
        } else {
            throw new NotFound('Unsupported assessment type: ' . AssessmentType::objectToString($assessmentSpec));
        }

        $this->response = $this->responseFromTemplate(
            $template,
            $twigData,
            "Status of {$assessmentSpec->getTitle()}",
            'submission'
        );
    }

    /**
     * @param Model $courseSpec
     * @param $assessmentId
     * @return void
     * @api POST /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/publish
     */
    public function postPublishFeedback(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        new \Alaress_Probe($courseSpec, $assessmentSpec);
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
     * Helper method to check if current user can access the pop out
     *
     * @param CourseSpec\Model $courseSpec
     * @return boolean
     * @throws \Schoolbox\Mapper\Exception
     */
    private function getCanAccessPopOut(CourseSpec\Model $courseSpec): bool
    {
        // first check if the user has write access to the course
        $hasCourseWriteAccess = $this->access->getFolderAccess(
            $this->getUser(),
            $courseSpec->getFolder()->getId(),
        ) >= UserAccess::ACCESS_WRITE;
        if (!$hasCourseWriteAccess) {
            return false;
        }
        // then check if the user has write access to all the course implementations
        foreach ($courseSpec->getCourseImplementations() as $classImplementation) {
            $hasAccess = $this->access->getFolderAccess(
                $this->getUser(),
                $classImplementation->getFolder()->getId()
            ) >= UserAccess::ACCESS_WRITE;
            if (!$hasAccess) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if there are any submission files or feedback available for download on the assessment
     *
     * Direct clone from \Schoolbox\Learning\Assessment\DueWork\Controller::hasDownloadableSubmissionFiles
     * @see \Schoolbox\Learning\Assessment\DueWork\Controller::hasDownloadableSubmissionFiles
     * @param IAssessable $assessment
     * @return bool
     */
    private function hasDownloadableSubmissionFiles(IAssessable $assessment): bool
    {
        // Grab all participants in the assessment

        /** @var Participant $participant */
        foreach ($assessment->getAllParticipants() as $participant) {
            // Iterate all feedbacks
            foreach ($participant->getAllFeedbacks() as $feedback) {
                if ($feedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model && $feedback->isPublished()) {
                    return true;
                }
            }

            // Iterate all their responses
            /** @var Submission $response */
            foreach ($participant->getAllResponses() as $response) {
                // If the response is a submission...

                if ($response instanceof Submission && !$response->isDraft()) {
                    if ($response->getAllFiles()->count() > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
