<?php

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Resource\Storage\Model as Storage;

use \Schoolbox\Learning\Assessment\Participant;

use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as DueWorkSubmissionFilePlagscan;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use \Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;

/**
 * DueWorkTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWorkTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var User User
     */
    protected $hodes;

    /**
     * @var User
     */
    protected $sberryhill;

    /**
     * @var User
     */
    protected $slucier;

    /**
     * @var User
     */
    protected $bsalem;

    /**
     * @var User
     */
    protected $asaxon;

    /**
     * @var User
     */
    protected $celia;

    /**
     * @var User
     */
    protected $somodio;

    /**
     * @var \Alaress_Date_Format
     */
    protected $today;

    /**
     * @var \Alaress_Date_Format
     */
    protected $tomorrow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $thirteenDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $fourteenDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $twentyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $thirtyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $fortyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $fiftyDaysFromNow;

    /**
     * @var \Alaress_Date_Format
     */
    protected $sixtyDaysFromNow;

    /**
     * The Bestiary
     */
    protected function setup()
    {
        /* Dudes
         */
        $this->hodes = (new \Schoolbox\User)->setId(230)->setFirstname('David')->setLastname('Rhodes');
        $this->sberryhill = (new \Schoolbox\User)->setId(15)->setFirstname('Scott')->setLastname('Berryhill');
        $this->slucier = (new \Schoolbox\User)->setId(16)->setFirstname('Sam')->setLastname('Lucier');
        $this->bsalem = (new \Schoolbox\User)->setId(32)->setFirstname('Bobby')->setLastname('Salem');
        $this->asaxon = (new \Schoolbox\User)->setId(17)->setFirstname('Anita')->setLastname('Saxon');
        $this->celia = (new \Schoolbox\User)->setId(3)->setFirstname('Celia')->setLastname('Glenn');
        $this->somodio = (new \Schoolbox\User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio');

        /* Times
         */
        $dateFormat = 'Y-m-d H:i:s';
        $dateInit = '2016-09-08 11:30:00';

        $this->today = new \Alaress_Date_Format($dateInit, $dateFormat);
        $this->tomorrow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1);
        $this->thirteenDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(13);
        $this->fourteenDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(14);
        $this->twentyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(20);
        $this->thirtyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(30);
        $this->fortyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(40);
        $this->fiftyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(50);
        $this->sixtyDaysFromNow = (new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(60);
    }

    /**
     * Test that Due Work initialises into a usable state
     */
    public function testInit()
    {
        $dueWork = new DueWork();

        $this->assertInstanceOf(Collection::class, $dueWork->getAllParticipants());
        $this->assertInstanceOf(Collection::class, $dueWork->getAllFiles());
        $this->assertInstanceOf(DueWorkType::class, $dueWork->getType());
        $this->assertSame(DueWork::ACCEPT_ONLINE_NONE, $dueWork->getAcceptOnline());
    }

    /**
     * Test that Due Work basic details can be manufactured meaningfully
     */
    public function testBuild()
    {
        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $files = [
            [
                'id' => 1,
                'hash' => 'abcdefg12341234',
                'filename' => 'myfile1.jpg'
            ],
            [
                'id' => 2,
                'hash' => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg'
            ],
        ];

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setAllFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId($files[0]['id'])
                    ->setHash($files[0]['hash'])
                    ->setFilename($files[0]['filename']))
                ->add((new DueWorkFile())
                    ->setId($files[1]['id'])
                    ->setHash($files[1]['hash'])
                    ->setFilename($files[1]['filename'])))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Details
         */
        $this->assertEquals($title, $dueWork->getTitle());
        $this->assertEquals($description, $dueWork->getDescription());
        $this->assertEquals($typeName, $dueWork->getTypeName());

        /* Authored correctly?
         */
        $this->assertEquals($this->somodio->getId(), $dueWork->getCreator()->getId());

        /* Everybody on board?
         */
        $this->assertCount(4, $dueWork->getAllParticipants());

        /** @var User $currentUser */
        foreach ([$this->hodes, $this->bsalem, $this->sberryhill, $this->asaxon] as $currentUser) {
            $this->assertEquals($currentUser->getId(), $dueWork->getParticipant($currentUser)->getUser()->getId());
        }

        /* Files
         */
        for ($i = 0; $i < count($files); $i++) {
            $currentFile = $files[$i];

            $this->assertEquals($currentFile['id'], $dueWork->getAllFiles()->getOffset($i)->getId());
            $this->assertEquals($currentFile['hash'], $dueWork->getAllFiles()->getOffset($i)->getHash());
            $this->assertEquals($currentFile['filename'], $dueWork->getAllFiles()->getOffset($i)->getFilename());
        }

        /* Dates
         */
        $this->assertEquals($this->thirteenDaysFromNow->getDatabase(), $dueWork->getPublishAt()->getDatabase());
        $this->assertEquals($this->fourteenDaysFromNow->getDatabase(), $dueWork->getOpenAt()->getDatabase());
        $this->assertEquals($this->twentyDaysFromNow->getDatabase(), $dueWork->getDueAt()->getDatabase());
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $dueWork->getCloseAt()->getDatabase());

        $this->assertEquals($this->today->getDatabase(), $dueWork->getCreatedAt()->getDatabase());
        $this->assertEquals($this->today->getDatabase(), $dueWork->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $dueWork->getDeletedAt());
    }

    /**
     * Test that Due Work Responses can be manufactured meaningfully
     */
    public function testResponses()
    {
        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $files = [
            [
                'id' => 1,
                'hash' => 'abcdefg12341234',
                'filename' => 'myfile1.jpg'
            ],
            [
                'id' => 2,
                'hash' => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg'
            ],
        ];

        $submissionFiles = [
            [
                'id' => 11,
                'hash' => 's89789d9hw3thsug8923u5',
                'crocodocId' => 'vjfh-6677=eirj-01928333',
                'filename' => 'mark this Sir.xlsx',
                'plagscan' => [
                    'id' => 1234,
                    'status' => 1,
                    'score' => '93% plagiariased',
                    'html' => '<div><strong>YOU CHEATED</strong></div>'
                ]
            ],
            [
                'id' => 23,
                'hash' => 'lk5j34kl5jousdio',
                'crocodocId' => 'vjfh-6677=eirj-01928333',
                'filename' => 'and this too.docx'
            ],
        ];

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setAllFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId($files[0]['id'])
                    ->setHash($files[0]['hash'])
                    ->setFilename($files[0]['filename']))
                ->add((new DueWorkFile())
                    ->setId($files[1]['id'])
                    ->setHash($files[1]['hash'])
                    ->setFilename($files[1]['filename'])))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[0]['hash']))
                    ->setCrocodocId($submissionFiles[0]['crocodocId'])
                    ->setFilename($submissionFiles[0]['filename'])
                    ->setPlagscan((new DueWorkSubmissionFilePlagscan())
                        ->setId($submissionFiles[0]['plagscan']['id'])
                        ->setStatus($submissionFiles[0]['plagscan']['status'])
                        ->setScore($submissionFiles[0]['plagscan']['score'])
                        ->setHtml($submissionFiles[0]['plagscan']['html'])))
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[1]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[1]['hash']))
                    ->setCrocodocId($submissionFiles[1]['crocodocId'])
                    ->setFilename($submissionFiles[1]['filename'])))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Check that it linked to the Due Work correctly
         */
        $this->assertEquals(2, $submission->getAssessable()->getId());

        /* Authors
         */
        $this->assertEquals($this->hodes->getId(), $submission->getSubmittedBy()->getId());
        $this->assertEquals($this->hodes->getId(), $submission->getSubmittedOnBehalfOf()->getId());

        /* Files
         */
        for ($i = 0; $i < count($submissionFiles); $i++) {
            $currentFile = $submissionFiles[$i];

            $this->assertEquals($currentFile['id'], $submission->getAllFiles()->getOffset($i)->getId());
            $this->assertEquals($currentFile['hash'], $submission->getAllFiles()->getOffset($i)->getStorage()->getHash());
            $this->assertEquals($currentFile['crocodocId'], $submission->getAllFiles()->getOffset($i)->getCrocodocId());
            $this->assertEquals($currentFile['filename'], $submission->getAllFiles()->getOffset($i)->getFilename());
        }

        /* One of them has a Plagscan
         */
        $this->assertEquals($submissionFiles[0]['plagscan']['id'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getId());
        $this->assertEquals($submissionFiles[0]['plagscan']['status'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getStatus());
        $this->assertEquals($submissionFiles[0]['plagscan']['score'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getScore());
        $this->assertEquals($submissionFiles[0]['plagscan']['html'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getHtml());

        /* Dates
         */
        $this->assertEquals('2016-08-01 09:00:00', $submission->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-01 09:00:00', $submission->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $submission->getDeletedAt());
    }

    /**
     * Test that Due Work Feedback can be manufactured meaningfully
     */
    public function testFeedback()
    {
        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $files = [
            [
                'id' => 1,
                'hash' => 'abcdefg12341234',
                'filename' => 'myfile1.jpg'
            ],
            [
                'id' => 2,
                'hash' => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg'
            ],
        ];

        $feedbackFiles = [
            [
                'id' => 112,
                'hash' => 'lj23lj2llj5l2jsdkjf90',
                'crocodocId' => 'j239jf-23mndi9f-1234nns9fh-5nl6i0f',
                'filename' => 'This is the correct response.txt',
            ],
        ];

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setAllFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId($files[0]['id'])
                    ->setHash($files[0]['hash'])
                    ->setFilename($files[0]['filename']))
                ->add((new DueWorkFile())
                    ->setId($files[1]['id'])
                    ->setHash($files[1]['hash'])
                    ->setFilename($files[1]['filename'])))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setAllFiles((new Collection())
                ->add((new DueWorkFeedbackFile())
                    ->setId($feedbackFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($feedbackFiles[0]['hash']))
                    ->setCrocodocId($feedbackFiles[0]['crocodocId'])
                    ->setFilename($feedbackFiles[0]['filename'])))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Check that it linked to the Due Work correctly
         */
        $this->assertEquals(2, $feedback->getAssessable()->getId());
        $this->assertEquals(-3, $feedback->getNormalisedMark());
        $this->assertEquals('This is a flawed piece of work.', $feedback->getComment());

        /* Authors
         */
        $this->assertEquals($this->celia->getId(), $feedback->getReviewer()->getId());

        /* Files
         */
        for ($i = 0; $i < count($feedbackFiles); $i++) {
            $currentFile = $feedbackFiles[$i];

            $this->assertEquals($currentFile['id'], $feedback->getAllFiles()->getOffset($i)->getId());
            $this->assertEquals($currentFile['hash'], $feedback->getAllFiles()->getOffset($i)->getStorage()->getHash());
            $this->assertEquals($currentFile['crocodocId'], $feedback->getAllFiles()->getOffset($i)->getCrocodocId());
            $this->assertEquals($currentFile['filename'], $feedback->getAllFiles()->getOffset($i)->getFilename());
        }

        /* Dates
         */
        $this->assertEquals('2016-08-03 14:15:16', $feedback->getPublishedAt()->getDatabase());
        $this->assertEquals('2016-08-01 13:14:15', $feedback->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-02 14:14:12', $feedback->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $feedback->getDeletedAt());
    }

    /**
     * Test that Due Work can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Due Work
     */
    public function testResponseWithFeedback()
    {

        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $files = [
            [
                'id' => 1,
                'hash' => 'abcdefg12341234',
                'filename' => 'myfile1.jpg'
            ],
            [
                'id' => 2,
                'hash' => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg'
            ],
        ];

        $submissionFiles = [
            [
                'id' => 11,
                'hash' => 's89789d9hw3thsug8923u5',
                'crocodocId' => 'vjfh-6677=eirj-01928333',
                'filename' => 'mark this Sir.xlsx',
                'plagscan' => [
                    'id' => 1234,
                    'status' => 1,
                    'score' => '93% plagiariased',
                    'html' => '<div><strong>YOU CHEATED</strong></div>'
                ]
            ],
            [
                'id' => 23,
                'hash' => 'lk5j34kl5jousdio',
                'crocodocId' => 'vjfh-6677=eirj-01928333',
                'filename' => 'and this too.docx'
            ],
        ];

        $feedbackFiles = [
            [
                'id' => 112,
                'hash' => 'lj23lj2llj5l2jsdkjf90',
                'crocodocId' => 'j239jf-23mndi9f-1234nns9fh-5nl6i0f',
                'filename' => 'This is the correct response.txt',
            ],
        ];

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setAllFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId($files[0]['id'])
                    ->setHash($files[0]['hash'])
                    ->setFilename($files[0]['filename']))
                ->add((new DueWorkFile())
                    ->setId($files[1]['id'])
                    ->setHash($files[1]['hash'])
                    ->setFilename($files[1]['filename'])))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[0]['hash']))
                    ->setCrocodocId($submissionFiles[0]['crocodocId'])
                    ->setFilename($submissionFiles[0]['filename'])
                    ->setPlagscan((new DueWorkSubmissionFilePlagscan())
                        ->setId($submissionFiles[0]['plagscan']['id'])
                        ->setStatus($submissionFiles[0]['plagscan']['status'])
                        ->setScore($submissionFiles[0]['plagscan']['score'])
                        ->setHtml($submissionFiles[0]['plagscan']['html'])))
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[1]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[1]['hash']))
                    ->setCrocodocId($submissionFiles[1]['crocodocId'])
                    ->setFilename($submissionFiles[1]['filename'])))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setAllFiles((new Collection())
                ->add((new DueWorkFeedbackFile())
                    ->setId($feedbackFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($feedbackFiles[0]['hash']))
                    ->setCrocodocId($feedbackFiles[0]['crocodocId'])
                    ->setFilename($feedbackFiles[0]['filename'])))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setComment('Actually it is ok')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* Check everything connects up
         */
        $this->assertEquals(2, $submission->getAssessable()->getId());
        $this->assertEquals(2, $feedback->getAssessable()->getId());
        $this->assertCount(1, $submission->getAllFeedback());

        /* Total number of Submissions
         */
        $this->assertCount(1, $dueWork->getParticipant($this->hodes)->getAllResponses());
        $this->assertCount(2, $dueWork->getParticipant($this->hodes)->findAllFeedbackForAssessable($dueWork));

        /* Feedback setup
         * The Freefloater isn't published
         */
        $this->assertEquals(3385, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->getId());
        $this->assertEquals(true, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->isPublished());
        $this->assertEquals(false, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(1)->isPublished());

        /* Total number of Feedbacks
         */
        $this->assertCount(2, $dueWork->getParticipant($this->hodes)->getAllFeedbacks());
        $this->assertCount(2, $dueWork->getParticipant($this->hodes)->findAllFeedbackForAssessable($dueWork));

        /* Feedbacks directly associated with Assessable
         * and not the Submission itself
         */
        $this->assertEquals(4223, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(1)->getId());
    }

    /**
     * Test that Due Work Feedback Marks are stored correctly
     */
    public function testMarks()
    {
    }
}