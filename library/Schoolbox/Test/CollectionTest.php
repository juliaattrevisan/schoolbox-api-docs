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
     * @param mixed $input
     */
    public function test_from_Object($input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }


    /**
     * @dataProvider provider_sortNaturallyAscending
     * @param mixed $input
     * @param int   $expected
     */
    public function testSortNaturallyAscending($input, $expected)
    {
        $outcome = $input->sortNaturally('firstname');

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testSortNaturally
     *
     * @return array
     */
    public function provider_sortNaturallyAscending(): array
    {
        return [
            'Firstnames 1, Ascending' => [
                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
            ],
            'Firstnames 2, Ascending' => [
                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler'))
            ],
            'Firstnames 3, Ascending' => [
                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->add((new \Schoolbox\User())->setFirstname('there were seven cockatoos'))
                    ->add((new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->add((new \Schoolbox\User())->setFirstname('# Cannot Reproduce')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('# Cannot Reproduce'))
                    ->add((new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->add((new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->add((new \Schoolbox\User())->setFirstname('there were seven cockatoos'))
            ]
        ];
    }


    /**
     * @dataProvider provider_sortNaturallyDescending
     * @param mixed $input
     * @param int   $expected
     */
    public function testSortNaturallyDescending($input, $expected)
    {
        $outcome = $input->sortNaturally('firstname', Collection::ORDER_DESCENDING);

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testSortNaturally
     *
     * @return array
     */
    public function provider_sortNaturallyDescending(): array
    {
        return [
            'Firstnames 1, Descending' => [
                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS'))
            ],
            'Firstnames 2, Descending' => [
                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred'))
            ],
            'Firstnames 3, Descending' => [
                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->add((new \Schoolbox\User())->setFirstname('there were seven cockatoos'))
                    ->add((new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->add((new \Schoolbox\User())->setFirstname('# Cannot Reproduce')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('there were seven cockatoos'))
                    ->add((new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->add((new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->add((new \Schoolbox\User())->setFirstname('# Cannot Reproduce'))
            ]
        ];
    }


    /**
     * @dataProvider provider_sortNaturallyInvalid
     * @param mixed $input
     */
    public function testSortNaturallyInvalid($input)
    {
        $this->expectException(\InvalidArgumentException::class);
        $input->sortNaturally('firstname');
    }


    /**
     * Data provider for testSortNaturally
     *
     * @return array
     */
    public function provider_sortNaturallyInvalid(): array
    {
        return [
            'Invalid 1' => [
                (new Collection())->add('robert')
            ],
            'Invalid 2' => [
                (new Collection())->add([])
            ],
            'Invalid 3' => [
                (new Collection())
                    ->add(new \Schoolbox\User())
                    ->add('haha')
                    ->add(new \StdClass())
            ]
        ];
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
            [new \Schoolbox\Calendar\EventLevelCollection(new Collection(), new Collection())],
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
