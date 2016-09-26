<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\OnlineAssessment as QuizModel;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType as WorkTypeModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Resource\Storage\Model as StorageModel;
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
     * Test translation of generic assessment fields
     */
    public function testTranslateGenericFields()
    {
        $assessment = (new Task())
            ->setId(32)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Task сказать')
            ->setDescription('Blaargh ничего')
            ->setWeight(1000);

        $assessmentModel = $this->translator->translateToDataModel($assessment, 3)->getAssessmentModel();

        $this->assertEquals(32, $assessmentModel->getId());
        $this->assertEquals(3, $assessmentModel->getSequence());
        $this->assertEquals('2016-10-14 12:00:00', $assessmentModel->getPublishDate()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $assessmentModel->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-18 12:00:00', $assessmentModel->getDueDate()->getDatabase());
        $this->assertEquals('2016-10-23 13:00:00', $assessmentModel->getCloseDate()->getDatabase());
        $this->assertEquals('Mr Test Task сказать', $assessmentModel->getTitle());
        $this->assertEquals('Blaargh ничего', $assessmentModel->getDescription());
        $this->assertEquals(1000, $assessmentModel->getWeight());
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

        // Test quiz-specific fields
        $this->assertEquals(151, $quizModel->getUser()->getId());
        $this->assertEquals(7, $quizModel->getMaxAttempts());
        $this->assertEquals(1500, $quizModel->getDuration());
        $this->assertEquals('You done the quiz, Συγχαρητήρια!', $quizModel->getCompletionMessage());
    }

    /**
     * Test translation to due work
     */
    public function testTranslateDueWorkToDataModel()
    {
        // TODO test variations
        $dueWork = (new DueWork())
            // Generic fields
            ->setId(908)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Due Work Test งาน')
            ->setDescription('Something ไม่มีอะไร')
            ->setWeight(21)
            // Specific fields
            ->setAcceptsMultiple()
            ->showOnlySubmittedStudents()
            ->setAllFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId(33)
                    ->setFilename('a file.txt')
                    ->setHash('1234567890123456789012345678901234567890')
                )
                ->add((new DueWorkFile())
                    ->setId(65)
                    ->setFilename('an image.png')
                    ->setHash('0000000000000000000000000000000000000002')
                )
                ->add((new DueWorkFile())
                    ->setId(82)
                    ->setFilename('a sound.mp3')
                    ->setHash('0000000000000000000000000000000000000001')
                )
            )
            ->setType((new DueWorkType())
                ->setId(12)
                ->setName('A dumb type')
            )
            ->setHasRubric(true)
        ;

        /** @var DueWorkModel $dueWorkModel */
        $dueWorkModel = $this->translator->translateToDataModel($dueWork, 2);

        // Test type
        $this->assertInstanceOf(DueWorkModel::class, $dueWorkModel);

        // Test due work-specific fields
        $this->assertEquals(3, $dueWorkModel->getOwner()->getId());
        $this->assertTrue($dueWorkModel->getAcceptOnline());
        $this->assertTrue($dueWorkModel->getAcceptMultiple());
        $this->assertTrue($dueWorkModel->getUseRubric());

        // Test files
        $this->assertCount(3, $dueWorkModel->getAttachments());

        $this->assertInstanceOf(StorageModel::class, $dueWorkModel->getAttachments()[0]);
        $this->assertEquals('a file.txt', $dueWorkModel->getAttachments()[0]->getFilename());
        $this->assertEquals('1234567890123456789012345678901234567890', $dueWorkModel->getAttachments()[0]->getHash());

        $this->assertInstanceOf(StorageModel::class, $dueWorkModel->getAttachments()[1]);
        $this->assertEquals('an image.png', $dueWorkModel->getAttachments()[1]->getFilename());
        $this->assertEquals('0000000000000000000000000000000000000002', $dueWorkModel->getAttachments()[1]->getHash());

        $this->assertInstanceOf(StorageModel::class, $dueWorkModel->getAttachments()[2]);
        $this->assertEquals('a sound.mp3', $dueWorkModel->getAttachments()[2]->getFilename());
        $this->assertEquals('0000000000000000000000000000000000000001', $dueWorkModel->getAttachments()[2]->getHash());

        // Test type
        $this->assertInstanceOf(WorkTypeModel::class, $dueWorkModel->getWorkType());
        $this->assertEquals(12, $dueWorkModel->getWorkType()->getId());
        $this->assertEquals('A dumb type', $dueWorkModel->getWorkType()->getName());
    }
}
