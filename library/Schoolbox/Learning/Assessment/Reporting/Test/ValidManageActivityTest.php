<?php

/**
 * ValidManageActivityTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Reporting\ValidManageActivity;
use Schoolbox\Permission\Access\UserAccess;

/**
 * ValidManageActivity test
 */
class ValidManageActivityTest extends TestCase
{
    /**
     * @param bool $configOption
     * @param \Schoolbox\User $user
     * @param bool $expected
     */
    #[DataProvider('providerValidManageActivity')]
    public function testValidManageActivity($configOption, $user, $expected)
    {
        $outcome = (new ValidManageActivity())->isSatisfiedBy($configOption, $user);

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testValidManageActivity
     *
     * @return array
     */
    public static function providerValidManageActivity(): array
    {
        $lmsModerator = (new UserAccess())
            ->setService(UserAccess::SERVICE_LMS)
            ->setAccessLevel(UserAccess::ACCESS_FULL);

        $lmsWriteModerator = (new UserAccess())
            ->setService(UserAccess::SERVICE_LMS)
            ->setAccessLevel(UserAccess::ACCESS_WRITE);

        $lmsRead = (new UserAccess())
            ->setService(UserAccess::SERVICE_LMS)
            ->setAccessLevel(UserAccess::ACCESS_READ);

        $newsModerator = (new UserAccess())
            ->setService(UserAccess::SERVICE_NEWS)
            ->setAccessLevel(UserAccess::ACCESS_WRITE);

        return [
            /* Superusers are allowed
             */
            'Valid 1' => [
                true,
                (new \Schoolbox\User())->setSuperuser(true),
                true
            ],
            /* LMS Moderators with FULL access are allowed
             */
            'Valid 2a' => [
                true,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($lmsModerator)),
                true
            ],
            /* LMS Moderators with WRITE access are allowed
             * (I don't know the human word for this setup - what do you call these people?)
             */
            'Valid 2b' => [
                true,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($lmsWriteModerator)),
                true
            ],
            /* Superusers + LMS Moderators are allowed
             */
            'Valid 3a' => [
                true,
                (new \Schoolbox\User())
                    ->setSuperuser(true)
                    ->setAccessList((new GenericCollection(UserAccess::class))->add($lmsModerator)),
                true
            ],
            'Valid 3b' => [
                true,
                (new \Schoolbox\User())
                    ->setSuperuser(true)
                    ->setAccessList((new GenericCollection(UserAccess::class))->add($lmsWriteModerator)),
                true
            ],
            /* User with multiple access perms, one of which is an LMS moderator
             */
            'Valid 4a' => [
                true,
                (new \Schoolbox\User())
                    ->setSuperuser(true)
                    ->setAccessList((new GenericCollection(UserAccess::class))->add($newsModerator)->add($lmsRead)->add($newsModerator)->add($lmsModerator)->add($lmsRead)),
                true
            ],
            'Valid 4b' => [
                true,
                (new \Schoolbox\User())
                    ->setSuperuser(true)
                    ->setAccessList((new GenericCollection(UserAccess::class))->add($newsModerator)->add($lmsRead)->add($newsModerator)->add($lmsWriteModerator)->add($lmsRead)),
                true
            ],
            /* Feature flag is disabled: superusers are not allowed
             */
            'Feature disabled, super' => [
                false,
                (new \Schoolbox\User())->setSuperuser(true),
                false
            ],
            /* Feature flag is disabled: LMS Moderators are not allowed
             */
            'Feature disabled, LMS moderator' => [
                false,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($lmsModerator)),
                false
            ],
            'Feature disabled, LMS moderator with write access' => [
                false,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($lmsWriteModerator)),
                false
            ],
            /* Feature flag is disabled: Superusers + LMS Moderators are not allowed
             */
            'Feature disabled, super + LMS moderator' => [
                false,
                (new \Schoolbox\User())
                    ->setSuperuser(true)
                    ->setAccessList((new GenericCollection(UserAccess::class))->add($lmsModerator)),
                false
            ],
            'Feature disabled, super + LMS moderator with write access' => [
                false,
                (new \Schoolbox\User())
                    ->setSuperuser(true)
                    ->setAccessList((new GenericCollection(UserAccess::class))->add($lmsWriteModerator)),
                false
            ],
            /* Insufficient service level: must have WRITE+ to get in
             * READ service perms are disallowed
             */
            'Insufficient service level' => [
                true,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($lmsRead)),
                false
            ],
            /* Neither a super nor an LMS moderator
             */
            'Not LMS Moderator' => [
                true,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($newsModerator)),
                false
            ],
            /* Really not an LMS moderator at all
             */
            'Not LMS Moderator, multiple accesses' => [
                true,
                (new \Schoolbox\User())->setAccessList((new GenericCollection(UserAccess::class))->add($lmsRead)->add($lmsRead)->add($lmsRead)->add($newsModerator)),
                false
            ],
        ];
    }
}
