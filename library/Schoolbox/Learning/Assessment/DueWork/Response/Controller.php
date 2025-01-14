<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress\Exception\UnreachableException;
use Alaress\Web\Controller\Base;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Mapper as FeedbackFileMapper;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Fetcher;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
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
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Turnitin\Exception\Credentials;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Receiver;
use Schoolbox\Url\Uri;
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
     * @param ErrorMapper            $errorMapper
     * @param DataSource             $responseDataSource
     * @param BreadcrumbFactory      $bakery
     * @param Term\Mapper            $termMapper
     * @param Kami\Mapper            $kamiMapper
     * @param Kami\Connector         $kamiConnector
     * @param Access                 $access
     * @param FilesystemProxy        $filesystemProxy
     * @param Turnitin\File\Api|null $turnitinFileApi Null if TII isn't configured
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
        private ?Turnitin\File\Api $turnitinFileApi,
        private Receiver $filesystemReceiver,
        private DueWorkFeedbackMapperProxy $feedbackMapperProxy
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

            // Sort the files in a standard method (filename ASC)
            /** @var Model $response */
            $response->getAllFiles()->sortBy('filename', 'asc');

            // The file that's being reviewed
            $file = $this->getFile($response, $this->requestQuery->get('hash'));

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
                $this->routeGenerator,
                $participant->getUser(),
                $assessment
            )) :
            (new StudentCarousel\Standard(
                $this->routeGenerator,
                $participant->getUser(),
                $assessment
            ));

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) ])
                ->add(['Inline Grader', '']),
            'response' => $response,
            'isMostRecentResponse' => $isMostRecentResponse,
            'feedback' => $feedback,
            'selfAssessmentFeedback' => $selfAssessmentFeedback,
            'attachzone' => $attachZone->getTwigContext(),
            'selectedFileHash' => $this->requestQuery->get('hash'),

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

        $this->response = $this->responseFromTemplate('@learningDueWorkResponse/inlineGrading/standard.twig', $data);
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
            $session = $this->kamiConnector->viewSession($kami->getKamiDocumentId(), $this->getUser(), false, showSave: false, showPrint: false, showHelp: false, showMenu: false);
        }

        $this->response = $this->responseFromTemplate('@learningDueWorkResponse/kamiView.twig', [
            'kami'     => $kami,
            'session'  => $session,
            'response' => $response
        ]);
    }


    /**
     * @param Kami\Model $kami
     * @throws Kami\Exception\EmptyApiKeyException If Kami is not enabled
     * @throws Kami\Exception\NotFound If unable to find the document in Kami
     * @throws Kami\Exception\ResponseDecodeException If cannot decode the Kami Response
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
        $token = $this->requestQuery->get('token');

        // TODO consider adding cookie setting method in response factory
        $fqdn = \trim("{$this->getConfigLoader()->get('site_hostname')}.{$this->getConfigLoader()->get('site_domain')}");

        setcookie('kamiExportToken', $token, 0, '/', $fqdn, true, false);
        setcookie('kamiExportStatus', $export->getStatus(), 0, '/', $fqdn, true, false);

        // Unable to generate the export? (either due to error or timeout)
        if (!$export->isStatusDone()) {
            // Don't change browser viewport
            $this->response = $this->responseEmpty();
            return;
        }

        // This works in all browsers, but means we need to stream the file through schoolbox as a proxy.
        $filename           = $kami->getStudentSubmissionFile()->getBasename();
        $guzzleResponse     = (new Client())->get($export->getFileUrl());
        $this->response = $this->responseFromContent($guzzleResponse->getBody())
                               ->withHeader('Content-Description', 'File Transfer')
                               ->withHeader('Content-Type', 'application/octet-stream')
                               ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"')
                               ->withHeader('Expires', '0')
                               ->withHeader('Cache-Control', 'must-revalidate')
                               ->withHeader('Pragma', 'public')
                               ->withHeader('Content-Length', $guzzleResponse->getHeader('Content-Length')[0])
        ;
    }

    /**
     * Polls a kami export request to see if we can download the file
     *
     * @param Kami\Model $kami
     * @throws Exception
     * @throws Exception/Deleted
     * @throws GuzzleException
     * @api POST /kami/{kamiId:\d+}/poll
     * @since 24.0
     *
     */
    public function postPollKamiDownload(Kami\Model $kami): void
    {
        $responseCode = 400;
        /**
         * If there is no job reference, or if the (previous job has finished) status is done
         * spawn a new export job (so we can capture the latest annotations)
         *
         * else just poll to see if the current job has finished or failed.
         */
        if (!$kami->getJobReference() || Kami\Model::STATUS_DONE === $kami->getStatus()) {
            // spawn new export job on kami's side
            $exportJob = $this->kamiConnector->createAnnotatedExport($kami->getKamiDocumentId());
            $kami->setJobReference($exportJob->getId());
            $kami->setStatus($exportJob->getStatus());
            $this->kamiMapper->save($kami);
            $this->response = $this
                ->responseAsJson(['status' => $exportJob->getStatus()])
                ->withStatus(202); // still processing
            return;
        }
        // else check job status
        $export = $this->kamiConnector->getExport($kami->getJobReference());
        $status = $export->getStatus();
        if ($export->isStatusPending()) {
            $responseCode = 202;
        } elseif ($export->isStatusDone()) {
            $responseCode = 200;
        }
        $kami->setStatus($status);
        $this->kamiMapper->save($kami);

        // respond with correct status code
        // Respond with code:
        // * 200 if complete
        // * 202 if still processing (this tells polling process to try again)
        // * 400 if failed
        $this->response = $this
            ->responseAsJson(['status' => $status])
            ->withStatus($responseCode);
    }

    /**
     * Polls a kami export request to see if we can download and save to the attached student submission.
     * @param Kami\Model $kami
     * @param string $action // enum of what action we are working with, student submit(submission), teacher feedback(feedback), download(download)
     * @param DueWork|null $assessment
     * @param User|null $student
     * @param DueWorkResponse|null $response
     * @param Feedback|null $feedback
     * @throws Exception
     * @throws Exception/Deleted
     * @throws GuzzleException
     * @throws \Schoolbox\File\Exception
     * @api POST /kami/{kamiId:\d+}/{action:\w+}/poll
     * @since 24.0
     *
     */
    public function postKamiDownloadCallback(Kami\Model $kami, string $action, ?DueWork $assessment = null, ?User $student = null, ?DueWorkResponse $response = null, ?Feedback $feedback = null)
    {
        // kami is ready to download
        $export = $this->kamiConnector->getExport($kami->getJobReference());
        $expiryAt = null;
        $uploadingUser = $this->getUser();
        $filename = sprintf("%s.pdf", $kami->getStudentSubmissionFile()?->getFilename() ?? 'Student Submission');
        // if action is download, save to storage for a 2 week auto delete
        if ('download' === $action) {
            $expiryAt = \Alaress_Date_Format::now()->addWeeks(2);
        }
        // else store for all time
        // grab the file and save to storage ensuring it is a pdf, as that is what we get from kami
        if ('feedback' === $action) {
            $filename = sprintf("%s - Feedback.pdf", $kami->getStudentSubmissionFile()?->getFilename());
        }
        $guzzleResponse     = (new Client())->get($export->getFileUrl());
        $storage = $this->filesystemReceiver->acceptStream(
            $filename,
            $guzzleResponse->getBody(),
            expiryAt: $expiryAt,
            uploadingUser: $uploadingUser
        );

        // depending on the action, depends on where we save / send the file
        switch ($action) {
            case 'download':
                // taskd notify the user
                break;
            case 'submission': // student submission (save the current kami to the student submission file)
                if ($response) {
                    // update the worksheet entry to not use the original worksheet template, but the downloaded kami annotated file
                    $response->getAllFiles()->filter(function (SubmissionFile $file) use ($kami) {
                        return $kami->getStudentSubmissionFile()?->getId() === $file->getId();
                    })->first()?->setStorage($storage);
                    $this->responseDataSource->save($response);
                    // re save kami, or that entry is purged...
                    $this->kamiMapper->save($kami);
                }
                break;
            case 'feedback': // teacher feedback published (save the current kami to the newly created teacher feedback file)
                if ($feedback) {
                    // create a feedback file object
                    $feedback->getAllFiles()->add(
                        FeedbackFileMapper::getInstance()->createModel()
                            ->setStorage($storage)
                            ->setFilename($filename)
                            ->setIsWorksheet(true)
                    );
                    // save the things
                    $this->feedbackMapperProxy->save($feedback);
                }
                break;
        }
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
        // Files have been sorted, just get the first one
        return $response->getAllFiles()->first();
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
                $this->termMapper,
                $this->filesystemProxy,
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
     * @api   DELETE /assessments/:assessmentId/response/:responseId
     * @since 165
     * @param IAssessable $assessment
     * @param Model $response
     */
    public function deleteParticularResponse(IAssessable $assessment, Model $response)
    {
        try {
            $this->responseDataSource->delete($response);
            $this->response = $this->responseEmpty();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->response = $this->responseFromContent($e->getMessage())->withStatus(400);
        }
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
     * @throws DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getTurnitinReport(IAssessable $assessment, IResponse $response, Turnitin\File\Model $turnitin, Participant $participant): void
    {
        $viewerUrl = $this->determineTurnitinViewerUrl($assessment, $turnitin);
        if (null !== $viewerUrl) {
            $data = [
                'url' => $viewerUrl,
                'turnitin' => $turnitin,
                'assessment' => $assessment,
                'response' => $response,
                'participant' => $participant,
                'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                    ->add([$assessment->getTitle(), $this->routeGenerator->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId(), 'studentId' => $participant->getUser()->getId()])])
                    ->add(['Similarity Check', '']),
            ];

            $this->response = $this->responseFromTemplate('@learningDueWorkResponse/turnitin/iframe.twig', $data);
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

        $this->response = $this->responseFromTemplate('@learningDueWorkResponse/plagscan/iframe.twig', $data);
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

        $this->response = $this->responseFromTemplateUnthemed('@learningDueWorkResponse/plagscan/contents.twig', $data);
    }

    /**
     * Fetches and displays a single response file, if it is an editable-inline
     * HTML file.
     *
     * (Permission checks have already been done on the participant and response
     *  preloaded from the path.)
     *
     * @api   GET /learning/assessments/:assessmentId/:studentId/responses/:responseId/files/:fileId
     * @since 23.0.0
     *
     * @param SubmissionFile $file
     * @return void
     */
    public function getResponseFileInline(SubmissionFile $file): void
    {
        // Disallow BTTF for this place:
        // otherwise, it's possible to BTTF back to a download or iframe view
        $this->setAllowBackToTheFuture(false);

        // Is this file not editable inline? Just show in storage
        if (!$file->getEditableInline()) {
            $this->response = $this->redirectPermanent(
                (new Uri('/storage/fetch.php'))
                    ->appendQueryParameters($this->requestQuery->getArrayCopy())
                    ->__toString()
            );
        }

        // Otherwise: render it in Twig, and output as an attachment if either:
        // * the HTML mimetype is openable inline
        // * the `save` query string param exists
        $this->response = $this->responseFromTemplateUnthemed(
            '@learningDueWorkResponse/responseFileInline.twig',
            [
                'file'        => $file,
                'fileContent' => $this->filesystemProxy->read($file),
            ],
        );
        if ($this->requestQuery->get('save')) {
            $this->response = $this->response->withHeader(
                'Content-Disposition',
                sprintf('attachment; filename="%s"', $file->getBasename()),
            );
        }
    }


    /**
     * Downloads a particular response file belonging to a submission
     * (This is an equivalent of fetch.php with access permissions)
     *
     * @param SubmissionFile $file
     * @return void
     * @throws Exception
     * @throws UnreachableException
     * @throws FilesystemException
     * @throws UnableToReadFile
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getResponseFileDownload(SubmissionFile $file): void
    {
        /* Otherwise you supplied all the pieces
         * and you pass the perms checks
         *
         * We didn't code to interfaces, so fetch this in the data type required
         * for minimal change
         */
        $resource = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($file->getStorage()->getHash());

        /* TODO Arguably this should be in a storage controller in a storage route
         */
        (new Fetcher($this->getConfigLoader(), $this->getDi()->get('filesystem.storageProxy')))
            ->fetch($file->getBasename(), $resource, (bool)$this->requestQuery->get('cache'), $this->requestQuery['save'] ?? false);
        exit;
    }


    /**
     * Determine the URL via which the current user may view a TII report.
     *
     * @param IAssessable         $assessment The assessment in which the TII
     *                                        report was created.
     * @param Turnitin\File\Model $turnitin   The TII report model.
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function determineTurnitinViewerUrl(
        IAssessable $assessment,
        Turnitin\File\Model $turnitin,
    ): ?string {
        if (!$this->turnitinFileApi) {
            throw new DomainException('Turnitin configuration invalid.');
        }
        $isInstructor = $this->access->isInstructor($this->getUser(), $assessment->getContext()->getFolder());
        return $this->turnitinFileApi->generateViewerUrl(
            $turnitin,
            $this->getUser(),
            $isInstructor
                ? Turnitin\File\Process\Report::VIEWER_PERMISSION_SET_STAFF
                : Turnitin\File\Process\Report::VIEWER_PERMISSION_SET_STUDENT,
            $isInstructor,
        );
    }
}
