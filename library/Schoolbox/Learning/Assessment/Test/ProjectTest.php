<?php

/**
 * ProjectTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkSubmission;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Model as DueWorkFeedbackFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as DueWorkSubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as DueWorkSubmissionFilePlagscan;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

class ProjectTest extends TestCase
{
    use Defaults;


    /**
     * Test that Project initialises into a usable state
     *
     * @throws \PHPUnit\Framework\Exception
     */
    public function testInit()
    {
        $project = new Project(new SummatorAverage());

        $this->assertInstanceOf(Collection::class, $project->getAssessables());
        $this->assertInstanceOf(SummatorAverage::class, $project->getSummator());
    }


    /**
     * Test that project is in the specification context, if it's in a unit spec.
     *
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function testInSpecification()
    {
        $project = new Project(new SummatorAverage());
        $project->setContext(new UnitSpecification());

        $this->assertFalse($project->isImplementationContext());
        $this->assertTrue($project->isSpecificationContext());
    }


    /**
     * Test that project is in the implementation context, if it's in a folder.
     *
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function testInFolder()
    {
        $project = new Project(new SummatorAverage());
        $project->setContext(new Folder());

        $this->assertTrue($project->isImplementationContext());
        $this->assertFalse($project->isSpecificationContext());
    }


    /**
     * Test that project is in the implementation context, if it's in a unit impl.
     *
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function testInImplementation()
    {
        $project = new Project(new SummatorAverage());
        $project->setContext(new UnitImplementation());

        $this->assertTrue($project->isImplementationContext());
        $this->assertFalse($project->isSpecificationContext());
    }


    /**
     * Test that Project basic details can be manufactured meaningfully
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    public function testBuild()
    {
        $id          = 5567;
        $title       = 'Find Diablo';
        $description = 'The things you need to do in order to slay the prime evil';

        $project = $this->spawnProject($id, $title, $description);

        /* Details
         */
        $this->assertEquals($title, $project->getTitle());
        $this->assertEquals($description, $project->getDescription());

        /* Authored correctly?
         */
        $this->assertEquals($this->somodio->getId(), $project->getCreator()->getId());

        /* Everybody on board?
         */
        $this->assertCount(4, $project->getAllParticipants());

        /** @var User $currentUser */
        foreach ([$this->hodes, $this->bsalem, $this->sberryhill, $this->asaxon] as $currentUser) {
            $this->assertEquals($currentUser->getId(), $project->getParticipant($currentUser)->getUser()->getId());
        }

        /* Dates
         */
        $this->assertEquals($this->tomorrow->getDatabase(), $project->getPublishAt()->getDatabase());
        $this->assertEquals($this->tomorrow->getDatabase(), $project->getOpenAt()->getDatabase());
        $this->assertEquals($this->fiftyDaysFromNow->getDatabase(), $project->getDueAt()->getDatabase());
        $this->assertEquals($this->fiftyDaysFromNow->getDatabase(), $project->getCloseAt()->getDatabase());

        $this->assertEquals($this->today->getDatabase(), $project->getCreatedAt()->getDatabase());
        $this->assertEquals($this->today->getDatabase(), $project->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $project->getDeletedAt());
    }


    /**
     * Spawn a Project composite
     *
     * A giant project instantiation consisting of a due work, task, quiz
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testAssessables()
    {
        $id          = 5567;
        $title       = 'Find Diablo';
        $description = 'The things you need to do in order to slay the prime evil';

        $project = $this->spawnProject($id, $title, $description);

        $task    = $this->spawnTask();
        $dueWork = $this->spawnDueWork();
        $quiz    = $this->spawnQuiz();

        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($task)
            ->add($dueWork)
            ->add($quiz));

        /* There should be three
         */
        $this->assertCount(3, $project->getAssessables());

        /** @var Task $projectTask */
        $projectTask = $project->getAssessables()->getOffset(0);

        /** @var DueWork $projectDueWork */
        $projectDueWork = $project->getAssessables()->getOffset(1);

        /** @var Quiz $projectQuiz */
        $projectQuiz = $project->getAssessables()->getOffset(2);

        /* I should be able to access each one's normalised mark from here
         */
        $this->assertEquals(null, $projectTask->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());
        $this->assertEquals(100, $projectDueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());

        /* Quiz's Normalised Mark is derived. It does not use the getter/setter
         * for Normalised Mark like the other Assessables
         */
        $this->assertNotEquals(83, $projectQuiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());

        /* Test its derived stuff instead
         */
        $this->assertEquals(60, $projectQuiz->getParticipant($this->bsalem)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());
    }

    /**
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function testHasPublishedFeedBack()
    {
        $id          = 5567;
        $title       = 'Find Diablo';
        $description = 'The things you need to do in order to slay the prime evil';

        $project = $this->spawnProject($id, $title, $description);

        $task    = $this->spawnTask(false);
        $dueWork = $this->spawnDueWork(false);
        $quiz    = $this->spawnQuiz(false);

        // hasPublishedFeedback for project returns whether all required feedback has been marked.
        // If there's no feedback required to be marked, it returns true

        // should return true, as there's no assessment
        $project->setSummator(new SummatorAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
        $project->setSummator(new SummatorWeightedAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));

        // adding a task, should still return true, as task doesn't involve at all
        $project->setAssessables((new GenericCollection(IAssessable::class))
            ->add($task));

        $project->setSummator(new SummatorAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
        $project->setSummator(new SummatorWeightedAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));

        // adding a non-weighted quiz, should return false for Average and return true for WeightedAverage
        $project->getAssessables()->add($quiz);
        $project->setSummator(new SummatorAverage());
        $this->assertFalse($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
        $project->setSummator(new SummatorWeightedAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));

        // now mark the quiz and they should now be true
        $this->addFeedbackToQuiz($quiz, $this->hodes);
        $project->setSummator(new SummatorAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
        $project->setSummator(new SummatorWeightedAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));

        // adding a weighted due work, should return false for both Average and WeightedAverage
        $dueWork->setWeight(100);
        $project->getAssessables()->add($dueWork);
        $project->setSummator(new SummatorAverage());
        $this->assertFalse($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
        $project->setSummator(new SummatorWeightedAverage());
        $this->assertFalse($project->hasPublishedFeedback($project->getParticipant($this->hodes)));

        // now mark the quiz and they should now be true
        $this->addFeedbackToDueWork($dueWork, $this->hodes);
        $project->setSummator(new SummatorAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
        $project->setSummator(new SummatorWeightedAverage());
        $this->assertTrue($project->hasPublishedFeedback($project->getParticipant($this->hodes)));
    }

    /**
     * Spawn a Due Work
     * @param bool $withFeedback
     * @return DueWork
     * @throws \UnexpectedValueException
     */
    protected function spawnDueWork(bool $withFeedback = true)
    {

        $id          = 2;
        $title       = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName    = 'Essay';

        $files = [
            [
                'id'       => 1,
                'hash'     => 'abcdefg12341234',
                'filename' => 'myfile1.jpg',
            ],
            [
                'id'       => 2,
                'hash'     => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg',
            ],
        ];

        $submissionFiles = [
            [
                'id'       => 11,
                'hash'     => 's89789d9hw3thsug8923u5',
                'filename' => 'mark this Sir.xlsx',
                'plagscan' => [
                    'id'     => 1234,
                    'status' => 1,
                    'score'  => '93% plagiariased',
                    'html'   => '<div><strong>YOU CHEATED</strong></div>',
                ],
            ],
            [
                'id'       => 23,
                'hash'     => 'lk5j34kl5jousdio',
                'filename' => 'and this too.docx',
            ],
        ];

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAttachedFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId($files[0]['id'])
                    ->setStorage((new Storage())->setHash($files[0]['hash']))
                    ->setFilename($files[0]['filename']))
                ->add((new DueWorkFile())
                    ->setId($files[1]['id'])
                    ->setStorage((new Storage())->setHash($files[1]['hash']))
                    ->setFilename($files[1]['filename'])))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

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
                    ->setFilename($submissionFiles[0]['filename'])
                    ->setPlagscan((new DueWorkSubmissionFilePlagscan())
                        ->setId($submissionFiles[0]['plagscan']['id'])
                        ->setStatus($submissionFiles[0]['plagscan']['status'])
                        ->setScore($submissionFiles[0]['plagscan']['score'])))
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[1]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[1]['hash']))
                    ->setFilename($submissionFiles[1]['filename'])))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission);

        if (!$withFeedback) {
            return $dueWork;
        }
        return $this->addFeedbackToDueWork($dueWork, $this->hodes);
    }

    /**
     * @param DueWork $dueWork
     * @param User    $student
     * @return DueWork
     */
    private function addFeedbackToDueWork(DueWork $dueWork, User $student)
    {

        $feedbackFiles = [
            [
                'id'       => 112,
                'hash'     => 'lj23lj2llj5l2jsdkjf90',
                'filename' => 'This is the correct response.txt',
            ],
        ];

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles((new Collection())
                ->add((new DueWorkFeedbackFile())
                    ->setId($feedbackFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($feedbackFiles[0]['hash']))
                    ->setFilename($feedbackFiles[0]['filename'])))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($student)->getAllFeedbacks()->add($feedback);

        $submission = $dueWork->getParticipant($student)->getAllResponses()->first();
        $submission->getAllFeedback()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setComment('the oldest published stuff')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 22:22:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($student)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4236)
            ->setAssessable($dueWork)
            ->setNormalisedMark(100)
            ->setComment('meow - this is the most recently published feedback, despite the ordering in which this appears')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:43:43', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($student)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(10)
            ->setComment('this is the second most recent')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:23:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:23:21', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($student)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }


    /**
     * Spawn a Task
     *
     * @param bool $withFeedback
     * @return Task
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function spawnTask(bool $withFeedback = true)
    {
        $id          = 1234;
        $title       = 'Read the Horadric Scrolls';
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

        if (!$withFeedback) {
            return $task;
        }
        return $this->addFeedbackToTask($task, $this->hodes);
    }

    /**
     * @param Task $task
     * @param User $student
     * @return Task
     */
    private function addFeedbackToTask(Task $task, User $student)
    {
        $response = new TaskResponse();
        $response
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($student)
            ->setSubmittedOnBehalfOf($student)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new TaskFeedback())
            ->setResponse($response);


        $response->getAllFeedback()->add($feedback);

        $task->getParticipant($student)->getAllResponses()->add($response);
        $task->getParticipant($student)->getAllFeedbacks()->add($feedback);

        return $task;
    }


    /**
     * Spawn a Quiz
     *
     * @param bool $withFeedback
     * @return Quiz
     * @throws \UnexpectedValueException
     */
    protected function spawnQuiz(bool $withFeedback = true)
    {
        $id          = 3;
        $title       = 'How did you find The Prime Evil?';
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

        $plan = $this->getLinker()->createAndLinkPlan($quiz);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        $attempt = new QuizAttempt();
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->bsalem)
            ->setSubmittedOnBehalfOf($this->bsalem)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quiz->getParticipant($this->bsalem)->getAllResponses()->add($attempt);

        $attempt = new QuizAttempt();
        $attempt
            ->setId(8666)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $quiz->getParticipant($this->hodes)->getAllResponses()->add($attempt);

        if (!$withFeedback) {
            return $quiz;
        }
        return $this->addFeedbackToQuiz($quiz, $this->bsalem);
    }

    /**
     * @param Quiz $quiz
     * @param User $student
     * @return Quiz
     */
    private function addFeedbackToQuiz(Quiz $quiz, User $student)
    {

        $feedback = new QuizReview();
        $feedback
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(98)

            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt = $quiz->getParticipant($student)->getAllResponses()->first();
        $attempt->getAllFeedback()->add($feedback);
        $feedback->setResponse($attempt);

        $responses  = [
            [
                'mark'      => 1, // Question 1,
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 2
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 3
                'available' => 5,
            ],
            [
                'mark'      => 2, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven     += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
        $quiz->getParticipant($student)->getAllFeedbacks()->add($feedback);

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

        $responses  = [
            [
                'mark'      => 1, // Question 1,
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 2
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 3
                'available' => 5,
            ],
            [
                'mark'      => 2, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven     += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
        $quiz->getParticipant($student)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99761)
            ->setAssessable($quiz)
            ->setNormalisedMark(1)
            ->setComment('This is the most recent feedback')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_NullDate())
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $feedback->setResponse($attempt);
        $responses  = [
            [
                'mark'      => 1, // Question 1,
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 2
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 3
                'available' => 5,
            ],
            [
                'mark'      => 2, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven     += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);

        $quiz->getParticipant($student)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99762)
            ->setAssessable($quiz)
            ->setNormalisedMark(83)
            ->setComment('This is the most recent published feedback')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-25 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-10 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-10 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $feedback->setResponse($attempt);
        $responses  = [
            [
                'mark'      => 3, // Question 1,
                'available' => 5,
            ],
            [
                'mark'      => 2, // Question 2
                'available' => 5,
            ],
            [
                'mark'      => 2, // Question 3
                'available' => 5,
            ],
            [
                'mark'      => 5, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven     += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);
        $quiz->getParticipant($student)->getAllFeedbacks()->add($feedback);

        $quiz->getParticipant($student)->getAllResponses()->add($attempt);


        return $quiz;
    }

    /**
     * @param int            $id
     * @param string         $title
     * @param string         $description
     * @param ISummator|null $summator
     * @return Project
     */
    public function spawnProject(int $id, string $title, string $description, ?ISummator $summator = null): Project
    {
        $summator = $summator ?? new SummatorAverage();
        $project = new Project($summator);
        $project
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setPublishAt($this->tomorrow)
            ->setOpenAt($this->tomorrow)
            ->setDueAt($this->fiftyDaysFromNow)
            ->setCloseAt($this->fiftyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($project);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);
        return $project;
    }
}
