<?php

/**
 * TaskTest
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

use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

class TaskTest extends \PHPUnit_Framework_TestCase
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
     * Test that Task initialises into a usable state
     */
    public function testInit()
    {
        $task = new Task();

        $this->assertInstanceOf(Collection::class, $task->getAllParticipants());
    }

    /**
     * Test that Task basic details can be manufactured meaningfully
     */
    public function testBuild()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Details
         */
        $this->assertEquals($title, $task->getTitle());
        $this->assertEquals($description, $task->getDescription());

        /* Authored correctly?
         */
        $this->assertEquals($this->celia->getId(), $task->getCreator()->getId());

        /* Everybody on board?
         */
        $this->assertCount(3, $task->getAllParticipants());

        /** @var User $currentUser */
        foreach ([$this->hodes, $this->sberryhill, $this->asaxon] as $currentUser) {
            $this->assertEquals($currentUser->getId(), $task->getParticipant($currentUser)->getUser()->getId());
        }

        /* Dates
         */
        $this->assertEquals($this->thirteenDaysFromNow->getDatabase(), $task->getPublishAt()->getDatabase());
        $this->assertEquals($this->thirteenDaysFromNow->getDatabase(), $task->getOpenAt()->getDatabase());
        $this->assertEquals($this->fourteenDaysFromNow->getDatabase(), $task->getDueAt()->getDatabase());
        $this->assertEquals($this->fourteenDaysFromNow->getDatabase(), $task->getCloseAt()->getDatabase());

        $this->assertEquals($this->today->getDatabase(), $task->getCreatedAt()->getDatabase());
        $this->assertEquals($this->today->getDatabase(), $task->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $task->getDeletedAt());
    }

    /**
     * Test that Task Responses can be manufactured meaningfully
     */
    public function testResponses()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $completion = (new TaskCompletion);
        $completion
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $task->getParticipant($this->hodes)->getAllResponses()->add($completion);

        /* Check that it linked to the Task correctly
         */
        $this->assertEquals(1234, $completion->getAssessable()->getId());

        /* Authors
         */
        $this->assertEquals($this->hodes->getId(), $completion->getSubmittedBy()->getId());
        $this->assertEquals($this->hodes->getId(), $completion->getSubmittedOnBehalfOf()->getId());

        /* Dates
         */
        $this->assertEquals('2016-08-06 10:01:22', $completion->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-06 10:01:22', $completion->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $completion->getDeletedAt());
    }


    /**
     * Test that Task Feedback can be manufactured meaningfully
     */
    public function testFeedback()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $feedback = new TaskFeedback();
        $feedback
            ->setId(87)
            ->setAssessable($task)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Check that it linked to the Task correctly
         */
        $this->assertEquals(1234, $feedback->getAssessable()->getId());
        $this->assertEquals(null, $feedback->getComment());

        /* Authors
         */
        $this->assertEquals($this->celia->getId(), $feedback->getReviewer()->getId());

        /* Dates
         */
        $this->assertEquals('2016-09-15 01:27:33', $feedback->getPublishedAt()->getDatabase());
        $this->assertEquals('2016-08-07 19:03:19', $feedback->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-07 19:03:19', $feedback->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $feedback->getDeletedAt());
    }

    /**
     * Test that Task can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     */
    public function testResponseWithFeedback()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $completion = (new TaskCompletion);
        $completion
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = new TaskFeedback();
        $feedback
            ->setId(87)
            ->setAssessable($task)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $completion->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($completion);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Check everything connects up
         */
        $this->assertEquals(1234, $completion->getAssessable()->getId());
        $this->assertEquals(1234, $feedback->getAssessable()->getId());
        $this->assertCount(1, $completion->getAllFeedback());

        /* Total number of Responses
         */
        $this->assertCount(1, $task->getParticipant($this->hodes)->getAllResponses());
        $this->assertCount(1, $task->getParticipant($this->hodes)->findAllFeedbackForAssessable($task));

        /* Feedback setup
         */
        $this->assertEquals(87, $task->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->getId());
        $this->assertEquals(true, $task->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->isPublished());

        /* Total number of Feedbacks
         */
        $this->assertCount(1, $task->getParticipant($this->hodes)->getAllFeedbacks());
        $this->assertCount(1, $task->getParticipant($this->hodes)->findAllFeedbackForAssessable($task));
    }

    /**
     * Test that Tasks can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     */
    public function testPublishedFeedback()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $completion = (new TaskCompletion);
        $completion
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = new TaskFeedback();
        $feedback
            ->setId(87)
            ->setAssessable($task)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $completion->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($completion);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* The most recent feedback is published
         */
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getId());
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback($task)->getId());
    }

    /**
     * Test that Tasks can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     */
    public function testUnublishedFeedback()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $completion = (new TaskCompletion);
        $completion
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = new TaskFeedback();
        $feedback
            ->setId(87)
            ->setAssessable($task)
            ->setComment('This is the most recently published feedback')
            ->setNormalisedMark(999999)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $floatingFeedback = new TaskFeedback();
        $floatingFeedback
            ->setId(89)
            ->setAssessable($task)
            ->setNormalisedMark(8888888)
            ->setReviewer($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:20', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:20', \Alaress_Date_Format::DATABASE_FORMAT));

        $completion->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($completion);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* The most recent feedback was published 2016-09-15 01:27:33
         */
        $this->assertEquals(89, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getId());
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback($task)->getId());

        /* Most recent feedback has a normalised mark of null
         * Tasks always return null for normalised marks
         */
        $this->assertEquals(null, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getNormalisedMark());
        $this->assertEquals(false, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->isPublished());
    }
}