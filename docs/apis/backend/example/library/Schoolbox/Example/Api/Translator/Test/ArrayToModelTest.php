<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api\Translator\Test;

use Alaress_Date_Format;
use DateTimeZone;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Example\Api\Translator\ArrayToModel;
use Schoolbox\Example\Model;
use Schoolbox\User;

/**
 * Tests for the array-to-model translator.
 */
#[CoversClass(ArrayToModel::class)]
class ArrayToModelTest extends TestCase
{
    /**
     * Test the standard translation of a model to an array.
     *
     * @param array      $array
     * @param Model|null $model
     * @param Model      $expected
     * @return void
     */
    #[DataProvider('getTestInvokeData')]
    public function testInvoke(array $array, ?Model $model, Model $expected): void
    {
        $arrayToModel = new ArrayToModel();

        // Can't really equality-check models, need to test their content
        $actual = $arrayToModel($array, $model);
        $this->assertSame($expected->getId(), $actual->getId());
        $this->assertSame($expected->getUser()?->getId(), $actual->getUser()?->getId());
        $this->assertSame($expected->getCreatedAt()?->getUnixTimeStamp(), $actual->getCreatedAt()?->getUnixTimeStamp());
        $this->assertSame($expected->getUpdatedAt()?->getUnixTimeStamp(), $actual->getUpdatedAt()?->getUnixTimeStamp());
        $this->assertSame($expected->getDeletedAt()?->getUnixTimeStamp(), $actual->getDeletedAt()?->getUnixTimeStamp());
    }

    /**
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        // Base model contains a user, which must be set prior to init.
        $baseModel = (new Model())->setUser(new User());
        yield 'empty array, empty model' => [
            [],
            clone $baseModel,
            clone $baseModel,
        ];
        yield 'empty array, model with ID' => [
            [],
            (clone $baseModel)->setId(1),
            (clone $baseModel)->setId(1),
        ];
        yield 'empty array, model with user' => [
            [],
            (clone $baseModel)->setUser((new User())->setId(2)),
            (clone $baseModel)->setUser((new User())->setId(2)),
        ];
        yield 'empty array, model with dates' => [
            [],
            (clone $baseModel)
                ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')))
                ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'))),
            (clone $baseModel)
                ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')))
                ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'))),
        ];

        // ID isn't set by the translator
        yield 'array with ID, empty model' => [
            ['id' => 1],
            clone $baseModel,
            clone $baseModel,
        ];
        yield 'array with ID, model with ID' => [
            ['id' => 1],
            (clone $baseModel)->setId(2),
            (clone $baseModel)->setId(2),
        ];

        // Set user ID
        yield 'array with user ID, null model' => [
            ['user' => ['id' => 1]],
            null,
            (clone $baseModel)->setUser((new User())->setId(1)),
        ];
        yield 'array with user ID, empty model' => [
            ['user' => ['id' => 1]],
            clone $baseModel,
            (clone $baseModel)->setUser((new User())->setId(1)),
        ];
        yield 'array with user ID, model with ID' => [
            ['user' => ['id' => 2]],
            (clone $baseModel)->setUser((new User())->setId(1)),
            (clone $baseModel)->setUser((new User())->setId(2)),
        ];

        // Set various dates
        yield 'array with dates, empty model' => [
            [
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => '2024-02-01T00:00:00Z',
                'deletedAt' => '2024-03-01T00:00:00Z',
            ],
            clone $baseModel,
            (clone $baseModel)
                ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')))
                ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'))),
        ];
        yield 'array with dates, model with dates' => [
            [
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => '2024-02-01T00:00:00Z',
                'deletedAt' => '2024-03-01T00:00:00Z',
            ],
            (clone $baseModel)
                ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2023-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-02-01 00:00:00', new DateTimeZone('UTC')))
                ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2023-03-01 00:00:00', new DateTimeZone('UTC'))),
            (clone $baseModel)
                ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')))
                ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'))),
        ];
    }

    /**
     * Test for whether getting a user when uninitialized raises an error.
     *
     * @param array      $array
     * @param Model|null $model
     * @return void
     */
    #[DataProvider('getTestInvokeUserUninitializedErrorData')]
    public function testInvokeUserUninitializedError(array $array, ?Model $model): void
    {
        $this->expectException(Error::class);
        $arrayToModel = new ArrayToModel();
        $actual       = $arrayToModel($array, $model);
        $actual->getUser();
    }

    /**
     * @return iterable
     */
    public static function getTestInvokeUserUninitializedErrorData(): iterable
    {
        $baseModel = (new Model())->setUser(new User());
        yield 'null array, empty model' => [
            [],
            null,
            clone $baseModel,
        ];
    }
}
