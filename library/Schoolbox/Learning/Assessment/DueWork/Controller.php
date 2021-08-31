<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Aura\Web\Exception\InvalidStatusCode;
use DomainException;
use Eportfolio\ShowcaseMapper;
use ICollection;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Gotenberg\RequestFactory;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as RubricDescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Mapper as RubricIndicatorMarkMapper;
use Schoolbox\Assessment\Rubric\DataModel\Level\Mapper as RubricLevelMapper;
use Schoolbox\Assessment\Rubric\DataModel\Result\Mapper as RubricResultSetMapper;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Level;
use Schoolbox\Assessment\Rubric\MarkedGrid\IndicatorMark;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfAssessable;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfGradableWithResponse;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfReflectionWithResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantMapper;
use Schoolbox\Learning\Assessment\Timeline\Full as FullTimeline;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Sentry\State\HubInterface;
use TheCodingMachine\Gotenberg;
use UnexpectedValueException;
use ZipStream;

/**
 * Class Controller
 *
 * @package Schoolbox\Learning\Assessment\DueWork
 */
class Controller extends Assessment\Controller
{
    /** @var RubricResultSetMapper */
    private $resultSetMapper;

    /** @var RubricDataSource */
    private $rubricMapper;

    /** @var DueWorkResponseMapper */
    private $dueWorkResponseMapper;

    /** @var ParticipantMapper */
    private $participantMapper;

    /** @var SingleFile\Mapper */
    private $submissionFileMapper;

    /** @var Kami\Connector */
    private $kamiConnector;

    /** @var Kami\Mapper */
    private $kamiMapper;

    /**
     * PDF generator, as used by the `downloadSubmissions` method.
     */
    private Gotenberg\Client $pdfGenerator;

    /**
     * @param UserMapper                    $userMapper
     * @param Error\Mapper                  $errorMapper
     * @param AssessmentDataSource          $assessmentMapper
     * @param WorkTypeMapper                $workTypeMapper
     * @param Access                        $access
     * @param Transactor                    $transactor
     * @param Assessment\Breadcrumb\Factory $bakery
     * @param RubricDataSource              $rubricMapper
     * @param RubricResultSetMapper         $resultSetMapper
     * @param DueWorkResponseMapper         $dueWorkResponseMapper
     * @param ParticipantMapper             $participantMapper
     * @param Term\Mapper                   $termMapper
     * @param SingleFile\Mapper             $submissionFileMapper
     * @param Kami\Connector                $kamiConnector
     * @param Kami\Mapper                   $kamiMapper
     * @param Participant\DataSource        $participantDataSource
     * @param CourseSpecificationMapper     $courseSpecificationMapper
     * @param UnitSpecificationMapper       $unitSpecificationMapper
     * @param Replicator                    $replicator
     * @param Gotenberg\Client              $pdfGenerator
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        AssessmentDataSource $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        RubricDataSource $rubricMapper,
        RubricResultSetMapper $resultSetMapper,
        DueWorkResponseMapper $dueWorkResponseMapper,
        ParticipantMapper $participantMapper,
        Term\Mapper $termMapper,
        SingleFile\Mapper $submissionFileMapper,
        Kami\Connector $kamiConnector,
        Kami\Mapper $kamiMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator,
        Gotenberg\Client $pdfGenerator
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
            $replicator
        );
        $this->resultSetMapper       = $resultSetMapper;
        $this->rubricMapper          = $rubricMapper;
        $this->dueWorkResponseMapper = $dueWorkResponseMapper;
        $this->participantMapper     = $participantMapper;
        $this->submissionFileMapper  = $submissionFileMapper;
        $this->kamiConnector         = $kamiConnector;
        $this->kamiMapper            = $kamiMapper;
        $this->pdfGenerator          = $pdfGenerator;
    }


    /**
     * Determine if there are any submission files or feedback available for download on the assessment
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
                if ($feedback instanceof Feedback\Model && $feedback->isPublished()) {
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


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @param IAssessable $assessment
     * @throws InvalidArgumentException If assessment due date is null.
     * @throws PDOException
     * @throws UnexpectedValueException
     * @api GET /learning/assessment/:assessmentId/status
     */
    public function getStatus(IAssessable $assessment): void
    {
        // Only display download link if there are actually files to download

        $twigData = [
            'errors'            => StateMapper::getInstance()->listDisplayValues(),
            'type'              => Assessment\AssessmentType::objectToString($assessment),
            'markSchemeName'    => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    $assessment->getTitle(),
                    '',
                ]
            ),
            'assessment'        => $assessment,
            'download'          => $this->hasDownloadableSubmissionFiles($assessment),
            'rubricMarks'       => RubricIndicatorMarkMapper::getInstance()->findMarkedByAssessment($assessment->getId()),
            'interactionLocked' => (new Assessment\LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment),

