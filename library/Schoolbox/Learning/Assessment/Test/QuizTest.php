<?php

/**
 * QuizTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Resource\Storage\Model as Storage;

use \Schoolbox\Learning\Assessment\Participant;

use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use \Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

class QuizTest extends \PHPUnit_Framework_TestCase
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
        $dateInit = '2016-09-09 14:35:00';

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
     * Test that Quiz initialises into a usable state
     */
    public function testInit()
    {
        $quiz = new Quiz();

        $this->assertInstanceOf(Collection::class, $quiz->getAllParticipants());
    }

    /**
     * Test that Quiz basic details can be manufactured meaningfully
     */
    public function testBuild()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = (new Quiz())
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Details
         */
        $this->assertEquals($title, $quiz->getTitle());
        $this->assertEquals($description, $quiz->getDescription());

        /* Authored correctly?
         */
        $this->assertEquals($this->somodio->getId(), $quiz->getCreator()->getId());

        /* Everybody on board?
         */
        $this->assertCount(4, $quiz->getAllParticipants());

        /** @var User $currentUser */
        foreach ([$this->hodes, $this->bsalem, $this->sberryhill, $this->asaxon] as $currentUser) {
            $this->assertEquals($currentUser->getId(), $quiz->getParticipant($currentUser)->getUser()->getId());
        }

        /* Dates
         */
        $this->assertEquals($this->twentyDaysFromNow->getDatabase(), $quiz->getPublishAt()->getDatabase());
        $this->assertEquals($this->twentyDaysFromNow->getDatabase(), $quiz->getOpenAt()->getDatabase());
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $quiz->getDueAt()->getDatabase());
        $this->assertEquals($this->fortyDaysFromNow->getDatabase(), $quiz->getCloseAt()->getDatabase());

        $this->assertEquals($this->today->getDatabase(), $quiz->getCreatedAt()->getDatabase());
        $this->assertEquals($this->today->getDatabase(), $quiz->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $quiz->getDeletedAt());
    }

    /**
     * Test that Quiz Responses can be manufactured meaningfully
     */
    public function testResponses()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = (new Quiz())
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $attempt = (new QuizAttempt);
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->bsalem)
            ->setSubmittedOnBehalfOf($this->bsalem)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quiz->getParticipant($this->bsalem)->getAllResponses()->add($attempt);

        /* Check that it linked to the Quiz correctly
         */
        $this->assertEquals(3, $attempt->getAssessable()->getId());

        /* Authors
         */
        $this->assertEquals($this->bsalem->getId(), $attempt->getSubmittedBy()->getId());
        $this->assertEquals($this->bsalem->getId(), $attempt->getSubmittedOnBehalfOf()->getId());

        /* Dates
         */
        $this->assertEquals($this->today->getDatabase(), $attempt->getCreatedAt()->getDatabase());
        $this->assertEquals($this->today->getDatabase(), $attempt->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $attempt->getDeletedAt());
    }

    /**
     * Test that Quiz Feedback can be manufactured meaningfully
     */
    public function testFeedback()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = (new Quiz())
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $feedback = new QuizReview();
        $feedback
            ->setId(99762)
            ->setAssessable($quiz)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $quiz->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Check that it linked to the Quiz correctly
         */
        $this->assertEquals(3, $feedback->getAssessable()->getId());
        $this->assertEquals(null, $feedback->getComment());

        /* Authors
         */
        $this->assertEquals($this->celia->getId(), $feedback->getReviewer()->getId());

        /* Dates
         */
        $this->assertEquals('2016-09-15 03:27:33', $feedback->getPublishedAt()->getDatabase());
        $this->assertEquals('2016-08-08 21:03:19', $feedback->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-08 21:03:19', $feedback->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $feedback->getDeletedAt());
    }

    /**
     * Test that Quiz can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Quiz
     */
    public function testResponseWithFeedback()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = (new Quiz())
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $attempt = (new QuizAttempt);
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->bsalem)
            ->setSubmittedOnBehalfOf($this->bsalem)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $feedback = new QuizReview();
        $feedback
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(98)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99760)
            ->setAssessable($quiz)
            ->setNormalisedMark(45)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-16 05:31:29', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-12 12:12:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-12 12:12:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99761)
            ->setAssessable($quiz)
            ->setNormalisedMark(1)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99762)
            ->setAssessable($quiz)
            ->setNormalisedMark(83)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-25 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-08 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-08 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $quiz->getParticipant($this->bsalem)->getAllResponses()->add($attempt);

        /* Check everything connects up
         */
        $this->assertEquals(3, $attempt->getAssessable()->getId());
        $this->assertEquals(3, $feedback->getAssessable()->getId());
        $this->assertCount(4, $attempt->getAllFeedback());

        /* Total number of Responses
         */
        $this->assertCount(1, $quiz->getParticipant($this->bsalem)->getAllResponses());
        $this->assertCount(4, $quiz->getParticipant($this->bsalem)->findAllFeedbackForAssessable($quiz));

        /* Total number of Feedbacks
         */
        $this->assertCount(4, $quiz->getParticipant($this->bsalem)->getAllFeedbacks());
        $this->assertCount(4, $quiz->getParticipant($this->bsalem)->findAllFeedbackForAssessable($quiz));
    }

    /**
     * Test that Tasks can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     */
    public function testPublishedFeedback()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = (new Quiz())
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $attempt = (new QuizAttempt);
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->bsalem)
            ->setSubmittedOnBehalfOf($this->bsalem)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $feedback = new QuizReview();
        $feedback
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(98)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99760)
            ->setAssessable($quiz)
            ->setNormalisedMark(45)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-16 05:31:29', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-12 12:12:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-12 12:12:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99761)
            ->setAssessable($quiz)
            ->setNormalisedMark(1)
            ->setComment('This is the most recent feedback')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99762)
            ->setAssessable($quiz)
            ->setNormalisedMark(83)
            ->setComment('This is the most recent -published- feedback')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-25 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-08 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-08 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $quiz->getParticipant($this->bsalem)->getAllResponses()->add($attempt);

        /* The most recent feedback and most recent published feedback
         * 99761 and 99762 respectively
         */
        $this->assertEquals(99761, $quiz->getParticipant($this->bsalem)->findMostRecentFeedback($quiz)->getId());
        $this->assertEquals(99762, $quiz->getParticipant($this->bsalem)->findMostRecentPublishedFeedback($quiz)->getId());
    }
}