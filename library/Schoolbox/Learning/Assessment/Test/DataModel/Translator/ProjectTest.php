<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\OnlineAssessment as QuizModel;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Test\DataModel\TestTrait;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;

/**
 * Tests for the assessment data model translator (projects only).
 */
class ProjectTest extends \PHPUnit_Framework_TestCase
{
    use TestTrait;

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
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateProjectMarkSchemeToDataModelData()
    {
        return [
            [
                (new Project(new WeightedAverage()))->setMarkScheme(new None()),
                DueWorkModel\MarkType::TYPE_NONE,
                null,
            ],
            [
                (new Project(new WeightedAverage()))->setMarkScheme(new Percentage()),
                DueWorkModel\MarkType::TYPE_PERCENT,
                null,
            ],
            [
                (new Project(new WeightedAverage()))->setMarkScheme(new LetterGrade((new DueWorkModel\GradeLevel\Group\Model())->setId(7))),
                DueWorkModel\MarkType::TYPE_LETTER,
                7,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateProjectMarkSchemeToDataModelData
     *
     * @param Project $project
     * @param int     $markTypeId
     * @param int     $gradeGroupId
     */
    public function testTranslateProjectMarkSchemeToDataModel(Project $project, $markTypeId, $gradeGroupId)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project, 1);

        // Test type
        $this->assertInstanceOf(ProjectModel::class, $projectModel);

        // Test mark type
        $this->assertEquals($markTypeId, $projectModel->getMarkType()->getId());
        $this->assertEquals($gradeGroupId, $projectModel->getGradeGroup()->getId());
    }

    /**
     * Test translation of project
     */
    public function testTranslateProjectToDomainModel()
    {
        $projectModel = (new ProjectModel())
            ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
            ->setAssessmentModel((new AssessmentModel())->setId(4))
            ->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_NONE));

        $projectModel->getAssessments()
            ->add((new TaskModel())
                ->setOwner($this->teacher1)
                ->setAssessmentModel((new AssessmentModel())->setId(1))
            )
            ->add((new QuizModel())
                ->setUser($this->teacher2)
                ->setAssessmentModel((new AssessmentModel())->setId(2))
            )
            ->add((new DueWorkModel())
                ->setOwner($this->teacher2)
                ->setAssessmentModel((new AssessmentModel())->setId(3))
                ->setMarkType($this->buildMarkType())
            );

        /** @var Project $project */
        $project = $this->translator->translateToDomainModel($projectModel);

        // Test project-specific fields
        $this->assertInstanceOf(Project::class, $project);
        $this->assertInstanceOf(Average::class, $project->getSummator());
        $this->assertEquals(4, $project->getId());

        // Test sub-assessables
        $this->assertCount(3, $project->getAssessables());

        $this->assertInstanceOf(Task::class, $project->getAssessables()[0]);
        $this->assertEquals(1, $project->getAssessables()[0]->getId());

        $this->assertInstanceOf(Quiz::class, $project->getAssessables()[1]);
        $this->assertEquals(2, $project->getAssessables()[1]->getId());

        $this->assertInstanceOf(DueWork::class, $project->getAssessables()[2]);
        $this->assertEquals(3, $project->getAssessables()[2]->getId());
    }

    /**
     * Test translation of projects which contain projects.
     *
     * @expectedException \UnexpectedValueException
     */
    public function testTranslateProjectWithinProjectToDomainModel()
    {
        $projectModel = (new ProjectModel())
            ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
            ->setAssessmentModel((new AssessmentModel())->setId(4));
        $projectModel->getAssessments()
            ->add((new ProjectModel())
                ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                ->setAssessmentModel((new AssessmentModel())->setId(1))
            );

        $this->translator->translateToDomainModel($projectModel);
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateProjectSummatorToDomainModelData()
    {
        return [
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType()),
                WeightedAverage::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType()),
                Average::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MINIMUM)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType()),
                Minimum::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MAXIMUM)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType()),
                Maximum::class,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateProjectSummatorToDomainModelData
     *
     * @param ProjectModel $dataModel
     * @param string       $summatorClass
     */
    public function testTranslateProjectSummatorToDomainModel(ProjectModel $dataModel, $summatorClass)
    {
        /** @var Project $project */
        $project = $this->translator->translateToDomainModel($dataModel);

        // Test type
        $this->assertInstanceOf(Project::class, $project);

        // Test summator
        $this->assertInstanceOf($summatorClass, $project->getSummator());
    }

    /**
     * Data provider for different project mark scheme settings.
     */
    public function getTestTranslateProjectMarkSchemeToDomainModelData()
    {
        return [
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_NONE))
                    ->setGradeGroup((new GradeGroup())),
                None::class,
                null
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_PERCENT))
                    ->setGradeGroup((new GradeGroup())),
                Percentage::class,
                null
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MINIMUM)
                    ->setAssessmentModel((new AssessmentModel())->setId(1))
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_LETTER))
                    ->setGradeGroup((new GradeGroup())->setId(1)),
                LetterGrade::class,
                1
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateProjectMarkSchemeToDomainModelData
     *
     * @param ProjectModel $dataModel
     * @param string       $markSchemeClass
     * @param int          $gradeGroupId
     */
    public function testTranslateProjectMarkSchemeToDomainModel(ProjectModel $dataModel, $markSchemeClass, $gradeGroupId)
    {
        /** @var Project $project */
        $project = $this->translator->translateToDomainModel($dataModel);

        // Test type
        $this->assertInstanceOf(Project::class, $project);

        // Test mark scheme and ancillary information
        $scheme = $project->getMarkScheme();
        $this->assertInstanceOf($markSchemeClass, $scheme);
        if ($scheme instanceof LetterGrade) {
            $this->assertEquals($gradeGroupId, $scheme->getGradeGroup()->getId());
        }
    }
}
