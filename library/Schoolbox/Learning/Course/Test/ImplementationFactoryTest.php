<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test;

use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\ImplementationFactory as CourseImplementationFactory;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\ImplementationFactory as UnitImplementationFactory;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for the Course Implementation Factory
 */
class ImplementationFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that the Course Implementation is built from spec properly.
     */
    public function testBuild()
    {
        $courseSpec = new CourseSpecification();
        $courseSpec->setFolder((new FolderModel())->setId(4)->setName('A Course Folder'));

        $unitSpec1 = new UnitSpecification();
        $unitSpec1->setFolder((new FolderModel())->setId(5)->setName('Unit 1'))
              ->getAssessables()
              ->add((new Task())->setId(999)->setTitle('Task 1.1'))
              ->add((new Task())->setId(888)->setTitle('Task 1.2'));

        $unitSpec2 = new UnitSpecification();
        $unitSpec2->setFolder((new FolderModel())->setId(6)->setName('Unit 2'))
              ->getAssessables()
              ->add((new Task())->setId(777)->setTitle('Task 2.1'))
              ->add((new Task())->setId(666)->setTitle('Task 2.2'))
              ->add((new Task())->setId(555)->setTitle('Task 2.3'));

        $courseSpec->getUnits()->add($unitSpec1)->add($unitSpec2);

        $factory     = new CourseImplementationFactory(new UnitImplementationFactory());
        $classFolder = (new FolderModel())->setId(1999)->setName('English Class');
        $courseImpl  = $factory->build($courseSpec, $classFolder);

        // Verify that spec is copied to implementation
        $this->assertSame($courseSpec, $courseImpl->getSpecification());
        $this->assertSame($classFolder, $courseImpl->getFolder());

        // Verify that units are copied properly
        /** @var UnitImplementation $unitImpl1 */
        /** @var UnitImplementation $unitImpl2 */
        $unitImpl1 = $courseImpl->getUnits()->first();
        $unitImpl2 = $courseImpl->getUnits()->last();

        $this->assertSame($unitImpl1->getSpecification(), $unitSpec1);
        $this->assertSame($unitImpl2->getSpecification(), $unitSpec2);

        $this->assertCount(2, $unitImpl1->getAssessables());
        $this->assertCount(3, $unitImpl2->getAssessables());
    }
}
