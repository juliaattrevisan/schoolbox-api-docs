<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType as WorkTypeModel;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;
use Schoolbox\Learning\Assessment\Test\DataModel\TestTrait;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\Resource\Storage\Model as StorageModel;

/**
 * Tests for the assessment data model translator (due work only).
 */
class DueWorkTest extends \PHPUnit_Framework_TestCase
{
    use TestTrait;

    /**
     * Test translation to due work
     */
    public function testTranslateToDataModel()
    {
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
            ->setMarkScheme(new None())
            ->useRubric()
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

        // Test mark scheme
        $this->assertEquals(DueWorkModel\MarkType::TYPE_NONE, $dueWorkModel->getMarkType()->getId());
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateMarkSchemeToDataModelData()
    {
        return [
            [
                (new DueWork())->setMarkScheme(new None()),
                DueWorkModel\MarkType::TYPE_NONE,
                null,
                null,
            ],
            [
                (new DueWork())->setMarkScheme(new Percentage()),
                DueWorkModel\MarkType::TYPE_PERCENT,
                null,
                null,
            ],
            [
                (new DueWork())->setMarkScheme(new TotalMarkWholeNumber(10)),
                DueWorkModel\MarkType::TYPE_TOTAL,
                10,
                null,
            ],
            [
                (new DueWork())->setMarkScheme(new LetterGrade((new DueWorkModel\GradeLevel\Group\Model())->setId(7))),
                DueWorkModel\MarkType::TYPE_LETTER,
                null,
                7,
            ],
        ];
    }

    /**
    /**
     * Test translation of mark scheme.
     *
     * @dataProvider getTestTranslateMarkSchemeToDataModelData
     *
     * @param DueWork $dueWork
     * @param int     $markTypeId
     * @param int     $totalMark
     * @param int     $gradeGroupId
     */
    public function testTranslateMarkSchemeToDataModel(DueWork $dueWork, $markTypeId, $totalMark, $gradeGroupId)
    {
        /** @var DueWorkModel $dueWorkModel */
        $dueWorkModel = $this->translator->translateToDataModel($dueWork, 2);

        // Test type
        $this->assertInstanceOf(DueWorkModel::class, $dueWorkModel);

        // Test due work-specific fields
        $this->assertEquals($markTypeId, $dueWorkModel->getMarkType()->getId());
        $this->assertEquals($totalMark, $dueWorkModel->getMaxMark());
        $this->assertEquals($gradeGroupId, $dueWorkModel->getGradeGroup()->getId());
    }

    /**
     * Data provider for different due work response acceptance settings.
     */
    public function getTestTranslateResponseAcceptanceToDataModelData()
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
     * @dataProvider getTestTranslateResponseAcceptanceToDataModelData
     *
     * @param DueWork $dueWork
     * @param bool    $acceptsOnline
     * @param bool    $acceptsMultiple
     */
    public function testTranslateResponseAcceptanceToDataModel(DueWork $dueWork, $acceptsOnline, $acceptsMultiple)
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
     * Test translating due work specific fields to domain model
     */
    public function testTranslateToDomainModel()
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
                    ->setBasename('a file.txt')
                    ->setHash('1234567890123456789012345678901234567890')
                )
                ->add((new StorageModel())
                    ->setBasename('an image.png')
                    ->setHash('0000000000000000000000000000000000000002')
                )
                ->add((new StorageModel())
                    ->setBasename('a sound.mp3')
                    ->setHash('0000000000000000000000000000000000000001')
                )
            )
            ->setWorkType((new WorkTypeModel())
                ->setId(12)
                ->setName('A dumb type')
            )
            ->setMarkType($this->buildMarkType())
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
    public function getTestTranslateResponseAcceptanceToDomainModelData()
    {
        return [
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(true)
                    ->setAcceptMultiple(true)
                    ->setMarkType($this->buildMarkType()),
                true,
                true,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(true)
                    ->setAcceptMultiple(false)
                    ->setMarkType($this->buildMarkType()),
                true,
                false,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(false)
                    ->setAcceptMultiple(false)
                    ->setMarkType($this->buildMarkType()),
                false,
                false,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setAcceptOnline(false)
                    ->setAcceptMultiple(true)
                    ->setMarkType($this->buildMarkType()),
                false,
                false,
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings to domain model.
     *
     * @dataProvider getTestTranslateResponseAcceptanceToDomainModelData
     *
     * @param DueWorkModel $dataModel
     * @param bool         $acceptsOnline
     * @param bool         $acceptsMultiple
     */
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
        return [
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_NONE)),
                None::class,
                null,
                null,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_PERCENT)),
                Percentage::class,
                null,
                null,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_LETTER))
                    ->setGradeGroup((new DueWorkModel\GradeLevel\Group\Model())->setId(1)),
                LetterGrade::class,
                null,
                1,
            ],
            [
                (new DueWorkModel())
                    ->setAssessmentModel(new AssessmentModel())
                    ->setMarkType($this->buildMarkType(DueWorkModel\MarkType::TYPE_TOTAL))
                    ->setMaxMark(15),
                TotalMarkWholeNumber::class,
                15,
                null
            ],
        ];
    }

    /**
     * Test translation of acceptance response settings.
     *
     * @dataProvider getTestTranslateMarkSchemeToDomainModelData
     *
     * @param DueWorkModel $dataModel
     * @param string       $markSchemeClass
     * @param int          $totalMark
     * @param int          $gradeGroupId
     */
    public function testTranslateMarkSchemeToDomainModel(DueWorkModel $dataModel, $markSchemeClass, $totalMark, $gradeGroupId)
    {
        /** @var DueWork $project */
        $dueWork = $this->translator->translateToDomainModel($dataModel);

        // Test type
        $this->assertInstanceOf(DueWork::class, $dueWork);

        // Test mark scheme and ancillary information
        $scheme = $dueWork->getMarkScheme();
        $this->assertInstanceOf($markSchemeClass, $scheme);
        if ($scheme instanceof TotalMarkWholeNumber) {
            $this->assertEquals($totalMark, $scheme->getTotalMark());
        }
        if ($scheme instanceof LetterGrade) {
            $this->assertEquals($gradeGroupId, $scheme->getGradeGroup()->getId());
        }
    }
}
