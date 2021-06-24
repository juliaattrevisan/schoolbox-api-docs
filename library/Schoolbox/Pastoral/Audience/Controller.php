<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{

    /** @var Pastoral\Group\Mapper $pastoralGroupMapper */
    private $pastoralGroupMapper;

    /** @var UserMapper $userMapper */
    private $userMapper;

    /**@var Mapper */
    private $pastoralAudienceMapper;

    /** @var RoleMapper */
    private $roleMapper;

    /** @var Pastoral\Type\Mapper */
    private $pastoralTypeMapper;


    /**
     * Constructor
     *
     * @param UserMapper              $userMapper
     * @param Pastoral\Group\Mapper   $pastoralGroupMapper
     * @param Mapper                  $pastoralAudienceMapper
     * @param RoleMapper              $roleMapper
     * @param Pastoral\Type\Mapper    $pastoralTypeMapper
     */
    public function __construct(
        UserMapper $userMapper,
        Pastoral\Group\Mapper $pastoralGroupMapper,
        Mapper $pastoralAudienceMapper,
        RoleMapper $roleMapper,
        Pastoral\Type\Mapper $pastoralTypeMapper
    ) {
        $this->userMapper             = $userMapper;
        $this->pastoralGroupMapper    = $pastoralGroupMapper;
        $this->pastoralAudienceMapper = $pastoralAudienceMapper;
        $this->roleMapper             = $roleMapper;
        $this->pastoralTypeMapper     = $pastoralTypeMapper;
    }


    /**
     * Display default audiences for pastoral types and sub-types
     *
     * @since 18.2
     * @api   GET /pastoral/management/audience
     */
    public function getAudiences(): void
    {
        $flyout = new Flyout($this->getConfigLoader(), [
            ['Pastoral Care', $this->router->generate('pastoral.getIndex')],
            ['Manage Type Defaults', $this->router->generate('pastoralAudience.getAudiences')],
        ]);
        
        $this->getResponse()->content->set([
            'template' => '@pastoralAudience/audiences.twig',
            'data'     => [
                'types'    => $this->pastoralTypeMapper->findAll(),
                'flyout' => $flyout->generateVars(''),
                'breadcrumbs' => $flyout->generateBreadcrumbs('')
            ],
            'title'    => 'Pastoral Group Management',
        ]);
    }


    /**
     * @api   GET /pastoral/management/audience/type/{typeId}/modify
     * @since 18.2
     * @param Pastoral\Type\Model $type
     * @throws InvalidColumnException
     */
    public function getAudiencesTypeModify(Pastoral\Type\Model $type): void
    {
        $this->getAudiencesModify($type, null);
    }


    /**
     * Edit the defaults for a particular type and subtype
     *
     * @api   GET /pastoral/management/audience/subtype/{subtypeId}/modify
     * @since 18.2
     * @param Pastoral\SubType\Model $subType
     * @throws InvalidColumnException
     */
    public function getAudiencesSubTypeModify(Pastoral\SubType\Model $subType): void
    {
        $this->getAudiencesModify($subType->getPastoralType(), $subType);
    }


    /**
     * Edit the defaults for a particular type and subtype
     *
     * @api   GET /pastoral/management/audience/type/{typeId}/subtype/{subtypeId}/modify
     * @since 18.2
     * @param Pastoral\Type\Model         $type
     * @param Pastoral\SubType\Model|null $subType
     * @throws InvalidColumnException
     */
    private function getAudiencesModify(Pastoral\Type\Model $type, ?Pastoral\SubType\Model $subType): void
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
        $record           = [];
        $record['id']     = "fakeId";
        $record['access'] = $this->getAudienceForType($type, $subType);

        $this->getResponse()->content->set([
            'template' => '@pastoralAudience/audiences_modify.twig',
            'data'     => [
                'record'          => $record,
                'allowIndividual' => $this->getConfigLoader()->get('pastoral_individual_access'),
                'allowParents'    => false,  //prevent user search from including student parents
                'roles'           => $roles,
                'roleUsers'       => $roleUsers,
                'groups'          => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
                'type'            => $type,
                'subtype'         => $subType,
            ],
            'title'    => 'Pastoral Group Management',
        ]);
    }


    /**
     * @api   POST /pastoral/management/audience/type/{typeId}/modify
     * @since 18.2
     * @param Pastoral\Type\Model $type
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAudienceTypeModify(Pastoral\Type\Model $type): void
    {
        $this->postAudienceModify($type, null, $this->getRequest()->post->getArrayCopy());
    }


    /**
     * @api   POST /pastoral/management/audience/type/{typeId}/subtype/{subtypeId}/modify
     * @since 18.2
     * @param Pastoral\SubType\Model $subType
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAudienceSubTypeModify(Pastoral\SubType\Model $subType): void
    {
        $this->postAudienceModify($subType->getPastoralType(), $subType, $this->getRequest()->post->getArrayCopy());
    }


    /**
     * Update default audience for a pastoral Type or SubType
     *
     * @param Pastoral\Type\Model         $type
     * @param Pastoral\SubType\Model|null $subType
     * @param array                       $post
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function postAudienceModify(Pastoral\Type\Model $type, ?Pastoral\SubType\Model $subType, array $post): void
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

        $this->response->redirect->to($this->getRouteIfAuthorized('pastoralAudience.getAudiences'));
    }


    /**
     * @param Pastoral\Type\Model         $type
     * @param Pastoral\SubType\Model|null $subtype
     * @return GenericCollection|Pastoral\Audience\Model[]
     * @throws InvalidColumnException
     */
    private function getAudienceForType(Pastoral\Type\Model $type, ?Pastoral\SubType\Model $subtype): GenericCollection
    {
        $pb = $this->pastoralAudienceMapper->getPredicateBuilder();
        $subtype ? $pb->equals('subtype_id', $subtype->getId()) : $pb->equals('type_id', $type->getId());
        return $this->pastoralAudienceMapper->findByPredicates($pb);
    }
}
