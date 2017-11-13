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
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for Unit Specification objects
 */
class SpecificationTest extends TestCase
{
    /**
     * Test that object is in a particular state when created.
     */
    public function testConstruct()
    {
        $spec = new UnitSpecification();

        // Doesn't have a folder yet
        $this->assertNull($spec->getFolder());
        // Assessables is an empty collection
        $this->assertInstanceOf(Collection::class, $spec->getAssessables());
        $this->assertCount(0, $spec->getAssessables());
        // Dates are all null
        $this->assertNull($spec->getCreatedAt());
        $this->assertNull($spec->getUpdatedAt());
        $this->assertNull($spec->getDeletedAt());

        // This is a spec and not an impl
        $this->assertTrue($spec->isSpecificationContext());
        $this->assertFalse($spec->isImplementationContext());
    }

    /**
     * Test that this object's attributes are set appropriately.
     */
    public function testAttributes()
    {
        $spec = new UnitSpecification();
        $spec
            ->setFolder((new FolderModel())->setId(9876))
            ->setCreatedAt(new Alaress_Date_Format('2016-09-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new Alaress_Date_Format('2016-09-02 00:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDeletedAt(new Alaress_Date_Format('2016-09-03 00:00:00', Alaress_Date_Format::DATABASE_FORMAT));
        $spec->getAssessables()
             ->add((new Task())->setId(999))
             ->add((new Task())->setId(888));


        // Verify dependent attributes
        $this->assertEquals('2016-09-01 00:00:00', $spec->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $spec->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $spec->getDeletedAt()->getDatabase());
        $this->assertCount(2, $spec->getAssessables());
        $this->assertEquals(999, $spec->getAssessables()->first()->getId());
        $this->assertEquals(888, $spec->getAssessables()->last()->getId());

        // Verify non-dependent attributes
        $this->assertEquals(9876, $spec->getFolder()->getId());
    }
}
