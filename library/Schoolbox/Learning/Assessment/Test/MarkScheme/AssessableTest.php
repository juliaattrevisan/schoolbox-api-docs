<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\MarkScheme;


use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\User;
use \InvalidArgumentException;

/**
 * Tests IAssessable:
 * * getHumanReadableMark(User $user, bool $publishedOnly):string|null
 * * getNormalisedMark(User $user, bool $publishedOnly):float|null
 *
 * Metrics to test:
 * * IAssessable {Project, Task, Quiz, DueWork}
 * * User activity {None, Response only, Feedback only, Response + Feedback}
 * * publishedOnly {true, false}
 */
class AssessableTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Cache so we can re-use User objects
     *
     * @var User[]
     */
    private $studentCache = [];

    /** @var User[] */
    private $teacherCache = [];


    /**
     * @dataProvider provider_task
     * @param Task $task
     * @param User $student
     * @param string $expected
     */
    public function test_Task_getHumanReadable(Task $task, User $student, $expected)
    {
        $actual = $task->getHumanReadableMark($student, false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected'', Actual: '$actual''";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_task_publishedOnly
     * @param Task $task
     * @param User $student
     * @param string $expected
     */
    public function test_Task_getHumanReadable_PublishedOnly(Task $task, User $student, $expected)
    {
        // A student-assessed response is always published
        // A teacher-assessed feedback can be unpublished

        $actual = $task->getHumanReadableMark($student, true);
        $message = "User: {$student->getFirstname()}, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_quiz
     * @param Quiz $quiz
     * @param User $student
     * @param string $expected
     */
    public function test_Quiz_getHumanReadable(Quiz $quiz, User $student, $expected)
    {
        $actual = $quiz->getHumanReadableMark($student, false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected'', Actual: '$actual''";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_quiz_publishedOnly
     * @param Quiz $quiz
     * @param User $student
     * @param string $expected
     */
    public function test_Quiz_getHumanReadable_PublishedOnly(Quiz $quiz, User $student, $expected)
    {
        $actual = $quiz->getHumanReadableMark($student, true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected'', Actual: '$actual''";
        $this->assertEquals($expected, $actual, $message);
    }


    // ============= Data Providers =============

    public function provider_task()
    {
        $task = $this->getDummyTask();
        return [
            [$task, $this->getDummyStudent(1), Completion::HUMAN_READABLE_INCOMPLETE],
            [$task, $this->getDummyStudent(2), Completion::HUMAN_READABLE_STUDENT_COMPLETED],
            [$task, $this->getDummyStudent(3), Completion::HUMAN_READABLE_TEACHER_COMPLETED],
            [$task, $this->getDummyStudent(4), Completion::HUMAN_READABLE_TEACHER_COMPLETED],
        ];
    }

    public function provider_task_publishedOnly()
    {
        $task = $this->getDummyTask();
        return [
            [$task, $this->getDummyStudent(1), Completion::HUMAN_READABLE_INCOMPLETE],
            [$task, $this->getDummyStudent(2), Completion::HUMAN_READABLE_STUDENT_COMPLETED],
            [$task, $this->getDummyStudent(3), Completion::HUMAN_READABLE_INCOMPLETE],
            [$task, $this->getDummyStudent(4), Completion::HUMAN_READABLE_STUDENT_COMPLETED],
        ];
    }

    public function provider_quiz()
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMarkScheme(new TotalMarkFractional($quiz->getMaxMark()));
        return [
            [$quiz, $this->getDummyStudent(1), null],
            [$quiz, $this->getDummyStudent(2), '1 / 2'],
            [$quiz, $this->getDummyStudent(3), '1 / 2'],
            [$quiz, $this->getDummyStudent(4), '2 / 2'],
            [$quiz, $this->getDummyStudent(5), '2 / 2'],
        ];
    }

    public function provider_quiz_publishedOnly()
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMarkScheme(new TotalMarkFractional($quiz->getMaxMark()));
        return [
            [$quiz, $this->getDummyStudent(1), null],
            [$quiz, $this->getDummyStudent(2), '1 / 2'],
            [$quiz, $this->getDummyStudent(3), null],
            [$quiz, $this->getDummyStudent(4), '1 / 2'],
            [$quiz, $this->getDummyStudent(5), '1 / 3'],
        ];
    }




    /**
     * Generates a task.
     * * user1: none
     * * user2: self-assessed
     * * user3: teacher-assessed
     * * user4: self-assessed then teacher-assessed
     *
     * In all cases, QuizReviews are unpublished
     *
     * @return Task
     */
    private function getDummyTask()
    {
        $task = (new Task())->setId(1)->setMarkScheme(new Completion());

        $student1 = $this->getDummyStudent(1);
        $student2 = $this->getDummyStudent(2);
        $student3 = $this->getDummyStudent(3);
        $student4 = $this->getDummyStudent(4);

        $teacher = $this->getDummyTeacher(101);

        $participant1 = new Participant($student1);
        $participant2 = new Participant($student2);
        $participant3 = new Participant($student3);
        $participant4 = new Participant($student4);

        $task->getAllParticipants()
            ->add($participant1)
            ->add($participant2)
            ->add($participant3)
            ->add($participant4);

        // user1: no Response or Feedback

        // user2: self-assessed
        $response2 = new TaskCompletion();
        $response2->setId(2);
        $response2->setAssessable($task);
        $response2->setSubmittedBy($student2);
        $response2->setSubmittedOnBehalfOf($student2);
        $response2->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $participant2->getAllResponses()->add($response2);
        $task->getResponses()->add($response2);


        // user3: teacher-assessed
        $response3 = new TaskCompletion();
        $response3->setId(3);
        $response3->setAssessable($task);
        $response3->setSubmittedBy($teacher);
        $response3->setSubmittedOnBehalfOf($student3);
        $response3->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $participant3->getAllResponses()->add($response3);
        $task->getResponses()->add($response3);

        $feedback3 = new TaskFeedback();
        $feedback3->setId(3);
        $feedback3->setAssessable($task);
        $feedback3->setResponse($response3);
        $feedback3->setReviewer($teacher);
        $feedback3->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback3->setPublishedAt($this->getDummyDate(null));
        $participant3->getAllFeedbacks()->add($feedback3);
        $task->getFeedbacks()->add($feedback3);
        $response3->getAllFeedback()->add($feedback3);

        // user4: student-assessed then teacher-assessed
        $response4 = new TaskCompletion();
        $response4->setId(4);
        $response4->setAssessable($task);
        $response4->setSubmittedBy($student4);
        $response4->setSubmittedOnBehalfOf($student4);
        $response4->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $participant4->getAllResponses()->add($response4);
        $task->getResponses()->add($response4);

        $feedback4 = new TaskFeedback();
        $feedback4->setId(4);
        $feedback4->setAssessable($task);
        $feedback4->setResponse($response4);
        $feedback4->setReviewer($teacher);
        $feedback4->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback4->setPublishedAt($this->getDummyDate(null));
        $participant4->getAllFeedbacks()->add($feedback4);
        $task->getFeedbacks()->add($feedback4);
        $response4->getAllFeedback()->add($feedback4);

        return $task;
    }

    /**
     * Generate a Quiz with attached Attempts, Feedbacks, + Participants
     * The quiz logically has 1 section with 3 questions worth 1 mark each.
     *
     * Each student attempt was for 2 of the 3 questions
     *
     * * student 1: none
     * * student 2: 1 attempt + 1 published feedback (1/2)
     * * student 3: 1 attempt + 1 unpublished feedback (1/2)
     * * student 4: 2 attempt + 1 published feedback (1/2) + 1 later unpublished feedback (2/2)
     * * student 5: 2 attempt + 1 published feedback (1/3) + 1 later unpublished feedback (2/2)
     *
     * Quiz does not yet have a MarkType or MarkScheme attached, that is caller's responsibility
     *
     * @return Quiz
     */
    private function getDummyQuiz()
    {
        // Create everything
        $quiz = (new Quiz())
            ->setId(1)
            ->setMaxMark(3.0)
            ->setHasFixedTotalMarks(false);

        $student1 = $this->getDummyStudent(1);
        $student2 = $this->getDummyStudent(2);
        $student3 = $this->getDummyStudent(3);
        $student4 = $this->getDummyStudent(4);
        $student5 = $this->getDummyStudent(5);


        $participant1 = new Participant($student1);
        $participant2 = new Participant($student2);
        $participant3 = new Participant($student3);
        $participant4 = new Participant($student4);
        $participant5 = new Participant($student5);

        // student 1: no attempts

        // student 2: 1 attempt + 1 published feedback (1/2)
        $attempt2 = (new QuizAttempt())
            ->setId(2)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(2.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback2 = (new QuizFeedback())
            ->setId(2)
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 3: 1 attempt + 1 unpublished feedback (1/2)
        $attempt3 = (new QuizAttempt())
            ->setId(3)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(2.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback3 = (new QuizFeedback())
            ->setId(3)
            ->setPublishedAt($this->getDummyDate(null))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 4: 2 attempt + 1 published feedback (1/2) + 1 unpublished feedback (2/2)
        $attempt4a = (new QuizAttempt())
            ->setId(4)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(2.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback4a = (new QuizFeedback())
            ->setId(4)
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $attempt4b = (new QuizAttempt())
            ->setId(5)
            ->setMarksGiven(2.0)
            ->setMarksAvailable(2.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback4b = (new QuizFeedback())
            ->setId(5)
            ->setPublishedAt($this->getDummyDate(null))
            ->setUpdatedAt($this->getDummyDate('2016-08-10 21:03:19'));

        // student 5: 2 attempt + 1 published feedback (1/3) + 1 unpublished feedback (2/2)
        $attempt5a = (new QuizAttempt())
            ->setId(6)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(3.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback5a = (new QuizFeedback())
            ->setId(6)
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $attempt5b = (new QuizAttempt())
            ->setId(7)
            ->setMarksGiven(2.0)
            ->setMarksAvailable(2.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback5b = (new QuizFeedback())
            ->setId(7)
            ->setPublishedAt($this->getDummyDate(null))
            ->setUpdatedAt($this->getDummyDate('2016-08-10 21:03:19'));

        // Link them all together

        // Add Participants to Assessable
        $quiz->getAllParticipants()
            ->add($participant1)
            ->add($participant2)
            ->add($participant3)
            ->add($participant4)
            ->add($participant5);

        // Add Responses to Assessable
        $quiz->getResponses()
            ->add($attempt2)
            ->add($attempt3)
            ->add($attempt4a)
            ->add($attempt4b)
            ->add($attempt5a)
            ->add($attempt5b);

        // Add Assessable to Responses
        $attempt2->setAssessable($quiz);
        $attempt3->setAssessable($quiz);
        $attempt4a->setAssessable($quiz);
        $attempt4b->setAssessable($quiz);
        $attempt5a->setAssessable($quiz);
        $attempt5b->setAssessable($quiz);

        // Add Feedback to Assessable
        $quiz->getFeedbacks()
            ->add($feedback2)
            ->add($feedback3)
            ->add($feedback4a)
            ->add($feedback4b)
            ->add($feedback5a)
            ->add($feedback5b);

        // Add Assessable to Feedbacks
        $feedback2->setAssessable($quiz);
        $feedback3->setAssessable($quiz);
        $feedback4a->setAssessable($quiz);
        $feedback4b->setAssessable($quiz);
        $feedback5a->setAssessable($quiz);
        $feedback5b->setAssessable($quiz);

        // Add Responses to Participants
        $participant2->getAllResponses()->add($attempt2);
        $participant3->getAllResponses()->add($attempt3);
        $participant4->getAllResponses()->add($attempt4a)->add($attempt4b);
        $participant5->getAllResponses()->add($attempt5a)->add($attempt5b);

        // Add Feedbacks to Participants
        $participant2->getAllFeedbacks()->add($feedback2);
        $participant3->getAllFeedbacks()->add($feedback3);
        $participant4->getAllFeedbacks()->add($feedback4a)->add($feedback4b);
        $participant5->getAllFeedbacks()->add($feedback5a)->add($feedback5b);

        // Add Responses to Feedbacks
        $feedback2->setResponse($attempt2);
        $feedback3->setResponse($attempt3);
        $feedback4a->setResponse($attempt4a);
        $feedback4b->setResponse($attempt4b);
        $feedback5a->setResponse($attempt5a);
        $feedback5b->setResponse($attempt5b);

        // Add Feedbacks to Responses
        $attempt2->getAllFeedback()->add($feedback2);
        $attempt3->getAllFeedback()->add($feedback3);
        $attempt4a->getAllFeedback()->add($feedback4a);
        $attempt4b->getAllFeedback()->add($feedback4b);
        $attempt5a->getAllFeedback()->add($feedback5a);
        $attempt5b->getAllFeedback()->add($feedback5b);

        return $quiz;
    }


    /**
     * @param int $userId
     * @return User
     */
    private function getDummyStudent($userId)
    {
        // Use cache if possible
        if (isset($this->studentCache[$userId]) && $this->studentCache[$userId] instanceof User) {
            return $this->studentCache[$userId];
        }

        $user = (new User())->setId($userId);
        switch ($userId) {
            case 1:
                $user->setFirstname('Andrew (Student)');
                break;
            case 2:
                $user->setFirstname('Barry (Student)');
                break;
            case 3:
                $user->setFirstname('Charlie (Student)');
                break;
            case 4:
                $user->setFirstname('David (Student)');
                break;
            case 5:
                $user->setFirstname('Ethan (Student)');
                break;
            default:
                throw new InvalidArgumentException("Unknown userId: $userId");
        }
        $this->studentCache[$userId] = $user;
        return $user;
    }

    /**
     * @param int $userId {101, 102}
     * @return User
     */
    private function getDummyTeacher($userId)
    {
        // Use cache if possible
        if (isset($this->teacherCache[$userId]) && $this->teacherCache[$userId] instanceof User) {
            return $this->teacherCache[$userId];
        }

        $user = (new User())->setId($userId);
        switch ($userId) {
            case 101:
                $user->setFirstname('Andy (Teacher)');
                break;
            case 102:
                $user->setFirstname('Boris (Teacher)');
                break;
            default:
                throw new InvalidArgumentException("Unknown userId: $userId");
        }
        $this->teacherCache[$userId] = $user;
        return $user;
    }

    /**
     * @param string $dateString eg '2016-08-08 21:03:19'
     * @return \Alaress_Date_Format
     */
    private function getDummyDate($dateString = null)
    {

        return null === $dateString
            ? new \Alaress_Date_NullDate()
            : new \Alaress_Date_Format($dateString, \Alaress_Date_Format::DATABASE_FORMAT);
    }
}