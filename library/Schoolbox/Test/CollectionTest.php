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
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Task\Model as TaskAssessable;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Test the mapper's getModelAsCollection()
 */
class CollectionTest extends TestCase
{
    /**
     * @param Collection $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCollection')]
    public function testFromCollection(Collection $input)
    {
        $actual = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals($actual, $input);
    }

    /**
     * @param Collection $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerICollection')]
    public function testFromICollection(Collection $input)
    {
        $actual = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals($actual, $input);
    }

    /**
     * @param array $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerArray')]
    public function testFromArray(array $input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }

    /**
     * @param mixed $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalar')]
    public function testFromScalar($input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }

    /**
     * @param mixed $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerObject')]
    public function testFromObject($input)
    {
        $collection = $this->getUserMapper()->getModelAsCollection($input);
        $this->assertEquals(get_class($collection), Collection::class);
    }


    /**
     * @param mixed $input
     * @param mixed $expectedNoIndex
     * @param mixed $expectedWithIndex
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSortNaturallyAscending')]
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
    public static function providerSortNaturallyAscending(): array
    {
        return [
            'Firstnames 1, Ascending' => [
                (new Collection())
                    ->setOffset('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->setOffset('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->setOffset('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->setOffset('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD')),

                (new Collection())
                    ->setOffset('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS'))
                    ->setOffset('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->setOffset('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->setOffset('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD')),
            ],
            'Firstnames 2, Ascending' => [
                (new Collection())
                    ->setOffset(1, (new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->setOffset(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->setOffset(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->setOffset(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler')),

                (new Collection())
                    ->setOffset(1, (new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->setOffset(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->setOffset(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->setOffset(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),
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
                    ->setOffset(3, (new \Schoolbox\User())->setFirstname('# Cannot Reproduce'))
                    ->setOffset(2, (new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->setOffset(0, (new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->setOffset(1, (new \Schoolbox\User())->setFirstname('there were seven cockatoos')),
            ]
        ];
    }


    /**
     * @param mixed $input
     * @param mixed $expectedNoIndex
     * @param mixed $expectedWithIndex
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSortNaturallyDescending')]
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
    public static function providerSortNaturallyDescending(): array
    {
        return [
            'Firstnames 1, Descending' => [
                (new Collection())
                    ->setOffset('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->setOffset('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->setOffset('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->setOffset('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->add((new \Schoolbox\User())->setFirstname('robert'))
                    ->add((new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->add((new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),

                (new Collection())
                    ->setOffset('C', (new \Schoolbox\User())->setFirstname('RoNAld mcDonAlD'))
                    ->setOffset('A', (new \Schoolbox\User())->setFirstname('robert'))
                    ->setOffset('B', (new \Schoolbox\User())->setFirstname('JOSEPH'))
                    ->setOffset('D', (new \Schoolbox\User())->setFirstname('a STABLE GENIUS')),
            ],
            'Firstnames 2, Descending' => [
                (new Collection())
                    ->setOffset(1, (new \Schoolbox\User())->setFirstname('  Alfred'))
                    ->setOffset(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->setOffset(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->setOffset(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion')),

                (new Collection())
                    ->add((new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->add((new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->add((new \Schoolbox\User())->setFirstname(' is'))
                    ->add((new \Schoolbox\User())->setFirstname('  Alfred')),

                (new Collection())
                    ->setOffset(3, (new \Schoolbox\User())->setFirstname('   The Butler'))
                    ->setOffset(4, (new \Schoolbox\User())->setFirstname('  of Wayne Mansion'))
                    ->setOffset(2, (new \Schoolbox\User())->setFirstname(' is'))
                    ->setOffset(1, (new \Schoolbox\User())->setFirstname('  Alfred')),
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
                    ->setOffset(1, (new \Schoolbox\User())->setFirstname('there were seven cockatoos'))
                    ->setOffset(0, (new \Schoolbox\User())->setFirstname('1. please do not'))
                    ->setOffset(2, (new \Schoolbox\User())->setFirstname('# Let me make my point'))
                    ->setOffset(3, (new \Schoolbox\User())->setFirstname('# Cannot Reproduce')),
            ]
        ];
    }


    /**
     * @param mixed $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSortNaturallyInvalid')]
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
    public static function providerSortNaturallyInvalid(): array
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

    public static function providerCollection()
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

    public static function providerICollection()
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

    public static function providerArray()
    {
        return [
            [[]],
            [[null]],
            [[1, 2, 3]],
            [[null, 1, new User(), []]],
        ];
    }

    public static function providerScalar()
    {
        return [
            [null],
            [1],
            ['scalar_string'],
            [1.0],
            [true],
        ];
    }

    public static function providerObject()
    {
        $resultCacheMapper = ResultCache\Mapper::getInstance();

        return [
            [new User()],
            [new Participant(new User(), new Plan(new TaskAssessable()), $resultCacheMapper)],
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
