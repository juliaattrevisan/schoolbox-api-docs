<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\Unit;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for Unit Implementation objects
 */
class ImplementationTest extends TestCase
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
        // Doesn't have a spec yet
        $this->assertNull($impl->getSpecification());
        // Assessables is an empty collection
        $this->assertInstanceOf(Collection::class, $impl->getAssessables());
        $this->assertCount(0, $impl->getAssessables());
        // Dates are all null
        $this->assertNull($impl->getOpenDate());
        $this->assertNull($impl->getDueDate());
        $this->assertNull($impl->getCreatedAt());
        $this->assertNull($impl->getUpdatedAt());
        $this->assertNull($impl->getDeletedAt());

        // This is an impl and not a spec
        $this->assertTrue($impl->isImplementationContext());
        $this->assertFalse($impl->isSpecificationContext());
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

        // Verify dependent attributes
        $this->assertEquals('Something', $impl->getTitle());
        $this->assertEquals('2016-08-01 00:00:00', $impl->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-01 00:00:00', $impl->getDueDate()->getDatabase());
        $this->assertEquals('2016-09-01 00:00:00', $impl->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $impl->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $impl->getDeletedAt()->getDatabase());
        $this->assertCount(2, $impl->getAssessables());
        $this->assertEquals(999, $impl->getAssessables()->first()->getId());
        $this->assertEquals(888, $impl->getAssessables()->last()->getId());

        // Verify non-dependent attributes
        $this->assertEquals(111, $impl->getCourse()->getId());
        $this->assertEquals(9876, $impl->getFolder()->getId());
        $this->assertEquals(12345, $impl->getSpecification()->getId());
    }
}
