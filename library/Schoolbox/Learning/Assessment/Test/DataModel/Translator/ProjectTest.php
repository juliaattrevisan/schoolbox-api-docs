<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
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
     * Returns an assessment mapper that by-passes the model cache.
     *
     * This enables multiple unit test to execute in series with models of the same ID not polluting other tests.
     *
     * @return Assessment\Mapper
     */
    protected function getAssessmentMapper(): Assessment\Mapper
    {
        return new class extends Assessment\Mapper
        {
            public function createModel($id = null)
            {
                return parent::createModel()->setId($id);
            }
        };
    }


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
            ->setWeight(13)
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
            ->setAssessables(new GenericCollection(IAssessable::class));

        // Add sub-assessables
        $project->getAssessables()
                ->add((new Task())
                    ->setId(1)
                    ->setTitle('Sub-task')
                    ->setSequence(1))
                ->add((new Quiz())
                    ->setId(2)
                    ->setTitle('Sub-quiz')
                    ->setSequence(2))
                ->add((new DueWork())
                    ->setId(3)
                    ->setTitle('Sub-work')
                    ->setSequence(3)
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL));

        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test project-specific fields
        $this->assertInstanceOf(ProjectModel::class, $projectModel);
        $this->assertEquals(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE, $projectModel->getSummator());
        $this->assertFalse($projectModel->getRequireAllActivities());

        // Test sub-assessables
        $this->assertCount(3, $projectModel->getAssessments());

        $this->assertInstanceOf(TaskModel::class, $projectModel->getAssessments()[0]->getSubObject());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getSequence());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getId());

        $this->assertInstanceOf(QuizModel::class, $projectModel->getAssessments()[1]->getSubObject());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getSequence());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getId());

        $this->assertInstanceOf(DueWorkModel::class, $projectModel->getAssessments()[2]->getSubObject());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getSequence());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getId());
    }

    /**
     * Assert that translating a domain model to a data model, then back to a
     * domain model returns the original domain model.
     */
    public function testTranslateDomainModelBackToDataModel()
    {
        $projectDomainModel = (new Project())
            ->setId(908)
            ->setContext((new Folder())->setId(1))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        $projectDataModel               = $this->translator->translateToDataModel($projectDomainModel);
        $retranslatedProjectDomainModel = $this->translator->translateToDomainModel($projectDataModel);
        $this->assertSame($projectDomainModel, $retranslatedProjectDomainModel);
    }

    /**
     * Assert that null domain model can be translated to a data model.
     */
    public function testTranslateNullDomainModelToDataModel()
    {
        $nullProjectDomainModel = (new Project())->setId(null)->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $nullProjectDataModel   = $this->translator->translateToDataModel($nullProjectDomainModel);
        $this->assertNull($nullProjectDataModel->getId());
    }

    /**
     * Test translation of projects which contain projects, greater than permitted level
     *
     *
     */
    public function testTranslateWithinProjectToDataModel()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->translator->setMaxProjectLevels(1);

        $project = (new Project(new Average()))
            ->setContext((new Folder())->setId(1))
            ->setId(12)
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
            ->setCreator($this->teacher1);
        $project->getAssessables()->add((new Project(new Minimum()))->setCreator($this->teacher1)->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT));
        $this->translator->translateToDataModel($project);
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public static function getTestTranslateSummatorToDataModelData()
    {
        $context = (new Folder())->setId(1);
        $user    = (new User())->setId(3);
        return [
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                ProjectModel::SUMMATOR_WEIGHTED_AVERAGE,
            ],
            [
                (new Project(new Average()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                ProjectModel::SUMMATOR_AVERAGE,
            ],
            [
                (new Project(new Minimum()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                ProjectModel::SUMMATOR_MINIMUM,
            ],
            [
                (new Project(new Maximum()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                ProjectModel::SUMMATOR_MAXIMUM,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     *
     * @param Project $project
     * @param string  $summatorType
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateSummatorToDataModelData')]
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
    public static function getTestTranslateMarkSchemeToDataModelData()
    {
        $context = (new Folder())->setId(1);
        $user    = (new User())->setId(3);
        return [
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkScheme(new None())
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                DueWorkModel\MarkType::TYPE_NONE,
                null,
            ],
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkScheme(new Percentage())
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                DueWorkModel\MarkType::TYPE_PERCENT,
                null,
            ],
            [
                (new Project(new WeightedAverage()))
                    ->setContext($context)
                    ->setCreator($user)
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
                    ->setMarkScheme(new LetterGrade((new DueWorkModel\GradeLevel\Group\Model())->setId(7)->setGradeLevels(
                        new GenericCollection(DueWorkModel\GradeLevel\Model::class, [new DueWorkModel\GradeLevel\Model()])
                    ))),
                DueWorkModel\MarkType::TYPE_LETTER,
                7,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     *
     * @param Project  $project
     * @param int      $markTypeId
     * @param null|int $gradeGroupId
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateMarkSchemeToDataModelData')]
    public function testTranslateMarkSchemeToDataModel(Project $project, int $markTypeId, ?int $gradeGroupId)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test type
        $this->assertInstanceOf(ProjectModel::class, $projectModel);

        // Test mark type
        $this->assertEquals($markTypeId, $projectModel->getMarkType()->getId());
        if (null !== $gradeGroupId) {
            $this->assertEquals($gradeGroupId, $projectModel->getGradeGroup()->getId());
        }
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
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
            ->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_NONE));

        $subTask = (new TaskModel())
            ->setOwner($this->teacher1)
            ->setAssessmentModel((new AssessmentModel())->setId(1));
        $subQuiz = (new QuizModel())
            ->setUser($this->teacher2)
            ->setAssessmentModel((new AssessmentModel())->setId(2));
        $subDueWork = (new DueWorkModel())
            ->setOwner($this->teacher2)
            ->setAssessmentModel((new AssessmentModel())->setId(3))
            ->setMarkType(self::buildMarkType())
            ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL);

        $projectModel->setAssessments(new GenericCollection(AssessmentModel::class));
        $projectModel->getAssessments()
            ->add($subTask->getAssessmentModel()->setSubObject($subTask))
            ->add($subQuiz->getAssessmentModel()->setSubObject($subQuiz))
            ->add($subDueWork->getAssessmentModel()->setSubObject($subDueWork));

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
     * Assert that translating a data model to a domain model, then back to a
     * data model returns the original data model.
     */
    public function testTranslateDataModelBackToDomainModel()
    {
        $projectDataModel = (new ProjectModel())
            ->setId(6789)
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setAssessmentContext((new Folder())->setId(1))
                    ->setId(33)
            );

        $projectDomainModel           = $this->translator->translateToDomainModel($projectDataModel);
        $retranslatedProjectDataModel = $this->translator->translateToDataModel($projectDomainModel);
        $this->assertSame($projectDataModel, $retranslatedProjectDataModel);
    }

    /**
     * Assert that null data model can be translated to a domain model.
     */
    public function testTranslateNullDataModelToDomainModel()
    {
        $nullProjectDataModel = (new ProjectModel())->setId(null)->setAssessmentModel(new AssessmentModel())->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $nullProjectDomainModel = $this->translator->translateToDomainModel($nullProjectDataModel);
        $this->assertNull($nullProjectDomainModel->getId());
    }

    /**
     * Test translation of projects which contain projects.
     *
     *
     */
    public function testTranslateWithinProjectToDomainModel()
    {
        $this->expectException(\UnexpectedValueException::class);
        $projectModel = (new ProjectModel())
            ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(4)
                    ->setAssessmentContext((new Folder())->setId(1))
            )
            ->setAssessments(new GenericCollection(AssessmentModel::class))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $projectModel->getAssessments()
            ->add((new ProjectModel())
                ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                ->setAssessmentModel((new AssessmentModel())->setId(1))
                ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT));

        $this->translator->translateToDomainModel($projectModel);
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public static function getTestTranslateSummatorToDomainModelData()
    {
        $assessmentModel = (new AssessmentModel())
            ->setId(1)
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType())
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                WeightedAverage::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType())
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                Average::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MINIMUM)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType())
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                Minimum::class,
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MAXIMUM)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType())
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                Maximum::class,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     *
     * @param ProjectModel $dataModel
     * @param string       $summatorClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateSummatorToDomainModelData')]
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
    public static function getTestTranslateMarkSchemeToDomainModelData()
    {
        $assessmentModel = (new AssessmentModel())
            ->setId(1)
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType())
                    ->setGradeGroup((new GradeGroup()))
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                None::class,
                null
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_AVERAGE)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType(DueWorkModel\MarkType::TYPE_PERCENT))
                    ->setGradeGroup((new GradeGroup()))
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT),
                Percentage::class,
                null
            ],
            [
                (new ProjectModel())
                    ->setSummator(ProjectModel::SUMMATOR_MINIMUM)
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType(self::buildMarkType(DueWorkModel\MarkType::TYPE_LETTER))
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
                    ->setGradeGroup((new GradeGroup())->setId(1)->setGradeLevels(
                        new GenericCollection(DueWorkModel\GradeLevel\Model::class, [new DueWorkModel\GradeLevel\Model()])
                    )),
                LetterGrade::class,
                1
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     *
     * @param ProjectModel $dataModel
     * @param string       $markSchemeClass
     * @param int          $gradeGroupId
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateMarkSchemeToDomainModelData')]
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
        $projectModel = (new ProjectModel())
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
                                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
                            )
                    )
            )
            ->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_NONE))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        // Project activities may have a specification, even though they're in the context of a project
        $subTask = (new TaskModel())
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
            );
        $subQuiz = (new QuizModel())
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
            );
        $subDueWork = (new DueWorkModel())
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
                                    ->setMarkType(self::buildMarkType())
                                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL)
                            )
                    )
            )
            ->setMarkType(self::buildMarkType())
            ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL);

        $projectModel->setAssessments(new GenericCollection(AssessmentModel::class));
        $projectModel->getAssessments()
                     ->add($subTask->getAssessmentModel()->setSubObject($subTask))
                     ->add($subQuiz->getAssessmentModel()->setSubObject($subQuiz))
                     ->add($subDueWork->getAssessmentModel()->setSubObject($subDueWork));

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
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
            ->setSpecificationAssessment(
                (new Project(new WeightedAverage()))
                    ->setContext($unitSpec)
                    ->setId(4)
                    ->setCreator($this->teacher1)
                    ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
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
                                ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL)
                        )
                        ->setWorkType(new WorkType())
                        ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL)
                );

        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($project);

        // Test sub-assessables
        $this->assertCount(3, $projectModel->getAssessments());

        $this->assertInstanceOf(TaskModel::class, $projectModel->getAssessments()[0]->getSubObject());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getSequence());
        $this->assertEquals(101, $projectModel->getAssessments()[0]->getId());
        $this->assertEquals(1, $projectModel->getAssessments()[0]->getSpecificationAssessment()->getId());

        $this->assertInstanceOf(QuizModel::class, $projectModel->getAssessments()[1]->getSubObject());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getSequence());
        $this->assertEquals(102, $projectModel->getAssessments()[1]->getId());
        $this->assertEquals(2, $projectModel->getAssessments()[1]->getSpecificationAssessment()->getId());

        $this->assertInstanceOf(DueWorkModel::class, $projectModel->getAssessments()[2]->getSubObject());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getSequence());
        $this->assertEquals(103, $projectModel->getAssessments()[2]->getId());
        $this->assertEquals(3, $projectModel->getAssessments()[2]->getSpecificationAssessment()->getId());
    }


    /**
     * @param ProjectModel $projectModel
     * @param int          $id           Id of this project node
     * @param int          $upExpected   How many nodes above this one?
     * @param int          $downExpected How many nodes below this one?
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLinearDataProjectTree')]
    public function testLinearDataProjectTree(ProjectModel $projectModel, int $id, int $upExpected, int $downExpected)
    {
        /** @var Project $domainObject */
        $domainObject = $this->translator->translateToDomainModel($projectModel);
        $this->assertEquals($id, $domainObject->getId());

        $up            = $down = 0;
        $domainContext = $domainObject->getContext();
        while ($domainContext instanceof Project) {
            $up++;
            $domainContext = $domainContext->getContext();
        }
        $this->assertEquals($upExpected, $up);

        $domainChild = $domainObject->getAssessables()->first();
        while ($domainChild instanceof Project) {
            $down++;
            $domainChild = $domainChild->getAssessables()->first();
        }
        $this->assertEquals($downExpected, $down);
    }


    /**
     * Verify that a project tree that touches max depth can be translated.
     */
    public function testDataProjectTreeTouchingMaxDepth()
    {
        $this->translator->setMaxProjectLevels(2);

        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);

        $q1 = (new QuizModel())->setId(3)->setAssessmentModel((new AssessmentModel())->setId(2));
        $q1->getAssessmentModel()->setSubObject($q1);
        $q1->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[2] = $q1->getAssessmentModel();

        $this->assertInstanceOf(Project::class, $this->translator->translateToDomainModel($p1));
    }


    /**
     * Ensure that a project assessment, if somehow in multiple projects,
     * is the same object in both projects.
     */
    public function testDataProjectTreeAssessmentInMultipleProjects()
    {
        // Create three projects: p1, p2, p3
        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);
        $p2 = (new ProjectModel())->setId(2)->setAssessmentModel((new AssessmentModel())->setId(2))->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2->getAssessmentModel()->setSubObject($p2);
        $p3 = (new ProjectModel())->setId(3)->setAssessmentModel((new AssessmentModel())->setId(3))->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p3->getAssessmentModel()->setSubObject($p3);

        // Put p2 and p3 in p1
        $p2->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[2] = $p2->getAssessmentModel();
        $p3->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[3] = $p3->getAssessmentModel();

        // Create quiz q4, in both p2 and p3
        $q4 = (new QuizModel())->setId(4)->setAssessmentModel((new AssessmentModel())->setId(4));
        $q4->getAssessmentModel()->setSubObject($q4);
        $p2->getAssessments()[4] = $q4->getAssessmentModel();
        $p3->getAssessments()[4] = $q4->getAssessmentModel();
        // An assessment may be in several projects, but may only have one context
        $q4->getAssessmentModel()->setContextProject($p3);

        /** @var Project $p1DomainModel */
        $p1DomainModel = $this->translator->translateToDomainModel($p1);

        /** @var Project $p2DomainModel */
        $p2DomainModel = $p1DomainModel->getAssessables()[2];

        /** @var Project $p3DomainModel */
        $p3DomainModel = $p1DomainModel->getAssessables()[3];

        $this->assertInstanceOf(Project::class, $p1DomainModel);
        $this->assertInstanceOf(Project::class, $p2DomainModel);
        $this->assertInstanceOf(Project::class, $p3DomainModel);

        $p2q4DomainModel = $p2DomainModel->getAssessables()[4];
        $p3q4DomainModel = $p3DomainModel->getAssessables()[4];
        $this->assertInstanceOf(Quiz::class, $p2q4DomainModel);
        $this->assertInstanceOf(Quiz::class, $p3q4DomainModel);
        $this->assertSame($p2q4DomainModel, $p3q4DomainModel);
    }


    /**
     * Verify that a project tree with a cyclic reference cannot be translated.
     *
     *
     */
    public function testDataProjectTreeCyclicReference()
    {
        $this->expectException(\LogicException::class);
        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);

        $p2 = (new ProjectModel())->setId(2)->setAssessmentModel((new AssessmentModel())->setId(2))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2->getAssessmentModel()->setSubObject($p2);
        $p2->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[2] = $p2->getAssessmentModel();

        // Add a cyclic reference (p2 is within p1, put p1 within p2)
        $p1->getAssessmentModel()->setContextProject($p2);

        $this->translator->translateToDomainModel($p1);
    }


    /**
     * Verify that a project tree that is too deep cannot be translated.
     *
     *
     */
    public function testDataProjectTreeExceedingMaxDepth()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->translator->setMaxProjectLevels(1);

        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);

        $q1 = (new QuizModel())->setId(3)->setAssessmentModel((new AssessmentModel())->setId(2))
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $q1->getAssessmentModel()->setSubObject($q1);
        $q1->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[2] = $q1->getAssessmentModel();

        $this->translator->translateToDomainModel($p1);
    }


    /**
     * Verify that a project tree cannot contain a project at its bottom level.
     *
     *
     */
    public function testDataProjectTreeDisallowsBottomLevelProject()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->translator->setMaxProjectLevels(2);

        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);

        $p2 = (new ProjectModel())->setId(2)->setAssessmentModel((new AssessmentModel())->setId(2))->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2->getAssessmentModel()->setSubObject($p2);
        $p2->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[2] = $p2->getAssessmentModel();

        $this->translator->translateToDomainModel($p1);
    }


    /**
     * @param ProjectModel $projectModel
     * @param int          $id
     * @param int          $up
     * @param int          $treeSize    Total expected tree size
     * @param int          $descendants Expected descendant count
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerBranchedDataProjectTree')]
    public function testBranchedDataProjectTree(ProjectModel $projectModel, int $id, int $up, int $treeSize, int $descendants)
    {
        /** @var Project $domainObject */
        $domainObject = $this->translator->translateToDomainModel($projectModel);
        $this->assertEquals($id, $domainObject->getId());

        // How far up to root are we?
        $upActual = 0;
        $root     = $domainObject;
        while ($root->getContext() instanceof Project) {
            $upActual++;
            $root = $root->getContext();
        }
        $this->assertEquals($up, $upActual);

        // Is the tree as big as expected?
        $this->assertEquals($treeSize, 1 + $this->countSubAssessmentsRecursiveProjectObject($root));

        $this->assertEquals($descendants, $this->countSubAssessmentsRecursiveProjectObject($domainObject));
    }


    /**
     * @param Project $projectObject
     * @param int     $id           Id of this project node
     * @param int     $upExpected   How many nodes above this one?
     * @param int     $downExpected How many nodes below this one?
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLinearDomainProjectTree')]
    public function testLinearDomainProjectTree(Project $projectObject, int $id, int $upExpected, int $downExpected)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($projectObject);
        $this->assertEquals($id, $projectModel->getAssessmentModel()->getId());

        $upActual       = $downActual = 0;
        $contextProject = $projectModel->getAssessmentModel()->getContextProject();
        while ($contextProject instanceof ProjectModel) {
            $upActual++;
            $contextProject = $contextProject->getAssessmentModel()->getContextProject();
        }
        $this->assertEquals($upExpected, $upActual);

        /** @var AssessmentModel $dataChild */
        $dataChild = $projectModel->getAssessments()->first();
        while ($dataChild instanceof AssessmentModel && $dataChild->getSubObject() instanceof ProjectModel) {
            $downActual++;
            /** @var ProjectModel $subObject */
            $subObject = $dataChild->getSubObject();
            $dataChild = $subObject->getAssessments()->first();
        }
        $this->assertEquals($downExpected, $downActual);
    }


    /**
     * Verify that a project tree that touches max depth can be translated.
     */
    public function testDomainProjectTreeTouchingMaxDepth()
    {
        $this->translator->setMaxProjectLevels(2);

        $p1 = (new Project())->setId(1)
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $q2 = (new Quiz())->setId(2);

        $p1->getAssessables()[2] = $q2;
        $q2->setContext($p1);

        $this->assertInstanceOf(ProjectModel::class, $this->translator->translateToDataModel($p1));
    }


    /**
     * Verify that a project tree with a cyclic reference cannot be translated.
     *
     *
     */
    public function testDomainProjectTreeCyclicReference()
    {
        $this->expectException(\LogicException::class);
        $p1 = (new Project())->setId(1)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        $p2 = (new Project())->setId(2)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        $p1->getAssessables()[2] = $p2;
        $p2->setContext($p1);

        // Add a cyclic reference (p2 is within p1, put p1 within p2)
        $p2->getAssessables()[1] = $p1;
        $p1->setContext($p2);

        $this->translator->translateToDataModel($p1);
    }


    /**
     * Verify that a project tree that is too deep cannot be translated.
     *
     *
     */
    public function testDomainProjectTreeExceedingMaxDepth()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->translator->setMaxProjectLevels(1);

        $p1 = (new Project())->setId(1)
            ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $q2 = (new Quiz())->setId(2);

        $p1->getAssessables()[2] = $q2;
        $q2->setContext($p1);

        $this->translator->translateToDataModel($p1);
    }


    /**
     * Verify that a project tree cannot contain a project at its bottom level.
     *
     *
     */
    public function testDomainProjectTreeDisallowsBottomLevelProject()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->translator->setMaxProjectLevels(2);

        $p1 = (new Project())->setId(1)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2 = (new Project())->setId(2)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        $p1->getAssessables()[2] = $p2;
        $p2->setContext($p1);

        $this->translator->translateToDataModel($p1);
    }


    /**
     * Ensure that a project assessment, if somehow in multiple projects,
     * is the same object in both projects.
     */
    public function testDomainProjectTreeAssessmentInMultipleProjects()
    {
        // Create three projects: p1, p2, p3
        $p1 = (new Project())->setId(1)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2 = (new Project())->setId(2)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p3 = (new Project())->setId(3)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        // Put p2 and p3 in p1
        $p1->getAssessables()[2] = $p2;
        $p1->getAssessables()[3] = $p3;
        $p2->setContext($p1);
        $p3->setContext($p1);

        // Create quiz q4, in both p2 and p3
        $q4 = (new Quiz())->setId(4);
        $p2->getAssessables()[4] = $q4;
        $p3->getAssessables()[4] = $q4;
        // An assessment may be in several projects, but may only have one context
        $q4->setContext($p3);

        /** @var ProjectModel $p1DataModel */
        $p1DataModel = $this->translator->translateToDataModel($p1);

        /** @var ProjectModel $p2DataModel */
        $p2DataModel = $p1DataModel->getAssessments()[2]->getProject();

        /** @var ProjectModel $p3DataModel */
        $p3DataModel = $p1DataModel->getAssessments()[3]->getProject();

        $this->assertInstanceOf(ProjectModel::class, $p1DataModel);
        $this->assertInstanceOf(ProjectModel::class, $p2DataModel);
        $this->assertInstanceOf(ProjectModel::class, $p3DataModel);

        /** @var QuizModel $p2q4DataModel */
        $p2q4DataModel = $p2DataModel->getAssessments()[4]->getQuiz();

        /** @var QuizModel $p3q4DataModel */
        $p3q4DataModel = $p3DataModel->getAssessments()[4]->getQuiz();

        $this->assertInstanceOf(QuizModel::class, $p2q4DataModel);
        $this->assertInstanceOf(QuizModel::class, $p3q4DataModel);
        $this->assertSame($p2q4DataModel, $p3q4DataModel);
    }


    /**
     * @param Project $projectObject
     * @param int     $id
     * @param int     $up
     * @param int     $treeSize    Total expected tree size
     * @param int     $descendants Expected descendant count
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerBranchedDomainProjectTree')]
    public function testBranchedDomainProjectTree(Project $projectObject, int $id, int $up, int $treeSize, int $descendants)
    {
        /** @var ProjectModel $projectModel */
        $projectModel = $this->translator->translateToDataModel($projectObject);
        $this->assertEquals($id, $projectModel->getAssessmentModel()->getId());

        // How far up to root are we?
        $upActual    = 0;
        $rootProject = $projectModel;
        while ($rootProject->getAssessmentModel()->getContextProject() instanceof ProjectModel) {
            $upActual++;
            $rootProject = $rootProject->getAssessmentModel()->getContextProject();
        }
        $this->assertEquals($up, $upActual);

        // Is the tree as big as expected?
        $this->assertEquals($treeSize, 1 + $this->countSubAssessmentsRecursiveProjectModel($rootProject));

        $this->assertEquals($descendants, $this->countSubAssessmentsRecursiveProjectModel($projectModel));
    }


    /**
     * Returns the number of project sub-assessments recursively
     *
     * @param ProjectModel $projectModel
     * @return int
     * @throws \UnexpectedValueException
     */
    private function countSubAssessmentsRecursiveProjectModel(ProjectModel $projectModel): int
    {
        $count = $projectModel->getAssessments()->count();
        foreach ($projectModel->getAssessments() as $subAssessment) {
            $subObject = $subAssessment->getSubObject();
            if ($subObject instanceof ProjectModel) {
                $count += $this->countSubAssessmentsRecursiveProjectModel($subObject);
            }
        }
        return $count;
    }


    /**
     * Returns the number of project sub-assessments recursively
     *
     * @param Project $projectObject
     * @return int
     */
    private function countSubAssessmentsRecursiveProjectObject(Project $projectObject): int
    {
        $count = $projectObject->getAssessables()->count();
        foreach ($projectObject->getAssessables() as $subAssessable) {
            if ($subAssessable instanceof Project) {
                $count += $this->countSubAssessmentsRecursiveProjectObject($subAssessable);
            }
        }
        return $count;
    }


    /**
     * Returns a tree with a single branch of 5 nodes, from 1 (root) to 5 (leaf)
     *
     * @return array
     */
    public static function providerLinearDataProjectTree(): array
    {
        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);

        $p2 = (new ProjectModel())->setId(2)->setAssessmentModel((new AssessmentModel())->setId(2))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2->getAssessmentModel()->setSubObject($p2);
        $p2->getAssessmentModel()->setContextProject($p1);
        $p1->getAssessments()[2] = $p2->getAssessmentModel();

        $p3 = (new ProjectModel())->setId(3)->setAssessmentModel((new AssessmentModel())->setId(3))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p3->getAssessmentModel()->setSubObject($p3);
        $p3->getAssessmentModel()->setContextProject($p2);
        $p2->getAssessments()[3] = $p3->getAssessmentModel();

        $p4 = (new ProjectModel())->setId(4)->setAssessmentModel((new AssessmentModel())->setId(4))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p4->getAssessmentModel()->setSubObject($p4);
        $p4->getAssessmentModel()->setContextProject($p3);
        $p3->getAssessments()[4] = $p4->getAssessmentModel();

        $p5 = (new ProjectModel())->setId(5)->setAssessmentModel((new AssessmentModel())->setId(5))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p5->getAssessmentModel()->setSubObject($p5);
        $p5->getAssessmentModel()->setContextProject($p4);
        $p4->getAssessments()[5] = $p5->getAssessmentModel();

        return [
            [$p1, 1, 0, 4],
            [$p2, 2, 1, 3],
            [$p3, 3, 2, 2],
            [$p4, 4, 3, 1],
            [$p5, 5, 4, 0],
        ];
    }


    /**
     * Returns a tree with 5 nodes, from 1 (root) to 5 (leaf), with two forks
     *
     * @return array
     */
    public static function providerBranchedDataProjectTree(): array
    {
        $p1 = (new ProjectModel())->setId(1)->setAssessmentModel((new AssessmentModel())->setId(1))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p1->getAssessmentModel()->setSubObject($p1);
        $p2 = (new ProjectModel())->setId(2)->setAssessmentModel((new AssessmentModel())->setId(2))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2->getAssessmentModel()->setSubObject($p2);
        $p3 = (new ProjectModel())->setId(3)->setAssessmentModel((new AssessmentModel())->setId(3))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p3->getAssessmentModel()->setSubObject($p3);
        $p4 = (new ProjectModel())->setId(4)->setAssessmentModel((new AssessmentModel())->setId(4))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p4->getAssessmentModel()->setSubObject($p4);
        $p5 = (new ProjectModel())->setId(5)->setAssessmentModel((new AssessmentModel())->setId(5))
                                  ->setAssessments(new GenericCollection(AssessmentModel::class))
                                  ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p5->getAssessmentModel()->setSubObject($p5);

        // #2 and #3 are children of #1
        $p1->getAssessments()[2] = $p2->getAssessmentModel();
        $p1->getAssessments()[3] = $p3->getAssessmentModel();
        $p2->getAssessmentModel()->setContextProject($p1);
        $p3->getAssessmentModel()->setContextProject($p1);

        // #4 and #5 are children of #2
        $p2->getAssessments()[4] = $p4->getAssessmentModel();
        $p2->getAssessments()[5] = $p5->getAssessmentModel();
        $p4->getAssessmentModel()->setContextProject($p2);
        $p5->getAssessmentModel()->setContextProject($p2);

        // [node, nodeId, countUp, countTreeSize, countDescendants]
        return [
            [$p1, 1, 0, 5, 4],
            [$p2, 2, 1, 5, 2],
            [$p3, 3, 1, 5, 0],
            [$p4, 4, 2, 5, 0],
            [$p5, 5, 2, 5, 0],
        ];
    }


    /**
     * Returns a tree with a single branch of 5 nodes, from 1 (root) to 5 (leaf)
     *
     * @return array
     */
    public static function providerLinearDomainProjectTree(): array
    {
        $p1 = (new Project())->setId(1)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2 = (new Project())->setId(2)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p3 = (new Project())->setId(3)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p4 = (new Project())->setId(4)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p5 = (new Project())->setId(5)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        $p1->getAssessables()[2] = $p2;
        $p2->setContext($p1);

        $p2->getAssessables()[3] = $p3;
        $p3->setContext($p2);

        $p3->getAssessables()[4] = $p4;
        $p4->setContext($p3);

        $p4->getAssessables()[5] = $p5;
        $p5->setContext($p4);

        // [Initial translation node, node id, ancestor count, descendant count]
        return [
            [$p1, 1, 0, 4],
            [$p2, 2, 1, 3],
            [$p3, 3, 2, 2],
            [$p4, 4, 3, 1],
            [$p5, 5, 4, 0],
        ];
    }


    /**
     * Returns a tree with 5 nodes, from 1 (root) to 5 (leaf), with two forks
     *
     * @return array
     */
    public static function providerBranchedDomainProjectTree(): array
    {
        $p1 = (new Project())->setId(1)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p2 = (new Project())->setId(2)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p3 = (new Project())->setId(3)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p4 = (new Project())->setId(4)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $p5 = (new Project())->setId(5)
                             ->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);

        // #2 and #3 are children of #1
        $p1->getAssessables()[2] = $p2;
        $p1->getAssessables()[3] = $p3;
        $p2->setContext($p1);
        $p3->setContext($p1);

        // #4 and #4 are children of #2
        $p2->getAssessables()[4] = $p4;
        $p2->getAssessables()[5] = $p5;
        $p4->setContext($p2);
        $p5->setContext($p2);

        // [node, nodeId, countUp, countTreeSize, countDescendants]
        return [
            [$p1, 1, 0, 5, 4],
            [$p2, 2, 1, 5, 2],
            [$p3, 3, 1, 5, 0],
            [$p4, 4, 2, 5, 0],
            [$p5, 5, 2, 5, 0],
        ];
    }
}
