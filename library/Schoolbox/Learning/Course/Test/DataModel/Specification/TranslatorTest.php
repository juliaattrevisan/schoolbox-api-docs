<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\DataModel\Specification;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitTranslator;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;;

/**
 * Test for the specification translator
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    public function testTranslateToDataModel()
    {
        // Build my example domain model

        // Create a Course Specification for a Folder
        $courseSpecFolder = new FolderModel();
        $courseSpecFolder->setId(51)->setName('Houses');

        $courseSpec = new CourseSpecification();
        $courseSpec->setFolder($courseSpecFolder)
                   ->setCreatedAt(new Alaress_Date_Format('2015-09-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                   ->setUpdatedAt(new Alaress_Date_Format('2015-09-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                   ->setDeletedAt(new Alaress_Date_NullDate());

//        // Add the Course Spec back to the Folder
//        $courseSpecFolder->setCourseSpecification($courseSpec);


        // Unit Spec: Fox House
        $foxSpecFolder = new FolderModel();
        $foxSpecFolder->setId(52)->setName('Fox House');

        $foxSpec = new UnitSpecification();
        $foxSpec->setFolder($foxSpecFolder)
                ->setCreatedAt(new Alaress_Date_Format('2015-10-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                ->setUpdatedAt(new Alaress_Date_Format('2015-10-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                ->setDeletedAt(new Alaress_Date_NullDate());

        // Fox House Assessables
        $foxTask1 = new TaskModel();
        $foxTask1->setId(1)->setTitle('Fox House Task 1');

        $foxTask2 = new TaskModel();
        $foxTask2->setId(2)->setTitle('Fox House Task 2');

        $foxSpec->getAssessables()->add($foxTask1)->add($foxTask2);


        // Unit Spec: Oak House
        // (This one happens to have been deleted)
        $oakSpecFolder = new FolderModel();
        $oakSpecFolder->setId(54)->setName('Oak House');

        $oakSpec = new UnitSpecification();
        $oakSpec->setFolder($oakSpecFolder)
                ->setCreatedAt(new Alaress_Date_Format('2015-11-01 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                ->setUpdatedAt(new Alaress_Date_Format('2015-11-02 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                ->setDeletedAt(new Alaress_Date_Format('2015-11-03 12:00:00', Alaress_Date_Format::DATABASE_FORMAT));

        // Oak House Assessables
        $oakTask1 = new TaskModel();
        $oakTask1->setId(3)->setTitle('Oak Task 1');

        $oakTask2 = new TaskModel();
        $oakTask2->setId(4)->setTitle('Oak Task 2');

        $oakSpec->getAssessables()->add($oakTask1)->add($oakTask2);


        // Add all unit specs to course spec
        $courseSpec->getUnits()->add($foxSpec)->add($oakSpec);


        // Translate!
        $unitTranslator   = new UnitTranslator();
        $courseTranslator = new CourseTranslator($unitTranslator);

        $courseDataModel  = $courseTranslator->translateToDataModel($courseSpec);

        // Verify that some of these things are the same
        $this->assertSame($courseSpec->getFolder(), $courseDataModel->getFolder());
        $this->assertEquals($courseSpec->getCreatedAt()->getDatabase(), $courseDataModel->getCreatedAt()->getDatabase());
        $this->assertEquals($courseSpec->getUpdatedAt()->getDatabase(), $courseDataModel->getUpdatedAt()->getDatabase());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $courseDataModel->getDeletedAt());

        // Number of units should be the same
        $this->assertCount(2, $courseDataModel->getUnits());

        // Content of units gets tested in the test case for UnitTranslator
    }
}
