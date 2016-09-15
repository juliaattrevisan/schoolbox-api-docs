<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\DataModel\Implementation;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementationDataModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Translator as CourseTranslator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementationDataModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationDataModel;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Test for the Course Implementation translator.
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
        $courseSpec->setId(72)->setFolder($courseSpecFolder);

        // Create a Course Implementation which uses the Course Spec
        $courseImplFolder = new FolderModel();
        $courseImplFolder->setId(237)->setName('9AH French');

        $courseImpl = new CourseImplementation();
        $courseImpl->setSpecification($courseSpec)
                   ->setFolder($courseImplFolder)
                   ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                   ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                   ->setDeletedAt(new Alaress_Date_NullDate());

        // Add unit implementations to course implementation
        $courseImpl->getUnits()->add(new UnitImplementation())->add(new UnitImplementation());

        // Translate!
        $dataModel = $this->courseTranslator->translateToDataModel($courseImpl);

        // Verify that some of these things are the same
        $this->assertSame($courseImpl->getFolder(), $dataModel->getFolder());
        // Course specification - should have the same ID, don't care about anything else
        $this->assertEquals($courseImpl->getSpecification()->getId(), $dataModel->getSpecification()->getId());
        $this->assertEquals($courseImpl->getCreatedAt()->getDatabase(), $dataModel->getCreatedAt()->getDatabase());
        $this->assertEquals($courseImpl->getUpdatedAt()->getDatabase(), $dataModel->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $dataModel->getDeletedAt());

        // Content of units gets tested in the test case for UnitTranslator. Just verify that counts are the same.
        $this->assertCount(2, $dataModel->getUnits());
    }

    /**
     * Test the translation of a Course Implementation's data model to its domain model representation.
     */
    public function testTranslateToDomainModel()
    {
        // Create a data model
        $dataModel = new CourseImplementationDataModel();
        $dataModel->setId(11)
                  ->setFolder((new FolderModel())->setId(51)->setName('Houses'))
                  ->setSpecification((new CourseSpecificationDataModel())->setId(39))
                  ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                  ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                  ->setDeletedAt(new Alaress_Date_NullDate());

        // Add unit data models
        $dataModel->getUnits()->add(new UnitImplementationDataModel())->add(new UnitImplementationDataModel());

        // Translate!
        $courseImpl = $this->courseTranslator->translateToDomainModel($dataModel);

        // Verify that details are the same
        $this->assertSame($dataModel->getFolder(), $courseImpl->getFolder());
        // Course specification - should have the same ID, don't care about anything else
        $this->assertEquals($dataModel->getSpecification()->getId(), $courseImpl->getSpecification()->getId());
        $this->assertEquals($dataModel->getId(), $courseImpl->getId());
        $this->assertEquals($dataModel->getCreatedAt()->getDatabase(), $courseImpl->getCreatedAt()->getDatabase());
        $this->assertEquals($dataModel->getUpdatedAt()->getDatabase(), $courseImpl->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $courseImpl->getDeletedAt());

        // Content of units gets tested in the test case for UnitTranslator. Just verify that counts are the same.
        $this->assertCount(2, $courseImpl->getUnits());
    }
}
