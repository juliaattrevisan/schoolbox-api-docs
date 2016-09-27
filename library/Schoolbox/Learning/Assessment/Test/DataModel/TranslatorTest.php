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
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
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
    public function testTranslateGenericFieldsToDataModel()
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
     * Test translating a project
     */
    public function testTranslateProjectToDataModel()
    {
        $project = (new Project(new WeightedAverage()))
            // Generic fields
            ->setId(16)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Project ништа')
            ->setDescription('A description сплат')
            ->setWeight(13);

        // Add sub-assessables
        $project->getAssessables()
                ->add((new Task())
                    ->setId(1)
                    ->setTitle('Sub-task')
                )
                ->add((new Quiz())
                    ->setId(2)
                    ->setTitle('Sub-quiz')
                )
                ->add((new DueWork())
                    ->setId(3)
                    ->setTitle('Sub-work')
                );

        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project, 1);

        // Test project-specific fields
        $this->assertInstanceOf(ProjectModel::class, $projectModel);
        $this->assertEquals(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE, $projectModel->getSummator());

        // Test sub-assessables
        $this->assertCount(3, $projectModel->getAssessments());

        $this->assertInstanceOf(TaskModel::class, $projectModel->getAssessments()[0]);
        $this->assertEquals(0, $projectModel->getAssessments()[0]->getAssessmentModel()->getSequence());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getAssessmentModel()->getId());

        $this->assertInstanceOf(QuizModel::class, $projectModel->getAssessments()[1]);
        $this->assertEquals(1, $projectModel->getAssessments()[1]->getAssessmentModel()->getSequence());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getAssessmentModel()->getId());

        $this->assertInstanceOf(DueWorkModel::class, $projectModel->getAssessments()[2]);
        $this->assertEquals(2, $projectModel->getAssessments()[2]->getAssessmentModel()->getSequence());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getAssessmentModel()->getId());
    }

    /**
     * Test translation of projects which contain projects.
     *
     * @expectedException \UnexpectedValueException
     */
    public function testTranslateProjectWithinProjectToDataModel()
    {
        $project = (new Project(new Average()))->setId(12);
        $project->getAssessables()->add((new Project(new Minimum())));
        $this->translator->translateToDataModel($project, 1);
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateProjectSummatorToDataModelData()
    {
        return [
            [new Project(new WeightedAverage()), ProjectModel::SUMMATOR_WEIGHTED_AVERAGE,],
            [new Project(new Average()), ProjectModel::SUMMATOR_AVERAGE,],
            [new Project(new Minimum()), ProjectModel::SUMMATOR_MINIMUM,],
            [new Project(new Maximum()), ProjectModel::SUMMATOR_MAXIMUM,],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateProjectSummatorToDataModelData
     *
     * @param Project $project
     * @param string  $summatorType
     */
    public function testTranslateProjectSummatorToDataModel(Project $project, $summatorType)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project, 1);

        // Test type
        $this->assertInstanceOf(ProjectModel::class, $projectModel);

        // Test summator
        $this->assertEquals($summatorType, $projectModel->getSummator());
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
        $this->assertTrue($dueWorkModel->getSkipUnsubmitted());

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

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateDueWorkResponseAcceptanceToDataModelData()
    {
        return [
            [(new DueWork())->setAcceptsMultiple(), true, true,],
            [(new DueWork())->setAcceptsSingle(), true, false,],
            [(new DueWork())->setAcceptsNone(), false, false,],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateDueWorkResponseAcceptanceToDataModelData
     *
     * @param DueWork $dueWork
     * @param bool    $acceptsOnline
     * @param bool    $acceptsMultiple
     */
    public function testTranslateDueWorkResponseAcceptanceToDataModel(DueWork $dueWork, $acceptsOnline, $acceptsMultiple)
    {
        /** @var DueWorkModel $dueWorkModel */
        $dueWorkModel = $this->translator->translateToDataModel($dueWork, 2);

        // Test type
        $this->assertInstanceOf(DueWorkModel::class, $dueWorkModel);

        // Test due work-specific fields
        $this->assertEquals($acceptsOnline, $dueWorkModel->getAcceptOnline());
        $this->assertEquals($acceptsMultiple, $dueWorkModel->getAcceptMultiple());
    }

    /**
     * Test translating generic assessment data model fields to the domain object.
     */
    public function testTranslateGenericFieldsToDomainModel()
    {
        $dataModel = (new TaskModel())
            ->setId(15)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(401)
                    ->setTitle('Some assessment\'s title')
                    ->setDescription('Blargh')
                    ->setWeight(51)
                    ->setPublishDate(new Alaress_Date_Format('2016-10-10 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setOpenDate(new Alaress_Date_Format('2016-10-11 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setDueDate(new Alaress_Date_Format('2016-10-12 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setCloseDate(new Alaress_Date_Format('2016-10-13 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
        );

        /** @var IAssessable $assessment */
        $assessment = $this->translator->translateToDomainModel($dataModel);

        $this->assertEquals(401, $assessment->getId());
        $this->assertEquals('Some assessment\'s title', $assessment->getTitle());
        $this->assertEquals('Blargh', $assessment->getDescription());
        $this->assertEquals(51, $assessment->getWeight());
        $this->assertEquals('2016-10-10 12:00:00', $assessment->getPublishAt()->getDatabase());
        $this->assertEquals('2016-10-11 12:00:00', $assessment->getOpenAt()->getDatabase());
        $this->assertEquals('2016-10-12 12:00:00', $assessment->getDueAt()->getDatabase());
        $this->assertEquals('2016-10-13 12:00:00', $assessment->getCloseAt()->getDatabase());
    }

    /**
     * Test translation of projects which contain projects.
     *
     * @expectedException \UnexpectedValueException
     */
    public function testTranslateEmptyAssessmentToDataModel()
    {
        $dataModel = (new TaskModel())->setId(15);
        $this->translator->translateToDomainModel($dataModel);
    }

    /**
     * Data provider for testTranslateTaskToDomainModel
     *
     * @return array
     */
    public function getTestTranslateTaskToDomainModelData()
    {
        // Instance vars defined in setUp() aren't available to data providers
        // I am not sure if this is a bug.
        return [
            [
                (new TaskModel())
                    ->setOwner((new User())->setId(3))
                    ->setAssessmentModel((new AssessmentModel()))
                    ->setLink('https://www.google.com.au/')
                    ->setRequiresConfirmation(false),
                'https://www.google.com.au/',
                true,
                3
            ],
            [
                (new TaskModel())
                    ->setOwner((new User())->setId(151))
                    ->setAssessmentModel((new AssessmentModel()))
                    ->setLink(null)
                    ->setRequiresConfirmation(true),
                null,
                false,
                151
            ],
        ];
    }

    /**
     * Test translating task-specific data model fields
     *
     * @dataProvider getTestTranslateTaskToDomainModelData
     *
     * @param TaskModel $taskModel
     * @param string    $link
     * @param bool      $selfMarkable
     * @param int       $creatorId
     */
    public function testTranslateTaskToDomainModel(TaskModel $taskModel, $link, $selfMarkable, $creatorId)
    {
        /** @var Task $task */
        $task = $this->translator->translateToDomainModel($taskModel);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals($link, $task->getLink());
        $this->assertEquals($selfMarkable, $task->getSelfMarkable());
        $this->assertEquals($creatorId, $task->getCreator()->getId());
    }

    /**
     * Test translating quiz-specific fields to domain model
     */
    public function testTranslateQuizToDomainModel()
    {
        $quizModel = (new QuizModel())
            ->setId(13)
            ->setAssessmentModel((new AssessmentModel())->setId(105))
            ->setCompletionMessage('you done a quiz')
            ->setDuration(Quiz::DURATION_UNLIMITED)
            ->setMaxAttempts(Quiz::ATTEMPTS_UNLIMITED)
            ->setUser($this->teacher1)
        ;

        /** @var Quiz $quiz */
        $quiz = $this->translator->translateToDomainModel($quizModel);

        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertEquals(105, $quiz->getId());
        $this->assertEquals('you done a quiz', $quiz->getCompletionMessage());
        $this->assertEquals(Quiz::DURATION_UNLIMITED, $quiz->getDuration());
        $this->assertEquals(Quiz::ATTEMPTS_UNLIMITED, $quiz->getMaxAttempts());
        $this->assertEquals(3, $quiz->getCreator()->getId());
    }

    /**
     * Test translating due work specific fields to domain model
     */
    public function testTranslateDueWorkToDomainModel()
    {
        $dueWorkModel = (new DueWorkModel())
            ->setId(6789)
            ->setAssessmentModel(
                (new AssessmentModel())->setId(33)
            )
            ->setSkipUnsubmitted(false)
            ->setAcceptOnline(true)
            ->setAcceptMultiple(true)
            ->setUseRubric(false)
            ->setOwner($this->teacher2)
            ->setAttachments((new Collection())
                ->add((new StorageModel())
                    ->setFilename('a file.txt')
                    ->setHash('1234567890123456789012345678901234567890')
                )
                ->add((new StorageModel())
                    ->setFilename('an image.png')
                    ->setHash('0000000000000000000000000000000000000002')
                )
                ->add((new StorageModel())
                    ->setFilename('a sound.mp3')
                    ->setHash('0000000000000000000000000000000000000001')
                )
            )
            ->setWorkType((new WorkTypeModel())
                ->setId(12)
                ->setName('A dumb type')
            )
        ;

        /** @var DueWork $dueWork */
        $dueWork = $this->translator->translateToDomainModel($dueWorkModel);

        $this->assertInstanceOf(DueWork::class, $dueWork);
        $this->assertEquals(33, $dueWork->getId());
        $this->assertFalse($dueWork->getShowSubmittedOnly());
        $this->assertFalse($dueWork->isAcceptNone());
        $this->assertFalse($dueWork->isAcceptSingle());
        $this->assertTrue($dueWork->isAcceptMulti());
        $this->assertEquals(151, $dueWork->getCreator()->getId());

        $this->assertCount(3, $dueWork->getAllFiles());

        $this->assertInstanceOf(DueWorkFile::class, $dueWork->getAllFiles()[0]);
        $this->assertEquals('a file.txt', $dueWork->getAllFiles()[0]->getFilename());
        $this->assertEquals('1234567890123456789012345678901234567890', $dueWork->getAllFiles()[0]->getHash());

        $this->assertInstanceOf(DueWorkFile::class, $dueWork->getAllFiles()[1]);
        $this->assertEquals('an image.png', $dueWork->getAllFiles()[1]->getFilename());
        $this->assertEquals('0000000000000000000000000000000000000002', $dueWork->getAllFiles()[1]->getHash());

        $this->assertInstanceOf(DueWorkFile::class, $dueWork->getAllFiles()[2]);
        $this->assertEquals('a sound.mp3', $dueWork->getAllFiles()[2]->getFilename());
        $this->assertEquals('0000000000000000000000000000000000000001', $dueWork->getAllFiles()[2]->getHash());

        $this->assertInstanceOf(DueWorkType::class, $dueWork->getType());
        $this->assertEquals(12, $dueWork->getType()->getId());
        $this->assertEquals('A dumb type', $dueWork->getType()->getName());
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateDueWorkResponseAcceptanceToDomainModelData()
    {
        return [
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(true)
                    ->setAcceptMultiple(true),
                true,
                true,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(true)
                    ->setAcceptMultiple(false),
                true,
                false,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(false)
                    ->setAcceptMultiple(false),
                false,
                false,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(false)
                    ->setAcceptMultiple(true),
                false,
                false,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings to domain model.
     *
     * @dataProvider getTestTranslateDueWorkResponseAcceptanceToDomainModelData
     *
     * @param DueWorkModel $dataModel
     * @param bool         $acceptsOnline
     * @param bool         $acceptsMultiple
     */
    public function testTranslateDueWorkResponseAcceptanceToDomainModel(DueWorkModel $dataModel, $acceptsOnline, $acceptsMultiple)
    {
        /** @var DueWork $dueWork */
        $dueWork = $this->translator->translateToDomainModel($dataModel);

        // Test type
        $this->assertInstanceOf(DueWork::class, $dueWork);

        // Test due work-specific fields
        $this->assertNotEquals($acceptsOnline, $dueWork->isAcceptNone());
        $this->assertEquals($acceptsOnline && !$acceptsMultiple, $dueWork->isAcceptSingle());
        $this->assertEquals($acceptsMultiple, $dueWork->isAcceptMulti());
    }
}
