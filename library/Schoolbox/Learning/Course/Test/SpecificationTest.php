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
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for Course Specification objects
 */
class SpecificationTest extends TestCase
{
    /**
     * Test that object is in a particular state when created.
     */
    public function testConstruct()
    {
        $spec = new CourseSpecification();

        // Doesn't have a folder yet
        $this->assertNull($spec->getFolder());
        // Units is an empty collection
        $this->assertInstanceOf(Collection::class, $spec->getUnits());
        $this->assertCount(0, $spec->getUnits());
        // Dates are all null
        $this->assertNull($spec->getCreatedAt());
        $this->assertNull($spec->getUpdatedAt());
        $this->assertNull($spec->getDeletedAt());
    }

    /**
     * Test that this object's attributes are set appropriately.
     */
    public function testAttributes()
    {
        $spec = new CourseSpecification();
        $spec
            ->setFolder((new FolderModel())->setId(9876))
            ->setCreatedAt(new Alaress_Date_Format('2016-09-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new Alaress_Date_Format('2016-09-02 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDeletedAt(new Alaress_Date_Format('2016-09-03 00:00:00', Alaress_Date_Format::DATABASE_FORMAT));
        $spec->getUnits()
             ->add((new UnitSpecification())->setId(555))
             ->add((new UnitSpecification())->setId(666));

        // Verify dependent attributes
        $this->assertEquals('2016-09-01 00:00:00', $spec->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $spec->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $spec->getDeletedAt()->getDatabase());
        $this->assertCount(2, $spec->getUnits());
        $this->assertEquals(555, $spec->getUnits()->first()->getId());
        $this->assertEquals(666, $spec->getUnits()->last()->getId());

        // Verify non-dependent attributes
        $this->assertEquals(9876, $spec->getFolder()->getId());
    }
}
