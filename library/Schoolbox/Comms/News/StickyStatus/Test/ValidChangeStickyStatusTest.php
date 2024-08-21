<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\StickyStatus\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\StickyStatus\ValidChangeStickyStatus;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Comms\News\StickyStatus\ValidChangeStickyStatus::class)]
class ValidChangeStickyStatusTest extends TestCase
{
    /**
     * @param ValidChangeStickyStatus $validChangeStickyStatus
     * @param User                    $user
     * @param bool                    $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy(ValidChangeStickyStatus $validChangeStickyStatus, User $user, bool $expected): void
    {
        $this->assertSame($expected, $validChangeStickyStatus->isSatisfiedBy($user));
    }

    /**
     * Data provider for `testIsSatisfiedBy`
     *
     * @return iterable
     */
    public function getTestIsSatisfiedByData(): iterable
    {
        $superuser        = (new User())->setSuperuser(true);
        $nonSuperuser     = (new User())->setSuperuser(false);
        $newsModerator    = $this->createAccessMock(true);
        $nonNewsModerator = $this->createAccessMock(false);

        yield 'config enabled, user is news moderator' => [
            new ValidChangeStickyStatus($newsModerator, true),
            $nonSuperuser,
            true,
        ];
        yield 'config enabled, user is news moderator and superuser' => [
            new ValidChangeStickyStatus($newsModerator, true),
            $superuser,
            true,
        ];
        yield 'config enabled, user is not news moderator' => [
            new ValidChangeStickyStatus($nonNewsModerator, true),
            $nonSuperuser,
            false,
        ];
        yield 'config enabled, user is not news moderator but is superuser' => [
            new ValidChangeStickyStatus($nonNewsModerator, true),
            $superuser,
            true,
        ];

        yield 'config disabled, user is news moderator' => [
            new ValidChangeStickyStatus($newsModerator, false),
            $nonSuperuser,
            false,
        ];
        yield 'config disabled, user is news moderator and superuser' => [
            new ValidChangeStickyStatus($newsModerator, false),
            $superuser,
            false,
        ];
        yield 'config disabled, user is not news moderator' => [
            new ValidChangeStickyStatus($nonNewsModerator, false),
            $nonSuperuser,
            false,
        ];
        yield 'config disabled, user is not news moderator but is superuser' => [
            new ValidChangeStickyStatus($nonNewsModerator, false),
            $superuser,
            false,
        ];
    }

    /**
     * Create mock access object for this test.
     *
     * @param bool $hasNewsModerateAccess
     * @return Access
     */
    private function createAccessMock(bool $hasNewsModerateAccess): Access
    {
        $accessMock = $this->createMock(Access::class);
        $accessMock
            ->expects($this->any())
            ->method('getAccessLevel')
            ->willReturn($hasNewsModerateAccess ? UserAccess::ACCESS_FULL : UserAccess::ACCESS_WRITE);
        return $accessMock;
    }
}
