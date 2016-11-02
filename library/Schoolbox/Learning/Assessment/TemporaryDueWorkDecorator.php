<?php
/**
 * TemporaryDueWorkDecorator
 *
 * A temporary decorator for the submission history screen
 * because this is so huge and convoluted
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\File\Mimetype;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use \Schoolbox\Learning\Assessment\DueWork\WorkType as WorkType;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as FeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as Plagscan;

class TemporaryDueWorkDecorator
{

    /**
     * TemporaryDueWorkDecorator constructor.
     */
    public function __construct()
    {
        $this->hodes = \Schoolbox\UserMapper::getInstance()->findById(230);
        $this->celia = \Schoolbox\UserMapper::getInstance()->findById(3);
    }

    /**
     * Interior Decorate
     *
     * @param DueWork $assessment
     */
    public function decorate(DueWork $assessment)
    {
        $this->generateResponse1($assessment);
        $this->generateResponse2($assessment);
        $this->generateFloaters($assessment);
    }

    /**
     * First chunk of test data
     *
     * @param DueWork $assessment
     */
    private function generateResponse1(DueWork $assessment)
    {
        $plagscan1 = new Plagscan();
        $plagscan1
            ->setId(1234)
            ->setStatus(3)
            ->setScore('93%')
            ->setHtml('<div><strong>YOU CHEATED</strong></div>');

        $mime1 = new Mimetype();
        $mime1->setDescription('JPEG Image');
        $mime1->setMime('image/jpeg');
        $mime1->setIconFile('image_jpeg.png');
        $mime1->setInline(1);
        $mime1->setEnable(1);
        $mime1->setMaxFileSize(0);

        $storage1 = new \Schoolbox\Resource\Storage\Model();
        $storage1
            ->setHash('s89789d9hw3thsug8923u5')
            ->setSize('12345678')
            ->setMimeType($mime1);

        $submissionFile1 = new SubmissionFile();
        $submissionFile1
            ->setId(11)
            ->setStorage($storage1)
            ->setCrocodocId('vjfh-6677=eirj-01928333')
            ->setFilename('mark this Sir.xlsx')
            ->setPlagscan($plagscan1);

        /* File 2
         */
        $plagscan2 = new Plagscan();
        $plagscan2
            ->setId(1234)
            ->setStatus(3)
            ->setScore('14%')
            ->setHtml('<div><strong>YOU CHEATED</strong></div>');

        $mime2 = new Mimetype();
        $mime2->setDescription('Microsoft Word Document');
        $mime2->setMime('application/msword');
        $mime2->setIconFile('msword_doc.png');
        $mime2->setInline(1);
        $mime2->setEnable(1);
        $mime2->setMaxFileSize(0);

        $storage2 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('c68af9f8084969afe55335989a0c8f58e9eec923');

        $submissionFile2 = new SubmissionFile();
        $submissionFile2
            ->setId(12)
            ->setStorage($storage2)
            ->setCrocodocId('vjfh-6677=eirj-01928333')
            ->setFilename('online-submission.html')
            ->setPlagscan($plagscan2);

        $response = new Submission();
        $response
            ->setId(1)
            ->setAssessable($assessment)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add($submissionFile1)
                ->add($submissionFile2)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Feedback for this Response
         */
        $mime3 = new Mimetype();
        $mime3->setDescription('Microsoft Word Document');
        $mime3->setMime('application/msword');
        $mime3->setIconFile('msword_doc.png');
        $mime3->setInline(1);
        $mime3->setEnable(1);
        $mime3->setMaxFileSize(0);

        $storage3 = new \Schoolbox\Resource\Storage\Model();
        $storage3
            ->setHash('aaaabbbcccccddd')
            ->setSize('1024')
            ->setMimetype($mime3);

        $feedbackFile1 = new FeedbackFile();
        $feedbackFile1
            ->setId(12)
            ->setStorage($storage3)
            ->setCrocodocId('j239jf-23mndi9f-1234nns9fh-5nl6i0f')
            ->setFilename('This is the correct response.txt');

        $feedback = new Feedback();
        $feedback
            ->setId(11)
            ->setReviewer($this->celia)
            ->setAssessable($assessment)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setAllFiles((new Collection())
                ->add($feedbackFile1)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-01 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $response->getAllFeedback()->add($feedback);

        $assessment->getResponses()->add($response);
        $assessment->getFeedbacks()->add($feedback);
    }

    /**
     * Second chunk of test data
     *
     * @param DueWork $assessment
     */
    private function generateResponse2(DueWork $assessment)
    {
        /* First file
         */
        $plagscan1 = new Plagscan();
        $plagscan1
            ->setId(1234)
            ->setStatus(3)
            ->setScore('45%')
            ->setHtml('<div><strong>YOU CHEATED</strong></div>');

        $mime2 = new Mimetype();
        $mime2->setDescription('Microsoft Word Document');
        $mime2->setMime('application/msword');
        $mime2->setIconFile('msword_doc.png');
        $mime2->setInline(1);
        $mime2->setEnable(1);
        $mime2->setMaxFileSize(0);

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
            ->setId(888)
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
            ->setId(9999)
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
            ->setId(7777)
            ->setStatus(2);

        $storage4 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('a351bf467f221eead2d0a184db35d13cc86a43e3');

        $submissionFile4 = new SubmissionFile();
        $submissionFile4
            ->setId(64267)
            ->setStorage($storage4)
            ->setCrocodocId('3d33lkjl2590')
            ->setFilename('chemistry.pptx')
            ->setPlagscan($plagscan4);

        /* Accumulate
         */
        $response = new Submission();
        $response
            ->setId(2)
            ->setAssessable($assessment)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add($submissionFile1)
                ->add($submissionFile2)
                ->add($submissionFile3)
                ->add($submissionFile4)
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = new Feedback();
        $feedback
            ->setId(22)
            ->setAssessable($assessment)
            ->setReviewer($this->celia)
            ->setAssessable($assessment)
            ->setNormalisedMark(56)
            ->setComment('Hadoken.')
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-03 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-03 14:14:14', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $response->getAllFeedback()->add($feedback);

        $assessment->getResponses()->add($response);
        $assessment->getFeedbacks()->add($feedback);
    }

    /**
     * Third hunk of test data
     *
     * @param DueWork $assessment
     */
    private function generateFloaters(DueWork $assessment)
    {
        /* Floaters
         */
        $floating1 = new Feedback();
        $floating1
            ->setId(111)
            ->setAssessable($assessment)
            ->setReviewer($this->celia)
            ->setNormalisedMark(86)
            ->setComment('This is a floating feedback.')
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 00:00:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $floating2 = new Feedback();
        $floating2
            ->setId(222)
            ->setAssessable($assessment)
            ->setReviewer($this->celia)
            ->setComment('A Feedback upon which it doth float.')
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Let's give this one a file
         */
        $storage3 = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('78b04bf16ecf16550ec20d8df26b700ff22e6756');

        $feedbackFile1 = new FeedbackFile();
        $feedbackFile1
            ->setId(4598903)
            ->setStorage($storage3)
            ->setCrocodocId('34068-33836-3860386-sdf')
            ->setFilename('Some feedback in a random file I uploaded for you.txt');

        $floating3 = new Feedback();
        $floating3
            ->setId(333)
            ->setAssessable($assessment)
            ->setReviewer($this->celia)
            ->setComment('Antigravity Feedbackery.')
            ->setAllFiles((new Collection())->add($feedbackFile1))
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $assessment
            ->getFeedbacks()
                ->add($floating1)
                ->add($floating2)
                ->add($floating3);
    }
}