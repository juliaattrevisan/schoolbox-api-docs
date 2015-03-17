<?php
/**
 * Unit tests for Alaress\Json library.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Test;
use Alaress\Json;
use PHPUnit_Framework_TestCase;

class JsonTest extends PHPUnit_Framework_TestCase {
    /**
     * @return array
     */
    public function getJsonSerializeValueData() {
        return array(
            array(5),
            array('some string'),
            array(new \DateTime()),
        );
    }

    /**
     * Test that applying this to a single value that doesn't implement
     * JsonSerializable does nothing.
     *
     * @dataProvider getJsonSerializeValueData
     */
    public function testJsonSerializeValue($object) {
        $this->assertSame($object, Json::applyJsonSerializable($object));
    }

    /**
     * Test a nested data structure.
     */
    public function testJsonSerializableObject() {
        // Test recursion
        $object = new Serializable(new Serializable(new Serializable(new Serializable('something'))));
        $this->assertSame(
            json_encode(
                array(
                    'label' => 'object',
                    'value' => array(
                        'label' => 'object',
                        'value' => array(
                            'label' => 'object',
                            'value' => array(
                                'label' => 'object',
                                'value' => 'something',
                            ),
                        ),
                    ),
                )
            ),
            json_encode(Json::applyJsonSerializable($object))
        );
    }

    /**
     * Test a complicated nested data structure with arrays, iterable objects
     * and serializable objects.
     */
    public function testJsonSerializableTraversable() {
        // traversable containing serializable
        $object = new Traversable(
            array(
                new Serializable('cow'),
                new Serializable('goat'),
                // array containing serializable
                array(
                    new Serializable('rabbit'),
                    new Serializable('fox'),
                ),
                // traversable containing array
                new Traversable(array('dog', 'cat')),
                // serializable containing traversable containing array of serializable
                new Serializable(
                    new Traversable(
                        array(
                            array(
                                new Serializable('bird'),
                                new Serializable('duck'),
                            ),
                            new Serializable('chicken'),
                        )
                    )
                ),
            )
        );
        $this->assertSame(
            json_encode(
                array(
                    'label' => 'collection',
                    'value' => array(
                        array(
                            'label' => 'object',
                            'value' => 'cow',
                        ),
                        array(
                            'label' => 'object',
                            'value' => 'goat',
                        ),
                        array(
                            array(
                                'label' => 'object',
                                'value' => 'rabbit',
                            ),
                            array(
                                'label' => 'object',
                                'value' => 'fox',
                            ),
                        ),
                        array(
                            'label' => 'collection',
                            'value' => array('dog', 'cat',)
                        ),
                        array(
                            'label' => 'object',
                            'value' => array(
                                'label' => 'collection',
                                'value' => array(
                                    array(
                                        array(
                                            'label' => 'object',
                                            'value' => 'bird',
                                        ),
                                        array(
                                            'label' => 'object',
                                            'value' => 'duck',
                                        ),
                                    ),
                                    array(
                                        'label' => 'object',
                                        'value' => 'chicken',
                                    ),
                                )
                            ),
                        ),
                    ),
                )
            ),
            json_encode(Json::applyJsonSerializable($object))
        );
    }
}
