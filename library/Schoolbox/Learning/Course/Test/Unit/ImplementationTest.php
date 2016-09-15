<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\Unit;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for Unit Implementation objects
 */
class ImplementationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that object is in a particular state when created.
     */
    public function testConstruct()
    {
        $impl = new UnitImplementation();

        // Doesn't have a title yet
        $this->assertNull($impl->getTitle());
        // Doesn't have a course yet, and therefore no folder
        $this->assertNull($impl->getCourse());
        $this->assertNull($impl->getFolder());
        // Doesn't have a spec yet
        $this->assertNull($impl->getSpecification());
        // Assessables is an empty collection
        $this->assertInstanceOf(Collection::class, $impl->getAssessables());
        $this->assertCount(0, $impl->getAssessables());
        // Dates are all null ADFs
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getOpenDate());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getDueDate());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getCreatedAt());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getUpdatedAt());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getDeletedAt());
    }

    /**
     * Test that this object's attributes are set appropriately.
     */
    public function testAttributes()
    {
        $impl = new UnitImplementation();
        $impl
            ->setTitle('Something')
            ->setCourse(
                (new CourseImplementation())
                    ->setId(111)
                    ->setFolder((new FolderModel())->setId(9876))
            )
            ->setSpecification((new UnitSpecification())->setId(12345))
            ->setOpenDate(new Alaress_Date_Format('2016-08-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueDate(new Alaress_Date_Format('2016-10-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new Alaress_Date_Format('2016-09-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new Alaress_Date_Format('2016-09-02 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDeletedAt(new Alaress_Date_Format('2016-09-03 00:00:00', Alaress_Date_Format::DATABASE_FORMAT));
        $impl->getAssessables()
             ->add((new Task())->setId(999))
             ->add((new Task())->setId(888));

        // Clone deep-clones dates and units (dependent objects)
        $clone = clone $impl;
        $clone->setTitle('Nothing');
        $clone->getOpenDate()->addMonths(1);
        $clone->getDueDate()->addMonths(1);
        $clone->getCreatedAt()->addMonths(1);
        $clone->getUpdatedAt()->addMonths(1);
        $clone->getDeletedAt()->addMonths(1);
        $clone->getAssessables()->first()->setId(777);
        $clone->getAssessables()->slice(0, 1);

        // Verify dependent attributes
        $this->assertEquals('Nothing', $clone->getTitle());
        $this->assertEquals('Something', $impl->getTitle());
        $this->assertEquals('2016-09-01 00:00:00', $clone->getOpenDate()->getDatabase());
        $this->assertEquals('2016-08-01 00:00:00', $impl->getOpenDate()->getDatabase());
        $this->assertEquals('2016-09-01 00:00:00', $clone->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-01 00:00:00', $impl->getDueDate()->getDatabase());
        $this->assertEquals('2016-11-01 00:00:00', $clone->getDueDate()->getDatabase());
        $this->assertEquals('2016-09-01 00:00:00', $impl->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-01 00:00:00', $clone->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $impl->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-02 00:00:00', $clone->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $impl->getDeletedAt()->getDatabase());
        $this->assertEquals('2016-10-03 00:00:00', $clone->getDeletedAt()->getDatabase());
        $this->assertCount(2, $impl->getAssessables());
        $this->assertEquals(999, $impl->getAssessables()->first()->getId());
        $this->assertEquals(888, $impl->getAssessables()->last()->getId());
        $this->assertCount(1, $clone->getAssessables());
        $this->assertEquals(777, $clone->getAssessables()->first()->getId());

        // Clone doesn't deep-clone course or spec (these are non-dependent objects)
        $clone->getSpecification()->setId(54321);
        $clone->getCourse()->setId(222);
        $clone->getCourse()->getFolder()->setId(6789);

        // Verify non-dependent attributes
        $this->assertEquals(222, $impl->getCourse()->getId());
        $this->assertEquals(6789, $impl->getFolder()->getId());
        $this->assertEquals(54321, $impl->getSpecification()->getId());
        $this->assertEquals(222, $clone->getCourse()->getId());
        $this->assertEquals(6789, $clone->getFolder()->getId());
        $this->assertEquals(54321, $clone->getSpecification()->getId());
    }
}
