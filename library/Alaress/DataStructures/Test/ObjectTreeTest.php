<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\DataStructures\Test;

use Alaress\DataStructures\ObjectTree;
use PHPUnit\Framework;
use stdClass;

/**
 * @codeCoverageIgnore
 * @covers \Alaress\DataStructures\ObjectTree
 */
class ObjectTreeTest extends Framework\TestCase
{

    /**
     * Tests basic build of a linear tree
     *
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     */
    public function testBuildLinearTree(): void
    {
        $linearTree = new ObjectTree();

        // Create some nodes
        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);
        $element4 = $this->createElement(4);

        // Build linear tree from nodes
        $linearTree->addElement($element1);
        $linearTree->addElement($element2);
        $linearTree->addElement($element3);
        $linearTree->moveElement($element2, $element1);
        $linearTree->moveElement($element3, $element2);

        // Check tree looks ok
        $this->assertEquals(3, $linearTree->size());

        $this->assertNull($linearTree->getParent($element1));
        $this->assertSame($element1, $linearTree->getParent($element2));
        $this->assertSame($element2, $linearTree->getParent($element3));
        $this->assertNull($linearTree->getParent($element4));

        $this->assertTrue($linearTree->containsElement($element1));
        $this->assertTrue($linearTree->containsElement($element2));
        $this->assertTrue($linearTree->containsElement($element3));
        $this->assertFalse($linearTree->containsElement($element4));

        $this->assertSame($linearTree->getChildren($element1), [$element2]);
        $this->assertSame($linearTree->getChildren($element2), [$element3]);
        $this->assertSame($linearTree->getChildren($element3), []);
        $this->assertNull($linearTree->getChildren($element4));

