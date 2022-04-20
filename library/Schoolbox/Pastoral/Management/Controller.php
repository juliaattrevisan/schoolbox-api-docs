<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Web\Controller\Base;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Search\Results;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /** PastoralGroupMapper */
    private PastoralGroupMapper $pastoralGroupMapper;

    /** PastoralGroupMemberMapper */
    private PastoralGroupMemberMapper $pastoralGroupMemberMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected PastoralAccessMapper $pastoralAccessMapper;

    /** UserMapper */
    private UserMapper $userMapper;

    /** RoleMapper */
    private RoleMapper $roleMapper;


    /**
     * Constructor
     *
     * @param UserMapper                $userMapper
     * @param RoleMapper                $roleMapper
     * @param PastoralGroupMapper       $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param PastoralAccessMapper      $pastoralAccessMapper
     */
    public function __construct(
        UserMapper $userMapper,
        RoleMapper $roleMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        PastoralAccessMapper $pastoralAccessMapper
    ) {
        $this->userMapper                = $userMapper;
        $this->roleMapper                = $roleMapper;
        $this->pastoralGroupMapper       = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper = $pastoralGroupMemberMapper;
        $this->pastoralAccessMapper      = $pastoralAccessMapper;
    }

    /**
     * Display pastoral group management page
     */
    public function getGroups()
    {

        $groups = $this->pastoralGroupMapper->findAll();
        $groups->sortByCallback(
            function (PastoralGroup $a, PastoralGroup $b) {
                return strnatcasecmp($a->getName(), $b->getName());
            }
        );

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralManagement/groups.twig',
                'data'     => [
                    'groups' => $groups,
                ],
                'title'    => 'Pastoral Group Management',
            ]
        );
    }


    /**
     * Display form to allow insert/update of a pastoral group
     *
     * @param PastoralGroup $group
     */
    public function getGroupsModify(PastoralGroup $group): void
    {
        // Find staff roles with pastoral permission
        $roles = $this->roleMapper->findByColumnList(
            [
                'group_type'      => Role::STAFF,
                'access_pastoral' => true,
            ]
        );

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralManagement/groups_modify.twig',
                'data'     => [
                    'group'               => $group,
                    'roleTypes'           => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
                    'roles'               => $roles,
                    'moderatorIds'        => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)->getPropertyValues('id'),
                    'minimumSearchLength' => Results::MIN_SEARCH_LENGTH,
                ],
                'title'    => 'Update Pastoral Group',
            ]
        );
    }

    /**
     * Delete pastoral care group
     */
    public function postGroupsDelete()
    {
        $groupId = $this->getRequest()->post['id'];
        $group = $this->pastoralGroupMapper->findById($groupId);
        $this->pastoralGroupMapper->delete($group);

        $this->getResponse()->redirect->afterPost(
            $this->router->generate('pastoralManagement.getGroups')
        );
    }

    /**
     * Insert/update/delete pastoral group in database
     */
    public function postGroupsModify()
    {
        $post = $this->getRequest()->post;

        $memberIds = explode(",", $post['members']);

        if (empty($post['id'])) {
            // Creating new group

            $group = $this->pastoralGroupMapper->createModel();
            $group->setUser($this->getUser());
        } else {
            // Updating existing group (or create a new one if its been deleted from under us)

            $group = $this->pastoralGroupMapper->findByIdWithFallback($post['id']);
        }

        if (empty(trim($post['name']))) {
            $post['name'] = 'Untitled Group';
        }

        $group->setName($post['name']);
        $this->pastoralGroupMapper->save($group);

        // If we are updating an existing group, we need to delete unwanted existing group members

        // Store all of the IDs we find in the database so we don't re-add them later

        $membersToSkip = [];

        if (!empty($post['id'])) {
            $membersExisting = $this->pastoralGroupMemberMapper->findByColumn('group_id', $group->getId());

            // Go through every member in the database

            /** @var PastoralGroupMember $memberCurrent */
            foreach ($membersExisting as $memberCurrent) {
                // Store all of the IDs we find in the database so we don't re-add them later

                $membersToSkip[] = $memberCurrent->getuser()->getId();

                // We need to find out if we should keep this member in the database

                $deleteMember = true;

                // Go through all the members the user saved in the group

                /** @var int $memberId */
                foreach ($memberIds as $memberId) {
                    if ($memberCurrent->getUser()->getId() == $memberId) {
                        // The user will wants this member

                        $deleteMember = false;
                    }
                }

                // Check if we need to delete this person

                if ($deleteMember) {
                    $this->pastoralGroupMemberMapper->delete($memberCurrent);
                }
            }
        }

        // Now add people to this group

        foreach ($memberIds as $memberId) {
            if (!empty($memberId)) {
                // Make sure they weren't already in the database

                if (!in_array($memberId, $membersToSkip)) {
                    // Not in the database, add them

                    /** @var PastoralGroupMember $pastoralGroupMember */
                    $pastoralGroupMember = $this->pastoralGroupMemberMapper->createModel();
                    $pastoralGroupMember->setGroup($group);
                    $pastoralGroupMember->setAuthor($this->getUser());
                    $pastoralGroupMember->setUser($this->userMapper->createModel($memberId));

                    $this->pastoralGroupMemberMapper->save($pastoralGroupMember);
                }
            }
        }

        $this->getResponse()->redirect->afterPost(
            $this->router->generate('pastoralManagement.getGroups')
        );
    }
}
