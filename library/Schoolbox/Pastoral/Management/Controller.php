<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Web\Controller\Base;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Pastoral\Audience\FlyoutNodes;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Submenu\Flyout;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
    * The smallest amount of characters to allow when searching for users
    */
    const MIN_CHARACTER_LIMIT = 2;


    /**
     * Constructor
     *
     * @param UserMapper                $userMapper
     * @param RoleMapper                $roleMapper
     * @param PastoralGroupMapper       $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param RosettaStone $rosettaStone
     */
    public function __construct(
        private UserMapper $userMapper,
        private RoleMapper $roleMapper,
        private PastoralGroupMapper $pastoralGroupMapper,
        private PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        private RosettaStone $rosettaStone,
    ) {
    }

    /**
     * Display pastoral group management page
     *
     * @api /pastoral/management/groups
     */
    public function getGroups()
    {

        $groups = $this->pastoralGroupMapper->findAll();
        $groups->sortByCallback(
            function (PastoralGroup $a, PastoralGroup $b) {
                return strnatcasecmp($a->getName(), $b->getName());
            }
        );

        /**
         * both pastoral Write and Moderators access this page, and they can access via different directions.
         * we should try to map the breadcrumbs to the most logical starting point
         * and build the sidenav with admin if they have access
         *
         * write > Pastoral home
         * write+ > Administration Home
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);
        $adminRoute = $this->getRouteIfAuthorized('adminHome.getIndex');
        $breadcrumbs = [
            [$pastoralCareProper, $this->routeGenerator->generate('pastoral.getIndex')],
            ["Groups Management", null],
        ];

        /* Assuming the Pastoral moderator came from Admin > Pastoral, rather than directly from Pastoral
         */
        if ($this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)) {
            $breadcrumbs = [
                ['Administration', $adminRoute],
                ['Pastoral Care', null],
                ["Groups Management", null],
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@pastoralManagement/groups.twig',
            [
                // Pastoral Moderators have access to other parts of the admin system, so they should see the admin nav menu.
                'showAdminNavMenu' => $this->getUser()->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
                'groups' => $groups,
                'breadcrumbs' => $breadcrumbs,
            ],
            'Pastoral Group Management',
        );
    }


    /**
     * Display form to allow insert/update of a pastoral group
     *
     * @api /pastoral/management/groups/modify/{group}
     * @param PastoralGroup $group
     */
    public function getGroupsModify(PastoralGroup $group): void
    {
        // Find staff roles with pastoral permission
        $roles = $this->roleMapper->findByColumnList(
            [
                'group_type' => Role::STAFF,
                'access_pastoral' => true,
            ]
        );

        /**
         * both pastoral Write and Moderators access this page, and they can access via different directions.
         * we should try to map the breadcrumbs to the most logical starting point
         * and build the sidenav with admin if they have access
         *
         * write > Pastoral home
         * write+ > Administration Home
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);
        $adminRoute = $this->getRouteIfAuthorized('adminHome.getIndex');
        $breadcrumbs = [
            [$pastoralCareProper, $this->routeGenerator->generate('pastoral.getIndex')],
            ["Groups Management", $this->routeGenerator->generate('pastoralManagement.getGroups')],
        ];

        /* Assuming the Pastoral moderator came from Admin > Pastoral, rather than directly from Pastoral
         */
        if ($this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)) {
            $breadcrumbs = [
                ['Administration', $adminRoute],
                ['Pastoral Care', null],
                ["Groups Management", $this->routeGenerator->generate('pastoralManagement.getGroups')],
            ];
        }

        /* if group has no ID (create) workout the action for breadcrumbs, headers, and buttons */
        if (empty($group->getId())) {
            $action = 'Create';
        } else {
            $action = 'Modify';
        }
        $breadcrumbs[] = [sprintf('%s %s Group', $action, $this->rosettaStone->translate('pastoralCare', false, true)), null];


        $this->response = $this->responseFromTemplate(
            '@pastoralManagement/groups_modify.twig',
            [
                'heading'             => sprintf('%s %s Group', $action, $this->rosettaStone->translate('pastoralCare', false, true)),
                'button'              => $group->getId() ? 'Save Changes' : 'Create Group',
                'breadcrumbs'         => $breadcrumbs,
                // Pastoral Moderators have access to other parts of the admin system, so they should see the admin nav menu.
                'showAdminNavMenu'    => $this->getUser()->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
                'group'               => $group,
                'roleTypes'           => null,
                'roles'               => $roles,
                'moderatorIds'        => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)->getPropertyValues('id'),
                'minimumSearchLength' => self::MIN_CHARACTER_LIMIT,
            ],
            'Update Pastoral Group',
        );
    }

    /**
     * Delete pastoral care group
     */
    public function postGroupsDelete()
    {
        $groupId = $this->requestPost['id'];
        $group = $this->pastoralGroupMapper->findById($groupId);
        $this->pastoralGroupMapper->delete($group);

        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('pastoralManagement.getGroups')
        );
    }

    /**
     * Insert/update/delete pastoral group in database
     */
    public function postGroupsModify()
    {
        $post = $this->requestPost;

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

        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('pastoralManagement.getGroups')
        );
    }
}
