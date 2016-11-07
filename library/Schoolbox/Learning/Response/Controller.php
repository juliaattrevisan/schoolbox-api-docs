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
use Schoolbox\Learning\Assessment\DueWork\Timeline\ResponsesOnly as Timeline;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\User;

use \Schoolbox\Learning\Assessment\DueWork\TemporaryDataDecorator;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as FeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as Plagscan;

use \Schoolbox\Learning\InlineGrader\Facade as InlineGraderFacade;
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
     * @param DueWork $assessment
     * @param User $student
     */
    public function getResponseList(DueWork $assessment, User $student)
    {
        /* TODO: Don't use temporary data
         * Ram in some temporary responses so that I
         * can make the Submission History section
         */
        (new TemporaryDataDecorator())->decorate($assessment);

        $data = [
            'assessment' => $assessment,
            'student' => $student,
            'timeline' => (new Timeline($assessment))->getTimeline()
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
        $file = $response->getAllFiles()->first();

        foreach ($response->getAllFiles() as $currentFile) {
            if ($currentFile->getId() == $this->getRequest()->query['fileId']) {
                $file = $currentFile;
                break;
            }
        }

        /* TODO: Get a list of possible participants and whether they've submitted stuff
         */
        $allPeople = [];

        foreach ($response->getAssessable()->getAllParticipants() as $currentParticipant) {
            $submissionExists = $currentParticipant->findMostRecentResponse();

            $person = [
                'text' => $currentParticipant->getUser()->getFullname(),
                'value' => $this->getRouter()->generate('learningResponse.getResponseList', [ 'assessmentId' => $response->getAssessable()->getId(), 'studentId' => $currentParticipant->getUser()->getId()]),
                'html' => "
                    <div class=\"row\">
                        <div class=\"avatar card small-10\">
                            <img src=\"/portrait.php?userId={$currentParticipant->getUser()->getExternalId()}&size=square64\" alt=\"View Profile\" />
                            <p>{$currentParticipant->getUser()->getFullName()}</p>
                            <span class=\"label status unsubmitted\">Not Submitted</span>
                        </div>
                    </div>
                ",
                'unsubmitted' => $submissionExists ? false : true,
            ];

            $allPeople[] = $person;
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

                'currentPerson' => $this->getRouter()->generate('learningResponse.getResponseList', [ 'assessmentId' => $response->getAssessable()->getId(), 'studentId' => $response->getSubmittedBy()->getId()]),
                'allPeople' => $allPeople,
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
     * @param SubmissionFile $file
     * @param Submission $response
     * @throws \RangeException Cannot authenticate at crocodoc
     * @throws \DomainException Some kind of curl error between us and crocodoc
     */
    public function getCrocodocDownload(Submission $response, SubmissionFile $file)
    {
        if (strlen($file->getCrocodocId())) {
            try {
                $location = (new InlineGraderFacade($response, $file, $this->getUser()))->getDownloadUrl();

                $this->getResponse()->redirect->to($location);
            }
            catch (\RangeException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
            }
            catch (\DomainException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
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
