<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule\Test;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress_Date_Format;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Authorizer\Rule\CanViewAssessable;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests authorization rules for viewing assessable learning items.
 */
class CanViewAssessableTest extends TestCase
{
    /**
     * Ensures parent access is checked against the parent user rather than inherited child visibility.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testParentIsNotAuthorizedByChildVisibility(): void
    {
        if (!class_exists(\Schoolbox\External\User::class, false)) {
            class_alias(CanViewAssessableTestExternalUser::class, \Schoolbox\External\User::class);
        }

        $parent = (new User())
            ->setId(1)
            ->setRole((new Role())->setType(Role::PARENT));
        $child = (new User())
            ->setId(2)
            ->setRole((new Role())->setType(Role::STUDENT));
        $parent->setChildren(new GenericCollection(User::class, [$child]));

        $assessment = (new DueWork\Model())
            ->setId(3)
            ->setTitle('Staff and students due work');

        $viewedUserIds = [];
        $visibility = $this->createStub(Visibility::class);
        $visibility
            ->method('isVisibleToUser')
            ->willReturnCallback(
                /**
                 * Records visibility checks and only allows the child user to view the assessment.
                 */
                function (
                    IAssessable $assessment,
                    User $viewer,
                    bool $showClosed,
                    ?Alaress_Date_Format $now = null,
                    bool $respectWorkTypeVisibility = true
                ) use (
                    &$viewedUserIds,
                    $child
                ): bool {
                    $viewedUserIds[] = $viewer->getId();

                    return $viewer->getId() === $child->getId();
                }
            );

        $rule = new CanViewAssessable($visibility);

        $this->expectException(Unauthorized::class);

        try {
            $rule->authorize(
                [
                    'assessment' => $assessment,
                    'studentId' => $child->getId(),
                ],
                $parent
            );
        } finally {
            self::assertSame([$parent->getId()], $viewedUserIds);
        }
    }
}
