<?php
/**
 * TemporaryResponse
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Response;

use Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as FeedbackFile;
use Schoolbox\Learning\Assessment\IAssessable;

class TemporaryResponse
{

    /**
     * Spawn a fully-populated response with corresponding feedbacks
     * Originally used to simulate submissions in inline grader
     *
     * Augment $assessment with the responses and feedbacks
     *
     * @param IAssessable $assessment
     * @return Submission
     */
    public function produceResponse(IAssessable $assessment)
    {
        $hodes = \Schoolbox\UserMapper::getInstance()->findById(230);
        $celia = \Schoolbox\UserMapper::getInstance()->findById(3);

        /* First file
         * Online Submission
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
            ->setId(523300000001)
            ->setStorage($storage1)
            ->setFilename($storage1->getFilename())
            ->setPlagscan($plagscan1);

        /* Second file
         * Crocodocable pdf
         */
        $plagscan2 = new Plagscan();
        $plagscan2
            ->setId(2)
            ->setPlagscanId(4396431)
            ->setStatus(-1);

        $storage2 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('603b535ff1c451df9013b392c59ed60ca36d5c89');

        $submissionFile2 = new SubmissionFile();
        $submissionFile2
            ->setId(133450000000)
            ->setStorage($storage2)
            ->setCrocodocId('768f4013-71d1-410e-bb2b-8e9d312d9dbc')
            ->setFilename($storage2->getFilename())
            ->setPlagscan($plagscan2);

        /* Third file
         * Image
         */
        $plagscan3 = new Plagscan();
        $plagscan3
            ->setId(3)
            ->setPlagscanId(4396443)
            ->setStatus(1);

        $storage3 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('efb4b418c6c963dc8760e9676986a4e99706f636');

        $submissionFile3 = new SubmissionFile();
        $submissionFile3
            ->setId(5645600000001)
            ->setStorage($storage3)
            ->setFilename($storage3->getFilename())
            ->setPlagscan($plagscan3);

        /* Fourth file
         * Crocodocable pptx
         */
        $plagscan4 = new Plagscan();
        $plagscan4
            ->setId(4)
            ->setPlagscanId(43964701)
            ->setStatus(2);

        $storage4 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('4b50da92d887f9e05804f7425125b5064f5cb385');

        $submissionFile4 = new SubmissionFile();
        $submissionFile4
            ->setId(6426700000001)
            ->setStorage($storage4)
            ->setCrocodocId('75ac23be-4210-43cf-93e1-77c172afab78')
            ->setFilename($storage4->getFilename())
            ->setPlagscan($plagscan4);

        /* Fifth file
         * Download only; cannot preview
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
            ->setId(301938400000001)
            ->setStorage($storage5)
            ->setFilename($storage5->getFilename())
            ->setPlagscan($plagscan5);

        /* Sixth file
         * Video
         */
        $storage6 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('287d6486bce26b39b03d959d4ff8f8625d5a811b');

        $submissionFile6 = new SubmissionFile();
        $submissionFile6
            ->setId(34590689000000001)
            ->setStorage($storage6)
            ->setFilename($storage6->getFilename());

        /* Seventh file
         * Audio
         */
        $storage7 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('3c3594d7a353225aed0335a78c7c829e2be4b135');

        $submissionFile7 = new SubmissionFile();
        $submissionFile7
            ->setId(5564226)
            ->setStorage($storage7)
            ->setFilename($storage7->getFilename());

        /* Eighth file
         * Crocodocable file that requires uploading
         */
        $storage8 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('50d1bfcdf0b16c800db2df44c6f866688f6fd50d');

        $submissionFile8 = new SubmissionFile();
        $submissionFile8
            ->setId(3648592600000001)
            ->setStorage($storage8)
            ->setCrocodocId(null)
            ->setFilename($storage8->getFilename() . ' - needs crocodoc upload');

        /* Feedback for this Response
         */
        $storage = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('8b6b83f10b12a9f16f9c6ce0581c992fffe11ed6');

        $feedbackFile = new FeedbackFile();
        $feedbackFile
            ->setId(12)
            ->setStorage($storage)
            ->setFilename($storage3->getFilename());

        /* Annotated Crocodoc
         * Note that we have to steal info from the SubmissionFile instance
         */
        $annotatedCrocodoc = new FeedbackFile();
        $annotatedCrocodoc
            ->setId(1331)
            ->setStorage($storage4)
            ->setCrocodocId('75ac23be-4210-43cf-93e1-77c172afab78')
            ->setFilename($storage4->getFilename());

        $feedback = new Feedback();
        $feedback
            ->setId(110000001)
            ->setReviewer($celia)
            ->setAssessable($assessment)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setAllFiles((new Collection())
                ->add($feedbackFile)
                ->add($annotatedCrocodoc)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-11-01 03:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-11-01 04:14:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 11:23:13', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Accumulate
         */
        $response = new Submission();
        $response
            ->setId(2)
            ->setAssessable($assessment)
            ->setSubmittedBy($hodes)
            ->setSubmittedOnBehalfOf($hodes)
            ->setAllFiles((new \Schoolbox\Collection())
                ->add($submissionFile1)
                ->add($submissionFile2)
                ->add($submissionFile3)
                ->add($submissionFile4)
                ->add($submissionFile5)
                ->add($submissionFile6)
                ->add($submissionFile7)
                ->add($submissionFile8)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $response->getAllFeedback()->add($feedback);

        $assessment->getResponses()->add($response);
        $assessment->getFeedbacks()->add($feedback);

        $this->addFloatingFeedbacks($assessment);

        return $response;
    }

    /**
     * Produce floating feedbacks for $assessment
     *
     * @param IAssessable $assessment
     */
    private function addFloatingFeedbacks(IAssessable $assessment)
    {
        $celia = \Schoolbox\UserMapper::getInstance()->findById(3);

        /* Floaters
         */
        $floating1 = new Feedback();
        $floating1
            ->setId(111)
            ->setAssessable($assessment)
            ->setReviewer($celia)
            ->setNormalisedMark(76)
            ->setHumanReadableMark('LOL @ U')
            ->setComment('This is a floating feedback.')
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 00:00:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $floating2 = new Feedback();
        $floating2
            ->setId(222)
            ->setAssessable($assessment)
            ->setReviewer($celia)
            ->setComment('A Feedback upon which it doth float.')
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Let's give this one a file
         */
        $storage3 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('78b04bf16ecf16550ec20d8df26b700ff22e6756');

        $feedbackFile1 = new FeedbackFile();
        $feedbackFile1
            ->setId(4598903)
            ->setStorage($storage3)
            ->setFilename('Some feedback in a random file I uploaded for you.txt');

        $floating3 = new Feedback();
        $floating3
            ->setId(333)
            ->setAssessable($assessment)
            ->setReviewer($celia)
            ->setComment('Antigravity Feedbackery.')
            ->setAllFiles((new Collection())->add($feedbackFile1))
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $assessment->getFeedbacks()->add($floating1)->add($floating2)->add($floating3);
    }
}