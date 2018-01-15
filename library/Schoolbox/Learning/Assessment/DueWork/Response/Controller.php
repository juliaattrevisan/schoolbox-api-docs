<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Response\DataSource;
use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\Timeline\ResponsesOnly;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Permission\Access\Access;
use \Schoolbox\Learning\Assessment\Attachzone;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\InlineGrader\Factory;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use DueWork_ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Assessment\Term;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Learning\InlineGrader\Kami;
use GuzzleHttp\Client;

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
     * @param DueWork     $assessment
     * @param Participant $participant
     * @param Model       $response
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
        Participant $participant,
        Model $response
    ): void
    {

        /* Self-Assessment/Self Reflection: the response must be provided from the url param
         * Assume this is the one to load. To continue with his part of self-assessment workflow check the
         * participation setting. Learners are allowed in this section with a non-submitted Response under
         * these circumstances
         */
        $selfAssessedWithResponse = (new ValidSelfAssessmentWithResponse())->isSatisfiedBy($this->getUser(), $participant);
        $selfReflectedWithResponse = (new ValidSelfReflectionWithResponse())->isSatisfiedBy($this->getUser(), $participant);

        if (!$selfAssessedWithResponse && !$selfReflectedWithResponse) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_COMPLETE_BEFORE_SELF_REFLECT);
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
        $latestFeedback    = $participant->findMostRecentFeedback();
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
            'latestFeedback' => $latestFeedback,
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
        ];

        /* Rubrics data
        */
        $rubricMapper = RubricMapper::getInstance();
        if($assessment->getUseRubric()){
            $assessment->setRubric($rubricMapper->findByDueWork($assessment));
        }
        $rubricContext = new RubricContext($rubricMapper);

        // Should rubric be readonly or markable?
        $data['rubricContext'] = $isInteractionLocked && !$this->getUser()->isSuperuser()
            ? $rubricContext->generate($assessment, $participant->getUser(), $this->getUser(), Access::getInstance(), false)
            : $rubricContext->generateMarkable($assessment, $participant->getUser());

        /* Figure out who's actually here and what screen to show
         */
        if ($selfAssessedWithResponse) {
            $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/inlineGrading/selfAssessment.twig', $data));
        }
        elseif ($selfReflectedWithResponse) {
            $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/inlineGrading/selfReflection.twig', $data));
        }
    }

    /**
     * Get the contents of a particular $response
     *
     * Show the specified kami doc in an inline window if available;
     * otherwise display html/audio/video/image contents differently, inline with the dom
     *
     * I can't call it getResponse() lol wups
     *
     * @param DueWork     $assessment
     * @param Participant $participant
     * @param Model       $response
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
        Participant $participant,
        Model $response = null
    ): void {

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
        }

        $file = $response ? $this->getFile($response, $this->getRequest()->query['hash']) : null;

        // Work with the correct feedback for this response.
        // * Most recent feedback for the response
        // * Most recent for the participant
        // * Nothing
        $latestFeedback = null;
        if ($response) {
            $latestFeedback = $response->findMostRecentFeedback();
        }
        $latestFeedback = $latestFeedback ?? $participant->findMostRecentFeedback();

        // The zone which contains the files
        $attachZoneFactory = new Attachzone\Factory();
        $attachZone        = $attachZoneFactory->buildForDueWorkFeedback($latestFeedback);

        // Is the page being displayed in readonly mode?
        $isInteractionLocked = (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment);

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), $this->getRouter()->generate('learningAssessment.getStatus',['assessmentId' => $assessment->getId()]) ])
                ->add(['Inline Grader', '']),
            'response' => $response,
            'isMostRecentResponse' => $isMostRecentResponse,
            'latestFeedback' => $latestFeedback,
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
            'errors' => DueWork_ErrorMapper::getInstance()->listDisplayValues(),
            'participant' => $participant,

            /* Plagscan status
             */
            'plagscanEnabled' => PlagscanMapper::getInstance()->isEnabled($this->getUser()),
            'interactionLocked' => $isInteractionLocked,

            /* Self-Reflectable Rubrics
             */
            'learnerRubricContext' => null
        ];

        /* Rubrics data
        */
        $rubricMapper = RubricMapper::getInstance();

        if($assessment->getUseRubric()){
            $assessment->setRubric($rubricMapper->findByDueWork($assessment));
        }

        $rubricContext = new RubricContext($rubricMapper);

        // Should rubric be readonly or markable?
        if ($isInteractionLocked && !$this->getUser()->isSuperuser()) {
            $data['rubricContext'] = $rubricContext->generate($assessment, $participant->getUser(), $this->getUser(), Access::getInstance(), false);
        }
        elseif ($response) {
            $data['rubricContext'] = $rubricContext->generateMarkableForResponse($assessment, $participant->getUser(), $response->getId());
        }
        else {
            $data['rubricContext'] = $rubricContext->generateMarkable($assessment, $participant->getUser());
        }

        /* If this contained a self-reflectable response, load that as well in addition to the usual rubricContext
         * The Instructor needs to see what the Learner chose simultaneously with what the Instructor will give
         *
         * Currently have to trickt he validation class a little for this context; we only care if the assessment
         * -was- validly set for the participant, not for the Instructor currently logged in
         *
         * If it was self-reflectable and used a rubric, find out what the latest Learner-owned Feedback is for the
         * IResponse that we're looking at
         */
        $selfReflected = (new ValidSelfReflection())->isSatisfiedBy($participant->getUser(), $participant);

        if ($selfReflected && $assessment->getUseRubric()) {
            if ($isInteractionLocked && !$this->getUser()->isSuperuser()) {
                $data['learnerRubricContext'] = $rubricContext->generate($assessment, $participant->getUser(), $this->getUser(), Access::getInstance(), false);
            }
            elseif ($response) {
                $data['learnerRubricContext'] = $rubricContext->generateMarkableForResponse($assessment, $participant->getUser(), $response->getId());
            }
            else {
                $data['learnerRubricContext'] = $rubricContext->generateMarkable($assessment, $participant->getUser());
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/inlineGrading/standard.twig', $data));
    }


    /**
     * Display a kami document in an iFrame
     *
     * @api   /learning/kami/{kamiId}
     * @level 17.5
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
        }
        catch (Kami\Exception\IKamiException $e) {
            // Kami Exceptions
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
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
     * @level 165
     * @param Participant $participant
     * @param Model $response
     */
    public function deleteParticularResponse(Participant $participant, Model $response)
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
