<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\OnlineAssessment as QuizModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\User;

/**
 * Tests for the assessment data model translator.
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var User
     */
    protected $teacher1;

    /**
     * @var User
     */
    protected $teacher2;

    /**
     * Set up
     */
    protected function setUp()
    {
        $this->translator = new Translator();

        // Test users
        $this->teacher1 = (new User())
            ->setId(3)
            ->setExternalId(8)
            ->setTitle('Ms')
            ->setFirstname('Celia')
            ->setLastname('Glenn')
        ;

        $this->teacher2 = (new User())
            ->setId(151)
            ->setExternalId(165)
            ->setTitle('Mr')
            ->setFirstname('Zane')
            ->setLastname('Smith')
        ;
    }

    /**
     * Test translating a task
     */
    public function testTranslateTaskToDataModel()
    {
        $task = (new Task())
            // Generic fields
            ->setId(32)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Task сказать')
            // Description is ignored for tasks
            ->setDescription('Blaargh ничего')
            // Tasks are always weight 0, so this doesn't matter
            ->setWeight(1000)
            // Specific fields
            ->setLink('https://www.something.com/')
            ->setSelfMarkable(true)
        ;

        /** @var TaskModel $taskModel */
        $taskModel = $this->translator->translateToDataModel($task, 3);

        // Test type
        $this->assertInstanceOf(TaskModel::class, $taskModel);

        // Test generic fields
        $this->assertEquals(32, $taskModel->getAssessmentModel()->getId());
        $this->assertEquals(3, $taskModel->getAssessmentModel()->getSequence());
        $this->assertEquals('2016-10-14 12:00:00', $taskModel->getAssessmentModel()->getPublishDate()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $taskModel->getAssessmentModel()->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-18 12:00:00', $taskModel->getAssessmentModel()->getDueDate()->getDatabase());
        $this->assertEquals('2016-10-23 13:00:00', $taskModel->getAssessmentModel()->getCloseDate()->getDatabase());
        $this->assertEquals('Mr Test Task сказать', $taskModel->getAssessmentModel()->getTitle());
        $this->assertEquals('Blaargh ничего', $taskModel->getAssessmentModel()->getDescription());
        $this->assertEquals(1000, $taskModel->getAssessmentModel()->getWeight());

        // Test task-specific fields
        $this->assertEquals(3, $taskModel->getOwner()->getId());
        $this->assertEquals('https://www.something.com/', $taskModel->getLink());
        $this->assertEquals(false, $taskModel->getRequiresConfirmation());
    }

    /**
     * Test translation to quiz
     */
    public function testTranslateQuizToDataModel()
    {
        $quiz = (new Quiz())
            // Generic fields
            ->setId(61)
            ->setCreator($this->teacher2)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Test Task κάτι')
            ->setDescription('Blah blah blah τίποτα')
            ->setWeight(65)
            // Specific fields
            ->setMaxAttempts(7)
            ->setDuration(1500)
            ->setCompletionMessage('You done the quiz, Συγχαρητήρια!')
        ;

        /** @var QuizModel $quizModel */
        $quizModel = $this->translator->translateToDataModel($quiz, 0);

        // Test type
        $this->assertInstanceOf(QuizModel::class, $quizModel);

        // Test generic fields
        $this->assertEquals(61, $quizModel->getAssessmentModel()->getId());
        $this->assertEquals(0, $quizModel->getAssessmentModel()->getSequence());
        $this->assertEquals('2016-10-14 12:00:00', $quizModel->getAssessmentModel()->getPublishDate()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $quizModel->getAssessmentModel()->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-18 12:00:00', $quizModel->getAssessmentModel()->getDueDate()->getDatabase());
        $this->assertEquals('2016-10-23 13:00:00', $quizModel->getAssessmentModel()->getCloseDate()->getDatabase());
        $this->assertEquals('Test Task κάτι', $quizModel->getAssessmentModel()->getTitle());
        $this->assertEquals('Blah blah blah τίποτα', $quizModel->getAssessmentModel()->getDescription());
        $this->assertEquals(65, $quizModel->getAssessmentModel()->getWeight());

        // Test quiz-specific fields
        $this->assertEquals(151, $quizModel->getUser()->getId());
        $this->assertEquals(7, $quizModel->getMaxAttempts());
        $this->assertEquals(1500, $quizModel->getDuration());
        $this->assertEquals('You done the quiz, Συγχαρητήρια!', $quizModel->getCompletionMessage());
    }
}