        $this->assertCount(1, $linearTree->getRoots());
    }


    public function testGetElementByHash(): void
    {
        $tree = new ObjectTree($this->getHashFunctionIndexById());
        $element1 = $this->createElement(1);
        $tree->addElement($element1);

        $this->assertSame($element1, $tree->getElementByHash(1));
    }


    /**
     * Test we cannot move an element not yet added, but can once added.
     *
     * @throws Framework\AssertionFailedError
     */
    public function testMoveElement(): void
    {
        $tree     = new ObjectTree();
        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);

        $tree->addElement($element1);
        $tree->addElement($element2);

        $this->assertFalse($tree->moveElement($element3, $element2));


        $tree->addElement($element3, $element1);
        $this->assertTrue($tree->moveElement($element3, $element2));

        $this->assertSame($element2, $tree->getParent($element3));
    }


    /**
     * Tests the linear tree still behaves after removing a node from middle of branch,
     * thus forming a new root
     *
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     */
    public function testRemoveNodeFromLinearTree(): void
    {
        $linearTree = new ObjectTree();

        // Create some nodes
        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);
        $element4 = $this->createElement(4);

        // Build linear tree from nodes
        $linearTree->addElement($element1);
        $linearTree->addElement($element2);
        $linearTree->addElement($element3);
        $linearTree->moveElement($element2, $element1);
        $linearTree->moveElement($element3, $element2);

        // Remove the middle node from the branch
        $this->assertTrue($linearTree->removeElement($element2));
        $this->assertFalse($linearTree->removeElement($element2));

        $this->assertTrue($linearTree->containsElement($element1));
        $this->assertFalse($linearTree->containsElement($element2));
        $this->assertTrue($linearTree->containsElement($element3));
        $this->assertFalse($linearTree->containsElement($element4));

        $this->assertSame($linearTree->getChildren($element1), []);
        $this->assertNull($linearTree->getChildren($element2));
        $this->assertSame($linearTree->getChildren($element3), []);
        $this->assertNull($linearTree->getChildren($element4));

        $this->assertCount(2, $linearTree->getRoots());
    }


    /**
     * Test moving an element to a different parent
     */
    public function testMoveElementToDifferentParent(): void
    {
        $tree = new ObjectTree();

        // Create some nodes
        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);
        $element4 = $this->createElement(4);

        // Initialise to straight branch
        $tree->addElement($element1);
        $tree->addElement($element2, $element1);
        $tree->addElement($element3, $element2);
        $tree->addElement($element4, $element3);

        // Re-attach sub-tree to root
        $tree->moveElement($element4, $element1);

        // Check parents
        $this->assertNull($tree->getParent($element1));
        $this->assertSame($element1, $tree->getParent($element2));
        $this->assertSame($element2, $tree->getParent($element3));
        $this->assertSame($element1, $tree->getParent($element4));

        // Check children
        $this->assertSame([$element2, $element4], $tree->getChildren($element1));
        $this->assertSame([$element3], $tree->getChildren($element2));
        $this->assertSame([], $tree->getChildren($element3));
        $this->assertSame([], $tree->getChildren($element4));
    }


    /**
     * Test removing a root, any children should become roots
     */
    public function testRemoveRoot(): void
    {
        $tree = new ObjectTree();

        // Create some nodes
        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);
        $element4 = $this->createElement(4);

        // Initialise to straight branch
        $tree->addElement($element1);
        $tree->addElement($element2, $element1);
        $tree->addElement($element3, $element2);
        $tree->addElement($element4, $element3);

        // Remove root
        $tree->removeElement($element1);

        $this->assertSame([$element2], array_values($tree->getRoots()));
        $this->assertNull($tree->getParent($element2));
    }


    /**
     * Test we can get all elements as a flat indexed array
     */
    public function testToFlatArray(): void
    {
        $tree = new ObjectTree($this->getHashFunctionIndexById());

        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);

        $tree->addElement($element1);
        $tree->addElement($element2, $element1);
        $tree->addElement($element3, $element1);

        $expected = [
            1 => $element1,
            2 => $element2,
            3 => $element3,
        ];
        $this->assertSame($expected, $tree->toFlatArray());
    }


    /**
     * @throws Framework\Exception
     */
    public function testToNestedArray(): void
    {
        $tree = new ObjectTree($this->getHashFunctionIndexById());

        // Create some nodes
        $element1 = $this->createElement(1);
        $element2 = $this->createElement(2);
        $element3 = $this->createElement(3);
        $element4 = $this->createElement(4);
        $element5 = $this->createElement(5);
        $element6 = $this->createElement(6);

        // 2 roots, first is linear 3 long, second is balanced branched
        $tree->addElement($element1);
        $tree->addElement($element2, $element1);
        $tree->addElement($element3, $element2);

        $tree->addElement($element4);
        $tree->addElement($element5, $element4);
        $tree->addElement($element6, $element4);

        $this->assertEquals(6, $tree->size());


        $wholeTree = $tree->toNestedArray();
        $this->assertCount(2, $wholeTree);

        // Test indexed whole tree
        $this->assertEquals($element1, $wholeTree[1]->getElement());
        $this->assertEquals($element2, $wholeTree[1]->getChildren()[2]->getElement());
        $this->assertEquals($element3, $wholeTree[1]->getChildren()[2]->getChildren()[3]->getElement());
        $this->assertEquals($element4, $wholeTree[4]->getElement());
        $this->assertEquals($element5, $wholeTree[4]->getChildren()[5]->getElement());
        $this->assertEquals($element6, $wholeTree[4]->getChildren()[6]->getElement());

        // Test un-indexed sub-tree
        $subTree = $tree->toNestedArray($element4);
        $this->assertEquals($element5, $subTree[5]->getElement());
        $this->assertEquals($element6, $subTree[6]->getElement());
    }


    // Helpers


    /**
     * Returns a typical element that could be used in trees.
     *
     * Has a single property 'id'.
     *
     * @param int $id
     * @return stdClass
     */
    private function createElement(int $id): stdClass
    {
        $stdClass     = new stdClass();
        $stdClass->id = $id;
        return $stdClass;
    }


    /**
     * Returns a function that will expects a stdClass and returns stdClass::id
     *
     * @return callable
     */
    private function getHashFunctionIndexById(): callable
    {
        return static function (stdClass $element): int {
            return $element->id;
        };
    }
}
