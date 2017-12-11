<?php

/**
 * TaskTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

class TaskTest extends TestCase
{
    use Defaults;


    /**
     * Test that Task initialises into a usable state
     *
     * @throws \PHPUnit\Framework\Exception
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
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($task);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        return $task;
    }


    /**
     * Test that Task basic details can be manufactured meaningfully
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    public function testBuild()
    {
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
     *
     * @throws \UnexpectedValueException
     */
    public function testResponses()
    {
        $task = $this->spawnTask();

        $response = new TaskResponse();
        $response
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $task->getParticipant($this->hodes)->getAllResponses()->add($response);

        /* Check that it linked to the Task correctly
         */
        $this->assertEquals(1234, $response->getAssessable()->getId());

        /* Authors
         */
        $this->assertEquals($this->hodes->getId(), $response->getSubmittedBy()->getId());
        $this->assertEquals($this->hodes->getId(), $response->getSubmittedOnBehalfOf()->getId());

        /* Dates
         */
        $this->assertEquals('2016-08-06 10:01:22', $response->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-06 10:01:22', $response->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $response->getDeletedAt());
    }


    /**
     * Test that Task Feedback can be manufactured meaningfully
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function testFeedback()
    {
        $task = $this->spawnTask();

        $response = (new TaskResponse())
            ->setId(87)
            ->setAssessable($task)
            ->setSubmittedBy($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new TaskFeedback())
            ->setResponse($response);

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
        $this->assertEquals('2016-09-15 01:27:33', $feedback->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-07 19:03:19', $feedback->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $feedback->getDeletedAt());
    }


    /**
     * Test that Task can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testResponseWithFeedback()
    {
        $task = $this->spawnTask();

        $response = new TaskResponse();
        $response
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new TaskFeedback())
            ->setResponse($response);

        $response->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($response);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Check everything connects up
         */
        $this->assertEquals(1234, $response->getAssessable()->getId());
        $this->assertEquals(1234, $feedback->getAssessable()->getId());
        $this->assertCount(1, $response->getAllFeedback());

        /* Total number of Responses
         */
        $this->assertCount(1, $task->getParticipant($this->hodes)->getAllResponses());
        $this->assertCount(1, $task->getParticipant($this->hodes)->getAllFeedbacks());

        /* Feedback setup
         */
        $this->assertEquals(883, $task->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->getId());
        $this->assertEquals(true, $task->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->isPublished());

        /* Total number of Feedbacks
         */
        $this->assertCount(1, $task->getParticipant($this->hodes)->getAllFeedbacks());
    }


    /**
     * Test that Tasks can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function testMostRecentPublishedFeedback()
    {
        $task = $this->spawnTask();

        $response = new TaskResponse();
        $response
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new TaskFeedback())
            ->setResponse($response);

        $response->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($response);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* The most recent feedback is published
         */
        $this->assertEquals(883, $task->getParticipant($this->hodes)->findMostRecentFeedback()->getId());
        $this->assertEquals(883, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback()->getId());
    }


    /**
     * Test that Tasks can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testMostRecentFeedback()
    {
        $task = $this->spawnTask();

        $response1 = new TaskResponse();
        $response1
            ->setId(87)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));
        $task->getParticipant($this->hodes)->getAllResponses()->add($response1);

        $response2 = new TaskResponse();
        $response2
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback2 = (new TaskFeedback())
            ->setResponse($response2);
        $response2->getAllFeedback()->add($feedback2);
        $task->getParticipant($this->hodes)->getAllResponses()->add($response2);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback2);

        /* The most recent feedback was published 2016-09-15 01:27:33
         */
        $this->assertEquals(883, $task->getParticipant($this->hodes)->findMostRecentFeedback()->getId());
        $this->assertEquals(883, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback()->getId());

        /* Most recent feedback has a normalised mark of null
         * Tasks always return null for normalised marks
         */
        $this->assertEquals(null, $task->getParticipant($this->hodes)->findMostRecentFeedback()->getNormalisedMark());
        $this->assertEquals(true, $task->getParticipant($this->hodes)->findMostRecentFeedback()->isPublished());
    }


    /**
     * What happens if you update an already-published feedback?
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function testUpdateFeedbackAfterPublish()
    {
        $task = $this->spawnTask();

        $response = new TaskResponse();
        $response
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new TaskFeedback())
            ->setResponse($response);

        $response->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($response);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* The most recent feedback was published 2016-09-15 01:27:33
         */
        $this->assertEquals(883, $task->getParticipant($this->hodes)->findMostRecentFeedback()->getId());
        $this->assertEquals(883, $task->getParticipant($this->hodes)->findMostRecentPublishedFeedback()->getId());

        /* Most recent feedback has a normalised mark of null
         * Tasks always return null for normalised marks
         */
        $this->assertEquals(null, $task->getParticipant($this->hodes)->findMostRecentFeedback()->getNormalisedMark());
        $this->assertEquals(true, $task->getParticipant($this->hodes)->findMostRecentFeedback()->isPublished());
    }
}



