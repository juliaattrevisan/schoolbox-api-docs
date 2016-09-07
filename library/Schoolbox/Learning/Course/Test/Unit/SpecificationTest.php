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
 * Tests for Unit Specification objects
 */
class SpecificationTest extends \PHPUnit_Framework_TestCase
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
        // Dates are all null ADFs
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $spec->getCreatedAt());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $spec->getUpdatedAt());
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $spec->getDeletedAt());
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

        // Clone deep-clones dates and assessables (dependent objects)
        $clone = clone $spec;
        $clone->getCreatedAt()->addMonths(1);
        $clone->getUpdatedAt()->addMonths(1);
        $clone->getDeletedAt()->addMonths(1);
        $clone->getAssessables()->first()->setId(777);
        $clone->getAssessables()->slice(0, 1);

        // Verify dependent attributes
        $this->assertEquals('2016-09-01 00:00:00', $spec->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-01 00:00:00', $clone->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-09-02 00:00:00', $spec->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-02 00:00:00', $clone->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-09-03 00:00:00', $spec->getDeletedAt()->getDatabase());
        $this->assertEquals('2016-10-03 00:00:00', $clone->getDeletedAt()->getDatabase());
        $this->assertCount(2, $spec->getAssessables());
        $this->assertEquals(999, $spec->getAssessables()->first()->getId());
        $this->assertEquals(888, $spec->getAssessables()->last()->getId());
        $this->assertCount(1, $clone->getAssessables());
        $this->assertEquals(777, $clone->getAssessables()->first()->getId());

        // Clone doesn't deep-clone folder (these are non-dependent objects)
        // TODO determine whether folder should be nulled on clone - only one unit spec may exist per folder
        $clone->getFolder()->setId(6789);

        // Verify non-dependent attributes
        $this->assertEquals(6789, $spec->getFolder()->getId());
        $this->assertEquals(6789, $clone->getFolder()->getId());
    }
}
