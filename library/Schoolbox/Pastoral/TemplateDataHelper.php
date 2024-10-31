<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

/**
 * Helper class for loading data to be passed to a Twig template for the
 * pastoral record form.
 */
class TemplateDataHelper
{
    /**
     * Constructor
     *
     * @param IConfigLoader            $config
     * @param Pastoral\SubType\Mapper  $pastoralSubTypeMapper
     * @param Pastoral\Type\Mapper     $pastoralTypeMapper
     * @param Pastoral\Severity\Mapper $pastoralSeverityMapper
     * @param RoleMapper               $roleMapper
     * @param Pastoral\Access\Mapper   $pastoralAccessMapper
     * @param Pastoral\Group\Mapper    $pastoralGroupMapper
     * @param UserMapper               $userMapper
     * @param Pastoral\Audience\Mapper $pastoralAudienceMapper
     */
    public function __construct(
        private IConfigLoader $config,
        private Pastoral\SubType\Mapper $pastoralSubTypeMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
        private Pastoral\Severity\Mapper $pastoralSeverityMapper,
        private RoleMapper $roleMapper,
        private Pastoral\Access\Mapper $pastoralAccessMapper,
        private Pastoral\Group\Mapper $pastoralGroupMapper,
        private UserMapper $userMapper,
        private Pastoral\Audience\Mapper $pastoralAudienceMapper,
    ) {
    }

