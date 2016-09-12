<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\DataModel\Specification\Unit;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitTranslator;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Test for the unit specification translator
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UnitTranslator
     */
    protected $unitTranslator;

    /**
     * Sets up course translator
     */
    protected function setUp()
    {
        $this->unitTranslator = new UnitTranslator();
    }

    /**
     * Test the translation of a Unit Specification to its data model representation.
     */
    public function testTranslateToDataModel()
    {
        // Create a basic Course Specification data model for the course data model to live in
        $courseSpecFolder = new FolderModel();
        $courseSpecFolder->setId(51)->setName('Houses');

        $courseDataModel = new CourseDataModel();
        $courseDataModel->setId(3)
                           ->setFolder($courseSpecFolder);

        // Create a Unit Specification
        $unitSpecFolder = new FolderModel();
        $unitSpecFolder->setId(52)->setName('Fox House');

        $unitSpec = new UnitSpecification();
        $unitSpec->setId(12)
                 ->setFolder($unitSpecFolder)
                 ->setCreatedAt(new Alaress_Date_Format('2015-10-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                 ->setUpdatedAt(new Alaress_Date_Format('2015-10-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                 ->setDeletedAt(new Alaress_Date_NullDate());

        // Unit Specification's Assessables
        $unitTask1 = new TaskModel();
        $unitTask1->setId(1)->setTitle('Fox House Task 1');

        $unitTask2 = new TaskModel();
        $unitTask2->setId(2)->setTitle('Fox House Task 2');

        $unitSpec->getAssessables()->add($unitTask1)->add($unitTask2);

        // Translate!
        $unitDataModel = $this->unitTranslator->translateToDataModel($unitSpec, $courseDataModel);

        // Verify that some of these things are the same
        $this->assertEquals($unitDataModel->getId(), $unitSpec->getId());
        $this->assertSame($unitDataModel->getFolder(), $unitSpec->getFolder());
        $this->assertSame($unitDataModel->getCourse(), $courseDataModel);
        $this->assertEquals($unitDataModel->getCreatedAt()->getDatabase(), $unitSpec->getCreatedAt()->getDatabase());
        $this->assertEquals($unitDataModel->getUpdatedAt()->getDatabase(), $unitSpec->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $unitDataModel->getDeletedAt());

        // Translation of assessables (if present) gets tested in a different test.
        // Just verify that counts are the same.
        $this->assertCount(2, $unitDataModel->getAssessables());
    }

    /**
     * Test the translation of a Unit Specification's data model to its domain model representation.
     */
    public function testTranslateToDomainModel()
    {
        // Basic course spec data model
        $courseDataModel = new CourseDataModel();
        $courseDataModel->setId(11)
                        ->setFolder((new FolderModel())->setId(51)->setName('Houses'))
                        ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                        ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                        ->setDeletedAt(new Alaress_Date_NullDate());

        // Unit spec data model
        $unitDataModel = new UnitDataModel();
        $unitDataModel->setId(1)
                      ->setCourse($courseDataModel)
                      ->setFolder((new FolderModel())->setId(52)->setName('Fox House'))
                      ->setCreatedAt(new Alaress_Date_Format('2015-10-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                      ->setUpdatedAt(new Alaress_Date_Format('2015-10-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                      ->setDeletedAt(new Alaress_Date_NullDate());

        // Assessables
        $unitTask1 = new TaskModel();
        $unitTask1->setId(1)->setTitle('Fox House Task 1');

        $unitTask2 = new TaskModel();
        $unitTask2->setId(2)->setTitle('Fox House Task 2');

        $unitDataModel->getAssessables()->add($unitTask1)->add($unitTask2);

//        $courseDataModel->getUnits()->add($unitDataModel);

        // Translate!
        $unitSpec = $this->unitTranslator->translateToDomainModel($unitDataModel);

        // Verify that details are the same
        $this->assertEquals($unitSpec->getId(), $unitDataModel->getId());
        $this->assertSame($unitSpec->getFolder(), $unitDataModel->getFolder());
        $this->assertEquals($unitSpec->getCreatedAt()->getDatabase(), $unitDataModel->getCreatedAt()->getDatabase());
        $this->assertEquals($unitSpec->getUpdatedAt()->getDatabase(), $unitDataModel->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $unitDataModel->getDeletedAt());

        // Translation of assessables (if present) gets tested in a different test.
        // Just verify that counts are the same.
        $this->assertCount(2, $unitSpec->getAssessables());
    }
}
