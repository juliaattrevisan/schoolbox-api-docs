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
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
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
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\InlineGrader\Facade as InlineGraderFacade;
use \Schoolbox\Learning\InlineGrader\Authenticator as InlineGraderAuthenticator;
use \Schoolbox\Learning\InlineGrader\Factory\ResponseFile as ResponseFileFactory;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use DueWork_ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Assessment\Term;
use Schoolbox\Application\Exception as ApplicationException;

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


    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param DataSource  $responseDataSource
     * @param BreadcrumbFactory $bakery
     * @param Term\Mapper $termMapper
     */
    public function __construct(ErrorMapper $errorMapper, DataSource $responseDataSource, BreadcrumbFactory $bakery, Term\Mapper $termMapper)
    {
        $this->setErrorMapper($errorMapper);
        $this->responseDataSource = $responseDataSource;
        $this->bakery = $bakery;
        $this->termMapper = $termMapper;

    }

    /**
     * Get the contents of a particular $response
     *
     * Show the specified $_GET['hash'] in iframe window if it's a crocodoc-compatible
     * file; otherwise display html/audio/video/image contents differently, inline with
     * the dom
     *
     * I can't call it getResponse() lol wups
     *
     * @param DueWork $assessment
     * @param Participant $participant
     * @param Submission $response
     * @throws \RangeException Cannot authenticate at crocodoc
     * @throws \DomainException Some kind of curl error between us and crocodoc
     * @throws \InvalidArgumentException If assessment due date is null.
     */
    public function getParticularResponse(DueWork $assessment, Participant $participant, Submission $response = null): void
    {
        /* Module doesn't have access to $this->getUser()
         *
         * Superusers can see draft Responses in the inline grader
         * Teachers cannot see draft Responses in the inline grader
         */
        if (!$response) {
            $response = $participant->findMostRecentSubmittedResponse();
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

        /* ResponseFileFactory figures out which fragment is required
         */
        $file = null;

        if ($response && $response->getAllFiles()->count()) {

            /* Sort files alphabetically
             */
            $response->getAllFiles()->sortBy('filename', 'asc');

            try {
                $file = $response->findFileByHash($this->getRequest()->query['hash']);
            }
            catch (\OutOfBoundsException $e) {
                $file = $response->getAllFiles()->first();
            }
        }

        // The zone which contains the files
        /** @var IFeedback $latestFeedback */
        $latestFeedback    = $participant->findMostRecentFeedback();
        $attachZoneFactory = new Attachzone\Factory();
        $attachZone        = $attachZoneFactory->buildForDueWorkFeedback($latestFeedback);

        // Is the page being displayed in readonly mode?
        $isInteractionLocked = (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment);

        /* ResponseFile Factory might throw exceptions if you're viewing
         * crocodocable files that somehow die
         */
        try {

            $data = [
                'assessment' => $assessment,
                'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                    ->add([ $assessment->getTitle(), $this->getRouter()->generate('learningAssessment.getStatus',['assessmentId' => $assessment->getId()]) ])
                    ->add(['Inline Grader', '']),
                'response' => $response,
                'latestFeedback' => $latestFeedback,
                'attachzone' => $attachZone->getTwigContext(),
                'selectedFileHash' => $this->getRequest()->query['hash'],
                'markSchemeName' => $this->deriveSchemeName($assessment->getMarkScheme()),
                'carousel' => (new StudentCarousel($this->getRouter(), $participant->getUser(), $assessment))->produce(),
                'submissionStates' => \DueWork_ErrorMapper::getInstance()->findAll(),

                /* A list of all the Responses that the participant has spawned
                 */
                'responseList' => (new ResponsesOnly($participant))->getForTeacher(),

                /* This is now conditional depending on the presence of a $response
                 */
                'file' => $file,
                'subfragment' => $file ? (new ResponseFileFactory($response, $file, $this->getUser()))->produce() : null,

                /* For saving feedbacks
                 */
                'errors' => DueWork_ErrorMapper::getInstance()->listDisplayValues(),
                'participant' => $participant,

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

            $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/response.twig', $data));
        }
        catch (\RangeException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }
        catch (\DomainException $e) {
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
     * @param Submission $response
     */
    public function deleteParticularResponse(Participant $participant, Submission $response)
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
     * Download a particular annotated crocodoc as a pdf
     *
     * @param Submission     $response
     * @param SubmissionFile $file
     * @throws ApplicationException\Exception
     */
    public function getCrocodocDownload(Submission $response, SubmissionFile $file)
    {
        try {
            $download = $this->getInlineGraderFacade($response, $file)->getDownloadUrl();
        }
        catch (\InvalidArgumentException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
        catch (\RangeException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }
        catch (\DomainException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }

        $this->getResponse()->redirect->to($download);
    }

    /**
     * View a particular annotated crocodoc
     *
     * @param Submission $response
     * @param Feedback $feedback
     * @param SubmissionFile $file
     * @throws ServiceNotFound If unable to locate Web Renderer in DI
     * @throws ApplicationException\Exception
     */
    public function getCrocodocView(Submission $response, Feedback $feedback, SubmissionFile $file)
    {
        try {
            $facade = $this->getInlineGraderFacade($response, $file);
        }
        catch (\InvalidArgumentException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
        catch (\RangeException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }
        catch (\DomainException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }

        $data = [
            'viewUrl'     => $facade->getViewUrl(),
            'downloadUrl' => $facade->getDownloadUrl(),
            'response'    => $response,
            'feedback'    => $feedback,
            'file'        => $file,
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningDueWorkResponse/crocodocView.twig', $data));
    }

    /**
     * Figure out a Response File's crocodoc url
     * This is a link to the third-party Crocodoc system
     *
     * @param Submission     $response
     * @param SubmissionFile $file
     * @return InlineGraderFacade
     * @throws \RangeException Cannot authenticate at crocodoc
     * @throws \DomainException Some kind of curl error between us and crocodoc
     * @throws \InvalidArgumentException If no crocodoc ID
     */
    private function getInlineGraderFacade(Submission $response, SubmissionFile $file)
    {
        if (!$file->getCrocodocId()) {
            throw new \InvalidArgumentException('There is no Inline Grader annotated version of this document.');
        }

        return new InlineGraderFacade(new InlineGraderAuthenticator(), $response, $file, $this->getUser());
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
        $data = [
            'html' => $plagscan->getHtml(),
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
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
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
