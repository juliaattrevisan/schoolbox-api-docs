<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Test;

use Alaress\ObjectReader;
use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Common\Email;
use Schoolbox\External\Normalise\User\Student;
use stdClass;

class ObjectReaderTest extends TestCase
{
    /** @var Student */
    protected $dummyUser;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     */
    protected function setUp(): void
    {
        $campus1 = new Campus();
        $campus1->setName('Apple');

        $campus2 = new Campus();
        $campus2->setName('Briar');

        $campus3 = new Campus();
        $campus3->setName('Cedars');

        $this->dummyUser = new Student();
        $this->dummyUser->setCampus(new Collection([$campus1, $campus2, $campus3]));

        $email1 = new Email();
        $email1->setAddress('apple.house@schoolbox.com.au');

        $email2 = new Email();
        $email2->setAddress('year.7@schoolbox.com.au');

        $this->dummyUser->addEmail($email1)->addEmail($email2);

        $this->dummyUser->setYear(new Student\Year());
        $this->dummyUser->getYear()->setLevel(7);
    }


    /**
     * Data for string coercion tests
     *
     * @return array
     */
    public static function stringCoercionDataToStringWithStringOutput()
    {
        return [
            ['string', 'string'],
            [0, '0'],
            [true, '1'],
            [false, ''],
        ];
    }

    /**
     * Data for string coercion tests - tostringable object
     *
     * @return array
     */
    public static function stringCoercionDataToStringWithObjectOutput()
    {
        return [
            [new ObjectReaderStringableClass('helloWorld'), new ObjectReaderStringableClass('helloWorld'),],
        ];
    }

    /**
     * Data for string coercion tests - invalid
     *
     * @return array
     */
    public static function stringCoercionDataInvalid()
    {
        return [
            [['a', 'b', 'c']],
            [new stdClass()],
        ];
    }

    /**
     * Test coercion of values to string
     *
     * @param mixed  $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stringCoercionDataToStringWithStringOutput')]
    public function testCoerceToStringWithStringOutput($input, $expected)
    {
        $reader = new ObjectReader();
        $value = $reader->coerce($input);
        $this->assertIsString($value);
        $this->assertEquals($expected, $value);
    }

    /**
     * Test coercion of values to string
     *
     * @param mixed  $input
     * @param object $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stringCoercionDataToStringWithObjectOutput')]
    public function testCoerceToStringWithObjectOutput($input, $expected)
    {
        $reader = new ObjectReader();
        $value = $reader->coerce($input);
        $this->assertEquals(get_class($expected), get_class($value));
        $this->assertEquals("$expected", "$value");
    }

    /**
     * Test coercion of values to string
     *
     * @param mixed $input
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stringCoercionDataInvalid')]
    public function testCoerceToStringInvalid($input)
    {
        $this->expectException(DomainException::class);
        $reader = new ObjectReader();
        $reader->coerce($input);
    }

    /**
     * Test the retrieval of a value nested inside an array hierarchy
     */
    public function testReadArray()
    {
        $reader = new ObjectReader();

        $value = 'foobar';

        $input = [
            'a' => [
                'b' => [
                    'c' => $value,
                ],
            ],
        ];

        $output = $reader->read($input, 'a.b.c');

        $this->assertEquals($value, $output);
    }


    /**
     * Test the retrieval of a value nested inside an object hierarchy with direct access
     */
    public function testReadStdObjectNest()
    {
        $reader = new ObjectReader();

        $value = 'foobar';

        $input = (object)[
            'a' => (object)[
                'b' => (object)[
                    'c' => $value,
                ],
            ],
        ];

        $output = $reader->read($input, 'a.b.c');

        $this->assertEquals($value, $output);
    }


    /**
     * Test the retrieval of a value nested inside an object hierarchy with getters
     */
    public function testReadDefinedObjectNest()
    {
        $reader = new ObjectReader();

        $output = $reader->read($this->dummyUser, 'year.level');

        $this->assertEquals($this->dummyUser->getYear()->getLevel(), $output);
    }


    /**
     * Test the retrieval of a value from a collection via dot notation
     */
    public function testReadSingleCollectionValue()
    {
        $reader = new ObjectReader();

        $output = $reader->read($this->dummyUser, 'campus.1.name');

        $this->assertEquals($this->dummyUser->getCampus()->offsetGet(1)->getName(), $output);
    }


    /**
     * Test the retrieval of a value from a collection via array notation
     */
    public function testReadSingleCollectionValueArrayNotation()
    {
        $reader = new ObjectReader();

        $output = $reader->read($this->dummyUser, 'campus[1].name');

        $this->assertEquals($this->dummyUser->getCampus()->offsetGet(1)->getName(), $output);
    }


    /**
     * Test that a non-existent property throws a domain exception
     *
     *
     */
    public function testReadNonexistentCollectionValue()
    {
        $this->expectException(DomainException::class);
        $reader = new ObjectReader();

        // will throw
        $reader->read($this->dummyUser, 'campus.2.nothingToSeeHere');
    }


    /**
     * Test that a collective object (campus) is carried as an array and brought back as such
     */
    public function testReadMultiCollectionValue()
    {
        $reader = new ObjectReader();

        $output = $reader->read($this->dummyUser, 'campus.name');

        $names = $this->dummyUser->getCampus()->getPropertyValues('name');

        $this->assertEquals($names, $output);
    }


    /**
     * Test that an object structure can correctly have its paths extracted
     */
    public function testPathIteration()
    {
        $reader = new ObjectReader();

        $value = 'foobar';

        $input = (object)[
            'a' => (object)[
                'b' => (object)[
                    'c' => $value,
                ],
            ],
            'b' => (object)[
                'c' => (object)[
                    'd' => $value,
                    'e' => $value,
                ],
                'd' => (object)[
                    'e' => $value,
                    'f' => $value,
                ],
                'e' => $value,
            ],
            'c' => $value,
        ];


        $paths = $reader->paths($input);

        $this->assertContains('a.b.c', $paths);
        $this->assertContains('b.c.d', $paths);
        $this->assertContains('b.c.e', $paths);
        $this->assertContains('b.d.e', $paths);
        $this->assertContains('b.d.f', $paths);
        $this->assertContains('b.e', $paths);
        $this->assertContains('c', $paths);
        $this->assertCount(7, $paths);
    }
}