            /* Find the first valid Blind Review url
             */
            'blindReviewUrl'    => (new StudentCarousel\Blind(
                $this->getRouter(),
                null,
                $assessment
            ))->getFirstUrl(),
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/dueWork/status.twig',
                $twigData,
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }


    /**
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws MapperException
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 165
     *
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {

        /* Check if this assessment has a SEB requirement
         * If it does, send you to the download screen
         */
        $sebDownload = $this->getRouteIfAuthorized(
            'learningAssessment.getSebDownload',
            ['assessment' => $assessment,
            'assessmentId' => $assessment->getId()]
        );

        if ($sebDownload && !$this->isSebCheckPasses($assessment)) {
            $this->getResponse()->redirect->to($sebDownload);
            return;
        }

        if ($this->getRequest()->query->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->getRequest()->query->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        $participant = $assessment->getParticipantIfExtant($student);

        // Generate rubric marked grids for every feedback being displayed
        if ($assessment->getUseRubric()) {
            $assessment->setRubricGrid($this->rubricMapper->retrieveGrid($assessment, false));
            if ($participant) {
                foreach ($participant->getAllFeedbacks() as $feedback) {
                    // All feedbacks ought to be due work feedbacks,
                    // and thus have a rubric grid
                    if ($feedback instanceof Feedback\Model) {
                        $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
                    }
                }
            }
        }

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    $assessment->getTitle(),
                    '',
                ]
            ),
            'assessment'  => $assessment,
            'student'     => $student,
            'participant' => $participant,
            'returnUrl'   => $returnUrl,
            'timeline'               => $participant ? (new FullTimeline($participant))->getForUser($this->getUser()) : null,
            'authoritativeFeedback'  => $participant ? $participant->findAuthoritativeVisibleFeedback($this->getUser()) : null,
            'selfAssessmentFeedback' => $participant ? $participant->findMostRecentSelfAssessedPublishedFeedback() : null,
            'selfReflect'            => $participant ? (new ValidSelfReflectionWithResponse())->isSatisfiedBy($this->getUser(), $participant) : null,
            'selfAssess'             => $participant ? (new ValidSelfGradableWithResponse())->isSatisfiedBy($this->getUser(), $participant) : null,

            /* Plagscan status
             */
            'plagscanEnabled'        => PlagscanMapper::getInstance()->isEnabled($this->getUser()),

            /* Eportfolio Showcase - whether to show the "send to showcase" trophy button
             */
            'canShowcase'            => $assessment->isImplementation() && (new \Eportfolio_Authenticator($this->getUser(), $student))->canModify(),
            /* Whether or not this assessment is showcased on the user's eportfolio
             */
            'isShowcased'            => ShowcaseMapper::getInstance()->isShowcased((int)$assessment->getId(), (int)$student->getId()),
        ];

        $routeArgs = [
            'assessment'   => $assessment,
            'assessmentId' => $assessment->getId(),
            'student'      => $student,
            'studentId'    => $student->getId(),
        ];

        if ($assessment->isSelfAssessable()) {
            $twigData['acknowlegementBlocked'] = false === $this->getRouteIfAuthorized('learningAssessment.postSelfAcknowledge', $routeArgs);
        } else {
            // if not self-assessable, the ack routes are always blocked
            $twigData['acknowlegementBlocked'] = true;
        }

        try {
            $assessment->assertAcceptSubmission($participant);
            $twigData['submissionsBlocked']    = false;
            $twigData['acknowlegementBlocked'] = false;
        } catch (DomainException $e) {
            $twigData['submissionsBlocked'] = $this->getErrorMapper()->generateException($e->getCode())->getMessage();

            // detect the combination of "accept none + self-assess" and allow ACK in this case
            $twigData['acknowlegementBlocked'] = false === ($assessment->isSelfAssessable() && $assessment->isAcceptNone());
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/dueWork/summary/student.twig',
                $twigData,
                "{$assessment->getTitle()} - {$student->getFullName()}",
                'submission'
            )
        );
    }


    /**
     * Sanitize a string for use in filename
     *
     * @param string $string
     * @return string
     */
    private function sanitizeForFilename($string): string
    {
        return preg_replace("/[^A-Za-z0-9\-. ]/", '_', $string);
    }


    /**
     * Download all student submissions for a given assessable
     * @param IAssessable $assessment
     * @throws MapperException
     * @api   GET /learning/assessments/:assessmentId/submissions/download
     * @since 165
     */
    public function downloadSubmissions(IAssessable $assessment): void
    {
        // Creating a ZIP archive might take a while
        $this->extendExecutionTime();

        // Retrieve all participants

        $participants = $assessment->getAllParticipants();

        // Cache users

        /** @var User[] $users */
        $users = [];

        // Create a zip stream to hold the downloaded submissions

        $titleSanitized = $this->sanitizeForFilename($assessment->getTitle());
        $dateNow        = Alaress_Date_Format::now()->getUsingFormat('Ymd-His');

        $zipOptions = new ZipStream\Option\Archive();
        $zipOptions->setSendHttpHeaders(true);
        $zipStream = new ZipStream\ZipStream("{$titleSanitized}-{$dateNow}.zip", $zipOptions);

        // Iterate participants and get their responses
        /** @var Participant $participant */
        foreach ($participants as $participant) {
            // Short-circuit if there's no interactions
            // (draft feedback does count, though)
            if ($participant->getAllFeedbacks()->count() === 0 && !$participant->hasSubmittedResponse()) {
                continue;
            }

            // Get the users details and generate an appropriate folder name
            if (!isset($users[$participant->getUser()->getId()])) {
                $users[$participant->getUser()->getId()] = $this->getUserMapper()->findByIdWithFallback($participant->getUser()->getId());
            }
            $participantUser = $users[$participant->getUser()->getId()];
            $baseFolderName = sprintf(
                "%s_%s_%d",
                $this->sanitizeForFilename($participantUser->getLastname()),
                $this->sanitizeForFilename($participantUser->getFirstname()),
                $participantUser->getId(),
            );

            // Iterate all their submitted responses
            /** @var Submission $response */
            foreach ($participant->getSubmittedResponses() as $response) {
                // If the response is a submission...
                if ($response instanceof Submission) {
                    // ...iterate all files in that submission and add them to the ZIP archive
                    foreach ($response->getAllFiles() as $file) {
                        if ($file->exists()) {
                            $zipFilename = sprintf(
                                "%s/%s/%s",
                                $baseFolderName,
                                $response->getSubmittedAt()->getUsingFormat('Ymd-His'),
                                $this->sanitizeForFilename($file->getBasename())
                            );
                            $zipStream->addFileFromStream($zipFilename, $file->readStream());
                        }
                    }
                }
            }
            $this->addSubmissionHistoryToZipStream($participant, $zipStream, $baseFolderName);
        }

        // Stream the zip archive
        $zipStream->finish();
        exit;
    }


    /**
     * Shows a rubric overview
     *
     * @param IAssessable|Model $assessment
     * @throws DomainException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws DefinitionException
     * @api   GET /learning/assessments/:assessmentId/overview
     * @since 165
     */
    public function getOverview(IAssessable $assessment): void
    {
        // Sort participant by lastname, firstname, and id
        $participants = $assessment->getParticipantUsers()->sortByCallback(
            static function (User $participantA, User $participantB) {
                return strnatcasecmp($participantA->getLastname(), $participantB->getLastname())
                    ?: strnatcasecmp($participantA->getFirstname(), $participantB->getFirstname())
                        ?: strnatcasecmp($participantA->getId(), $participantB->getId());
            }
        );

        $data = [
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                                   ->add(
                                       [
                                           $assessment->getTitle(),
                                           $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]),
                                       ]
                                   )
                                   ->add(['Rubric Report', '']),
            'dueWork'      => $assessment,
            'participants' => $participants,
        ];

        if ($assessment->getUseRubric()) {
            $rubricGrid = $this->rubricMapper->retrieveGrid($assessment, false);
            $assessment = $this->participantMapper->loadParticipationIntoAssessment($assessment);

            $data = array_merge(
                $data,
                [
                'headers' => $this->getRubricHeaders($rubricGrid),
                'marks'   => $this->getMarks($assessment->getAllParticipants()),
                ]
            );
        }

        $this->getResponse()->content->set(
            [
                'title'    => 'Rubric Report',
                'template' => '@learningAssessment/dueWork/overview.twig',
                'data'     => $data,
            ]
        );
    }


    /**
     * Downloads a rubric overview
     *
     * @param IAssessable|Model $assessment
     * @throws DefinitionException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws Exception
     * @since 165
     * @api   GET /learning/assessments/:assessmentId/overview/export?format=csv
     */
    public function downloadOverview(IAssessable $assessment): void
    {
        if (!$assessment->getUseRubric()) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_PAGE_NOT_FOUND);
        }

        $rubricGrid = $this->rubricMapper->retrieveGrid($assessment, false);
        $assessment = $this->participantMapper->loadParticipationIntoAssessment($assessment);

        $basename = (new \Url($assessment->getTitle()))->getSlug();
        $content  = $this->array2csv($this->collateResultSquare($rubricGrid, $assessment->getAllParticipants()));
        $headers  = $this->getResponse()->headers;

        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', strlen($content));

        $headers->set('Content-Disposition', "attachment; filename=\"$basename-results.csv\"");

        $this->getResponse()->content->set(
            [
                'format' => 'file',
                'data'   => $content,
            ]
        );
    }


    /**
     * Get headers for the rubric report or CSV download.
     *
     * @param Grid $grid
     * @return string[][] Grouped by capability name, then indicator ID
     */
    private function getRubricHeaders(Grid $grid): array
    {
        $headers = [];
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $capability                                           = $capabilityUsage->getCapability();
                $indicator                                            = $indicatorUsage->getIndicator();
                $headers[$capability->getName()][$indicator->getId()] = $indicator->getName();
            }
        }
        return $headers;
    }


    /**
     * Get rubric marks given for each indicator.
     *
     * @param ICollection|Participant[] $participants
     * @return IndicatorMark[][] Grouped in sets indexed by user.id
     */
    private function getMarks(ICollection $participants): array
    {
        $marks = [];

        foreach ($participants as $participant) {
            $feedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
            if (!$feedback) {
                continue;
            }

            try {
                $markedGrid = $this->rubricMapper->retrieveMarkedGrid($feedback);
            } catch (MapperException $e) {
                // can't really do anything
                continue;
            }
            $studentId = $participant->getUser()->getId();
            foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
                foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                    $marks[$studentId][$indicatorMark->getIndicatorUsage()->getIndicator()->getId()] = $indicatorMark;
                }
            }
        }
        return $marks;
    }


    /**
     * Creates a CSV structure for this rubric grid and its participants.
     *
     * @param Grid                     $grid
     * @param Collection|Participant[] $participants
     * @return array
     */
    private function collateResultSquare(Grid $grid, Collection $participants): array
    {
        // array inside an array that's *nearly* empty...
        $data = [['Name', 'External ID']];

        // array of all used indicator ids for the assessment
        $allIndicatorUsageIds = [];
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $allIndicatorUsageIds[] = $indicatorUsage->getIndicator()->getId();
            }
        }

        // put header row in array
        foreach ($this->getRubricHeaders($grid) as $grouping => $criteria) {
            foreach ($criteria as $id => $criterion) {
                $data[0][] = "$grouping - $criterion";
                $data[0][] = '';
            }
        }
        $data[0][] = 'Total';

        /**
         * Collate by id, each sub-collection will contain only 1 User element
         *
         * @var Collection[] $participantsGrouped
         */
        $participantsGrouped = $participants->groupByCallback(
            function (Participant $participant) {
                return $participant->getUser()->getId();
            }
        )->getAll();
        foreach ($this->getMarks($participants) as $studentId => $marks) {
            // array of all marked indicator ids of the participant
            $markedIndicatorIds = array_keys($marks);

            /** @var Participant $participant */
            $participant = $participantsGrouped[$studentId]->first();
            $studentName = $participant->getUser()->formatName('{lastname}, {firstname}');

            $row = [$studentName, $participant->getUser()->getExternalId()];
            $sum = null;

            if (count($marks)) {
                foreach ($allIndicatorUsageIds as $indicatorUsageId) {
                    if (in_array($indicatorUsageId, $markedIndicatorIds, true)) {
                        if ($marks[$indicatorUsageId] instanceof IndicatorMark) {
                            $descriptor = $marks[$indicatorUsageId]->getDescriptor();
                            $row[]      = $descriptor ? $descriptor->getDescription() : '';
                            $row[]      = $marks[$indicatorUsageId]->getValue();
                            $sum        += $marks[$indicatorUsageId]->getValue();
                        } else {
                            $row[] = $marks[$indicatorUsageId];
                            $row[] = '';
                        }
                    } else {
                        $row[] = '';
                        $row[] = '';
                    }
                }
            } else {
                $row = array_merge($row, array_pad([], count($data[0]) - 1, ''));
            }
            $row[]  = $sum;
            $data[] = $row;
        }
        return $data;
    }


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array): string
    {
        $fp = fopen('php://temp', 'w');
        foreach ($array as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);
        return $contents;
    }


    /**
     * Displays a form enabling a student to create Submission for a Due Work
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws ServiceNotFound If unable to locate HTML web Output in DI
     * @since 165
     * @api   GET /learning/assessment/:assessmentId/:participantId/submit
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        $allResponses = $participant ? $participant->getAllResponses() : new Collection();

        /* Otherwise continue
         */
        $latestResponse = null;
        $editorContents = '';
        $dangerZone     = null;

        $attachZoneFactory = new Assessment\Attachzone\Factory();

        /* Assume the most recent record is the latest submission
         */
        if ($allResponses->count()) {
            /** @var Submission $latestResponse */
            $latestResponse = $participant->findMostRecentResponse();

            /* If the latest response is a draft, collect all its contents for editing
             */
            if ($latestResponse->isDraft()) {
                /* Files attached to the draft submission
                 * Attach files if any exist
                 */
                $dangerZone = $attachZoneFactory->buildForDueWorkResponse($latestResponse);

                /* Load in the wizzywig instead of a file attachment,
                 * if the $file uses the wizzywig editor
                 */
                foreach ($latestResponse->getAllFiles() as $file) {
                    if ($file->getEditableInline()) {
                        $editorContents = $file->read();
                    }
                }
            } else {
                /* Otherwise, spawn an entirely new draft
                 * I would not expect it to recycle files from my previous Submitted submission
                 */
                $latestResponse = null;
                $dangerZone     = $attachZoneFactory->buildForDueWorkResponse(new Submission());
            }
        } else {
            /* Init a dangerZone that is ready to accept files for
             * the first ever draft
             */
            $dangerZone = $attachZoneFactory->buildForDueWorkResponse(new Submission());
        }

        /* Pump into twig land
         */
        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/dueWork/createSubmission.twig',
                [
                'breadcrumbs'    => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                    [
                        'Create Response for ' . $assessment->getTitle(),
                        '',
                    ]
                ),
                'assessment'     => $assessment,
                'student'        => $student,
                'participant'    => $participant,
                'attachzone'     => $dangerZone ? $dangerZone->getTwigContext() : null,
                'response'       => $latestResponse,
                'editorContents' => $editorContents,
                'policy'         => $this->getConfigLoader()->get('lms_student_policy'),
                'errorMessage'   => $errorMessage,
                ]
            )
        );
    }


    /**
     * Try to use a pseudo-standardised json payload return structure (I think)
     *
     * @param string $errorMessage
     * @param string $redirectTo
     * @param int    $errorCode
     * @throws InvalidStatusCode
     */
    private function configureResponseWithError(string $errorMessage, string $redirectTo, int $errorCode = 403): void
    {
        $rs = $this->getResponse();

        if ($this->getRequest()->isXhr()) {
            $result['success'] = false;
            $result['message'] = $errorMessage;

            $rs->status->setCode($errorCode);
            $rs->content->set($this->buildJsonOutput($result));
        } else {
            // Show some error message?
            $this->getSession()->getSegment(static::class)->set('errorMessage', $errorMessage);
            $rs->redirect->to($redirectTo);
        }
    }


    /**
     * Save a Response, on behalf of a User, for a particular Assessment
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @return void
     * @throws Exception
     * @throws InvalidStatusCode
     * @throws MapperException If unable to create new Response object
     * @throws MapperException\Deleted If specified Response is deleted
     * @throws PDOException If unable to save the Response
     * @throws RouteNotFound If unable to generate a specified Route
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws LogicException If files are not saved prior to insertion into kami upload queue
     * @throws \Schoolbox\File\Exception
     * @api POST /learning/assessments/:assessmentId/:studentId/submit
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        /* Set in javascript when automatically saving a draft
         * Careful the negation
         */
        $isFinal = !$rq->post['saveDraft'];
        $body    = $rq->post['body'];

        /* Determine whether a finalised submission could be initialised
         * Check that you don't exceed the specified rate limit
         */
        if ($isFinal) {
            try {
                (new ValidFinalisedSubmission())->isSatisfiedBy($assessment, $student, $this->getConfigLoader()->get('submission_window'));
            } catch (DomainException $e) {
                // Report errors in this thing
                if ($this->getDi()->has('sentry')) {
                    /** @var HubInterface $sentry */
                    $sentry = $this->getDi()->get('sentry');
                    $sentry->captureException($e);
                }

                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_SUBMISSION_WINDOW_EXCEEDED);
            }
        }

        if ($rq->post['responseId']) {
            // Load an existing response to update
            $response = $this->dueWorkResponseMapper->findById($rq->post['responseId']);

            /* @var Response\Model $response */
            //check if $response has already been finalised, if so error
            if (!$response->isDraft()) {
                //cannot update
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_WORK_SUBMITTED);
            }
        } else {
            // Create a new response
            $response = $this->dueWorkResponseMapper->createModel()
                                                    ->setSubmittedBy($this->getUser())
                                                    ->setSubmittedOnBehalfOf($student)
                                                    ->setCreatedAt(Alaress_Date_Format::now())
                                                    ->setUpdatedAt(Alaress_Date_Format::now());
        }
        $response->setAssessable($assessment);

        // Replace files with what was actually submitted
        $response->setAllFiles(new Collection());

        /* Add the online submission to this response's list of files
         * only if there is content in it.
         */
        if (strlen(trim(strip_tags($body)))) {
            $response->attachOnlineSubmissionContent($body);
        }

        // Make one of those factories that builds storages from hashes
        $factory = new CollectorFactory();
        if (!empty($rq->post['attachment'])) {
            // Validate attachment list can actually be built
            try {
                $attachments = $factory->build($rq->post['attachment'])->getAll();
            } catch (InvalidArgumentException $e) {
                $errorMessage = 'One or more files were not able to be attached';
                $redirect     = $this->getRouter()->generate(
                    'learningAssessment.getCreateResponseForm',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId'    => $student->getId(),
                    ]
                );

                $this->configureResponseWithError($errorMessage, $redirect);
                return;
            }

            foreach ($attachments as $attachment) {
                /* Don't stack the online-submission.html file here, it should never
                 * appear in the attached files list at all (it should always be
                 * loaded into the wizzywig)
                 */
                $attachedFile = $this->submissionFileMapper->createModel()
                                                           ->setStorage($attachment)
                                                           ->setFilename($attachment->getFilename())
                                                           ->setEditableInline(false);

                /* Stack it up
                 */
                $response->getAllFiles()->add($attachedFile);
            }
        }

        /* If we're saving the final version of the submission (ie. not a draft) ram the current datetime in
         * If this requires self-assessment, then this will always be a draft;
         * participant must finalise their response as part of self-review.
         */
        $participant    = $assessment->getParticipantIfExtant($student);
        $selfAssessable = $participant ? (new ValidSelfAssessable())->isSatisfiedBy($this->getUser(), $participant) : false;

        if ($isFinal) {
            $validateSubmission = !$rq->post->getBool('submitted_in_person');

            // verify that there should be any files attached; if not, that's a fail case
            if ($validateSubmission && $response->getAllFiles()->count() === 0) {
                $errorMessage = 'One or more files failed to attach to your submission; please try again.';
                $redirect     = $this->getRouter()->generate(
                    'learningAssessment.getCreateResponseForm',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId'    => $student->getId(),
                    ]
                );

                $this->configureResponseWithError($errorMessage, $redirect);
                return;
            }

            /* submitted_at is determined after the next step when creating a self-assessed or self-reflected
             * response. Otherwise, proceed normally
             */
            if (!$selfAssessable) {
                $response->setSubmittedAt(Alaress_Date_Format::now());
            }
        }

        /* Do the actual saving
         */
        $this->dueWorkResponseMapper->save($response);

        // If not a draft, check if any files should be uploaded to Kami
        if ($isFinal) {
            $this->enqueueKamiUploads($response->getAllFiles());
        }

        /* Similarity Report endpoints should only spawn upon saving a Submitted IResponse instance
         * This screen is shared between self-reflection and non-self-reflection steps
         * Self-reflection's Similarity Check stubs saving .......happens elsewhere
         *
         * Similarity Check must be enabled to use plag or turnitin
         * This is processed in a detached function so it doesn't hang the ui
         * while it's executing, since accessing third party api can be slow
         */
        $detachedFunction = static function () {
        };

        if ($isFinal && !$selfAssessable && $response->getId() && $assessment->getSimilarityCheckEnabled()) {
            /* This is the original behaviour. Note that the Plagscan Mapper itself can
             * figure out if plagscan is enabled or not so we don't need to do it here
             *
             * This is only a temporary setup - eventually plagscan goes away entirely,
             * halving the logic established here
             */
            $isPlagscan = true;
            $isTurnitin = false;

            /* Only one of these can be active at a time, but both universes can co-exist
             * Specifically enable turnitin if its api key was set
             */
            if ('' !== (string)$this->getConfigLoader()->get('turnitin_api_key')) {
                $isPlagscan = false;
                $isTurnitin = true;
            }

            if ($isPlagscan) {
                /* Save stubble record and send each file in the Submission to Plagscan
                 * if the Submission was saved successfully
                 *
                 * Only plagscan when actually submitting final
                 * Only plagscan non-online-submission files (for some reason, plag won't do html files)
                 */
                $plagscanMapper = PlagscanMapper::getInstance();

                foreach ($response->getAllFiles() as $currentFile) {
                    if (!$currentFile->getEditableInline()) {
                        $plagscanMapper->saveNewPlagscanStub($currentFile);
                    }
                }

                /* Set up detached function so plagscan can continue in the background
                 */
                $detachedFunction = static function () use ($plagscanMapper, $response) {
                    foreach ($response->getAllFiles() as $currentFile) {
                        $filename = $plagscanMapper->plagscanFilename($currentFile);

                        $plagscanMapper->saveNewPlagscan($currentFile, $filename);
                    }
                };
            } elseif ($isTurnitin) {
                /* Set up a turnitin stub record
                 * This allows it to be picked up and processed by an async script later
                 * if it doesn't start processing via the $detachedFunction
                 */
                $turnitinMapper = Turnitin\Mapper::getInstance();

                foreach ($response->getAllFiles() as $currentFile) {
                    $turnitinMapper->saveStub($currentFile);
                }

                /* Set up detached function so turnitin can continue in the background
                 */
//                $detachedFunction = static function () use ($isFinal, $selfAssessable, $response, $assessment) {
//                    /* TODO Turnitin api endpoint implementation
//                     */
//                };
            }
        }

        /* Asynchronously shove it into plagscan so we don't make the user wait ages
         * for it to read 1 byte at a time >_<
         */
        $this->getResponse()->content->set(
            [
                'format'     => 'noContent',
                'postOutput' => [
                    $detachedFunction
                ],
            ]
        );

        /* Saving drafts from the wizzywig
         */
        if ($rq->isXhr()) {
            $result['success'] = true;
            $result['message'] = 'OK';
            $result['id']      = $response->getId();

            $rs->status->setCode(200);
            $rs->content->set($this->buildJsonOutput($result));
        } else {
            /* Go to the next step of Self-Assessment/Self-Reflection if appropriate
             */
            if ($selfAssessable) {
                $rs->redirect->to(
                    $this->getRouter()->generate(
                        'learningDueWorkResponse.getParticularResponseForSelfReflection',
                        [
                            'assessmentId' => $assessment->getId(),
                            'studentId'    => $student->getId(),
                            'responseId'   => $response->getId(),
                        ]
                    )
                );
            } else {
                // redirect after saving to Due Work Summary For Particular Student
                $rs->redirect->to(
                    $this->getRouter()->generate(
                        'learningAssessment.getSummary',
                        [
                            'assessmentId' => $assessment->getId(),
                            'studentId'    => $student->getId(),
                        ]
                    )
                );
            }
        }
    }


    /**
     * Acknowledge receipt of (potentially offline) submission from dueWork status page
     *
     * Creates an empty SOBO. Needs a different route to postCreateResponse()
     * to cater for different auth rules.
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @api   POST /learning/assessments/:assessmentId/:studentId/received
     * @since 17.0
     */
    public function postStatusAcknowledge(IAssessable $assessment, User $student): void
    {
        try {
            $response = $this->persistAcknowledgementResponse($assessment, $student, Alaress_Date_Format::now());
            $data     = [
                'success'       => true,
                'id'            => $response->getId(),
                'submittedLate' => $assessment->isOverdue() || $assessment->isPastClose(),
            ];
            $code     = 200;
        } catch (PDOException $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $code = 403;
        }
        $this->getResponse()->status->set($code);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Action for a due work participant to create an empty acknowledgement
     * response.
     *
     * If the assessment doesn't allow self-reflect or self-assessment, this
     * will simply create the acknowledgement response.
     *
     * If it does, it will:
     * * Redirect to the self-reflect screen for the most recent draft response
     *   so the participant may reflect on their work, and publish from there
     * * If there is no draft; a new draft acknowledgement response will be
     *   created, and then will redirect to the self-reflect screen for that
     *   response
     *
     * TODO merge common code w/ postPendingAcknowledge, postStatusAcknowledge
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @api   POST /learning/assessments/:assessmentId/:studentId/ackDraft
     * @since 18.0
     */
    public function postSelfAcknowledge(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);
        $selfReview  = $assessment instanceof Model && $assessment->isSelfAssessable();

        // Route and arguments for location to redirect to once acknowledged.
        $route  = 'learningAssessment.getSummary';
        $params = [
            'assessment'   => $assessment,
            'assessmentId' => $assessment->getId(),
            'student'      => $student,
            'studentId'    => $student->getId(),
        ];

        try {
            if ($selfReview) {
                $response = $participant ? $participant->findMostRecentDraftResponse() : null;
                if (!$response) {
                    $response = $this->persistAcknowledgementResponse($assessment, $student, null);
                }

                $route                = 'learningDueWorkResponse.getParticularResponseForSelfReflection';
                $params['response']   = $response;
                $params['responseId'] = $response->getId();
            } else {
                $response = $this->persistAcknowledgementResponse($assessment, $student, Alaress_Date_Format::now());
            }

            $data = [
                'success'       => true,
                'id'            => $response->getId(),
                'submittedLate' => $assessment->isOverdue() || $assessment->isPastClose(),
                'location'      => $this->getRouteIfAuthorized($route, $params, $this->getUser()),
            ];
            $code = 200;
        } catch (PDOException $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $code = 403;
        }
        $this->getResponse()->status->set($code);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Acknowledge receipt of (potentially offline) submission
     *
     * Creates an empty SOBO. Needs a different route to postCreateResponse()
     * to cater for different auth rules.
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws UnexpectedValueException
     * @api   POST /learning/assessments/:assessmentId/:studentId/ack
     * @since 17.0
     */
    public function postPendingAcknowledge(IAssessable $assessment, User $student): void
    {
        try {
            $this->persistAcknowledgementResponse($assessment, $student, Alaress_Date_Format::now());
            $participant = $assessment->getParticipant($student);
            $this->getResponse()->content->set(
                $this->buildUnthemedHtmlOutput(
                    '@learning/fragments/toMarkParticipant.twig',
                    [
                    'participant' => $participant,
                    'assessment'  => $participant->getAssessable(),
                    ]
                )
            );
            $code = 200;
        } catch (PDOException $e) {
            $this->getResponse()->content->set(
                $this->buildJsonOutput(
                    [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ]
                )
            );
            $code = 403;
        }
        $this->getResponse()->status->set($code);
    }


    /**
     * Common logic for 'dueWork status' and 'pending assessments' pages 'acknowledgement of submission receipt'.
     *
     * @param Model                    $dueWork
     * @param User                     $student
     * @param Alaress_Date_Format|null $submittedDate Date this was submitted at, null if draft
     * @return Submission
     * @throws PDOException If something went wrong in the save process
     * @throws UnexpectedValueException
     */
    private function persistAcknowledgementResponse(Model $dueWork, User $student, ?Alaress_Date_Format $submittedDate): Response\Model
    {
        $response = $this->dueWorkResponseMapper->createModel()
                                                ->setAssessable($dueWork)
                                                ->setSubmittedBy($this->getUser())
                                                ->setSubmittedOnBehalfOf($student)
                                                ->setSubmittedAt($submittedDate)
                                                ->setCreatedAt(Alaress_Date_Format::now())
                                                ->setUpdatedAt(Alaress_Date_Format::now());

        $this->dueWorkResponseMapper->save($response);

        // Ensure we update our domain graph so callers to this method can rely on the graph being accurate
        $participant = $dueWork->getParticipantIfExtant($student);
        if (!$participant) {
            $dynamicPlan = $dueWork->getDynamicPlan();
            $participant = new Participant($student, $dynamicPlan);
            /** @noinspection NullPointerExceptionInspection (because we can't have no participant without a dynamic plan) */
            $dynamicPlan->getParticipants()[$student->getId()] = $participant;
        }
        $participant->getAllResponses()[$response->getId()] = $response;

        return $response;
    }


    /**
     * @param Model|IAssessable $assessment
     * @return array
     * @throws UnexpectedValueException
     * @throws MapperException
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        $stoneFactory = (new RosettaStoneFactory())->build(\Schoolbox\L10n\Mapper::getInstance());

        // setup some data...
        $data = [
            'acceptingResponses' => true,
            'attachzone'         => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
            'allMarkTypes'       => $this->getAllMarkTypes($assessment),
            'allGradeSchemes'    => $this->getAllGradeSchemes(),
            'selfReviewTypes'    => [
                'none'    => [
                    'value' => SubmissionBox::SELF_REVIEWABILITY_NONE,
                    'label' => 'None',
                ],
                'reflect' => [
                    'value' => SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY,
                    'label' => $stoneFactory->translate('selfReflection', false, true)
                ],
                'full' => [
                    'value' => SubmissionBox::SELF_REVIEWABILITY_FULL,
                    'label' => sprintf(
                        '%s + %s',
                        $stoneFactory->translate('selfReflection', false, true),
                        $stoneFactory->translate('selfAssessment', false, true)
                    )
                ],
            ],
        ];

        // ...append rubric-related data...
        $data = array_merge($data, $this->generateRubricTwigData($assessment));

        // ...and scene.
        return $data;
    }


    /**
     * Common logic when generating Rubrics
     *
     * @param Model $dueWork
     * @return array
     * @throws MapperException
     * @throws UnexpectedValueException
     */
    private function generateRubricTwigData(Model $dueWork): array
    {
        $data = [];

        $rubricZone = new Zone('rubric');
        // JS file.type isn't set reliably enough to use text/csv mime filter
        $rubricZone->setFileVar('rb_attach[]')->setAttachPrompt('Import a rubric');//->setMimeFilter('text/csv');
        $data['rubricAttachZone'] = $rubricZone->getTwigContext();

        $resultSets = $this->resultSetMapper->findAll();

        $rs = new Collection();

        foreach ($resultSets as $result) {
            if (!$rs[$result->getResultSetId()] instanceof ICollection) {
                $rs[$result->getResultSetId()] = $this->resultSetMapper->createCollection();
            }
            $rs[$result->getResultSetId()][$result->getValue()] = $result;
        }

        $data['rubricResultSets'] = $rs;

        // Do any students have marks for this rubric?
        $levelIds      = RubricLevelMapper::getInstance()->findIdsByColumn('b.assessment_id', $dueWork->getId());
        $descriptorIds = RubricDescriptorMapper::getInstance()->findIdsByColumn('level_id', $levelIds);
        $countMarkIds  = RubricIndicatorMarkMapper::getInstance()->countByColumnList(
            [
                'descriptor_id' => $descriptorIds,
                'valid'         => true,
            ]
        );

        $data['rubricHasMarks'] = $countMarkIds > 0;

        // The rubric grid can be used as is without Twig-wrangling
        $rubricGrid = $this->rubricMapper->retrieveGrid($dueWork, true);
        if (\count($rubricGrid->getLevels()) === 0) {
            // Two fake levels
            $rubricGrid->getLevels()
                       ->add(new Level())
                       ->add(new Level());
        }
        $data['rubricGrid'] = $rubricGrid;

        return $data;
    }


    /**
     * Update particular fields of an existing IAssessable
     *
     * @param IAssessable $assessment
     * @return void
     * @throws InvalidStatusCode
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function patchUpdateAssessment(IAssessable $assessment): void
    {
        /* TODO: Make this more generalised
         * For now the only utility here is to update Skip Unsubmitted setting
         */
        /** @var DueWork $assessment */
        if ((int)$this->getRequest()->post['showSubmittedOnly']) {
            $assessment->showOnlySubmittedStudents();
            $skipUnsubmitted = true;
        } else {
            $assessment->showAllSubmissibleStudents();
            $skipUnsubmitted = false;
        }

        /* Persist checkbox setting
         */
        try {
            $submitBox = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $assessment->getId());
            $submitBox->setSkipUnsubmitted($skipUnsubmitted);
            SubmissionBoxMapper::getInstance()->save($submitBox);
        } catch (MapperException $e) {
            // noop - submission box doesn't exist
        }

        $this->getResponse()->status->setCode(204);
        $this->getResponse()->content->set($this->buildJsonOutput(null));
        return;
    }


    /**
     * Enqueue any eligible files for upload to Kami.
     *
     * Must be called *after* the files are saved to ensure their IDs exist
     *
     * @param Collection|SubmissionFile[] $files
     * @return GenericCollection|Kami\Model[]
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function enqueueKamiUploads(Collection $files): GenericCollection
    {
        $kamiModels = $this->kamiMapper->createCollection();

        // Short-circuit if Kami is not enabled
        if (!$this->kamiConnector->isEnabled()) {
            return $kamiModels;
        }

        // Check files have been saved so Kami docs can point to them
        $fileIds = $files->map(
            function (SubmissionFile $file) {
                if (empty($file->getId())) {
                    throw new LogicException("File '{$file->getBasename()}' has not been saved, cannot process");
                }
                return $file->getId();
            }
        );

        // Check if any files have already been queued for kami upload
        $existingKamiModels = $this->kamiMapper->findByColumn('student_submission_file_id', $fileIds);
        $existingFileIds    = $existingKamiModels->map(
            function (Kami\Model $kamiModel) {
                return $kamiModel->getStudentSubmissionFile()->getId();
            }
        )->getAll();
        $existingFileIds    = array_combine($existingFileIds, $existingFileIds);

        foreach ($files as $file) {
            if ($file->getEditableInline()) {
                // For now, we ignore the WYSIWYG editor contents.
                continue;
            }
            if (array_key_exists($file->getId(), $existingFileIds)) {
                // Don't re-enqueue if already queued
                continue;
            }
            if (!array_key_exists(strtolower($file->getExtension()), Kami\Model::ALLOWED_FILE_TYPES)) {
                // Don't enqueue if extension not eligible for Kami
                continue;
            }
            // File is eligible, and not yet queued.
            $kamiModel = $this->kamiMapper->createModel()
                                          ->setStudentSubmissionFile($file)
                                          ->setStatusQueued();
            $this->kamiMapper->save($kamiModel);
            $kamiModels[$kamiModel->getId()] = $kamiModel;
        }
        return $kamiModels;
    }


    /**
     * Add this participant's submission history (as a generated PDF) to the Zip
     * stream for download.
     *
     * @param Participant         $participant    The participant record
     * @param ZipStream\ZipStream $zipStream      The stream to download
     * @param string              $baseFolderName The folder within `$zipStream`
     *                                            to put the PDF
     * @throws Gotenberg\ClientException
     * @throws Gotenberg\FilesystemException
     * @throws Gotenberg\RequestException
     * @throws RouteNotFound
     * @throws ZipStream\Exception\FileNotFoundException
     * @throws ZipStream\Exception\FileNotReadableException
     */
    private function addSubmissionHistoryToZipStream(
        Participant $participant,
        ZipStream\ZipStream $zipStream,
        string $baseFolderName
    ): void {
        // Generate a PDF of the submission history page,
        // Since PDF generation is a remote service, need a fully-qualified
        // domain name in the URL.
        $urlFactory = new Factory($this->getConfigLoader());
        $url        = $urlFactory
            ->buildFullyQualified()
            ->setScheme('https')
            ->setPort($this->request->server->get('SERVER_PORT'))
            ->setPath(
                $this->router->generate(
                    'learningAssessment.getSummary',
                    [
                        'assessmentId' => $participant->getAssessable()->getId(),
                        'studentId'    => $participant->getUser()->getId(),
                    ],
                )
            );

        $requestFactory = new RequestFactory($this->getConfigLoader()->get(ConfigMapper::NAME_GOTENBERG_API_KEY));
        $request        = $requestFactory->buildUrlRequest(
            $url->toString(),
            RequestFactory::DEFAULT_WAIT_DELAY,
            RequestFactory::DEFAULT_WAIT_TIMEOUT,
            session_id(),
        );
        $request->setScale(0.75);

        // Generate, download and store the PDF
        $tempfile = sb_tempnam();
        try {
            $this->pdfGenerator->store($request, $tempfile);
            $zipFilename = sprintf(
                "%s/submissionHistory.pdf",
                $baseFolderName,
            );
            $zipStream->addFileFromPath($zipFilename, $tempfile);
        } finally {
            // Clean up once finished
            unlink($tempfile);
        }
    }
}
