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

use \Schoolbox\Learning\Assessment\Participant;

use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

class TaskTest extends \PHPUnit_Framework_TestCase
{
    use \Schoolbox\Learning\Assessment\Test\Defaults;

    /**
     * Test that Task initialises into a usable state
     */
    public function testInit()
    {
        $task = new Task();

        $this->assertInstanceOf(Collection::class, $task->getAllParticipants());
    }

    /**
     * Spawn a Task
     *
     * @return Task
     */
    private function spawnTask()
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

        return $task;
    }

    /**
     * Test that Task basic details can be manufactured meaningfully
     */
    public function testBuild()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = $this->spawnTask();

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
        $task = $this->spawnTask();

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
        $task = $this->spawnTask();

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
        $task = $this->spawnTask();

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
    public function testMostRecentPublishedFeedback()
    {
        $task = $this->spawnTask();

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
    public function testMostRecentFeedback()
    {
        $task = $this->spawnTask();

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
            ->setComment('Helly was here')
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
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getId());
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback($task)->getId());

        /* Most recent feedback has a normalised mark of null
         * Tasks always return null for normalised marks
         */
        $this->assertEquals(null, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getNormalisedMark());
        $this->assertEquals(true, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->isPublished());
    }

    /**
     * What happens if you update an already-published feedback?
     */
    public function testUpdateFeedbackAfterPublish()
    {
        $task = $this->spawnTask();

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
            ->setComment('This is the most recently published feedback, updated after publishing')
            ->setNormalisedMark(999999)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2017-08-27 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $floatingFeedback = new TaskFeedback();
        $floatingFeedback
            ->setId(89)
            ->setAssessable($task)
            ->setComment('Helly was here')
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
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getId());
        $this->assertEquals(87, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback($task)->getId());

        /* Most recent feedback has a normalised mark of null
         * Tasks always return null for normalised marks
         */
        $this->assertEquals(null, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->getNormalisedMark());
        $this->assertEquals(true, $task->getParticipant($this->hodes)->findMostRecentFeedback($task)->isPublished());
    }
}



