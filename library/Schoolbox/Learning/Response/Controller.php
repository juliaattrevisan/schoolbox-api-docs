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
use Schoolbox\User;

use \Schoolbox\Learning\Assessment\DueWork\TemporaryDataDecorator;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as FeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as Plagscan;

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
     * @param IAssessable $assessment
     * @param User $student
     */
    public function getResponseList(IAssessable $assessment, User $student)
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
     * Show the specified $_GET['fileId'] in iframe window
     * or other preview mechanism
     *
     * I can't call it getResponse() lol wups
     *
     * @param IResponse $response
     */
    public function getParticularResponse($response)
    {
        /* TODO: Generate a populated response for now
         */
        $response = $this->generateResponse1();

        /* Factory to figure out which fragment is required
         */
        $file = $response->getAllFiles()->getOffset(4);

        $factory = new \Schoolbox\Learning\InlineGrader\Factory\ResponseFile($file);
        $factory->produce();

        $data = [
            'assessment' => $assessment,
            'response' => $response,
            'selectedFileId' => $this->getRequest()->query['fileId'],
            'file' => $file,
            'strategy' => $factory->produce()
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningResponse/response.twig', $data));
    }

    /**
     * Dump out a plagscan report
     *
     * @param Plagscan $plagscan
     */
    public function getPlagscanReport(Plagscan $plagscan)
    {
        $url = sprintf(
            "%s://%s/submission/plagiarismReport/%s/#zx_h=672px",
            $this->getRequest()->server->get('REQUEST_SCHEME'),
            $this->getRequest()->server->get('HTTP_HOST'),
            $plagscan->getPlagscanId()
        );

        /* Twiggle it
         */
        $data = [
            'url' => $url
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningResponse/plagscanReport.twig', $data));
    }

    /**
     * Temporary populator
     *
     * @return Submission
     */
    private function generateResponse1()
    {
        $hodes = \Schoolbox\UserMapper::getInstance()->findById(230);

        /* First file
         */
        $plagscan1 = new Plagscan();
        $plagscan1
            ->setId(1)
            ->setPlagscanId(4396294)
            ->setStatus(3)
            ->setScore('45%')
            ->setHtml('<div><strong>YOU CHEATED</strong></div>');

        $storage1 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('d3f0f82be30a7da942fa00792d221a7eddff43bd');

        $submissionFile1 = new SubmissionFile();
        $submissionFile1
            ->setId(5233)
            ->setStorage($storage1)
            ->setCrocodocId('slkjl2j3589uslk23jl4')
            ->setFilename('online-submission.html')
            ->setPlagscan($plagscan1);

        /* Second file
         */
        $plagscan2 = new Plagscan();
        $plagscan2
            ->setId(2)
            ->setPlagscanId(4396431)
            ->setStatus(-1);

        $storage2 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('8b6b83f10b12a9f16f9c6ce0581c992fffe11ed6');

        $submissionFile2 = new SubmissionFile();
        $submissionFile2
            ->setId(13345)
            ->setStorage($storage2)
            ->setCrocodocId('dfue45636')
            ->setFilename('some-unplaggable-file.pdf')
            ->setPlagscan($plagscan2);

        /* Third file
         */
        $plagscan3 = new Plagscan();
        $plagscan3
            ->setId(3)
            ->setPlagscanId(4396443)
            ->setStatus(1);

        $storage3 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('efb4b418c6c963dc8760e9676986a4e99706f636');

        $submissionFile3 = new SubmissionFile();
        $submissionFile3
            ->setId(13345)
            ->setStorage($storage3)
            ->setCrocodocId('36dg36')
            ->setFilename('thingy.jpg')
            ->setPlagscan($plagscan3);

        /* Fourth file
         */
        $plagscan4 = new Plagscan();
        $plagscan4
            ->setId(4)
            ->setPlagscanId(4396470)
            ->setStatus(2);

        $storage4 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('a351bf467f221eead2d0a184db35d13cc86a43e3');

        $submissionFile4 = new SubmissionFile();
        $submissionFile4
            ->setId(64267)
            ->setStorage($storage4)
            ->setCrocodocId('3d33lkjl2590')
            ->setFilename('chemistry.pptx')
            ->setPlagscan($plagscan4);

        /* Fifth file
         */
        $plagscan5 = new Plagscan();
        $plagscan5
            ->setId(5)
            ->setPlagscanId(4396541)
            ->setScore('67%')
            ->setStatus(3);

        $storage5 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('724f3f7e395e639bfa2e5cb58589f8dcf88b4be9');

        $submissionFile5 = new SubmissionFile();
        $submissionFile5
            ->setId(3019384)
            ->setStorage($storage5)
            ->setCrocodocId('3d33lkjl2590')
            ->setFilename('chemistry.pptx')
            ->setPlagscan($plagscan5);

        /* Accumulate
         */
        $response = new Submission();
        $response
            ->setId(2)
            ->setSubmittedBy($hodes)
            ->setSubmittedOnBehalfOf($hodes)
            ->setAllFiles((new \Schoolbox\Collection())
                ->add($submissionFile1)
                ->add($submissionFile2)
                ->add($submissionFile3)
                ->add($submissionFile4)
                ->add($submissionFile5)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        return $response;
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
