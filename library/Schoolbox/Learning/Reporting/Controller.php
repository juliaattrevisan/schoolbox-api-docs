<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting;

use Alaress\Web\Controller\Base;
use Alaress\HTMLPurifier\IPurifier;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\DueWork\State;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\Markbook\FeedbackFolders;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

/**
 * Put activity-specific endpoints here. This \Schoolbox\Learning\*\Controller class is intended for endpoints
 * that are outside the canonical path suggested by \Schoolbox\Assessment\Controller so you can manage the unique
 * and nuanced behaviours of your learning activity without exposing it to every other learning activity and break
 * away from the inheritance tree of \Schoolbox\Assessment\Controller.
 */
class Controller extends Base
{
    /**
     * Constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param StateMapper           $stateMapper
     * @param IPurifier             $purifier
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private StateMapper $stateMapper,
        private IPurifier $purifier,
    ) {
    }


    /**
     * Generate the mega markbook payload for the reporting activity status page.
     *
     * @param Reporting\Model $assessment
     */
    public function getStatus(Reporting\Model $assessment): void
    {
        $folder = $assessment->getBaseContext()->getFolder();

        $markbook = $this->buildMarkbook($folder);

        /* Reporting Markbook specific behaviour:
         * Since the reporting activity won't be within the markbook's assessments,
         * manually add it on the end so that the markbook can render the special
         * feedback cells for it at the far right of the table.
         *
         * NOTE: this does mean you can't trust all the data in $markbook anymore to include this last special assessment...
         * We likely don't want to add it earlier as Markbook would then include it within the overall stats calculations
         */
        $markbookAssessments = $markbook->getAssessments()->shallowClone();
        $markbookAssessments->add($assessment);

        $data = [
            /* Only show class ranking if the config allows it. It seemingly ranks based on Student Weighted Average.
             */
            'rank' => (bool)$this->getConfigLoader()->get('view_rank') ? $markbook->getRanking() : null,
            'assessments' => $markbookAssessments->reindex()->map(function (IAssessable $markbookAssessment, $sequence) use ($assessment) {
                $unitAssessmentsTranslator = new UnitAssessments(
                    $this->routeGenerator,
                    $this->getAuthorizer(),
                    $this->getUser(),
                    $this->getUser(),
                );

                $unitSpec = null;
                $base = $markbookAssessment->getBaseContext();
                if ($base instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
                    // Implementation
                    $unitSpec = $base->getSpecification();
                }

                return [
                    'id'              => $markbookAssessment->getId(),
                    'title'           => $markbookAssessment->getTitle(),
                    'typeName'        => $markbookAssessment->getTypeName(),
                    'assessmentType'  => $markbookAssessment->getFamilyName(),
                    'sequence'        => $sequence,
                    'unitSpec'        => $unitSpec !== null ? [
                        /* for rendering the unit bars at the top of the page */
                        'id'    => $unitSpec->getId(),
                        'title' => $unitSpec->getTitle(),
                    ] : null,
                    /* special reporting markbook specific flag.
                     * true for the reporting activity that should have its marks/comments shown at the end of the table
                     */
                    'splitComments' => $markbookAssessment->getId() === $assessment->getId(),
                    /* the reporting markbook doesn't need any of the below, as this is always an implementation... I think? */
                    'isSpecification' => $markbookAssessment->isSpecification(),
                    'stats'           => null,
                    '_links'          => $unitAssessmentsTranslator->getLinks($markbookAssessment, true),
                    '_routes'         => [
                        /* this will change depending on if it's due work or reporting activity */
                        'postFeedbacks' => $markbookAssessment instanceof DueWork\Model ? $this->getRouteIfAuthorized(
                            'learningDueWorkFeedback.postParticipantFeedbacks',
                            [
                                'assessment'   => $markbookAssessment,
                                'assessmentId' => $markbookAssessment->getId(),
                            ]
                        ) : ($markbookAssessment instanceof Reporting\Model ? $this->getRouteIfAuthorized(
                            'learningReportingFeedback.postParticipantFeedbacks',
                            [
                                'assessment'   => $markbookAssessment,
                                'assessmentId' => $markbookAssessment->getId(),
                            ]
                        ) : null),
                    ],
                ];
            })->indexByCallback(fn(array $markbookAssessment) => $markbookAssessment['id']),
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
                'assessments' => $markbookAssessments->indexBy('id')->map(function (IAssessable $aImpl) use ($markbook, $assessment) {
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
                        // 'weight'         => $aImpl->getWeight(), /* not relevant on the reporting markbook */
                        'projectId'      => $parentProject?->getId(),
                        // nothing on the reporting markbook is markable *except* for the reporting activity itself
                        'writable'       => $aImpl->getId() === $assessment->getId(),
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
                        'stats'          => new \stdClass(), /* not relevant on the reporting markbook */
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
                                /* The original assessment customisation route, if it's a customised copy otherwise null */
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
                'stats' => null, /* not relevant on the reporting markbook */
            ]],
            '_routes' => [
                'exportReport' => null, /* not relevant on the reporting markbook */
                'courseImport.getImport' => null, /* not relevant on the reporting markbook */
            ],
            'availableVisibilities' => [], /* not relevant on the reporting markbook */
            'submissionErrorStates' => $this->stateMapper->findAll()->map(fn(State $state) => [
                'id' => $state->getId(),
                'name' => $state->getDisplayAs(),
                'normaliseAs' => $state->getNormaliseAs(),
            ])->reindex(),
            // 'filtersQueryArgs' => $codec->convertSelectionsToQueryString($selectionsPerFilter),
            'filtersQueryArgs' => '', /* reporting doesn't have filters like the course/class markbooks do */
        ];

        $this->response = $this->responseAsJson($data);
    }


