<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Test\Unit;

use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Course\Unit\ImplementationFactory as UnitImplementationFactory;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Tests for the Unit Implementation Factory
 */
class ImplementationFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that the Unit Implementation is built from spec properly.
     */
    public function testBuild()
    {
        $spec = new UnitSpecification();
        $spec->setFolder((new FolderModel())->setId(3)->setName('A Folder Name'))
             ->getAssessables()
             ->add((new Task())->setId(999)->setTitle('First Task'))
             ->add((new Task())->setId(888)->setTitle('Second Task'));

        $factory = new UnitImplementationFactory();
        $impl    = $factory->build($spec);

        // Verify that spec was copied to implementation as expected
        $this->assertEquals('A Folder Name', $impl->getTitle());
        $this->assertSame($spec, $impl->getSpecification());
        $this->assertCount(2, $impl->getAssessables());
        $this->assertNull($impl->getAssessables()->first()->getId());
        $this->assertEquals('First Task', $impl->getAssessables()->first()->getTitle());
        $this->assertNull($impl->getAssessables()->last()->getId());
        $this->assertEquals('Second Task', $impl->getAssessables()->last()->getTitle());
    }
}
