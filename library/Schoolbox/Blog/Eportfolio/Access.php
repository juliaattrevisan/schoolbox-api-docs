<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Blog\Eportfolio;

use Eportfolio\PermissionMapper;
use Schoolbox\Blog\Eportfolio\Post;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Access
{

    /** @var PermissionMapper */
    private $permissionMapper;

    /** @var Folder\Mapper */
    private $folderMapper;


    /**
     * @param PermissionMapper         $permissionMapper
     * @param Folder\Mapper            $folderMapper
     */
    public function __construct(PermissionMapper $permissionMapper, Folder\Mapper $folderMapper)
    {
        $this->permissionMapper = $permissionMapper;
        $this->folderMapper     = $folderMapper;
    }


    /**
     * Return whether or not the given user has access to the given eportfolio blog post
     *
     * @param Post\Model $post
     * @param User       $user
     * @throws Mapper\Exception
     * @return bool
     */
    public function userCanView(Post\Model $post, User $user): bool
    {
        $role = $user->getRole();

        if ($role->isStaff()) {
            // staff always have access
            return true;
        }

        if ($role->isParent()) {
            // parents have access if the page is flagged as such...
            if ($this->isPostVisibleToParents($post) && $user->isParentOf($post->getPage()->getOwner())) {
                return true;
            }

            // OR if they're a member of a group that has permission to view
            return $this->hasGroupViewPermission($post, $user);
        }

        if ($role->isStudent()) {
            // students can view their own post,
            // OR if they've been given explicit access,
            // OR if they're a member of a group that has permission to view
            return $this->isPostOwner($post, $user)
                || $this->isPostVisibleToStudent($post, $user)
                || $this->hasGroupViewPermission($post, $user);
        }

        // all other cases; nope.
        return false;
    }


    /**
     * Return whether the specified post is visible to parents
     *
     * @param Post\Model $post
     * @return bool
     */
    private function isPostVisibleToParents(Post\Model $post): bool
    {
        // Can parents view the page where the blog post resides?
        foreach ($this->permissionMapper->findParentSetting($post->getPage()->getPageId()) as $permission) {
            if ($permission->getValue()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether the specified user can view the specified post by virtue of group permissions.
     *
     * @param Post\Model $post
     * @param User       $user
     * @throws Mapper\Exception
     * @return bool
     */
    private function hasGroupViewPermission(Post\Model $post, User $user): bool
    {
        $groupPerms = $this->permissionMapper->findGroupPermissions($post->getPage());
        $userGroups = $this->folderMapper->findForUser($user, false);
        foreach ($groupPerms as $groupPerm) {
            foreach ($userGroups as $group) {
                if ((int)$groupPerm->getValue() === (int)$group->getId()) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Return whether the specified post is visible to the specified student
     *
     * @param Post\Model $post
     * @param User       $student
     * @return bool
     * @throws Mapper\Exception If invalid column name specified
     */
    private function isPostVisibleToStudent(Post\Model $post, User $student): bool
    {
        foreach ($this->permissionMapper->findStudentPermissions($post->getPage(), $student) as $permission) {
            if ($permission->getValue()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether the specified user is the specified post's owner
     *
     * @param Post\Model $post
     * @param User       $user
     * @return bool
     */
    private function isPostOwner(Post\Model $post, User $user): bool
    {
        // TODO remove explicit type-coercion once model hydration is fixed SA-4539
        return (int)$post->getPage()->getOwner()->getId() === (int)$user->getId();
    }
}
