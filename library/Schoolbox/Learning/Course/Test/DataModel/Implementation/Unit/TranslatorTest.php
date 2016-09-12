<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\DataModel\Implementation\Unit;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementationDataModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Translator as CourseTranslator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementationDataModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationDataModel;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Test for the unit implementation translator.
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UnitTranslator
     */
    protected $unitTranslator;

    /**
     * Sets up unit translator
     */
    protected function setUp()
    {
        $this->unitTranslator = new UnitTranslator();
    }

    /**
     * Test the translation of a Unit Implementation to its data model representation.
     */
    public function testTranslateToDataModel()
    {
        // Create a basic Course Implementation
        $courseImplFolder = new FolderModel();
        $courseImplFolder->setId(237)->setName('9AH French');

        $courseImpl = new CourseImplementation();
        $courseImpl->setId(66)->setFolder($courseImplFolder);

        // Create a basic Unit Specification data model for the course data model to live in
        $unitSpecFolder = new FolderModel();
        $unitSpecFolder->setId(54)->setName('Oak House');

        $unitSpec = new UnitSpecification();
        $unitSpec->setId(41)->setFolder($unitSpecFolder);

        // Create a Unit Implementation
        $unitImpl = new UnitImplementation();
        $unitImpl->setId(12)
                 ->setTitle('Something')
                 ->setSpecification($unitSpec)
                 ->setCourse($courseImpl)
                 ->setOpenDate(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                 ->setDueDate(new Alaress_Date_Format('2015-11-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                 ->setCreatedAt(new Alaress_Date_Format('2015-10-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                 ->setUpdatedAt(new Alaress_Date_Format('2015-10-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                 ->setDeletedAt(new Alaress_Date_NullDate());

        $unitImpl->getAssessables()->add(new TaskModel())->add(new TaskModel());

        // Translate!
        $unitDataModel = $this->unitTranslator->translateToDataModel($unitImpl);

        // Verify that some of these things are the same
        $this->assertEquals($unitImpl->getId(), $unitDataModel->getId());
        $this->assertEquals($unitImpl->getTitle(), $unitDataModel->getTitle());
        $this->assertEquals($unitImpl->getSpecification()->getId(), $unitDataModel->getSpecification()->getId());
        $this->assertSame($unitImpl->getCourse()->getId(), $unitDataModel->getCourse()->getId());
        $this->assertEquals($unitImpl->getOpenDate()->getDatabase(), $unitDataModel->getOpenDate()->getDatabase());
        $this->assertEquals($unitImpl->getDueDate()->getDatabase(), $unitDataModel->getDueDate()->getDatabase());
        $this->assertEquals($unitImpl->getCreatedAt()->getDatabase(), $unitDataModel->getCreatedAt()->getDatabase());
        $this->assertEquals($unitImpl->getUpdatedAt()->getDatabase(), $unitDataModel->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $unitDataModel->getDeletedAt());

        // Translation of assessables (if present) gets tested in a different test.
        // Just verify that counts are the same.
        $this->assertCount(2, $unitDataModel->getAssessables());
    }

//    /**
//     * Test the translation of a Unit Specification's data model to its domain model representation.
//     */
//    public function testTranslateToDomainModel()
//    {
//        // Basic course spec data model
//        $courseDataModel = new CourseDataModel();
//        $courseDataModel->setId(11)
//                        ->setFolder((new FolderModel())->setId(51)->setName('Houses'))
//                        ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
//                        ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
//                        ->setDeletedAt(new Alaress_Date_NullDate());
//
//        // Unit spec data model
//        $unitDataModel = new UnitDataModel();
//        $unitDataModel->setId(1)
//                      ->setCourse($courseDataModel)
//                      ->setFolder((new FolderModel())->setId(52)->setName('Fox House'))
//                      ->setCreatedAt(new Alaress_Date_Format('2015-10-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
//                      ->setUpdatedAt(new Alaress_Date_Format('2015-10-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
//                      ->setDeletedAt(new Alaress_Date_NullDate());
//
//        // Assessables
//        $unitTask1 = new TaskModel();
//        $unitTask1->setId(1)->setTitle('Fox House Task 1');
//
//        $unitTask2 = new TaskModel();
//        $unitTask2->setId(2)->setTitle('Fox House Task 2');
//
//        $unitDataModel->getAssessables()->add($unitTask1)->add($unitTask2);
//
////        $courseDataModel->getUnits()->add($unitDataModel);
//
//        // Translate!
//        $unitSpec = $this->unitTranslator->translateToDomainModel($unitDataModel);
//
//        // Verify that details are the same
//        $this->assertEquals($unitSpec->getId(), $unitDataModel->getId());
//        $this->assertSame($unitSpec->getFolder(), $unitDataModel->getFolder());
//        $this->assertEquals($unitSpec->getCreatedAt()->getDatabase(), $unitDataModel->getCreatedAt()->getDatabase());
//        $this->assertEquals($unitSpec->getUpdatedAt()->getDatabase(), $unitDataModel->getUpdatedAt()->getDatabase());
//        $this->assertInstanceOf(Alaress_Date_NullDate::class, $unitDataModel->getDeletedAt());
//
//        // Translation of assessables (if present) gets tested in a different test.
//        // Just verify that counts are the same.
//        $this->assertCount(2, $unitSpec->getAssessables());
//    }
}
