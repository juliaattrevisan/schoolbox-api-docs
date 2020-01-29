<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Learning\DueWork\StateMapper;
use GuzzleHttp\Client;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Attachzone;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
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
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param DataSource  $responseDataSource
     * @param BreadcrumbFactory $bakery
     * @param Term\Mapper $termMapper
     * @param Kami\Mapper $kamiMapper
     * @param Kami\Connector $kamiConnector
     */
    public function __construct(
        ErrorMapper $errorMapper,
        DataSource $responseDataSource,
        BreadcrumbFactory $bakery,
        Term\Mapper $termMapper,
        Kami\Mapper $kamiMapper,
        Kami\Connector $kamiConnector
    ) {
        $this->errorMapper        = $errorMapper;
        $this->responseDataSource = $responseDataSource;
        $this->bakery             = $bakery;
        $this->termMapper         = $termMapper;
        $this->kamiMapper         = $kamiMapper;
        $this->kamiConnector      = $kamiConnector;
    }

    /**
     * Get the contents of a particular $response for the purposes of Self Reflection
     *
     * @api   GET /learning/assessments/:assessmentId/:studentId/responses/self/:responseId
     * @since 18.0
     *
     * @param DueWork $assessment
     * @param User    $student
     * @param Model   $response
     * @throws ApplicationException\Exception When trying to access a draft Response when you shouldn't
     * @throws ServiceNotFound If unable to locate HTML web Output
     * @throws \Aura\Router\Exception\RouteNotFound If cannot generate a requested route
     * @throws \InvalidArgumentException If assessment due date is null.
     * @throws \PDOException If something went wrong when saving the kami document's status
     * @throws \LogicException If attempting to update the status of a kami doc that is not processing.
     * @throws \UnexpectedValueException If unexpected status change is reported from Kami API
     */
    public function getParticularResponseForSelfReflection(
        DueWork $assessment,
        User $student,
        Model $response
    ): void
    {
        $participant = $assessment->getParticipant($student);

        /* Self-Assessment/Self Reflection: the response must be provided from the url param
         * Assume this is the one to load. To continue with his part of self-assessment workflow check the
         * participation setting. Learners are allowed in this section with a non-submitted Response under
         * these circumstances
         */
        $selfAssessedWithResponse = (new ValidSelfGradableWithResponse())->isSatisfiedBy($this->getUser(), $participant);
        $selfReflectedWithResponse = (new ValidSelfReflectionWithResponse())->isSatisfiedBy($this->getUser(), $participant);

        if (!$selfAssessedWithResponse && !$selfReflectedWithResponse) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_COMPLETE_BEFORE_SELF_REFLECT);
        }

        $reviewRoute = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        if ($selfReflectedWithResponse) {
            $reviewRoute = ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION;
        } elseif ($selfAssessedWithResponse) {
            $reviewRoute = ReturnedWork::REVIEW_ROUTE_SELF_GRADING;
        }

        /* For the last bread node
         */
        $breadcrumbNode = 'Self Assessment/Reflection';

        if ($selfAssessedWithResponse) {
            $breadcrumbNode = $this->getDi()->get('sitePage')->getLocalisedTerm('selfAssessment', false, true);
        }
        elseif ($selfReflectedWithResponse) {
            $breadcrumbNode = $this->getDi()->get('sitePage')->getLocalisedTerm('selfReflection', false, true);
        }

        // The zone which contains the files
        $latestFeedback    = $response->findMostRecentSelfAssessedFeedback();
        $attachZoneFactory = new Attachzone\Factory();
        $attachZone        = $attachZoneFactory->buildForDueWorkFeedback($latestFeedback);

        // Is the page being displayed in readonly mode?
        $isInteractionLocked = (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment);

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                ->add([$assessment->getTitle(), $this->getRouter()->generate('learningAssessment.getSummary',['assessmentId' => $assessment->getId(), 'studentId' => $participant->getUser()->getId()])])
                ->add([$breadcrumbNode, '']),
            'response' => $response,
            'feedback' => $latestFeedback,
            'attachzone' => $attachZone->getTwigContext(),
            'markSchemeName' => $this->deriveSchemeName($assessment->getMarkScheme()),

            /* For saving feedbacks
             */
            'participant' => $participant,

            /* I need this, otherwise Combobox dies catastrophically
             */
            'errors' => [],

            /* Plagscan status
             */
            'plagscanEnabled' => PlagscanMapper::getInstance()->isEnabled($this->getUser()),
            'interactionLocked' => $isInteractionLocked,
            'reviewRoute'       => $reviewRoute,
            'studentView'       => true,
        ];

        /* Rubrics data
        */
        $rubricMapper = RubricDataSource::getInstance();
        if ($assessment->getUseRubric()) {
            $assessment->setRubricGrid($rubricMapper->retrieveGrid($assessment, false));
            if ($latestFeedback instanceof Feedback) {
                $latestFeedback->setRubricMarkedGrid($rubricMapper->retrieveMarkedGrid($latestFeedback));
            }
        }

        $data['selfReflect'] = false;
        $data['selfAssess'] = false;

        /* Figure out who's actually here and what screen to show
         */
        if ($selfAssessedWithResponse) {
            $data['selfAssess'] = true;
            $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/inlineGrading/selfAssessment.twig', $data));
        }
        elseif ($selfReflectedWithResponse) {
            $data['selfReflect'] = true;
            $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/inlineGrading/selfReflection.twig', $data));
        }
    }

    /**
     * Get the contents of a particular $response for instructor marking.
     *
     * Show the specified kami doc in an inline window if available;
     * otherwise display html/audio/video/image contents differently, inline with the dom
     *
     * I can't call it getResponse() lol wups
     *
     * @param DueWork $assessment
     * @param User    $student
     * @param Model   $response
     * @throws ApplicationException\Exception When trying to access a draft Response when you shouldn't
     * @throws ServiceNotFound If unable to locate HTML web Output
     * @throws \Aura\Router\Exception\RouteNotFound If cannot generate a requested route
     * @throws \InvalidArgumentException If assessment due date is null.
     * @throws \PDOException If something went wrong when saving the kami document's status
     * @throws \LogicException If attempting to update the status of a kami doc that is not processing.
     * @throws \UnexpectedValueException If unexpected status change is reported from Kami API
     */
    public function getParticularResponse(
        DueWork $assessment,
        User $student,
        Model $response = null
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
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            // The file that's being reviewed
            $file = $response ? $this->getFile($response, $this->getRequest()->query['hash']) : null;

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

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), $this->getRouter()->generate('learningAssessment.getStatus',['assessmentId' => $assessment->getId()]) ])
                ->add(['Inline Grader', '']),
            'response' => $response,
            'isMostRecentResponse' => $isMostRecentResponse,
            'feedback' => $feedback,
            'selfAssessmentFeedback' => $selfAssessmentFeedback,
            'attachzone' => $attachZone->getTwigContext(),
            'selectedFileHash' => $this->getRequest()->query['hash'],
            'markSchemeName' => $this->deriveSchemeName($assessment->getMarkScheme()),
            'carousel' => (new StudentCarousel($this->getRouter(), $participant->getUser(), $assessment))->produce(),

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

            /* Plagscan status
             */
            'plagscanEnabled' => PlagscanMapper::getInstance()->isEnabled($this->getUser()),
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
     * @throws ServiceNotFound
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
            if($pollCount >= $maxPolls){
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
     * @throws \InvalidArgumentException If cannot convert Kami request or response to a string
     * @throws \LogicException If attempting to update the status of a kami doc that is not processing.
     * @throws \PDOException If something went wrong when saving the kami document's status
     * @throws \UnexpectedValueException If unexpected status change is reported from Kami API
     */
    private function getSubFragment(SubmissionFile $file): array
    {
        try {
            return (new Factory\ResponseFile(
                $this->kamiMapper,
                $this->kamiConnector
            ))->produce($file, $this->getUser());
        } catch (Kami\Exception\IKamiException $e) {
            $this->captureException($e);
            // Kami Exceptions
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
        return substr($c, strrpos($c, '\\')+1);
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
            $this->getResponseDataSource()->delete($response);

        } catch (\Schoolbox\Mapper\Exception $e) {
            $code = 400;
            $data = $e->getMessage();
        }

        $this->getResponse()->status->set($code);
        $this->getResponse()->content->set($this->buildContent('', $data));
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
        $interactiveAvailable = false;

        /* If there is a known interactive report url, try to
         * use it - otherwise use the old crappy version
         *
         * Find out if plagscan is up
         */
        try {
            $mapper = PlagscanMapper::getInstance();
            $interactiveUrl = $mapper->getConnector()->fetchInteractiveReportUrl($plagscan->getPlagscanId());
            if (strlen($interactiveUrl)) {
                $plagscan->setInteractiveUrl($interactiveUrl);
                $interactiveAvailable = true;
            }
        } catch (\LogicException $e) {
            //Api failed to return a valid response - Fall back to old report
        } catch (\RuntimeException $e) {
            //could not connect - Fall back to old report
        }

        $data = [
            'plagscan' => $plagscan,
            'assessment' => $assessment,
            'response' => $response,
            'participant' => $participant,
            'interactiveAvailable' => $interactiveAvailable
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
            'html' => $plagscan->getStorage()->read(),
        ];

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learningDueWorkResponse/plagscan/contents.twig', $data));
    }

    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @param DataSource $responseDataSource
     * @return static
     */
    public function setResponseDataSource($responseDataSource)
    {
        $this->responseDataSource = $responseDataSource;
        return $this;
    }


    /**
     * @return DataSource
     */
    protected function getResponseDataSource()
    {
        return $this->responseDataSource;
    }

    /**
     * @return BreadcrumbFactory
     */
    protected function getBakery()
    {
        return $this->bakery;
    }
}
