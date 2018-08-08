<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Pastoral\Audience\Mapper as PastoralAudienceMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Resource\Search\Results;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{
    /** @var PastoralAccessMapper $pastoralAccessMapper */
    private $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    private $pastoralGroupMapper;

    /** @var PastoralGroupMemberMapper $pastoralGroupMemberMapper */
    private $pastoralGroupMemberMapper;

    /** @var UserMapper $userMapper */
    private $userMapper;

    /**@var PastoralAudienceMapper $pastoralAudienceMapper */
    private $pastoralAudienceMapper;

    /** @var RoleMapper $roleMapper */
    private $roleMapper;


    /**
     * Constructor
     *
     * @param UserMapper                $userMapper
     * @param PastoralAccessMapper      $pastoralAccessMapper
     * @param PastoralGroupMapper       $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param PastoralAudienceMapper    $pastoralAudienceMapper
     */
    public function __construct(
        UserMapper $userMapper,
        PastoralAccessMapper $pastoralAccessMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        PastoralAudienceMapper $pastoralAudienceMapper,
        RoleMapper $roleMapper
    ) {
        $this->userMapper                = $userMapper;
        $this->pastoralAccessMapper      = $pastoralAccessMapper;
        $this->pastoralGroupMapper       = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper = $pastoralGroupMemberMapper;
        $this->pastoralAudienceMapper    = $pastoralAudienceMapper;
        $this->roleMapper                = $roleMapper;
    }

    /**
     * Display pastoral group management page
     */
    public function getGroups()
    {
        $this->getResponse()->content->set([
            'template' => '@pastoralManagement/groups.twig',
            'data'     => [
                'groups' => $this->pastoralGroupMapper->findAll(),
            ],
            'title' => 'Pastoral Group Management',
        ]);
    }

    /**
     * Display default audiences for pastoral types and sub-types
     */
    public function getAudiences()
    {
        $this->getResponse()->content->set([
            'template' => '@pastoralManagement/audiences.twig',
            'data'     => [
                'types'     => PastoralTypeMapper::getInstance()->findAll(),
                'subtypes'  => PastoralSubTypeMapper::getInstance()->findAll()
            ],
            'title' => 'Pastoral Group Management',
        ]);
    }


    /**
     *  Edit the defaults for a particular type and subtype
     *
     * @param $typeId
     * @param $subtypeId
     */
    public function getAudiencesModify($typeId, $subtypeId)
    {
        $roles = new Collection();
        $roleUsers = [];
        $rolesAll = RoleMapper::getInstance()->findAll(['order' => ['name' => 'ASC']]);

        //Staff roles only for default audiences
        /** @var Role $roleCurrent */
        foreach ($rolesAll as $roleCurrent) {
            // Staff role? Show all users
            if ($roleCurrent->isStaff()) {
                $roles->add($roleCurrent);
                $roleUsers[$roleCurrent->getId()] = $this->userMapper->countByColumn('group_id', $roleCurrent->getId());
            }
        }

        //This is fudged using the same audience screen from the record. So to get the saved records to display, it is easiest to attach them to $record
        $record = [];
        $record['id'] = "fakeId";
        $record['access'] = $this->getAudienceForType($typeId, $subtypeId);

        $this->getResponse()->content->set([
            'template' => '@pastoralManagement/audiences_modify.twig',
            'data'     => [

                'record'            => $record,
                'allowIndividual'   => ConfigMapper::getInstance()->get('pastoral_individual_access'),
                'allowParents'      => false,
                'roles'             => $roles,
                'groups'            => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
                'typeId'            => $typeId,
                'subtypeId'         => $subtypeId,
                'groups'            => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
            ],
            'title' => 'Pastoral Group Management',
        ]);
    }

    public function getAudiencesModifyNoSub($typeId) {
        $this->getAudiencesModify($typeId, null);
    }

    public function postAudienceUpdateNoSub($typeId) {
        $this->postAudienceUpdate($typeId, null);
    }

    public function postAudienceUpdate($typeId, $subtypeId) {
        //Delete the old ones
        $oldPerms = $this->pastoralAudienceMapper->findByColumnList(['type_id' => $typeId, 'subtype_id' => $subtypeId], ['deleted' => true]);

        if(count($oldPerms) > 0) {
            foreach ($oldPerms as $oldie) {
                $this->pastoralAudienceMapper->hardDelete($oldie);
            }
        }

        // Save system role permissions, de-duplicating as we go

        $foundRoles = [];

        if (is_array($_POST["role"])) {
            foreach ($_POST["role"] as $role_id => $accessLevel) {
                if (!in_array($role_id, $foundRoles)) {
                    $foundRoles[] = $role_id;

                    try {
                        /** @var Role $role */
                        $role = $this->roleMapper->findById($role_id);
                        $audience = $this->pastoralAudienceMapper->createModel();
                        $audience->setTypeId($typeId);
                        $audience->setSubtypeId($subtypeId);
                        $audience->setRole($role);
                        $audience->setAccess($accessLevel);
                        $this->pastoralAudienceMapper->save($audience);
                    } catch (MapperException $e) {
                        // Noop
                    }
                }
            }
        }

        // Save pastoral group permissions, de-duplicating as we go

        $foundGroups = [];

        if (is_array($_POST["group"])) {
            foreach ($_POST["group"] as $group_id => $accessLevel) {
                if (!in_array($group_id, $foundGroups)) {
                    $foundGroups[] = $group_id;

                    try {
                        /** @var PastoralGroup $group */
                        $group = $this->pastoralGroupMapper->findById($group_id);
                        $audience = $this->pastoralAudienceMapper->createModel();
                        $audience->setTypeId($typeId);
                        $audience->setSubtypeId($subtypeId);
                        $audience->setPastoralGroup($group);
                        $audience->setAccess($accessLevel);

                        $this->pastoralAudienceMapper->save($audience);
                    } catch (MapperException $e) {
                        // Noop
                    }
                }
            }
        }

        // Save individual user permissions, de-duplicating as we go

        $foundUsers = [];

        if (is_array($_POST["user"])) {
            foreach ($_POST["user"] as $user_id => $accessLevel) {
                if (!in_array($user_id, $foundUsers)) {
                    $foundUsers[] = $user_id;

                    try {
                        /** @var UserMapper $user */
                        $user = $this->userMapper->findById($user_id);
                        $audience = $this->pastoralAudienceMapper->createModel();
                        $audience->setTypeId($typeId);
                        $audience->setSubtypeId($subtypeId);
                        $audience->setAccess($accessLevel);
                        $audience->setUser($user);
                        $this->pastoralAudienceMapper->save($audience);
                    } catch (MapperException $e) {
                        // Noop
                    }
                }
            }
        }

        $this->response->redirect->to($this->getRouteIfAuthorized('pastoralManagement.getAudiences'));
    }

    public function getAudienceForType($typeId, $subtypeId)
    {
        $rolePerms = $this->pastoralAudienceMapper->findByColumnList(['type_id' => $typeId, 'subtype_id' => $subtypeId]);
        return $rolePerms;
    }

    /**
     * Display form to allow insert/update of a pastoral group
     *
     * @param PastoralGroup $group
     */
    public function getGroupsModify($group)
    {
        $this->getResponse()->content->set([
            'template' => '@pastoralManagement/groups_modify.twig',
            'data'     => [
                'group'               => $group,
                'minimumSearchLength' => Results::MIN_SEARCH_LENGTH,
            ],
            'title' => 'Update Pastoral Group',
        ]);
    }

    /**
     * Delete pastoral care group
     */
    public function postGroupsDelete()
    {
        $_POST = $this->getRequest()->post;

        $this->pastoralGroupMapper->deleteByColumn('id', $_POST['id']);
        $this->pastoralAccessMapper->deleteByColumn('group_id', $_POST['id']);

        $this->getResponse()->redirect->afterPost(
            $this->router->generate('pastoralManagement.getGroups')
        );
    }

    /**
     * Insert/update/delete pastoral group in database
     */
    public function postGroupsModify()
    {
        $_POST = $this->getRequest()->post;

        $memberIds = explode(",", $_POST['members']);

        /** @var PastoralGroup $group */

        if (empty($_POST['id'])) {
            // Creating new group

            $group = $this->pastoralGroupMapper->createModel();
            $group->setUser($this->getUser());
        } else {
            // Updating existing group (or create a new one if its been deleted from under us)

            $group = $this->pastoralGroupMapper->findByIdWithFallback($_POST['id']);
        }

        if (empty(trim($_POST['name']))) {
            $_POST['name'] = 'Untitled Group';
        }

        $group->setName($_POST['name']);
        $this->pastoralGroupMapper->save($group);

        // If we are updating an existing group, we need to delete unwanted existing group members

        // Store all of the IDs we find in the database so we don't re-add them later

        $membersToSkip = [];

        if (!empty($_POST['id'])) {
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
