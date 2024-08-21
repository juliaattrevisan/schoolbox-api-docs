<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\Test\DataModel\TestTrait;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Model as StorageModel;

/**
 * Tests for the assessment data model translator (due work only).
 */
class DueWorkTest extends TestCase
{
    use TestTrait;

    /**
     * Test translation to due work
     */
    public function testTranslateToDataModel(): void
    {
        $storage1 = new StorageModel();
        $storage1->setFilename('a file');
        $storage1->setExtension('txt');
        $storage1->setHash('1234567890123456789012345678901234567890');

        $storage2 = new StorageModel();
        $storage2->setFilename('an image');
        $storage2->setExtension('jpg');
        $storage2->setHash('0000000000000000000000000000000000000002');

        $storage3 = new StorageModel();
        $storage3->setFilename('a sound');
        $storage3->setExtension('mp3');
        $storage3->setHash('0000000000000000000000000000000000000001');

        $dueWork = (new DueWork())
            // Generic fields
            ->setId(908)
            ->setContext((new Folder())->setId(1))
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
            ->setAttachedFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId(33)
                    ->setFilename('a file')
                    ->setStorage($storage1))
                ->add((new DueWorkFile())
                    ->setId(65)
                    ->setFilename('an image')
                    ->setStorage($storage2))
                ->add((new DueWorkFile())
                    ->setId(82)
                    ->setFilename('a sound')
                    ->setStorage($storage3)))
            ->setWorkType((new WorkType())
                ->setId(12)
                ->setName('A dumb type'))
            ->setMarkScheme(new None())
            ->setUseRubric(true)
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL)
        ;

        /** @var DueWorkModel $dueWorkModel */
        $dueWorkModel = $this->translator->translateToDataModel($dueWork);

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

        $this->assertInstanceOf(SubmissionBox\SingleFile\Model::class, $dueWorkModel->getAttachments()[0]);
        $this->assertEquals('a file', $dueWorkModel->getAttachments()[0]->getFilename());
        $this->assertEquals('a file.txt', $dueWorkModel->getAttachments()[0]->getBasename());
        $this->assertEquals('1234567890123456789012345678901234567890', $dueWorkModel->getAttachments()[0]->getHash());

        $this->assertInstanceOf(SubmissionBox\SingleFile\Model::class, $dueWorkModel->getAttachments()[1]);
        $this->assertEquals('an image', $dueWorkModel->getAttachments()[1]->getFilename());
        $this->assertEquals('an image.jpg', $dueWorkModel->getAttachments()[1]->getBasename());
        $this->assertEquals('0000000000000000000000000000000000000002', $dueWorkModel->getAttachments()[1]->getHash());

        $this->assertInstanceOf(SubmissionBox\SingleFile\Model::class, $dueWorkModel->getAttachments()[2]);
        $this->assertEquals('a sound', $dueWorkModel->getAttachments()[2]->getFilename());
        $this->assertEquals('a sound.mp3', $dueWorkModel->getAttachments()[2]->getBasename());
        $this->assertEquals('0000000000000000000000000000000000000001', $dueWorkModel->getAttachments()[2]->getHash());

        // Test type
        $this->assertInstanceOf(WorkType::class, $dueWorkModel->getAssessmentModel()->getWorkType());
        $this->assertEquals(12, $dueWorkModel->getAssessmentModel()->getWorkType()->getId());
        $this->assertEquals('A dumb type', $dueWorkModel->getAssessmentModel()->getWorkType()->getName());

        // Test mark scheme
        $this->assertEquals(DueWorkModel\MarkType::TYPE_NONE, $dueWorkModel->getMarkType()->getId());
    }


    /**
     * Assert that translating a domain model to a data model, then back to a
     * domain model returns the original domain model.
     */
    public function testTranslateDomainModelBackToDataModel()
    {
        $dueWorkDomainModel = (new DueWork())
            ->setId(908)
            ->setContext((new Folder())->setId(1))
        ;

        $dueWorkDataModel               = $this->translator->translateToDataModel($dueWorkDomainModel);
        $retranslatedDueWorkDomainModel = $this->translator->translateToDomainModel($dueWorkDataModel);
        $this->assertSame($dueWorkDomainModel, $retranslatedDueWorkDomainModel);
    }


    /**
     * Assert that null domain model can be translated to a data model.
     */
    public function testTranslateNullDomainModelToDataModel()
    {
        $nullDueWorkDomainModel = (new DueWork())->setId(null);
        $nullDueWorkDataModel   = $this->translator->translateToDataModel($nullDueWorkDomainModel);
        $this->assertNull($nullDueWorkDataModel->getId());
    }


    /**
     * Data provider for different due work response acceptance settings.
     */
    public static function getTestTranslateMarkSchemeToDataModelData()
    {
        $context = (new Folder())->setId(1);
        return [
            [
                (new DueWork())
                    ->setContext($context)
                    ->setMarkScheme(new None())
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                DueWorkModel\MarkType::TYPE_NONE,
                null,
                null,
            ],
            [
                (new DueWork())
                    ->setContext($context)
                    ->setMarkScheme(new Percentage())
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                DueWorkModel\MarkType::TYPE_PERCENT,
                null,
                null,
            ],
            [
                (new DueWork())
                    ->setContext($context)
                    ->setMarkScheme((new TotalMarkFractional(10)))
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                DueWorkModel\MarkType::TYPE_TOTAL,
                10,
                null,
            ],
            [
                (new DueWork())
                    ->setContext($context)
                    ->setMarkScheme(new LetterGrade(
                        (new DueWorkModel\GradeLevel\Group\Model())
                            ->setId(7)
                            ->setGradeLevels(new GenericCollection(GradeLevel\Model::class, [new DueWorkModel\GradeLevel\Model()]))
                    ))
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                DueWorkModel\MarkType::TYPE_LETTER,
                null,
                7,
            ],
        ];
    }


    /**
     * /**
     * Test translation of mark scheme.
     *
     *
     * @param DueWork  $dueWork
     * @param int      $markTypeId
     * @param null|int $totalMark
     * @param null|int $gradeGroupId
     * @throws \LogicException
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateMarkSchemeToDataModelData')]
    public function testTranslateMarkSchemeToDataModel(DueWork $dueWork, int $markTypeId, ?int $totalMark, ?int $gradeGroupId)
    {
        /** @var DueWorkModel $dueWorkModel */
        $dueWorkModel = $this->translator->translateToDataModel($dueWork);

        // Test type
        $this->assertInstanceOf(DueWorkModel::class, $dueWorkModel);

        // Test due work-specific fields
        if (null === $markTypeId) {
            $this->assertNull($dueWorkModel->getMarkType());
        } else {
            $this->assertEquals($markTypeId, $dueWorkModel->getMarkType()->getId());
        }
        $this->assertEquals($totalMark, $dueWorkModel->getMaxMark());
        if (null === $gradeGroupId) {
            $this->assertNull($dueWorkModel->getGradeGroup());
        } else {
            $this->assertEquals($gradeGroupId, $dueWorkModel->getGradeGroup()->getId());
        }
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public static function getTestTranslateResponseAcceptanceToDataModelData()
    {
        $context = (new Folder())->setId(1);
        return [
            [
                (new DueWork())
                    ->setContext($context)
                    ->setAcceptsMultiple()
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                 true,
                 true,
            ],
            [
                (new DueWork())
                    ->setContext($context)
                    ->setAcceptsSingle()
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                true,
                false,
            ],
            [
                (new DueWork())
                    ->setContext($context)
                    ->setAcceptsNone()
                    ->setWorkType(new WorkType())
                    ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL),
                false,
                false,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     *
     * @param DueWork $dueWork
     * @param bool    $acceptsOnline
     * @param bool    $acceptsMultiple
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateResponseAcceptanceToDataModelData')]
    public function testTranslateResponseAcceptanceToDataModel(DueWork $dueWork, $acceptsOnline, $acceptsMultiple)
    {
        /** @var DueWorkModel $dueWorkModel */
        $dueWorkModel = $this->translator->translateToDataModel($dueWork);

        // Test type
        $this->assertInstanceOf(DueWorkModel::class, $dueWorkModel);

        // Test due work-specific fields
        $this->assertEquals($acceptsOnline, $dueWorkModel->getAcceptOnline());
        $this->assertEquals($acceptsMultiple, $dueWorkModel->getAcceptMultiple());
    }

    /**
     * Test translating due work specific fields to domain model
     */
    public function testTranslateToDomainModel()
    {
        // Upload files: ensure the storage file's name is different to the
        // due work file's name, as the same file can be uploaded more than
        // once, each time with a different name.
        $storage1 = new StorageModel();
        $storage1->setFilename('storage file');
        $storage1->setExtension('txt');
        $storage1->setHash('1234567890123456789012345678901234567890');
        $file1 = (new SubmissionBox\SingleFile\Model())
            ->setStorage($storage1)
            ->setFilename('a file');

        $storage2 = new StorageModel();
        $storage2->setFilename('storage image');
        $storage2->setExtension('jpg');
        $storage2->setHash('0000000000000000000000000000000000000002');
        $file2 = (new SubmissionBox\SingleFile\Model())
            ->setStorage($storage2)
            ->setFilename('an image');

        $storage3 = new StorageModel();
        $storage3->setFilename('storage sound');
        $storage3->setExtension('mp3');
        $storage3->setHash('0000000000000000000000000000000000000001');
        $file3 = (new SubmissionBox\SingleFile\Model())
            ->setStorage($storage3)
            ->setFilename('a sound');

        $dueWorkModel = (new DueWorkModel())
            ->setId(6789)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setAssessmentContext((new Folder())->setId(1))
                    ->setId(33)
                    ->setWorkType((new WorkType())
                        ->setId(12)
                        ->setName('A dumb type'))
            )
            ->setSkipUnsubmitted(false)
            ->setAcceptOnline(true)
            ->setAcceptMultiple(true)
            ->setUseRubric(false)
            ->setOwner($this->teacher2)
            ->setAttachments((new GenericCollection(SubmissionBox\SingleFile\Model::class))
                ->add($file1)
                ->add($file2)
                ->add($file3))
            ->setMarkType($this->buildMarkType())
            ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL)
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

        $this->assertCount(3, $dueWork->getAttachedFiles());

        $this->assertInstanceOf(DueWorkFile::class, $dueWork->getAttachedFiles()[0]);
        $this->assertEquals('a file', $dueWork->getAttachedFiles()[0]->getFilename());
        $this->assertEquals('a file.txt', $dueWork->getAttachedFiles()[0]->getBasename());
        $this->assertEquals('1234567890123456789012345678901234567890', $dueWork->getAttachedFiles()[0]->getHash());

        $this->assertInstanceOf(DueWorkFile::class, $dueWork->getAttachedFiles()[1]);
        $this->assertEquals('an image', $dueWork->getAttachedFiles()[1]->getFilename());
        $this->assertEquals('an image.jpg', $dueWork->getAttachedFiles()[1]->getBasename());
        $this->assertEquals('0000000000000000000000000000000000000002', $dueWork->getAttachedFiles()[1]->getHash());

        $this->assertInstanceOf(DueWorkFile::class, $dueWork->getAttachedFiles()[2]);
        $this->assertEquals('a sound', $dueWork->getAttachedFiles()[2]->getFilename());
        $this->assertEquals('a sound.mp3', $dueWork->getAttachedFiles()[2]->getBasename());
        $this->assertEquals('0000000000000000000000000000000000000001', $dueWork->getAttachedFiles()[2]->getHash());

        $this->assertInstanceOf(WorkType::class, $dueWork->getWorkType());
        $this->assertEquals(12, $dueWork->getWorkType()->getId());
        $this->assertEquals('A dumb type', $dueWork->getWorkType()->getName());
    }


    /**
     * Assert that translating a data model to a domain model, then back to a
     * data model returns the original data model.
     */
    public function testTranslateDataModelBackToDomainModel()
    {
        $dueWorkDataModel = (new DueWorkModel())
            ->setId(6789)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setAssessmentContext((new Folder())->setId(1))
                    ->setId(33)
                    ->setWorkType((new WorkType())
                        ->setId(12)
                        ->setName('A dumb type'))
            );

        $dueWorkDomainModel           = $this->translator->translateToDomainModel($dueWorkDataModel);
        $retranslatedDueWorkDataModel = $this->translator->translateToDataModel($dueWorkDomainModel);
        $this->assertSame($dueWorkDataModel, $retranslatedDueWorkDataModel);
    }


    /**
     * Assert that null data model can be translated to a domain model.
     */
    public function testTranslateNullDataModelToDomainModel()
    {
        $nullDueWorkDataModel   = (new DueWorkModel())->setId(null)->setAssessmentModel(new AssessmentModel());
        $nullDueWorkDomainModel = $this->translator->translateToDomainModel($nullDueWorkDataModel);
        $this->assertNull($nullDueWorkDomainModel->getId());
    }


    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateResponseAcceptanceToDomainModelData()
    {
        $assessmentModel = (new AssessmentModel())
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setAcceptOnline(true)
                    ->setAcceptMultiple(true)
                    ->setMarkType($this->buildMarkType())
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                true,
                true,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setAcceptOnline(true)
                    ->setAcceptMultiple(false)
                    ->setMarkType($this->buildMarkType())
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                true,
                false,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setAcceptOnline(false)
                    ->setAcceptMultiple(false)
                    ->setMarkType($this->buildMarkType())
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                false,
                false,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setAcceptOnline(false)
                    ->setAcceptMultiple(true)
                    ->setMarkType($this->buildMarkType())
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                false,
                false,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings to domain model.
     *
     *
     * @param DueWorkModel $dataModel
     * @param bool         $acceptsOnline
     * @param bool         $acceptsMultiple
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateResponseAcceptanceToDomainModelData')]
    public function testTranslateResponseAcceptanceToDomainModel(DueWorkModel $dataModel, $acceptsOnline, $acceptsMultiple)
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

    /**
     * Data provider for different project mark scheme settings.
     */
    public function getTestTranslateMarkSchemeToDomainModelData()
    {
        $assessmentModel = (new AssessmentModel())
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType())
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                None::class,
                null,
                null,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_PERCENT))
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                Percentage::class,
                null,
                null,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_LETTER))
                    ->setGradeGroup((new DueWorkModel\GradeLevel\Group\Model())->setId(1)->setGradeLevels(
                        new GenericCollection(DueWorkModel\GradeLevel\Model::class, [new DueWorkModel\GradeLevel\Model()])
                    ))
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                LetterGrade::class,
                null,
                1,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel($assessmentModel)
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_TOTAL))
                    ->setMaxMark(15)
                    ->setSelfReviewability(DueWorkModel::SELF_REVIEWABILITY_FULL),
                TotalMarkFractional::class,
                15,
                null
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     *
     * @param DueWorkModel $dataModel
     * @param string       $markSchemeClass
     * @param int          $totalMark
     * @param int          $gradeGroupId
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateMarkSchemeToDomainModelData')]
    public function testTranslateMarkSchemeToDomainModel(DueWorkModel $dataModel, $markSchemeClass, $totalMark, $gradeGroupId)
    {
        /** @var DueWork $dueWork */
        $dueWork = $this->translator->translateToDomainModel($dataModel);

        // Test type
        $this->assertInstanceOf(DueWork::class, $dueWork);

        // Test mark scheme and ancillary information
        $scheme = $dueWork->getMarkScheme();
        $this->assertInstanceOf($markSchemeClass, $scheme);
        if ($scheme instanceof TotalMarkFractional) {
            $this->assertEquals($totalMark, $scheme->getTotalMark());
        }
        if ($scheme instanceof LetterGrade) {
            $this->assertEquals($gradeGroupId, $scheme->getGradeGroup()->getId());
        }
    }
}
