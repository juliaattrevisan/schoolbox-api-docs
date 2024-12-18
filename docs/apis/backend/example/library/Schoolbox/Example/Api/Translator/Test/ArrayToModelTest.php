<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api\Translator\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Example\Api\Translator\ArrayToModel;
use Schoolbox\Example\Model;

/**
 * Tests for the array-to-model translator.
 */
#[CoversClass(ArrayToModel::class)]
class ArrayToModelTest extends TestCase
{
    /**
     * @param array      $array
     * @param Model|null $model
     * @param Model      $expected
     * @return void
     */
    #[DataProvider('getTestInvokeData')]
    public function testInvoke(array $array, ?Model $model, Model $expected): void
    {
        $arrayToModel = new ArrayToModel();
        $this->assertSame($expected, $arrayToModel($array, $model));
    }

    /**
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        yield 'empty array, null object' => [
            [],
            null,
            new Model(),
        ];
        yield 'empty array, empty object' => [
            [],
            new Model(),
            new Model(),
        ];
        yield 'empty array, object with ID' => [
            [],
            (new Model())->setId(1),
            (new Model())->setId(1),
        ];
    }
}
