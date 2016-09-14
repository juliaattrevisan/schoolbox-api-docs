<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use \InvalidArgumentException;
use \DomainException;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider_task_markTypeInvalid
     * @param Task $task
     * @expectedException DomainException
     */
    public function test_task_markTypeInvalid(Task $task)
    {
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($task);
    }

    /**
     * @dataProvider provider_task_markTypeValid
     * @param Task $task
     * @param string $expectedMarkScheme
     */
    public function test_task_markTypeValid(Task $task, $expectedMarkScheme)
    {
        $factory = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($task);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }

    /**
     * @dataProvider provider_quiz_markTypeInvalid
     * @param Quiz $quiz
     * @expectedException DomainException
     */
    public function test_quiz_markTypeInvalid(Quiz $quiz)
    {
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($quiz);
    }

    /**
     * @dataProvider provider_quiz_markTypeValid
     * @param Quiz $quiz
     * @param string $expectedMarkScheme
     */
    public function test_quiz_markTypeValid(Quiz $quiz, $expectedMarkScheme)
    {
        $factory = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($quiz);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }

    /**
     * @dataProvider provider_dueWOrk_markTypeInvalid
     * @param DueWork $dueWork
     * @expectedException DomainException
     */
    public function test_dueWork_markTypeInvalid(DueWork $dueWork)
    {
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($dueWork);
    }

    /**
     * @dataProvider provider_dueWork_markTypeValid
     * @param DueWork $dueWork
     * @param string $expectedMarkScheme
     */
    public function test_dueWork_markTypeValid(DueWork $dueWork, $expectedMarkScheme)
    {
        $factory = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($dueWork);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }

    /**
     * @dataProvider provider_project_markTypeInvalid
     * @param Project $project
     * @expectedException DomainException
     */
    public function test_project_markTypeInvalid($project)
    {
        $factory = new MarkSchemeFactory();
        $factory->spawnMarkSchemeFor($project);
    }

    /**
     * @dataProvider provider_project_markTypeValid
     * @param Project $project
     * @param string $expectedMarkScheme
     */
    public function test_project_markTypeValid($project, $expectedMarkScheme)
    {
        $factory = new MarkSchemeFactory();
        $markScheme = $factory->spawnMarkSchemeFor($project);
        $this->assertInstanceOf($expectedMarkScheme, $markScheme);
    }

    // ==================== Data Providers ====================

    /**
     * @return Task[][]
     */
    public function provider_task_markTypeInvalid()
    {
        return [
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE))],
        ];
    }

    /**
     * @return Task[][]
     */
    public function provider_task_markTypeValid()
    {
        return [
            [(new Task())->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION)), Completion::class],
        ];
    }

    /**
     * @return Quiz[][]
     */
    public function provider_quiz_markTypeInvalid()
    {
        return [
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT))],
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER))],
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE))],
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION))],
        ];
    }

    /**
     * @return Quiz[][]
     */
    public function provider_quiz_markTypeValid()
    {
        return [
            [(new Quiz())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL)), TotalMarkFractional::class],
        ];
    }

    /**
     * @return DueWork[][]
     */
    public function provider_dueWork_markTypeInvalid()
    {
        return [
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION))],
        ];
    }

    /**
     * @return DueWork[][]
     */
    public function provider_dueWork_markTypeValid()
    {
        return [
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT)), Percentage::class],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER))->setGradeGroup($this->getDummyLetterGradeGroup()), LetterGrade::class],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))->setMaxMark(50), TotalMarkWholeNumber::class],
            [(new DueWork())->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE)), None::class],
        ];
    }

    /**
     * @return Project[][]
     */
    public function provider_project_markTypeInvalid()
    {
        return [
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_TOTAL))],
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_FREE))],
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_RUBRIC))],
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_COMPLETION))],
        ];
    }

    /**
     * @return Project[][]
     */
    public function provider_project_markTypeValid()
    {
        return [
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_PERCENT)), Percentage::class],
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_LETTER))->setGradeGroup($this->getDummyLetterGradeGroup()), LetterGrade::class],
            [(new Project())->setMarkType($this->getDummyMarkType(MarkType::TYPE_NONE)), None::class],
        ];
    }

    /**
     * @param int $id
     * @return MarkType
     */
    public function getDummyMarkType($id)
    {
        $markType = new MarkType();
        $markType->setId($id);

        switch ($id) {
            case 1:
                return $markType
                    ->setName('Percentage (%)')
                    ->setSlug('percent')
                    ->setComment('This should be a whole percentage between 0 and 100.')
                    ->setEnabled(true);
            case 2:
                return $markType
                    ->setName('Letter Grade')
                    ->setSlug('letter')
                    ->setComment('Se1ect a letter grade from the list.')
                    ->setEnabled(true);
            case 3:
                return $markType
                    ->setName('Total Mark')
                    ->setSlug('raw')
                    ->setComment('This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.')
                    ->setEnabled(true);
            case 4:
                return $markType
                    ->setName('Free Type (legacy support)')
                    ->setSlug('free')
                    ->setComment('Should be a letter (A), score (15/20), or percentage (88%).')
                    ->setEnabled(false);
            case 5:
                return $markType
                    ->setName('Rubric')
                    ->setSlug('rubric')
                    ->setComment('To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.')
                    ->setEnabled(false);
            case 6:
                return $markType
                    ->setName('None')
                    ->setSlug('none')
                    ->setComment('This due work is not marked.')
                    ->setEnabled(true);
            case 7:
                return $markType
                    ->setName('Completion')
                    ->setSlug('completion')
                    ->setComment("Either 'Completed' or 'Not Completed'")
                    ->setEnabled(false);
            default:
                throw new InvalidArgumentException("Unknown MarkTypeId: $id");
        }
    }

    /**
     * @return GradeGroup
     */
    private function getDummyLetterGradeGroup()
    {
        $gradeGroup = new GradeGroup();
        $gradeGroup->setId(3);
        $gradeGroup->setName('Competency');

        $levelDatums = [
            ['13', '0',  '20', 'Needs Attention'],
            ['14', '40', '53', 'Acceptable'],
            ['15', '60', '68', 'Good'],
            ['16', '75', '88', 'Very Good'],
            ['17', '90', '95', 'Excellent'],
        ];
        foreach ($levelDatums as $levelDatum) {
            $level = new GradeLevel();
            $level->setId($levelDatum[0]);
            $level->setLowerBound($levelDatum[1]);
            $level->setRawGrade($levelDatum[2]);
            $level->setCaption($levelDatum[3]);
            $level->setGroup($gradeGroup);

            $gradeGroup->addGradeLevel($level);
        }
        return $gradeGroup;
    }
}