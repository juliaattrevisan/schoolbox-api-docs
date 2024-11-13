<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Alaress\Color\ColorFactory;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Alaress\Web\Request\Values;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Translator\VisibilityTranslator;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Submenu\Flyout;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Audience\Model as PastoralAudience;

class Controller extends Base
{
    /**
     * Constructor
     *
     * @param UserMapper                 $userMapper
     * @param Pastoral\Group\Mapper      $pastoralGroupMapper
     * @param Mapper                     $pastoralAudienceMapper
     * @param RoleMapper                 $roleMapper
     * @param Pastoral\Type\Mapper       $pastoralTypeMapper
     * @param Pastoral\SubType\Mapper    $pastoralSubTypeMapper
     * @param Pastoral\Action\Mapper     $pastoralActionMapper
     * @param Pastoral\TypeAction\Mapper $pastoralTypeActionMapper
     * @param IconMapper                 $iconMapper
     * @param RosettaStone               $rosettaStone
     * @param ColorFactory               $colorFactory
     * @param Pastoral\Access\Mapper     $pastoralAccessMapper
     */
    public function __construct(
        private UserMapper $userMapper,
        private Pastoral\Group\Mapper $pastoralGroupMapper,
        private Mapper $pastoralAudienceMapper,
        private RoleMapper $roleMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
        private Pastoral\SubType\Mapper $pastoralSubTypeMapper,
        private Pastoral\Action\Mapper $pastoralActionMapper,
        private Pastoral\TypeAction\Mapper $pastoralTypeActionMapper,
        private IconMapper $iconMapper,
        private RosettaStone $rosettaStone,
        private ColorFactory $colorFactory,
        private Pastoral\Access\Mapper $pastoralAccessMapper
    ) {
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
                    $this->response = $this->redirectFound(
                        $this->getRouteIfAuthorized('pastoralAudience.getAudiencesSubTypeModify', [
                            'subtypeId' => $subtype->getId(),
                        ])
                    );

                    return;
                }
            }

            /* If there aren't any subtypes, find the first available type
             * and redirect to its modify screen
             */
            $this->response = $this->redirectFound(
                $this->getRouteIfAuthorized('pastoralAudience.getAudiencesTypeModify', [
                    'typeId' => $type->getId(),
                    'type' => $type,
                ])
            );

            return;
        }

        /* Otherwise if there are neither subtypes nor types, then you've
         * not configured the Pastoral Module sufficiently
         * Empty State
         */
        $this->response = $this->responseFromTemplate(
            '@pastoralAudience/audiences.twig',
            [],
            'Pastoral Group Management',
        );
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
            $url = $this->routeGenerator->generate('pastoralAudience.getAudiencesSubTypeModify', [
                'typeId' => $type->getId(),
                'subtypeId' => $subType->getId(),
            ]);
        } else {
            $url = $this->routeGenerator->generate('pastoralAudience.getAudiencesTypeModify', [
                'typeId' => $type->getId(),
            ]);
        }

        /* Set up flyout with special prefix breadcrumbs because we're in some deep
         * part of the system where the flyout doesn't represent the full app hierarchy
         */
        $pastoralCareProper = $this->rosettaStone->translate('pastoralCare', false, true);

        /**
         * both pastoral Write and Moderators access this page, and they can access via different directions.
         * we should try to map the breadcrumbs to the most logical starting point
         * and build the sidenav with admin if they have access
         *
         * write > Pastoral home
         * write+ > Administration Home
         */
        $adminRoute = $this->getRouteIfAuthorized('adminHome.getIndex');
        $firstBreadcrumb = [$pastoralCareProper, $this->routeGenerator->generate('pastoral.getIndex')];
        if ($adminRoute) {
            $firstBreadcrumb = ['Administration', '/adminv2/home'];
        }

        $flyout = (new Flyout(
            $this->getConfigLoader(),
            new FlyoutNodes($this->routeGenerator, $this->pastoralTypeMapper)
        ))->prefixBreadcrumbs([
            $firstBreadcrumb,
            ["Manage {$pastoralCareProper} Record Settings", $this->routeGenerator->generate('pastoralAudience.getAudiences')],
        ]);
        if ($adminRoute) {
            $flyout->injectParentEntry([
                $adminRoute => [
                    'name' => 'Back to Administration',
                    'visible' => true,
                    'hasBreadcrumbLink' => true,
                    'description' => '',
                    'children' => [],
                ]
            ]);
        }

        // Figure out a background color based on the record's color
        // (default color: black)
        $foregroundColor = ($subType ? $subType->getColor() : $type->getColor());
        $transformer     = new ColorHelper();
        $backgroundColor = ($foregroundColor === null ? null : $transformer->calculateBackground($foregroundColor));

        $this->response = $this->responseFromTemplate(
            '@pastoralAudience/audiences_modify.twig',
            [
                'flyout'          => $flyout->generateVars($url),
                'breadcrumbs'     => $flyout->generateBreadcrumbs($url),
                'record'          => $record,
                'allowIndividual' => $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_INDIVIDUAL_ACCESS_ENABLED),
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
                'typeActions'     => $subType ? $subType->getTypeActions() : $type->getTypeActions(),
                'icons'           => $this->iconMapper->findAll()->sortNaturally('title'),
                'sentiments'      => Pastoral\Type\TypeAbstract::SENTIMENT_VALUES,
                'flagColors'  => [
                    'foreground' => ($foregroundColor === null) ? null : $foregroundColor->getAsRgba(),
                    'background' => ($backgroundColor === null) ? null : $backgroundColor->getAsRgba(),
                    'baseLight'  => ColorHelper::BASE_BACKGROUND_COLOR_LIGHT,
                    'baseDark'   => ColorHelper::BASE_BACKGROUND_COLOR_DARK,
                ],
            ],
            'Pastoral Group Management',
        );
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
        $this->postAudienceModify($type, null, $this->requestPost);
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
        $this->postAudienceModify($subType->getPastoralType(), $subType, $this->requestPost);
    }


    /**
     * Update default audience for a pastoral Type or SubType
     *
     * @param Pastoral\Type\Model         $type
     * @param Pastoral\SubType\Model|null $currentType
     * @param Values                      $post
     * @return void
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function postAudienceModify(Pastoral\Type\Model $type, ?Pastoral\SubType\Model $currentType, Values $post): void
    {
        $isVisibilityLocked = $post->getBool('visibilityLocked', false);

        $this->pastoralTypeMapper->getDatabase()->beginTransaction();

        /** @var Pastoral\Type\TypeAbstract $currentType */
        $currentType = $currentType ?? $type;
        $isSubType   = $currentType instanceof Pastoral\SubType\Model;

        // Fetch the exiting rules
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            $actions          =
                is_array($post->get('actions'))
                    ? $this->pastoralActionMapper->findByColumn('id', array_map('intval', array_filter($post['actions'])))
                    : $this->pastoralActionMapper->createCollection();
            $defaultActionIds = is_array($post->get('defaultActions')) ? array_map('intval', array_filter($post->get('defaultActions'))) : [];

            // remove all the current actions
            $this->pastoralTypeActionMapper->deleteByColumn($isSubType ? 'subtype_id' : 'type_id', $currentType->getId());
            $currentType->setTypeActions($this->pastoralTypeActionMapper->createCollection());

            /** @var Pastoral\Action\Model $action */
            foreach ($actions as $action) {
                $typeAction = (new Pastoral\TypeAction\Model())
                    ->setAction($action)
                    ->setDefault(in_array($action->getId(), $defaultActionIds));
                if ($isSubType) {
                    $typeAction->setSubtypeId($currentType->getId());
                } else {
                    $typeAction->setTypeId($currentType->getId());
                }
                $currentType->getTypeActions()->add($typeAction);
            }
            $this->pastoralTypeActionMapper->save($currentType->getTypeActions());
        }

        /* Save the "General Template" content
         */
        $currentType->setTemplate($post['template']);
        $currentType->setTemplatePrivate($post['templatePrivate']);
        $currentType->setIcon($post['icon'] ?: null);
        if (in_array($post['sentiment'], Pastoral\Type\TypeAbstract::SENTIMENT_VALUES)) {
            $currentType->setSentiment($post['sentiment']);
        }
        $currentType->setColor(!empty($post['color']) ? $this->colorFactory->build($post['color']) : null);
        $currentType->setVisibilityLocked($isVisibilityLocked);
        if ($isSubType) {
            $this->pastoralSubTypeMapper->save($currentType);
        } else {
            $this->pastoralTypeMapper->save($currentType);
        }

        /* Reload this page
         */
        $redirect = $isSubType
            ? $this->getRouteIfAuthorized('pastoralAudience.getAudiencesSubTypeModify', [
                'subtypeId' => $currentType->getId(),
            ])
            : $this->getRouteIfAuthorized('pastoralAudience.getAudiencesTypeModify', [
                'typeId' => $currentType->getId(),
                'type'   => $currentType,
            ]);

        // Build the posted rules
        $postedAudiences = (new VisibilityTranslator(
            $this->pastoralAccessMapper,
            $this->pastoralGroupMapper,
            $this->userMapper,
            PastoralAudience::class
        ))->prepareAccesses($post, $currentType);

        // Save audiences
        $existingAudiences = $isSubType
            ? $this->pastoralAudienceMapper->findByColumnList(['subtype_id' => $currentType->getId()])->indexBy('id')
            : $this->pastoralAudienceMapper->findByColumnList(['type_id' => $currentType->getId()])->indexBy('id');
        $keptAudienceIds   = [];
        /** @var PastoralAudience $audience */
        foreach ($postedAudiences as $audience) {
            if ($existingAudiences->offsetExists($audience->getId())) {
                $keptAudienceIds[] = $audience->getId();
            }
            $newAudience = clone $audience;
            $this->pastoralAudienceMapper->save($newAudience);
        }
        // Delete audiences no longer exists
        $removingAudiences = $existingAudiences->filter(
            static function ($audience) use ($keptAudienceIds) {
                /** @var PastoralAudience $audience */
                return !in_array($audience->getId(), $keptAudienceIds);
            }
        );
        $this->pastoralAudienceMapper->delete($removingAudiences);

        $this->pastoralTypeMapper->getDatabase()->commit();

        /* TODO Reload the current page.
         *  Because getAudiences() now redirects you to the first meaningful record,
         *  after you save you'll be redirected to a whole different record than the
         *  one you just saw, which is a disorienting experience
         *
         * TODO Possibly replace this with an async toast
         */
        $this->response = $this->redirectFound($redirect);
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
