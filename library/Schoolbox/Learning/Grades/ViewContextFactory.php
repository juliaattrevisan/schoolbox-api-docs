<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;

final class ViewContextFactory
{
    public function __construct(private readonly ExternalUser $externalUser)
    {
    }

    public function create(User $currentUser, User $student, ?string $requestedMode): ViewContext
    {
        if (!$currentUser->getRole()->isStaff() && !$currentUser->isSuperuser()) {
            if ($currentUser->getRole()->isParent()) {
                return new ViewContext(ViewMode::PARENT, $currentUser, false);
            }

            return new ViewContext(ViewMode::STUDENT, $student, false);
        }

        $mode = ViewMode::fromRequestValue($requestedMode);

        if ($mode === ViewMode::STUDENT) {
            return new ViewContext($mode, $student, false);
        }

        if ($mode === ViewMode::PARENT) {
            return new ViewContext($mode, $this->resolveParentViewer($student), false);
        }

        return new ViewContext(ViewMode::STAFF, $currentUser, $currentUser->getRole()->isStaff());
    }

    private function resolveParentViewer(User $student): User
    {
        $this->externalUser->loadParents([$student]);
        $parents = $student->getParents();

        if ($parents !== null && $parents->count() > 0) {
            $parent = $parents->first();
            if ($parent instanceof User) {
                return $parent;
            }
        }

        $fallback = (new User())
            ->setId(0)
            ->setRole((new Role())->setType(Role::PARENT))
            ->setChildren(new GenericCollection(User::class));
        $fallback->getChildren()->setOffset($student->getId(), $student);

        return $fallback;
    }
}
