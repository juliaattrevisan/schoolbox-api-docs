<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Example\Api\Translator\Test;

use Alaress\Authorizer\Authorizer;
use Alaress_Date_Format;
use Aura\Router\Generator;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Example\Api\Translator\ModelToArray;
use Schoolbox\Example\Model;
use Schoolbox\User;

/**
 * Tests for the model-to-array translator.
 */
#[CoversClass(ModelToArray::class)]
class ModelToArrayTest extends TestCase
{
    /**
     * Test the standard translation of a model to an array.
     *
     * @param Model $model
     * @param bool  $hasPermissions
     * @param array $expected
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[DataProvider('getTestInvokeData')]
    public function testInvoke(Model $model, bool $hasPermissions, array $expected): void
    {
        // Create a mock generator.
        // These aren't real links, but those aren't supposed to be tested here.
        $generator = $this->createStub(Generator::class);
        $generator->method('generate')
            ->willReturnCallback(function ($route, $params) {
                return match ($route) {
                    'exampleApi.getItem' => '/example/api/read/' . $params['id'],
                    'exampleApi.putItem' => '/example/api/update/' . $params['id'],
                    'exampleApi.deleteItem' => '/example/api/delete/' . $params['id'],
                    default => '',
                };
            });

        // Create a mock authorizer.
        // As above these aren't real permissions, but again those aren't
        // supposed to be tested here.
        $authorizer = $this->createStub(Authorizer::class);
        $authorizer->method('isPathAuthorizedForUser')
            ->willReturn($hasPermissions);

        // Create a user to authorize: Their permissions don't matter, as
        // permissions aren't being tested.
        $currentUser = new User();

        // Create a user translator: again, this isn't being tested
        $userTranslator = $this->createMock(User\Translator\Author\ModelToArray::class);
        $userTranslator->method('__invoke')
            ->willReturnCallback(fn (?User $user) => $user ? ['id' => $user->getId()] : null);

        // And test:
        // equality is fine for arrays, because it doesn't check key ordering
        // which is irrelevant
        $modelToArray = new ModelToArray($generator, $authorizer, $currentUser, $userTranslator);
        $actual       = $modelToArray($model);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        // Base model contains a user, which must be set prior to init.
        $baseModel     = (new Model())
            ->setId(1)
            ->setUser(new User());
        $baseExpected  = [
            'id'        => 1,
            'user'      => ['id' => null],
            'createdAt' => null,
            'updatedAt' => null,
            'deletedAt' => null,
            '_links'    => [
                'read'   => null,
                'update' => null,
                'delete' => null,
            ],
        ];
        yield 'empty model, no permissions' => [
            clone $baseModel,
            false,
            $baseExpected,
        ];
        yield 'empty model, permissions' => [
            clone $baseModel,
            true,
            [
                '_links' => [
                    'read'   => '/example/api/read/1',
                    'update' => '/example/api/update/1',
                    'delete' => '/example/api/delete/1',
                ]
            ] + $baseExpected,
        ];
        yield 'user, permissions' => [
            (clone $baseModel)->setUser((new User())->setId(2)),
            true,
            [
                'user'   => ['id' => 2],
                '_links' => [
                    'read'   => '/example/api/read/1',
                    'update' => '/example/api/update/1',
                    'delete' => '/example/api/delete/1',
                ]
            ] + $baseExpected,
        ];
        yield 'dates, permissions' => [
            (clone $baseModel)
                ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')))
                ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')))
                ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'))),
            true,
            [
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'updatedAt' => '2024-02-01T00:00:00+00:00',
                'deletedAt' => '2024-03-01T00:00:00+00:00',
                '_links'    => [
                    'read'   => '/example/api/read/1',
                    'update' => '/example/api/update/1',
                    'delete' => '/example/api/delete/1',
                ]
            ] + $baseExpected,
        ];
    }
}
