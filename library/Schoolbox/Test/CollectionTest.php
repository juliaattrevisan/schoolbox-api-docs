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
     * @param mixed $expectedNoIndex
     * @param mixed $expectedWithIndex
     */
    public function testSortNaturallyAscending($input, $expectedNoIndex, $expectedWithIndex)
    {
        $inputWithIndex = clone $input;
        $outcomeNoIndex = $input->sortNaturally('firstname');
        $this->assertEquals($expectedNoIndex, $outcomeNoIndex);

        $outcomeWithIndex = $inputWithIndex->sortNaturally('firstname', Collection::ORDER_ASCENDING, true);
        $this->assertEquals($expectedWithIndex, $outcomeWithIndex);
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
                    ->offsetSet('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->offsetSet('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->offsetSet('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->offsetSet('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD')),

                (new Collection())
                    ->offsetSet('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS'))
                    ->offsetSet('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->offsetSet('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->offsetSet('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD')),
            ],
            'Firstnames 2, Ascending' => [
                (new Collection())
                    ->offsetSet(1, (new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->offsetSet(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->offsetSet(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->offsetSet(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler')),

                (new Collection())
                    ->offsetSet(1, (new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->offsetSet(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->offsetSet(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->offsetSet(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),
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
                    ->add((new \Schoolbox\User())->setFirstname('there were seven cockatoos')),

                (new Collection())
                    ->offsetSet(3, (new \Schoolbox\User())->setFirstname('# Cannot Reproduce'))
                    ->offsetSet(2, (new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->offsetSet(0, (new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->offsetSet(1, (new \Schoolbox\User())->setFirstname('there were seven cockatoos')),
            ]
        ];
    }


    /**
     * @dataProvider provider_sortNaturallyDescending
     * @param mixed $input
     * @param mixed $expectedNoIndex
     * @param mixed $expectedWithIndex
     */
    public function testSortNaturallyDescending($input, $expectedNoIndex, $expectedWithIndex)
    {
        $inputWithIndex = clone $input;
        $outcomeNoIndex = $input->sortNaturally('firstname', Collection::ORDER_DESCENDING);
        $this->assertEquals($expectedNoIndex, $outcomeNoIndex);

        $outcomeWithIndex = $inputWithIndex->sortNaturally('firstname', Collection::ORDER_DESCENDING, true);
        $this->assertEquals($expectedWithIndex, $outcomeWithIndex);
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
                    ->offsetSet('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->offsetSet('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->offsetSet('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->offsetSet('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->offsetSet('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->offsetSet('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->offsetSet('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->offsetSet('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),
            ],
            'Firstnames 2, Descending' => [
                (new Collection())
                    ->offsetSet(1, (new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->offsetSet(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->offsetSet(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->offsetSet(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred')),

                (new Collection())
                    ->offsetSet(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->offsetSet(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->offsetSet(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->offsetSet(1, (new \Schoolbox\User())->setFirstname('  Alfred')),
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
                    ->add((new \Schoolbox\User())->setFirstname('# Cannot Reproduce')),

                (new Collection())
                    ->offsetSet(1, (new \Schoolbox\User())->setFirstname('there were seven cockatoos'))
                    ->offsetSet(0, (new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->offsetSet(2, (new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->offsetSet(3, (new \Schoolbox\User())->setFirstname('# Cannot Reproduce')),
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
