<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Comment\Translator\Test;

use Alaress\Authorizer\Authorizer;
use Alaress\HTMLPurifier\Adapter\MockAdapter;
use Alaress\Web\Router\RouterFactory;
use Alaress_Date_Format;
use DateTimeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Pastoral\Comment\Model;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\User;

/**
 * @covers \Schoolbox\Pastoral\Comment\Translator\ModelToArray
 */
class ModelToArrayTest extends TestCase
{
    /**
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\Pastoral\Comment\Translator\ModelToArray::__invoke
     *
     * @param Model $input
     * @param bool  $allowDelete
     * @param array $expected
     * @return void
     */
    public function testInvoke(Model $input, bool $allowDelete, array $expected): void
    {
        self::assertSame(
            $expected,
            call_user_func($this->createTranslator($allowDelete), $input),
        );
    }

    public function getTestInvokeData(): iterable
    {
        $users = [
            (new User())
                ->setId(1)
                ->setFirstname('Aaron')
                ->setLastname('Aaronson'),
            (new User())
                ->setId(2)
                ->setFirstname('Ben')
                ->setLastname('Benson'),
            (new User())
                ->setId(3)
                ->setFirstname('Carl')
                ->setLastname('Carlson'),
        ];

        $baseAtomDate    = '2021-01-01T00:00:00+00:00';
        $baseDate        = new Alaress_Date_Format($baseAtomDate, DateTimeInterface::ATOM);
        $baseComment     = (new Model())
            ->setId(1)
            ->setOwner($users[0])
            ->setPastoral(
                (new PastoralModel())
                    ->setId(2)
                    ->setOwner($users[2])
                    ->setStudent($users[1])
            )
            ->setBody('Test')
            ->setCreatedAt(clone $baseDate)
            ->setUpdatedAt(clone $baseDate)
            ->setPrivate(false);
        $baseTranslation = [
            'id'           => 1,
            'pastoral'     => [
                'id'      => 2,
                'owner'   => ['id' => 3],
                'student' => ['id' => 2],
            ],
            'owner'        => ['id' => 1],
            'body'         => 'Test',
            'externalCode' => null,
            'createdAt'    => $baseAtomDate,
            'updatedAt'    => $baseAtomDate,
            'isDeleted'    => false,
            'private'      => false,
            '_links'       => ['delete' => '/record/2/comment/1/delete'],
        ];

        yield 'deletable' => [
            $baseComment,
            true,
            $baseTranslation,
        ];

        yield 'not deletable' => [
            $baseComment,
            false,
            array_merge(
                $baseTranslation,
                ['_links' => ['delete' => null]],
            ),
        ];

        yield 'deleted' => [
            (clone $baseComment)->setDeletedAt(clone $baseDate),
            true,
            array_merge(
                $baseTranslation,
                ['isDeleted' => true],
            ),
        ];

        yield 'changed id' => [
            (clone $baseComment)->setId(111),
            true,
            array_merge(
                $baseTranslation,
                [
                    'id'     => 111,
                    '_links' => ['delete' => '/record/2/comment/111/delete'],
                ],
            ),
        ];

        yield 'changed pastoral' => [
            (clone $baseComment)->setPastoral(
                (new PastoralModel())
                    ->setId(222)
                    ->setOwner($users[1])
                    ->setStudent($users[2])
            ),
            true,
            array_merge(
                $baseTranslation,
                [
                    'pastoral' => [
                        'id'      => 222,
                        'owner'   => ['id' => 2],
                        'student' => ['id' => 3],
                    ],
                    '_links'   => ['delete' => '/record/222/comment/1/delete'],
                ],
            ),
        ];

        yield 'changed owner' => [
            (clone $baseComment)->setOwner($users[2]),
            true,
            array_merge(
                $baseTranslation,
                ['owner' => ['id' => 3]],
            ),
        ];

        yield 'changed body' => [
            (clone $baseComment)->setBody('qwerty'),
            true,
            array_merge(
                $baseTranslation,
                ['body' => 'qwerty'],
            ),
        ];

        yield 'changed external code' => [
            (clone $baseComment)->setExternalCode('qwerty'),
            true,
            array_merge(
                $baseTranslation,
                ['externalCode' => 'qwerty'],
            ),
        ];

        $newAtomDate = '2021-12-31T00:00:00-01:00';
        yield 'changed created at' => [
            (clone $baseComment)->setCreatedAt(
                new Alaress_Date_Format($newAtomDate, DateTimeInterface::ATOM)
            ),
            true,
            array_merge(
                $baseTranslation,
                ['createdAt' => $newAtomDate],
            ),
        ];

        yield 'changed updated at' => [
            (clone $baseComment)->setUpdatedAt(
                new Alaress_Date_Format($newAtomDate, DateTimeInterface::ATOM)
            ),
            true,
            array_merge(
                $baseTranslation,
                ['updatedAt' => $newAtomDate],
            ),
        ];

        yield 'changed private' => [
            (clone $baseComment)->setPrivate(true),
            true,
            array_merge(
                $baseTranslation,
                ['private' => true],
            ),
        ];
    }

    private function createTranslator(bool $allowDelete): ModelToArray
    {
        // Test router setup
        $factory    = new RouterFactory();
        $testRouter = $factory->newInstance();
        $testRouter->getRoutes()->add(
            'pastoral.postCommentDelete',
            '/record/{recordId}/comment/{commentId}/delete',
        );

        // Test authorizer setup
        /** @var Authorizer|MockObject $testAuthorizer */
        $testAuthorizer = $this->createMock(Authorizer::class);
        $testAuthorizer
            ->method('isPathAuthorizedForUser')
            ->willReturnCallback(function (string $route) use ($allowDelete): bool {
                if ($route === 'pastoral.postCommentDelete') {
                    return $allowDelete;
                }
                return false;
            });

        return new ModelToArray(
            static function (User $user) {
                return ['id' => $user->getId()];
            },
            $testRouter,
            $testAuthorizer,
            new User(),
            new MockAdapter(),
        );
    }
}
