<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Turnitin\Api as TurnitinApi;
use Schoolbox\Learning\DueWork\StateMapper;
use GuzzleHttp\Client;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Attachzone;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Response\DataSource;
use Schoolbox\Learning\Assessment\Timeline\ResponsesOnly;
use Schoolbox\Learning\InlineGrader\Factory;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Turnitin\Exception\Credentials;
use Schoolbox\Learning\Turnitin\Helper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;
    /**
     * @var DataSource
     */
    protected $responseDataSource;

    /** @var BreadcrumbFactory */
    protected $bakery;

    /** @var Term\Mapper */
    private $termMapper;

    /** @var Kami\Mapper */
    private $kamiMapper;

    /** @var Kami\Connector */
    private $kamiConnector;


    /**
     * @var Access
     */
    private Access $access;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper       $errorMapper
     * @param DataSource        $responseDataSource
     * @param BreadcrumbFactory $bakery
     * @param Term\Mapper       $termMapper
     * @param Kami\Mapper       $kamiMapper
     * @param Kami\Connector    $kamiConnector
     * @param Access            $access
     * @param FilesystemProxy   $filesystemProxy
     */
    public function __construct(
        ErrorMapper $errorMapper,
        DataSource $responseDataSource,
        BreadcrumbFactory $bakery,
        Term\Mapper $termMapper,
        Kami\Mapper $kamiMapper,
        Kami\Connector $kamiConnector,
        Access $access,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->errorMapper        = $errorMapper;
        $this->responseDataSource = $responseDataSource;
        $this->bakery             = $bakery;
        $this->termMapper         = $termMapper;
        $this->kamiMapper         = $kamiMapper;
        $this->kamiConnector      = $kamiConnector;
        $this->access             = $access;
    }

    /**
     * An alias for getParticularResponse but with blind mode activated
     * There are too many little twig subcomponents to justify propagating the querystring param
     * being built in twig separately from backend
     *
     * @param DueWork    $assessment
     * @param User       $student
     * @param Model|null $response
     * @throws ApplicationException\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getParticularResponseBlind(
        DueWork $assessment,
        User $student,
        Model $response = null
    ): void {
        $this->getParticularResponse($assessment, $student, $response, true);
    }

    /**
     * Get the contents of a particular $response for instructor marking.
     *
     * Show the specified kami doc in an inline window if available;
     * otherwise display html/audio/video/image contents differently, inline with the dom
     *
     * I can't call it getResponse() lol wups
     *
     * @param DueWork    $assessment
     * @param User       $student
     * @param Model|null $response
     * @param bool|null  $blindMode
     * @throws ApplicationException\Exception When trying to access a draft Response when you shouldn't
     * @throws \Aura\Router\Exception\RouteNotFound If cannot generate a requested route
     * @throws \InvalidArgumentException If assessment due date is null.
     * @throws \LogicException If attempting to update the status of a kami doc that is not processing.
     * @throws \PDOException If something went wrong when saving the kami document's status
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException If unexpected status change is reported from Kami API
     */
    public function getParticularResponse(
        DueWork $assessment,
        User $student,
        Model $response = null,
        ?bool $blindMode = false
    ): void {

        $participant = $assessment->getParticipant($student);

        /* Module doesn't have access to $this->getUser()
         *
         * Superusers can see draft Responses in the inline grader
         * Teachers cannot see draft Responses in the inline grader
         */
        $mostRecentResponse = $participant->findMostRecentSubmittedResponse();

        if (!$response) {
            $response             = $mostRecentResponse;
            $isMostRecentResponse = true;
        } else {
            $isMostRecentResponse = ($mostRecentResponse && $mostRecentResponse->getId() == $response->getId());
        }

        $file = null;
        $selfAssessmentFeedback = null;

        /* If there is an an actual Latest Response, use it and subject
         * it to the usual checks.
         *
         * This means you won't be able to spawn Rubrics selections for
         * Floating Feedback ref. jleckie
         */
        if ($response) {
            if (!$this->getUser()->isSuperuser() && $this->getUser()->getRole()->isStaff() && !$response->isSubmitted()) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            // The file that's being reviewed
            $file = $this->getFile($response, $this->getRequest()->query->get('hash'));

            // Self-assessed feedback (published only)
            $selfAssessmentFeedback = $response->findMostRecentSelfAssessedPublishedFeedback();
        }

        // Authoritative feedback, draft or published
        $feedback = $participant->findMostRecentInstructorGivenFeedback();

        // The zone which contains the files
        $attachZoneFactory = new Attachzone\Factory();
        $attachZone        = $attachZoneFactory->buildForDueWorkFeedback(
            $feedback instanceof Feedback ? $feedback : null
        );

        // Is the page being displayed in readonly mode?
        $isInteractionLocked = (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment);

        /* Decide which StudentCarousel to show
        */
        $carousel = $blindMode ?
            (new StudentCarousel\Blind(
                $this->getRouter(),
                $participant->getUser(),
                $assessment
            )) :
            (new StudentCarousel\Standard(
                $this->getRouter(),
                $participant->getUser(),
                $assessment
            ));

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) ])
                ->add(['Inline Grader', '']),
            'response' => $response,
            'isMostRecentResponse' => $isMostRecentResponse,
            'feedback' => $feedback,
            'selfAssessmentFeedback' => $selfAssessmentFeedback,
            'attachzone' => $attachZone->getTwigContext(),
            'selectedFileHash' => $this->request->query->get('hash'),
            'markSchemeName' => $this->deriveSchemeName($assessment->getMarkScheme()),

            /* The Student Carousel is the selectize thing that cycles between the currently
             * selected student and the previous/next students participating in the assessment
             * (it appears at the top of Inline Grader)
             *
             * the 'b' option indicates whether the Carousel should initiate Blind Marking, which
             * obfuscates the student's identifying features while you're doing the marking
             */
            'carousel' => $carousel->produce(),

            /* This is passed through standard.twig into comment.twig, where a placeholder text
             * identifies the current guy you're marking
             */
            'blindMode' => $blindMode,

            /* A list of all the Responses that the participant has spawned
             */
            'responseList' => (new ResponsesOnly($participant))->getForTeacher(),

            /* This is now conditional depending on the presence of a $response
             */
            'file' => $file,
            'subfragment' => $file ? $this->getSubFragment($file) : null,

            /* For saving feedbacks
             */
            'errors' => StateMapper::getInstance()->listDisplayValues(),
            'participant' => $participant,

            /* Similarity Check status
             */
            'turnitinApiEnabled' => (new Turnitin\Helper($this->getConfigLoader()))->isEnabled($this->getUser()),
            'interactionLocked' => $isInteractionLocked,
            'studentView' => false,
            'selfAssess' => false,
            'selfReflect' => false,
        ];

        /* Rubrics data
        */
        $rubricMapper = RubricDataSource::getInstance();
        if ($assessment->getUseRubric()) {
            $assessment->setRubricGrid($rubricMapper->retrieveGrid($assessment, false));
            if ($feedback instanceof Feedback) {
                $feedback->setRubricMarkedGrid($rubricMapper->retrieveMarkedGrid($feedback));
            }
        }

        // Self-assessment rubric data
        if ($selfAssessmentFeedback instanceof Feedback && $assessment->getUseRubric()) {
            $selfAssessmentFeedback->setRubricMarkedGrid($rubricMapper->retrieveMarkedGrid($selfAssessmentFeedback));
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/inlineGrading/standard.twig', $data));
    }

    /**
     * Display a kami document in an iFrame
     *
     * @api   /learning/kami/{kamiId}
     * @since 17.5
     * @param Kami\Model $kami
     * @param Model      $response
     * @throws Kami\Exception\ApiException
     * @throws Kami\Exception\EmptyApiKeyException
     * @throws Kami\Exception\NotFound
     * @throws Kami\Exception\ResponseDecodeException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws Kami\Exception\EmptySiteSchoolCode
     */
    public function getKamiView(Kami\Model $kami, Model $response): void
    {
        // Was it still being processed by Kami last time we checked?
        if ($kami->isStatusProcessing()) {
            $statusChecker = new Kami\StatusChecker($this->kamiMapper, $this->kamiConnector);
            $kami          = $statusChecker->updateStatus($kami);
        }

        // Don't attempt to get a view session if not done processing
        $session = null;
        if ($kami->isStatusDone()) {
            $session = $this->kamiConnector->viewSession($kami->getKamiDocumentId(), $this->getUser(), false);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/kamiView.twig', [
            'kami'     => $kami,
            'session'  => $session,
            'response' => $response,
        ]));
    }


    /**
     * @param Kami\Model $kami
     * @throws Kami\Exception\EmptyApiKeyException If Kami is not enabled
     * @throws Kami\Exception\NotFound If unable to find the document in Kami
     * @throws Kami\Exception\ResponseDecodeException If cannot decode the Kami Response
     * @throws \Aura\Web\Exception\InvalidStatusCode If tried to set an invalid HTTP status code on the response.
     */
    public function getKamiExport(Kami\Model $kami): void
    {
        // Less overhead in sleeping the server process, than spinning it up per client poll.
        // Total timeout before failure = $sleep * $exponent^$maxPolls - $sleep
        $maxPolls  = 10;
        $exponent  = 2;
        $sleep     = 500000; // 0.5 seconds initial back-off
        $pollCount = 0;
        $export    = $this->kamiConnector->createAnnotatedExport($kami->getKamiDocumentId());
        while ($export->isStatusPending()) {
            if ($pollCount >= $maxPolls) {
                break;
            }
            usleep($sleep);
            $sleep  *= $exponent; // exponential back-off.
            $export = $this->kamiConnector->getExport($export->getId());
            $pollCount++;
        }

        // Let the browser know that the synchronous request has finished (either success or failure)
        $response = $this->getResponse();
        $token    = $this->getRequest()->query->get('token');
        $response->cookies->set('kamiExportToken', $token, null, '/', null, null, false);
        $response->cookies->set('kamiExportStatus', $export->getStatus(), null, '/', null, null, false);

        // Unable to generate the export? (either due to error or timeout)
        if (!$export->isStatusDone()) {
            // Don't change browser viewport
            $response->status->setCode(204);
            return;
        }

        // This works in all browsers, but means we need to stream the file through schoolbox as a proxy.
        $response->status->setCode(200);
        $filename = $kami->getStudentSubmissionFile()->getBasename();
        $guzzleResponse = (new Client())->get($export->getFileUrl());
        $response->headers->set('Content-Description', 'File Transfer');
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"');
        $response->headers->set('Expires', '0');
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Length', $guzzleResponse->getHeader('Content-Length')[0]);
        $response->content->set($this->buildFileOutputFromString($guzzleResponse->getBody()));
    }


    /**
     * Get which file the user is likely to wish to see details for
     *
     * @param Model       $response
     * @param null|string $hash
     * @return null|SubmissionFile
     */
    private function getFile(Model $response, ?string $hash): ?SubmissionFile
    {
        // Use hash if possible
        if ($hash) {
            try {
                return $response->findFileByHash($hash);
            } catch (\OutOfBoundsException $e) {
                // no op. Silently fall back to first file if invalid hash given
            }
        }
        // Sort files alphabetically
        return $response->getAllFiles()->sortBy('filename', 'asc')->first();
    }


    /**
     * Preview the Response's filetype
     *
     * @param SubmissionFile $file
     * @return array
     * @throws ApplicationException\Exception If a Kami exception occurs
     */
    private function getSubFragment(SubmissionFile $file): array
    {
        try {
            return (new Factory\ResponseFile(
                $this->kamiMapper,
                $this->kamiConnector,
                $this->termMapper
            ))->produce($file, $this->getUser());
        } catch (\Exception $e) {
            // catching all exceptions, as we don't want to break inline grader.
            $this->captureException($e);
            return [
                'strategy' => 'kami_error.twig',
                'data'     => [
                    'reason' => $e->getMessage(),
                    'date'   => \Alaress_Date_Format::now(),
                ],
            ];
        }
    }


    /**
     * @param IMarkScheme $scheme
     * @return string
     */
    protected function deriveSchemeName(IMarkScheme $scheme)
    {
        $c = get_class($scheme);
        return substr($c, strrpos($c, '\\') + 1);
    }

    /**
     * @api   DELETE /assessments/:assessmentId/response/:responseId
     * @since 165
     * @param IAssessable $assessment
     * @param Model $response
     */
    public function deleteParticularResponse(IAssessable $assessment, Model $response)
    {
        $code = 204;
        $data = [];

        try {
            $this->responseDataSource->delete($response);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $code = 400;
            $data = $e->getMessage();
        }

        $this->getResponse()->status->set($code);
        $this->getResponse()->content->set($this->buildContent('', $data));
    }


    /**
     * Dump out a turnitin report
     *
     * @param IAssessable         $assessment
     * @param IResponse           $response
     * @param Turnitin\File\Model $turnitin
     * @param Participant         $participant
     * @throws BadEndpoint
     * @throws Credentials
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getTurnitinReport(IAssessable $assessment, IResponse $response, Turnitin\File\Model $turnitin, Participant $participant): void
    {
        /* Check if turnitin is configured sufficiently
         */
        if (!(new Helper($this->getConfigLoader()))->isConfigured()) {
            throw new \DomainException('Turnitin configuration invalid.');
        }

        /* We need this api
         */
        $api = new TurnitinApi(
            $this->getConfigLoader()->get('site_version'),
            $this->getConfigLoader()->get('turnitin_api_host'),
            $this->getConfigLoader()->get('turnitin_api_key')
        );

        /* A specially-hardcoded value to have programmatically accepted the eula
         * It's part of our T&C with turnitin
         */
        $eulaAcceptance = '2021-01-01T00:00:00.000Z';

        /* Choose a viewer permission set based on your role
         * Staff members get a different set than students or parents
         *
         * Start with the lowest-permission settings
         */
        $payload = [
            'viewer_user_id' => $this->getUser()->getId(),
            'locale' => 'en',
            'viewer_default_permission_set' => Turnitin\File\Process\Report::VIEWER_PERMISSION_SET_STUDENT,
            'eula' => [
                'version' => 'v1beta',
                'accepted_timestamp' => $eulaAcceptance,
                'language' => 'en-US'
            ],
        ];

        /* Then decide if you're a staff and you need more stuff
         */
        if ($this->access->isInstructor($this->getUser(), $assessment->getContext()->getFolder())) {
            $payload = array_merge($payload, [
                'viewer_default_permission_set' => Turnitin\File\Process\Report::VIEWER_PERMISSION_SET_STAFF,
                'similarity' => [
                    'view_settings' => [
                        'save_changes' => true
                    ]
                ]
            ]);
        }

        /* TODO Apparently localisation is a thing, set via locale tuple;
         *  but I have assumed en for now
         */
        $viewerUrl = $api->retrieve('POST', "/api/v1/submissions/{$turnitin->getTurnitinSubmissionId()}/viewer-url", $payload);

        if (null !== $viewerUrl) {
            $data = [
                'url' => $viewerUrl['viewer_url'],
                'turnitin' => $turnitin,
                'assessment' => $assessment,
                'response' => $response,
                'participant' => $participant,
                'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                    ->add([$assessment->getTitle(), $this->getRouter()->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId(), 'studentId' => $participant->getUser()->getId()])])
                    ->add(['Similarity Check', '']),
            ];

            $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/turnitin/iframe.twig', $data));
        }

        /* TODO You might error out here when json_decode returns null
         *  in odd circumstances; but what do we display in that case?
         */
    }

    /**
     * Dump out a plagscan report
     *
     * @param IAssessable $assessment
     * @param IResponse $response
     * @param Plagscan $plagscan
     * @param Participant $participant
     */
    public function getPlagscanReport(IAssessable $assessment, IResponse $response, Plagscan $plagscan, Participant $participant)
    {
        $data = [
            'plagscan' => $plagscan,
            'assessment' => $assessment,
            'response' => $response,
            'participant' => $participant,
            'interactiveAvailable' => false
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/plagscan/iframe.twig', $data));
    }

    /**
     * Loads up the Plagscan report contents - used in conjunction with
     * getPlagscanReport() because this loads plagscan-formatted html
     * which needs to be spawned in an iframe (sigh)
     *
     * @param Plagscan $plagscan
     */
    public function getPlagscanReportContents(Plagscan $plagscan)
    {
        /* Assume all files were migrated successfully so load the html
         * contents from the storage instance
         */
        $data = [
            'html' => $this->filesystemProxy->read($plagscan->getStorage()),
        ];

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningDueWorkResponse/plagscan/contents.twig', $data));
    }
}
