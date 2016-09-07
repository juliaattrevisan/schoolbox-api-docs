<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for Course Implementation objects
 */
class ImplementationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that object is in a particular state when created.
     */
    public function testConstruct()
    {
        $impl = new CourseImplementation();

        // Doesn't have a folder yet
        $this->assertNull($impl->getFolder());
        // Doesn't have a spec yet
        $this->assertNull($impl->getSpecification());
        // Units is an empty collection
        $this->assertInstanceOf(Collection::class, $impl->getUnits());
        $this->assertCount(0, $impl->getUnits());
        // Dates are all null ADFs
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getCreatedAt());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getUpdatedAt());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $impl->getDeletedAt());
    }

    /**
     * Test that this object's attributes are set appropriately.
     */
    public function testAttributes()
    {
        $impl = new CourseImplementation();
        $impl
            ->setFolder((new FolderModel())->setId(9876))
            ->setSpecification((new CourseSpecification())->setId(12345))
            ->setCreatedAt(new Alaress_Date_Format('2016-09-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new Alaress_Date_Format('2016-09-02 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDeletedAt(new Alaress_Date_Format('2016-09-03 00:00:00', Alaress_Date_Format::DATABASE_FORMAT));
        $impl->getUnits()
             ->add((new UnitImplementation())->setId(555))
             ->add((new UnitImplementation())->setId(666));

        // Verify attributes
        $this->assertEquals(9876, $impl->getFolder()->getId());
        $this->assertEquals(12345, $impl->getSpecification()->getId());
        $this->assertCount(2, $impl->getUnits());
        $this->assertEquals(555, $impl->getUnits()->first()->getId());
        $this->assertEquals(666, $impl->getUnits()->last()->getId());
        $this->assertEquals('2016-09-01 00:00:00', $impl->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $impl->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $impl->getDeletedAt()->getDatabase());

        // Clone deep-clones dates and units (dependent objects)
        $clone = clone $impl;
        $clone->getCreatedAt()->addMonths(1);
        $clone->getUpdatedAt()->addMonths(1);
        $clone->getDeletedAt()->addMonths(1);
        $clone->getUnits()->first()->setId(777);
        $clone->getUnits()->slice(0, 1);

        $this->assertEquals('2016-09-01 00:00:00', $impl->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-01 00:00:00', $clone->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $impl->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-02 00:00:00', $clone->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $impl->getDeletedAt()->getDatabase());
        $this->assertEquals('2016-10-03 00:00:00', $clone->getDeletedAt()->getDatabase());
        $this->assertCount(2, $impl->getUnits());
        $this->assertEquals(555, $impl->getUnits()->first()->getId());
        $this->assertEquals(666, $impl->getUnits()->last()->getId());
        $this->assertCount(1, $clone->getUnits());
        $this->assertEquals(777, $clone->getUnits()->first()->getId());

        // Clone doesn't deep-clone folder or spec (these are non-dependent objects)
        $clone->getFolder()->setId(6789);
        $clone->getSpecification()->setId(54321);

        $this->assertEquals(6789, $impl->getFolder()->getId());
        $this->assertEquals(6789, $clone->getFolder()->getId());
        $this->assertEquals(54321, $impl->getSpecification()->getId());
        $this->assertEquals(54321, $clone->getSpecification()->getId());
    }
}