    /**
     * Builds a markbook for a particular assessment.
     *
     * @param FolderModel $folder
     * @return Markbook
     * @throws Exception
     * @throws NotFound
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function buildMarkbook(FolderModel $folder): Markbook
    {
        $start = microtime(true);

        $feedbackFolders = new FeedbackFolders(
            $this->getDi()->get('databaseInternal'),
            $this->getDi()->get('externalClass'),
            \Schoolbox\UserMapper::getInstance(), // TODO inject this from wherever you implement later
        );

        // toggle the stats
        $overallStatistics = false;

        /* Find data between these date ranges
         */
        $from = new \Alaress_Date_Format('2025-01-01 00:00:00', 'Y-m-d H:i:s');
        $to = new \Alaress_Date_Format('2026-11-07 00:00:00', 'Y-m-d H:i:s');

        /* Note that the first arg to findAllByFolderIdForReview() can actually be a set of folder ids
         */
        $assessments = $this->assessmentMapperProxy->findAllByFolderIdForReview(
            /* For the students in $folder, find all the folders where they had a published feedback
             */
            folderId: $feedbackFolders->findForFolder($folder, $from, $to),
            /* For the $studentIds provided, find all the folders where they had a published feedback
             */
            //                (new FeedbackFolders($this->getDi()->get('databaseInternal'), $this->getDi()->get('externalClass')))
            //                    ->findForStudents($studentIds, $from, $to),
            /* Instead of the FeedbackFolders, you can slam together your own list of folders arbitrarily
             * I found these manually
             */
            // folderId: [
            //                     $folder->getId(),  // typically 80, 9ENGA
            //                     78, // 9SPANISHA
            //                     189, // 9MATA
            //                     85, // 9PDHC
            //                     190, // 9MYPA
            //                     97, // 3TR
            //                     93, // 3AC
            //                     92, // 3IB
            //                     95, // 3IBSPA
            //                     96, // 3IBVA
            //                     250, // ELCB
            //                 ],
            /* This was thought to limit it to one student, but it doesn't help much; it just finds assessments
             * this student did, which still ends up hydrating what every other student in that assessment too,
             * by virtue of the swamp.
             *
             * student: \Schoolbox\UserMapper::getInstance()->findById(13),
             */
            /* Only hydrate these particular participants and their feedbacks, if specified: otherwise swamp hydrates
             * everyone who participated in each $assessment which might be more than you need.
             */
            users: $feedbackFolders->findStudents($folder),
            /* Somewhat experimental:
             * Don't need any response instances. You get some mixed results here, since the markbook infers states:
             * a response existing, but no feedback given yet, is shown as a hyphen - ...but if a response doesn't
             * exist, the markbook shows "Pending" under the assumption you never submitted anything.
             */
            includeResponses: true,
            /* There's this somewhat obscure "options to pass to findByColumList" but there's actually a few
             * rarely-used, rarely-encountered ideas here: ref. convertOptionsToPredicateBuilder() in the
             * Assessment MapperProxy:1790. You get there via findAllByFolderIdForReview() and it has a plethora of
             * date range filters, term-based filters, etc. deep in the swamp where you'd never look.
             */
            options: [
                'due_after' => $from,
                'due_before' => $to,
                'type' => [
                    AssessmentType::TYPE_DUE_WORK,
                    AssessmentType::TYPE_QUIZ,
                    AssessmentType::TYPE_PROJECT,
                    AssessmentType::TYPE_LTI_ASSESSMENT
                ],
                'work_type' => [
                    1, // Formative Assessment Task
                    2, // Summative Assessment Task
                    3, // Learning Activity
                    4, // Homework Task
                    5, // Observation
                    6, // Lesson Planning
                    7, // Reporting
                    8, // Unknown (maybe deleted)
                    9, // Approach To Learning
                ],
                /* If both are provided, assessment ids and curriculum nodes execute an intersect: this results in
                 * all the assessment ids provided that -also- are associated with any of the curric codes specified
                 */
            //                    'ids' => [391],
            //                    'curriculum_codes' => [
            //                        'AC9SAS09',
            //                        'GC-FA-a'
            //                    ]
            ],
        );

        // Build a markbook
        $markSchemeFactory = new MarkScheme\Factory($this->getConfigLoader());
        $overallMarkScheme = $markSchemeFactory->spawnForFolder($folder, $this->getUser()->getRole()->isStaff());

        $markbook = new Markbook(
            assessments: $assessments,
            defaultMarkScheme: $overallMarkScheme,
            viewingUser: $this->getUser(),
            /* Ignore processing weight, mean, stdev, min, max, etc. statistics. This appears in an accordion thing on
             * the markbook page. Setting this to false zeroes out the data, ignoring its normal calculation algorithms
             */
            overallStatistics: $overallStatistics,
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
}
