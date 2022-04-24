<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Submenu\Flyout;
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
     * @var Pastoral\SubType\Mapper
     */
    private Pastoral\SubType\Mapper $pastoralSubTypeMapper;

    /**
     * @var Pastoral\Action\Mapper
     */
    private Pastoral\Action\Mapper $pastoralActionMapper;

    /**
     * @var Pastoral\Access\Mapper
     */
    private Pastoral\Access\Mapper $pastoralAccessMapper;

    /**
     * Icon mapper: the list of icons is fetched from here.
     */
    private IconMapper $iconMapper;

    /**
     * @var RosettaStone
     */
    private RosettaStone $rosettaStone;

    /**
     * A factory object for creating color objects from form input.
     */
    private ColorFactory $colorFactory;

    /**
     * Constructor
     *
     * @param UserMapper              $userMapper
     * @param Pastoral\Group\Mapper   $pastoralGroupMapper
     * @param Mapper                  $pastoralAudienceMapper
     * @param RoleMapper              $roleMapper
     * @param Pastoral\Type\Mapper    $pastoralTypeMapper
     * @param Pastoral\SubType\Mapper $pastoralSubTypeMapper
     * @param Pastoral\Action\Mapper  $pastoralActionMapper
     * @param IconMapper              $iconMapper
     * @param RosettaStone            $rosettaStone
     * @param ColorFactory            $colorFactory
     */
    public function __construct(
        UserMapper $userMapper,
        Pastoral\Group\Mapper $pastoralGroupMapper,
        Mapper $pastoralAudienceMapper,
        RoleMapper $roleMapper,
        Pastoral\Type\Mapper $pastoralTypeMapper,
        Pastoral\SubType\Mapper $pastoralSubTypeMapper,
        Pastoral\Action\Mapper $pastoralActionMapper,
        IconMapper $iconMapper,
        RosettaStone $rosettaStone,
        ColorFactory $colorFactory,
        Pastoral\Access\Mapper $pastoralAccessMapper
    ) {
        $this->userMapper             = $userMapper;
        $this->pastoralGroupMapper    = $pastoralGroupMapper;
        $this->pastoralAudienceMapper = $pastoralAudienceMapper;
        $this->roleMapper             = $roleMapper;
        $this->pastoralTypeMapper     = $pastoralTypeMapper;
        $this->pastoralSubTypeMapper  = $pastoralSubTypeMapper;
        $this->pastoralActionMapper = $pastoralActionMapper;
        $this->iconMapper             = $iconMapper;
        $this->rosettaStone           = $rosettaStone;
        $this->colorFactory           = $colorFactory;
        $this->pastoralAccessMapper   = $pastoralAccessMapper;
    }


    /**
     * Display default audiences for pastoral types and sub-types
     *
     * @since 18.2
     * @api   GET /pastoral/management/audience
     */
    public function getAudiences(): void
    {
        $types = $this->pastoralTypeMapper->findAll();

        foreach ($types as $type) {
            /* Find the first Type that didn't have any Subtypes
             * You can't edit Types if they have Subtypes
             */
            if ($type->getSubTypes()->count()) {
                foreach ($type->getSubTypes() as $subtype) {
                    /* Find the first available subtype
                     * and redirect to its modify screen
                     */
                    $this->getResponse()->redirect->to(
                        $this->getRouteIfAuthorized('pastoralAudience.getAudiencesSubTypeModify', [
                            'subtypeId' => $subtype->getId()
                        ])
                    );

                    return;
                }
            }

            /* If there aren't any subtypes, find the first available type
             * and redirect to its modify screen
             */
            $this->getResponse()->redirect->to(
                $this->getRouteIfAuthorized('pastoralAudience.getAudiencesTypeModify', [
                    'typeId' => $type->getId(),
                    'type' => $type
                ])
            );

            return;
        }

        /* Otherwise if there are neither subtypes nor types, then you've
         * not configured the Pastoral Module sufficiently
         * Empty State
         */
        $this->getResponse()->content->set([
            'template' => '@pastoralAudience/audiences.twig',
            'data' => [],
            'title' => 'Pastoral Group Management',
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
     *
     * @param Pastoral\Type\Model         $type
     * @param Pastoral\SubType\Model|null $subType
     * @throws InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function getAudiencesModify(Pastoral\Type\Model $type, ?Pastoral\SubType\Model $subType): void
    {
        $staffPb = $this->roleMapper->getPredicateBuilder()->equals('group_type', Role::STAFF)->equals('access_pastoral', 1);
        $pb = $this->roleMapper->getPredicateBuilder()->all($staffPb);

        $roles     = $this->roleMapper->findByPredicates($pb, ['order' => ['name' => 'ASC']]);
        $roleUsers = [];

        foreach ($roles as $role) {
            $roleId             = $role->getId();
            $roleUsers[$roleId] = $this->userMapper->countByColumn('group_id', $roleId);
        }

        //This is fudged using the same audience screen from the record. So to get the saved records to display, it is easiest to attach them to $record
        $record                         = [];
        $record['id']                   = "fakeId";
        $record['accessWithPermission'] = $this->getAudienceForType($type, $subType);

        /* Set up flyout menu; identify our current url to see what should
         * be selected in the flyout menu. Since the modify route addresses both
         * Pastoral Types and Pastoral Subtypes we have to figure out which one we
         * are actually in...
         */
        if (null !== $subType) {
            $url = $this->router->generate('pastoralAudience.getAudiencesSubTypeModify', [
                'typeId' => $type->getId(),
                'subtypeId' => $subType->getId()
            ]);
        } else {
            $url = $this->router->generate('pastoralAudience.getAudiencesTypeModify', [
                'typeId' => $type->getId(),
            ]);
        }

        /* Set up flyout with special prefix breadcrumbs because we're in some deep
         * part of the system where the flyout doesn't represent the full app hierarchy
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);
        $flyout = (new Flyout(
            $this->getConfigLoader(),
            new FlyoutNodes($this->router, $this->pastoralTypeMapper)
        ))->prefixBreadcrumbs([
            [$pastoralCareProper, $this->router->generate('pastoral.getIndex')],
            ["Manage ${pastoralCareProper} Record Settings", $this->router->generate('pastoralAudience.getAudiences')],
        ]);


        // Figure out a background color based on the record's color
        // (default color: black)
        $foregroundColor = ($subType ? $subType->getColor() : $type->getColor()) ?? Color::black();
        $transformer     = new ColorHelper();
        $backgroundColor = $transformer->calculateBackground($foregroundColor);

        $this->getResponse()->content->set([
            'template' => '@pastoralAudience/audiences_modify.twig',
            'data'     => [
                'flyout'          => $flyout->generateVars($url),
                'breadcrumbs'     => $flyout->generateBreadcrumbs($url),
                'record'          => $record,
                'allowIndividual' => $this->getConfigLoader()->get('pastoral_individual_access'),
                'allowParents'    => false,  //prevent user search from including student parents
                'roles'           => $roles,
                'roleTypes'       => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
                'roleUserCounts'  => $roleUsers,
                'rolesWithUsers'  => $roleUsers,
                'groups'          => $this->pastoralGroupMapper->findAll()->sortNaturally('name'),
                'moderatorIds'    => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)->getPropertyValues('id'),
                'type'            => $type,
                'subtype'         => $subType,
                'actions'         => $this->pastoralActionMapper->findAll(),
                'typeActions'     => $subType ? $this->pastoralActionMapper->findForSubtype($subType) : $this->pastoralActionMapper->findforType($type),
                'icons'           => $this->iconMapper->findAll()->sortNaturally('title'),
                'sentiments'      => Pastoral\Type\Model::SENTIMENT_VALUES,
                'flagColors'  => [
                    'foreground' => $foregroundColor->getAsRgba(),
                    'background' => $backgroundColor->getAsRgba(),
                    'baseLight'  => ColorHelper::BASE_BACKGROUND_COLOR_LIGHT,
                    'baseDark'   => ColorHelper::BASE_BACKGROUND_COLOR_DARK,
                ]
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
        $isVisibilityLocked = isset($post['visibilityLocked']) ? (bool)$post['visibilityLocked'] : false;
        $actions            =
            is_array($post['actions'] ?? null)
                ? $this->pastoralActionMapper->findByColumn('id', array_map('intval', array_filter($post['actions'])))
                : $this->pastoralActionMapper->createCollection();
        // Fetch the exiting rules
        if ($subType) {
            $existing = $this->pastoralAudienceMapper->findByColumnList(['subtype_id' => $subType->getId()]);
            if (!$this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $actions = $this->pastoralActionMapper->findForSubtype($subType);
            }
            $subType->setActions($actions);

            /* Save the "General Template" content
             */
            $subType->setTemplate($post['template']);
            $subType->setTemplatePrivate($post['templatePrivate']);
            $subType->setIcon($post['icon'] ?: null);
            if (in_array($post['sentiment'], Pastoral\Type\Model::SENTIMENT_VALUES)) {
                $subType->setSentiment($post['sentiment']);
            }
            $subType->setColor(!empty($post['color']) ? $this->colorFactory->build($post['color']) : null);
            $subType->setVisibilityLocked($isVisibilityLocked);
            $this->pastoralSubTypeMapper->save($subType);

            /* Reload this page
             */
            $redirect = $this->getRouteIfAuthorized('pastoralAudience.getAudiencesSubTypeModify', [
                'subtypeId' => $subType->getId()
            ]);
        } else {
            $existing = $this->pastoralAudienceMapper->findByColumnList(['type_id' => $type->getId()]);
            if (!$this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $actions = $this->pastoralActionMapper->findForType($type);
            }
            $type->setActions($actions);

            /* Save the "General Template" content
             */
            $type->setTemplate($post['template']);
            $type->setTemplatePrivate($post['templatePrivate']);
            $type->setIcon($post['icon'] ?: null);
            if (in_array($post['sentiment'], Pastoral\Type\Model::SENTIMENT_VALUES)) {
                $type->setSentiment($post['sentiment']);
            }
            $type->setColor(!empty($post['color']) ? $this->colorFactory->build($post['color']) : null);
            $type->setVisibilityLocked($isVisibilityLocked);
            $this->pastoralTypeMapper->save($type);

            /* Reload this page
             */
            $redirect = $this->getRouteIfAuthorized('pastoralAudience.getAudiencesTypeModify', [
                'typeId' => $type->getId(),
                'type' => $type
            ]);
        }

        // Build the posted rules
        $posted = $this->pastoralAudienceMapper->createCollection();

        // Build role and roletype-based rules
        foreach ($post["role"] ?? [] as $roleId => $accessLevel) {
            $audience = $this->pastoralAudienceMapper->createModel();
            $audience->setAccess((int)$accessLevel);
            $subType ? $audience->setSubtype($subType) : $audience->setType($type);
            if (preg_match('/^role-type-(?P<roleType>.*)$/', (string)$roleId, $matches)) {
                $audience->setRoleType($matches['roleType']);
            } else {
                $audience->setRole($this->roleMapper->findById($roleId));
                $audience->setAccess((int)$accessLevel);
            }

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

        /* TODO Reload the current page.
         *  Because getAudiences() now redirects you to the first meaningful record,
         *  after you save you'll be redirected to a whole different record than the
         *  one you just saw, which is a disorienting experience
         *
         * TODO Possibly replace this with an async toast
         */
        $this->response->redirect->to($redirect);
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
        return $this->pastoralAudienceMapper->findByPredicates($pb)->filter($this->pastoralAudienceMapper->getPermissionCallback());
    }
}
