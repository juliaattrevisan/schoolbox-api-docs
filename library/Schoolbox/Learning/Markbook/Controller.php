<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Alaress\HTMLPurifier\IPurifier;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment\AssessmentType\Definition as AssessmentTypeDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Feedback\Visibility;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Learning\DueWork\State;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

use function func_get_args;

class Controller extends Base
{
    /**
     * @param AssessmentMapperProxy              $assessmentMapperProxy
     * @param Term\Mapper                        $termMapper
     * @param Access                             $access
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param StateMapper                        $stateMapper
     * @param IPurifier                          $purifier
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private Term\Mapper $termMapper,
        private Access $access,
        private Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private StateMapper $stateMapper,
        private IPurifier $purifier,
        private EditorTemplateHelper $editorTemplateHelper
    ) {
    }


    /**
     * Render the shell of the markbook for the specified class.
     *
     * This renders the filter panel, but not its contents: those are rendered
     * by `postMarkbookForClass`.
     *
     * @api   GET /learning/markbook/class/:folderId?{filterQueryArgs}
     * @since 20.1
     *
     * @param FolderModel $folder
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClass(FolderModel $folder): void
    {
        // This is now the final location, not the redirect
        // Ignore viewee: always use current user
        $page      = Filter\Page\Model::PAGE_CLASS_MARK_BOOK;
        $route     = $this->routeGenerator->generate(
            'learningMarkbook.postMarkbookForClass',
            [
                'folder'   => $folder,
                'folderId' => $folder->getId(),
            ]
        );
        $queryArgs = $this->requestQuery->getArrayCopy();

        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), func_get_args(), $queryArgs);

        $pageTitle = 'Class Markbook';

        $this->response = $this->responseFromTemplate('@learningMarkbook/markbookOuter.twig', [
                'folder'      => $folder,
                'filterPanel' => $filterPanel,
                'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
            ], $pageTitle, 'submission');
    }


    /**
     * Receives a set of filter panel selections and render a filtered markbook
     * for the given class.
     *
     * @api   POST /learning/markbook/class/:folderId
     * @since 20.1
     *
     * @param FolderModel $folder
     */
    public function postMarkbookForClass(FolderModel $folder): void
    {
        try {
            $request = $this->requestPost['filter_selection'] ?? [];

            /**
             * @var null[]|Collection[]        $filters
             * @var Filter\Codec               $codec
             * @var Filter\Selection\Model[][] $selectionsPerFilter
             */
            [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($request, true);

            $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
            $markbook      = $this->buildMarkbook($assessmentIds, $folder);

            /**
             * need to get an list of the students by id, with their ranking
             */
            $rank = $markbook->getRanking();

            $terms = $this->termMapper->findAll(['order' => ['start' => 'ASC']]);

            $data = [
                'rank' => (bool)$this->getConfigLoader()->get('view_rank') ? $rank : null,
                'assessments' => $markbook->getAssessments()->reindex()->map(function (IAssessable $assessment, $sequence) {
                    $unitAssessmentsTranslator = new UnitAssessments(
                        $this->routeGenerator,
                        $this->getAuthorizer(),
                        $this->getUser(),
                        $this->getUser(),
                    );

                    $unitSpec = null;
                    $base = $assessment->getBaseContext();
                    if ($base instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
                        // Implementation
                        $unitSpec = $base->getSpecification();
                    }

                    return [
                        'id'              => $assessment->getId(),
                        'title'           => $assessment->getTitle(),
                        'typeName'        => $assessment->getTypeName(),
                        'assessmentType'  => $assessment->getFamilyName(),
                        'sequence'        => $sequence,
                        'unitSpec'        => $unitSpec !== null ? [
                            /* for rendering the unit bars at the top of the page */
                            'id'    => $unitSpec->getId(),
                            'title' => $unitSpec->getTitle(),
                        ] : null,
                        /* the class markbook doesn't need any of the below, as this is always an implementation */
                        'isSpecification' => $assessment->isSpecification(),
                        'stats'           => null,
                        '_links'          => $unitAssessmentsTranslator->getLinks($assessment, true),
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
                 * For the class markbook, there is only ever one of these - the class folder itself
                 */
                'classImpls' => [[
                    'folder' => [
                        'id'    => $folder->getFolder()->getId(),
                        'title' => $folder->getFolder()->getTitle(),
                    ],
                    'assessments' => $markbook->getAssessments()->indexBy('id')->map(function (IAssessable $aImpl) use ($markbook, $terms) {
                        /** Only due work & reporting have the concept of "instructorMarkScheme", others just use markScheme for now */
                        $instructorMarkScheme = ($aImpl instanceof DueWork\Model || $aImpl instanceof Reporting\Model)
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
                            'id'             => $aImpl->getId(),
                            'markVisibility' => $aImpl->getMarkVisibility(),
                            'courseManaged'  => $aImpl->getCourseManaged(),
                            'weight'         => $aImpl->getWeight(),
                            'projectId'      => $parentProject?->getId(),
                            'writable'       => $this->isAssessmentMarkable($this->getUser(), $aImpl, $terms),
                            /* feedback comments are enabled by default, unless a reporting activity opted out of them */
                            'feedbackCommentsEnable' => $aImpl instanceof Reporting\Model
                                ? $aImpl->getFeedbackCommentsEnable()
                                : true,
                            'instructorMarkScheme' => $instructorMarkScheme?->generateContext(),
                            'participants'   => $markbook->getStudents()->map(
                                function (User $student) use ($aImpl, $markbook) {
                                    $participant = $aImpl->getParticipantIfExtant($student, false);
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

                                    $draftMark = isset($markbook->getHasDraft()[$aImpl->getId()][$student->getId()]);

                                    /** This basically matches the logic inside library/Schoolbox/Learning/Assessment/ListController.php:getInfo */
                                    $authoritativeFeedback = $participant->getAuthoritativeFeedback(true);
                                    $authoritativeFeedbackDraft = $draftMark ? $participant->getAuthoritativeFeedback(false) : null;

                                    if ($authoritativeFeedbackDraft && !$authoritativeFeedbackDraft->isDraft()) {
                                        // this isn't actually a draft (it'll be the same as $authoritativeFeedback)
                                        $authoritativeFeedbackDraft = null;
                                    }

                                    $resubmissionRequiredPublished = $authoritativeFeedback instanceof DueWork\Feedback\Model
                                        ? $authoritativeFeedback->getResubmissionRequired()
                                        : false;
                                    $resubmissionRequiredDraft = $authoritativeFeedbackDraft instanceof DueWork\Feedback\Model
                                        ? $authoritativeFeedbackDraft->getResubmissionRequired()
                                        : false;

                                    $responseIdPublished = $authoritativeFeedback?->getId() ?? null;
                                    $responseIdDraft = $authoritativeFeedbackDraft?->getId() ?? null;

                                    $authNormMarkPublished = $authoritativeFeedback || $aImpl instanceof Project ? $participant->getNormalisedMark(true) : null;
                                    $authNormMarkDraft = $authoritativeFeedbackDraft || $aImpl instanceof Project ? $participant->getNormalisedMark(false) : null;

                                    /** the markbook uses responseId !== null to determine if this mark actually exists, so jank a -1 here for projects */
                                    if ($aImpl instanceof Project) {
                                        if ($authNormMarkPublished !== null) {
                                            $responseIdPublished = -1;
                                        }

                                        if ($authNormMarkDraft !== null) {
                                            $responseIdDraft = -1;
                                        }
                                    }

                                    return [
                                        'isExcluded'           => $participant->isExcluded(),
                                        'hasSubmittedResponse' => $participant->hasSubmittedResponse(),
                                        'hasPublishedFeedback' => $participant->hasPublishedFeedback(),
                                        'progressTick'       => $progressTick,
                                        'marks' => [
                                            'published' => [
                                                'norm'         => $authNormMarkPublished,
                                                'hr'           => $authoritativeFeedback || $aImpl instanceof Project ? $participant->getHumanReadableMark(true) : null,
                                                'hrInstructor' => $authoritativeFeedback || $aImpl instanceof Project ? $participant->getHumanReadableInstructorMark(true) : null,
                                                'comment'      => $this->purifier->purify($authoritativeFeedback?->getComment()),
                                                'resubmissionRequired' => $resubmissionRequiredPublished,
                                                'responseId'   => $responseIdPublished,
                                            ],
                                            'draft' => [
                                                'norm'         => $authNormMarkDraft,
                                                'hr'           => $authoritativeFeedbackDraft || $aImpl instanceof Project ? $participant->getHumanReadableMark(false) : null,
                                                'hrInstructor' => $authoritativeFeedbackDraft || $aImpl instanceof Project ? $participant->getHumanReadableInstructorMark(false) : null,
                                                'comment'      => $this->purifier->purify($authoritativeFeedbackDraft?->getComment()),
                                                'resubmissionRequired' => $resubmissionRequiredDraft,
                                                'responseId'   => $responseIdDraft
                                            ],
                                        ],
                                        'plan' => [
                                            'isStatic' => $participant->getPlan()->isStatic(),
                                            'name' => $participant->getPlan()->getName(),
                                            'dueDate' => $participant->getPlan()->getDueDate()?->getShort() ?? null,
                                        ],
                                    ];
                                }
                            ),
                            'stats'          => array_merge(
                                $markbook->getAssessmentStatistics()[$aImpl->getId()] ?? [],
                                [ 'markVisibility' => $aImpl->getMarkVisibility() ]
                            ),
                            '_routes'        => [
                                'learningAssessment.getStatus' => $this->routeGenerator->generate(
                                    'learningAssessment.getStatus',
                                    [ 'assessmentId' => $aImpl->getId() ]
                                ),
                                'student' => $markbook->getStudents()->map(fn(User $student) => [
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
                                    'learningAssessment.getInfo' => $this->getRouteIfAuthorized(
                                        'learningAssessment.getInfo',
                                        [
                                            'assessment' => $aImpl,
                                            'assessmentId' => $aImpl->getId(),
                                            'studentId' => $student->getId(),
                                            'student' => $student,
                                        ]
                                    ),
                                    /* reporting has its own one of these as you can't submit stuff for it */
                                    'historyRoute' =>
                                        $aImpl instanceof Reporting\Model ? $this->getRouteIfAuthorized(
                                            'learningReportingFeedback.getParticularFeedback',
                                            [
                                                'assessment' => $aImpl,
                                                'assessmentId' => $aImpl->getId(),
                                                'learnerId' => $student->getId(),
                                                'learner' => $student,
                                            ]
                                        ) : $this->getRouteIfAuthorized(
                                            'learningAssessment.getSummary',
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
                                    'postPublishFeedback' => (
                                        $aImpl instanceof DueWork\Model ? $this->getRouteIfAuthorized(
                                            'learningDueWorkFeedback.postPublishFeedback',
                                            [
                                                'assessment' => $aImpl,
                                                'assessmentId' => $aImpl->getId(),
                                                'studentId' => $student->getId(),
                                                'student' => $student,
                                            ]
                                        ) : (
                                            $aImpl instanceof Reporting\Model ? $this->getRouteIfAuthorized(
                                                'learningReportingFeedback.postPublishFeedback',
                                                [
                                                    'assessment' => $aImpl,
                                                    'assessmentId' => $aImpl->getId(),
                                                    'learnerId' => $student->getId(),
                                                    'learner' => $student,
                                                ]
                                            ) : null
                                        )
                                    ),
                                    /* The original assessment customisation route, if it's a customised copy otherwise null
                                     */
                                    'getAssessmentParticipationForm' => $originalUrl,
                                ]),
                            ],
                        ];
                    }),
                    'students'    => $markbook->getStudents()->map(fn (User $student) => [
                        'id'         => $student->getId(),
                        'externalId' => $student->getExternalId(), // ...for portrait.twig
                        'givenName'  => $student->getGivenName(),
                        'lastName'   => $student->getLastname(),
                        'stats'      => $markbook->getStudentStatistics()[$student->getId()],
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
                    ]),
                    /* overall stats for the entire class */
                    'stats' => [
                        'normalisedMean' => $markbook->getNormalisedMean(),
                        'draftNormalisedMean' => $markbook->getDraftNormalisedMean(),
                        'weightedMean' => $markbook->getWeightedMean(),
                        'draftWeightedMean' => $markbook->getDraftWeightedMean(),
                    ],
                ]],
                '_routes' => [
                    'exportReport' => $this->routeGenerator->generate(
                        'learningMarkbook.getMarkbookExportForClass',
                        [
                            'folder'   => $folder,
                            'folderId' => $folder->getId(),
                        ]
                    ),
                    'courseImport.getImport' => null, /* not relevant on the class markbook */
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
            $this->captureException($e);
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
        } catch (\Throwable $e) {
            $this->captureException($e);
            $this->returnJsonError($e);
        }
    }


    /**
     * Redirect to the markbook for the specified class and filter to a term
     *
     * @api   GET /learning/markbook/class/:folderId/term/:termCode
     * @since 20.1
     *
     * @param FolderModel $folder
     * @param Term\Model  $term
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClassAndTerm(FolderModel $folder, Term\Model $term): void
    {
        // TODO override the filter instead of redirecting
        $this->buildMarkbookRedirect($folder, $term);
    }


    /**
     * @param FolderModel     $folder
     * @param null|Term\Model $term
     * @throws RouteNotFound If unable to generate the redirect route
     */
    private function buildMarkbookRedirect(FolderModel $folder, ?Term\Model $term): void
    {
        $route = $this->routeGenerator->generate(
            'learningMarkbook.getMarkbookForClass',
            [
                'folder'   => $folder,
                'folderId' => $folder->getId(),
            ]
        );

        $filterMapper    = Filter\Mapper::getInstance();
        $selectionMapper = Filter\Selection\Mapper::getInstance();

        $selections = new GenericCollection(GenericCollection::class);
        $filters    = $filterMapper->findForEntity(\Schoolbox\Learning\Assessment\DataModel\Mapper::class);
        foreach ($filters as $filter) {
            if ($term && Filter\Filters\Assessment\Term\Due\Definition::class === $filter->getClass()) {
                $selections[$filter->getId()]                 = $selectionMapper->createCollection();
                $selections[$filter->getId()][$term->getId()] = $this->createSelectionModel(
                    $selectionMapper,
                    $filter,
                    (string)$term->getId()
                );
            }
        }

        $codec        = new Codec(Filter\Mapper::getInstance(), Filter\Selection\Mapper::getInstance());
        $getArgString = $codec->convertSelectionsToQueryString($selections);

        $this->response = $this->redirectFound("{$route}?{$getArgString}");
    }


    /**
     * Helper method to create a Filter Selection model
     *
     * @param Filter\Selection\Mapper $selectionMapper
     * @param Filter\Model            $filter
     * @param string                  $key
     * @return Filter\Selection\Model
     */
    private function createSelectionModel(Filter\Selection\Mapper $selectionMapper, Filter\Model $filter, $key): Filter\Selection\Model
    {
        $selection = $selectionMapper->createModel();
        $selection
            ->setFilter($filter)
            ->setUser($this->getUser())
            ->setPage(Filter\Page\Model::PAGE_CLASS_MARK_BOOK)
            ->setKey($key)
            ->setSelected(true);
        return $selection;
    }


    /**
     * @param array $request  Indexed by filter.id, option.key
     * @param bool  $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    private function convertRequestToFilters(array $request, bool $saveToDB): array
    {
        $selectionMapper = Filter\Selection\Mapper::getInstance(); // TODO pull from DI
        $page            = Filter\Page\Model::PAGE_CLASS_MARK_BOOK;
        $codec           = new Codec(Filter\Mapper::getInstance(), Filter\Selection\Mapper::getInstance());

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $selectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Ensure that assessments are not Assessment Specs
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }


    /**
     * Stream a filtered markbook for a folder in CSV format
     *
     * @api   GET /learning/markbook/class/:folderId/export?{filterQueryArgs}
     * @since 20.1
     *
     * @param FolderModel $folder
     *
     * @throws \DomainException                      If attempting to get the slug from a filter where it has not been set
     * @throws Filter\BrokenFilterException          If a injected filter is unable to be processed
     * @throws \InvalidArgumentException             If any filter keys not recognised
     * @throws \ReflectionException                  If a specified filter class does not exist
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     */
    public function getMarkbookExportForClass(FolderModel $folder): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $filtersArray = $this->requestQuery->getArrayCopy();
        unset($filtersArray['page']);

        [$filters] = $this->convertRequestToFilters($filtersArray, false);

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
        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
        $markbook      = $this->buildMarkbook($assessmentIds, $folder);
        $showDrafts    = (bool)($filtersArray['showDrafts'] ?? false);
        $showDisplayMarks = (bool)($filtersArray['showDisplayMarks'] ?? false);
        $csvExporter   = new Export\CSV((bool)$this->getConfigLoader()->get('view_rank'));
        $csvExporter->export(
            $markbook,
            (($showDrafts ? 'Draft - ' : '') . sprintf('%s - Class Markbook.csv', $folder->getName())),
            $showDrafts,
            $showDisplayMarks,
        );
        exit;
    }


    /**
     * Builds a markbook for this folder.
     *
     * @param array       $assessmentIds
     * @param FolderModel $folder
     * @return Markbook
     */
    private function buildMarkbook(array $assessmentIds, FolderModel $folder): Markbook
    {
        $start = microtime(true);
        // Fetch assessments & participants
        if (empty($assessmentIds)) {
            $assessments = $this->assessmentMapperProxy->createCollection();
        } else {
            /* Note that the first arg to findAllByFolderIdForReview() can actually be a set of folder ids
             */
            $assessments = $this->assessmentMapperProxy->findAllByFolderIdForReview(
                [
                    $folder->getId(),  // typically 80, 9ENGA
                    78, // 9SPANISHA
                    189, // 9MATA
                    85, // 9PDHC
                    190, // 9MYPA
                    97, // 3TR
                    93, // 3AC
                    92, // 3IB
                    95, // 3IBSPA
                    96, // 3IBVA
                    250, // ELCB
                ],
                \Schoolbox\UserMapper::getInstance()->findById(13),
                //['ids' => $assessmentIds]

                /* There's this somewhat obscure "options to pass to findByColumList" but there's actually a few
                 * rarely-used, rarely-encountered ideas here: ref. convertOptionsToPredicateBuilder() in the
                 * Assessment MapperProxy:1790. You get there via findAllByFolderIdForReview() and it has a plethora of
                 * date range filters, term-based filters, etc. deep in the swamp where you'd never look.
                 */
//                [
//                    'due_after' => new \Alaress_Date_Format('2026-01-01 00:00:00', 'Y-m-d H:i:s'),
//                    'due_before' => new \Alaress_Date_Format('2026-02-06 00:00:00', 'Y-m-d H:i:s'),
//                    'type' => [
//                        AssessmentType::TYPE_DUE_WORK,
//                        AssessmentType::TYPE_QUIZ,
//                        AssessmentType::TYPE_PROJECT,
//                        AssessmentType::TYPE_LTI_ASSESSMENT
//                    ],
//                    'work_type' => [
//                        7, // Reporting
//                        3, // Learning Activity
//                    ],
//                    /* Assessment ids and curriculum nodes execute an intersect, so they work together (if provided)
//                     */
//                    'ids' => [391],
//                    'curriculum_codes' => [
//                        'AC9SAS09'
//                    ]
//                ]
            );
        }

        // Build a markbook
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $overallMarkScheme = $markSchemeFactory->spawnForFolder($folder, $this->getUser()->getRole()->isStaff());

        $markbook = new Markbook(
            assessments: $assessments,
            defaultMarkScheme: $overallMarkScheme,
            viewingUser: $this->getUser(),
            /* Use this to reduce $assessments to only returning a row for these guy(s)
             * findAllByFolderIdForReview() doesn't really support individual participants across multiple folders.
             * It goes into a different orthogonal method
             * Plus, this isn't the usual use case afaik: you're operating on your entire class of students at a time,
             * mostly: not an individual. Nevertheless, you can operate on a reduced set by using this reductive.
             */
            studentIds: null,
            curriculumCodes: null,
            folderIds: null,
        );

        $markbook->sortAssessables();
        $end = microtime(true);

        /* Temporary timing experiment: unfiltered, for 10 homepages, markbook takes:
         * float(2.780165910720825) to build
         *
         * When filtered to use due dates, types and work types, the markbook takes:
         * float(0.5908069610595703) to build (4 times faster?)
         *
         * When filtered to also use a curric code, the markbook takes:
         * float(0.085470199584961) to build
         */
        $_SESSION['mbdur'] =  $end - $start;

        return $markbook;
    }


    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param \Throwable $e
     */
    protected function returnJsonError($e): void
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
