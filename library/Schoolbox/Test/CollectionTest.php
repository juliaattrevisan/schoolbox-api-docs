<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Task\Model as TaskAssessable;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Test the mapper's getModelAsCollection()
 */
class CollectionTest extends TestCase
{
    /**
     * @dataProvider provider_Collection
     * @param Collection $input
     */
    public function test_from_Collection(Collection $input)
    {
        $actual = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals($actual, $input);
    }

    /**
     * @dataProvider provider_ICollection
     * @param Collection $input
     */
    public function test_from_ICollection(Collection $input)
    {
        $actual = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals($actual, $input);
    }

    /**
     * @dataProvider provider_array
     * @param array $input
     */
    public function test_from_Array(array $input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }

    /**
     * @dataProvider provider_scalar
     * @param mixed $input
     */
    public function test_from_Scalar($input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }

    /**
     * @dataProvider provider_object
     * @param $input
     */
    public function test_from_Object($input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }


    // ============= Data Providers =============

    public function provider_Collection()
    {
        return [
            [new Collection()],
            [new Collection([])],
            [(new Collection())->add(null)],
            [(new Collection())->add(1)->add(2)],
            [(new Collection())->add([1, 2, 3])],
            [(new Collection())->add(new User())],
        ];
    }

    public function provider_ICollection()
    {
        return [
            [new \Schoolbox\Calendar\EventLevelCollection([], new Collection())],
            [new \EOTC\ListGroup\Collection()],
            [\Schoolbox\CampusMapper::getInstance()->createCollection()],
            [new \Schoolbox\MappingCollection()],
            [new \Schoolbox\External\SubjectCollection()],
            [new \Schoolbox\GenericCollection(User::class)],
        ];
    }

    public function provider_array()
    {
        return [
            [[]],
            [[null]],
            [[1, 2, 3]],
            [[null, 1, new User(), []]],
        ];
    }

    public function provider_scalar()
    {
        return [
            [null],
            [1],
            ['scalar_string'],
            [1.0],
            [true],
        ];
    }

    public function provider_object()
    {
        return [
            [new User()],
            [new Participant(new User(), new Plan(new TaskAssessable()))],
            [new TaskAssessable()],
        ];
    }

    // ============= Helper methods =============

    /**
     * @return UserMapper
     */
    private function getUserMapper()
    {
        return \Schoolbox\UserMapper::getInstance();
    }
}
