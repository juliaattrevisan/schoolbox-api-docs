<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use UnexpectedValueException;

/**
 * Logic tests for various Collection methods
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Collection::class)]
class CollectionMethodsTest extends TestCase
{
    /**
     * @param Collection $input
     * @param Collection $expected
     * @param int        $offset
     * @param int        $count
     * @param bool       $preserveKeys
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSliceOnce')]
    public function testSliceOnce(
        Collection $input,
        Collection $expected,
        $offset,
        $count,
        $preserveKeys
    ): void {
        $beforeSlice = new Collection($input->getAll());

        $actual = $input->slice($offset, $count, $preserveKeys);
        self::assertEquals($expected->getAll(), $actual->getAll());

        // Ensure we didn't mutate operand collections
        self::assertEquals($beforeSlice->getAll(), $input->getAll());
    }


    /**
     * @param Collection $input
     * @param Collection $expected
     * @param int        $offset
     * @param int        $count
     * @param bool       $preserveKeys
     * @throws \PHPUnit\Framework\SkippedTestError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSliceOnce')]
    public function testSliceInvertKeyPreservation(
        Collection $input,
        Collection $expected,
        $offset,
        $count,
        $preserveKeys
    ): void {
        // Ignore 0 length arrays or arrays starting from first index (these do not give different keys)
        if (!$offset || !$count) {
            self::markTestSkipped();
        }
        $beforeSlice = new Collection($input->getAll());
        $actual = $input->slice($offset, $count, !$preserveKeys);
        self::assertNotEquals($expected->getAll(), $actual->getAll());

        // Ensure we didn't mutate operand collections
        self::assertEquals($beforeSlice->getAll(), $input->getAll());
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @param Collection $input
     * @param Collection[] $expectedSlices
     * @param int[] $offsets
     * @param int[] $counts
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSliceMultiple')]
    public function testSliceMulti(Collection $input, $expectedSlices, $offsets, $counts): void
    {
        // Ensure our test data is correct
        if (count($expectedSlices) != count($offsets)) {
            throw new LogicException("Argument count mismatch.");
        }

        $countSlices = count($expectedSlices);

        $beforeSlice = $input->getAll();

        for ($i = 0; $i < $countSlices; $i++) {
            $expected = $expectedSlices[$i];
            $offset = $offsets[$i];
            $count = isset($counts[$i]) ? $counts[$i] : null;

            $actual = $input->slice($offset, $count);
            self::assertEquals($expected->getAll(), $actual->getAll());
        }

        $afterSlice = $input->getAll();
        self::assertEquals($beforeSlice, $afterSlice);
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMerge')]
    public function testMerge($inputs, Collection $expected): void
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->merge($input);
        }

        self::assertEquals($expected, $actual);

        for ($i = 0; $i < count($inputs); $i++) {
            self::assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMergeUnique')]
    public function testMergeUnique($inputs, Collection $expected): void
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input);
        }

        self::assertEquals($expected->getAll(), $actual->getAll());

        for ($i = 0; $i < count($inputs); $i++) {
            self::assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @param Collection[] $inputs
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMergeUniqueObjects')]
    public function testMergeUniqueObjects($inputs): void
    {
        $this->expectException(UnexpectedValueException::class);
        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input);
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMerge')]
    public function testMergeNotUnique($inputs, Collection $expected): void
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input);
        }

        self::assertNotEquals($expected->getAll(), $actual->getAll());

        for ($i = 0; $i < count($inputs); $i++) {
            self::assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMergeEmpty')]
    public function testMergeEmpty($inputs, Collection $expected): void
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input, 'id');
        }

        //var_dump($actual);
        self::assertEquals($expected->getAll(), $actual->getAll());

        for ($i = 0; $i < count($inputs); $i++) {
            self::assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Limit should NOT mutate the collection
     *
     * @param Collection $input
     * @param Collection $expected
     * @param int        $count
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLimit')]
    public function testLimit(Collection $input, Collection $expected, $count): void
    {
        $beforeLimit = $input->getAll();

        $actual = $input->limit($count);

        $message = "Failed asserting that two arrays are equal. \$count: $count";

        self::assertEquals($expected->getAll(), $actual->getAll(), $message);

        self::assertEquals($beforeLimit, $input->getAll());
    }


    /**
     * Tests an ordinary numerically indexed collection
     *
     * @param Collection $input
     * @param mixed      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLastIndexed')]
    public function testLastIndexed(Collection $input, $expected): void
    {
        self::assertSame($expected, $input->last());
    }


    /**
     * Tests a sparse numerically indexed collection
     *
     * @param Collection $input
     * @param mixed      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLastIndexedSparse')]
    public function testLastIndexedSparse(Collection $input, $expected): void
    {
        self::assertSame($expected, $input->last());
    }


    /**
     * Tests an associative collection
     *
     * @param Collection $input
     * @param mixed      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLastAssociative')]
    public function testLastAssociative(Collection $input, $expected): void
    {
        self::assertSame($expected, $input->last());
    }


    /**
     * Tests intersection
     *
     * @param Collection    $first
     * @param Collection    $second
     * @param callable|null $compare
     * @param Collection    $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIntersect')]
    public function testIntersect(Collection $first, Collection $second, ?callable $compare, Collection $expected): void
    {
        self::assertSame($expected->getAll(), $first->intersect($second, $compare)->getAll());
    }


    /**
     * Test `getPropertyValues`
     *
     * @param Collection $collection
     * @param string     $property
     * @param array      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetPropertyValuesProvider')]
    public function testGetPropertyValues(Collection $collection, string $property, array $expected): void
    {
        self::assertSame($expected, $collection->getPropertyValues($property));
    }

    /**
     * Test `getPropertyValues` case where the property does not exist:
     * this should be a warning.
     *
     * @param Collection $collection
     * @param string     $property
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetPropertyValuesPropertyDoesNotExistProvider')]
    public function testGetPropertyValuesPropertyDoesNotExist(Collection $collection, string $property): void
    {
        $this->expectException(InvalidArgumentException::class);
        $collection->getPropertyValues($property);
    }

    // ============= Data Providers =============

    public static function providerLimit(): iterable
    {
        $array = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        $data = [];

        for ($i = 0; $i < 9; $i++) {
            $data[] = [new Collection($array), new Collection(array_slice($array, 0, $i)), $i];
        }

        return $data;
    }

    public static function providerMerge(): iterable
    {
        $data = [];

        $data[] = [
            [
                new Collection([]),
                new Collection([1]),
                new Collection([1]),
            ],
            new Collection([1, 1]),
        ];
        $data[] = [
            [
                new Collection([]),
                new Collection([1]),
                new Collection([1, 2, 3, 4]),
            ],
            new Collection([1, 1, 2, 3, 4]),
        ];
        return $data;
    }

    public static function providerMergeUnique(): iterable
    {
        $data = [];

        $data[] = [
            [
                new Collection([]),
                new Collection([1]),
                new Collection([1]),
            ],
            new Collection([1]),
        ];
        $data[] = [
            [
                new Collection([]),
                new Collection([1]),
                new Collection([1, 2, 3, 4]),
            ],
            new Collection([1, 2, 3, 4]),
        ];

        return $data;
    }

    public static function providerMergeUniqueObjects(): iterable
    {
        $data = [];

        $campusOne = new \Schoolbox\Campus();
        $campusOne->setId(1);

        $campusTwo = new \Schoolbox\Campus();
        $campusTwo->setId(2);

        $campusThree = new \Schoolbox\Campus();
        $campusThree->setId(3);

        $campusFour = new \Schoolbox\Campus();
        $campusFour->setId(4);

        $data[] = [
            [
                new Collection([]),
                new Collection([$campusOne]),
                new Collection([$campusOne, $campusTwo, $campusThree, $campusFour]),
                new Collection([$campusThree, $campusFour]),
            ],
        ];

        return $data;
    }


    public static function providerMergeEmpty(): iterable
    {
        $data = [];

        $campusOne = new \Schoolbox\Campus();
        $campusOne->setId(1);

        $campusTwo = new \Schoolbox\Campus();
        $campusTwo->setId(2);

        $campusThree = new \Schoolbox\Campus();
        $campusThree->setId(3);

        $campusFour = new \Schoolbox\Campus();
        $campusFour->setId(4);

        $data[] = [
            [
                new Collection([]),
                new Collection([$campusOne]),
                new Collection([$campusOne]),
            ],
            new Collection([$campusOne]),
        ];
        $data[] = [
            [
                new Collection([]),
                new Collection([$campusOne]),
                new Collection([$campusOne, $campusTwo, $campusThree, $campusFour]),
                new Collection([$campusThree, $campusFour]),

            ],
            new Collection([$campusOne, $campusTwo, $campusThree, $campusFour]),
        ];
        return $data;
    }

    public static function providerSliceOnce(): iterable
    {
        $array = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        $input = new Collection($array);
        $data = [];

        for ($i = 0; $i < 9; $i++) {
            for ($j = 0; $j < count($array) - $i; $j++) {
                $data[] = [$input, new Collection(array_slice($array, $i, $j, true)), $i, $j, true];
                $data[] = [$input, new Collection(array_slice($array, $i, $j, false)), $i, $j, false];
            }
        }

        return $data;
    }

    public static function providerSliceMultiple(): iterable
    {
        $array = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        $input = new Collection($array);
        $data = [];

        $data[] = [
            $input,
            [
                new Collection([1]),
                new Collection([2]),
            ],
            [0, 1],
            [1, 1],
        ];

        $data[] = [
            $input,
            [
                new Collection([1, 2, 3, 4]),
                new Collection([2, 3, 4, 5]),
            ],
            [0, 1],
            [4, 4],
        ];

        $data[] = [
            $input,
            [
                new Collection([1, 2, 3, 4]),
                new Collection([5, 6, 7, 8, 9]),
            ],
            [0, 4],
            [4],
        ];

        $data[] = [
            $input,
            [
                new Collection([1, 2, 3, 4]),
                new Collection([5, 6, 7, 8, 9]),
            ],
            [0, 4],
            [4, null],
        ];

        $data[] = [
            $input,
            [
                new Collection([1, 2, 3, 4]),
                new Collection([5, 6, 7, 8, 9]),
            ],
            [0, 4],
            [4, PHP_INT_MAX],
        ];
        return $data;
    }

    public static function providerLastIndexed(): array
    {
        return [
            [new Collection(), null],
            [new Collection([1,2,3]), 3],
            [new Collection(['a','b','c']), 'c'],
            [(new Collection())->add(1)->add(2)->add(3), 3],
            [(new Collection())->add('a')->add('b')->add('c'), 'c'],
        ];
    }

    public static function providerLastIndexedSparse(): array
    {
        $c1 = new Collection();
        $c1[0] = 1;
        $c1[2] = 2;

        $c2 = new Collection();
        $c2[2] = 2;
        $c2[0] = 1;

        return [
            [$c1, 2],
            [$c2, 1],
        ];
    }

    public static function providerLastAssociative(): array
    {
        return [
            [new Collection(), null],
            [(new Collection())->setOffset(0, 0)->setOffset(2, 2), 2],
            [(new Collection())->setOffset('key1', 'value1'), 'value1'],
            [(new Collection())->setOffset('key1', 'value1')->setOffset('key2', 'value2'), 'value2'],
            [(new Collection())->setOffset('key1', 'value1')->setOffset(0, 0), 0],
        ];
    }

    public static function providerIntersect(): iterable
    {
        // Set notation refresher:
        // ∅:     Empty set
        // ∩:     Intersection
        // |A|:   Cardinality (number of members) of set A
        // A ⊂ B: A is a proper subset of B (B contains all A, but is not equal)
        // A ⊃ B: A is a proper superset of B
        return [
            '∅ ∩ ∅ = ∅'             => [new Collection(), new Collection(), null, new Collection()],
            'A ∩ ∅ = ∅'             => [new Collection([1]), new Collection(), null, new Collection()],
            '∅ ∩ B = ∅'             => [new Collection(), new Collection([1]), null, new Collection()],
            'A ∩ B, disjoint'       => [new Collection([2, 3, 4]), new Collection([1, 5]), null, new Collection()],
            'A ∩ B, non-disjoint'   => [new Collection([2, 3, 4]), new Collection([1, 2, 5]), null, new Collection([2])],
            'A ∩ B, A ⊂ B'          => [new Collection([2]), new Collection([1, 2, 5]), null, new Collection([2])],
            'A ∩ B, A ⊃ B'          => [new Collection([2]), new Collection([1, 2, 5]), null, new Collection([2])],
            'A ∩ B, A = B'          => [new Collection([2, 3, 4]), new Collection([2, 3, 4]), null, new Collection([2, 3, 4])],
            'A ∩ B, custom compare' => [
                new Collection([
                    ['id' => 2, 'name' => 'B'],
                    ['id' => 3, 'name' => 'B'],
                    ['id' => 4, 'name' => 'B'],
                ]),
                new Collection([
                    ['id' => 3, 'name' => 'C'],
                    ['id' => 4, 'name' => 'C'],
                    ['id' => 5, 'name' => 'C'],
                ]),
                static function ($a, $b) {
                    return $a['id'] === $b['id'];
                },
                new Collection([
                    ['id' => 3, 'name' => 'B'],
                    ['id' => 4, 'name' => 'B'],
                ]),
            ],
        ];
    }

    /**
     * Data provider for `testGetPropertyValues`.
     *
     * @return iterable
     */
    public static function getTestGetPropertyValuesProvider(): iterable
    {
        $campusOne = new \Schoolbox\Campus();
        $campusOne->setId(1);

        $campusTwo = new \Schoolbox\Campus();
        $campusTwo->setId(2);

        $campusThree = new \Schoolbox\Campus();
        $campusThree->setId(3);

        $campusFour = new \Schoolbox\Campus();
        $campusFour->setId(4);

        $property = 'id';
        return [
            'empty'     => [new Collection(), 'id', []],
            'populated' => [new Collection([$campusOne, $campusTwo, $campusThree, $campusFour]), $property, [1, 2, 3, 4]],
            'reversed'  => [new Collection([$campusFour, $campusThree, $campusTwo, $campusOne]), $property, [4, 3, 2, 1]],
        ];
    }

    /**
     * Data provider for `testGetPropertyValuesPropertyDoesNotExist`.
     *
     * @return iterable
     */
    public static function getTestGetPropertyValuesPropertyDoesNotExistProvider(): iterable
    {
        $campusOne = new \Schoolbox\Campus();
        $campusOne->setId(1);

        $campusTwo = new \Schoolbox\Campus();
        $campusTwo->setId(2);

        $campusThree = new \Schoolbox\Campus();
        $campusThree->setId(3);

        $campusFour = new \Schoolbox\Campus();
        $campusFour->setId(4);

        $dummy1 = new class {
            public function getId()
            {
                return 1;
            }
        };

        $dummy2 = new class {
            public function getName()
            {
                return 'name';
            }
        };

        return [
            'none have property' => [new Collection([$dummy1, $dummy2]), 'date'],
            'some have property' => [new Collection([$dummy1, $dummy2]), 'id'],
        ];
    }
}
