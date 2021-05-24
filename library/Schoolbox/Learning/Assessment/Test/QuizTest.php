<?php

/**
 * QuizTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

class QuizTest extends TestCase
{
    use Defaults;


    /**
     * Test that Quiz initialises into a usable state
     *
     * @throws \PHPUnit\Framework\Exception
     */
    public function testInit()
    {
        $quiz = new Quiz();
        $this->assertInstanceOf(Collection::class, $quiz->getAllParticipants());
    }


    /**
     * Test that Quiz basic details can be manufactured meaningfully
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    public function testBuild()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $quizPlan);

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
     *
     * @throws \UnexpectedValueException
     */
    public function testResponses()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $quizPlan);

        $attempt = new QuizAttempt();
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
     *
     * @throws \UnexpectedValueException
     */
    public function testFeedback()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $quizPlan);

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
     *
     * @throws \UnexpectedValueException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testResponseWithFeedback()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $quizPlan);

        $attempt = new QuizAttempt();
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
        $this->assertCount(4, $quiz->getParticipant($this->bsalem)->getAllFeedbacks());

        /* Total number of Feedbacks
         */
        $this->assertCount(4, $quiz->getParticipant($this->bsalem)->getAllFeedbacks());
    }


    /**
     * Test that Tasks can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Task
     *
     * @throws \UnexpectedValueException
     */
    public function testMostRecentPublishedFeedback()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $quizPlan);

        $responses = [
            [
                'mark' => 4, // Question 1,
                'available' => 5,
            ],
            [
                'mark' => 0, // Question 2
                'available' => 5,
            ],
            [
                'mark' => 2, // Question 3
                'available' => 5,
            ],
            [
                'mark' => 1, // Question 4
                'available' => 5,
            ]
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt = (new QuizAttempt())
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->bsalem)
            ->setSubmittedOnBehalfOf($this->bsalem)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today)
            ->setMarksAvailable($marksAvailable)
            ->setMarksGiven($marksGiven);

        $feedback = (new QuizReview())
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(98)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setResponse($attempt);

        $attempt->getAllFeedback()->add($feedback);


        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);
        $quiz->getParticipant($this->bsalem)->getAllResponses()->add($attempt);

        /* The most recent feedback is 99759
         */
        $this->assertEquals(99759, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenFeedback()->getId());

        /* The most recent feedback is also published
         */
        $this->assertEquals(99759, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getId());

        /* Quiz's Normalised Mark is derived. It does not use the getter/setter
         * for Normalised Mark like the other Assessables
         */
        $this->assertNotEquals(83, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());

        /* Test its derived stuff instead
         * 7 / 20 = 35.0
         */
        $this->assertEquals(35.0, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());
    }


    /**
     * What happens if you update an already-published feedback?
     *
     * @throws \UnexpectedValueException
     */
    public function testUpdateFeedbackAfterPublish()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = new Quiz();
        $quiz
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $quizPlan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $quizPlan);

        $attempt = new QuizAttempt();
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
        $feedback->setResponse($attempt);
        $responses = [
            [
                'mark' => 4, // Question 1,
                'available' => 5
            ],
            [
                'mark' => 0, // Question 2
                'available' => 5
            ],
            [
                'mark' => 2, // Question 3
                'available' => 5
            ],
            [
                'mark' => 1, // Question 4
                'available' => 5
            ]
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
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
        $feedback->setResponse($attempt);
        $responses = [
            [
                'mark' => 5, // Question 1,
                'available' => 5
            ],
            [
                'mark' => 5, // Question 2
                'available' => 5
            ],
            [
                'mark' => 3, // Question 3
                'available' => 5
            ],
            [
                'mark' => 3, // Question 4
                'available' => 5
            ]
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99761)
            ->setAssessable($quiz)
            ->setNormalisedMark(1)
            ->setReviewer($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $feedback->setResponse($attempt);
        $responses = [
            [
                'mark' => 1, // Question 1,
                'available' => 5
            ],
            [
                'mark' => 1, // Question 2
                'available' => 5
            ],
            [
                'mark' => 1, // Question 3
                'available' => 5
            ],
            [
                'mark' => 2, // Question 4
                'available' => 5
            ]
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99762)
            ->setAssessable($quiz)
            ->setNormalisedMark(83)
            ->setComment('This is the most recent published feedback; also most recent feedback')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-25 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-10 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2017-09-10 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $feedback->setResponse($attempt);
        $responses = [
            [
                'mark' => 3, // Question 1,
                'available' => 5
            ],
            [
                'mark' => 2, // Question 2
                'available' => 5
            ],
            [
                'mark' => 2, // Question 3
                'available' => 5
            ],
            [
                'mark' => 5, // Question 4
                'available' => 5
            ]
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
        $quiz->getParticipant($this->bsalem)->getAllFeedbacks()->add($feedback);

        $quiz->getParticipant($this->bsalem)->getAllResponses()->add($attempt);

        /* The most recent feedback is 99762
         */
        $this->assertEquals(99762, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenFeedback()->getId());

        /* The most recent feedback is also published
         */
        $this->assertEquals(99762, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getId());

        /* Quiz's Normalised Mark is derived. It does not use the getter/setter
         * for Normalised Mark like the other Assessables
         */
        $this->assertNotEquals(83, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());

        /* Test its derived stuff instead
         */
        $this->assertEquals(60, $quiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());
    }


    /**
     * Test most recent response retrieval, when responses are published on the
     * same date.
     */
    public function testFindMostRecentResponseWhenDatesAreIdentical(): void
    {
        $time     = \Alaress_Date_Format::newDatabaseDate('2020-07-01 00:00:00');
        $quiz     = new Quiz();
        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);

        $publishedResponse1 = new QuizAttempt();
        $publishedResponse1
            ->setId(1)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $publishedResponse2 = new QuizAttempt();
        $publishedResponse2
            ->setId(2)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $unpublishedResponse1 = new QuizAttempt();
        $unpublishedResponse1
            ->setId(3)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt(new \Alaress_Date_NullDate())
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $unpublishedResponse2 = new QuizAttempt();
        $unpublishedResponse2
            ->setId(4)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt(new \Alaress_Date_NullDate())
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        // Test feedback belonging to a participant
        $participant = $quiz->getParticipant($this->hodes);
        $participant->setAllResponses(
            $participant->getAllResponses()->emptyClone()
                        ->add($publishedResponse1)
                        ->add($publishedResponse2)
                        ->add($unpublishedResponse1)
                        ->add($unpublishedResponse2)
        );

        self::assertSame($publishedResponse2, $participant->findMostRecentSubmittedResponse());
        self::assertSame($unpublishedResponse2, $participant->findMostRecentResponse());
        self::assertSame($unpublishedResponse2, $participant->findMostRecentDraftResponse());
    }


    /**
     * Test most recent feedback retrieval, when feedbacks are published on the
     * same date.
     */
    public function testFindMostRecentFeedbackWhenDatesAreIdentical(): void
    {
        $time     = \Alaress_Date_Format::newDatabaseDate('2020-07-01 00:00:00');
        $quiz     = new Quiz();
        $response = new QuizAttempt();
        $response
            ->setId(1)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $quizPlan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $quizPlan);

        // Feedback given on the same date:
        // disambiguate by ID (highest ID is most recent)
        $publishedFeedback1 = (new QuizReview())
            ->setId(1)
            ->setAssessable($quiz)
            ->setReviewer($this->celia)
            ->setPublishedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);
        $publishedFeedback2 = (new QuizReview())
            ->setId(2)
            ->setAssessable($quiz)
            ->setReviewer($this->celia)
            ->setPublishedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $unpublishedFeedback1 = (new QuizReview())
            ->setId(3)
            ->setAssessable($quiz)
            ->setReviewer($this->celia)
            ->setPublishedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);
        $unpublishedFeedback2 = (new QuizReview())
            ->setId(4)
            ->setAssessable($quiz)
            ->setReviewer($this->celia)
            ->setPublishedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        // Test feedback belonging to a response
        $response->setAllFeedback(
            $response->getAllFeedback()->emptyClone()
                     ->add($publishedFeedback1)
                     ->add($publishedFeedback2)
                     ->add($unpublishedFeedback1)
                     ->add($unpublishedFeedback2)
        );

        self::assertSame($publishedFeedback2, $response->findMostRecentInstructorGivenPublishedFeedback());
        self::assertSame($unpublishedFeedback2, $response->findMostRecentInstructorGivenFeedback());
        self::assertNull($response->findMostRecentSelfAssessedPublishedFeedback());
        self::assertNull($response->findMostRecentSelfAssessedFeedback());

        // Test feedback belonging to a participant
        $participant = $quiz->getParticipant($this->hodes);
        $participant->setAllFeedbacks(
            $participant->getAllFeedbacks()->emptyClone()
                        ->add($publishedFeedback1)
                        ->add($publishedFeedback2)
                        ->add($unpublishedFeedback1)
                        ->add($unpublishedFeedback2)
        );

        self::assertSame($publishedFeedback2, $participant->findMostRecentInstructorGivenPublishedFeedback());
        self::assertSame($unpublishedFeedback2, $participant->findMostRecentInstructorGivenFeedback());
        self::assertNull($participant->findMostRecentSelfAssessedPublishedFeedback());
        self::assertNull($participant->findMostRecentSelfAssessedFeedback());
    }
}
