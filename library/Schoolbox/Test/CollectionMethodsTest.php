<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use UnexpectedValueException;

/**
 * Logic tests for various Collection methods
 */
class CollectionMethodsTest extends TestCase
{
    /**
     * @dataProvider provider_slice_once
     * @param Collection $input
     * @param Collection $expected
     * @param int        $offset
     * @param int        $count
     * @param bool       $preserveKeys
     */
    public function test_slice_once(Collection $input, Collection $expected, $offset, $count, $preserveKeys)
    {
        $beforeSlice = new Collection($input->getAll());

        $actual = $input->slice($offset, $count, $preserveKeys);
        $this->assertEquals($expected->getAll(), $actual->getAll());

        // Ensure we didn't mutate operand collections
        $this->assertEquals($beforeSlice->getAll(), $input->getAll());
    }


    /**
     * @dataProvider provider_slice_once
     * @param Collection $input
     * @param Collection $expected
     * @param int        $offset
     * @param int        $count
     * @param bool       $preserveKeys
     * @throws \PHPUnit\Framework\SkippedTestError
     */
    public function test_slice_invert_key_preservation(
        Collection $input,
        Collection $expected,
        $offset,
        $count,
        $preserveKeys
    ) {
        // Ignore 0 length arrays or arrays starting from first index (these do not give different keys)
        if (!$offset || !$count) {
            $this->markTestSkipped();
            return;
        }
        $beforeSlice = new Collection($input->getAll());
        $actual = $input->slice($offset, $count, !$preserveKeys);
        $this->assertNotEquals($expected->getAll(), $actual->getAll());

        // Ensure we didn't mutate operand collections
        $this->assertEquals($beforeSlice->getAll(), $input->getAll());
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @dataProvider provider_slice_multiple
     * @param Collection $input
     * @param Collection[] $expectedSlices
     * @param int[] $offsets
     * @param int[] $counts
     */
    public function test_slice_multi(Collection $input, $expectedSlices, $offsets, $counts)
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
            $this->assertEquals($expected->getAll(), $actual->getAll());
        }

        $afterSlice = $input->getAll();
        $this->assertEquals($beforeSlice, $afterSlice);
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @dataProvider provider_merge
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    public function test_merge($inputs, Collection $expected)
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->merge($input);
        }

        $this->assertEquals($expected, $actual);

        for ($i = 0; $i < count($inputs); $i++) {
            $this->assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @dataProvider provider_merge_unique
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    public function test_merge_unique($inputs, Collection $expected)
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input);
        }

        $this->assertEquals($expected->getAll(), $actual->getAll());

        for ($i = 0; $i < count($inputs); $i++) {
            $this->assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @dataProvider provider_merge_unique_objects
     * @param Collection[] $inputs
     * @expectedException UnexpectedValueException
     */
    public function test_merge_unique_objects($inputs)
    {
        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input);
        }

    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @dataProvider provider_merge
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    public function test_merge_not_unique($inputs, Collection $expected)
    {
        $beforeMerge = [];
        foreach ($inputs as $input) {
            $beforeMerge[] = $input->getAll();
        }

        $actual = new Collection();
        foreach ($inputs as $input) {
            $actual = $actual->mergeUnique($input);
        }

        $this->assertNotEquals($expected->getAll(), $actual->getAll());

        for ($i = 0; $i < count($inputs); $i++) {
            $this->assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Ensure result is correct and operand collections not mutated
     *
     * @dataProvider provider_merge_empty
     * @param Collection[] $inputs
     * @param Collection   $expected
     */
    public function test_merge_empty($inputs, Collection $expected)
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
        $this->assertEquals($expected->getAll(), $actual->getAll());

        for ($i = 0; $i < count($inputs); $i++) {
            $this->assertEquals($beforeMerge[$i], $inputs[$i]->getAll());
        }
    }

    /**
     * Limit should NOT mutate the collection
     *
     * @dataProvider provider_limit
     * @param Collection $input
     * @param Collection $expected
     * @param int        $count
     */
    public function test_limit(Collection $input, Collection $expected, $count)
    {
        $beforeLimit = $input->getAll();

        $actual = $input->limit($count);

        $message = "Failed asserting that two arrays are equal. \$count: $count";

        $this->assertEquals($expected->getAll(), $actual->getAll(), $message);

        $this->assertEquals($beforeLimit, $input->getAll());
    }


    /**
     * Tests an ordinary numerically indexed collection
     *
     * @param Collection $input
     * @param mixed      $expected
     * @dataProvider provider_last_indexed
     */
    public function test_last_indexed(Collection $input, $expected): void
    {
        $this->assertSame($expected, $input->last());
    }


    /**
     * Tests a sparse numerically indexed collection
     *
     * @param Collection $input
     * @param mixed      $expected
     * @dataProvider provider_last_indexed_sparse
     */
    public function test_last_indexed_sparse(Collection $input, $expected): void
    {
        $this->assertSame($expected, $input->last());
    }


    /**
     * Tests an associative collection
     *
     * @param Collection $input
     * @param mixed      $expected
     * @dataProvider provider_last_associative
     */
    public function test_last_associative(Collection $input, $expected): void
    {
        $this->assertSame($expected, $input->last());
    }


    /**
     * Tests intersection
     *
     * @param Collection    $first
     * @param Collection    $second
     * @param callable|null $compare
     * @param Collection    $expected
     * @dataProvider provider_intersect
     */
    public function testIntersect(Collection $first, Collection $second, ?callable $compare, Collection $expected): void
    {
        $this->assertSame($expected->getAll(), $first->intersect($second, $compare)->getAll());
    }

    // ============= Data Providers =============

    public function provider_limit()
    {
        $array = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        $data = [];

        for ($i = 0; $i < 9; $i++) {
            $data[] = [new Collection($array), new Collection(array_slice($array, 0, $i)), $i];
        }

        return $data;
    }

    public function provider_merge()
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

    public function provider_merge_unique()
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

    public function provider_merge_unique_objects()
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


    public function provider_merge_empty()
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

    public function provider_slice_once()
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

    public function provider_slice_multiple()
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

    public function provider_last_indexed(): array
    {
        return [
            [new Collection(), null],
            [new Collection([1,2,3]), 3],
            [new Collection(['a','b','c']), 'c'],
            [(new Collection())->add(1)->add(2)->add(3), 3],
            [(new Collection())->add('a')->add('b')->add('c'), 'c'],
        ];
    }

    public function provider_last_indexed_sparse(): array
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

    public function provider_last_associative(): array
    {
        return [
            [new Collection(), null],
            [(new Collection())->setOffset(0, 0)->setOffset(2, 2), 2],
            [(new Collection())->setOffset('key1', 'value1'), 'value1'],
            [(new Collection())->setOffset('key1', 'value1')->setOffset('key2', 'value2'), 'value2'],
            [(new Collection())->setOffset('key1', 'value1')->setOffset(0, 0), 0],
        ];
    }

    public function provider_intersect(): iterable
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
            'A ∩ B, custom compare' => [new Collection([2, 3, 4]), new Collection([2, 3, 4]), null, new Collection([2, 3, 4])],
        ];
    }
}
