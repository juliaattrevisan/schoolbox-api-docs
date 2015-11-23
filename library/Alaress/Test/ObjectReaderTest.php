<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Test;


use Alaress\ObjectReader;
use DomainException;
use Schoolbox\Collection;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Common\Email;
use Schoolbox\External\Normalise\User\Student;
use stdClass;

class ObjectReaderTest extends \PHPUnit_Framework_TestCase
{

    /** @var Student */
    protected $dummyUser;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     */
    protected function setUp()
    {
        $campus1 = new Campus();
        $campus1->setName('Apple');

        $campus2 = new Campus();
        $campus2->setName('Briar');

        $campus3 = new Campus();
        $campus3->setName('Cedars');

        $this->dummyUser = new Student();
        $this->dummyUser->setCampus(new Collection([ $campus1, $campus2, $campus3 ]));

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
    public function stringCoercionData()
    {
        return [
            [ 'string', 'string' ],
            [ 0, '0' ],
            [ true, '1' ],
            [ false, ''],
            [ ['a','b','c'], new DomainException() ],
            [ new stdClass(), new DomainException() ],
            [ new ObjectReaderStringableClass('helloWorld'), 'helloWorld' ]
        ];
    }


    /**
     * Test coercion of values to string
     *
     * @dataProvider stringCoercionData
     * @param mixed $input
     * @param mixed $expected
     */
    public function testCoerceToString($input, $expected)
    {
        $reader = new ObjectReader();

        try {
            $value = $reader->coerceToString($input);
            $this->assertInternalType('string', $value);
            $this->assertEquals($expected, $value);
        } catch (DomainException $e) {
            $expectedType = is_object($expected) ? get_class($expected) : gettype($expected);
            $this->assertEquals($expectedType, get_class($e));
        }
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
                    'c' => $value
                ]
            ]
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
                    'c' => $value
                ]
            ]
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
     * @expectedException DomainException
     */
    public function testReadNonexistentCollectionValue()
    {
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

        $names = $this->dummyUser->getCampus()->getColumnValues('name');

        $this->assertEquals($names, $output);
    }

}
