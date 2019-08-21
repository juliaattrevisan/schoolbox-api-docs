<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Test\ParticipantLinker;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevel;
use Schoolbox\User;
use InvalidArgumentException;
use Alaress_Date_Format;
use Alaress_Date_NullDate;

/**
 * Tests IAssessable:
 * * getHumanReadableMark(Participant $participant, bool $publishedOnly):string|null
 * * getNormalisedMark(Participant $participant, bool $publishedOnly):float|null
 *
 * Metrics to test:
 * * IAssessable {Project, Task, Quiz, DueWork}
 * * User activity {None, Response only, Feedback only, Response + Feedback}
 * * publishedOnly {true, false}
 */
class AssessableTest extends TestCase
{
    public const FAVOR_FLOATING = false;
    /**
     * Cache so we can re-use User objects
     *
     * @var User[]
     */
    private $studentCache = [];

    /** @var User[] */
    private $teacherCache = [];

    /** @var ParticipantLinker */
    private $linker;


    /**
     * @param null|string $name
     * @param array       $data
     * @param string      $dataName
     */
    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->linker = new ParticipantLinker();
    }


    /**
     * @dataProvider provider_task_completion_getHR
     * @param Task $task
     * @param User $student
     * @param string $expected
     */
    public function test_Task_Completion_getHumanReadable(Task $task, User $student, $expected)
    {
        $actual = $task->getHumanReadableMark($task->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_task_completion_getHR_publishedOnly
     * @param Task $task
     * @param User $student
     * @param string $expected
     */
    public function test_Task_Completion_getHumanReadable_PublishedOnly(Task $task, User $student, $expected)
    {
        // A student-assessed response is always published
        // A teacher-assessed feedback can be unpublished

        $actual = $task->getHumanReadableMark($task->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_quiz_totalMarkFraction_getHR
     * @param Quiz $quiz
     * @param User $student
     * @param string $expected
     */
    public function test_Quiz_TotalMarkFraction_getHumanReadable(Quiz $quiz, User $student, $expected)
    {
        $actual = $quiz->getHumanReadableMark($quiz->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_quiz_totalMarkFraction_getHR_publishedOnly
     * @param Quiz $quiz
     * @param User $student
     * @param string $expected
     */
    public function test_Quiz_TotalMarkFraction_getHumanReadable_PublishedOnly(Quiz $quiz, User $student, $expected)
    {
        $actual = $quiz->getHumanReadableMark($quiz->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_quiz_totalMarkFraction_getNorm
     * @param Quiz $quiz
     * @param User $student
     * @param string $expected
     */
    public function test_Quiz_TotalMarkFraction_getNormalised(Quiz $quiz, User $student, $expected)
    {
        $actual = $quiz->getNormalisedMark($quiz->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_quiz_totalMarkFraction_getNorm_publishedOnly
     * @param Quiz $quiz
     * @param User $student
     * @param string $expected
     */
    public function test_Quiz_TotalMarkFraction_getNormalised_PublishedOnly(Quiz $quiz, User $student, $expected)
    {
        $actual = $quiz->getNormalisedMark($quiz->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_None_getHR
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_None_getHumanReadable(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_None_getHR_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_None_getHumanReadable_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_Percent_getHR
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_Percent_getHumanReadable(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_Percent_getHR_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_Percent_getHumanReadable_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_TotalMark_getHR
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_TotalMark_getHumanReadable(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_TotalMark_getHR_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_TotalMark_getHumanReadable_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_LetterGrade_getHR
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_LetterGrade_getHumanReadable(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_LetterGrade_getHR_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_LetterGrade_getHumanReadable_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getHumanReadableMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_None_getNorm
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_None_getNormalised(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_None_getNorm_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_None_getNormalised_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_Percent_getNorm
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_Percent_getNormalised(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_Percent_getNorm_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_Percent_getNormalised_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_TotalMark_getNorm
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_TotalMark_getNormalised(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_TotalMark_getNorm_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_TotalMark_getNormalised_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_LetterGrade_getNorm
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_LetterGrade_getNormalised(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_dueWork_LetterGrade_getNorm_publishedOnly
     * @param DueWork $dueWork
     * @param User $student
     * @param string $expected
     */
    public function test_DueWork_LetterGrade_getNormalised_publishedOnly(DueWork $dueWork, User $student, $expected)
    {
        $actual = $dueWork->getNormalisedMark($dueWork->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @dataProvider provider_project_Min_getNorm
     * @param Project $project
     * @param User    $student
     * @param string  $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_None_getNorm(Project $project, User $student, $expected)
    {
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message, 0.1);
    }


    /**
     * @dataProvider provider_project_Min_getNorm_publishedOnly
     * @param Project $project
     * @param User    $student
     * @param string  $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_None_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message, 0.1);
    }


    /**
     * @dataProvider provider_project_Min_getNorm
     * @param Project $project
     * @param User    $student
     * @param string  $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_LetterGrade_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Min_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_LetterGrade_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Min_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_Percent_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @dataProvider provider_project_Min_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_Percent_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_None_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_None_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_LetterGrade_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_LetterGrade_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_Percent_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_Percent_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_None_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_None_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_LetterGrade_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_LetterGrade_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_Percent_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_Percent_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_None_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_None_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_LetterGrade_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_LetterGrade_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_getNorm
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_Percent_getNorm(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_getNorm_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_Percent_getNorm_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());

        $actual = $project->getNormalisedMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_None_getHR(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Minimum_None_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Min_LetterGrade_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Minimum_LetterGrade_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Min_LetterGrade_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Minimum_LetterGrade_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Min_Percentage_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Minimum_Percentage_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Min_Percentage_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Minimum_Percentage_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_None_getHR(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Maximum_None_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_LetterGrade_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Maximum_LetterGrade_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_LetterGrade_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Maximum_LetterGrade_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_Percentage_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Maximum_Percentage_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Max_Percentage_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Maximum_Percentage_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_None_getHR(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_Average_None_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_LetterGrade_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Average_LetterGrade_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_LetterGrade_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Average_LetterGrade_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_Percent_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Average_Percentage_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_Average_Percent_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_Average_Percentage_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_None_getHR(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_None
     * @param Project $project
     * @param User $student
     * @param string $expected
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function test_Project_WeightedAverage_None_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $project->setMarkScheme(new None());

        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_LetterGrade_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_WeightedAverage_LetterGrade_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_LetterGrade_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_WeightedAverage_LetterGrade_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_Percent_getHR
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_WeightedAverage_Percentage_getHR(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), false);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_project_WeightedAverage_Percent_getHR_publishedOnly
     * @param Project $project
     * @param User $student
     * @param string $expected
     */
    public function test_Project_WeightedAverage_Percentage_getHR_publishedOnly(Project $project, User $student, $expected)
    {
        $actual = $project->getHumanReadableMark($project->getParticipantIfExtant($student), true);
        $message = "User: {$student->getFirstname()}, Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }


    // ============= Data Providers =============


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_task_completion_getHR(): array
    {
        $task = $this->getDummyTask();
        return [
            [$task, $this->getDummyStudent(1), Completion::HUMAN_READABLE_INCOMPLETE],
            [$task, $this->getDummyStudent(2), Completion::HUMAN_READABLE_COMPLETE],
            [$task, $this->getDummyStudent(3), Completion::HUMAN_READABLE_COMPLETE],
            [$task, $this->getDummyStudent(4), Completion::HUMAN_READABLE_COMPLETE],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_task_completion_getHR_publishedOnly(): array
    {
        $task = $this->getDummyTask();
        return [
            [$task, $this->getDummyStudent(1), Completion::HUMAN_READABLE_INCOMPLETE],
            [$task, $this->getDummyStudent(2), Completion::HUMAN_READABLE_COMPLETE],
            [$task, $this->getDummyStudent(3), Completion::HUMAN_READABLE_COMPLETE],
            [$task, $this->getDummyStudent(4), Completion::HUMAN_READABLE_COMPLETE],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_quiz_totalMarkFraction_getHR(): array
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMarkScheme(new TotalMarkFractional());
        if(self::FAVOR_FLOATING){
            return [
                [$quiz, $this->getDummyStudent(1), null],
                [$quiz, $this->getDummyStudent(2), '1 / 2'],
                [$quiz, $this->getDummyStudent(3), '1 / 2'],
                [$quiz, $this->getDummyStudent(4), '2 / 2'],
                [$quiz, $this->getDummyStudent(5), '2 / 2'],
            ];
        }
        return [
            [$quiz, $this->getDummyStudent(1), null],
            [$quiz, $this->getDummyStudent(2), '1 / 2'],
            [$quiz, $this->getDummyStudent(3), '1 / 2'],
            [$quiz, $this->getDummyStudent(4), '2 / 2'],
            [$quiz, $this->getDummyStudent(5), '2 / 2'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_quiz_totalMarkFraction_getHR_publishedOnly(): array
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMarkScheme(new TotalMarkFractional());
        return [
            [$quiz, $this->getDummyStudent(1), null],
            [$quiz, $this->getDummyStudent(2), '1 / 2'],
            [$quiz, $this->getDummyStudent(3), null],
            [$quiz, $this->getDummyStudent(4), '1 / 2'],
            [$quiz, $this->getDummyStudent(5), '1 / 3'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_quiz_totalMarkFraction_getNorm(): array
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMarkScheme(new TotalMarkFractional());

        if(self::FAVOR_FLOATING){
            return [
                [$quiz, $this->getDummyStudent(1), null],
                [$quiz, $this->getDummyStudent(2), 50.0],
                [$quiz, $this->getDummyStudent(3), 50.0],
                [$quiz, $this->getDummyStudent(4), 100.0],
                [$quiz, $this->getDummyStudent(5), 100.0],
            ];
        }
        return [
            [$quiz, $this->getDummyStudent(1), null],
            [$quiz, $this->getDummyStudent(2), 50.0],
            [$quiz, $this->getDummyStudent(3), 50.0],
            [$quiz, $this->getDummyStudent(4), 100.0],
            [$quiz, $this->getDummyStudent(5), 100.0],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_quiz_totalMarkFraction_getNorm_publishedOnly(): array
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMarkScheme(new TotalMarkFractional());
        return [
            [$quiz, $this->getDummyStudent(1), null],
            [$quiz, $this->getDummyStudent(2), 50.0],
            [$quiz, $this->getDummyStudent(3), null],
            [$quiz, $this->getDummyStudent(4), 50.0],
            [$quiz, $this->getDummyStudent(5), 33.3333333333333],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_None_getHR(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $dueWork->setMarkScheme(new TotalMarkFractional($dueWork->getMaxMark()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), '10 / 20'],
            [$dueWork, $this->getDummyStudent(5), '20 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '20 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '20 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_None_getHR_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $dueWork->setMarkScheme(new TotalMarkFractional($dueWork->getMaxMark()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), '10 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '10 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '10 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_Percent_getHR(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $dueWork->setMarkScheme(new Percentage());
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), '10 / 20'],
            [$dueWork, $this->getDummyStudent(5), '20 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '20 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '20 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_Percent_getHR_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $dueWork->setMarkScheme(new Percentage());
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), '10 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '10 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '10 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_TotalMark_getHR(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_TOTAL));
        $dueWork->setMarkScheme(new TotalMarkFractional($dueWork->getMaxMark()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), '10 / 20'],
            [$dueWork, $this->getDummyStudent(5), '20 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '20 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '20 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_TotalMark_getHR_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_TOTAL));
        $dueWork->setMarkScheme(new TotalMarkFractional($dueWork->getMaxMark()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), '10 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '10 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '10 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_LetterGrade_getHR(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $dueWork->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), '10 / 20'],
            [$dueWork, $this->getDummyStudent(5), '20 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '20 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '20 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_LetterGrade_getHR_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $dueWork->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), '10 / 20'],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), '10 / 20'],
            [$dueWork, $this->getDummyStudent(6), '10 / 20'],
            [$dueWork, $this->getDummyStudent(7), '10 / 20'],
            [$dueWork, $this->getDummyStudent(8), '10 / 20'],
            [$dueWork, $this->getDummyStudent(9), '10 / 20'],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_None_getNorm(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $dueWork->setMarkScheme(new None());
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), 50.0],
            [$dueWork, $this->getDummyStudent(5), 100.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 100.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 100.0],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_None_getNorm_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_NONE));
        $dueWork->setMarkScheme(new None());
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), 50.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 50.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 50.0],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_Percent_getNorm(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $dueWork->setMarkScheme(new Percentage());
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), 50.0],
            [$dueWork, $this->getDummyStudent(5), 100.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 100.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_Percent_getNorm_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $dueWork->setMarkScheme(new Percentage());
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), 50.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 50.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 50.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_TotalMark_getNorm(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_TOTAL));
        $dueWork->setMarkScheme(new TotalMarkFractional($dueWork->getMaxMark()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), 50.0],
            [$dueWork, $this->getDummyStudent(5), 100.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 100.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_TotalMark_getNorm_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_TOTAL));
        $dueWork->setMarkScheme(new TotalMarkFractional($dueWork->getMaxMark()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), 50.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 50.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 50.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_LetterGrade_getNorm(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $dueWork->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), 50.0],
            [$dueWork, $this->getDummyStudent(5), 100.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 100.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_dueWork_LetterGrade_getNorm_publishedOnly(): array
    {
        $dueWork = $this->getDummyDueWork();
        $dueWork->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $dueWork->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$dueWork, $this->getDummyStudent(1), null],
            [$dueWork, $this->getDummyStudent(2), null],
            [$dueWork, $this->getDummyStudent(3), 50.0],
            [$dueWork, $this->getDummyStudent(4), null],
            [$dueWork, $this->getDummyStudent(5), 50.0],
            [$dueWork, $this->getDummyStudent(6), 50.0],
            [$dueWork, $this->getDummyStudent(7), 50.0],
            [$dueWork, $this->getDummyStudent(8), 50.0],
            [$dueWork, $this->getDummyStudent(9), 50.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Min_getNorm(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Minimum());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 50.0],
            [$project, $this->getDummyStudent(5), 100],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 100.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Min_getNorm_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Minimum());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 50.0],
            [$project, $this->getDummyStudent(5), 33.333333333333],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 50.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 50.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Max_getNorm(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Maximum());
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), 50.0],
                [$project, $this->getDummyStudent(3), 50.0],
                [$project, $this->getDummyStudent(4), 100.0],
                [$project, $this->getDummyStudent(5), 100.0],
                [$project, $this->getDummyStudent(6), 50.0],
                [$project, $this->getDummyStudent(7), 100.0],
                [$project, $this->getDummyStudent(8), 50.0],
                [$project, $this->getDummyStudent(9), 100.0],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 100.0],
            [$project, $this->getDummyStudent(5), 100.0],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 100.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Max_getNorm_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Maximum());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 50.0],
            [$project, $this->getDummyStudent(5), 50.0],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 50.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 50.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Average_getNorm(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Average());
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), 50.0],
                [$project, $this->getDummyStudent(3), 50.0],
                [$project, $this->getDummyStudent(4), 75.0],
                [$project, $this->getDummyStudent(5), 100.0],
                [$project, $this->getDummyStudent(6), 50.0],
                [$project, $this->getDummyStudent(7), 100.0],
                [$project, $this->getDummyStudent(8), 50.0],
                [$project, $this->getDummyStudent(9), 100.0],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 75.0],
            [$project, $this->getDummyStudent(5), 100.0],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 100.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Average_getNorm_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Average());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 50.0],
            [$project, $this->getDummyStudent(5), 41.6666666666666],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 50.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 50.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_WeightedAverage_getNorm(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new WeightedAverage());
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), 50.0],
                [$project, $this->getDummyStudent(3), 50.0],
                [$project, $this->getDummyStudent(4), 60.0],
                [$project, $this->getDummyStudent(5), 100.0],
                [$project, $this->getDummyStudent(6), 50.0],
                [$project, $this->getDummyStudent(7), 100.0],
                [$project, $this->getDummyStudent(8), 50.0],
                [$project, $this->getDummyStudent(9), 100.0],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 60.0],
            [$project, $this->getDummyStudent(5), 100.0],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 100.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 100.0],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_WeightedAverage_getNorm_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new WeightedAverage());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 50.0],
            [$project, $this->getDummyStudent(3), 50.0],
            [$project, $this->getDummyStudent(4), 50.0],
            [$project, $this->getDummyStudent(5), 46.666666666666666],
            [$project, $this->getDummyStudent(6), 50.0],
            [$project, $this->getDummyStudent(7), 50.0],
            [$project, $this->getDummyStudent(8), 50.0],
            [$project, $this->getDummyStudent(9), 50.0],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_None(): array
    {
        $project = $this->getDummyProject();
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), null],
            [$project, $this->getDummyStudent(3), null],
            [$project, $this->getDummyStudent(4), null],
            [$project, $this->getDummyStudent(5), null],
            [$project, $this->getDummyStudent(6), null],
            [$project, $this->getDummyStudent(7), null],
            [$project, $this->getDummyStudent(8), null],
            [$project, $this->getDummyStudent(9), null],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Min_LetterGrade_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Acceptable'],
            [$project, $this->getDummyStudent(5), 'Excellent'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Excellent'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Excellent'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Min_LetterGrade_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Acceptable'],
            [$project, $this->getDummyStudent(5), 'Needs Attention'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Acceptable'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Acceptable'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Min_Percentage_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '50 %'],
            [$project, $this->getDummyStudent(5), '100 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '100 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '100 %'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Min_Percentage_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Minimum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '50 %'],
            [$project, $this->getDummyStudent(5), '33.33 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '50 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '50 %'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Max_LetterGrade_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), 'Acceptable'],
                [$project, $this->getDummyStudent(3), 'Acceptable'],
                [$project, $this->getDummyStudent(4), 'Excellent'],
                [$project, $this->getDummyStudent(5), 'Excellent'],
                [$project, $this->getDummyStudent(6), 'Acceptable'],
                [$project, $this->getDummyStudent(7), 'Excellent'],
                [$project, $this->getDummyStudent(8), 'Acceptable'],
                [$project, $this->getDummyStudent(9), 'Excellent'],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Excellent'],
            [$project, $this->getDummyStudent(5), 'Excellent'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Excellent'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Excellent'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Max_LetterGrade_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Acceptable'],
            [$project, $this->getDummyStudent(5), 'Acceptable'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Acceptable'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Acceptable'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Max_Percentage_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), '50 %'],
                [$project, $this->getDummyStudent(3), '50 %'],
                [$project, $this->getDummyStudent(4), '100 %'],
                [$project, $this->getDummyStudent(5), '100 %'],
                [$project, $this->getDummyStudent(6), '50 %'],
                [$project, $this->getDummyStudent(7), '100 %'],
                [$project, $this->getDummyStudent(8), '50 %'],
                [$project, $this->getDummyStudent(9), '100 %'],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '100 %'],
            [$project, $this->getDummyStudent(5), '100 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '100 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '100 %'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Max_Percentage_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Maximum());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '50 %'],
            [$project, $this->getDummyStudent(5), '50 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '50 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '50 %'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Average_LetterGrade_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), 'Acceptable'],
                [$project, $this->getDummyStudent(3), 'Acceptable'],
                [$project, $this->getDummyStudent(4), 'Very Good'],
                [$project, $this->getDummyStudent(5), 'Excellent'],
                [$project, $this->getDummyStudent(6), 'Acceptable'],
                [$project, $this->getDummyStudent(7), 'Excellent'],
                [$project, $this->getDummyStudent(8), 'Acceptable'],
                [$project, $this->getDummyStudent(9), 'Excellent'],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Very Good'],
            [$project, $this->getDummyStudent(5), 'Excellent'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Excellent'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Excellent'],
        ];
     }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Average_LetterGrade_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Acceptable'],
            [$project, $this->getDummyStudent(5), 'Acceptable'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Acceptable'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Acceptable'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Average_Percent_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), '50 %'],
                [$project, $this->getDummyStudent(3), '50 %'],
                [$project, $this->getDummyStudent(4), '75 %'],
                [$project, $this->getDummyStudent(5), '100 %'],
                [$project, $this->getDummyStudent(6), '50 %'],
                [$project, $this->getDummyStudent(7), '100 %'],
                [$project, $this->getDummyStudent(8), '50 %'],
                [$project, $this->getDummyStudent(9), '100 %'],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '75 %'],
            [$project, $this->getDummyStudent(5), '100 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '100 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '100 %'],
        ];
     }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_Average_Percent_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new Average());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '50 %'],
            [$project, $this->getDummyStudent(5), '41.67 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '50 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '50 %'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_WeightedAverage_LetterGrade_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), 'Acceptable'],
                [$project, $this->getDummyStudent(3), 'Acceptable'],
                [$project, $this->getDummyStudent(4), 'Good'],
                [$project, $this->getDummyStudent(5), 'Excellent'],
                [$project, $this->getDummyStudent(6), 'Acceptable'],
                [$project, $this->getDummyStudent(7), 'Excellent'],
                [$project, $this->getDummyStudent(8), 'Acceptable'],
                [$project, $this->getDummyStudent(9), 'Excellent'],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Good'],
            [$project, $this->getDummyStudent(5), 'Excellent'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Excellent'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Excellent'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_WeightedAverage_LetterGrade_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_LETTER));
        $project->setMarkScheme(new LetterGrade($this->getDummyLetterGradeGroup()));
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), 'Acceptable'],
            [$project, $this->getDummyStudent(3), 'Acceptable'],
            [$project, $this->getDummyStudent(4), 'Acceptable'],
            [$project, $this->getDummyStudent(5), 'Acceptable'],
            [$project, $this->getDummyStudent(6), 'Acceptable'],
            [$project, $this->getDummyStudent(7), 'Acceptable'],
            [$project, $this->getDummyStudent(8), 'Acceptable'],
            [$project, $this->getDummyStudent(9), 'Acceptable'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_WeightedAverage_Percent_getHR(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        if(self::FAVOR_FLOATING){
            return [
                [$project, $this->getDummyStudent(1), null],
                [$project, $this->getDummyStudent(2), '50 %'],
                [$project, $this->getDummyStudent(3), '50 %'],
                [$project, $this->getDummyStudent(4), '60 %'],
                [$project, $this->getDummyStudent(5), '100 %'],
                [$project, $this->getDummyStudent(6), '50 %'],
                [$project, $this->getDummyStudent(7), '100 %'],
                [$project, $this->getDummyStudent(8), '50 %'],
                [$project, $this->getDummyStudent(9), '100 %'],
            ];
        }
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '60 %'],
            [$project, $this->getDummyStudent(5), '100 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '100 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '100 %'],
        ];
    }

    /**
     * @return array
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function provider_project_WeightedAverage_Percent_getHR_publishedOnly(): array
    {
        $project = $this->getDummyProject();
        $project->setSummator(new WeightedAverage());
        $project->setMarkType($this->getDummyMarkType(MarkType::SLUG_PERCENT));
        $project->setMarkScheme(new Percentage());
        return [
            [$project, $this->getDummyStudent(1), null],
            [$project, $this->getDummyStudent(2), '50 %'],
            [$project, $this->getDummyStudent(3), '50 %'],
            [$project, $this->getDummyStudent(4), '50 %'],
            [$project, $this->getDummyStudent(5), '46.67 %'],
            [$project, $this->getDummyStudent(6), '50 %'],
            [$project, $this->getDummyStudent(7), '50 %'],
            [$project, $this->getDummyStudent(8), '50 %'],
            [$project, $this->getDummyStudent(9), '50 %'],
        ];
    }


    /**
     * Generates a task.
     * * student 1: none
     * * student 2: self-assessed as complete
     * * student 3: teacher-assessed as complete
     * * student 4: self-assessed as complete then teacher-assessed as complete
     *
     * In all cases, QuizReviews are unpublished
     *
     * @return Task
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    private function getDummyTask(): Task
    {
        $task = (new Task())
            ->setId(1)
            ->setIsSelfAssessable(true)
            ->setMarkScheme(new Completion());

        $student1 = $this->getDummyStudent(1);
        $student2 = $this->getDummyStudent(2);
        $student3 = $this->getDummyStudent(3);
        $student4 = $this->getDummyStudent(4);

        $teacher = $this->getDummyTeacher(101);

        $plan = $this->linker->createAndLinkPlan($task);
        $participant1 = $this->getDummyParticipant($student1->getId(), $plan);
        $participant2 = $this->getDummyParticipant($student2->getId(), $plan);
        $participant3 = $this->getDummyParticipant($student3->getId(), $plan);
        $participant4 = $this->getDummyParticipant($student4->getId(), $plan);

        $task->getAllParticipants()
            ->add($participant1)
            ->add($participant2)
            ->add($participant3)
            ->add($participant4);

        // user1: no Response or Feedback

        // user2: self-assessed
        $response2 = new TaskResponse();
        $response2->setId(2);
        $response2->setAssessable($task);
        $response2->setSubmittedBy($student2);
        $response2->setSubmittedOnBehalfOf($student2);
        $response2->setMarkedComplete(true);
        $response2->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $participant2->getAllResponses()->add($response2);


        // user3: teacher-assessed
        $response3 = new TaskResponse();
        $response3->setId(3);
        $response3->setAssessable($task);
        $response3->setSubmittedBy($teacher);
        $response3->setSubmittedOnBehalfOf($student3);
        $response3->setMarkedComplete(true);
        $response3->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $participant3->getAllResponses()->add($response3);

        $feedback3 = (new TaskFeedback())
            ->setResponse($response3);
        $participant3->getAllFeedbacks()->add($feedback3);
        $response3->getAllFeedback()->add($feedback3);

        // user4: student-assessed then teacher-assessed
        $response4a = new TaskResponse();
        $response4a->setId(4);
        $response4a->setAssessable($task);
        $response4a->setSubmittedBy($student4);
        $response4a->setSubmittedOnBehalfOf($student4);
        $response4a->setMarkedComplete(true);
        $response4a->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $participant4->getAllResponses()->add($response4a);

        $response4b = new TaskResponse();
        $response4b->setId(4);
        $response4b->setAssessable($task);
        $response4b->setSubmittedBy($student4);
        $response4b->setSubmittedOnBehalfOf($teacher);
        $response4b->setMarkedComplete(true);
        $response4b->setUpdatedAt($this->getDummyDate('2016-10-10 21:03:19'));
        $participant4->getAllResponses()->add($response4b);

        $feedback4b = (new TaskFeedback())
            ->setResponse($response4b);
        $participant4->getAllFeedbacks()->add($feedback4b);
        $response4b->getAllFeedback()->add($feedback4b);

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
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    private function getDummyQuiz(): Quiz
    {
        // Create everything
        $quiz = (new Quiz())
            ->setId(2)
        ;

        $student1 = $this->getDummyStudent(1);
        $student2 = $this->getDummyStudent(2);
        $student3 = $this->getDummyStudent(3);
        $student4 = $this->getDummyStudent(4);
        $student5 = $this->getDummyStudent(5);

        $plan = $this->linker->createAndLinkPlan($quiz);
        $participant1 = $this->getDummyParticipant($student1->getId(), $plan);
        $participant2 = $this->getDummyParticipant($student2->getId(), $plan);
        $participant3 = $this->getDummyParticipant($student3->getId(), $plan);
        $participant4 = $this->getDummyParticipant($student4->getId(), $plan);
        $participant5 = $this->getDummyParticipant($student5->getId(), $plan);

        // student 1: no attempts

        // student 2: 1 attempt + 1 published feedback (1/2)
        $attempt2 = (new QuizAttempt())
            ->setId(2)
            ->setSubmittedOnBehalfOf($student2)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(2.0)
            ->setSubmittedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback2 = (new QuizFeedback())
            ->setId(2)
            ->setReviewee($student2)
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 3: 1 attempt + 1 unpublished feedback (1/2)
        $attempt3 = (new QuizAttempt())
            ->setId(3)
            ->setSubmittedOnBehalfOf($student3)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(2.0)
            ->setSubmittedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback3 = (new QuizFeedback())
            ->setId(3)
            ->setReviewee($student3)
            ->setPublishedAt($this->getDummyDate(null))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 4: 2 attempt + 1 published feedback (1/2) + 1 unpublished feedback (2/2)
        $attempt4a = (new QuizAttempt())
            ->setId(4)
            ->setSubmittedOnBehalfOf($student4)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(2.0)
            ->setSubmittedAt($this->getDummyDate('2016-08-08 00:00:00'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 00:00:00'));
        $feedback4a = (new QuizFeedback())
            ->setId(4)
            ->setReviewee($student4)
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-10 21:03:19'));

        $attempt4b = (new QuizAttempt())
            ->setId(5)
            ->setSubmittedOnBehalfOf($student4)
            ->setMarksGiven(2.0)
            ->setMarksAvailable(2.0)
            ->setSubmittedAt($this->getDummyDate('2016-08-09 00:00:00'))
            ->setUpdatedAt($this->getDummyDate('2016-08-09 00:00:00'));
        $feedback4b = (new QuizFeedback())
            ->setId(5)
            ->setReviewee($student4)
            ->setPublishedAt($this->getDummyDate(null))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 5: 2 attempt + 1 published feedback (1/3) + 1 unpublished feedback (2/2)
        $attempt5a = (new QuizAttempt())
            ->setId(6)
            ->setSubmittedOnBehalfOf($student5)
            ->setMarksGiven(1.0)
            ->setMarksAvailable(3.0)
            ->setSubmittedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback5a = (new QuizFeedback())
            ->setId(6)
            ->setReviewee($student5)
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        $attempt5b = (new QuizAttempt())
            ->setId(7)
            ->setSubmittedOnBehalfOf($student5)
            ->setMarksGiven(2.0)
            ->setMarksAvailable(2.0)
            ->setSubmittedAt($this->getDummyDate('2016-08-09 21:03:19'))
            ->setUpdatedAt($this->getDummyDate('2016-08-09 21:03:19'));
        $feedback5b = (new QuizFeedback())
            ->setId(7)
            ->setReviewee($student5)
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

        // Add Assessable to Responses
        $attempt2->setAssessable($quiz);
        $attempt3->setAssessable($quiz);
        $attempt4a->setAssessable($quiz);
        $attempt4b->setAssessable($quiz);
        $attempt5a->setAssessable($quiz);
        $attempt5b->setAssessable($quiz);

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
     * Generate a DueWork with attached Attempts, Feedbacks, + Participants
     *
     * DueWork has Total Marks of 20 and a 'Competency' Letter Grade Group {'Needs Attention' ... 'Excellent'}
     *
     * DueWork does not yet have a MarkType or MarkScheme attached, that is caller's responsibility
     *
     * All Feedbacks have HumanReadable cached in the TotalMarkWhole format, eg '10 / 20'
     *
     * * student 1: none
     * * student 2: 1 submission
     * * student 3: 1 submission + 1 published feedback (50.0)
     * * student 4: 1 submission + 1 unpublished feedback (50.0)
     * * student 5: 1 submission + 1 published feedback (50.0) + 1 later unpublished feedback (100.0)
     * * student 6: 2 submission + 1 published floating feedback (50.0)
     * * student 7: 2 submission + 1 published floating feedback (50.0) + 1 later unpublished floating feedback (100.0)
     * * student 8: 0 submission + 1 published floating feedback (50.0)
     * * student 9: 0 submission + 1 published floating feedback (50.0) + 1 later unpublished floating feedback (100.0)
     *
     * @return DueWork
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    private function getDummyDueWork(): DueWork
    {
        // Create everything
        $dueWork = (new DueWork())
            ->setId(3)
            ->setMaxMark(20)
            ->setGradeGroup($this->getDummyLetterGradeGroup())
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

        $student1 = $this->getDummyStudent(1);
        $student2 = $this->getDummyStudent(2);
        $student3 = $this->getDummyStudent(3);
        $student4 = $this->getDummyStudent(4);
        $student5 = $this->getDummyStudent(5);
        $student6 = $this->getDummyStudent(6);
        $student7 = $this->getDummyStudent(7);
        $student8 = $this->getDummyStudent(8);
        $student9 = $this->getDummyStudent(9);

        $plan = $this->linker->createAndLinkPlan($dueWork);
        $participant1 = $this->getDummyParticipant($student1->getId(), $plan);
        $participant2 = $this->getDummyParticipant($student2->getId(), $plan);
        $participant3 = $this->getDummyParticipant($student3->getId(), $plan);
        $participant4 = $this->getDummyParticipant($student4->getId(), $plan);
        $participant5 = $this->getDummyParticipant($student5->getId(), $plan);
        $participant6 = $this->getDummyParticipant($student6->getId(), $plan);
        $participant7 = $this->getDummyParticipant($student7->getId(), $plan);
        $participant8 = $this->getDummyParticipant($student8->getId(), $plan);
        $participant9 = $this->getDummyParticipant($student9->getId(), $plan);

        // student 1: no attempts

        // student 2: 1 submission
        $submission2 = (new DueWorkSubmission())
            ->setId(2)
            ->setSubmittedOnBehalfOf($student2)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 3: 1 submission + 1 published feedback (50.0)
        $submission3 = (new DueWorkSubmission())
            ->setId(3)
            ->setSubmittedOnBehalfOf($student3)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        $feedback3 = (new DueWorkFeedback())
            ->setId(3)
            ->setReviewee($student3)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 4: 1 submission + 1 unpublished feedback (50.0)
        $submission4 = (new DueWorkSubmission())
            ->setId(4)
            ->setSubmittedOnBehalfOf($student4)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        $feedback4 = (new DueWorkFeedback())
            ->setId(4)
            ->setReviewee($student4)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate(null));

        // student 5: 1 submission + 1 published feedback (50.0) + 1 later unpublished feedback (100.0)
        $submission5 = (new DueWorkSubmission())
            ->setId(5)
            ->setSubmittedOnBehalfOf($student5)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        $feedback5a = (new DueWorkFeedback())
            ->setId(5)
            ->setReviewee($student5)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback5b = (new DueWorkFeedback())
            ->setId(6)
            ->setReviewee($student5)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('20 / 20')
            ->setNormalisedMark(100.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-10 21:03:19'))
            ->setPublishedAt($this->getDummyDate(null));

        // student 6: 2 submission + 1 published floating feedback (50.0)
        $submission6a = (new DueWorkSubmission())
            ->setId(5)
            ->setSubmittedOnBehalfOf($student6)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $submission6b = (new DueWorkSubmission())
            ->setId(6)
            ->setSubmittedOnBehalfOf($student6)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        $feedback6 = (new DueWorkFeedback())
            ->setId(7)
            ->setReviewee($student6)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 7: 2 submission + 1 published floating feedback (50.0) + 1 later unpublished floating feedback (100.0)
        $submission7a = (new DueWorkSubmission())
            ->setId(7)
            ->setSubmittedOnBehalfOf($student7)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $submission7b = (new DueWorkSubmission())
            ->setId(8)
            ->setSubmittedOnBehalfOf($student7)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'));

        $feedback7a = (new DueWorkFeedback())
            ->setId(8)
            ->setReviewee($student7)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback7b = (new DueWorkFeedback())
            ->setId(9)
            ->setReviewee($student7)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('20 / 20')
            ->setNormalisedMark(100.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-10 21:03:19'))
            ->setPublishedAt($this->getDummyDate(null));

        // student 8: 0 submission + 1 published floating feedback (50.0)
        $feedback8 = (new DueWorkFeedback())
            ->setId(10)
            ->setReviewee($student8)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'));

        // student 9: 0 submission + 1 published floating feedback (50.0) + 1 later unpublished floating feedback (100.0)
        $feedback9a = (new DueWorkFeedback())
            ->setId(11)
            ->setReviewee($student9)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('10 / 20')
            ->setNormalisedMark(50.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-08 21:03:19'))
            ->setPublishedAt($this->getDummyDate('2016-08-08 21:03:19'));
        $feedback9b = (new DueWorkFeedback())
            ->setId(12)
            ->setReviewee($student9)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setHumanReadableMark('20 / 20')
            ->setNormalisedMark(100.0)
            ->setUpdatedAt($this->getDummyDate('2016-08-10 21:03:19'))
            ->setPublishedAt($this->getDummyDate(null));

        // Link them all together

        // Add Participants to Assessable
        $dueWork->getAllParticipants()
            ->add($participant1)
            ->add($participant2)
            ->add($participant3)
            ->add($participant4)
            ->add($participant5)
            ->add($participant6)
            ->add($participant7)
            ->add($participant8)
            ->add($participant9);

        // Add Assessable to Responses
        $submission2->setAssessable($dueWork);
        $submission3->setAssessable($dueWork);
        $submission4->setAssessable($dueWork);
        $submission5->setAssessable($dueWork);
        $submission6a->setAssessable($dueWork);
        $submission6b->setAssessable($dueWork);
        $submission7a->setAssessable($dueWork);
        $submission7b->setAssessable($dueWork);

        // Add Assessable to Feedbacks
        $feedback3->setAssessable($dueWork);
        $feedback4->setAssessable($dueWork);
        $feedback5a->setAssessable($dueWork);
        $feedback5b->setAssessable($dueWork);
        $feedback6->setAssessable($dueWork);
        $feedback7a->setAssessable($dueWork);
        $feedback7b->setAssessable($dueWork);
        $feedback8->setAssessable($dueWork);
        $feedback9a->setAssessable($dueWork);
        $feedback9b->setAssessable($dueWork);

        // Add Responses to Participants
        $participant2->getAllResponses()->add($submission2);
        $participant3->getAllResponses()->add($submission3);
        $participant4->getAllResponses()->add($submission4);
        $participant5->getAllResponses()->add($submission5);
        $participant6->getAllResponses()->add($submission6a)->add($submission6b);
        $participant7->getAllResponses()->add($submission7a)->add($submission7b);


        // Add Feedbacks to Participants
        $participant3->getAllFeedbacks()->add($feedback3);
        $participant4->getAllFeedbacks()->add($feedback4);
        $participant5->getAllFeedbacks()->add($feedback5a)->add($feedback5b);
        $participant6->getAllFeedbacks()->add($feedback6);
        $participant7->getAllFeedbacks()->add($feedback7a)->add($feedback7b);
        $participant8->getAllFeedbacks()->add($feedback8);
        $participant9->getAllFeedbacks()->add($feedback9a)->add($feedback9b);

        // Add Responses to Feedbacks
        $feedback3->setResponse($submission3);
        $feedback4->setResponse($submission4);
        $feedback5a->setResponse($submission5);
        $feedback5b->setResponse($submission5);

        // Add Feedbacks to Responses
        $submission3->getAllFeedback()->add($feedback3);
        $submission4->getAllFeedback()->add($feedback4);
        $submission5->getAllFeedback()->add($feedback5a)->add($feedback5b);

        return $dueWork;
    }


    /**
     * Returns a project composed of 1 Task (weight=0), 1 Quiz (weight=1) + 1 DueWork (weight=4)
     *
     * student 1: Task: -
     *            Quiz: -
     *            DueW: -
     * student 2: Task: self-assessed
     *            Quiz: 1 attempt + 1 published feedback (1/2)
     *            DueW: 1 submission
     * student 3: Task: teacher-assessed
     *            Quiz: 1 attempt + 1 unpublished feedback (1/2)
     *            DueW: 1 submission + 1 published feedback (50.0)
     * student 4: Task: self-assessed then teacher-assessed
     *            Quiz: 2 attempt + 1 published feedback (1/2) + 1 later unpublished feedback (2/2)
     *            DueW: 1 submission + 1 unpublished feedback (50.0)
     * student 5: Task: -
     *            Quiz: 2 attempt + 1 published feedback (1/3) + 1 later unpublished feedback (2/2)
     *            DueW: 1 submission + 1 published feedback (50.0) + 1 later unpublished feedback (100.0)
     * student 6: Task: -
     *            Quiz: -
     *            DueW: 2 submission + 1 published floating feedback (50.0)
     * student 7: Task: -
     *            Quiz: -
     *            DueW: 2 submission + 1 published floating feedback (50.0) + 1 later unpublished floating feedback (100.0)
     * student 8: Task: -
     *            Quiz: -
     *            DueW: 0 submission + 1 published floating feedback (50.0)
     * student 9: Task: -
     *            Quiz: -
     *            DueW: 0 submission + 1 published floating feedback (50.0) + 1 later unpublished floating feedback (100.0)
     *
     * @return Project
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    public function getDummyProject(): Project
    {
        $project = (new Project())
            ->setId(4)
            ->setGradeGroup($this->getDummyLetterGradeGroup());

        $task = $this->getDummyTask()->setWeight(0);
        $dueWork = $this->getDummyDueWork()->setWeight(4);
        $quiz = $this->getDummyQuiz()->setWeight(1);

        $project->getAssessables()
            ->add($task)
            ->add($dueWork)
            ->add($quiz);

        $plan = $this->linker->createAndLinkPlan($project);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(1), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(2), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(3), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(4), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(5), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(6), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(7), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(8), $plan);
        $this->linker->createAndLinkParticipant($this->getDummyStudent(9), $plan);

        return $project;
    }


    /**
     * @param string $markSlug
     * @return MarkType
     * @throws InvalidArgumentException If an unrecognised mark slug is passed
     */
    private function getDummyMarkType(string $markSlug): MarkType
    {
        $data = [
            [MarkType::SLUG_PERCENT, 'Percentage (%)', 1, 'This should be a whole percentage between 0 and 100.', 1],
            [MarkType::SLUG_LETTER, 'Letter Grade', 2, 'Se1ect a letter grade from the list.', 1],
            [MarkType::SLUG_TOTAL, 'Total Mark', 3, 'This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.', 1],
            [MarkType::SLUG_FREE, 'Free Type (legacy support)', 4, 'Should be a letter (A), score (15/20), or percentage (88%).', 0],
            [MarkType::SLUG_RUBRIC, 'Rubric', 5, 'To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.', 0],
            [MarkType::SLUG_NONE, 'None', 6, 'This due work is not marked.', 1],
            [MarkType::SLUG_COMPLETION, 'Completion', 7, 'Either \'Completed\' or \'Not Completed\'', 0],
        ];

        foreach ($data as $row) {
            if ($row[0] === $markSlug) {
                return (new MarkType())
                    ->setId($row[2])
                    ->setName($row[1])
                    ->setSlug($row[1])
                    ->setComment($row[3])
                    ->setEnabled($row[4]);
            }
        }
        throw new InvalidArgumentException("Unknown markType slug: $markSlug");
    }


    /**
     * @return GradeGroup
     */
    private function getDummyLetterGradeGroup(): GradeGroup
    {
        $data = [
            [
                'id' => 3,
                'name' => 'Competency',
                'grades' => [
                    [
                        'grade_id' => 13,
                        'lower_bound' => 0,
                        'raw_grade' => 20,
                        'caption' => 'Needs Attention',
                    ],
                    [
                        'grade_id' => 14,
                        'lower_bound' => 40,
                        'raw_grade' => 53,
                        'caption' => 'Acceptable',
                    ],
                    [
                        'grade_id' => 15,
                        'lower_bound' => 60,
                        'raw_grade' => 68,
                        'caption' => 'Good',
                    ],
                    [
                        'grade_id' => 16,
                        'lower_bound' => 75,
                        'raw_grade' => 88,
                        'caption' => 'Very Good',
                    ],
                    [
                        'grade_id' => 17,
                        'lower_bound' => 90,
                        'raw_grade' => 95,
                        'caption' => 'Excellent',
                    ],
                ]
            ]
        ];
        $gradeGroup = (new GradeGroup())
            ->setId($data[0]['id'])
            ->setName($data[0]['name'])
            ->setGradeLevels(new GenericCollection(GradeLevel::class));

        foreach ($data[0]['grades'] as $gradeLevelArr) {
            $gradeLevel = (new GradeLevel())
                ->setGroup($gradeGroup)
                ->setId($gradeLevelArr['grade_id'])
                ->setLowerBound($gradeLevelArr['lower_bound'])
                ->setRawGrade($gradeLevelArr['raw_grade'])
                ->setCaption($gradeLevelArr['caption']);
            $gradeGroup->getGradeLevels()->add($gradeLevel);
        }
        return $gradeGroup;
    }


    /**
     * @param int $userId
     * @param Plan $plan
     * @return Participant
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    private function getDummyParticipant($userId, Plan $plan): Participant
    {
        return $this->linker->createAndLinkParticipant($this->getDummyStudent($userId), $plan);
    }


    /**
     * @param int $userId
     * @return User
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    private function getDummyStudent($userId): User
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
            case 6:
                $user->setFirstname('Fred (Student)');
                break;
            case 7:
                $user->setFirstname('Gary (Student)');
                break;
            case 8:
                $user->setFirstname('Harry (Student)');
                break;
            case 9:
                $user->setFirstname('Issac (Student)');
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
     * @throws InvalidArgumentException If an unrecognised user.id is passed
     */
    private function getDummyTeacher($userId): User
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
            ? new Alaress_Date_NullDate()
            : new Alaress_Date_Format($dateString, Alaress_Date_Format::DATABASE_FORMAT);
    }
}
