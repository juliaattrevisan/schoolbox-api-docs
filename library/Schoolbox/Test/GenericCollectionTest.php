<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Test;

use Countable;
use FilterIterator;
use Iterator;
use IteratorAggregate;
use IteratorIterator;
use LimitIterator;
use PHPUnit\Framework\TestCase;
use RecursiveIterator;
use ReflectionException;
use RegexIterator;
use Schoolbox\GenericCollection;
use Traversable;

/**
 * Tests the GenericCollection class
 */
class GenericCollectionTest extends TestCase
{
    // ============= Tests =============
    /**
     * @param GenericCollection $collection
     * @param string            $interface
     * @param bool              $expected
     * @throws ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsCollectionOfInterface')]
    public function testIsCollectionOfInterface(GenericCollection $collection, string $interface, bool $expected): void
    {
        $actual = $collection->isCollectionOf($interface);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @param GenericCollection $collection
     * @param string            $class
     * @param bool              $expected
     * @throws ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsCollectionOfClass')]
    public function testIsCollectionOfClass(GenericCollection $collection, string $class, bool $expected): void
    {
        $actual = $collection->isCollectionOf($class);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @param GenericCollection $collection
     * @param string            $otherType
     * @param bool              $expected
     * @throws ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsCollectionOf')]
    public function testIsCollectionOf(GenericCollection $collection, string $otherType, bool $expected): void
    {
        $actual = $collection->isCollectionOf($otherType);
        $this->assertEquals($expected, $actual);
    }


    // ============= Data Providers =============

    public static function providerIsCollectionOfInterface(): array
    {
        // SPL Interface inheritance tree:
        /* - Traversable
         *   - Iterator
         *     - RecursiveIterator
         *   - IteratorAggregate
         */

        // $collection, $elementType, $expected
        return [
            [new GenericCollection(Iterator::class), Iterator::class, true],              // same interface
            [new GenericCollection(Iterator::class), IteratorAggregate::class, false],    // different adjacent interface
            [new GenericCollection(RecursiveIterator::class), Traversable::class, true],  // collection is descendant interface
            [new GenericCollection(Traversable::class), RecursiveIterator::class, false], // collection is ancestor interface
        ];
    }

    public static function providerIsCollectionOfClass(): array
    {
        // SPL Class inheritance tree:
        /* - IteratorIterator
         *   - FilterIterator (abstract)
         *     - RegexIterator
         *   - LimitIterator
         */

        // $collection, $elementType, $expected
        return [
            [new GenericCollection(FilterIterator::class), FilterIterator::class, true],   // same class
            [new GenericCollection(FilterIterator::class), LimitIterator::class, false],   // different adjacent class
            [new GenericCollection(RegexIterator::class), IteratorIterator::class, true],  // collection is descendant class
            [new GenericCollection(IteratorIterator::class), RegexIterator::class, false], // collection is ancestor class
        ];
    }

    public static function providerIsCollectionOf(): array
    {
        // SPL Class interfaces:
        // - IteratorIterator implements Iterator   (but not Countable)

        // $collection, $elementType, $expected
        return [
            [new GenericCollection(IteratorIterator::class), Iterator::class, true],    // collection type is class
            [new GenericCollection(Iterator::class), IteratorIterator::class, false],   // collection type is interface
            [new GenericCollection(IteratorIterator::class), Countable::class, false],  // collection type is class
            [new GenericCollection(Countable::class), IteratorIterator::class, false],  // collection type is interface
        ];
    }
}
