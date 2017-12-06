<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Quiz\Model as QuizModel;
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
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;

/**
 * Tests for the assessment data model translator (projects only).
 */
class ProjectTest extends TestCase
{
    use TestTrait;

    /**
     * Test translating a project
     */
    public function testTranslateToDataModel()
    {
        $project = (new Project(new WeightedAverage()))
            // Generic fields
            ->setContext((new Folder())->setId(1))
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
                    ->setSequence(1)
                )
                ->add((new Quiz())
                    ->setId(2)
                    ->setTitle('Sub-quiz')
                    ->setSequence(2)
                )
                ->add((new DueWork())
                    ->setId(3)
                    ->setTitle('Sub-work')
                    ->setSequence(3)
                    ->setWorkType(new WorkType())
                    ->setSelfAssessable(true)
                    ->setSelfReflectable(true)
                );

        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test project-specific fields
        $this->assertInstanceOf(ProjectModel::class, $projectModel);
        $this->assertEquals(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE, $projectModel->getSummator());

        // Test sub-assessables
        $this->assertCount(3, $projectModel->getAssessments());

        $this->assertInstanceOf(TaskModel::class, $projectModel->getAssessments()[0]);
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getAssessmentModel()->getSequence());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getAssessmentModel()->getId());

        $this->assertInstanceOf(QuizModel::class, $projectModel->getAssessments()[1]);
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getAssessmentModel()->getSequence());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getAssessmentModel()->getId());

        $this->assertInstanceOf(DueWorkModel::class, $projectModel->getAssessments()[2]);
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getAssessmentModel()->getSequence());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getAssessmentModel()->getId());
    }

    /**
     * Test translation of projects which contain projects.
     *
     * @expectedException \UnexpectedValueException
     */
    public function testTranslateWithinProjectToDataModel()
    {
        $project = (new Project(new Average()))
            ->setContext((new Folder())->setId(1))
            ->setId(12)
            ->setCreator($this->teacher1);
        $project->getAssessables()->add((new Project(new Minimum()))->setCreator($this->teacher1));
        $this->translator->translateToDataModel($project);
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateSummatorToDataModelData()
    {
        $context = (new Folder())->setId(1);
        $user    = (new User())->setId(3);
        return [
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user),
                ProjectModel::SUMMATOR_WEIGHTED_AVERAGE,
            ],
            [
                (new Project(new Average()))
                    ->setContext($context)
                    ->setCreator($user),
                ProjectModel::SUMMATOR_AVERAGE,
            ],
            [
                (new Project(new Minimum()))
                    ->setContext($context)
                    ->setCreator($user),
                ProjectModel::SUMMATOR_MINIMUM,
            ],
            [
                (new Project(new Maximum()))
                    ->setContext($context)
                    ->setCreator($user),
                ProjectModel::SUMMATOR_MAXIMUM,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateSummatorToDataModelData
     *
     * @param Project $project
     * @param string  $summatorType
     */
    public function testTranslateSummatorToDataModel(Project $project, $summatorType)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test type
        $this->assertInstanceOf(ProjectModel::class, $projectModel);

        // Test summator
        $this->assertEquals($summatorType, $projectModel->getSummator());
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateMarkSchemeToDataModelData()
    {
        $context = (new Folder())->setId(1);
        $user    = (new User())->setId(3);
        return [
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkScheme(new None()),
                DueWorkModel\MarkType::TYPE_NONE,
                null,
            ],
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkScheme(new Percentage()),
                DueWorkModel\MarkType::TYPE_PERCENT,
                null,
            ],
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkScheme(new LetterGrade((new DueWorkModel\GradeLevel\Group\Model())->setId(7)->addGradeLevel(
                        new DueWorkModel\GradeLevel\Model()
                    ))),
                DueWorkModel\MarkType::TYPE_LETTER,
                7,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateMarkSchemeToDataModelData
     *
     * @param Project $project
     * @param int     $markTypeId
     * @param int     $gradeGroupId
     */
    public function testTranslateMarkSchemeToDataModel(Project $project, $markTypeId, $gradeGroupId)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test type
        $this->assertInstanceOf(ProjectModel::class, $projectModel);

        // Test mark type
        $this->assertEquals($markTypeId, $projectModel->getMarkType()->getId());
        $this->assertEquals($gradeGroupId, $projectModel->getGradeGroup()->getId());
    }

    /**
     * Test translation of project
     */
    public function testTranslateToDomainModel()
    {
        $projectModel = (new ProjectModel())
            ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(4)
                    ->setAssessmentContext((new Folder())->setId(1))
            )
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
                ->setSelfReflectable(true)
                ->setSelfAssessable(true)
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
    public function testTranslateWithinProjectToDomainModel()
    {
        $projectModel = (new ProjectModel())
            ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(4)
                    ->setAssessmentContext((new Folder())->setId(1))
            );
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
    public function getTestTranslateSummatorToDomainModelData()
    {
        $assessmentModel = (new AssessmentModel())
            ->setId(1)
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType()),
                WeightedAverage::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType()),
                Average::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MINIMUM)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType()),
                Minimum::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MAXIMUM)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType()),
                Maximum::class,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateSummatorToDomainModelData
     *
     * @param ProjectModel $dataModel
     * @param string       $summatorClass
     */
    public function testTranslateSummatorToDomainModel(ProjectModel $dataModel, $summatorClass)
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
    public function getTestTranslateMarkSchemeToDomainModelData()
    {
        $assessmentModel = (new AssessmentModel())
            ->setId(1)
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType())
                    ->setGradeGroup((new GradeGroup())),
                None::class,
                null
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_PERCENT))
                    ->setGradeGroup((new GradeGroup())),
                Percentage::class,
                null
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MINIMUM)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_LETTER))
                    ->setGradeGroup((new GradeGroup())->setId(1)->addGradeLevel(
                        new DueWorkModel\GradeLevel\Model()
                    )),
                LetterGrade::class,
                1
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateMarkSchemeToDomainModelData
     *
     * @param ProjectModel $dataModel
     * @param string       $markSchemeClass
     * @param int          $gradeGroupId
     */
    public function testTranslateMarkSchemeToDomainModel(ProjectModel $dataModel, $markSchemeClass, $gradeGroupId)
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

    /**
     * Test translation of a project's activity's specification assessments to a domain model.
     */
    public function testTranslateSpecificationAssessmentToDomainModel()
    {
        // The project is within a unit implementation
        // It also has unit specifications
        $unitSpecModel = (new UnitSpecification())
            ->setId(2)
            ->setCourse((new CourseSpecification())->setId(3));
        $projectModel  = (new ProjectModel())
            ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(104)
                    ->setAssessmentContext((new UnitImplementation())->setId(1))
                    ->setSpecificationAssessment(
                        (new AssessmentModel())
                            ->setId(4)
                            ->setAssessmentContext($unitSpecModel)
                            ->setSubObject(
                                (new ProjectModel())
                                    ->setId(4)
                                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                                    ->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_NONE))
                            )
                    )
            )
            ->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_NONE));

        // Project activities may have a specification, even though they're in the context of a project
        $projectModel->getAssessments()
                     ->add(
                         (new TaskModel())
                             ->setOwner($this->teacher1)
                             ->setAssessmentModel(
                                 (new AssessmentModel())
                                     ->setId(101)
                                     ->setSpecificationAssessment(
                                         (new AssessmentModel())
                                             ->setId(1)
                                             ->setAssessmentContext($unitSpecModel)
                                             ->setSubObject((new TaskModel())->setId(1))
                                     )
                             )
                     )
                     ->add(
                         (new QuizModel())
                             ->setUser($this->teacher2)
                             ->setAssessmentModel(
                                 (new AssessmentModel())
                                     ->setId(102)
                                     ->setSpecificationAssessment(
                                         (new AssessmentModel())
                                             ->setId(2)
                                             ->setAssessmentContext($unitSpecModel)
                                             ->setSubObject((new QuizModel())->setId(2))
                                     )
                             )
                     )
                     ->add(
                         (new DueWorkModel())
                             ->setOwner($this->teacher2)
                             ->setAssessmentModel(
                                 (new AssessmentModel())
                                     ->setId(103)
                                     ->setSpecificationAssessment(
                                         (new AssessmentModel())
                                             ->setId(3)
                                             ->setAssessmentContext($unitSpecModel)
                                             ->setSubObject(
                                                 (new DueWorkModel())
                                                     ->setId(3)
                                                     ->setMarkType($this->buildMarkType())
                                                     ->setSelfAssessable(true)
                                                     ->setSelfReflectable(true)
                                             )
                                     )
                             )
                             ->setMarkType($this->buildMarkType())
                             ->setSelfReflectable(true)
                             ->setSelfAssessable(true)
                     );

        /** @var Project $project */
        $project = $this->translator->translateToDomainModel($projectModel);

        // Test project-specific fields
        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals(104, $project->getId());

        // Test activities
        // Activities remain within the context of their project, and have a specification
        $this->assertCount(3, $project->getAssessables());

        $this->assertInstanceOf(Task::class, $project->getAssessables()[0]);
        $this->assertEquals(101, $project->getAssessables()[0]->getId());
        $this->assertEquals(1, $project->getAssessables()[0]->getSpecificationAssessment()->getId());
        $this->assertInstanceOf(Project::class, $project->getAssessables()[0]->getContext());

        $this->assertInstanceOf(Quiz::class, $project->getAssessables()[1]);
        $this->assertEquals(102, $project->getAssessables()[1]->getId());
        $this->assertEquals(2, $project->getAssessables()[1]->getSpecificationAssessment()->getId());
        $this->assertInstanceOf(Project::class, $project->getAssessables()[1]->getContext());

        $this->assertInstanceOf(DueWork::class, $project->getAssessables()[2]);
        $this->assertEquals(103, $project->getAssessables()[2]->getId());
        $this->assertEquals(3, $project->getAssessables()[2]->getSpecificationAssessment()->getId());
        $this->assertInstanceOf(Project::class, $project->getAssessables()[2]->getContext());
    }

    /**
     * Test translation of a project's activity's specification assessments to a data model.
     */
    public function testTranslateSpecificationAssessmentToDataModel()
    {
        $unitSpec = (new UnitSpecification())->setId(2);
        $project  = (new Project(new WeightedAverage()))
            // Generic fields
            ->setContext((new UnitImplementation())->setId(1))
            ->setId(104)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Project ништа')
            ->setDescription('A description сплат')
            ->setWeight(13)
            ->setSpecificationAssessment(
                (new Project(new WeightedAverage()))
                    ->setContext($unitSpec)
                    ->setId(4)
                    ->setCreator($this->teacher1)
            );

        // Add sub-assessables
        $project->getAssessables()
                ->add(
                    (new Task())
                        ->setId(101)
                        ->setTitle('Sub-task')
                        ->setSequence(1)
                        ->setSpecificationAssessment(
                            (new Task())
                                ->setContext($unitSpec)
                                ->setId(1)
                                ->setTitle('Sub-task')
                        )
                )
                ->add(
                    (new Quiz())
                        ->setId(102)
                        ->setTitle('Sub-quiz')
                        ->setSequence(2)
                        ->setSpecificationAssessment(
                            (new Quiz())
                                ->setContext($unitSpec)
                                ->setId(2)
                                ->setTitle('Sub-quiz')
                        )
                )
                ->add(
                    (new DueWork())
                        ->setId(103)
                        ->setTitle('Sub-work')
                        ->setSequence(3)
                        ->setSpecificationAssessment(
                            (new DueWork())
                                ->setContext($unitSpec)
                                ->setId(3)
                                ->setTitle('Sub-work')
                                ->setWorkType(new WorkType())
                        )
                        ->setWorkType(new WorkType())
                        ->setSelfReflectable(true)
                        ->setSelfAssessable(true)
                );

        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test sub-assessables
        $this->assertCount(3, $projectModel->getAssessments());

        $this->assertInstanceOf(TaskModel::class, $projectModel->getAssessments()[0]);
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getAssessmentModel()->getSequence());
        $this->assertEquals(101, $projectModel->getAssessments()[0]->getAssessmentModel()->getId());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getAssessmentModel()->getSpecificationAssessment()->getId());

        $this->assertInstanceOf(QuizModel::class, $projectModel->getAssessments()[1]);
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getAssessmentModel()->getSequence());
        $this->assertEquals(102, $projectModel->getAssessments()[1]->getAssessmentModel()->getId());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getAssessmentModel()->getSpecificationAssessment()->getId());

        $this->assertInstanceOf(DueWorkModel::class, $projectModel->getAssessments()[2]);
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getAssessmentModel()->getSequence());
        $this->assertEquals(103, $projectModel->getAssessments()[2]->getAssessmentModel()->getId());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getAssessmentModel()->getSpecificationAssessment()->getId());
    }
}
