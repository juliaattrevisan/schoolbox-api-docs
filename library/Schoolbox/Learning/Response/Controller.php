<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Response;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Timeline\ResponsesOnly as Timeline;
use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\User;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as Plagscan;

use \Schoolbox\Learning\InlineGrader\Facade as InlineGraderFacade;
use \Schoolbox\Learning\InlineGrader\Authenticator as InlineGraderAuthenticator;
use \Schoolbox\Learning\InlineGrader\Factory\ResponseFile as ResponseFileFactory;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->setErrorMapper($errorMapper);
    }

    /**
     * Get a list of Responses by $student for a particular
     * IAssessable $assessment
     *
     * @param Participant $participant
     */
    public function getResponseList(Participant $participant)
    {
        $data = [
            'participant' => $participant,
            'carousel' => (new StudentCarousel($this->getRouter(), $participant->getUser(), $participant->getAssessable(), null))->produce(),
            'timeline' => (new Timeline(new Participant($student, $assessment)))->getTimeline(),
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningResponse/responseList.twig', $data));
    }

    /**
     * Get the contents of a particular $response
     *
     * Show the specified $_GET['fileId'] in iframe window if it's a crocodoc-compatible
     * file; otherwise display html/audio/video/image contents differently, inline with
     * the dom
     *
     * I can't call it getResponse() lol wups
     *
     * @param Submission $response
     * @throws \RangeException Cannot authenticate at crocodoc
     * @throws \DomainException Some kind of curl error between us and crocodoc
     */
    public function getParticularResponse(Submission $response)
    {
        /* Factory to figure out which fragment is required
         */
        $file = null;

        if ($response->getAllFiles()->count()) {
            try {
                $file = $response->findFileById($this->getRequest()->query['fileId']);
            }
            catch (\OutOfBoundsException $e) {
                $file = $response->getAllFiles()->first();
            }
        }

        /* ResponseFile Factory might throw exceptions if you're viewing
         * crocodocable files that somehow die
         */
        try {
            $data = [
                'assessment' => $response->getAssessable(),
                'response' => $response,
                'attachzone' => (new Zone('feedbackFiles'))->getTwigContext(),
                'selectedFileId' => $this->getRequest()->query['fileId'],
                'file' => $file,
                'subfragment' => (new ResponseFileFactory($response, $file, $this->getUser()))->produce(),
                'carousel' => (new StudentCarousel($this->getRouter(), $response->getSubmittedOnBehalfOf(), $response->getAssessable(), $response))->produce(),
                'submissionStates' => \DueWork_ErrorMapper::getInstance()->findAll(),

                /* Rubrics data
                 */
                'rubricContext' => (new RubricContext())->generateMarkable($response->getAssessable(), $response->getSubmittedOnBehalfOf()),
            ];

            $this->getResponse()->content->set($this->buildHtmlOutput('@learningResponse/response.twig', $data));
        }
        catch (\RangeException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }
        catch (\DomainException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }
    }

    /**
     * Download a particular annotated crocodoc as a pdf
     *
     * @param Submission $response
     * @param SubmissionFile $file
     * @param Feedback $feedback
     * @throws \RangeException Cannot authenticate at crocodoc
     * @throws \DomainException Some kind of curl error between us and crocodoc
     */
    public function getCrocodocDownload(Submission $response, Feedback $feedback, SubmissionFile $file)
    {
        /* If you are a student and the feedback is published
         * and the requested file has a crocodocked url,
         * attempt to download it
         *
         * Otherwise 404
         */
        if ($this->getUser()->getRole()->isStaff() || ($this->getUser()->getRole()->isStudent() && $feedback->isPublished())) {
            if (strlen($file->getCrocodocId())) {
                try {
                    $location = (
                    new InlineGraderFacade(
                        new InlineGraderAuthenticator(),
                        $response,
                        $file,
                        $this->getUser()
                    )
                    )->getDownloadUrl();

                    $this->getResponse()->redirect->to($location);
                } catch (\RangeException $e) {
                    throw $this->getErrorMapper()->generateException(
                        ErrorMapper::ERROR_PROCESSING,
                        null,
                        $e->getMessage()
                    );
                } catch (\DomainException $e) {
                    throw $this->getErrorMapper()->generateException(
                        ErrorMapper::ERROR_PROCESSING,
                        null,
                        $e->getMessage()
                    );
                }
            } else {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        }
        else {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }

    /**
     * Dump out a plagscan report
     *
     * @param Plagscan $plagscan
     */
    public function getPlagscanReport(Plagscan $plagscan)
    {
        /* Make an absolute url
         *
         * http or https, and top-level host where we are installed
         */
        $requestScheme = $this->getRequest()->server->get('REQUEST_SCHEME');
        $host = $this->getRequest()->server->get('HTTP_HOST');

        /* Twiggle it
         */
        $data = [
            'url' => $plagscan->getUrl($requestScheme, $host)
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningResponse/plagscanReport.twig', $data));
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
}
