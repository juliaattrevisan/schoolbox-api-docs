<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Audience\Mapper as PastoralAudienceMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
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

    /**@var PastoralAudienceMapper */
    private $pastoralAudienceMapper;

    /** @var RoleMapper */
    private $roleMapper;

    /** @var Pastoral\Type\Mapper */
    private $pastoralTypeMapper;

    /** @var Pastoral\SubType\Mapper */
    private $pastoralSubTypeMapper;


    /**
     * Constructor
     *
     * @param UserMapper                $userMapper
     * @param PastoralAccessMapper      $pastoralAccessMapper
     * @param PastoralGroupMapper       $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param PastoralAudienceMapper    $pastoralAudienceMapper
     * @param RoleMapper                $roleMapper
     * @param Pastoral\Type\Mapper      $pastoralTypeMapper
     * @param Pastoral\SubType\Mapper   $pastoralSubTypeMapper
     */
    public function __construct(
        UserMapper $userMapper,
        PastoralAccessMapper $pastoralAccessMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        PastoralAudienceMapper $pastoralAudienceMapper,
        RoleMapper $roleMapper,
        Pastoral\Type\Mapper $pastoralTypeMapper,
        Pastoral\SubType\Mapper $pastoralSubTypeMapper
    ) {
        $this->userMapper                = $userMapper;
        $this->pastoralAccessMapper      = $pastoralAccessMapper;
        $this->pastoralGroupMapper       = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper = $pastoralGroupMemberMapper;
        $this->pastoralAudienceMapper    = $pastoralAudienceMapper;
        $this->roleMapper                = $roleMapper;
        $this->pastoralTypeMapper        = $pastoralTypeMapper;
        $this->pastoralSubTypeMapper     = $pastoralSubTypeMapper;
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
     *
     * @api   GET /pastoral/management/audience
     * @level 18.2
     */
    public function getAudiences(): void
    {
        $this->getResponse()->content->set([
            'template' => '@pastoralManagement/audiences.twig',
            'data'     => [
                'types'     => $this->pastoralTypeMapper->findAll(),
                'subtypes'  => $this->pastoralSubTypeMapper->findAll()
            ],
            'title' => 'Pastoral Group Management',
        ]);
    }


    /**
     * @api   GET /pastoral/management/audience/type/{typeId}/modify
     * @level 18.2
     * @param PastoralType $type
     * @throws InvalidColumnException
     */
    public function getAudiencesTypeModify(PastoralType $type): void
    {
        $this->getAudiencesModify($type, null);
    }


    /**
     * Edit the defaults for a particular type and subtype
     *
     * @api   GET /pastoral/management/audience/subtype/{subtypeId}/modify
     * @level 18.2
     * @param PastoralSubType $subType
     * @throws InvalidColumnException
     */
    public function getAudiencesSubTypeModify(PastoralSubType $subType): void
    {
        $this->getAudiencesModify($subType->getPastoralType(), $subType);
    }


    /**
     * Edit the defaults for a particular type and subtype
     *
     * @api   GET /pastoral/management/audience/type/{typeId}/subtype/{subtypeId}/modify
     * @level 18.2
     * @param PastoralType         $type
     * @param PastoralSubType|null $subType
     * @throws InvalidColumnException
     */
    private function getAudiencesModify(PastoralType $type, ?PastoralSubType $subType): void
    {
        $pb = $this->roleMapper->getPredicateBuilder()->equals('group_type', Role::STAFF);
        if ($this->getConfigLoader()->get('pastoral_parent_access')) {
            $pb->equals('group_type', Role::PARENT);
            $pb = $this->roleMapper->getPredicateBuilder()->any($pb);
        }

        $roles     = $this->roleMapper->findByPredicates($pb, ['order' => ['name' => 'ASC']]);
        $roleUsers = [];

        foreach ($roles as $role) {
            $roleId             = $role->getId();
            $roleUsers[$roleId] = $this->userMapper->countByColumn('group_id', $roleId);
        }

        //This is fudged using the same audience screen from the record. So to get the saved records to display, it is easiest to attach them to $record
        $record = [];
        $record['id'] = "fakeId";
        $record['access'] = $this->getAudienceForType($type, $subType);

        $this->getResponse()->content->set([
            'template' => '@pastoralManagement/audiences_modify.twig',
            'data'     => [
                'record'            => $record,
                'allowIndividual'   => $this->getConfigLoader()->get('pastoral_individual_access'),
                'allowParents'      => false,  //prevent user search from including student parents
                'roles'             => $roles,
                'roleUsers'         => $roleUsers,
                'groups'            => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
                'type'              => $type,
                'subtype'           => $subType,
            ],
            'title' => 'Pastoral Group Management',
        ]);
    }


    /**
     * @api   POST /pastoral/management/audience/type/{typeId}/modify
     * @level 18.2
     * @param PastoralType $type
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAudienceTypeModify(PastoralType $type): void
    {
        $this->postAudienceModify($type, null, $this->getRequest()->post->getArrayCopy());
    }


    /**
     * @api   POST /pastoral/management/audience/type/{typeId}/subtype/{subtypeId}/modify
     * @level 18.2
     * @param PastoralSubType $subType
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAudienceSubTypeModify(PastoralSubType $subType): void
    {
        $this->postAudienceModify($subType->getPastoralType(), $subType, $this->getRequest()->post->getArrayCopy());
    }


    /**
     * Update default audience for a pastoral Type or SubType
     *
     * @param PastoralType         $type
     * @param PastoralSubType|null $subType
     * @param array                $post
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function postAudienceModify(PastoralType $type, ?PastoralSubType $subType, array $post): void
    {
        // Fetch the exiting rules
        if ($subType) {
            $existing = $this->pastoralAudienceMapper->findByColumnList(['subtype_id' => $subType->getId()]);
        } else {
            $existing = $this->pastoralAudienceMapper->findByColumnList(['type_id' => $type->getId()]);
        }

        // Build the posted rules
        $posted = $this->pastoralAudienceMapper->createCollection();

        // Build role-based rules
        foreach ($post["role"] ?? [] as $roleId => $accessLevel) {
            $foundRoles[] = $roleId;
            $role         = $this->roleMapper->findById($roleId);
            $audience     = $this->pastoralAudienceMapper->createModel();
            $subType ? $audience->setSubtype($subType) : $audience->setType($type);
            $audience->setRole($role);
            $audience->setAccess((int)$accessLevel);
            $posted->add($audience);
        }

        // Build pastoralGroup-based rules
        foreach ($post["group"] ?? [] as $groupId => $accessLevel) {
            $foundGroups[] = $groupId;
            $group         = $this->pastoralGroupMapper->findById($groupId);
            $audience      = $this->pastoralAudienceMapper->createModel();
            $subType ? $audience->setSubtype($subType) : $audience->setType($type);
            $audience->setGroup($group);
            $audience->setAccess((int)$accessLevel);
            $posted->add($audience);
        }

        // Build user-based rules
        foreach ($post["user"] ?? [] as $userId => $accessLevel) {
            $foundUsers[] = $userId;
            $user         = $this->userMapper->findById($userId);
            $audience     = $this->pastoralAudienceMapper->createModel();
            $subType ? $audience->setSubtype($subType) : $audience->setType($type);
            $audience->setAccess((int)$accessLevel);
            $audience->setUser($user);
            $posted->add($audience);
        }

        $rulesToDelete = $this->pastoralAudienceMapper->createCollection();  // removed entityType+entityId
        $rulesToAdd    = $this->pastoralAudienceMapper->createCollection();  // new     entityType+entityId
        $rulesToUpdate = $this->pastoralAudienceMapper->createCollection();  // same    entityType+entityId, accessLevel has changed

        // New Rules
        foreach ($posted as $postedRule) {
            $found = false;
            foreach ($existing as $existingRule) {
                if ($postedRule->equals($existingRule, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $rulesToAdd->add($postedRule);
            }
        }

        // Updated Rules
        foreach ($posted as $postedRule) {
            foreach ($existing as $existingRule) {
                if ($postedRule->equals($existingRule, true) && $postedRule->getAccess() !== $existingRule->getAccess()) {
                    $rulesToUpdate->add($existingRule->setAccess($postedRule->getAccess()));
                    break;
                }
            }
        }

        // Deleted Rules
        foreach ($existing as $existingRule) {
            $found = false;
            foreach ($posted as $postedRule) {
                if ($postedRule->equals($existingRule, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $rulesToDelete->add($existingRule);
            }
        }

        $this->pastoralAudienceMapper->delete($rulesToDelete);
        $this->pastoralAudienceMapper->save($rulesToAdd);
        $this->pastoralAudienceMapper->save($rulesToUpdate);

        $this->response->redirect->to($this->getRouteIfAuthorized('pastoralManagement.getAudiences'));
    }


    /**
     * @param PastoralType         $type
     * @param PastoralSubType|null $subtype
     * @return GenericCollection|Pastoral\Audience\Model[]
     * @throws InvalidColumnException
     */
    private function getAudienceForType(PastoralType $type, ?PastoralSubType $subtype): GenericCollection
    {
        $pb = $this->pastoralAudienceMapper->getPredicateBuilder();
        $subtype ? $pb->equals('subtype_id', $subtype->getId()) : $pb->equals('type_id', $type->getId());
        return $this->pastoralAudienceMapper->findByPredicates($pb);
    }

    /**
     * Display form to allow insert/update of a pastoral group
     *
     * @param Pastoral\Group\Model $group
     */
    public function getGroupsModify(Pastoral\Group\Model $group): void
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
        $groupId = $this->getRequest()->post['id'];

        $this->pastoralGroupMapper->deleteByColumn('id', $groupId);
        $this->pastoralAccessMapper->deleteByColumn('group_id', $groupId);

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

        /** @var PastoralGroup $group */

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
