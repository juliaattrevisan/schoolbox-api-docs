<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Comment\Translator\Test;

use Alaress\Authorizer\Authorizer;
use Alaress\HTMLPurifier\IPurifier;
use Alaress\Web\Router\RouterFactory;
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

        yield 'deletable' => [
            (new Model())
                ->setId(1)
                ->setOwner($users[0])
                ->setPastoral(
                    (new PastoralModel())
                        ->setId(2)
                        ->setStudent($users[1])
                        ->setOwner($users[2])
                )
                ->setBody('Test')
                ->setCreatedAt(new \Alaress_Date_Format('2021-01-01T00:00:00+00:00', \DateTime::ATOM))
                ->setUpdatedAt(new \Alaress_Date_Format('2021-01-01T00:00:00+00:00', \DateTime::ATOM))
                ->setExternalCode(null)
                ->setPrivate(false),
            true,
            [
                'id'           => 1,
                'pastoral'     => [
                    'id'      => 2,
                    'owner'   => ['id' => 3],
                    'student' => ['id' => 2],
                ],
                'owner'        => ['id' => 1],
                'body'         => 'Test',
                'externalCode' => null,
                'createdAt'    => '2021-01-01T00:00:00+00:00',
                'updatedAt'    => '2021-01-01T00:00:00+00:00',
                'isDeleted'    => false,
                'private'      => false,
                '_links'       => [
                    'delete' => '/record/2/comment/1/delete',
                ],
            ],
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

        // Test purifier setup
        $testPurifier = new class implements IPurifier {
            public function purify(?string $input): ?string
            {
                return $input;
            }
        };

        return new ModelToArray(
            static function (User $user) {
                return ['id' => $user->getId()];
            },
            $testRouter,
            $testAuthorizer,
            new User(),
            $testPurifier,
        );
    }
}
