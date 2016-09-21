<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\MarkScheme;


use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\MarkScheme\Completion;
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
    public function test_getHumanReadable(Task $task, User $student, $expected)
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
    public function test_getHumanReadable_PublishedOnly(Task $task, User $student, $expected)
    {
        // A student-assessed response is always published
        // A teacher-assessed feedback can be unpublished

        $actual = $task->getHumanReadableMark($student, true);
        $message = "User: {$student->getFirstname()}, Expected: $expected, Actual: $actual";
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

        $teacher = $this->getDummyTeacher(5);

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

    private function getDummyStudents()
    {
        return [
            $this->getDummyStudent(1),
            $this->getDummyStudent(2),
            $this->getDummyStudent(3),
            $this->getDummyStudent(4),
        ];
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
                $user->setFirstname('Andrew');
                break;
            case 2:
                $user->setFirstname('Barry');
                break;
            case 3:
                $user->setFirstname('Charlie');
                break;
            case 4:
                $user->setFirstname('David');
                break;
            default:
                throw new InvalidArgumentException("Unknown userId: $userId");
        }
        $this->studentCache[$userId] = $user;
        return $user;
    }

    /**
     * @param int $userId
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
            case 5:
                $user->setFirstname('Ethan');
                break;
            case 6:
                $user->setFirstname('Fred');
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