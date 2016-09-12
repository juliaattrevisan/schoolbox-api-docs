<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\DataModel\Specification;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitTranslator;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Test for the Course Specification translator.
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CourseTranslator
     */
    protected $courseTranslator;

    /**
     * Sets up course translator
     */
    protected function setUp()
    {
        $this->courseTranslator = new CourseTranslator(new UnitTranslator());
    }

    /**
     * Test the translation of a Course Specification to its data model representation.
     */
    public function testTranslateToDataModel()
    {
        // Create a Course Specification
        $courseSpecFolder = new FolderModel();
        $courseSpecFolder->setId(51)->setName('Houses');

        $courseSpec = new CourseSpecification();
        $courseSpec->setFolder($courseSpecFolder)
                   ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                   ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                   ->setDeletedAt(new Alaress_Date_NullDate());

        // Add unit specs to course spec
        $courseSpec->getUnits()->add(new UnitSpecification())->add(new UnitSpecification());

        // Translate!
        $courseDataModel = $this->courseTranslator->translateToDataModel($courseSpec);

        // Verify that some of these things are the same
        $this->assertSame($courseSpec->getFolder(), $courseDataModel->getFolder());
        $this->assertEquals($courseSpec->getCreatedAt()->getDatabase(), $courseDataModel->getCreatedAt()->getDatabase());
        $this->assertEquals($courseSpec->getUpdatedAt()->getDatabase(), $courseDataModel->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $courseDataModel->getDeletedAt());

        // Content of units gets tested in the test case for UnitTranslator. Just verify that counts are the same.
        $this->assertCount(2, $courseDataModel->getUnits());

    }

    /**
     * Test the translation of a Course Specification's data model to its domain model representation.
     */
    public function testTranslateToDomainModel()
    {
        // Create a data model
        $courseDataModel = new CourseDataModel();
        $courseDataModel->setId(11)
                        ->setFolder((new FolderModel())->setId(51)->setName('Houses'))
                        ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                        ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                        ->setDeletedAt(new Alaress_Date_NullDate());

        // Add unit data models
        $courseDataModel->getUnits()->add(new UnitDataModel())->add(new UnitDataModel());

        // Translate!
        $courseSpec = $this->courseTranslator->translateToDomainModel($courseDataModel);

        // Verify that details are the same
        $this->assertSame($courseDataModel->getFolder(), $courseSpec->getFolder());
        $this->assertEquals($courseDataModel->getId(), $courseSpec->getId());
        $this->assertEquals($courseDataModel->getCreatedAt()->getDatabase(), $courseSpec->getCreatedAt()->getDatabase());
        $this->assertEquals($courseDataModel->getUpdatedAt()->getDatabase(), $courseSpec->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $courseSpec->getDeletedAt());

        // Content of units gets tested in the test case for UnitTranslator. Just verify that counts are the same.
        $this->assertCount(2, $courseSpec->getUnits());
    }
}
