<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;


use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Tests for the assessment data model translator.
 */
class TranslatorTest extends TestCase
{
    use TestTrait;

    /**
     * Test translation of generic assessment fields
     */
    public function testTranslateGenericFieldsToDataModel()
    {
        $assessment = (new Task())
            ->setId(32)
            ->setSequence(3)
            ->setContext((new Folder())->setId(1))
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_NullDate())
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_NullDate())
            ->setCreatedAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new Alaress_Date_Format('2016-10-15 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDeletedAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Task сказать')
            ->setDescription('Blaargh ничего')
            ->setWeight(1000);

        $assessmentModel = $this->translator->translateToDataModel($assessment)->getAssessmentModel();

        $this->assertEquals(32, $assessmentModel->getId());
        $this->assertEquals(3, $assessmentModel->getSequence());
        $this->assertEquals('2016-10-14 12:00:00', $assessmentModel->getPublishDate()->getDatabase());
        $this->assertEquals('2016-10-14 12:00:00', $assessmentModel->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-18 12:00:00', $assessmentModel->getDueDate()->getDatabase());
        $this->assertEquals('2016-10-18 12:00:00', $assessmentModel->getCloseDate()->getDatabase());
        $this->assertEquals('2016-10-14 12:00:00', $assessmentModel->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-15 12:00:00', $assessmentModel->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $assessmentModel->getDeletedAt()->getDatabase());
        $this->assertEquals('Mr Test Task сказать', $assessmentModel->getTitle());
        $this->assertEquals('Blaargh ничего', $assessmentModel->getDescription());
        // Tasks are always weightless, regardless of what the assessment says
        $this->assertEquals(0, $assessmentModel->getWeight());
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
                    ->setAssessmentContext((new Folder())->setId(1))
                    ->setTitle('Some assessment\'s title')
                    ->setDescription('Blargh')
                    ->setWeight(51)
                    ->setPublishDate(new Alaress_Date_Format('2016-10-10 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setOpenDate(new \Alaress_Date_NullDate())
                    ->setDueDate(new Alaress_Date_Format('2016-10-12 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setCloseDate(new \Alaress_Date_NullDate())
                    ->setCreatedAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setUpdatedAt(new Alaress_Date_Format('2016-10-15 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setDeletedAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
        );

        /** @var IAssessable $assessment */
        $assessment = $this->translator->translateToDomainModel($dataModel);

        $this->assertEquals(401, $assessment->getId());
        $this->assertEquals('Some assessment\'s title', $assessment->getTitle());
        $this->assertEquals('Blargh', $assessment->getDescription());
        // Tasks are always weightless, regardless of what the assessment says
        $this->assertEquals(0, $assessment->getWeight());
        $this->assertEquals('2016-10-10 12:00:00', $assessment->getPublishAt()->getDatabase());
        $this->assertEquals('2016-10-10 12:00:00', $assessment->getOpenAt()->getDatabase());
        $this->assertEquals('2016-10-12 12:00:00', $assessment->getDueAt()->getDatabase());
        $this->assertEquals('2016-10-12 12:00:00', $assessment->getCloseAt()->getDatabase());
        $this->assertEquals('2016-10-14 12:00:00', $assessment->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-15 12:00:00', $assessment->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $assessment->getDeletedAt()->getDatabase());
    }

    /**
     * Test translation of data model with no assessment.
     *
     * @expectedException \UnexpectedValueException
     */
    public function testTranslateEmptyAssessmentToDomainModel()
    {
        $dataModel = (new TaskModel())->setId(15);
        $this->translator->translateToDomainModel($dataModel);
    }

    /**
     * Data for assessment context to data model test.
     *
     * @return array
     */
    public function getTranslateAssessmentContextToDataModelData()
    {
        return [
            [
                (new Task())
                    ->setContext((new Folder())->setId(1))
                    ->setId(1)
                    ->setCreator($this->teacher1),
                Folder::class,
                1
            ],
            [
                (new Task())
                    ->setContext((new UnitSpecification())->setId(3))
                    ->setId(1)
                    ->setCreator($this->teacher1),
                UnitSpecification::class,
                3
            ],
            [
                (new Task())
                    ->setContext((new UnitImplementation())->setId(6))
                    ->setId(1)
                    ->setCreator($this->teacher1),
                UnitImplementation::class,
                6
            ],
        ];
    }

    /**
     * Test translation of assessment context to data model.
     *
     * @dataProvider getTranslateAssessmentContextToDataModelData
     *
     * @param IAssessable $assessment
     * @param string      $contextClass
     * @param int         $contextId
     */
    public function testTranslateAssessmentContextToDataModel(IAssessable $assessment, $contextClass, $contextId)
    {
        $assessmentModel = $this->translator->translateToDataModel($assessment)->getAssessmentModel();
        $this->assertInstanceOf($contextClass, $assessmentModel->getAssessmentContext());
        $this->assertEquals($contextId, $assessmentModel->getAssessmentContext()->getId());
    }

    /**
     * Data for assessment context to domain model test.
     *
     * @return array
     */
    public function getTranslateAssessmentContextToDomainModelData()
    {
        return [
            [
                (new TaskModel())
                    ->setId(1)
                    ->setAssessmentModel(
                        (new AssessmentModel())
                            ->setAssessmentContext((new Folder())->setId(12))
                    ),
                Folder::class,
                12
            ],
            [
                (new TaskModel())
                    ->setId(2)
                    ->setAssessmentModel(
                        (new AssessmentModel())
                            ->setAssessmentContext(
                                (new UnitSpecification())
                                    ->setId(34)
                                    ->setCourse((new CourseSpecification())->setId(1))
                            )
                    ),
                UnitSpecification::class,
                34
            ],
            [
                (new TaskModel())
                    ->setId(2)
                    ->setAssessmentModel(
                        (new AssessmentModel())
                            ->setAssessmentContext((new UnitImplementation())->setId(56))
                    ),
                UnitImplementation::class,
                56
            ],
        ];
    }

    /**
     * Test translation of assessment context to data model.
     *
     * @dataProvider getTranslateAssessmentContextToDomainModelData
     *
     * @param IAssessableDataModel $assessmentModel
     * @param string               $contextClass
     * @param int                  $contextId
     */
    public function testTranslateAssessmentContextToDomainModel(IAssessableDataModel $assessmentModel, $contextClass, $contextId)
    {
        $assessmentModel = $this->translator->translateToDomainModel($assessmentModel);
        $this->assertInstanceOf($contextClass, $assessmentModel->getContext());
        $this->assertEquals($contextId, $assessmentModel->getContext()->getId());
    }

    /**
     * @return array [assessmentModel, class, id, specificationClass, specificationId, specificationContextClass, specificationContextId]
     */
    public function getTranslateSpecificationAssessmentToDomainModelData()
    {
        return [
            [
                // Basic assessment with a specification
                (new TaskModel())
                    ->setAssessmentModel(
                        (new AssessmentModel())
                            ->setId(2)
                            ->setAssessmentContext((new UnitImplementation())->setId(12345))
                            ->setSpecificationAssessment(
                                (new AssessmentModel())
                                    ->setId(1)
                                    ->setAssessmentContext(
                                        (new UnitSpecification())
                                            ->setId(67890)
                                            ->setCourse(
                                                (new CourseSpecification())
                                                    ->setId(123)
                                            )
                                    )
                                    ->setSubObject(
                                        (new TaskModel())
                                            ->setId(1)
                                    )
                            )
                    ),
                Task::class,
                2,
                Task::class,
                1,
                UnitSpecification::class,
                67890
            ],
            [
                // Assessment whose specification's type differs from its own.
                // should still be translated without question
                (new TaskModel())
                    ->setAssessmentModel(
                        (new AssessmentModel())
                            ->setId(2)
                            ->setAssessmentContext((new UnitImplementation())->setId(12345))
                            ->setSpecificationAssessment(
                                (new AssessmentModel())
                                    ->setId(1)
                                    ->setAssessmentContext(
                                        (new UnitSpecification())
                                            ->setId(6666)
                                            ->setCourse(
                                                (new CourseSpecification())
                                                    ->setId(123)
                                            )
                                    )
                                    ->setSubObject(
                                        (new QuizModel())
                                            ->setId(1)
                                    )
                            )
                    ),
                Task::class,
                2,
                Quiz::class,
                1,
                UnitSpecification::class,
                6666
            ],
            [
                // Usually only assessments within a unit implementation context will have a specification.
                // However, that isn't a hard and fast rule.
                (new TaskModel())
                    ->setAssessmentModel(
                        (new AssessmentModel())
                            ->setId(2)
                            ->setAssessmentContext((new Folder())->setId(12345))
                            ->setSpecificationAssessment(
                                (new AssessmentModel())
                                    ->setId(1)
                                    ->setAssessmentContext(
                                        (new Folder())
                                            ->setId(3333)
                                    )
                                    ->setSubObject(
                                        (new TaskModel())
                                            ->setId(1)
                                    )
                            )
                    ),
                Task::class,
                2,
                Task::class,
                1,
                Folder::class,
                3333
            ],
        ];
    }

    /**
     * Test translation of specification assessment to domain model.
     *
     * @dataProvider getTranslateSpecificationAssessmentToDomainModelData
     *
     * @param IAssessableDataModel $assessmentModel
     * @param string               $class
     * @param int                  $id
     * @param string               $specificationClass
     * @param int                  $specificationId
     * @param string               $specificationContextClass
     * @param int                  $specificationContextId
     */
    public function testTranslateSpecificationAssessmentToDomainModel(
        IAssessableDataModel $assessmentModel,
        $class,
        $id,
        $specificationClass,
        $specificationId,
        $specificationContextClass,
        $specificationContextId
    ) {
        $assessment = $this->translator->translateToDomainModel($assessmentModel);

        // Assert assessment
        $this->assertInstanceOf($class, $assessment);
        $this->assertEquals($id, $assessment->getId());

        // Assert specification
        $this->assertInstanceOf($specificationClass, $assessment->getSpecificationAssessment());
        $this->assertEquals($specificationId, $assessment->getSpecificationAssessment()->getId());

        // Assert specification context
        $this->assertInstanceOf($specificationContextClass, $assessment->getSpecificationAssessment()->getContext());
        $this->assertEquals($specificationContextId, $assessment->getSpecificationAssessment()->getContext()->getId());
    }

    /**
     * @return array [assessment, class, id, specificationClass, specificationId, specificationContextClass, specificationContextId]
     */
    public function getTranslateSpecificationAssessmentToDataModelData()
    {
        return [
            [
                // Basic assessment with a specification
                (new Task())
                    ->setId(3)
                    ->setContext((new UnitImplementation())->setId(12345))
                    ->setCreator($this->teacher1)
                    ->setSpecificationAssessment(
                        (new Task())
                            ->setId(2)
                            ->setContext((new UnitSpecification())->setId(54321))
                            ->setCreator($this->teacher1)
                    ),
                3,
                TaskModel::class,
                2,
                UnitSpecification::class,
                54321,
            ],
            [
                // Assessment whose specification's type differs from its own.
                // should still be translated without question
                (new Task())
                    ->setId(33)
                    ->setContext((new UnitImplementation())->setId(12345))
                    ->setCreator($this->teacher1)
                    ->setSpecificationAssessment(
                        (new Quiz())
                            ->setId(22)
                            ->setContext((new UnitSpecification())->setId(6666))
                            ->setCreator($this->teacher1)
                    ),
                33,
                QuizModel::class,
                22,
                UnitSpecification::class,
                6666
            ],
            [
                // Usually only assessments within a unit implementation context will have a specification.
                // However, that isn't a hard and fast rule.
                (new Task())
                    ->setId(3)
                    ->setContext((new Folder())->setId(12345))
                    ->setCreator($this->teacher1)
                    ->setSpecificationAssessment(
                        (new Task())
                            ->setId(2)
                            ->setContext((new Folder())->setId(54321))
                            ->setCreator($this->teacher1)
                    ),
                3,
                TaskModel::class,
                2,
                Folder::class,
                54321,
            ],
        ];
    }

    /**
     * Test translation of specification assessment to data model.
     *
     * @dataProvider getTranslateSpecificationAssessmentToDataModelData
     *
     * @param IAssessable $assessment
     * @param int         $id
     * @param string      $specificationClass
     * @param int         $specificationId
     * @param string      $specificationContextClass
     * @param int         $specificationContextId
     */
    public function testTranslateSpecificationAssessmentToDataModel(
        IAssessable $assessment,
        $id,
        $specificationClass,
        $specificationId,
        $specificationContextClass,
        $specificationContextId
    ) {
        $assessmentModel = $this->translator->translateToDataModel($assessment)->getAssessmentModel();

        // Assert assessment
        $this->assertInstanceOf(AssessmentModel::class, $assessmentModel);
        $this->assertEquals($id, $assessmentModel->getId());

        // Assert specification
        $specificationAssessmentModel = $assessmentModel->getSpecificationAssessment();
        $this->assertInstanceOf($specificationClass, $specificationAssessmentModel->getSubObject());
        $this->assertEquals($specificationId, $specificationAssessmentModel->getId());

        // Assert specification context
        $this->assertInstanceOf($specificationContextClass, $specificationAssessmentModel->getAssessmentContext());
        $this->assertEquals($specificationContextId, $specificationAssessmentModel->getAssessmentContext()->getId());
    }
}
