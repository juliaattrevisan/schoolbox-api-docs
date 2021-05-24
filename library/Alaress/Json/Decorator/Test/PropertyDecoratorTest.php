<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json\Decorator\Test;

use Alaress\Json\Decorator\IDecorator;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Json\Serializable;
use Alaress\Json\SerializeTrait;
use PHPUnit\Framework\TestCase;

class PropertyDecoratorTest extends TestCase
{
    /** @var Serializable */
    protected $model;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->model = new class (2) implements Serializable {
            use SerializeTrait;

            /** @var int */
            private $x;

            /**
             * @param int $x
             */
            public function __construct(int $x)
            {
                $this->x = $x;
            }


            /**
             * @return array
             */
            public function getDefaultJsonMap(): array
            {
                return [
                    'primitive',
                    'objectCustomHydration' => [
                        'primitive',
                    ]
                ];
            }


            /**
             * @return int
             */
            public function getPrimitive()
            {
                return $this->x;
            }


            /**
             * @return static
             */
            public function getObjectDefaultHydration()
            {
                return new static((int)($this->x * 2));
            }


            /**
             * @return static
             */
            public function getObjectCustomHydration()
            {
                return new static((int)($this->x * 2));
            }
        };
    }


    public function testDecorateDefault()
    {
        $pd = new PropertyDecorator();

        $actual = $pd->decorate([], $this->model);

        $expected = [
            'primitive' => $this->model->getPrimitive(),
            'objectCustomHydration' => [
                'primitive' => $this->model->getPrimitive() * 2
            ]
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testDecorateCustomMap()
    {
        $pd = new PropertyDecorator([
            'objectDefaultHydration' => [
                'objectDefaultHydration' => [
                    'objectDefaultHydration' => [
                        'primitive'
                    ]
                ]
            ]
        ]);

        $actual = $pd->decorate([], $this->model);

        $expected = [
            'objectDefaultHydration' => [
                'objectDefaultHydration' => [
                    'objectDefaultHydration' => [
                        'primitive' => 16
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testNestedDecorator()
    {
        $nested = new class implements IDecorator {
            /**
             * Decorate the given JSON payload according to the model given.
             *
             * @param array  $json  - the JSON payload, as it exists so far, to be decorated
             * @param object $model - the object with which to decorate the given JSON payload
             * @return array         - the mutated payload
             */
            public function decorate(array $json, $model): array
            {
                $json['_nesting'] = true;

                return $json;
            }
        };

        $pd = new PropertyDecorator([ 'primitive' ], $nested);

        $actual = $pd->decorate([], $this->model);

        $expected = [
            'primitive' => 2,
            '_nesting'  => true
        ];

        $this->assertEquals($expected, $actual);
    }
}