    /**
     * Load all template data pastoral forms need, except for:
     * * record
     *
     * @return array
     */
    public function loadTemplateData(): array
    {
        $allowIndividual = $this->config->get(ConfigMapper::NAME_PASTORAL_INDIVIDUAL_ACCESS_ENABLED);
        $allowParents    = $this->config->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED);
        $allowStudents   = $this->config->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED);

        // Load roles and empty counts to display on pastoral visibility
        $roles          = $this->loadRoles((bool)$allowParents);
        $roleUserCounts = $this->loadRoleUserCounts($roles);

        // Requirements for updating selections on change
        $types            = $this->pastoralTypeMapper->findAll()->indexBy('id');
        $subtypes         = $this->pastoralSubTypeMapper->findAll()->indexBy('id');
        $audiences        = $this->loadAudiences();
        $typeAudiences    = $this->loadTypeAudiences($audiences);
        $subtypeAudiences = $this->loadSubtypeAudiences($audiences);

        $typesIndex           = $types->map(fn (Pastoral\Type\Model $model) => $model->getTemplate());
        $typesIndexPrivate    = $types->map(fn (Pastoral\Type\Model $model) => $model->getTemplatePrivate());
        $subtypesIndex        = $subtypes->map(fn (Pastoral\SubType\Model $model) => $model->getTemplate());
        $subtypesIndexPrivate = $subtypes->map(fn (Pastoral\SubType\Model $model) => $model->getTemplatePrivate());

        $audienceDefaults = $this->loadTypeAndSubtypeDefaults($types, $typeAudiences, $subtypes, $subtypeAudiences);

        return [
            // Base form requirements
            'types'      => $types,
            'subTypes'   => $subtypes,
            'severities' => $this->config->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)
                ? $this->pastoralSeverityMapper->findAll()
                : [],

            // Requirements for displaying visibility inputs
            'allowIndividual' => $allowIndividual,
            'allowParents'    => $allowParents,
            'allowStudents'   => $allowStudents,
            'roles'           => $roles,
            'roleTypes'       => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
            'groups'          => $this->pastoralGroupMapper->findAll()->sortNaturally('name'),
            'roleUserCounts'  => $roleUserCounts,
            'moderatorIds'    => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)
                                                  ->getPropertyValues('id'),

            // Requirements for displaying scripts
            'audiences'            => $audienceDefaults,
            'typesIndexPrivate'    => $typesIndexPrivate,
            'typesIndex'           => $typesIndex,
            'subtypesIndexPrivate' => $subtypesIndexPrivate,
            'subtypesIndex'        => $subtypesIndex,
        ];
    }

    /**
     * @param bool $allowParentRoles
     * @return GenericCollection<Role>
     */
    private function loadRoles(bool $allowParentRoles): GenericCollection
    {
        $roles        = $this->roleMapper->createCollection();
        $allowedRoles = $this->pastoralAccessMapper->determineAllowedRoles();
        foreach ($allowedRoles as $roleCurrent) {
            if ($roleCurrent->isStaff() && $roleCurrent->getAccessPastoral()) {
                $roles[] = $roleCurrent;
            }
            if ($allowParentRoles && $roleCurrent->isParent()) {
                $roles[] = $roleCurrent;
            }
        }
        return $roles;
    }

    /**
     * @param GenericCollection<Role> $roles
     * @return array
     */
    private function loadRoleUserCounts(GenericCollection $roles): array
    {
        $roleUserCounts = [];
        foreach ($roles as $roleCurrent) {
            // This depends on whether the role count is actually shown.
            if ($roleCurrent->isStaff()) {
                $roleUserCounts[$roleCurrent->getId()] = $this->userMapper->countByColumn('group_id', $roleCurrent->getId());
            } else {
                $roleUserCounts[$roleCurrent->getId()] = 0;
            }
        }
        return $roleUserCounts;
    }

    /**
     * @return GenericCollection<Pastoral\Audience\Model>
     */
    private function loadAudiences(): GenericCollection
    {
        return $this->pastoralAudienceMapper->findAll()->filter($this->pastoralAudienceMapper->getPermissionCallback());
    }

    /**
     * @param GenericCollection<Pastoral\Audience\Model> $audiences
     * @return array<Pastoral\Audience\Model>
     */
    private function loadTypeAudiences(GenericCollection $audiences): array
    {
        $typeAudiences = [];
        foreach ($audiences as $audience) {
            if (!$audience->getSubtype()) {
                $typeAudiences[$audience->getType()->getId()][] = $audience;
            }
        }
        return $typeAudiences;
    }

    /**
     * @param GenericCollection<Pastoral\Audience\Model> $audiences
     * @return array<Pastoral\Audience\Model>
     */
    private function loadSubtypeAudiences(GenericCollection $audiences): array
    {
        $subtypeAudiences = [];
        foreach ($audiences as $audience) {
            if ($audience->getSubtype()) {
                $subtypeAudiences[$audience->getSubtype()->getId()][] = $audience;
            }
        }
        return $subtypeAudiences;
    }

    /**
     * @param GenericCollection<Pastoral\Type\Model>    $types
     * @param array<int, Pastoral\Audience\Model>       $typeAudiences
     * @param GenericCollection<Pastoral\SubType\Model> $subtypes
     * @param array<int, Pastoral\Audience\Model>       $subtypeAudiences
     * @return array
     */
    private function loadTypeAndSubtypeDefaults(
        GenericCollection $types,
        array $typeAudiences,
        GenericCollection $subtypes,
        array $subtypeAudiences,
    ): array {
        $audienceDefaults = [];
        foreach ($types as $type) {
            $typesIndex[$type->getId()]        = $type->getTemplate();
            $typesIndexPrivate[$type->getId()] = $type->getTemplatePrivate();
            if (key_exists($type->getId(), $typeAudiences) || $type->getVisibilityLocked()) {
                $audienceDefaults[] = [
                    'typeId'    => $type->getId(),
                    'subtypeId' => 0,
                    'audiences' => $typeAudiences[$type->getId()] ?? [],
                ];
            }
        }

        foreach ($subtypes as $subtype) {
            $subtypesIndex[$subtype->getId()]        = $subtype->getTemplate();
            $subtypesIndexPrivate[$subtype->getId()] = $subtype->getTemplatePrivate();
            if (key_exists($subtype->getId(), $subtypeAudiences) || $subtype->getVisibilityLocked()) {
                $audienceDefaults[] = [
                    'typeId'    => $subtype->getPastoralType()->getId(),
                    'subtypeId' => $subtype->getId(),
                    'audiences' => $subtypeAudiences[$subtype->getId()] ?? [],
                ];
            }
        }
        return $audienceDefaults;
    }
}
