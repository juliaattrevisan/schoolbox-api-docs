<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for Course Implementation objects
 */
class ImplementationTest extends TestCase
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
        // Dates are all null
        $this->assertNull($impl->getCreatedAt());
        $this->assertNull($impl->getUpdatedAt());
        $this->assertNull($impl->getDeletedAt());
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

        // Verify dependent attributes
        $this->assertEquals('2016-09-01 00:00:00', $impl->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $impl->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $impl->getDeletedAt()->getDatabase());
        $this->assertCount(2, $impl->getUnits());
        $this->assertEquals(555, $impl->getUnits()->first()->getId());
        $this->assertEquals(666, $impl->getUnits()->last()->getId());

        // Verify non-dependent attributes
        $this->assertEquals(9876, $impl->getFolder()->getId());
        $this->assertEquals(12345, $impl->getSpecification()->getId());
    }
}
